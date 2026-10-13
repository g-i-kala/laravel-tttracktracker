# PRD — DJ Library Agent (TTTrackTracker)

## MVP definition

Milestone 1: **YouTube Supersimple TXT Exporter**.

A local, single-user Laravel app that turns a pasted track list into a TXT file containing only YouTube URLs, one per line.

```text
Manual track import
→ parse Artist/Title
→ review/edit
→ search YouTube or generate manual YouTube search URLs
→ select/customize links
→ generate Supersimple TXT export
```

## Glossary

| Term              | Meaning                                                                 |
| ----------------- | ----------------------------------------------------------------------- |
| Import Batch      | A group of imported tracks, e.g. `YouTube Export 2026-10-11 18:30`.     |
| Import Item       | One track/line inside a batch. Raw line `Adam Beyer - Teach Me (Original Mix)` → artist `Adam Beyer`, title `Teach Me (Original Mix)`. No separate version field in MVP. |
| YouTube Candidate | A possible YouTube result for an item. API mode stores up to 3.         |
| Export File       | A generated TXT file. Supersimple export contains only selected URLs.   |

## User flow

1. **Dashboard** — New YouTube Export button, recent imports, recent exports, items needing attention.
2. **New YouTube Export** — name (auto-generated, editable), optional year/month/default genre, track list textarea → **Parse**.
3. **Parse Review** (always shown) — edit artist/title/genre; exact-duplicate modal; similar-duplicate warning → **Search YouTube**.
4. **YouTube Results** — API mode: 3 scored candidates, best preselected; no-key mode: search URL + custom link.
5. **Supersimple TXT Export** — missing-link warning modal, existing-export version modal, download.

Details: [UI-FLOW.md](UI-FLOW.md).

## Functional requirements

### Import & parsing
- Paste 10–30 tracks in a textarea, one per line.
- Parse `Artist - Title`. Remix/version stays inside title (no separate field).
- Optional genre per item; batch default genre.

### Duplicates
- Exact duplicates → modal: Remove duplicates / Keep all / Review manually.
- Similar duplicates → non-blocking warning.
- Detection uses a stored `normalized_key`. Cross-batch warnings ("already exported in batch X") come after MVP.

### Organization
- Optional year/month on a batch, used to filter/organize imports.
- UI language: English.

### YouTube
- No-key mode is the primary mode at start; API mode is built in parallel.
- API mode when `YOUTUBE_DATA_API_KEY` is set: up to 3 candidates per track, scored 0–100 (title-based scoring).
- If the API quota is exceeded or the API fails, the item falls back to no-key mode (search URL + notice in UI).
- Score >= 80 → preselect + include in export; 60–79 → preselect only; < 60 → not included.
- No-key mode: generate `https://www.youtube.com/results?search_query=...`.
- Custom link accepts `watch?v=`, `youtu.be/`, `shorts/`; normalized to `https://www.youtube.com/watch?v=ID`; non-YouTube URLs rejected.
- Search results cached (`youtube_search_cache`).
- All links open in a new tab.

### Export
- TXT includes an item only if `include_in_export = true` and `selected_youtube_url` is not null.
- No comments, titles, metadata, empty lines, or placeholders.
- Filename: `{slug}_youtube_links.txt`, then `_v2`, `_v3`, …
- Export history stored; files downloadable.

## Acceptance criteria

- [ ] App runs locally via Sail.
- [ ] App has own PostgreSQL container.
- [ ] No auth is required.
- [ ] User can create new import.
- [ ] Import name auto-generates and can be edited.
- [ ] User can paste 10–30 tracks.
- [ ] System parses `Artist - Title`.
- [ ] Parse review is always shown.
- [ ] User can edit artist/title/genre.
- [ ] Exact duplicates show modal.
- [ ] Similar duplicates show warning.
- [ ] API mode fetches 3 YouTube candidates.
- [ ] No-key mode generates YouTube search URL.
- [ ] Links open in new tab.
- [ ] User can select candidate.
- [ ] User can paste custom YouTube link.
- [ ] Custom link is validated and normalized.
- [ ] User can toggle `include_in_export`.
- [ ] Export creates TXT with only URLs.
- [ ] Missing links are skipped after warning.
- [ ] Export is stored in history.
- [ ] Re-export can create `_v2`, `_v3`, etc.
- [ ] User can download TXT.

## Out of scope (MVP)

Audio downloading, Spotify sync, SoundCloud sync, Beatport integration, Rekordbox XML, local file scanning, BPM/key detection, fingerprinting, user accounts/auth, AI parsing, RabbitMQ, SPA/Inertia.

## Roadmap

See [ROADMAP.md](ROADMAP.md).
