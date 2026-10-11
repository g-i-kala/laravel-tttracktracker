# Database

> Status: planned. No app migrations exist yet (only Laravel defaults).

Engine: PostgreSQL 16 (project-local Sail `pgsql` container), database `tttracktracker`. Tests use the `testing` database.

## Tables

### `import_batches`

| Column                 | Notes                                        |
| ---------------------- | -------------------------------------------- |
| id                     | PK                                           |
| name                   | auto-generated, editable                     |
| source_type            | e.g. `manual`                                |
| default_genre          | nullable                                     |
| year, month            | nullable; used for filtering/organizing imports |
| status                 | `ImportBatchStatus` enum                     |
| raw_input              | original textarea content                    |
| created_at, updated_at |                                              |

No stored counters (`items_count`, `exportable_count`, `missing_links_count` removed). Compute them with `withCount()` + conditional counts.

### `import_items`

| Column                    | Notes                                              |
| ------------------------- | -------------------------------------------------- |
| id                        | PK                                                 |
| import_batch_id           | FK → import_batches                                |
| line_number, raw_line     | source line                                        |
| artist, title, genre      | parsed/edited values (remix stays in title)        |
| normalized_key            | lowercase, collapsed spaces, without `feat.`/`Original Mix` etc.; used for duplicate detection |
| status                    | `ImportItemStatus` enum                            |
| selected_youtube_url      | normalized watch URL — **single source of truth** for the chosen link |
| selected_youtube_video_id |                                                    |
| selected_youtube_title    |                                                    |
| selected_youtube_channel  |                                                    |
| youtube_score             | 0–100, nullable (null for custom links)            |
| youtube_search_url        | no-key / fallback search URL                       |
| include_in_export         | boolean                                            |
| error_message             | nullable (e.g. quota exceeded notice)              |
| created_at, updated_at    |                                                    |

### `youtube_candidates`

| Column                 | Notes                     |
| ---------------------- | ------------------------- |
| id                     | PK                        |
| import_item_id         | FK → import_items         |
| video_id, url          |                           |
| title, channel_title   |                           |
| description            |                           |
| published_at           |                           |
| score                  | 0–100 (title-based)       |
| rank                   | 1..3                      |
| created_at, updated_at |                           |

- No `is_selected` column. A candidate is selected when `video_id = import_items.selected_youtube_video_id` (derived accessor).
- No `duration_seconds`. Scoring is title-based only.

### `export_files`

| Column                 | Notes                         |
| ---------------------- | ----------------------------- |
| id                     | PK                            |
| import_batch_id        | FK → import_batches           |
| type                   | e.g. `supersimple_txt`        |
| filename, path         |                               |
| version                | 1, 2, 3…                      |
| items_count            | URLs written (snapshot at export time) |
| options                | json                          |
| created_at, updated_at |                               |

### `youtube_search_cache`

Own table, not Laravel Cache, so search history stays visible for quota analysis.

| Column                 | Notes                     |
| ---------------------- | ------------------------- |
| id                     | PK                        |
| query_hash             | unique                    |
| query                  |                           |
| results                | json                      |
| provider               | e.g. `youtube_data_api`   |
| expires_at             |                           |
| created_at, updated_at |                           |

### Framework tables

- `jobs`, `failed_jobs`: database queue.
- `job_batches`: `Bus::batch()` for YouTube search (`php artisan make:queue-batches-table` if missing).
- `cache`, `sessions`: database drivers.

## Relationships

- ImportBatch hasMany ImportItem, hasMany ExportFile
- ImportItem belongsTo ImportBatch, hasMany YoutubeCandidate
- YoutubeCandidate belongsTo ImportItem
- ExportFile belongsTo ImportBatch

## Status values

Statuses describe **pipeline stage only**, implemented as PHP backed enums in `app/Enums/`.

`ImportBatchStatus`: `draft`, `parsed`, `searching`, `searched`, `exported`, `failed`.

`ImportItemStatus`: `parsed`, `duplicate`, `search_pending`, `searched`, `exported`, `failed`.

Derived (not stored):

| Derived state      | Rule                                              |
| ------------------ | ------------------------------------------------- |
| has link           | `selected_youtube_url` is not null                |
| no link            | `selected_youtube_url` is null after search       |
| excluded           | `include_in_export = false`                       |
| possible duplicate | computed by `DuplicateDetectionService` (warning) |
| batch has duplicates / ready to export / missing links | computed from items |

## Indexes (planned)

- `import_items (import_batch_id, line_number)`
- `import_items (normalized_key)`: duplicate detection, later also across batches
- `youtube_candidates (import_item_id, rank)`
- `export_files (import_batch_id, type, version)`
- `youtube_search_cache (query_hash)` unique
- `import_batches (year, month)`: filtering

## Migration notes

- Use `./vendor/bin/sail artisan migrate`; never `migrate:fresh` / `db:wipe` without explicit permission.
