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
  Models/        ImportBatch, ImportItem, YoutubeCandidate, ExportFile, YoutubeSearchCache, Track
  Livewire/      Full-page components (class-based), one per screen:
                 Dashboard, Imports/Index, Imports/Create, Imports/Review,
                 Imports/Show (YouTube results + export), Exports/Index, Library/Index
  Http/Controllers/
                 ExportController, TrackListController (file downloads only)
  Services/      ImportParserService, AiTrackListParserService, ImportBatchService (create batch + items),
                 ImportItemService (edit track details during review),
                 DuplicateDetectionService, YoutubeSearchService,
                 YoutubeSearchUrlService, YoutubeScoringService, YoutubeUrlNormalizer,
                 SupersimpleTxtExportService, ExportFilenameService,
                 LibraryService (music library: add import items as tracks)
  Ai/Agents/     TrackListParser, PlaylistTitleParser (Laravel AI SDK agents, structured output)
  Jobs/          ParseImportBatchWithAiJob, ParsePlaylistTitlesWithAiJob, SearchYoutubeForImportItemJob (dispatched via Bus::batch())

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
  → DuplicateDetectionService (computed on render, nothing stored) → duplicate modal / banner / row marks
  → Imports\Review::searchYoutube() → ImportBatchService::searchYoutube()
       No-key mode (no YOUTUBE_DATA_API_KEY): generateYoutubeSearchUrls()
         → YoutubeSearchUrlService → youtube_search_url, items + batch `searched` (sync, no queue)
       API mode (key set, implemented 2026-10-17): startYoutubeSearch()
         → search URLs for all items (fallback), items without a selected link → `search_pending`, batch `searching`
         → Bus::batch([SearchYoutubeForImportItemJob per item], allowFailures, finally → finishYoutubeSearch())
         → job: YoutubeSearchService (cache first; logs real calls) → YoutubeScoringService::rank() (top 3)
           → ImportItemService::storeYoutubeCandidates(): youtube_candidates replaced, best >= 60 → selected_youtube_*, >= 80 → include_in_export
         → on API error: item keeps its search URL + notice in error_message (quota: all remaining items, no more requests)
         → finishYoutubeSearch(): leftover pending items → `searched`, batch `exported` if it has an export, else `searched`
       → redirect to Imports\Show (YouTube Results) right away (no progress UI yet: step 4)
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

`similarKey(?artist, title)` (public) → like `normalizedKey`, but the title without any bracketed part (falls back to the full title if nothing is left) and the artists split on `&` / `,` / `and` / `x` / `vs`, normalized and sorted. Used for similar-duplicate warnings.

`App\Services\DuplicateDetectionService` (pure, works on a collection of items): `exactDuplicates()` → `[duplicate id => first item id]` grouped by `normalized_key` in line order; `similarItems()` → `[item id => [similar item ids]]` for items with a different normalized key whose similar-key parts match: one part (artists / base title) equal and the other equal or within 2 Levenshtein edits (less than half of the shorter text). Pairwise, O(n²); fine for batch sizes in the hundreds. `ImportBatchService::removeExactDuplicates()` deletes the duplicates and keeps the first of each group.

`App\Services\YoutubeSearchUrlService` (pure): `query(?artist, title)` → `"{artist} {title}"` with `(Original Mix)` / `[Original Mix]` removed (other versions, remixes, feat. kept; no artist → title only); `url()` → `https://www.youtube.com/results?search_query=…` (`http_build_query`, spaces as `+`). `generateYoutubeSearchUrls()` regenerates the URLs on every run (after edits in review); exported item/batch statuses are kept. `Imports\Show` builds a URL on the fly for an item without one.

