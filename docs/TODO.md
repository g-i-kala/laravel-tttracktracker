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
- [ ] App layout (`layouts/app.blade.php`) + `<x-modal>` component
- [ ] Build dashboard
- [ ] Build import create page
- [x] Implement parser (+ `normalized_key`)
- [ ] Parse review page
- [ ] Duplicate detection
- [ ] YouTube search URL service (no-key mode — primary at start)
- [ ] YouTube URL normalizer
- [ ] YouTube API search + cache + quota fallback
- [ ] Scoring service (title-based)
- [ ] Candidate selection / custom link / toggle export
- [ ] Supersimple TXT export (sync) + versioning
- [ ] Export history + download

## After MVP

- [ ] Cross-batch duplicate warning ("already exported in batch X")
