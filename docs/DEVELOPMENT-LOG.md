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
