# Decisions

## 2026-10-11 — Use separate PostgreSQL container

**Decision:**
Use a project-local PostgreSQL container (Sail `pgsql`) instead of shared `pg-dev`.

**Reason:**
Better isolation, easier deployment, simpler local setup.

**Consequences:**
Project docker-compose owns database service. `.env` must be switched from `pg-dev`.

## 2026-10-11 — Blade + Vue components, no SPA

**Decision:**
Blade pages with Vue components mounted inside; no Inertia, no SPA.

**Reason:**
Simple local app; interactivity needed only in tables and modals.

**Consequences:**
Routes are classic web routes; Vue components call JSON endpoints.

## 2026-10-11 — No auth in MVP

**Decision:**
No user accounts or login.

**Reason:**
Single-user local app.

**Consequences:**
No Breeze/Jetstream/Sanctum.

## 2026-10-11 — Database queue

**Decision:**
`QUEUE_CONNECTION=database` for MVP.

**Reason:**
No extra infrastructure. RabbitMQ may come later.

## 2026-10-11 — YouTube API with no-key fallback

**Decision:**
Use official YouTube Data API when key exists; otherwise generate search URLs and accept custom links. No scraping.

**Reason:**
App must work without an API key.

## 2026-10-11 — Install Laravel Boost

**Decision:**
Install `laravel/boost` (dev) with guidelines, skills and MCP server.

**Reason:**
Better AI-assisted development context (docs search, DB schema, Artisan tools).

**Consequences:**
Boost appends its guidelines to `CLAUDE.md` / `AGENTS.md`. Boost's rule "only create documentation files if explicitly requested" is overridden by the project's Documentation Policy in `CLAUDE.md`.

## 2026-10-11 — Atomic task execution

**Decision:**
Claude executes tasks atomically unless instructed otherwise.

**Reason:**
Keep changes small and reviewable; user drives the next step.

## 2026-10-11 — Architecture review (Q&A)

Decisions from the architecture review of `CLAUDE.md`, made point by point.

### A1 — YouTube quota fallback

**Decision:** On `quotaExceeded` or an API error, the item falls back to no-key mode (search URL + notice). No-key mode is the primary mode at start; API mode is built in parallel.
**Reason:** `search.list` costs 100 of the 10,000 daily units (~100 searches/day).
**Consequences:** The search cache is mandatory; the batch never fails as a whole.

### A2 — No video duration

**Decision:** No `duration_seconds`, no `videos.list` call. Scoring is title-based only.
**Reason:** Keep the API integration simple at start.
**Consequences:** "Full/long version" is judged by title keywords only. Can be revisited.

### A3 — Selected link: snapshot on item

**Decision:** `import_items.selected_youtube_*` is the single source of truth; `youtube_candidates.is_selected` is removed.
**Reason:** Two places storing the selection can drift apart.
**Consequences:** Selected candidate = `video_id` match (accessor). Custom links are stored only on the item.

### A4 — Pipeline-only statuses as enums

**Decision:** Batch: `draft, parsed, searching, searched, exported, failed`. Item: `parsed, duplicate, search_pending, searched, exported, failed`. Implemented as PHP backed enums.
**Reason:** States like selected/no_link/excluded/ready_to_export duplicated data.
**Consequences:** Derived states are accessors/scopes.

### A5 — No stored counters on batches

**Decision:** Remove `items_count`, `exportable_count`, `missing_links_count`; use `withCount()`.
**Reason:** Avoid counters drifting from data; the scale is small.

### B1 — Synchronous export

**Decision:** No `GenerateSupersimpleTxtExportJob`; export runs in the request.
**Reason:** Writing ~30 lines is instant; the modal flow needs an immediate result.

### B2 — Bus::batch() for YouTube search

**Decision:** Replace `SearchYoutubeForBatchJob` with `Bus::batch()` of `SearchYoutubeForImportItemJob`.
**Reason:** Built-in progress tracking and failure handling.
**Consequences:** `job_batches` table; UI polls progress.

### B3 — Queue worker as a compose service

**Decision:** A `queue` service in `compose.yaml` runs `queue:work`.
**Reason:** Prevents "search hangs" when no worker is running.
**Consequences:** Run `queue:restart` after code changes.

### B4 — Own search cache table

**Decision:** Keep `youtube_search_cache` instead of Laravel Cache.
**Reason:** Search history is visible, useful while learning the API quota.

### B5 — YouTube config via config/services.php

