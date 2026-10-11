# TODO

## Setup

- [x] Reformat `CLAUDE.md`
- [x] Install Laravel Boost
- [x] Create `docs/` and required doc files
- [x] Initialize git repository
- [x] Install Sail with `pgsql` service (`compose.yaml`: PHP 8.4, postgres:16, `queue` service)
- [x] Switch `.env` / `.env.example` to Sail PostgreSQL (`tttracktracker`, port 5433) + `YOUTUBE_*`
- [x] Architecture review (Q&A) — decisions in `DECISIONS.md`
- [ ] `sail build` + `sail up -d` + `sail artisan migrate` (first run)
- [ ] Re-run `boost:install` for Sail
- [ ] VS Code cleanup: remove `.cursor/`, `opencode.json`; add `.vscode/extensions.json`
- [ ] Slim down `CLAUDE.md` (spec → `docs/`)
- [ ] Add Vue 3 to Vite
- [ ] Decide: remove `database/database.sqlite`?
- [ ] Get YouTube Data API key

## MVP

- [ ] Status enums (`ImportBatchStatus`, `ImportItemStatus`)
- [ ] Create migrations (incl. `job_batches`, `normalized_key`)
- [ ] Create models + factories
- [ ] `config/services.php` YouTube entry
- [ ] Build dashboard
- [ ] Build import create page
- [ ] Implement parser (+ `normalized_key`)
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
