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
| Library Track     | One version of a track in the music library (Milestone 2). Import items link to it via Add to library. |
| POF / PON / Wanted | Playable offline (have a file) / playable online (has a link) / want a file. |

## User flow

1. **Dashboard** — New YouTube Export button, recent imports, recent exports, items needing attention.
2. **New YouTube Export** — name (auto-generated, editable), optional year/month/default genre, track list textarea → **Parse**.
3. **Parse Review** (always shown) — edit artist/title/genre, plus BPM/key/label (panel on row click); exact-duplicate modal; similar-duplicate warning → **Search YouTube**.
4. **YouTube Results** — API mode: 3 scored candidates, best preselected; no-key mode: search URL + custom link pasted in the row.
5. **Supersimple TXT Export** — missing-link warning modal, existing-export version modal, download.

Details: [UI-FLOW.md](UI-FLOW.md).

## Functional requirements

### Import & parsing
- Paste tracks in a textarea, one per line (typical 10–30; validated: 1–200 detected tracks).
- Parse `Artist - Title`. Remix/version stays inside title (no separate field).
- **AI parsing** (when an AI API key is set): any pasted format — plain lines, store copy-pastes (e.g. Beatport, one track over several lines), tracklists with timestamps. Extracts artist, title (with version in parentheses) and genre. Runs in the background; Parse Review shows progress and offers retry / simple parser on failure.
- Optional genre per item; batch default genre.
- **Save & close** on Parse Review; imports list to continue any import later.
- Add tracks to an existing import (Parse Review / YouTube Results): paste more tracks, duplicates of tracks already in the import are skipped; on YouTube Results they are searched right away.
- Delete imports (with their tracks and TXT files) and single export files, one by one or several at once, after a confirmation modal with a summary.

### AI settings and usage
- Settings → AI: this month's estimated spend, calls, tokens, recent calls (date, import, model, tokens, cost, time, status).
- API key entered in the UI, stored encrypted, shown masked; overrides `.env`; **Test key** (free provider check).
- Model (default `gpt-4.1-mini`) and optional monthly budget in USD: warning at 80%, at 100% new imports use the line parser until next month.

### Duplicates
- Exact duplicates (same artist + title after normalization) → modal right after parsing: Remove duplicates (keeps the first) / Keep all / Review manually. Later: banner + row badges.
- Similar duplicates (other version, e.g. Extended Mix vs Remix, or artists in another order, or a small typo in either the artist or the title, e.g. Brunello / Brunel) → non-blocking warning.
- Detection uses a stored `normalized_key`. Cross-batch warnings ("already exported in batch X") come after MVP.

### Organization
- Optional year/month on a batch, used to filter/organize imports.
- UI language: English.

### YouTube
- No-key mode is the primary mode at start; API mode is built in parallel.
- API mode when `YOUTUBE_DATA_API_KEY` is set: up to 3 candidates per track, scored 0–100 (title-based scoring).
- If the API quota is exceeded or the API fails, the item falls back to no-key mode (search URL + notice in UI).
- Score >= 80 → preselect + include in export; 60–79 → preselect only; < 60 → not included.
- No-key mode: generate `https://www.youtube.com/results?search_query=...` from artist + title without `(Original Mix)` (other versions, remixes, feat. kept).
- Custom link: pasted into a field in the track's row on YouTube Results (autosave); a valid link ticks Include in export.
- Custom link accepts `watch?v=`, `youtu.be/`, `shorts/` (also `embed/`, `live/`, `m.` and `music.` hosts); normalized to `https://www.youtube.com/watch?v=ID`; non-YouTube URLs rejected.
- Search results cached (`youtube_search_cache`).
- All links open in a new tab.

### Export
- TXT includes an item only if `include_in_export = true` and `selected_youtube_url` is not null.
- No comments, titles, metadata, empty lines, or placeholders.
- Filename: `{slug}_youtube_links.txt`, then `_v2`, `_v3`, …
- Export history stored; files downloadable. Generate TXT downloads the file right away and leaves a "Download again" bar.
- Re-export: modal "Create new version" (`_v2`, old file kept) / "Overwrite latest" / Cancel.

### Export track list
- **Export track list** (Parse Review, YouTube Results, imports list): modal to choose **TXT** (`Artist - Title` per line, only the title without an artist, like a paste) or **CSV** (header `Artist, Title, Genre, BPM, Key, Camelot, Label, YouTube URL`, comma-separated, UTF-8 with BOM).
- Every track of the import in line order, regardless of Export ticks or links. Generated on request and downloaded right away: no history, not listed on `/exports`.
- Filename: `{slug}_tracks.txt` / `{slug}_tracks.csv`.

### Music library (Milestone 2, in progress)
- **Add to library** (Parse Review, YouTube Results): choose tracks in a modal (those not in the library preselected), optional **Mark as wanted**. A track with the same artist + title (same version) links to the library track and fills its empty fields; a possibly matching track (other version, swapped artists) asks **Same track / New track** (default New track). Rows show **In library**. See DECISIONS 2026-10-19.
- **Library screen** (`/library`): search, filters (POF / PON / Wanted / none, genre, Camelot, BPM and year ranges), 50 per page, sortable columns. **POF** per row (one click) or for ticked tracks (Mark as POF / Unmark POF). **Edit a track** in an expanded row (autosave; artist, title, genre, BPM, year, key, label, YouTube link, notes, POF / Wanted) and see its sources (imports). **Remove** tracks one by one or the ticked ones (imports stay, items lose "In library"). Merge on an edit collision follows (1c-iv).
- Library track = one version. Availability: **POF** (playable offline, stored), **PON** (playable online, from the link), **Wanted** (want a file; cleared when POF).

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
- [x] Exact duplicates show modal.
- [x] Similar duplicates show warning.
- [ ] API mode fetches 3 YouTube candidates.
- [x] No-key mode generates YouTube search URL.
- [x] Links open in new tab.
- [ ] User can select candidate.
- [x] User can paste custom YouTube link.
- [x] Custom link is validated and normalized.
- [x] User can toggle `include_in_export`.
- [x] Export creates TXT with only URLs.
- [x] Missing links are skipped after warning.
- [x] Export is stored in history.
- [x] Re-export can create `_v2`, `_v3`, etc.
- [x] User can download TXT.

## Out of scope (MVP)

Audio downloading, Spotify sync, SoundCloud sync, Beatport integration, Rekordbox XML, local file scanning, BPM/key detection, fingerprinting, user accounts/auth, RabbitMQ, SPA/Inertia.

## Roadmap

See [ROADMAP.md](ROADMAP.md).