**Decision:** `config('services.youtube.*')`, never `env()` in app code.
**Reason:** Laravel convention; `env()` breaks with `config:cache`.
**Consequences (2026-10-13):** Keys `api_key`, `auto_include_threshold` (80), `preselect_threshold` (60), `candidates_limit` (3), `cache_ttl_days` (30). All thresholds live in config, so services have no magic numbers.

### C1 — normalized_key now, cross-batch duplicates later

**Decision:** Add `import_items.normalized_key` (indexed) in MVP; cross-batch duplicate warning after MVP.
**Reason:** Cheap now, avoids a later migration.

### C2 — Keep Vue in Blade

**Decision:** No Livewire; Vue 3 with one `createApp` per page and `data-props`.
**Status:** Superseded by "2026-10-11 — Livewire instead of Vue" (below).

### C3 — year/month for filtering

**Decision:** Keep optional `year`/`month` on batches for filtering/organizing.

### C4 — English UI

**Decision:** UI language is English.

### D1 — Slim down CLAUDE.md

**Decision:** CLAUDE.md is to keep rules, stack, safety, a short MVP summary and a docs map; the specification lives in `docs/`.
**Status:** Done 2026-10-13.

### D2 — Keep 13 doc files

**Decision:** No merging of doc files.

### D3 — VS Code only

**Decision:** Remove Boost's Cursor/OpenCode files, add `.vscode/extensions.json`, re-run `boost:install` after Sail.
**Status:** Partly done: `boost:install` re-run with Sail (only Claude Code agent). `.cursor/` and `opencode.json` removed. Adding `.vscode/extensions.json` still pending.

## 2026-10-11 — Sail runtime: PHP 8.4, PostgreSQL 16

**Decision:** Sail runtime `8.4` (not 8.3/8.5), image `postgres:16`, `FORWARD_DB_PORT=5433`, `APP_PORT=8000`.
**Reason:** Symfony 8 in `composer.lock` requires PHP >= 8.4.1 and matches the host PHP. Host port 5432 is taken by `pg-dev`.
**Consequences:** Connect to the DB from the host on `localhost:5433`.

## 2026-10-11 — App name and database name

**Decision:** `APP_NAME=TTTrackTracker`, database `tttracktracker` (instead of `dj_library_agent`).

## 2026-10-11 — Livewire instead of Vue

**Decision:**
Frontend is Blade + Livewire (with Alpine.js for small client-side interactions). Vue is dropped. Supersedes C2.

**Reason:**
- The app is server-centric: forms, tables and modals, with logic in PHP services.
- No JSON API layer to build. Item actions (edit, select candidate, custom link, toggle export) become Livewire component methods with server-side validation.
- YouTube search progress uses `wire:poll` on `Bus::batch()` state instead of a custom polling endpoint.
- The app is local and single-user, so the network round-trip per action is negligible.
- One language, one validation layer, and tests via `Livewire::test()` in PHPUnit.
- Vue was not installed yet, so switching costs nothing.

**Consequences:**
- Livewire is added via Composer. Vue/Vitest are not added.
- The planned JSON endpoints and Vue components are replaced by Livewire components.
- ARCHITECTURE, TECH-STACK, UI-FLOW, API, TODO, ROADMAP and CLAUDE.md updated for Livewire on 2026-10-13.

## 2026-10-13 — Livewire conventions

**Decision:**
1. **Full-page components**: every screen is a Livewire component routed with `Route::livewire()`. No page controllers. The only controller is `ExportController@download`.
2. **Class-based format**: `app/Livewire/` + `resources/views/livewire/` (not the v4 single-file default). `config/livewire.php` sets `make_command.type = class`.
3. **Modals**: our own `<x-modal>` Blade component with Alpine. No Flux, no wire-elements/modal.
4. **One component per screen**: rows are Blade partials. Row components only when there is a real need.
- **Autosave bindings** (added 2026-10-15): use `wire:model.live.blur`. Since Livewire 4.1, `wire:model.blur` only delays the client-side sync and sends no request, so the `updated*()` hook would not run until some other action. Tests assert the rendered binding.

**Reason:**
- Full-page components are the standard Livewire approach; they remove page controllers and POST/PATCH routes.
- Class-based is easiest to read for someone new to Livewire and has the most examples online (v2/v3 standard).
- Four simple modals do not justify a new dependency.
- Livewire docs advise against over-nesting components; 30 rows re-render instantly in a local app.

**Consequences:**
- API.md lists page routes plus a table of component actions instead of POST/PATCH routes and JSON endpoints.
- Planned controllers (Dashboard, ImportBatch, ImportItem, YoutubeSearch) are replaced by Livewire components.
- Feature tests use `Livewire::test()`.
- Flux can be added later if we want a nicer UI faster.

