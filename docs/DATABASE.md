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
| source_type            | string, default `manual`    | `manual` (pasted list) or `youtube_playlist` (`ImportBatchService::SOURCE_*`) |
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
| bpm                       | unsigned smallint, nullable | from AI parsing when the source lists it           |
| musical_key               | string(50), nullable        | key as written in the source, e.g. `E Minor`       |
| camelot_key               | string(3), nullable         | derived from `musical_key` by `MusicalKeyService`, e.g. `9A` |
| label                     | string, nullable            | record label from the source                       |
| normalized_key            | string                      | lowercase, collapsed spaces, without `feat.`/`Original Mix` etc.; duplicate detection |
| status                    | string, default `parsed`    | `ImportItemStatus`                                 |
| selected_youtube_url      | string, nullable            | normalized watch URL — **single source of truth** for the chosen link |
| selected_youtube_video_id | string(20), nullable        |                                                    |
| selected_youtube_title    | string, nullable            |                                                    |
| selected_youtube_channel  | string, nullable            |                                                    |
| youtube_score             | unsigned tinyint, nullable  | 0–100, null for custom links                       |
| youtube_search_url        | text, nullable              | no-key / fallback search URL                       |
| include_in_export         | boolean, default false      |                                                    |
| track_id                  | FK → tracks, nullable, **nullOnDelete** | library track (Add to library); removing the track unlinks the item |
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
| path                   | string                        | path on the `local` disk: `exports/{batch id}/{filename}` |
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

### `ai_usage_logs` — `AiUsageLog`

One row per AI agent call (Laravel AI SDK), written by the `RecordAiUsage` listener.

| Column                 | Type                         | Notes                                                    |
| ---------------------- | ---------------------------- | -------------------------------------------------------- |
| id                     | bigint PK                    |                                                          |
| import_batch_id        | FK nullable, `nullOnDelete`  | batch being parsed (from `Context`), null outside a batch; history survives batch deletion |
| invocation_id          | string                       | SDK invocation UUID                                      |
| agent                  | string                       | agent class, e.g. `App\Ai\Agents\TrackListParser`       |
| provider, model        | string                       | requested provider and model (e.g. `openai`, `gpt-4.1-mini`) |
| input_tokens           | unsigned int, default 0      | includes cached input tokens                             |
| cached_input_tokens    | unsigned int, default 0      | subset of input read from the prompt cache               |
| output_tokens          | unsigned int, default 0      |                                                          |
| cost_usd               | decimal(12,6), nullable      | estimate from `services.ai_pricing`; null = model has no price; 0 for failures |
| duration_ms            | unsigned int, nullable       |                                                          |
| status                 | string                       | `AiUsageStatus`                                          |
| error_message          | text, nullable               | failures only, max 1000 chars                            |
| created_at, updated_at | timestamps                   |                                                          |

### `youtube_api_calls` — `YoutubeApiCall`

One row per **real** YouTube Data API request (cache hits are not recorded), written by `YoutubeSearchService`. Used to estimate the daily quota use (Google does not expose the remaining quota through the API).

| Column                 | Type                         | Notes                                                    |
| ---------------------- | ---------------------------- | -------------------------------------------------------- |
| id                     | bigint PK                    |                                                          |
| import_batch_id        | FK nullable, `nullOnDelete`  | batch being searched (from `Context`), null outside a batch; history survives batch deletion |
| endpoint               | string                       | `search.list`                                            |
| query                  | text                         | search query as sent                                     |
| quota_units            | unsigned smallint            | estimate: 100 for `search.list` (also failed requests); 0 for `quotaExceeded` and connection failures |
| status                 | string                       | `YoutubeApiCallStatus`: `succeeded`, `quota_exceeded`, `failed` |
| error_reason           | string, nullable             | API reason (`quotaExceeded`, `keyInvalid`, …) or `connectionFailed` |
| results_count          | unsigned smallint, nullable  | successful calls only                                    |
| duration_ms            | unsigned int, nullable       |                                                          |
| created_at, updated_at | timestamps                   |                                                          |

### `settings` — `Setting`

