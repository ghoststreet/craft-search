<?php

namespace ghoststreet\craftaisearch\services;

use ghoststreet\craftaisearch\AiSearch;
use ghoststreet\craftaisearch\exceptions\DatabaseException;
use ghoststreet\craftaisearch\helpers\Logger;
use PDO;
use PDOException;
use yii\base\Component;

/**
 * Database Service for connecting to the pgvector-backed PostgreSQL database.
 *
 * Handles connection management (including URI parsing and IPv4 resolution
 * for cloud providers) and CRUD/query helpers on the admin-managed vectors
 * table. The plugin does NOT create or modify the schema — the admin runs the
 * SQL from the README before configuring this service.
 */
class DatabaseService extends Component
{
    /** Cache key used to skip repeated preflight checks within a single deploy. */
    public const SCHEMA_CACHE_KEY = 'aisearch_schema_initialized';

    private const LOCAL_HOSTS = ['127.0.0.1', '::1', 'localhost'];

    private ?PDO $connection = null;

    /**
     * Get database connection, throwing an exception if not configured or connection fails.
     *
     * @throws DatabaseException If configuration is incomplete, SSL policy fails, or connection fails
     */
    public function getConnection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $config = $this->resolveConnectionConfig();
        $missingFields = $this->getMissingConfigFields($config);

        if (!empty($missingFields)) {
            throw DatabaseException::configurationIncomplete($missingFields);
        }

        $this->enforceSslPolicy($config);

        $dsn = $this->buildDsn($config);