## 2026-10-13 — Schema conventions (MVP data model)

**Decision:**
1. Foreign keys use `restrictOnDelete()` (batch → items/exports, item → candidates). No cascade, no soft deletes.
2. Statuses are `string` columns + PHP backed enum casts (no CHECK constraints, no native PG enums).
3. Factories with states only; no demo seeder for now.
4. Keep Laravel's default `users` / `password_reset_tokens` tables (unused, no auth); remove only the Test User from `DatabaseSeeder`.
5. `import_items.artist` is nullable (lines without an `Artist - Title` separator); `title` is required.

**Reason:**
1. Deleting a batch must be a deliberate action; nothing disappears by accident.
2. Changing statuses needs no migration; Laravel standard.
3. Demo data is useful only once there is UI to look at.
4. The default users migration also creates `sessions`; fighting the skeleton gains nothing.
5. The parser must keep unparseable lines for review instead of dropping them.

**Consequences:**
- A future "delete batch" feature deletes candidates → items → export rows (and TXT files) explicitly, in a transaction.
- Re-searching an item deletes its old candidates first.

## 2026-10-13 — Import parser rules

**Decision:**
- Split on the first spaced dash (`-`, `–`, `—`); unspaced hyphens never split.
- Clean lines: skip empty lines, strip leading numbering and timestamps. Bare numbers are stripped only when zero-padded (`01`).
- `normalized_key`: medium normalization (ASCII, lowercase, no `feat.`, no `(Original Mix)`, `&` → `and`, no punctuation); remixes/extended mixes stay distinct.
- A line without a separator becomes an item with `artist = null` and `error_message = "Artist not detected"`.
- The parser is a pure service; persistence happens in the Livewire component.

**Reason:**
- Artist names contain hyphens (`Jay-Z`); titles contain ` - ` (`Song - Edit`).
- Tracklists from YouTube/set descriptions are numbered and timestamped. Stripping every bare number would break `50 Cent` / `808 State`.
- Merging different versions as exact duplicates could delete a track the user wants; similar-duplicate warnings cover looser matches.
- The user should fix unparseable lines in review, not lose them.

**Consequences:**
- Editing artist/title in Parse Review must recompute `normalized_key` via `ImportParserService::normalizedKey()`.

## 2026-10-13 — App layout and theme

