# AI Search for Craft CMS


**AI-powered semantic, hybrid, and RAG search for Craft CMS.**

---

> **Important: PostgreSQL with pgvector is REQUIRED**
>
> This plugin requires PostgreSQL with the [pgvector](https://github.com/pgvector/pgvector) extension installed. **MySQL and SQLite are NOT supported.** This is a hard requirement due to the vector similarity search capabilities that power semantic search.

---

## Overview

AI Search brings intelligent, AI-powered search capabilities to your Craft CMS site. Instead of relying solely on keyword matching, AI Search understands the *meaning* behind queries and content, delivering more relevant results.

**Four search types included:**

- **Semantic Search** - Find content by meaning using OpenAI embeddings and pgvector similarity search
- **BM25 Full-Text Search** - Traditional keyword search using PostgreSQL's native full-text ranking
- **Hybrid Search (RRF)** - Best of both worlds with Reciprocal Rank Fusion algorithm combining semantic and keyword signals
- **RAG Search** - AI-generated summaries with source citations using Retrieval Augmented Generation

## Key Features

- **Automatic indexing** - Entries are indexed automatically when saved
- **Smart semantic chunking** - Long content is intelligently split for optimal embedding quality
- **All field types supported** - Works with plain text, CKEditor, Matrix, Super Table, and nested fields
- **Multi-level caching** - Request-level and persistent caching (7-day TTL) for embeddings
- **Configurable algorithms** - Fine-tune similarity thresholds, RRF weights, and ranking parameters
- **Console commands** - Bulk index your entire site from the command line
- **RESTful API** - Three API endpoints for different search needs
- **Control Panel dashboard** - View indexing statistics and manage your search index
- **Multi-site support** - Filter search results by site

## Requirements

| Requirement | Version |
|-------------|---------|
| Craft CMS | 4.0+ or 5.0+ |
| PHP | 8.2+ |
| PostgreSQL | 12+ with [pgvector](https://github.com/pgvector/pgvector) extension |
| OpenAI API | Valid API key ([pricing](https://openai.com/pricing)) |

> **Note:** This plugin requires an OpenAI API key. OpenAI API usage incurs separate costs based on your usage volume.

### PostgreSQL & pgvector Setup

The pgvector extension must be installed on your PostgreSQL server. See the [pgvector installation guide](https://github.com/pgvector/pgvector#installation) for instructions.

Most managed PostgreSQL providers (Neon, Supabase, Railway, Render, AWS RDS) offer pgvector as a one-click extension.

## Installation

### Via Plugin Store (Recommended)

1. Go to the Plugin Store in your project's Control Panel
2. Search for "AI Search"
3. Click "Install"

### Via Composer

```bash
# Navigate to your Craft project
cd /path/to/my-project

# Install the plugin
composer require ghost-street/craft-ai-search

# Install in Craft
./craft plugin/install ai-search
```

### Getting Started

1. Ensure PostgreSQL has the [pgvector](https://github.com/pgvector/pgvector) extension enabled
2. Navigate to **AI Search** in the Control Panel sidebar
3. Go to **API Keys** and enter your OpenAI API key
4. Go to **Database** and configure your PostgreSQL connection
5. Go to **Data Sync** and click "Wipe & Re-index" to build your initial index
6. Test your search:
   ```bash
   curl "https://your-site.com/api/hybrid-search?q=your+search+query"
   ```

## Search Types Explained

### Semantic Search

Semantic search uses OpenAI embeddings to understand the meaning of both your query and your content. Instead of matching keywords, it finds content that is conceptually similar to what you're asking for.

**Supported embedding models:**
- `text-embedding-3-small` (default) - Fast and cost-effective
- `text-embedding-3-large` - Higher accuracy

**Best for:** Natural language queries, conceptual searches, "find content like this"

### BM25 Full-Text Search

BM25 (Best Matching 25) is a traditional keyword-based ranking algorithm using PostgreSQL's native full-text search with `ts_rank_cd`. It excels at finding exact term matches.

**Best for:** Known keywords, exact phrases, technical terms

### Hybrid Search (RRF)

Hybrid search combines semantic and BM25 results using the Reciprocal Rank Fusion algorithm. This gives you the best of both worlds - conceptual understanding plus keyword precision.

**How it works:**
1. Query is sent to both semantic and BM25 search
2. Results are ranked using RRF formula: `score = weight / (k + rank)`
3. Scores are combined with configurable weights (default: 30% semantic, 70% BM25)
4. Single-signal penalty applied to results appearing in only one search type

**Best for:** General-purpose search, production use cases

### RAG Search

RAG search performs hybrid search first, then passes the top results to an OpenAI language model to generate a contextual summary with source citations.

**Supported LLM models:**
- `gpt-5-nano` (default) - Fast and efficient
- `gpt-4o-mini` - Balanced performance
- `gpt-4o` - Highest quality
- `gpt-4-turbo` - High quality with larger context
- `gpt-3.5-turbo` - Legacy option

**Best for:** Question answering, summarization, chatbot integrations

## API Reference

All endpoints accept GET requests with the following common parameters:

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `q` | string | Yes | - | The search query |
| `limit` | integer | No | 10 (5 for RAG) | Maximum results to return |
| `siteId` | integer | No | Current site | Filter results by site ID |

### GET /api/hybrid-search

Performs hybrid search combining semantic and BM25 signals.

**Response:**
```json
{
  "success": true,
  "query": "your search query",
  "hybrid": true,
  "semanticResults": [
    {
      "id": 123,
      "title": "Entry Title",
      "url": "https://site.com/entry-slug",
      "excerpt": "Relevant content excerpt...",
      "score": 0.85,
      "similarity": 0.92
    }
  ],
  "semanticCount": 5
}
```

### GET /api/craft-search

Performs native Craft CMS search (keyword-based).

**Response:**
```json
{
  "success": true,
  "query": "your search query",
  "results": [
    {
      "id": 123,
      "title": "Entry Title",
      "url": "https://site.com/entry-slug"
    }
  ],
  "count": 5
}
```

### GET /api/rag-search

Performs AI-powered search with generated summary.

**Response:**
```json
{
  "success": true,
  "query": "your search query",
  "summary": "Based on your content, here is what I found...",
  "sources": [
    {
      "id": 123,
      "title": "Entry Title",
      "url": "https://site.com/entry-slug"
    }
  ],
  "count": 3,
  "confidence": 0.87
}
```

## Console Commands

### Index All Entries

```bash
./craft ai-search/index
```

### Index Specific Section

```bash
./craft ai-search/index --section=blog
```

### Index Specific Site

```bash
./craft ai-search/index --siteId=1
```

### Combined Options

```bash
./craft ai-search/index --section=news --siteId=2
```

The index command will:
1. Initialize the database schema if needed
2. Wipe existing vectors (full re-index)
3. Process all matching entries in batches
4. Report progress and any failures

## Configuration

All settings are configurable via the Control Panel under **AI Search**.

### Database Settings

| Setting | Default | Description |
|---------|---------|-------------|
| PostgreSQL Host | - | Database host (supports connection URIs) |
| PostgreSQL Port | `5432` | Database port |
| PostgreSQL Database | - | Database name |
| PostgreSQL User | - | Database username |
| PostgreSQL Password | - | Database password |
| SSL Mode | `require` | SSL connection mode (disable, allow, prefer, require, verify-ca, verify-full) |

All database settings support environment variables using Craft's `$VARIABLE_NAME` syntax.

### Embedding Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Hybrid Embedding Model | `text-embedding-3-small` | Model for hybrid search embeddings |
| RAG Embedding Model | `text-embedding-3-small` | Model for RAG search embeddings |
| Cache TTL | `604800` (7 days) | Embedding cache duration in seconds |

### Hybrid Search Settings

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| Minimum Similarity Threshold | `0.90` | 0-1 | Minimum cosine similarity for results |
| RRF K Parameter | `60` | 1-1000 | Reciprocal Rank Fusion constant |
| Semantic Weight | `0.3` | 0-1 | Weight for semantic results |
| BM25 Weight | `0.7` | 0-1 | Weight for BM25 results |
| Min Semantic Threshold | `0.5` | 0-1 | Minimum score for semantic-only results |
| Single Signal Penalty | `0.5` | 0-1 | Penalty for results from only one search type |
| Max Semantic Results | `100` | 10-500 | Maximum semantic candidates to consider |

### RAG Search Settings

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| LLM Model | `gpt-5-nano` | - | OpenAI model for summaries |
| Temperature | `0.3` | 0-2 | Response randomness (lower = more focused) |
| Custom Prompt | - | - | Custom system prompt for the AI |

### Content Chunking Settings

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| Min Chunk Tokens | `100` | 10-500 | Minimum tokens per chunk |
| Target Chunk Tokens | `400` | 100-1000 | Ideal chunk size |
| Max Chunk Tokens | `600` | 200-2000 | Maximum tokens per chunk |
| Overlap Tokens | `40` | 0-200 | Token overlap between chunks |
| Chunk Threshold | `500` | 100-1000 | Content size before chunking |

### Advanced Settings

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| IVFFlat Lists | `100` | 10-1000 | PostgreSQL index parameter |
| Excerpt Length | `200` | 50-500 | Characters shown in excerpts |
| Short Query Threshold | `3` | 1-10 | Word count for short query handling |

## How Content is Indexed

### Automatic Indexing

Entries are automatically indexed when:
- The entry is saved (not a draft or revision)
- The entry has a URL (entries without URLs are skipped)
- The entry is enabled/live

### What Gets Indexed

- **Entry title** (always)
- **All text-based custom fields:**
  - Plain Text, CKEditor/Redactor
  - Matrix fields (all blocks and nested fields)
  - Super Table fields
  - Other nested field types

### Content Chunking

Long content is automatically split into semantic chunks:
1. Content exceeding the threshold (default: 500 tokens) is chunked
2. Chunks are split at natural boundaries (paragraphs, sentences)
3. Overlap between chunks maintains context continuity
4. Each chunk is embedded separately

### Multi-Site

Each site's content is indexed with its `siteId`, allowing site-specific searches.

## Troubleshooting

### "pgvector extension not found"

Ensure pgvector is installed on your PostgreSQL server:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

On managed providers, enable it through their dashboard.

### "Connection refused" or database errors

1. Verify PostgreSQL host, port, and credentials
2. Check that the database user has CREATE and INSERT permissions
3. For remote databases, ensure your IP is allowlisted
4. Try different SSL modes if connection fails

### No results returned

1. Check that entries have been indexed (**AI Search > Dashboard**)
2. Verify entries have URLs (entries without URLs are not indexed)
3. Run a manual re-index via **Data Sync** or console command
4. Lower the similarity threshold in Hybrid Search settings

### Rate limiting from OpenAI

The plugin caches embeddings for 7 days by default. If you're hitting rate limits:
1. Increase the cache TTL in settings
2. Index content in smaller batches
3. Consider upgrading your OpenAI API tier

### Performance tuning

For large sites (10,000+ entries):
1. Increase `IVFFlat Lists` setting (higher = more accurate, slower index build)
2. Run initial indexing during off-peak hours
3. Consider dedicated PostgreSQL hosting

## Support

- **Email:** dev@ghost.st
- **Issues:** Report bugs via email

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/). A license is required for each Craft project running AI Search in production.

---

Built by [Ghost Street](https://ghost.st)
