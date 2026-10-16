# Development Log

## 2026-10-11

### Changed
- Reformatted `CLAUDE.md` into proper Markdown (headings, lists, code blocks, tables).
- Added "Working Rules" section to `CLAUDE.md` (atomic task execution).

### Added
- `laravel/boost` 2.10.3 (dev), `php artisan boost:install --guidelines --skills --mcp`.
  - Boost guidelines appended to `CLAUDE.md` and `AGENTS.md`.
  - Skills in `.claude/skills`, `.agents/skills`, `.cursor/skills`.
  - MCP config: `.mcp.json`, `.cursor/mcp.json`, `opencode.json`; `boost.json`.
- `docs/` with required files: START-TUTAJ, PRD, ARCHITECTURE, TECH-STACK, ROADMAP, DATABASE, API, UI-FLOW, DOCKER, DECISIONS, DEVELOPMENT-LOG, TODO, TESTING.

### Notes
- Project is a fresh Laravel 13.35 skeleton; Sail, Vue, app migrations not yet present.
- `.env` points to shared `pg-dev`; needs switching to Sail `pgsql`.
- Boost installed with `sail: false`; re-run installer after Sail setup.

## 2026-10-11 (2)

### Changed
- `compose.yaml`: Sail runtime PHP 8.4 (Symfony 8 requires >= 8.4.1), `postgres:16`, host DB port `5433` (`pg-dev` uses 5432), `laravel.test` waits for healthy `pgsql`, testing DB init script kept.
- `.env` / `.env.example`: `APP_NAME=TTTrackTracker`, `APP_PORT=8000`, Sail DB (`pgsql`, `tttracktracker`, `sail`/`password`), `FORWARD_DB_PORT=5433`, `YOUTUBE_*` keys.
- `CLAUDE.md`: app/DB name, DB `.env` example, PHP 8.4 note, queue section (Bus::batch, worker service, sync export).
- Docs updated after architecture review: DATABASE, ARCHITECTURE, DOCKER, TECH-STACK, PRD, TODO, START-TUTAJ.

### Added
- `queue` service in `compose.yaml` (`queue:work --tries=3 --timeout=90`, `restart: unless-stopped`).
- Architecture review decisions (A1–D3) in `DECISIONS.md`.

### Notes
- Rest of `CLAUDE.md` spec (statuses, schema, jobs) is still the old version; to be resolved in the CLAUDE.md slim-down task (spec moves to `docs/`).
- First `sail build` / `sail up` not run yet.

## 2026-10-11 (3)

### Changed
- Documented the `sail` shell alias in DOCKER.md and START-TUTAJ.md (alias already exists in `~/.zshrc`).
- Added session handoff notes to START-TUTAJ.md.
- CLAUDE.md Working Rules: read START-TUTAJ.md at session start, update handoff at session end, Conventional Commits.

## 2026-10-11 (4)

### Changed
- START-TUTAJ.md: status and handoff updated.

### Notes
- First `sail up` done: containers running, app online at http://localhost:8000.
- Work continues next session.
- Decision: Livewire instead of Vue (DECISIONS.md, C2 superseded). Other docs still mention Vue, to be updated next session.
- Verified: 3 containers up (laravel.test, pgsql healthy, queue), default migrations ran, queue worker running.
- `boost:install` re-run with Sail: `boost.json` `sail: true`, `.mcp.json` uses `vendor/bin/sail`, Boost guidelines in `CLAUDE.md` now use Sail commands.
- Added DOCKER.md troubleshooting entry for worker restarting before first migrate.
- Removed Boost's Cursor/OpenCode files (`.cursor/`, `opencode.json`); only Claude Code is used.

## 2026-10-13

### Changed
- Slimmed down `CLAUDE.md` (1253 → 370 lines): kept project summary, Working Rules, documentation policy (docs map table, update rules, dev-log format), tech stack summary, Code Style, Safety, Commands, Communication Style, Do Not Implement Yet and the Boost block. Removed the spec sections (data model, statuses, user flow, export rules, URL validation, scoring, `app/` structure, routes, acceptance criteria, testing strategy), which already live in `docs/` in their newer, post-review form.
- `CLAUDE.md` now states that `docs/` is the source of truth and lists Livewire as the frontend.
- Working Rules: added "we work as a team: ask questions when unclear or when there are several decisions".

