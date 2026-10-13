# Database

> Status: **implemented** (migrations, models, factories, 2026-10-13). No business logic yet.

Engine: PostgreSQL 16 (project-local Sail `pgsql` container), database `tttracktracker`. Tests use the `testing` database.

Conventions:
- Timestamps (`timestamp` without time zone) are stored in the app timezone (`APP_TIMEZONE=Europe/Warsaw`). Do not change the timezone once real data exists without converting it.
- Statuses are `string` columns cast to PHP backed enums (`app/Enums/`). Adding a status needs no migration.
- Foreign keys use **`restrictOnDelete()`**: a batch with items/exports (or an item with candidates) cannot be deleted until its children are removed. A future "delete batch" feature must delete children (and TXT files on disk) explicitly.
- Models use `#[Fillable]` attributes and `casts()` (Laravel 13 style). FK columns are not fillable; create children through relationships (`$batch->items()->create()`).

## Tables

### `import_batches` — `ImportBatch`

| Column                 | Type                        | Notes                                        |
| ---------------------- | --------------------------- | -------------------------------------------- |
| id                     | bigint PK                   |                                              |
| name                   | string                      | auto-generated, editable                     |
| source_type            | string, default `manual`    |                                              |
| default_genre          | string, nullable            |                                              |
| year                   | unsigned smallint, nullable | filtering/organizing                         |
| month                  | unsigned tinyint, nullable  | 1–12                                         |
| status                 | string, default `draft`     | `ImportBatchStatus`                          |
| raw_input              | text                        | original textarea content                    |
| created_at, updated_at | timestamps                  |                                              |

No stored counters (`items_count`, `exportable_count`, `missing_links_count` removed). Compute them with `withCount()` + conditional counts.

### `import_items` — `ImportItem`

| Column                    | Type                        | Notes                                              |
| ------------------------- | --------------------------- | -------------------------------------------------- |
| id                        | bigint PK                   |                                                    |
| import_batch_id           | FK → import_batches, restrict |                                                  |
| line_number               | unsigned int                | source line; for AI parsing the track's position (1..n) |
| raw_line                  | text                        | source line; for AI parsing the track's source text joined with ` \| ` |
| artist                    | string, nullable            | null when the line has no `Artist - Title` separator |
| title                     | string                      | remix/version stays in title                       |
| genre                     | string, nullable            |                                                    |
| normalized_key            | string                      | lowercase, collapsed spaces, without `feat.`/`Original Mix` etc.; duplicate detection |
| status                    | string, default `parsed`    | `ImportItemStatus`                                 |
| selected_youtube_url      | string, nullable            | normalized watch URL — **single source of truth** for the chosen link |
| selected_youtube_video_id | string(20), nullable        |                                                    |
| selected_youtube_title    | string, nullable            |                                                    |
| selected_youtube_channel  | string, nullable            |                                                    |
| youtube_score             | unsigned tinyint, nullable  | 0–100, null for custom links                       |
| youtube_search_url        | text, nullable              | no-key / fallback search URL                       |
| include_in_export         | boolean, default false      |                                                    |
| error_message             | text, nullable              | e.g. quota exceeded notice                         |
| created_at, updated_at    | timestamps                  |                                                    |

### `youtube_candidates` — `YoutubeCandidate`

| Column                 | Type                        | Notes                     |
| ---------------------- | --------------------------- | ------------------------- |
| id                     | bigint PK                   |                           |
| import_item_id         | FK → import_items, restrict |                           |
| video_id               | string(20)                  |                           |
| url                    | string                      |                           |
| title                  | string                      |                           |
| channel_title          | string, nullable            |                           |
| description            | text, nullable              |                           |
| published_at           | timestamp, nullable         |                           |
| score                  | unsigned tinyint            | 0–100 (title-based)       |
| rank                   | unsigned tinyint            | 1..3                      |
| created_at, updated_at | timestamps                  |                           |

- No `is_selected` column. A candidate is selected when `video_id = import_items.selected_youtube_video_id` (derived accessor, added when needed).
- No `duration_seconds`. Scoring is title-based only.
- Re-searching an item must delete its old candidates first (restrict FK).

### `export_files` — `ExportFile`