**Decision:**
- Left sidebar navigation (collapsible on mobile).
- Light/dark theme follows the OS; no toggle for now.
- Accent color configurable via `APP_ACCENT` in `.env` (red default; indigo, emerald, amber, sky, violet, navy). No Settings screen in MVP.
- `navy` (added 2026-10-13, the user's choice locally) is adaptive: dark navy in light mode, light blue (`sky`) in dark mode.
- `/` is a placeholder `Dashboard` Livewire component; the default `welcome.blade.php` and the default `ExampleTest` feature test were removed.

**Reason:**
- Sidebar leaves room for future sections (Beatport, local inventory, Rekordbox).
- Following the OS needs no extra state.
- A config value is cheap; using `accent-*` classes everywhere makes a later UI picker easy.

**Consequences:**
- Use `accent-*` (never a fixed color like `red-*`) for primary actions, links and active states; `zinc` for neutrals; always add `dark:` variants.
- A Settings screen with an accent picker is listed under "After MVP".

## 2026-10-13 — New Import screen

**Decision:**
- App timezone `Europe/Warsaw` via `APP_TIMEZONE` (was hard-coded `UTC`).
- Year and month are optional and empty by default.
- Track list limit: 1–200 detected tracks.
- After Parse, temporarily redirect to the Dashboard with a flash message; switch to Parse Review when it exists.
- Batch + item creation lives in `ImportBatchService::createFromRawInput()` (transaction); the component only validates.

**Reason:**
- The auto-generated name and UI dates must match the user's clock.
- The spec makes year/month optional.
- 200 covers big lists; the YouTube API quota (~100 searches/day) is the real limit and falls back to no-key mode anyway.
- Keeps this step atomic.

**Consequences:**
- `Imports\Create::save()` has a `TODO(parse-review)` for the redirect.
- Year/month are string properties in the component (empty form fields are empty strings) and cast to int on save.

## 2026-10-13 — Parse Review editing

**Decision:**
- Autosave each field on blur (`wire:model.live.blur` + `updatedItems()` hook); no Save button. (Was `wire:model.blur`, which in Livewire ≥ 4.1 only syncs client state and sends no request; fixed 2026-10-15.)
- Rows can be removed with a confirm dialog.
- Search YouTube button is shown disabled ("Coming soon") until search exists.
- Batch header on review is read-only.
- Editing logic lives in `ImportItemService::updateTrackDetails()` (squish, blank → null, recompute `normalized_key`, set/clear the missing-artist notice).

**Reason:**
- Nothing is lost when the tab is closed; Search YouTube does not need to save edits first.
- Junk lines are common in pasted tracklists.

**Consequences:**
- The component keeps editable values in `$items` keyed by item id; every action re-checks that the item belongs to the batch (`$batch->items()->findOrFail()`).
- `searchYoutube()` will not need to save edits.

## 2026-10-13 — AI parsing of pasted track lists

**Decision:**
- Parse pasted track lists with AI when an AI provider key is set; otherwise keep the line parser. No hand-written Beatport parser (user's choice: "AI only").
- Laravel AI SDK (`laravel/ai`) with a structured-output agent `TrackListParser`.
- Provider **OpenAI** by default (user has an OpenAI key), provider default model (`gpt-6.1-sol` in laravel/ai 1.2). Provider and model are configurable in `.env` (`AI_PARSER_PROVIDER`, `AI_PARSER_MODEL`), so Claude can be used later without code changes.
- Run in the background (`ParseImportBatchWithAiJob`, no automatic retries); Parse Review polls and offers retry / simple parser on failure.
- Extract only artist, title (version in parentheses) and genre; BPM/key/label are not stored (no columns yet).
- This supersedes "AI parsing" in the "Do not implement yet" list (removed from CLAUDE.md and PRD out-of-scope).

**Reason:**
- Real input is store copy-pastes (Beatport spreads one track over ~8 lines) and other formats the line parser cannot handle.
- Long lists can take minutes; a queued job avoids hanging requests.
- Retries cost money, so they are manual.

**Consequences:**
- New batch status `parsing`; `failed` also covers AI failures.
- `phpunit.xml` forces an empty `OPENAI_API_KEY`; tests enable AI via config and fake the agent (`preventStrayPrompts()`).
- `DB_QUEUE_RETRY_AFTER=300` (> job timeout 240 s).
- Without a key the app still works exactly as before.

## 2026-10-13 — AI parser model: gpt-4.1-mini

**Decision:** Use OpenAI `gpt-4.1-mini` for track list parsing (`AI_PARSER_MODEL=gpt-4.1-mini`, also in `.env.example`).

**Reason:** Extraction is simple; a small model is cheap and fast. Smoke test on two Beatport tracks: correct artists, titles with "(Original Mix)", genre, "Exclusive" badge removed, 3.4 s.

**Consequences:** Switch models via `.env` only. If results get worse on messy inputs, try a larger model first.

## 2026-10-13 — Save & close, imports list, AI usage and settings

**Decision:**
- Parse Review gets a **Save & close** button: sets the batch to a new status `reviewed` (between `parsed` and `searching`) and redirects to a new **imports list** (`/imports`), where the user can continue a batch later. Field edits keep autosaving on blur.
- **AI usage logging:** a listener on the SDK event `AgentPrompted` writes every call to a new table `ai_usage_logs` (batch id if known, agent, provider, model, input/output tokens, estimated `cost_usd`, duration, status). Model prices live in config; the cost is an estimate.
- **Monthly AI budget (USD)**, counted from the 1st of the month: warning at 80%. Once it is reached, new imports use the line parser instead of AI and show a "budget reached" message.
- **API key in the database, encrypted** (`settings` table, `encrypted` cast, `APP_KEY`). A key set in the UI overrides `.env`, and `.env` stays as the fallback. The UI only shows a masked key (`sk-…abcd`), with Replace / Remove / Test key.
- Screen **`/settings/ai`** (later `/settings/youtube`): spend vs. limit, call count, tokens, recent calls table, form (key, model, limit).

**Reason:** User feedback after the first manual test: there is no way to come back to a reviewed import, and the AI spend should be visible and capped from inside the app (the OpenAI key has a hard $4 cap).

**Consequences:**
- New tables `ai_usage_logs`, `settings`; new enum case `ImportBatchStatus::Reviewed`.
- The key is resolved at runtime (DB first, then `.env`). Losing `APP_KEY` makes the stored key unreadable, so the user has to enter it again.
- Order of work: Save & close + `/imports` → usage logging → settings screen.
- Implementation details (2026-10-13): the model is also editable in Settings (overrides `AI_PARSER_MODEL`); **Test key** calls the free `GET /v1/models` (no tokens, OpenAI only); the key is applied per call (`withApiKey()`), not at boot, so artisan/migrations never touch the `settings` table; a budget of 0 means AI is always off.

## 2026-10-13 — Track metadata: BPM, key, label

**Decision:**
- New nullable columns on `import_items`: `bpm` (small int), `musical_key` (as written in the source, e.g. `E Minor`), `camelot_key` (derived, e.g. `9A`), `label`.
- Filled only by AI parsing and only when the source lists them (no guessing); the line parser leaves them empty. Camelot is computed in PHP from `musical_key` (no AI cost) and recomputed after edits.
- `TrackListParser` instructions and schema change: stop ignoring BPM/key/label and return them.
- Not stored: release date, release name, price (user's choice), store badges.
- Parse Review: a grey second line under the title (`128 BPM · E Minor (9A) · tszr`); click the row to edit these fields.
- The TXT export does not change.

**Reason:** Store copy-pastes (Beatport) contain DJ-relevant data, and the user wants it kept. It is also useful for sorting/filtering and the later Rekordbox export.

**Consequences:** Revisit the field set when playlist imports (Rekordbox, Traktor) arrive: they carry more data (e.g. decimal BPM, rating, comments), and the answer may be more columns or a jsonb `metadata` column. One migration, a key → Camelot mapper with unit tests, prompt and schema change (slightly more output tokens), and the AI tests' fake responses get the new fields.

## 2026-10-14 — Duplicates within a batch

**Decision:**
- **Exact duplicate:** same `normalized_key` (unchanged rules). **Similar:** same similar key but a different `normalized_key`. The similar key is the title without any bracketed part (version, remix) plus the artists split on `&`, `,`, `and`, `x`, `vs` and sorted. So other versions and swapped artists match.
- **Typos (added 2026-10-14 after the manual test):** also similar when one part (sorted artists or base title) is equal and the other differs by at most 2 character edits (Levenshtein) that change less than half of the shorter text. Catches `Brunello` / `Brunel` and `Glue` / `Glu`; accepted false alarm: `Teach Me` / `Reach Me` (it is only a warning). Typos in both parts are not matched.
- Both are **computed on the fly** (`DuplicateDetectionService`, from the batch items), not stored. `ImportItemStatus::Duplicate` stays unused for now.
- The exact-duplicate **modal opens once right after parsing**: after a line-parser import (flash `check_duplicates`), when AI parsing finishes (`checkParsing`) or after "Use simple parser". Later visits show an amber banner with **Review duplicates**. No migration.
- **Remove duplicates** keeps the first item of each group (lowest line number) and deletes the rest, without copying metadata from the removed copies (user's choice). **Keep all** closes the modal and hides the banner until reload. **Review manually** closes the modal; duplicate rows stay highlighted ("Duplicate of #N").
- Similar tracks: non-blocking note above the table plus "Similar to #N" in the row.

**Reason:** User's answers (2026-10-14). Computing on the fly keeps the marks correct after every edit or removal without syncing a status.

**Consequences:** Cross-batch warnings (after MVP) can reuse the same keys; if they need an index, the similar key can become a stored column then.

## 2026-10-14 — No-key YouTube search and custom links

**Decision:**
- Search query = artist + title with only `(Original Mix)` removed; other versions, remixes and `feat.` stay. No artist → title only.
- **Search YouTube** on Parse Review runs synchronously in no-key mode (the only mode until the API key arrives): stores `youtube_search_url`, items and batch become `searched` (exported statuses kept), redirect to YouTube Results. Running it again regenerates the URLs.
- Custom links: an **inline field per row** on YouTube Results (autosave), not the planned custom link modal. A valid link ticks Include in export automatically.
- Work split in two steps: A (search URLs + results screen), B (normalizer + inline link + include checkbox), one commit each.

**Reason:** User's answers (2026-10-14). The inline field is the fastest flow for ~30 tracks: open the search, copy the video URL, paste it in the row.

**Consequences:** The "Custom link modal" is dropped from UI-FLOW. For `searched` / `exported` batches (`ImportBatchStatus::hasSearchResults()`), YouTube Results is reachable directly: **YouTube links** on the imports list and **YouTube links →** on Parse Review (user feedback 2026-10-15: entering only through Search YouTube was not OK). Search YouTube is only needed to (re)generate the search URLs.

## 2026-10-16 — Supersimple TXT export flow

**Decision:**
- Generate TXT on YouTube Results. Tracks without a link → missing links modal (Add links first / Export anyway). Existing export → version modal (Create new version / Overwrite latest / Cancel), as planned.
- The file **downloads automatically** after generation (`download-export` event → `window.location` to `exports.download`), and a "Download again" bar stays on the screen.
- File content: URLs joined by `\n`, no trailing newline.
- Overwrite keeps the latest version's filename and number. Exported items and the batch get the `exported` status.
- Work in two steps: 1) export + download, 2) Exports list `/exports`.

**Reason:** User's answers (2026-10-16). The planned `ExportController@download` stays the only controller, so the same download link works on YouTube Results now and on the exports list later.

**Consequences:** Files live in `storage/app/private/exports/{batch id}/`. A future "delete batch" must delete these files too.

## 2026-10-17 — Dashboard "Needs attention"

**Decision:** Three kinds of rows: imports to review (`parsed`), searched/exported imports with tracks without a link, and imports whose latest TXT is outdated. "Outdated" means the file content differs from the links that would be exported now (compared on the fly), not "an item was edited after the export". AI parsing failures are not listed (user's choice). Recent imports and exports: 10 each, with View all.

**Reason:** User's answers (2026-10-17). Comparing contents avoids false alarms from edits that do not affect the TXT (title, BPM, label).

**Consequences:** The dashboard reads the latest export file of every exported batch on each load; fine for a local single-user app, revisit if exports grow large.

## 2026-10-17 — Deleting imports and exports

**Decision:**
- Imports can be deleted from the imports list, export files from the exports list: one by one (row **Delete**) or several at once (checkboxes, select-all per page, **Delete selected**).
- Always through a confirmation modal with a summary (tracks and TXT files that will disappear). No soft deletes, no undo.
- Deleting an import deletes its YouTube candidates, items, export rows and TXT files explicitly, in a transaction (foreign keys stay `restrictOnDelete`, as decided in "Database"). AI usage logs stay (`nullOnDelete`).
- Deleting an export keeps the import; if it was the import's last export, the import and its exported items go back to `searched`.

**Reason:** User request (2026-10-17) after test imports piled up; user chose bulk selection and the summary modal.

**Consequences:** `ParseImportBatchWithAiJob` gets `#[DeleteWhenMissingModels]` so a queued job for a deleted batch does not fail.

## 2026-10-17 — Music library after MVP, remove unused SQLite file

**Decision:**
- Finish YouTube API mode first (closes Milestone 1), then build a **music library** as the new Milestone 2: unique tracks, library screen with filters, **playlists**, and import from the music folder on disk (file scan + tags). The library replaces the user's spreadsheets.
- No spreadsheet (Numbers/XLS/CSV) importer: the user's music folder is the source of truth for the existing collection.
- Beatport moves to Milestone 3, Rekordbox stays Milestone 4. The former "Local music inventory" milestone is merged into the library.
- `database/database.sqlite` (from the default Laravel install) is deleted; app and tests use PostgreSQL only.

**Reason:** User's answers (2026-10-17): the user wants to move their track list from spreadsheets into the app and organize playlists; the music already lives in one folder.

**Consequences:** The library milestone needs its own Q&A before code: data model (`tracks` table vs import items), how the Sail container reaches the host music folder (volume mount), and which tag reader (new package, needs approval). `CLAUDE.md` "Do Not Implement Yet" still applies until that milestone starts.

## 2026-10-17 — YouTube API mode: behaviour

**Decision:**
- **Search YouTube** uses the API automatically when `YOUTUBE_DATA_API_KEY` is set; without a key it stays in no-key mode. Per-item fallback to the search URL on `quotaExceeded` / API errors (A1).
- Progress on YouTube Results is counted from the batch's items (`search_pending` vs done); no column for the `job_batches` id, no migration.
- Candidates are shown as compact text rows (title · channel · score), no thumbnails.
- Re-running the search skips items with a custom (manually pasted) link, so manual work is not overwritten and no quota is spent on them.
- Work in atomic steps: 0) smoke test of the key, 1) `YoutubeSearchService` + cache, 2) scoring, 3) job + `Bus::batch()` + fallback, 4) candidates UI + progress, 5) real-API test and weight tuning.

