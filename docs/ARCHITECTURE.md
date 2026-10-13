# Architecture

> Status: planned. Nothing below is implemented yet unless marked otherwise.

## Main modules

| Module            | Responsibility                                              |
| ----------------- | ----------------------------------------------------------- |
| Import            | Create batch, store raw input, parse lines into items       |
| Duplicates        | Exact + similar duplicates within a batch via `normalized_key` (cross-batch after MVP) |
| YouTube search    | No-key search URL generation; API search + cache + quota fallback |
| Scoring           | Title-based score 0–100, decide preselect/include           |
| Link management   | Select candidate, custom link validation/normalization      |
| Export            | Generate Supersimple TXT synchronously, versioning, history, download |

## Laravel structure (planned)

```text
app/
  Enums/         ImportBatchStatus, ImportItemStatus
  Models/        ImportBatch, ImportItem, YoutubeCandidate, ExportFile, YoutubeSearchCache
  Livewire/      Full-page components (class-based), one per screen:
                 Dashboard, Imports/Index, Imports/Create, Imports/Review,
                 Imports/Show (YouTube results + export), Exports/Index
  Http/Controllers/
                 ExportController (file download only)
  Services/      ImportParserService, DuplicateDetectionService, YoutubeSearchService,
                 YoutubeSearchUrlService, YoutubeScoringService, YoutubeUrlNormalizer,
                 SupersimpleTxtExportService, ExportFilenameService
  Jobs/          SearchYoutubeForImportItemJob   (dispatched via Bus::batch())

resources/views/
  layouts/app.blade.php          Livewire page layout (`layouts::app`)
  livewire/...                   Views of the Livewire components
  components/modal.blade.php     Reusable `<x-modal>` (Blade + Alpine)
```

Rules: small components, business logic in services (components only validate input, call services and set UI state), statuses as enums, derived states as model accessors/scopes.

## Data flow

```text
Textarea input
  → ImportBatch (raw_input)
  → ImportParserService → ImportItem[] (artist, title, genre, normalized_key)
  → DuplicateDetectionService → status duplicate / possible-duplicate warning
  → Imports\Review::searchYoutube()
       No-key mode (default at start): YoutubeSearchUrlService → youtube_search_url (sync, no queue)
       API mode: Bus::batch([SearchYoutubeForImportItemJob, ...])
         → YoutubeSearchService (cache first) → YoutubeScoringService → YoutubeCandidate[]
         → best candidate copied to import_items.selected_youtube_*
         → on quotaExceeded / API error: fallback to youtube_search_url + notice
  → user selection / custom link (YoutubeUrlNormalizer) → import_items.selected_youtube_*
  → Imports\Show::export() (sync) → SupersimpleTxtExportService + ExportFilenameService
       → storage file + ExportFile row
```

## Queue strategy

- `QUEUE_CONNECTION=database`.
- YouTube API search: `Bus::batch()` with one `SearchYoutubeForImportItemJob` per item (`job_batches` table). The `Imports\Show` component polls batch progress with `wire:poll` while the batch is running (e.g. 12/30).
- No-key mode needs no queue: generating URLs is synchronous.
- Exports are synchronous (no job). Writing ~30 lines is instant and the modal flow needs an immediate result.
- Worker runs as the `queue` service in `compose.yaml`. Run `queue:restart` after code changes.
- No Redis/RabbitMQ in MVP.

## YouTube API / fallback strategy

- Config: `config('services.youtube.api_key' | 'auto_include_threshold' | 'candidates_limit')` from `.env` `YOUTUBE_*`.
- **No-key mode is the primary mode at start.** API mode is built in parallel once a key is obtained.
- API mode: official YouTube Data API `search.list`, up to 3 candidates.
- Quota: 10,000 units/day, `search.list` = 100 units (~100 searches/day, ~3 batches of 30). The cache in `youtube_search_cache` is checked before every API call.
- On `quotaExceeded` or any API failure: that item falls back to no-key mode (`youtube_search_url` + `error_message` notice). The batch never fails as a whole.
- No scraping.

## Scoring

Title-based only (no duration lookup):

- Boost: artist + title token match, official/Topic/label channel, "extended"/"original mix" when requested.
- Penalize: live set, podcast, lyrics, sped up, slowed, cover, reaction, tutorial, mix/set keywords.
- >= 80 preselect + include; 60–79 preselect only; < 60 not included.

## Export generation

- Select items where `include_in_export = true` and `selected_youtube_url IS NOT NULL`.
- Write URLs joined by newline, nothing else.
- Filename from slugged batch name; versioning `_v2`, `_v3` via `ExportFilenameService`.
- Overwrite-latest or create-new-version chosen by the user in a modal.
- Stored on local disk; an `ExportFile` row records path, version, items count, options.

## Frontend

- Blade + **Livewire 4** (with Alpine.js, bundled with Livewire). No Inertia, no SPA, no Vue. UI language: English.
- **Full-page components**: each screen is a class-based Livewire component routed with `Route::livewire()` and rendered inside `layouts::app`.
- **Class-based format**: `app/Livewire/*.php` + `resources/views/livewire/*.blade.php` (`config/livewire.php` → `make_command.type = class`).
- **One component per screen.** Table rows are plain Blade partials. Extract a row component only if there is a real need (e.g. performance).
- User actions (edit, select candidate, custom link, toggle export, export) are component methods with server-side validation. No JSON API.
- **Modals**: our own `<x-modal>` Blade component, shown/hidden by Alpine and bound to a component property. No modal packages.
- The only classic controller is `ExportController@download`, which streams the TXT file.
- See [UI-FLOW.md](UI-FLOW.md) and [API.md](API.md).
