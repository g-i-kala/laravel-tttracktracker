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
  Services/      ImportParserService, AiTrackListParserService, ImportBatchService (create batch + items),
                 ImportItemService (edit track details during review),
                 DuplicateDetectionService, YoutubeSearchService,
                 YoutubeSearchUrlService, YoutubeScoringService, YoutubeUrlNormalizer,
                 SupersimpleTxtExportService, ExportFilenameService
  Ai/Agents/     TrackListParser (Laravel AI SDK agent, structured output)
  Jobs/          ParseImportBatchWithAiJob (implemented), SearchYoutubeForImportItemJob (dispatched via Bus::batch())

resources/views/
  layouts/app.blade.php          Livewire page layout (`layouts::app`)
  livewire/...                   Views of the Livewire components
  components/modal.blade.php     Reusable `<x-modal>` (Blade + Alpine, x-modelable)
  components/nav-link.blade.php  Sidebar link; disabled "Coming soon" until its route exists
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

## Import parsing (implemented)

`App\Services\ImportParserService` is pure (no DB). `parse(string $rawInput)` returns a list of `{line_number, raw_line, artist, title, normalized_key, error_message}`. Persisting items is done by the caller (`Imports\Create`).

1. Split input into lines (`\R`); `line_number` = line in the original input.
2. Clean: collapse whitespace, strip leading numbering (`1.`, `1)`, `#1`, zero-padded `01`), then a leading timestamp (`00:12`, `1:02:33`, `[00:12]`, optionally followed by `-`/`|`). A bare number without a leading zero stays (`50 Cent`, `808 State`).
3. Skip lines that are empty after cleaning.
4. Split on the **first** ` - ` / ` – ` / ` — ` (dash surrounded by whitespace). An unspaced hyphen never splits (`Jay-Z`). The rest stays in the title (`Song - Edit`).
5. No separator → `artist = null`, `title` = whole line, `error_message = "Artist not detected"`.

`normalizedKey(?artist, title)` (public; recompute after edits in Parse Review) → `"{artist}|{title}"`, each part: ASCII transliteration, lowercase, featured credits removed (`feat.`, `ft.`, `featuring`, bracketed or trailing up to the next bracket), `(Original Mix)` / `[Original Mix]` removed, `&` → `and`, other punctuation → space, whitespace collapsed. Remixes and extended mixes stay distinct; looser matches are the job of similar-duplicate detection.

## AI parsing (implemented)

When the configured AI provider has an API key, New Import parses **any** pasted format with AI instead of the line parser.

- Agent `App\Ai\Agents\TrackListParser` (Laravel AI SDK, `HasStructuredOutput`): returns `tracks[]` of `{artist|null, title, genre|null, source}`. Instructions cover plain lines, store copy-pastes (Beatport: title + mix, artists, label, genre, BPM/key, date, price per track), timestamps/numbering, store badges ("Exclusive"), release names vs track titles. Version goes into the title in parentheses.
- `AiTrackListParserService`: `isEnabled()` (key present for `config('services.track_list_ai.provider')`), `parse()` maps tracks to the same shape as the line parser (`line_number` = position, `raw_line` = `source`, `normalized_key`, missing-artist notice) plus `genre`.
- Config `config('services.track_list_ai.*')`: `provider` (`AI_PARSER_PROVIDER`, default `openai`), `model` (`AI_PARSER_MODEL`, project default `gpt-4.1-mini`; empty = provider default, for OpenAI in laravel/ai 1.2: `gpt-6.1-sol`), `timeout` (`AI_PARSER_TIMEOUT`, 180 s). Provider keys live in `config/ai.php` (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, …).
- Flow: `Imports\Create::save()` → `ImportBatchService::createForAiParsing()` (batch `parsing`, no items) → `ParseImportBatchWithAiJob` (queue, `tries = 1`, `timeout = 240`) → `storeParsedLines()` (items, item genre = AI genre ?? batch default genre, batch `parsed`). On failure `failed()` logs and sets the batch `failed`.
- Parse Review **Save & close** (`saveAndClose()`) → `ImportBatchService::markReviewed()` (`parsed` → `reviewed`) → redirect to `Imports\Index` (`/imports`, list with `withCount('items')`, paginated).
- Parse Review polls (`wire:poll.2s="checkParsing"`) while `parsing`; on `failed` offers **Try again with AI** (`retryAiParsing`) and **Use simple parser** (`useLineParser`).
- **Usage logging:** listener `App\Listeners\RecordAiUsage` (auto-discovered, singleton) handles SDK events `PromptingAgent` (start time), `AgentPrompted` (tokens) and `AgentFailed` → `AiUsageService::record()` → `ai_usage_logs`. Works for any future agent. The job runs the parse inside `Context::scope([import_batch_id => …])`, so the log is linked to the batch. Cost = (uncached input × input price + cached input × cached price + output × output price) / 1M, prices in `config('services.ai_pricing.{provider}.{model}')`; unknown model → cost null.
- Without a key: the line parser runs synchronously as before (live counter, 1–200 tracks).
- `DB_QUEUE_RETRY_AFTER=300` must stay above the job timeout (240 s), otherwise a long AI call could run twice.