**Reason:** User's answers (2026-10-17), all as recommended.

**Consequences:** A custom link is recognised as "item has `selected_youtube_url` but `youtube_score` is null" (custom links store no score). The API returns HTML-encoded titles (`&amp;`, `&#39;`), so `YoutubeSearchService` must decode them before storing and scoring.

## 2026-10-17 — YouTube scoring weights (start)

**Decision:** Title words up to 40, artist words up to 30, channel = artist/label 15, official marker 5, requested version found 10; remix mismatch −25; each unwanted keyword (live, set, podcast, lyrics, sped up, slowed, nightcore, cover, reaction, tutorial, karaoke, 8D, 1 hour) −30 unless the track contains it. Clamped to 0–100. Edits (Radio Edit, Extended) are not penalized when the original is requested; only other producers' versions (remix, rework, bootleg, VIP, flip, refix) are. Search fetches 10 results; the best 3 are kept.
**Reason:** User accepted the proposal as a starting point (2026-10-17).
**Consequences:** Weights are constants in `YoutubeScoringService`; tune in step 5. Known cases from the first real search: "(NOT official video)" still gets the official bonus; official-looking re-uploads on fan channels reach 75 (preselected, not auto-included).

## 2026-10-17 — YouTube quota tracking: own call log

**Decision:** Every real YouTube Data API request is logged in a new `youtube_api_calls` table (step 3a, before the queued search) with estimated quota units: 100 per `search.list`, also for failed requests (Google charges invalid requests); 0 when the quota is already exceeded or the connection failed. Cache hits are not logged. Display (today's usage / remaining of 10,000 units, reset at midnight Pacific Time) comes later with the UI.
**Reason:** User request (2026-10-17): track the free search quota. Google does not expose the remaining quota through the API, only in Cloud Console; logging from the first real search keeps the count complete (option A).
**Consequences:** The count is an estimate: calls made with the same key elsewhere are not seen. Like `ai_usage_logs`, the log keeps its rows when an import is deleted (`nullOnDelete`). The two manual test calls of 2026-10-17 (step 0 and step 1 check) are not in the log.

## 2026-10-17 — YouTube API search: implementation details (step 3)

**Decision:**
- Search YouTube with a key searches only items **without any selected link** (not only those without a custom link): links chosen by the API earlier are kept too. To search one item again, clear its link field.
- Failure notices go into `import_items.error_message` ("YouTube search failed — use the search link." / "YouTube API quota used up for today — use the search link."); a successful search resets it to the missing-artist notice or null. Item status is `searched` in both cases (the search URL is a valid fallback result); `failed` is not used.
- After a daily quota error, a cache flag blocks further API requests until midnight Pacific Time, so the remaining jobs of the batch (and later searches) fall back without spending requests. Short rate limits do not set the flag.
- The batch status after the search is derived: `exported` if it has an export file, otherwise `searched`. `searching` counts as "has search results" (Continue / YouTube links open YouTube Results).
**Reason:** Agreed plan (2026-10-17) applied to the existing data model; skipping any linked item spends no quota on tracks that are done and never overwrites a choice.
**Consequences:** An item whose title was edited after an API pick keeps the old link until the user clears it. A search failure replaces the missing-artist notice for that item until the next successful search or edit.

## 2026-10-17 — Search progress: polling now, websockets maybe later

**Decision:** YouTube Results shows the API search progress by polling (`wire:poll.2s`, option A of three): progress bar, rows fill in as they are found, summary at the end. Rejected for now: B) a blocking "Searching…" modal on Parse Review with a redirect when done (makes the user wait); C) push updates over websockets (Laravel Reverb + Echo).
**Reason:** User's choice (2026-10-17). Polling needs no new packages or containers and is effectively instant for a local single-user app.
**Consequences:** Websockets (Reverb as an extra Sail service, events broadcast from jobs, Echo in the browser) can be reconsidered for long background work, e.g. scanning the music folder in the Library milestone. Noted in ROADMAP.

