# Roadmap

## Milestone 1 — YouTube Supersimple TXT Exporter (MVP)

- [ ] Environment: Sail, project-local PostgreSQL, Livewire
- [ ] Migrations + models (5 tables)
- [x] Dashboard (needs attention, recent imports/exports)
- [ ] New import page + auto-generated name
- [x] Import parser (`Artist - Title`)
- [x] AI parsing of any pasted format (Laravel AI SDK, OpenAI by default)
- [x] Save & close + imports list
- [x] AI usage logging + Settings → AI (key, model, monthly budget)
- [x] Parse review (edit artist/title/genre, BPM/key/label)
- [x] Duplicate detection (exact modal, similar warning)
- [x] YouTube search URL generator (no-key mode) + YouTube Results screen
- [ ] YouTube Data API search + cache
- [ ] Candidate scoring + preselect/include rules
- [ ] Candidate selection (API mode)
- [x] Custom link (inline field, validation + normalization)
- [x] Include-in-export toggle
- [x] Supersimple TXT export + missing-link modal
- [x] Export versioning + overwrite modal
- [x] Export history (`/exports`) + download

## Milestone 2 — Beatport integration / import helper

Not started. Do not implement until requested.

## Milestone 3 — Local music inventory

Not started. Local file scanning, matching tracks to files.

## Milestone 4 — Rekordbox export

Not started. Rekordbox XML export.

## Known future enhancements

- RabbitMQ queue.
- Spotify / SoundCloud sync.
- BPM/key detection, fingerprinting.
