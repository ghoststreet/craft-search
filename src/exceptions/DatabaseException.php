<?php

namespace ghoststreet\craftaisearch\exceptions;

use ghoststreet\craftaisearch\AiSearch;
use PDOException;
use Throwable;

/**
 * Thrown when database operations fail in the AI Search plugin.
 * Use instead of returning null/0 to make errors explicit and distinguishable from empty results.
 */
class DatabaseException extends AiSearchException
{
    /**
     * Create exception for query failures.
     */
    public static function queryFailed(string $operation, Throwable $previous): self
    {
        if ($previous instanceof PDOException && ($previous->getCode() === '42P01' || str_contains($previous->getMessage(), 'SQLSTATE[42P01]'))) {
            $settings = AiSearch::getInstance()?->getSettings();
            $table = $settings ? "{$settings->vectorsSchemaName}.{$settings->vectorsTableName}" : 'vector table';
            $friendly = "The vector table \"{$table}\" does not exist yet. Set up the pgvector schema before indexing — see the plugin README.";
            $e = new self($friendly, 0, $previous);
            $e->httpStatus = 503;
            return $e->setUserMessage($friendly);
        }

        $e = new self(
            "Database query failed in {$operation}: {$previous->getMessage()}",
            0,
            $previous
        );
        return $e->setUserMessage('A database query failed. The administrator can find the technical details in the AI Search log.');
    }

    /**
     * Create exception for schema initialization failures.
     */
    public static function schemaInitFailed(Throwable $previous): self
    {
        $e = new self(
            "Failed to initialize PostgreSQL schema: {$previous->getMessage()}",
            0,
            $previous
        );
        return $e->setUserMessage('Failed to initialize the pgvector schema. See the plugin README for setup instructions.');
    }

    /**
     * Create exception for incomplete configuration.
     */
    public static function configurationIncomplete(array $missingFields): self
    {
        $fieldList = implode(', ', $missingFields);
        $e = new self("PostgreSQL configuration incomplete. Missing: {$fieldList}");
        $e->httpStatus = 503;
        return $e->setUserMessage("Database connection is not configured. Missing: {$fieldList}.");
    }

    /**
     * Create exception for connection errors with PDO details.
     */
    public static function connectionError(string $message, ?Throwable $previous = null): self
    {
        $e = new self(
            "PostgreSQL connection error: {$message}",
            0,
            $previous
        );
        $e->httpStatus = 503;
        return $e->setUserMessage('Could not connect to the vector database. Check host, credentials, and SSL mode.');
    }
}