## 2026-10-17 — Add tracks to an existing import

**Decision:** An **Add tracks** button on Parse Review and YouTube Results opens a modal to paste more tracks (a track list, parsed like New Import: AI when enabled, otherwise one `Artist - Title` per line). Tracks already in the import (same normalized key) are skipped with a notice. On Parse Review the user stays there; on YouTube Results the new tracks are searched right away (API mode with progress, or search links). AI parsing runs synchronously in the request (not queued) for this modal.
**Reason:** User's answers (2026-10-17): 1) button on both screens, 2) paste a track list as before (not YouTube links), 3) skip tracks already in the import, 4) search right away on YouTube Results. Running AI in the request keeps the modal simple (no extra `parsing` state on a batch that already has items); added lists are usually short.
**Consequences:** Long AI pastes block the modal for up to a few minutes (`set_time_limit(240)`). Shared code lives in a trait `App\Livewire\Concerns\AddsTracks` (new folder `app/Livewire/Concerns/`) and a Blade partial. Adding is blocked while a search is running, to avoid two search batches at once.

## 2026-10-17 — YouTube quota counter

**Decision:** Show today's estimated YouTube quota use as one line ("YouTube quota: N / 10,000 units used today (~M searches left) · resets 09:00") on Dashboard, Parse Review and YouTube Results (only with an API key), plus a minimal Settings → YouTube screen (quota today + whether the key is set; no history, no key stored in the database). The daily limit is `YOUTUBE_DAILY_QUOTA` (default 10,000). Amber from 80%, red when less than one search is left or the API reported `quotaExceeded`.

