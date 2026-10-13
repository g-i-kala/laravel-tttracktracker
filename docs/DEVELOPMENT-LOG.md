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
