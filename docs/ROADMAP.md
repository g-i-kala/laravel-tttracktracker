# Roadmap

## Milestone 1 — YouTube Supersimple TXT Exporter (MVP)

- [ ] Environment: Sail, project-local PostgreSQL, Livewire
- [ ] Migrations + models (5 tables)
- [ ] Dashboard
- [ ] New import page + auto-generated name
- [x] Import parser (`Artist - Title`)
- [x] AI parsing of any pasted format (Laravel AI SDK, OpenAI by default)
- [x] Save & close + imports list
- [x] AI usage logging + Settings → AI (key, model, monthly budget)
- [ ] Parse review (edit artist/title/genre)
- [ ] Duplicate detection (exact modal, similar warning)
- [ ] YouTube search URL generator (no-key mode)
- [ ] YouTube Data API search + cache
- [ ] Candidate scoring + preselect/include rules
- [ ] Candidate selection, custom link (validation + normalization)
- [ ] Include-in-export toggle
- [ ] Supersimple TXT export + missing-link modal
- [ ] Export versioning + overwrite modal
- [ ] Export history + download

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
