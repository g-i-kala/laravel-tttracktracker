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
- [x] ~~Decide: remove `database/database.sqlite`?~~ Removed (unused, Postgres only; 2026-10-17)
- [x] Get YouTube Data API key (set in `.env`, 2026-10-17)

## MVP

- [x] Status enums (`ImportBatchStatus`, `ImportItemStatus`)
- [x] Create migrations (incl. `normalized_key`; `job_batches` already in default migration)
- [x] Create models + factories
- [x] `config/services.php` YouTube entry
- [x] App layout (`layouts/app.blade.php`) + `<x-modal>` component (+ placeholder Dashboard)
- [x] Build dashboard (2026-10-17)
- [x] Delete imports / export files: single + bulk selection, confirmation modal with summary (2026-10-17)
- [x] Manual test delete: imports list (row + selected), exports list (row + selected), last export deleted → import back to Searched (2026-10-17)
- [x] Manual test dashboard: needs attention rows + links, recent imports/exports, View all (2026-10-17)
- [x] Build import create page (redirect to review pending)
- [x] Implement parser (+ `normalized_key`)
- [x] AI parsing of any pasted format (Laravel AI SDK, queued, retry / simple-parser fallback)
- [x] Set `OPENAI_API_KEY` in `.env` (smoke test OK with `gpt-4.1-mini`)
- [x] ~~Rotate the OpenAI key~~ — not rotating: key is capped at $4 (user decision, 2026-10-13)
- [ ] (optional) Scrub the key from Claude Code transcripts in `~/.claude/projects/...`
- [x] Try a full real Beatport paste in the UI (user, 2026-10-17)
- [x] Confirm `gpt-4.1-mini` prices on the OpenAI site: $0.40 in / $0.10 cached / $1.60 out per 1M tokens, matches `config/services.php` (user, 2026-10-18)
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
  - [x] Step 0: real-key smoke test (2026-10-17)
  - [x] Step 1: `YoutubeSearchService` + cache + error/quota detection (2026-10-17)
  - [x] Step 3: job + `Bus::batch()` + per-item fallback + quota flag (2026-10-17)
  - [x] Manual test step 3 (user, 2026-10-17: works; asked for progress info instead of refreshing)
  - [x] Step 4a: progress bar + rows fill in live + summary (`wire:poll`, 2026-10-17)
  - [x] Step 4b: candidate list (top 3 with scores) + choosing another candidate (2026-10-17)
  - [x] Manual test steps 4a/4b (user, 2026-10-17)
  - [ ] Step 5: real-import test + scoring weight tuning
    - Real-import review done 2026-10-17 (import "YouTube Export 2026-10-17 21:39" + cache). User: works well enough for now, tune later. Agreed plan:
      - Topic channels with several artists: also match artists in the description when it starts with "Provided to YouTube" (What We Got 75 → 90, Noir Muse 68 → 90).
      - No official bonus for "NOT official video" / "unofficial".
      - Marker list, −30: `/`, `vs`, ` x ` or `&` joining titles, `mashup`, `medley`, festivals/performances (Glastonbury, Boiler Room, Tomorrowland, Coachella, Essential Mix, "performs", "at"). Not a generic extra-words penalty (hits good uploads like "Brunello, Vargas – Ghost Dance |[ Indie Dance ]| 2025").
      - Matching version (e.g. Extended Mix) keeps beating the official channel; add "official visualiser/visualizer" to the official bonus.
      - Drop duplicate candidates (same title + channel) so top 3 are distinct.
