# Roadmap

## Milestone 1 — YouTube Supersimple TXT Exporter (MVP)

- [ ] Environment: Sail, project-local PostgreSQL, Livewire
- [ ] Migrations + models (5 tables)
- [x] Dashboard (needs attention, recent imports/exports)
- [x] Delete imports and export files (single + bulk, confirmation modal)
- [ ] New import page + auto-generated name
- [x] Import parser (`Artist - Title`)
- [x] AI parsing of any pasted format (Laravel AI SDK, OpenAI by default)
- [x] Save & close + imports list
- [x] Add tracks to an existing import
- [x] AI usage logging + Settings → AI (key, model, monthly budget)
- [x] Parse review (edit artist/title/genre, BPM/key/label)
- [x] Duplicate detection (exact modal, similar warning)
- [x] YouTube search URL generator (no-key mode) + YouTube Results screen
- [ ] YouTube Data API search + cache
- [ ] Candidate scoring + preselect/include rules
- [x] Candidate selection (API mode)
- [x] Custom link (inline field, validation + normalization)
- [x] Include-in-export toggle
- [x] Supersimple TXT export + missing-link modal
- [x] Export versioning + overwrite modal
- [x] Export history (`/exports`) + download
- [x] Import from a YouTube playlist (Mixes: first page only, repeated videos skipped)
- [x] Export track list (TXT `Artist - Title` / CSV with all fields), download only

## Milestone 2 — Music library (planned, after MVP)

Replaces the user's spreadsheets (Numbers/XLS) as the main track list. Planned 2026-10-17, model and scope agreed 2026-10-19 (DECISIONS "Music library (Milestone 2): model and scope"). First use case: one 80s party set from many playlists and sources.

- [ ] Step 1: library tracks (one track = one version, matched by `normalized_key`), **Add to library** after review (exact duplicates merge, similar ones ask), library screen (search, filters: genre, BPM, Camelot, label, year, rating, tags, POF / PON / Wanted), track sources ("in N imports")
- [ ] Step 2: playlists of library tracks, manual order (drag and drop), add from the library and from Add to library
- [ ] Step 3: import from the music folder on disk (scan files, read tags, "Have file" status) — takes over the scope of the former "Local music inventory" milestone; Q&A first (path, Sail mount, formats, tag reader package)

## Milestone 3 — Beatport integration / import helper

Not started. Do not implement until requested.

## Milestone 4 — Rekordbox export

Not started. Rekordbox XML export.

## Known future enhancements

- Live updates over websockets (Laravel Reverb) instead of polling, if long background jobs appear (e.g. music folder scan). See DECISIONS 2026-10-17.

- RabbitMQ queue.
- Spotify / SoundCloud sync.
- BPM/key detection, fingerprinting.
