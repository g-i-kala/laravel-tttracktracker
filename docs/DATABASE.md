# Database

> Status: planned. No app migrations exist yet (only Laravel defaults).

Engine: PostgreSQL (project-local Sail container, database `dj_library_agent`).

## Tables

### `import_batches`

| Column              | Notes                                   |
| ------------------- | --------------------------------------- |
| id                  | PK                                      |
| name                | auto-generated, editable                |
| source_type         | e.g. `manual`                           |
| default_genre       | nullable                                |
| year, month         | nullable                                |
| status              | see statuses                            |
| raw_input           | original textarea content               |
| items_count         | counter                                 |
| exportable_count    | counter                                 |
| missing_links_count | counter                                 |
| created_at, updated_at |                                      |

### `import_items`

| Column                    | Notes                               |
| ------------------------- | ----------------------------------- |
| id                        | PK                                  |
| import_batch_id           | FK → import_batches                 |
| line_number, raw_line     | source line                         |
| artist, title, genre      | parsed/edited values                |
| status                    | see statuses                        |
| selected_youtube_url      | normalized watch URL                |
| selected_youtube_video_id |                                     |
| selected_youtube_title    |                                     |
| selected_youtube_channel  |                                     |
| youtube_score             | 0–100                               |
| youtube_search_url        | no-key fallback URL                 |
| include_in_export         | boolean                             |
| error_message             | nullable                            |
| created_at, updated_at    |                                     |

### `youtube_candidates`

| Column                 | Notes                     |
| ---------------------- | ------------------------- |
| id                     | PK                        |
| import_item_id         | FK → import_items         |
| video_id, url          |                           |
| title, channel_title   |                           |
| description            |                           |
| published_at           |                           |
| score                  | 0–100                     |
| rank                   | 1..3                      |
| is_selected            | boolean                   |
| created_at, updated_at |                           |

### `export_files`

| Column                 | Notes                         |
| ---------------------- | ----------------------------- |
| id                     | PK                            |
| import_batch_id        | FK → import_batches           |
| type                   | e.g. `supersimple_txt`        |
| filename, path         |                               |
| version                | 1, 2, 3…                      |
| items_count            |                               |
| options                | json                          |
| created_at, updated_at |                               |

### `youtube_search_cache`

| Column                 | Notes                     |
| ---------------------- | ------------------------- |
| id                     | PK                        |
| query_hash             | unique                    |
| query                  |                           |
| results                | json                      |
| provider               | e.g. `youtube_data_api`   |
| expires_at             |                           |
| created_at, updated_at |                           |

## Relationships

- ImportBatch hasMany ImportItem, hasMany ExportFile
- ImportItem belongsTo ImportBatch, hasMany YoutubeCandidate
- YoutubeCandidate belongsTo ImportItem
- ExportFile belongsTo ImportBatch

## Status values

Import batch: `draft`, `parsed`, `duplicates_found`, `ready_for_search`, `searching`, `searched`, `ready_to_export`, `exported`, `failed`.

Import item: `draft`, `parsed`, `duplicate`, `possible_duplicate`, `search_pending`, `searched`, `selected`, `no_link`, `excluded`, `exported`.

## Indexes (planned)

- `import_items (import_batch_id, line_number)`
- `youtube_candidates (import_item_id, rank)`
- `export_files (import_batch_id, type, version)`
- `youtube_search_cache (query_hash)` unique

## Migration notes

- Use `php artisan migrate`; never `migrate:fresh` / `db:wipe` without explicit permission.
- `.env` currently points to shared `pg-dev` — to be switched to the Sail `pgsql` service.