### Added
- Glossary section in PRD.md (moved from `CLAUDE.md` "Product Concepts").

## 2026-10-13 (2)

### Changed
- Docs updated for Livewire (Vue removed): ARCHITECTURE (structure, data flow, queue polling, frontend section), API (page routes + component actions table, no JSON API), UI-FLOW (screens as components, modals), TECH-STACK, ROADMAP, TESTING (`Livewire::test()`, Sail commands), DECISIONS.
- TECH-STACK: Boost section corrected (Sail, Claude Code only).

### Added
- `livewire/livewire` ^4.4 (Composer).
- `config/livewire.php` published; `make_command.type` set to `class`.
- DECISIONS: "Livewire conventions" (full-page, class-based, own `<x-modal>`, one component per screen).

### Notes
- Test suite (2 default tests) passes.

## 2026-10-13 (3)

### Changed
- Ran `sail artisan boost:update --no-interaction`: Boost detected Livewire, added `livewire/core` rules to the Boost block in `CLAUDE.md` and the `livewire-development` skill; `infer-conventions` skill refreshed. Our part of `CLAUDE.md` untouched.
- TECH-STACK: Boost skills list + `boost:update` note.

### Notes
- Used `boost:update` instead of a full `boost:install` (keeps existing config, only adds what's new).

## 2026-10-13 (4)

### Added
- Enums `ImportBatchStatus`, `ImportItemStatus` (`app/Enums/`).
- Migrations: `import_batches`, `import_items`, `youtube_candidates`, `export_files`, `youtube_search_cache` (restrict FKs, indexes per DATABASE.md, unique export version and query hash).
- Models with `#[Fillable]`, `casts()`, relationships; `YoutubeSearchCache` uses `#[Table('youtube_search_cache')]`.
- Factories for all 5 models (states: `ImportBatch::parsed`, `ImportItem::withSelectedYoutubeLink` / `withSearchUrlOnly`, `YoutubeSearchCache::expired`).
- `tests/Feature/ImportModelsTest.php` (5 tests).

### Changed
- `DatabaseSeeder`: removed Test User creation.
- DATABASE.md rewritten with column types, FK behavior, factories; DECISIONS "Schema conventions"; D1 marked done.

### Notes
- Ran `sail artisan migrate` (5 new migrations) and `sail artisan test` (7 passed). Pint clean.

## 2026-10-13 (5)

### Added
- `config/services.php` `youtube` entry: `api_key`, `auto_include_threshold`, `preselect_threshold`, `candidates_limit`, `cache_ttl_days`.
- `.env` / `.env.example`: `YOUTUBE_PRESELECT_THRESHOLD=60`, `YOUTUBE_CACHE_TTL_DAYS=30`.

### Changed
- ARCHITECTURE (config table), DOCKER (`.env`), DECISIONS (B5 consequences), TODO, START-TUTAJ.

### Notes
- Verified with `sail artisan config:show services.youtube`.

## 2026-10-13 (6)

### Added
- `App\Services\ImportParserService`: `parse()` (line cleanup, first spaced-dash split, missing-artist notice) and `normalizedKey()`.
- `tests/Unit/ImportParserServiceTest.php` (34 cases with data providers).

### Changed
- ARCHITECTURE: "Import parsing" section. DECISIONS: "Import parser rules". TESTING, TODO, START-TUTAJ.

### Notes
- Zero-padded-only rule for bare numbering (keeps `50 Cent`, `808 State`).
- `sail artisan test`: 41 passed. Pint clean.

## 2026-10-13 (7)

### Added
- `resources/views/layouts/app.blade.php`: sidebar layout, mobile Menu toggle, flash area, `#[Title]` page titles, `data-accent` from `config('app.accent')`.
- `<x-modal>` (Blade + Alpine `x-modelable`, Esc/backdrop/× close, focus trap) and `<x-nav-link>` (disabled "Coming soon" until the route exists).
- Accent palettes in `resources/css/app.css` (`accent-*` colors via CSS variables): red (default), indigo, emerald, amber, sky, violet. `APP_ACCENT` in `.env`/`.env.example`, `config/app.php` `accent`.
- Placeholder `App\Livewire\Dashboard` at `/` (route `dashboard`) with a "How it works" modal.
- `tests/Feature/DashboardTest.php` (4 tests).

### Changed
- Removed `welcome.blade.php` and the default `tests/Feature/ExampleTest.php`.
- Ran `sail npm install` + `sail npm run build` (first time).
- Docs: UI-FLOW, ARCHITECTURE, API, TECH-STACK, DOCKER, DECISIONS ("App layout and theme"), TODO, TESTING, START-TUTAJ.

### Notes
- `sail artisan test`: 44 passed. Pint clean. `GET /` → 200.

## 2026-10-13 (8)

### Added
- Adaptive `navy` accent palette: shifted `blue` scale (navy) in light mode, `sky` in dark mode (`prefers-color-scheme`).

### Changed
- Local `.env`: `APP_ACCENT=navy` (default in `config/app.php` / `.env.example` stays `red`).
- `config/app.php` comment, DOCKER, ARCHITECTURE, DECISIONS.

## 2026-10-13 (9)

### Added
- New Import screen: `App\Livewire\Imports\Create` at `/imports/create` (route `imports.create`): name (auto, editable), optional year/month/default genre, track list with live detected counter, validation 1–200 tracks.
- `App\Services\ImportBatchService::createFromRawInput()`: batch + items in a transaction, status `parsed`, default genre on items.
- `tests/Feature/Imports/CreateImportTest.php` (7 tests).

### Changed
- `config/app.php` timezone reads `APP_TIMEZONE`; `.env` / `.env.example`: `APP_TIMEZONE=Europe/Warsaw`.
- Sidebar/Dashboard "+ New YouTube Export" is now an active link.
- Docs: API, UI-FLOW, ARCHITECTURE, DATABASE (timezone note), PRD (limit), DOCKER, TECH-STACK, DECISIONS ("New Import screen"), TODO, TESTING, START-TUTAJ.

### Notes
- `sail artisan test`: 51 passed. Pint clean. Assets rebuilt.

## 2026-10-13 (10)

### Added
- Parse Review: `App\Livewire\Imports\Review` at `/imports/{batch}/review` (route `imports.review`): read-only batch header, editable table with autosave on blur, "Saved" indicator, remove row with confirm, disabled Search YouTube.
- `App\Services\ImportItemService::updateTrackDetails()`.
- `tests/Feature/Imports/ParseReviewTest.php` (7 tests).

### Changed
- New Import now redirects to Parse Review (flash "Imported N tracks. Review them before searching YouTube.").
- Docs: API, UI-FLOW, ARCHITECTURE, DECISIONS ("Parse Review editing"), TODO, TESTING, START-TUTAJ.

### Notes
- `sail artisan test`: 58 passed. Pint clean. Assets rebuilt.

## 2026-10-13 (11)

### Added
- `laravel/ai` ^1.2 + `config/ai.php` (published config only, no conversation migrations).
- `App\Ai\Agents\TrackListParser` (structured output: tracks with artist/title/genre/source).
- `App\Services\AiTrackListParserService` (`isEnabled()`, `parse()`), `App\Jobs\ParseImportBatchWithAiJob`.
- `ImportBatchService`: `createForAiParsing()`, `retryAiParsing()`, `parseWithLineParser()`, `storeParsedLines()`.
- `ImportBatchStatus::Parsing`.
- New Import AI mode; Parse Review parsing/failed states (`checkParsing`, `retryAiParsing`, `useLineParser`).
- `config/services.php` `track_list_ai`; `.env` / `.env.example`: `OPENAI_API_KEY`, `AI_PARSER_PROVIDER`, `AI_PARSER_MODEL`, `AI_PARSER_TIMEOUT`, `DB_QUEUE_RETRY_AFTER=300`.
- `tests/Feature/Ai/AiTrackListParsingTest.php` (9 tests); `phpunit.xml` forces empty `OPENAI_API_KEY`.

### Changed
- CLAUDE.md: AI parsing removed from "Do not implement yet"; AI summary in Tech Stack.
- Docs: ARCHITECTURE (AI parsing section), API, UI-FLOW, DATABASE, PRD, ROADMAP, TECH-STACK, DOCKER, DECISIONS, TODO, TESTING, START-TUTAJ.

### Notes
- Trigger: a Beatport copy-paste was split into one junk item per line by the line parser.
- `sail artisan test`: 67 passed. Pint clean. `queue:restart` done. Assets rebuilt.
- Not yet tried against the real OpenAI API (no key in `.env` yet).

## 2026-10-13 (12)

### Changed
- AI parser model set to `gpt-4.1-mini` (`.env`, `.env.example`, DOCKER, ARCHITECTURE, DECISIONS).

### Notes
- Real API smoke test (2 Beatport tracks): correct artist/title/genre, 3.4 s.
- The OpenAI key was accidentally printed in a session log (masking `sed` did not work on macOS); user advised to rotate it.

## 2026-10-13 (13)

### Changed
- START-TUTAJ: status rewritten, session handoff for 2026-10-13 (done list, uncommitted changes, key incident, next steps). TODO: key rotation decision.

### Notes
- Leaked OpenAI key not rotated: user capped it at $4. Risk accepted.
- End of session, user runs `/clear`.

## 2026-10-13 (Save & close, imports list)

### Added
- `ImportBatchStatus::Reviewed` (`reviewed`) + `label()` for UI badges; factory state `reviewed()`.
- `ImportBatchService::markReviewed()` (only `parsed` → `reviewed`).
- Parse Review: **Save & close** (`saveAndClose()`): validates all fields, flash, redirect to `/imports`.
- Imports list `Imports\Index` (`/imports`, `imports.index`): newest first, track count, status, Continue, pagination, empty state. Sidebar "Imports" is now active.
- Tests: `ImportsIndexTest`, three Save & close cases in `ParseReviewTest`.

### Changed
- `DashboardTest`: Imports is no longer "Coming soon".
- Docs: DATABASE, API, UI-FLOW, ARCHITECTURE, TODO.

### Notes
- User feedback after the first manual test: no way to return to a reviewed import.
- 72 tests pass.

## 2026-10-13 (AI usage logging)

### Added
- Table `ai_usage_logs`, model `AiUsageLog` (+ factory, `failed()` state), enum `AiUsageStatus`.
- `AiUsageService` (`record()`, `estimateCost()`), listener `RecordAiUsage` on `PromptingAgent` / `AgentPrompted` / `AgentFailed` (singleton in `AppServiceProvider`).
- `config('services.ai_pricing')`: `gpt-4.1-mini` $0.40 input / $0.10 cached input / $1.60 output per 1M tokens.
- `AiUsageLoggingTest` (5 tests).

### Changed
- `ParseImportBatchWithAiJob`: parse runs in `Context::scope()` with `import_batch_id` so logs link to the batch.
- Docs: DATABASE, ARCHITECTURE, TESTING, TODO.

### Notes
- Ran `sail artisan migrate` and `queue:restart`. 77 tests pass.
- No UI yet; data is shown on the Settings → AI screen (next step).
- Costs are estimates; verify prices on OpenAI's pricing page when changing the model.

## 2026-10-13 (Settings → AI)

### Added
- Table `settings` (key/value), model `Setting`, `SettingsService` (encrypted secrets).
- `AiSettingsService`: key (Settings ?? `.env`), source, masked key, `withApiKey()`, `testApiKey()`, model, monthly budget, budget usage / reached / 80% warning. `AiUsageService::spentThisMonth()`.
- Screen `Settings\Ai` (`/settings/ai`, `settings.ai`): month stats + progress bar, API key (save / test / remove), model + budget form, recent 20 calls. Sidebar link "AI Settings".
- New Import: budget banners. Review: retry with AI refused when AI is off.
- `AiSettingsTest` (9 tests).

### Changed
- `AiTrackListParserService`: `isEnabled()` = key present and budget not reached; `parse()` uses the settings model and key.
- Docs: PRD, ROADMAP, DATABASE, API, ARCHITECTURE, UI-FLOW, DECISIONS, TESTING, TODO.

### Notes
- Ran `sail artisan migrate`, `queue:restart`, `npm run build`. 86 tests pass.

## 2026-10-13 (Track metadata, step 1)

### Added
- Migration: `import_items.bpm`, `musical_key`, `camelot_key`, `label` (nullable).
- `MusicalKeyService::toCamelot()` + `MusicalKeyServiceTest` (27 cases).
- `ImportItemFactory::withMetadata()`.

### Changed
- `ImportItem` fillable/casts. Docs: DATABASE, ARCHITECTURE, TESTING, TODO, DECISIONS (metadata ADR, revisit with Rekordbox/Traktor imports).

### Notes
- Ran `sail artisan migrate`. 113 tests pass. Not filled yet: the AI agent change is step 2.

## 2026-10-13 (Track metadata, step 2)

### Changed
- `TrackListParser`: instructions and schema return `bpm`, `key`, `label` (only when listed; remixers are not the label).
- `AiTrackListParserService`: maps them to `bpm` (rounded, 40–300), `musical_key`, `camelot_key`, `label`; stored by `storeParsedLines()`.
- Tests: AI parsing tests cover the new fields, BPM rounding/out-of-range, unknown key → no Camelot.

### Fixed
- AI cost was never computed: `config("services.ai_pricing.openai.gpt-4.1-mini")` treats the dots in the model name as nesting. Prices are now read as `config('services.ai_pricing.openai')[$model]`. Regression test reads the shipped config. Tests had set the price with the same dotted path, which hid the bug.

### Notes
- Real smoke test on the user's Beatport paste (4 tracks, last one cut off): artists, titles with "(Original Mix)", genre, BPM, key, Camelot (9A, 7B, 5A) and label correct; cut-off track has only artist + title. 683 input / 261 output tokens, 4.7 s.
- 3 existing `ai_usage_logs` rows had `cost_usd` null because of the bug; backfilled with `estimateCost()` via tinker on 2026-10-14 ($0.000601, $0, $0.000691).
- 115 tests pass.

## 2026-10-14 (Track metadata, step 3)

### Added
- Parse Review: grey metadata line under the title (`128 BPM · E Minor (9A) · tszr`, or `+ BPM, key, label`); clicking the row or the line opens a panel with BPM, Key (Camelot shown, hint when unrecognized) and Label, autosaved on blur.
- Tests: metadata line, key → Camelot recompute, BPM/label save + empty → null, BPM validation (integer, 40–300).

### Changed
- `ImportItemService::updateTrackDetails()` also saves `bpm`, `musical_key`, `label` and recomputes `camelot_key`.
- `Imports\Review`: `items` holds the new fields; validation rules; each item renders in its own `<tbody>` with Alpine state.
- Docs: UI-FLOW, ARCHITECTURE, PRD, TODO.

### Fixed
- "Saved" notice: the `savedItemId` property + self-removing `<span>` was replaced with a Livewire `item-saved` event handled by Alpine in the row (`x-show`, 1.5 s timer), so morphing no longer affects it.

### Notes
- Ran `sail bin pint`, `sail npm run build`. 119 tests pass. Manual browser test by the user passed (metadata line, panel, "Saved" flash).

## 2026-10-14 (Duplicates)

### Added
- `DuplicateDetectionService`: `exactDuplicates()` (same `normalized_key`, first item in line order is the original) and `similarItems()` (same similar key, different normalized key).
- `ImportParserService::similarKey()`: title without bracketed parts + sorted artists (split on `&`, `,`, `and`, `x`, `vs`).
- `ImportBatchService::removeExactDuplicates()`.
- Parse Review: duplicate modal (opens once after parsing), banner "N exact duplicates" with **Review duplicates**, "Duplicate of #N" badge + amber row, similar note + "Similar to #N".
- Tests: `DuplicateDetectionServiceTest`, similar-key cases in `ImportParserServiceTest`, `ParseReviewDuplicatesTest` (8), flash assertion in `CreateImportTest`.

### Changed
- `Imports\Create` flashes `check_duplicates` after a line-parser import.
- `ImportItemFactory`: `normalized_key` computed from the final artist/title with `ImportParserService` (it was computed from the Faker values before overrides).
- Docs: DECISIONS (ADR), DATABASE, ARCHITECTURE, UI-FLOW, PRD, TESTING, TODO.

### Notes
- Ran `sail bin pint`, `sail npm run build`. 139 tests pass.
- Manual test by the user: exact duplicates OK; `Brunello – Ghost Dance (Original Mix)` vs `Brunel – Ghost Dance (Origl Mix)` was not flagged. User chose typo tolerance in the similar warning: one part equal, the other within 2 edits (less than half of the shorter text). `DuplicateDetectionService::similarItems()` now compares pairwise; tests for the typo cases (`typoProvider`). 146 tests pass.

## 2026-10-14 (YouTube search, step A: no-key mode)

### Added
- `YoutubeSearchUrlService` (`query()`, `url()`) + `YoutubeSearchUrlServiceTest`.
- `ImportBatchService::generateYoutubeSearchUrls()`.
- `Imports\Review::searchYoutube()`; the Search YouTube button is active.
- `Imports\Show` (YouTube Results, `/imports/{batch}`, `whereNumber`): track list with Search YouTube ↗ links (new tab), Back to review, Generate TXT placeholder.
- `ImportBatchStatus::hasSearchResults()`; imports list Continue opens YouTube Results for searched / exported batches.
- `YoutubeResultsTest` (7 tests).

### Changed
- Docs: DECISIONS (ADR), API, ARCHITECTURE, UI-FLOW, PRD, TESTING, TODO, START-TUTAJ.

### Notes
- Ran `sail bin pint`, `sail npm run build`. 160 tests pass. Manual browser test by the user passed (search links sensible, Continue opens results).

## 2026-10-14 (YouTube search, step B: custom links)

### Added
- `YoutubeUrlNormalizer` (`videoId()`, `normalize()`) + `YoutubeUrlNormalizerTest` (25 cases).
- `ImportItemService::selectCustomYoutubeLink()`, `clearYoutubeLink()`, `toggleExport()`.
- YouTube Results: inline **YouTube link** field per row (autosave on blur, blur on paste), Watch ↗ link, "Saved" flash, error for invalid links, **Export** checkbox (disabled without a link), header counts "N with a link · N in export".
- 6 feature tests in `YoutubeResultsTest`.

### Changed
- Docs: API, ARCHITECTURE, UI-FLOW (custom link modal removed), PRD, ROADMAP, TESTING, TODO, START-TUTAJ.

### Notes
- Ran `sail bin pint`, `sail npm run build`. 191 tests pass. Manual browser test by the user passed after the 2026-10-15 fixes (paste, Export checkbox, navigation).
- Manual test: pasted links were not saved. `x-on:paste` + `$nextTick(blur)` blurred the field before the browser inserted the pasted text, so `wire:model.blur` saw no change. Fixed with `x-on:input` when `inputType === 'insertFromPaste'` (fires after the text is in the field). Feature tests set the value directly, so they could not catch it.

## 2026-10-15 (Fix: autosave bindings)

### Fixed
- Pasted links on YouTube Results were never sent to the server; Parse Review edits only reached it with the next action (Remove, Save & close…). Cause: `wire:model.blur` in Livewire ≥ 4.1 (installed 4.4.7) only syncs client state on blur, without a request. Changed every autosave field to `wire:model.live.blur` (Review: 6, Show: 1).

### Added
- Regression tests asserting the `wire:model.live.blur` binding (`ParseReviewTest`, `YoutubeResultsTest`).
- DECISIONS: autosave binding rule under "Livewire conventions".

### Notes
- Found from the request log: no `/livewire/update` request after pasting. Verified in headless Chrome (puppeteer-core in the session scratchpad, temporary profile): Cmd+V paste → normalized + Saved + Export ticked; checkbox toggles; invalid link → error; clearing → excluded. Test link removed again afterwards.

### Changed
- Direct entry to YouTube Results (user feedback: only reachable via Search YouTube). Imports list actions: **Review** + **YouTube links** (once searched); Parse Review: **YouTube links →** (once searched). In step A only the batch name had been pointed to results; the Continue link still went to review, and the test only checked that the href existed somewhere on the page. Tests now assert the exact links.
- Docs: UI-FLOW, DECISIONS, TESTING.
- 194 tests pass.
