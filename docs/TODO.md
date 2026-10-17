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
- [x] Build dashboard (2026-10-17)
- [x] Delete imports / export files: single + bulk selection, confirmation modal with summary (2026-10-17)
- [ ] Manual test delete: imports list (row + selected), exports list (row + selected), last export deleted → import back to Searched
- [x] Manual test dashboard: needs attention rows + links, recent imports/exports, View all (2026-10-17)
- [x] Build import create page (redirect to review pending)
- [x] Implement parser (+ `normalized_key`)
- [x] AI parsing of any pasted format (Laravel AI SDK, queued, retry / simple-parser fallback)
- [x] Set `OPENAI_API_KEY` in `.env` (smoke test OK with `gpt-4.1-mini`)
- [x] ~~Rotate the OpenAI key~~ — not rotating: key is capped at $4 (user decision, 2026-10-13)
- [ ] (optional) Scrub the key from Claude Code transcripts in `~/.claude/projects/...`
- [ ] Try a full real Beatport paste in the UI (user)
- [x] Parse review page (edit + autosave + remove + duplicates + Search YouTube)
- [x] Save & close on Parse Review (`reviewed` status) + imports list `/imports`
- [x] AI usage logging (`ai_usage_logs`, `AgentPrompted` listener, cost from config prices)
- [x] Settings → AI (`/settings/ai`): usage stats, encrypted API key, model, monthly budget with fallback to line parser
- [x] Track metadata from AI: `bpm`, `musical_key` + `camelot_key`, `label` (second line under title on Parse Review, editable)
  - [x] Columns + `MusicalKeyService` (Camelot)
  - [x] AI agent instructions/schema + storing the fields
  - [x] Parse Review UI (metadata line + panel on row click)
- [x] "Saved" did not flash after editing a field on Parse Review: replaced with an `item-saved` event + Alpine (2026-10-14, confirmed manually)
- [x] Manual test: new Beatport import → metadata line, edit BPM/key/label, "Saved" flash (2026-10-14)
- [x] Duplicate detection (exact: modal once after parse + banner; similar: warning)
- [x] Manual test: import with exact and similar duplicates → modal, Remove / Keep all / Review manually, marks, typo warning (2026-10-14)
- [x] YouTube search URL service (no-key mode — primary at start) + Search YouTube + YouTube Results screen (step A, 2026-10-14)
- [x] Manual test step A: Search YouTube → YouTube Results, search links open in a new tab, imports list Continue (2026-10-14)
- [x] Step B: YouTube URL normalizer + inline custom link field per row (autosave, valid link auto-includes) + include-in-export checkbox (2026-10-14)
- [x] Manual test step B: paste links, Export checkbox, direct entry from imports list and review (2026-10-16)
- [ ] YouTube API search + cache + quota fallback
- [ ] Scoring service (title-based)
- [ ] Candidate selection (API mode)
- [x] Supersimple TXT export (sync) + versioning + download (export step 1, 2026-10-16)
- [x] Manual test export step 1: Generate TXT → missing links modal → file downloads; second export → version modal (new / overwrite); Download again (2026-10-17)
- [x] Export step 2: Exports list `/exports` (history, download older versions) + enable "Exports" in the sidebar (2026-10-17)
- [x] Manual test export step 2: sidebar Exports → list, Latest badge, Download, import link (2026-10-17)

## After MVP

- [ ] Settings screen (accent color picker; now `APP_ACCENT`)
- [ ] Light/dark toggle (now follows the OS)
- [ ] Cross-batch duplicate warning ("already exported in batch X")