`App\Services\YoutubeUrlNormalizer` (pure): `videoId(url)` / `normalize(url)` → `https://www.youtube.com/watch?v=ID` for `watch?v=` (any param order), `youtu.be/`, `shorts/`, `embed/`, `live/` on `youtube.com` / `www.` / `m.` / `music.` (scheme optional, `http`/`https` only); ID must be 11 chars `[A-Za-z0-9_-]`; anything else → null. `ImportItemService::selectCustomYoutubeLink()` stores URL + video id, clears title/channel/score (API-only) and sets `include_in_export`; `selectYoutubeCandidate()` copies an API candidate (URL, video id, title, channel, score) to the item and includes it (YouTube Results candidate list); `clearYoutubeLink()` clears all and excludes; `toggleExport()` only flips items with a link.

`App\Services\YoutubeSearchService` (API mode, step 1 of 5): `isEnabled()` = `services.youtube.api_key` set. `search(query)` → `list<{video_id, url, title, channel_title, description, published_at}>`: first a valid `youtube_search_cache` row (`query_hash` = sha256 of the lowercased, squished query; works without a key), otherwise `search.list` (`part=snippet`, `type=video`, `maxResults=10`: quota cost is the same for 1 or 50 results, scoring picks the best 3), results stored with `expires_at` = now + `cache_ttl_days` (`updateOrCreate`, so expired rows are refreshed). Titles/channels/descriptions are HTML-decoded. The key goes in the `X-Goog-Api-Key` header, never the URL. Failures throw `YoutubeSearchException` (`reason` from the API, e.g. `quotaExceeded`, `keyInvalid`, or `connectionFailed` / `missingApiKey`; `isQuotaExceeded()` also for `dailyLimitExceeded` / rate limits); errors are never cached. Every real request (not cache hits) is recorded in `youtube_api_calls` (step 3a): 100 quota units for `search.list`, also for failed requests; 0 for `quotaExceeded` and connection failures; `import_batch_id` from `Context` key `import_batch_id` (`YoutubeSearchService::BATCH_CONTEXT_KEY`). After a daily quota error (`quotaExceeded` / `dailyLimitExceeded`, not short rate limits) a cache flag (`youtube:quota_exceeded`, `isQuotaExceeded()`) blocks further requests until midnight Pacific Time (`QUOTA_TIMEZONE`); blocked searches throw the quota exception without a request or a log row. Cache hits still work.