Key/value app settings (`SettingsService`). Secrets are encrypted with `Crypt::encryptString()` (APP_KEY); if APP_KEY changes, the secret reads as missing and must be entered again.

| Column                 | Type               | Notes                                  |
| ---------------------- | ------------------ | -------------------------------------- |
| key                    | string PK          | e.g. `ai.api_key` (encrypted), `ai.model`, `ai.monthly_budget_usd` |
| value                  | text, nullable     |                                        |
| created_at, updated_at | timestamps         |                                        |

### `tracks` — `Track`

Music library (Milestone 2, step 1a, 2026-10-19). One row per **version** of a track (DECISIONS "Music library (Milestone 2): model and scope", "Library track availability").

| Column                 | Type                        | Notes                                             |
| ---------------------- | --------------------------- | ------------------------------------------------- |
| id                     | bigint PK                   |                                                   |
| artist                 | string, nullable            |                                                   |
| title                  | string                      | version stays in the title, as in `import_items`  |
| normalized_key         | string, unique              | `ImportParserService::normalizedKey()`; exact match on Add to library |
| similar_key            | string, indexed             | `ImportParserService::similarKey()`; possible matches ("Same track / New track") |
| genre                  | string, nullable            |                                                   |
| bpm                    | unsigned smallint, nullable |                                                   |
| musical_key            | string(50), nullable        |                                                   |
| camelot_key            | string(3), nullable         | `MusicalKeyService`                               |
| label                  | string, nullable            |                                                   |
| release_year           | unsigned smallint, nullable | year only                                         |
| rating                 | unsigned tinyint, nullable  | 1–5                                               |
| notes                  | text, nullable              |                                                   |
| playable_offline       | boolean, default false      | **POF**: the user has a file; manual, later the folder scan |
| wanted                 | boolean, default false      | "want a file"; cleared when the track becomes POF (`saving` hook) |
| youtube_url            | string, nullable            | main link, copied from the item on Add to library; **PON** = `isPlayableOnline()` (derived, not stored) |
| created_at, updated_at | timestamps                  |                                                   |

### `tags` — `Tag`

Step 1d (2026-10-19). Free tags on library tracks.

| Column                 | Type          | Notes                                                    |
| ---------------------- | ------------- | -------------------------------------------------------- |
| id                     | bigint PK     |                                                          |
| name                   | string(50)    | unique regardless of case (`tags_name_lower_unique` on `lower(name)`); first spelling kept |
| created_at, updated_at | timestamps    |                                                          |

A tag without tracks is deleted (`TagService::deleteUnused()`).

### `tag_track`

`tag_id` FK → tags (**cascadeOnDelete**), `track_id` FK → tracks (**cascadeOnDelete**, indexed), primary key `(tag_id, track_id)`.

### Framework tables

- `jobs`, `failed_jobs`, `job_batches`: database queue + `Bus::batch()` (all in the default `0001_01_01_000002_create_jobs_table` migration).
- `cache`, `cache_locks`: database cache driver.
- `sessions`: database session driver (created by the default users migration).
- `users`, `password_reset_tokens`: Laravel defaults, **unused** (no auth). Kept so the default skeleton stays intact.

## Relationships

| Model            | Relationship                                   |
| ---------------- | ---------------------------------------------- |
| ImportBatch      | `items()` hasMany ImportItem, `exportFiles()` hasMany ExportFile |
| ImportItem       | `importBatch()` belongsTo, `youtubeCandidates()` hasMany YoutubeCandidate, `track()` belongsTo Track (nullable) |
| Track            | `importItems()` hasMany ImportItem, `tags()` belongsToMany Tag (ordered by name) |
| Tag              | `tracks()` belongsToMany Track                 |
| YoutubeCandidate | `importItem()` belongsTo                       |
| ExportFile       | `importBatch()` belongsTo                      |
| AiUsageLog       | `importBatch()` belongsTo (nullable)           |

## Status values

Statuses describe **pipeline stage only**, implemented as PHP backed enums in `app/Enums/` (cases in TitleCase, values in snake_case).