| Column                 | Type                          | Notes                         |
| ---------------------- | ----------------------------- | ----------------------------- |
| id                     | bigint PK                     |                               |
| import_batch_id        | FK → import_batches, restrict |                               |
| type                   | string                        | e.g. `supersimple_txt`        |
| filename               | string                        |                               |
| path                   | string                        | path on the local disk        |
| version                | unsigned int                  | 1, 2, 3…                      |
| items_count            | unsigned int                  | URLs written (snapshot at export time) |
| options                | jsonb, nullable               | cast to `array`               |
| created_at, updated_at | timestamps                    |                               |

### `youtube_search_cache` — `YoutubeSearchCache`

Own table, not Laravel Cache, so search history stays visible for quota analysis. Singular table name, set on the model with `#[Table('youtube_search_cache')]`.

| Column                 | Type               | Notes                     |
| ---------------------- | ------------------ | ------------------------- |
| id                     | bigint PK          |                           |
| query_hash             | string(64), unique | sha256 of the query       |
| query                  | text               |                           |
| results                | jsonb              | cast to `array`           |
| provider               | string             | e.g. `youtube_data_api`   |
| expires_at             | timestamp          |                           |
| created_at, updated_at | timestamps         |                           |

### Framework tables

- `jobs`, `failed_jobs`, `job_batches`: database queue + `Bus::batch()` (all in the default `0001_01_01_000002_create_jobs_table` migration).
- `cache`, `cache_locks`: database cache driver.
- `sessions`: database session driver (created by the default users migration).
- `users`, `password_reset_tokens`: Laravel defaults, **unused** (no auth). Kept so the default skeleton stays intact.

## Relationships

| Model            | Relationship                                   |
| ---------------- | ---------------------------------------------- |
| ImportBatch      | `items()` hasMany ImportItem, `exportFiles()` hasMany ExportFile |
| ImportItem       | `importBatch()` belongsTo, `youtubeCandidates()` hasMany YoutubeCandidate |
| YoutubeCandidate | `importItem()` belongsTo                       |
| ExportFile       | `importBatch()` belongsTo                      |

## Status values

Statuses describe **pipeline stage only**, implemented as PHP backed enums in `app/Enums/` (cases in TitleCase, values in snake_case).

`ImportBatchStatus`: `Draft` (`draft`), `Parsing` (`parsing`, AI parsing queued/running), `Parsed`, `Reviewed` (`reviewed`, Save & close on Parse Review), `Searching`, `Searched`, `Exported`, `Failed` (also: AI parsing failed).

`ImportItemStatus`: `Parsed` (`parsed`), `Duplicate`, `SearchPending` (`search_pending`), `Searched`, `Exported`, `Failed`.

Defaults: batch `draft`, item `parsed` (DB default + model `$attributes`, so unsaved models see them too).

Derived (not stored):

| Derived state      | Rule                                              |
| ------------------ | ------------------------------------------------- |
| has link           | `selected_youtube_url` is not null                |
| no link            | `selected_youtube_url` is null after search       |
| excluded           | `include_in_export = false`                       |
| possible duplicate | computed by `DuplicateDetectionService` (warning) |
| batch has duplicates / ready to export / missing links | computed from items |

## Indexes

| Table                  | Index                                   | Purpose                                     |
| ---------------------- | --------------------------------------- | ------------------------------------------- |
| `import_batches`       | `(year, month)`                         | filtering                                   |
| `import_items`         | `(import_batch_id, line_number)`        | items of a batch in order (also covers the FK) |
| `import_items`         | `(normalized_key)`                      | duplicate detection, later across batches   |
| `youtube_candidates`   | `(import_item_id, rank)`                | candidates of an item in order (covers the FK) |
| `export_files`         | unique `(import_batch_id, type, version)` | one row per version (covers the FK)       |
| `youtube_search_cache` | unique `(query_hash)`                   | cache lookup                                |

## Factories

| Factory                     | States                                                      |
| --------------------------- | ----------------------------------------------------------- |
| `ImportBatchFactory`        | `parsed()`, `reviewed()`                                    |
| `ImportItemFactory`         | `withSelectedYoutubeLink()` (score 90, included), `withSearchUrlOnly()` (no-key mode) |
| `YoutubeCandidateFactory`   | —                                                           |
| `ExportFileFactory`         | —                                                           |
| `YoutubeSearchCacheFactory` | `expired()`                                                 |

No demo seeder yet (`DatabaseSeeder` is empty). Add one when there is UI to look at.

## Migration notes

- Use `./vendor/bin/sail artisan migrate`; never `migrate:fresh` / `db:wipe` without explicit permission.
- App migrations: `2026_10_13_082212` … `082216` (batches → items → candidates → export files → search cache).