        return $this->createConnection($dsn, $config['user'], $config['password']);
    }

    /**
     * Return the validated, fully-qualified vectors table identifier (`"schema"."table"`).
     */
    public function getQualifiedTable(): string
    {
        return AiSearch::getInstance()->getSettings()->getQualifiedVectorsTable();
    }

    /**
     * Reject `disable` / `allow` / `prefer` SSL modes for non-localhost hosts.
     *
     * @throws DatabaseException
     */
    private function enforceSslPolicy(array $config): void
    {
        $host = (string)($config['host'] ?? '');
        if (in_array($host, self::LOCAL_HOSTS, true)) {
            return;
        }

        $weak = ['disable', 'allow', 'prefer'];
        if (in_array($config['sslMode'], $weak, true)) {
            throw DatabaseException::connectionError(
                "Refusing to connect to remote host '{$host}' with sslmode='{$config['sslMode']}'. " .
                "Use 'require', 'verify-ca', or 'verify-full'."
            );
        }
    }

    /**
     * Resolve connection configuration from plugin settings, parsing a connection URI
     * if the host field contains one, otherwise using individual field values.
     *
     * @return array{host: ?string, port: int|string, database: ?string, user: ?string, password: ?string, sslMode: string}
     */
    private function resolveConnectionConfig(): array
    {
        $settings = AiSearch::getInstance()->getSettings();

        $host = $settings->getPostgresqlHost();
        $port = $settings->getPostgresqlPort();
        $database = $settings->getPostgresqlDatabase();
        $user = $settings->getPostgresqlUser();
        $password = $settings->getPostgresqlPassword();
        $sslMode = $settings->getPostgresqlSslMode();

        if (!empty($host) && $this->isConnectionUri($host)) {
            $parsedConnectionUri = $this->parseConnectionUri($host);

            if ($parsedConnectionUri) {
                return [...$parsedConnectionUri, 'sslMode' => $sslMode];
            }

            Logger::warning('Failed to parse connection URI');
        }

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'user' => $user,
            'password' => $password,
            'sslMode' => $sslMode,
        ];
    }

    /**
     * Get list of missing required configuration fields.
     *
     * @return string[] Missing field names, empty if config is complete
     */
    private function getMissingConfigFields(array $config): array
    {
        $missing = [];

        foreach (['host', 'database', 'user', 'password'] as $field) {
            if (empty($config[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Check if a host string is a full PostgreSQL connection URI.
     */
    private function isConnectionUri(string $host): bool
    {
        return str_starts_with($host, 'postgresql://') || str_starts_with($host, 'postgres://');
    }

    /**
     * Build a PDO DSN string from the resolved connection config, preferring
     * `hostaddr` (IPv4) over `host` (hostname) for cloud provider compatibility.
     */
    private function buildDsn(array $config): string
    {
        $hostaddr = $this->resolveHostAddress($config['host']);

        if ($hostaddr) {
            return sprintf(
                'pgsql:hostaddr=%s;port=%d;dbname=%s;sslmode=%s',
                $hostaddr,
                $config['port'],
                $config['database'],
                $config['sslMode']
            );
        }

        Logger::warning('Could not resolve IPv4 address, using hostname', ['host' => $config['host']]);

        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['sslMode']
        );
    }

    /**
     * Resolve hostname to IPv4 for the PDO DSN `hostaddr` parameter.
     *
     * Some cloud PostgreSQL providers (e.g. Neon, Supabase) require IPv4
     * addresses instead of hostnames due to libpq SNI/SSL handshake issues.
     * Returns null if resolution fails, causing buildDsn() to fall back to hostname.
     */
    private function resolveHostAddress(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }

        $records = @dns_get_record($host, DNS_A);

        if (!empty($records) && isset($records[0]['ip']) &&
            filter_var($records[0]['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $records[0]['ip'];
        }

        $resolved = gethostbyname($host);

        if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $resolved;
        }

        return null;
    }

    /**
     * @throws DatabaseException If connection fails
     */
    private function createConnection(string $dsn, string $user, string $password): PDO
    {
        try {
            $this->connection = new PDO($dsn, $user, $password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->exec('SET hnsw.ef_search = 20');

            Logger::info('Successfully connected to PostgreSQL database');

            return $this->connection;
        } catch (PDOException $e) {
            Logger::exception($e, 'getConnection');
            throw DatabaseException::connectionError($e->getMessage(), $e);
        }
    }

    /**
     * Sets the `app.site_id` GUC the README's RLS policy reads. Without RLS enabled
     * by the admin this is inert.
     */
    public function bindSiteScope(?int $siteId): void
    {
        if ($siteId === null) {
            return;
        }

        try {
            $db = $this->getConnection();
            $stmt = $db->prepare("SELECT set_config('app.site_id', :siteId, true)");
            $stmt->execute([':siteId' => (string)$siteId]);
        } catch (PDOException $e) {
            Logger::exception($e, 'bindSiteScope', ['siteId' => $siteId]);
        }
    }

    /**
     * Verify that the configured vectors table exists. The plugin never issues
     * DDL — admin owns the schema and must run the README SQL before this call.
     *
     * @throws DatabaseException If the table is missing or the lookup fails
     */
    public function preflightSchema(): void
    {
        $settings = AiSearch::getInstance()->getSettings();
        $schema = $settings->vectorsSchemaName;
        $table = $settings->vectorsTableName;

        try {
            $db = $this->getConnection();
            $stmt = $db->prepare('SELECT 1 FROM pg_tables WHERE schemaname = :schema AND tablename = :table');
            $stmt->execute([':schema' => $schema, ':table' => $table]);

            if ($stmt->fetch() === false) {
                throw DatabaseException::connectionError(sprintf(
                    'Vectors table "%s"."%s" does not exist. Run the schema SQL from the plugin README to create it.',
                    $schema,
                    $table
                ));
            }
        } catch (PDOException $e) {
            Logger::exception($e, 'preflightSchema');
            throw DatabaseException::connectionError($e->getMessage(), $e);
        }
    }

    /**
     * Non-throwing wrapper around preflightSchema() for CP dashboards that need
     * to render even when the vectors table is missing or the DB is unreachable.
     */
    public function isSchemaInitialized(): bool
    {
        try {
            $this->preflightSchema();
            return true;
        } catch (DatabaseException) {
            return false;
        }
    }

    /**
     * Delete all vectors while preserving the table structure and indexes.
     *
     * @return int Number of deleted rows
     * @throws DatabaseException If connection fails or query fails
     */
    public function clearAllVectors(): int
    {
        $db = $this->getConnection();
        $table = $this->getQualifiedTable();

        try {
            $stmt = $db->prepare("DELETE FROM {$table}");
            $stmt->execute();
            $count = $stmt->rowCount();

            Logger::info('Cleared all vectors', ['count' => $count]);

            return $count;
        } catch (PDOException $e) {
            Logger::exception($e, 'clearAllVectors');
            throw DatabaseException::queryFailed('clearAllVectors', $e);
        }
    }

    /**
     * Fetch dashboard statistics (entry count, chunk count, last indexed date) with connection status.
     *
     * @return array<string, array{chunkCount: int, lastIndexed: string}>
     * @throws DatabaseException
     */
    public function getIndexedSummary(?int $siteId = null): array
    {
        $db = $this->getConnection();
        $table = $this->getQualifiedTable();

        try {
            $sql = "
                SELECT \"elementId\", \"siteId\", COUNT(*) AS \"chunkCount\", MAX(\"dateUpdated\") AS \"lastIndexed\"
                FROM {$table}";
            $params = [];
            if ($siteId !== null) {
                $sql .= ' WHERE "siteId" = :siteId';
                $params[':siteId'] = $siteId;
            }
            $sql .= ' GROUP BY "elementId", "siteId"';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $map = [];
            while ($row = $stmt->fetch()) {
                $key = $row['elementId'] . '-' . $row['siteId'];
                $map[$key] = [
                    'chunkCount' => (int)$row['chunkCount'],
                    'lastIndexed' => $row['lastIndexed'],
                ];
            }
            return $map;
        } catch (PDOException $e) {
            Logger::exception($e, 'getIndexedSummary');
            throw DatabaseException::queryFailed('getIndexedSummary', $e);
        }
    }

    /**
     * @throws DatabaseException
     */
    public function getVectorsForElement(int $elementId, int $siteId): array
    {
        $db = $this->getConnection();
        $table = $this->getQualifiedTable();

        try {
            $stmt = $db->prepare("
                SELECT \"chunkIndex\", \"totalChunks\", content, \"dateUpdated\"
                FROM {$table}
                WHERE \"elementId\" = :elementId AND \"siteId\" = :siteId
                ORDER BY \"chunkIndex\" ASC
            ");
            $stmt->execute([':elementId' => $elementId, ':siteId' => $siteId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            Logger::exception($e, 'getVectorsForElement');
            throw DatabaseException::queryFailed('getVectorsForElement', $e);
        }
    }

    public function getStats(bool $useCache = true): array
    {
        $cache = \Craft::$app->getCache();
        $cacheKey = 'aisearch_dashboard_stats';

        if ($useCache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $db = $this->getConnection();
        $table = $this->getQualifiedTable();

        try {
            $stmt = $db->query("
                SELECT
                    COUNT(DISTINCT \"elementId\") AS \"entryCount\",
                    COUNT(*) AS \"chunkCount\",
                    MAX(\"dateUpdated\") AS \"lastIndexed\"
                FROM {$table}
            ");
            $row = $stmt->fetch();

            $stats = [
                'entryCount' => (int)($row['entryCount'] ?? 0),
                'chunkCount' => (int)($row['chunkCount'] ?? 0),
                'lastIndexed' => $row['lastIndexed'] ?? null,
                'isConnected' => true,
                'error' => null,
            ];
        } catch (PDOException $e) {
            Logger::exception($e, 'getStats');
            throw DatabaseException::queryFailed('getStats', $e);
        }

        $cache->set($cacheKey, $stats, 60);

        return $stats;
    }

    /**
     * Like getStats(), but returns a canonical disconnected-shape on failure
     * instead of throwing. Use from CP pages that need to render even when
     * the vector DB is unreachable.
     */
    public function getStatsSafe(bool $useCache = true): array
    {
        try {
            return $this->getStats($useCache);
        } catch (DatabaseException $e) {
            return [
                'entryCount' => 0,
                'chunkCount' => 0,
                'lastIndexed' => null,
                'isConnected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse a PostgreSQL connection URI (postgresql://user:pass@host:port/db) into
     * its component parts. Returns null if the URI does not match the expected format.
     *
     * @return array{user: string, password: string, host: string, port: int, database: string}|null
     */
    private function parseConnectionUri(string $uri): ?array
    {
        $rawUri = $uri;

        $uri = preg_replace('/^postgres(ql)?:\/\//', '', $uri);

        if (!preg_match('/^([^:]+):([^@]+)@([^:\/]+):?(\d+)?\/(.+)$/', $uri, $matches)) {
            Logger::warning('Failed to parse PostgreSQL connection URI', [
                'expected' => 'postgresql://user:password@host:port/database',
                'received' => preg_replace('/\/\/[^@]*@/', '//[redacted]@', $rawUri),
            ]);

            return null;
        }

        return [
            'user' => urldecode($matches[1]),
            'password' => urldecode($matches[2]),
            'host' => $matches[3],
            'port' => !empty($matches[4]) ? (int)$matches[4] : 5432,
            'database' => $matches[5],
        ];
    }
}