`ImportBatchStatus`: `Draft` (`draft`), `Parsing` (`parsing`, AI parsing queued/running), `Parsed`, `Reviewed` (`reviewed`, Save & close on Parse Review), `Searching`, `Searched`, `Exported`, `Failed` (also: AI parsing failed).

`ImportItemStatus`: `Parsed` (`parsed`), `Duplicate` (not used yet: duplicates are computed, see DECISIONS 2026-10-14), `SearchPending` (`search_pending`), `Searched`, `Exported`, `Failed`.

`AiUsageStatus`: `Succeeded` (`succeeded`), `Failed` (`failed`).

Defaults: batch `draft`, item `parsed` (DB default + model `$attributes`, so unsaved models see them too).

Derived (not stored):

| Derived state      | Rule                                              |
| ------------------ | ------------------------------------------------- |
| has link           | `selected_youtube_url` is not null                |
| no link            | `selected_youtube_url` is null after search       |
| excluded           | `include_in_export = false`                       |
| exact duplicate    | same `normalized_key` as an earlier item in the batch, computed by `DuplicateDetectionService` |
| possible duplicate | same similar key (`ImportParserService::similarKey()`), computed by `DuplicateDetectionService` (warning) |
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
| `ai_usage_logs`        | `(created_at)`                          | monthly spend sum                           |
| `youtube_api_calls`    | `(created_at)`                          | quota units used today                      |
| `import_items`         | `(track_id)`                            | items of a library track (FK)               |
| `tracks`               | unique `(normalized_key)`               | exact match on Add to library               |
| `tracks`               | `(similar_key)`                         | possible matches                            |
| `tags`                 | unique `lower(name)`                    | case-insensitive tag names                  |
| `tag_track`            | primary `(tag_id, track_id)`, `(track_id)` | tags of a track / tracks of a tag        |

## Factories

| Factory                     | States                                                      |
| --------------------------- | ----------------------------------------------------------- |
| `ImportBatchFactory`        | `parsed()`, `reviewed()`                                    |
| `ImportItemFactory`         | `withMetadata()` (Deep House, 128, E Minor / 9A, tszr), `withSelectedYoutubeLink()` (score 90, included), `withSearchUrlOnly()` (no-key mode) |
| `YoutubeCandidateFactory`   | —                                                           |
| `ExportFileFactory`         | —                                                           |
| `YoutubeSearchCacheFactory` | `expired()`                                                 |
| `AiUsageLogFactory`         | `failed()`                                                  |
| `YoutubeApiCallFactory`     | `quotaExceeded()` (0 units)                                 |
| `TagFactory`                | —                                                           |
| `TrackFactory`              | `withMetadata()`, `withYoutubeLink()` (PON), `playableOffline()`, `wanted()` |

No demo seeder yet (`DatabaseSeeder` is empty). Add one when there is UI to look at.

## Migration notes

- Use `./vendor/bin/sail artisan migrate`; never `migrate:fresh` / `db:wipe` without explicit permission.
- App migrations: `2026_10_13_082212` … `082216` (batches → items → candidates → export files → search cache). Later: `2026_10_17_205533_create_youtube_api_calls_table`, `2026_10_19_092356_create_tracks_table`, `2026_10_19_092357_add_track_id_to_import_items_table`, `2026_10_19_122126_create_tags_table`, `2026_10_19_122127_create_tag_track_table`.

## Planned: music library (Milestone 2)

> Status: **draft, not migrated** (agreed 2026-10-19, DECISIONS "Music library (Milestone 2): model and scope"). `tracks`, `import_items.track_id` (step 1a), `tags` and `tag_track` (step 1d) are implemented, see above. Exact types and indexes of the rest are settled when each step is built.

### `playlists` + `playlist_track` (step 2)

`playlists`: id, name, notes (nullable), timestamps. `playlist_track`: `playlist_id`, `track_id`, `position` (manual order), unique `(playlist_id, track_id)`.

### Later (step 3, folder scan)

`track_files` (path, format, size, …), details after the step 3 Q&A. A file sets `playable_offline`.