**Reason:** User choice (2026-10-17): see the remaining searches before and during a search; a fuller screen (7 days, recent calls, key in the database) is not needed now. The limit is configurable in case Google raises it.

**Consequences:** The count is an estimate from this app's own `youtube_api_calls` rows (other apps using the same key are not counted). The day starts at midnight Pacific Time, so `created_at` is compared after converting that moment to the app timezone. A stored YouTube key and call history can be added to Settings → YouTube later.

## 2026-10-18 — Import from a YouTube playlist

**Decision:**
- New Import gets two tabs: **Paste list** (as now) and **YouTube playlist** (a playlist URL field). Same import name and the same flow afterwards (Parse Review → YouTube Results). Adding a playlist to an existing import (Add tracks) is not part of this step.
- Videos are read with `playlistItems.list` (1 quota unit per 50 videos, logged in `youtube_api_calls`). Needs the YouTube API key; without it the tab is disabled with a hint (no scraping). Only public and unlisted playlists (private ones need OAuth). Deleted and private videos are skipped and counted in a notice.
- Artist/Title: video titles (with the channel name) go through the existing parser: AI when enabled, otherwise the line parser. For `X - Topic` channels the artist is X and the title is the video title. Noise like "(Official Video)" is removed.
- Each track gets its playlist video as the selected link with Export checked, so YouTube Results needs no search (0 search units). The link can be changed by hand.
- Playlists longer than 200 videos: the first 200 are imported, with a notice saying how many were skipped.