- [x] Scoring service (title-based) — step 2, 2026-10-17; tune weights in step 5
- [x] Candidate selection (API mode) (2026-10-17)
- [x] YouTube quota tracking (step 3a: `youtube_api_calls` log; display done 2026-10-17: quota line on Dashboard / Parse Review / YouTube Results + Settings → YouTube, `YOUTUBE_DAILY_QUOTA`): log every real `search.list` call (100 units; cache hits cost 0), show today's usage / remaining of 10,000 units (day resets at midnight Pacific Time = 09:00 Warsaw; 08:00 in the few weeks when US and EU daylight saving time differ), e.g. on YouTube Results + a Settings → YouTube screen (requested 2026-10-17)
- [x] Manual test quota counter: line on Dashboard / Parse Review / YouTube Results, sidebar YouTube Settings (user, 2026-10-17)
- [x] Supersimple TXT export (sync) + versioning + download (export step 1, 2026-10-16)
- [x] Manual test export step 1: Generate TXT → missing links modal → file downloads; second export → version modal (new / overwrite); Download again (2026-10-17)
- [x] Export step 2: Exports list `/exports` (history, download older versions) + enable "Exports" in the sidebar (2026-10-17)
- [x] Manual test export step 2: sidebar Exports → list, Latest badge, Download, import link (2026-10-17)

## After MVP

- [x] **Add tracks to an existing import** (requested 2026-10-17, done the same day): modal on Parse Review and YouTube Results, duplicates skipped, new tracks searched right away on YouTube Results
- [x] Manual test Add tracks (user, 2026-10-17)

- [ ] **Import from a YouTube playlist** (requested 2026-10-17, decisions 2026-10-18 in DECISIONS "Import from a YouTube playlist"): "YouTube playlist" tab on New Import, `playlistItems.list` (1 unit / 50 videos), API key required, public/unlisted only, AI or line parser for titles (Topic channels: artist = channel), playlist video = selected link + Export, first 200 videos + notice.
  - [x] Step 1: `YoutubePlaylistService` (playlist ID from link, `playlistItems.list` pages, limit, skipped videos, call log, quota flag) (2026-10-18)
  - [x] Step 2: videos → tracks (Topic channels, title noise, AI agent `PlaylistTitleParser` in the background / line parser, playlist video as selected link + Export) (2026-10-18)
  - [x] Step 3: New Import tabs + notices, headless Chrome check (2026-10-18)
  - [ ] Manual test on a real playlist (user; e.g. an 80s playlist, with AI)

- [x] **Export track list** (done 2026-10-18; requested 2026-10-18, decisions in DECISIONS "Export track list"; after the playlist import): modal TXT (`Artist - Title`) or CSV (all fields + YouTube URL), on Parse Review (top), YouTube Results and the imports list; download only, no history.

- [ ] Settings screen (accent color picker; now `APP_ACCENT`)
- [ ] Light/dark toggle (now follows the OS)
- [ ] Cross-batch duplicate warning ("already exported in batch X")

## Library (Milestone 2, after MVP)

- Use case to test on (user, 2026-10-18): an **80s party set** (Polish and international) built from many playlists and sources into one shared list. Needs: compare playlists/imports, remove duplicates across them, add to the library.
- [x] Questions first: data model, playlists, duplicates, order of work (2026-10-19, DECISIONS "Music library (Milestone 2): model and scope"; folder details postponed to step 3)
- [ ] Step 1: library tracks
  - [x] 1a: `tracks` table + `Track` model/factory, POF/Wanted flags (PON from links), `import_items.track_id` (`nullOnDelete`), `LibraryService` (exact merge fills empty fields, similar match detection, Mark as wanted) (2026-10-19)
  - [x] 1b (2026-10-19): **Add to library** on Parse Review and YouTube Results (whole import / selected) with a **Mark as wanted** checkbox, "Same track / New track" modal for similar matches, "In library" mark on items
  - [ ] Manual test 1b (user): Add to library on Parse Review and YouTube Results, Mark as wanted, Same track / New track, In library badge
  - [ ] 1c: Library screen: list, search, filters (genre, BPM, Camelot, label, year, rating, tags, POF / PON / Wanted), edit a track (incl. POF / Wanted), sources ("in N imports")
  - [ ] 1d: rating 1–5, notes, free tags (`tags` + `tag_track`)
- [ ] Step 2: playlists
  - [ ] 2a: `playlists` + `playlist_track` (position), playlists list + playlist screen with drag and drop order (`wire:sort`)
  - [ ] 2b: add to a playlist from the library and from Add to library; export a playlist (TXT/CSV, Supersimple TXT — not agreed yet, ask)
- [ ] Step 3: music folder scan — Q&A first (path, Sail mount, formats, tag reader package needs approval), `track_files`, "Have file" set automatically
