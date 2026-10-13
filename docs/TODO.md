# TODO

## Setup

- [x] Reformat `CLAUDE.md`
- [x] Install Laravel Boost
- [x] Create `docs/` and required doc files
- [x] Initialize git repository
- [x] Install Sail with `pgsql` service (`compose.yaml`: PHP 8.4, postgres:16, `queue` service)
- [x] Switch `.env` / `.env.example` to Sail PostgreSQL (`tttracktracker`, port 5433) + `YOUTUBE_*`
- [x] Architecture review (Q&A) — decisions in `DECISIONS.md`
- [x] `sail build` + `sail up -d` + `sail artisan migrate` (first run)
- [x] Re-run `boost:install` for Sail (`sail: true`, agent: Claude Code only)
- [x] Remove Boost's Cursor/OpenCode files (`.cursor/`, `opencode.json`)
- [ ] Add `.vscode/extensions.json`
- [x] Slim down `CLAUDE.md` (spec → `docs/`)
- [x] Update docs + `CLAUDE.md` for Livewire, then install Livewire (replaces "Add Vue 3 to Vite")
- [x] Run `boost:update` so Boost adds Livewire guidelines/skills
- [ ] Decide: remove `database/database.sqlite`?
- [ ] Get YouTube Data API key

## MVP

- [x] Status enums (`ImportBatchStatus`, `ImportItemStatus`)
- [x] Create migrations (incl. `normalized_key`; `job_batches` already in default migration)
- [x] Create models + factories
- [x] `config/services.php` YouTube entry
- [x] App layout (`layouts/app.blade.php`) + `<x-modal>` component (+ placeholder Dashboard)
- [ ] Build dashboard
- [x] Build import create page (redirect to review pending)
- [x] Implement parser (+ `normalized_key`)
- [x] AI parsing of any pasted format (Laravel AI SDK, queued, retry / simple-parser fallback)
- [x] Set `OPENAI_API_KEY` in `.env` (smoke test OK with `gpt-4.1-mini`)
- [x] ~~Rotate the OpenAI key~~ — not rotating: key is capped at $4 (user decision, 2026-10-13)
- [ ] (optional) Scrub the key from Claude Code transcripts in `~/.claude/projects/...`
- [ ] Try a full real Beatport paste in the UI (user)
- [x] Parse review page (edit + autosave + remove; duplicates and Search YouTube pending)
- [ ] Save & close on Parse Review (`reviewed` status) + imports list `/imports`
- [ ] AI usage logging (`ai_usage_logs`, `AgentPrompted` listener, cost from config prices)
- [ ] Settings → AI (`/settings/ai`): usage stats, encrypted API key, model, monthly budget with fallback to line parser
- [ ] Duplicate detection
- [ ] YouTube search URL service (no-key mode — primary at start)
- [ ] YouTube URL normalizer
- [ ] YouTube API search + cache + quota fallback
- [ ] Scoring service (title-based)
- [ ] Candidate selection / custom link / toggle export
- [ ] Supersimple TXT export (sync) + versioning
- [ ] Export history + download

## After MVP

- [ ] Settings screen (accent color picker; now `APP_ACCENT`)
- [ ] Light/dark toggle (now follows the OS)
- [ ] Cross-batch duplicate warning ("already exported in batch X")