## Queue strategy

- `QUEUE_CONNECTION=database`.
- YouTube API search: `Bus::batch()` with one `SearchYoutubeForImportItemJob` per item (`job_batches` table). The `Imports\Show` component polls batch progress with `wire:poll` while the batch is running (e.g. 12/30).
- No-key mode needs no queue: generating URLs is synchronous.
- AI parsing: one `ParseImportBatchWithAiJob` per batch (not retried automatically; the user retries from Parse Review). Requires `DB_QUEUE_RETRY_AFTER` (300) > job timeout (240).
- Exports are synchronous (no job). Writing ~30 lines is instant and the modal flow needs an immediate result.
- Worker runs as the `queue` service in `compose.yaml`. Run `queue:restart` after code changes.
- No Redis/RabbitMQ in MVP.

## YouTube API / fallback strategy

- Config (`config/services.php`, implemented), read via `config('services.youtube.*')`, never `env()` in app code:

  | Key                      | `.env`                            | Default | Meaning                                  |
  | ------------------------ | --------------------------------- | ------- | ---------------------------------------- |
  | `api_key`                | `YOUTUBE_DATA_API_KEY`            | empty   | empty → no-key mode                      |
  | `auto_include_threshold` | `YOUTUBE_AUTO_INCLUDE_THRESHOLD`  | 80      | score >= → preselect + include in export |
  | `preselect_threshold`    | `YOUTUBE_PRESELECT_THRESHOLD`     | 60      | score >= → preselect only                |
  | `candidates_limit`       | `YOUTUBE_CANDIDATES_LIMIT`        | 3       | candidates stored per item               |
  | `cache_ttl_days`         | `YOUTUBE_CACHE_TTL_DAYS`          | 30      | `youtube_search_cache.expires_at` = now + N days |
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
- **Layout** (`layouts::app`, implemented): left sidebar (Dashboard, Imports, Exports, "+ New YouTube Export"), collapsible on mobile; `wire:navigate` links with `data-current` active state; flash area for `session('status')`. Page title from the component's `#[Title]`.
- **Theme**: light/dark follows the OS (`prefers-color-scheme`, Tailwind `dark:`). Neutral `zinc` + an accent palette: `config('app.accent')` (`APP_ACCENT`) → `data-accent` on `<html>` → CSS variables → `accent-*` Tailwind colors (`resources/css/app.css`). Unknown values fall back to red. `navy` is adaptive: dark navy (shifted `blue` scale) in light mode, `sky` in dark mode.
- The only classic controller is `ExportController@download`, which streams the TXT file.
- See [UI-FLOW.md](UI-FLOW.md) and [API.md](API.md).
