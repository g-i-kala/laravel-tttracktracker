# Architecture

> Status: planned. Nothing below is implemented yet unless marked otherwise.

## Main modules

| Module            | Responsibility                                              |
| ----------------- | ----------------------------------------------------------- |
| Import            | Create batch, store raw input, parse lines into items       |
| Duplicates        | Detect exact and similar duplicates within a batch          |
| YouTube search    | API search + caching, or no-key search URL generation       |
| Scoring           | Score candidates 0–100, decide preselect/include            |
| Link management   | Select candidate, custom link validation/normalization      |
| Export            | Generate Supersimple TXT, versioning, history, download     |

## Laravel structure (planned)

```text
app/
  Models/        ImportBatch, ImportItem, YoutubeCandidate, ExportFile, YoutubeSearchCache
  Http/Controllers/
                 DashboardController, ImportBatchController, ImportItemController,
                 YoutubeSearchController, ExportController
  Services/      ImportParserService, DuplicateDetectionService, YoutubeSearchService,
                 YoutubeSearchUrlService, YoutubeScoringService, YoutubeUrlNormalizer,
                 SupersimpleTxtExportService, ExportFilenameService
  Jobs/          SearchYoutubeForBatchJob, SearchYoutubeForImportItemJob,
                 GenerateSupersimpleTxtExportJob
```

Rules: small controllers, business logic in services, slow work in jobs, form requests when validation grows.

## Data flow

```text
Textarea input
  → ImportBatch (raw_input)
  → ImportParserService → ImportItem[] (artist, title, genre)
  → DuplicateDetectionService → statuses duplicate / possible_duplicate
  → SearchYoutubeForBatchJob → SearchYoutubeForImportItemJob (per item)
       API mode:   YoutubeSearchService (+ cache) → YoutubeScoringService → YoutubeCandidate[]
       No-key:     YoutubeSearchUrlService → import_items.youtube_search_url
  → user selection / custom link (YoutubeUrlNormalizer)
  → SupersimpleTxtExportService + ExportFilenameService → storage file + ExportFile row
```

## Queue strategy

- `QUEUE_CONNECTION=database` for MVP.
- Batch search dispatches one job per item.
- Worker: `./vendor/bin/sail artisan queue:work`.
- No Redis/RabbitMQ in MVP.

## YouTube API / fallback strategy

- `YOUTUBE_DATA_API_KEY` present → official YouTube Data API, `YOUTUBE_CANDIDATES_LIMIT=3`.
- Results cached in `youtube_search_cache` by query hash with `expires_at`.
- Key missing → no API calls; generate search URL; user pastes custom link.
- No scraping.

## Export generation

- Select items where `include_in_export = true` and `selected_youtube_url IS NOT NULL`.
- Write URLs joined by newline, nothing else.
- Filename from slugged batch name; versioning `_v2`, `_v3` via `ExportFilenameService`.
- Overwrite-latest or create-new-version chosen by user in modal.
- Stored on local disk; `ExportFile` row records path, version, items count, options.

## Frontend

Blade pages with Vue components mounted inside them (no Inertia, no SPA). See [UI-FLOW.md](UI-FLOW.md).
