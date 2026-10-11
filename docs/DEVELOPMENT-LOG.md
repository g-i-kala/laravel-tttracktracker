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