**Reason:** User's answers (2026-10-18), all recommended options. A playlist already contains the chosen videos, so searching again would only spend quota.

**Consequences:** Playlist imports always need the API key. The import-time limit (200) stays the same for both tabs.

## 2026-10-18 — Playlist import: repeated videos and YouTube Mixes

**Decision:** A playlist import keeps each video once (by `video_id`) and counts the repeats in the notice ("Skipped N repeated videos."). A YouTube Mix link (`list=RD…`, except curated `RDCLAK…` playlists) is read from its first page only (up to 50 videos), with a notice that it is a Mix.

**Reason:** User report (2026-10-18): a Mix link (`watch?v=…&list=RD…&start_radio=1`) produced 200 tracks full of duplicates. Mixes are auto-generated and endless: the API keeps returning a next page with mostly the same ~50 videos, so the import ran up to the 200 limit. User chose options 1 + 2 (dedupe + one page for Mixes) over rejecting Mixes.

**Consequences:** A Mix import gives at most 50 tracks and costs 1 quota unit. A normal playlist that contains the same video twice gets it once.

## 2026-10-18 — Playlist import: titles parsed by a dedicated AI agent in the background

**Decision:**
- A separate agent `PlaylistTitleParser` gets numbered video titles with channel names and returns `{number, artist, title}`, so each result maps to exactly one video (and its link). `X - Topic` videos are not sent to AI (artist = X, title = video title).
- Tracks with their links are stored right away with the simple parser (Topic rule + `Artist - Title` + title noise removed). When AI is enabled and some videos are not Topic, the batch is `parsing` and a queued job updates artist/title in place; Parse Review shows "Parsing…" as for pasted lists. If the job fails, the simple-parser result stays and the batch becomes `parsed` (nothing is lost, no retry needed).
- Without AI, a title without a separator gets no artist and the "Artist not detected" notice (the channel is often a label, so it is not used as the artist).

**Reason:** User's answers (2026-10-18), all recommended. Matching by number keeps links on the right tracks; the existing `TrackListParser` returns tracks without a link to the input lines.

**Consequences:** Two AI agents to maintain. AI usage is logged for the batch as for pasted lists.

## 2026-10-18 — Export track list (without YouTube search)

**Decision:** An **Export track list** button opens a modal to choose **TXT** (`Artist - Title`, one per line, like a paste) or **CSV** (Artist, Title, Genre, BPM, Key, Camelot, Label, YouTube URL when there is one). On Parse Review (top), YouTube Results and as a row action on the imports list. The file is generated on request and downloaded right away: no versions, no rows on `/exports` (Supersimple TXT keeps its history). Built after the YouTube playlist import.

**Reason:** User request (2026-10-18): the list is needed even without YouTube links, e.g. to merge many playlists and sources into one set for an 80s party (Polish and international). Answers: playlist import first, both formats, all three places, download only.

**Consequences:** The file always reflects the current state of the import. A stored history can be added later if needed.