`App\Services\YoutubePlaylistService` (playlist import, step 1 of 3): `playlistId(url)` reads the `list` parameter from youtube.com / m. / music. / youtu.be links (also `watch?v=…&list=`) or accepts a bare playlist ID (12–64 characters, so a video ID is never taken for one); pure. `videos(url, limit)` → `{videos: list<{video_id, url, title, channel_title, description, position}>, unavailable, duplicates, notImported, isMix}`: `playlistItems.list` (`part=snippet,status`, 50 per page, `pageToken`) only until `limit` playable videos are collected; videos that are not `public`/`unlisted` (deleted = `privacyStatusUnspecified`, private) are skipped and counted in `unavailable`; each video is kept once (first position wins), repeats are counted in `duplicates`; `notImported` = `pageInfo.totalResults` minus processed items when the limit was hit. `isMix(playlistId)`: YouTube Mix IDs (`RD…`, except curated `RDCLAK…`) are auto-generated and endless (every page repeats mostly the same videos), so a Mix is read from its first page only (≤ 50 videos, `notImported` = 0, `isMix` = true). `channel_title` is `videoOwnerChannelTitle` (the video's channel; `snippet.channelTitle` is the playlist owner). Needs the API key and respects the shared quota flag (`YoutubeSearchService::isQuotaExceeded()` / `markQuotaExceeded()`). Failures throw `YoutubePlaylistException` (extends `YoutubeSearchException`; reasons `invalidPlaylistUrl`, `missingApiKey`, `playlistNotFound`, `playlistItemsNotAccessible`, quota, `connectionFailed`) with `userMessage()` for the UI. Each request is logged in `youtube_api_calls` (`endpoint` `playlistItems.list`, `query` = playlist ID, 1 unit; 0 for quota errors and connection failures), so it counts in the quota counter. No cache.

`App\Services\MusicalKeyService::toCamelot(?key)` (pure): store key (`E Minor`, `F♯ Major`, `Bbm`, `C# min`, note only = major) or Camelot (`09a`) → Camelot code (`9A`), null when unrecognized. Enharmonic spellings map to the same code.

## Adding tracks to an import (implemented)

"Add tracks" on Parse Review and YouTube Results (trait `App\Livewire\Concerns\AddsTracks`, modal partial `livewire/imports/partials/add-tracks-modal.blade.php`):

- `ImportBatchService::parseAdditionalTracks()`: AI when enabled (key + budget), **synchronously in the request** (`set_time_limit(240)`, the modal shows "Parsing with AI…"), inside `Context::scope` so the usage is logged against the batch; otherwise / on "Use simple parser" the line parser. AI exceptions are reported and shown in the modal.
- `appendParsedLines()`: new items after the highest `line_number`, `status = parsed`, batch default genre; tracks whose `normalized_key` is already in the batch (or repeated in the paste) are skipped; returns `{added, skipped}`. The batch status is not changed. Limit: 200 tracks per import (`Create::MAX_TRACKS`).
- Parse Review reloads the rows and shows "Added N tracks. N already in this import were skipped."; YouTube Results runs `searchYoutube()` at once (API mode: only items without a link are queued, progress + summary as usual; no-key mode: search URLs). The button is disabled on YouTube Results (and hidden on Review) while the batch is `searching`.

## AI parsing (implemented)

When the configured AI provider has an API key, New Import parses **any** pasted format with AI instead of the line parser.

- Agent `App\Ai\Agents\TrackListParser` (Laravel AI SDK, `HasStructuredOutput`): returns `tracks[]` of `{artist|null, title, genre|null, bpm|null (number), key|null (as written), label|null, source}`. BPM/key/label/genre only when listed in the source (no guessing). Instructions cover plain lines, store copy-pastes (Beatport: title + mix, artists, label, genre, BPM/key, date, price per track), timestamps/numbering, store badges ("Exclusive"), release names vs track titles. Version goes into the title in parentheses.
- `AiTrackListParserService`: `isEnabled()` (key present for `config('services.track_list_ai.provider')`), `parse()` maps tracks to the same shape as the line parser (`line_number` = position, `raw_line` = `source`, `normalized_key`, missing-artist notice) plus `genre`, `bpm` (rounded; outside 40–300 → null), `musical_key`, `camelot_key` (`MusicalKeyService`), `label`.

**Playlist import (steps 2–3 of 3):**
- UI: `Imports\Create` has `source` (`paste` / `playlist`, `#[Url]`) and `playlistUrl`; `playlistEnabled` = YouTube API key set. `save()` on the playlist tab validates the link (`YoutubePlaylistService::playlistId()`), calls `videos(url, MAX_TRACKS)` synchronously, shows `YoutubePlaylistException::userMessage()` on the field, then `createFromPlaylist()` and flashes the imported / skipped / repeated / left-out counts (plus a Mix note) + `check_duplicates`.
- `PlaylistTrackService` (pure): `isTopicChannel()` (`X - Topic`), `cleanTitle()` (drops a leading "Premiere" and bracketed noise: official/music video, audio, lyric(s) video, visualiser/visualizer, clip, HD/HQ/4K, remastered + year; versions and remixes stay), `parsedLines(videos)` → line-parser shape (`line_number` = playlist order, `raw_line` = original video title) plus `selected_youtube_url` / `_video_id` / `_title` / `_channel` and `include_in_export = true`. Topic: artist = X, title = cleaned video title; otherwise `Artist - Title` split of the cleaned title, no separator → artist null + "Artist not detected" (the channel is not used, it is often a label).
- `ImportBatchService::createFromPlaylist(attributes, videos)`: batch with `source_type = youtube_playlist`, `raw_input` = video titles one per line, items stored right away (default genre fallback). When AI is enabled and at least one video is not from a Topic channel: status `parsing` + `ParsePlaylistTitlesWithAiJob`; otherwise `parsed`.
- `ParsePlaylistTitlesWithAiJob` (1 try, 240 s, AI usage logged with the batch) → `ImportBatchService::applyAiPlaylistTitles()`: sends non-Topic items as `N. <original title> [channel: <channel>]` (N = `line_number`) to `AiTrackListParserService::parsePlaylistTitles()` → agent `PlaylistTitleParser` (`tracks[]` of `{number, artist|null, title}`; channel name as artist only for the artist's own channel). Answers with unknown numbers or empty titles are dropped; matched items get new artist/title/`normalized_key`/notice in place; links are never touched; batch → `parsed`. `failed()` sets `parsed` and keeps the simple-parser result (no `failed` status, so no retry/simple-parser buttons).
- Search YouTube on such a batch spends no `search.list` calls: every item already has a selected link.
- Config `config('services.track_list_ai.*')`: `provider` (`AI_PARSER_PROVIDER`, default `openai`), `model` (`AI_PARSER_MODEL`, project default `gpt-4.1-mini`; empty = provider default, for OpenAI in laravel/ai 1.2: `gpt-6.1-sol`), `timeout` (`AI_PARSER_TIMEOUT`, 180 s). Provider keys live in `config/ai.php` (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, …).
- Flow: `Imports\Create::save()` → `ImportBatchService::createForAiParsing()` (batch `parsing`, no items) → `ParseImportBatchWithAiJob` (queue, `tries = 1`, `timeout = 240`) → `storeParsedLines()` (items, item genre = AI genre ?? batch default genre, batch `parsed`). On failure `failed()` logs and sets the batch `failed`.
- Parse Review autosave: `updatedItems()` → `ImportItemService::updateTrackDetails()` (artist, title, genre, bpm, musical_key, label; recomputes `normalized_key` and `camelot_key` via `MusicalKeyService`) → dispatches `item-saved` (Alpine flashes "Saved" in the row).
- Parse Review duplicates: computed properties `exactDuplicates` / `similarItems` (reset with `rows` after every edit/removal). `showDuplicateModal` opens once after parsing (session flash `check_duplicates` from `Imports\Create`, `checkParsing()`, `useLineParser()`); `removeDuplicates()`, `keepAllDuplicates()`; "Review manually" only closes the modal (Alpine).
- Parse Review **Save & close** (`saveAndClose()`) → `ImportBatchService::markReviewed()` (`parsed` → `reviewed`) → redirect to `Imports\Index` (`/imports`, list with `withCount('items')`, paginated).
- Parse Review polls (`wire:poll.2s="checkParsing"`) while `parsing`; on `failed` offers **Try again with AI** (`retryAiParsing`) and **Use simple parser** (`useLineParser`).
- **Usage logging:** listener `App\Listeners\RecordAiUsage` (auto-discovered, singleton) handles SDK events `PromptingAgent` (start time), `AgentPrompted` (tokens) and `AgentFailed` → `AiUsageService::record()` → `ai_usage_logs`. Works for any future agent. The job runs the parse inside `Context::scope([import_batch_id => …])`, so the log is linked to the batch. Cost = (uncached input × input price + cached input × cached price + output × output price) / 1M, prices in `config('services.ai_pricing.{provider}')[$model]` (model names contain dots, so they are array keys, not part of the config path); unknown model → cost null.
- **Settings (`AiSettingsService`, screen `Settings\Ai`):** API key = encrypted `settings.ai.api_key` ?? `config('ai.providers.{provider}.key')` (.env). Model = `settings.ai.model` ?? `services.track_list_ai.model`. `AiTrackListParserService::parse()` runs the prompt inside `withApiKey()`, which sets the provider key in config, forgets the cached `AiManager` provider instance (the worker keeps it between jobs), and restores both afterwards.
- **Budget:** `settings.ai.monthly_budget_usd` (null = no limit). `AiUsageService::spentThisMonth()` sums `cost_usd` since the start of the month (app timezone). `isEnabled()` = key present **and** budget not reached, so New Import falls back to the line parser and Review's retry is refused. Banner on New Import at ≥ 80% and at 100%. The budget is checked before dispatch, not inside the job.
- Without a key: the line parser runs synchronously as before (live counter, 1–200 tracks).
- `DB_QUEUE_RETRY_AFTER=300` must stay above the job timeout (240 s), otherwise a long AI call could run twice.

## Queue strategy

- `QUEUE_CONNECTION=database`.
- YouTube API search (implemented): `Bus::batch()` with one `SearchYoutubeForImportItemJob` per item without a selected link (`job_batches` table), `allowFailures()`, `finally` → `ImportBatchService::finishYoutubeSearch()`. Job: `Batchable`, `#[DeleteWhenMissingModels]`, `tries = 1`, `timeout = 60`; skips items not in `search_pending` and cancelled batches; runs the search inside `Context::scope([import_batch_id => …])` so API calls are linked to the batch; `failed()` falls back like an API error. One worker → jobs run one after another (~0.3–0.5 s per API call, cache hits instant).
- Progress (implemented, step 4a): `Imports\Show` keeps `pendingItemIds` / `searchedItemIds`; while the batch is `searching` it polls `checkSearchProgress()` every 2 s (ready = items not `search_pending`), copies the links of newly finished items into `links[id]` and, once the batch is no longer `searching`, sets `searchSummary` (linked / to check / not found / failed). No websockets: polling is enough for a local single-user app (DECISIONS 2026-10-17).
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
  | `daily_quota`            | `YOUTUBE_DAILY_QUOTA`             | 10000   | daily quota units shown in the quota counter |
- **No-key mode is the primary mode at start.** API mode is built in parallel once a key is obtained.
- API mode: official YouTube Data API `search.list`, up to 3 candidates.
- Quota: 10,000 units/day, `search.list` = 100 units (~100 searches/day, ~3 batches of 30). The cache in `youtube_search_cache` is checked before every API call.
- **Quota counter (`YoutubeQuotaService::today()`):** sums `youtube_api_calls.quota_units` since midnight Pacific Time (converted to the app timezone before querying) → used, limit (`daily_quota`), remaining, searches left (remaining / 100), usage, `isUsedUp` (less than one search left **or** the `youtube:quota_exceeded` flag), `resetsAt` (next midnight PT in the app timezone). It is an estimate from this app's own calls. Shown by `<x-youtube-quota>` (one line; amber ≥ 80%, red when used up) via trait `App\Livewire\Concerns\ShowsYoutubeQuota` (computed `youtubeQuota`, null without an API key) on Dashboard, Parse Review and YouTube Results (refreshes with the search polling), and on `Settings\Youtube`.
- On `quotaExceeded` or any API failure: that item falls back to no-key mode (`youtube_search_url` + `error_message` notice). The batch never fails as a whole.
- No scraping.

## Scoring

`App\Services\YoutubeScoringService` (pure, step 2 of 5). Title-based only (no duration lookup). `score(artist, title, label, result)` → 0–100; `rank(…, results, limit)` adds `score`, sorts highest first (API order on ties), keeps `limit` (config `candidates_limit`). Text is compared as lowercase ASCII words (`&` → `and`).

| Signal | Points |
| --- | --- |
| Base title words (without bracketed version / feat.) in the video title | up to +40 (share of words) |
| Artist words in the video title or channel name | up to +30 (share of words) |
| Channel = one of the artists or the label (ignoring `Topic`, `VEVO`, `Official`, `Music`, `Records`, `Recordings`) | +15 |
| "Official Audio" / "Official Video" / "Official Music Video" in the title or "Provided to YouTube" in the description | +5 |
| Requested version words (e.g. `Extended`, `Hammer Remix`; `Mix`, `Version`, `Original` ignored) all in the video title | +10 |
| Remix requested but not found, or a remix (`remix`, `rmx`, `rework`, `bootleg`, `vip`, `flip`, `refix`) when the original was requested | −25 |
| Each unwanted keyword in the video title: live, set, podcast, lyrics, sped up, slowed, nightcore, cover, reaction, tutorial, karaoke, 8D, 1 hour / 10 hours (skipped when the track itself contains it) | −30 each |

- Thresholds (config): >= 80 preselect + include; 60–79 preselect only; < 60 not included (applied in step 3).
- Weights are the starting point agreed on 2026-10-17; tune in step 5 with real imports.

## Export generation

- Implemented in `SupersimpleTxtExportService` (sync, `DB::transaction`), filenames by `ExportFilenameService`, download by `ExportController@download`.
- Select items where `include_in_export = true` and `selected_youtube_url IS NOT NULL`, in line order.
- Write URLs joined by newline, nothing else (no trailing newline, no empty lines).
- Filename from slugged batch name; versioning `_v2`, `_v3` via `ExportFilenameService`.
- Overwrite-latest or create-new-version chosen by the user in a modal.
- Stored on the `local` disk (`storage/app/private`) under `exports/{batch id}/{filename}`; an `ExportFile` row (`type = supersimple_txt`) records path, version, items count, options. Overwrite keeps the latest row's filename/version, updates `items_count` and `updated_at`.
- After export: exported items → `ImportItemStatus::Exported`, batch → `ImportBatchStatus::Exported`.
- `isLatestExportOutdated(batch)`: compares `content(batch)` with the latest file (missing file = outdated; no export = not outdated). Edits that do not change the exported links (title, BPM…) keep it up to date. Used by the Dashboard.

## Track list export

- `TrackListExportService` (2026-10-18): `txt(batch)` = `Artist - Title` per line (title only without an artist, no trailing newline); `csv(batch)` = header `Artist, Title, Genre, BPM, Key, Camelot, Label, YouTube URL` + one row per track (`fputcsv`, comma, empty cells for nulls, `selected_youtube_url`), prefixed with a UTF-8 BOM for Excel. Every item in line order, ignoring `include_in_export`. Generated on request, nothing stored, no `ExportFile` row, statuses untouched.
- `TrackListController@download` streams it; filename `ExportFilenameService::trackList()` → `{slug}_tracks.{txt|csv}`.
- UI: `<x-track-list-button :batch :count>` (data attributes with the name, count and both URLs; disabled without tracks) opens `imports/partials/track-list-modal` (Alpine only: `trackList` + `trackListOpen` in the page root's x-data; the options are plain download links). Used on Parse Review, YouTube Results and per row on the imports list.

## Frontend

- Blade + **Livewire 4** (with Alpine.js, bundled with Livewire). No Inertia, no SPA, no Vue. UI language: English.
- **Full-page components**: each screen is a class-based Livewire component routed with `Route::livewire()` and rendered inside `layouts::app`.
- **Class-based format**: `app/Livewire/*.php` + `resources/views/livewire/*.blade.php` (`config/livewire.php` → `make_command.type = class`).
- **One component per screen.** Table rows are plain Blade partials. Extract a row component only if there is a real need (e.g. performance).
- User actions (edit, select candidate, custom link, toggle export, export) are component methods with server-side validation. No JSON API.
- **Modals**: our own `<x-modal>` Blade component, shown/hidden by Alpine and bound to a component property. No modal packages.
- **Layout** (`layouts::app`, implemented): left sidebar (Dashboard, Imports, Exports, "+ New YouTube Export"), collapsible on mobile; `wire:navigate` links with `data-current` active state; flash area for `session('status')`. Page title from the component's `#[Title]`.
- **Theme**: light/dark follows the OS (`prefers-color-scheme`, Tailwind `dark:`). Neutral `zinc` + an accent palette: `config('app.accent')` (`APP_ACCENT`) → `data-accent` on `<html>` → CSS variables → `accent-*` Tailwind colors (`resources/css/app.css`). Unknown values fall back to red. `navy` is adaptive: dark navy (shifted `blue` scale) in light mode, `sky` in dark mode.
- The only classic controllers are `ExportController@download` (Supersimple TXT) and `TrackListController@download` (track list TXT/CSV), both file downloads.
- See [UI-FLOW.md](UI-FLOW.md) and [API.md](API.md).

## Deleting

- `ImportBatchService::deletionSummary(ids)` → `{imports, tracks, exports}` for the modal. `deleteBatches(ids)` deletes in a transaction, in foreign-key order: YouTube candidates → items → export rows → batches; then the TXT files and `exports/{batch id}/` directories (after the transaction). `ai_usage_logs.import_batch_id` is `nullOnDelete`, so cost history stays.
- `SupersimpleTxtExportService::deleteExports(ids)` deletes rows (transaction) and files; batches left without any export go back from `exported` to `searched`, and so do their exported items.
- `ParseImportBatchWithAiJob` has `#[DeleteWhenMissingModels]`: a job queued for a batch deleted in the meantime is dropped silently.

## Music library (Milestone 2, steps 1a–1b)

`App\Services\LibraryService`:
- `addItem(item, markAsWanted = false)`: the track with the item's `normalized_key` (`exactTrack()`) → `linkToTrack()`; otherwise a new track (artist, title, keys, genre, BPM, key, Camelot, label, `youtube_url` = `selected_youtube_url`, `wanted` = the checkbox). Sets `import_items.track_id`.
- `linkToTrack(item, track, markAsWanted = false)`: exact match or the user's "Same track" choice. Fills only the track's empty fields (genre, BPM, key, Camelot, label, YouTube link); artist/title/rating/notes are never overwritten. Wanted is only switched on, never off.
- `similarTracks(item)`: tracks with the same `similar_key` and another `normalized_key` (other version, swapped artists) for the "Same track / New track" modal. Call it when `exactTrack()` is null.
- `addItem()` keeps an item that already has a track on that track (even after its title was edited), so ticking it again only fills empty fields / marks wanted.
- `addItems(items, markAsWanted, sameTrackIds)` → `{created, linked}`: line order; `sameTrackIds` (item id → track id) are the "Same track" choices, the rest goes through `addItem()` (`wasRecentlyCreated` counts new tracks).
- `similarMatches(items)` → `[item id => similar tracks]` for items without a track and without an exact match; two queries for the whole list.
- UI: trait `App\Livewire\Concerns\AddsToLibrary` (Parse Review, YouTube Results; component hook `addedToLibrary(notice)`): `openLibraryModal()` preselects items without `track_id`; `addToLibrary()` first goes to the "Same track / New track" step when `similarMatches()` is not empty (choices default `new`), then calls `addItems()` with the choices that are really among the item's matches; `backToLibrarySelection()`. Selection ids are limited to the batch's items.
- **Library screen** `App\Livewire\Library\Index` (`/library`, step 1c-i): `#[Url]` filters, `tracks` computed paginator (50). Search: `artist`/`title`/`label` `ilike` (LIKE wildcards escaped) or `normalized_key` containing the ASCII-lowercased text (accents ignored: "niz" finds "niż"). Availability filters are ANDed (`pof` = `playable_offline`, `pon` = has `youtube_url`, `wanted`, `none` = none of them; `none` clears the others). Ranges ignore non-numeric input. Sorting by a whitelist of SQL expressions, `nulls last`, then artist, title, id; Camelot sorts by its number (`substring(camelot_key from '^[0-9]+')`, PostgreSQL). Genre and Camelot options come from the library's values.
- `LibraryService::setPlayableOffline(ids, bool)`: one `update` query; marking also sets `wanted = false` (same rule as the model `saving` hook, which a mass update skips). Used by the POF button and Mark as POF / Unmark POF on the library screen.
- `Track`: POF = `playable_offline`, PON = `isPlayableOnline()` (has `youtube_url`), `wanted` cleared in a `saving` hook when POF is set.

## Dashboard

`App\Livewire\Dashboard` computed properties: `toReview` (status `parsed`, `withCount('items')`), `missingLinks` (status `searched`/`exported`, `whereHas` items without `selected_youtube_url`, `missing_links_count`), `outdatedExports` (batches with exports filtered by `SupersimpleTxtExportService::isLatestExportOutdated()`; reads the latest file of each exported batch, fine for a single-user local app), `recentImports` / `recentExports` (`RECENT_LIMIT = 10`). `ImportBatchStatus::screenRouteName()` picks YouTube Results or Parse Review for links to a batch.
