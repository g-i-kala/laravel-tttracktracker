# UI Flow

> Status: planned. Blade + Livewire full-page components (one per screen), no SPA. UI language: English.

Each screen below is one class-based Livewire component (`app/Livewire/...`). Rows are Blade partials. Modals use our `<x-modal>` Blade component (Alpine).

## Layout (implemented)

- Left sidebar: app name, Dashboard / Library / Imports / Exports, **+ New YouTube Export** button, **AI Settings** and **YouTube Settings** at the bottom. Screens not built yet are shown disabled ("Coming soon"). On mobile the sidebar collapses behind a **Menu** button.
- Theme follows the OS (light/dark). Accent color from `APP_ACCENT`.
- Flash message area above the page content.

## Dashboard (`/`) — `Dashboard`

> Status: implemented (2026-10-17).

- Header: **How it works** (modal) + **+ New YouTube Export** (when there are imports; otherwise in the empty state).
- No imports at all → empty state "No imports yet" with **+ New YouTube Export**.
- **Needs attention** (amber list, each row links to the right screen), or "All caught up…" when empty:
  - imports `To review` (status `parsed`): "N tracks to review" → **Review** (Parse Review);
  - searched / exported imports with tracks without a link: "N of M tracks without a link" → **Add links** (YouTube Results);
  - imports whose latest TXT no longer matches the current links: "links changed since the last TXT" → **Export again** (YouTube Results).
- **Recent imports** (10 newest: name → YouTube Results once searched, else Parse Review; track count; status) and **Recent exports** (10 newest by date: filename, import, link count, date, **Download**), each with **View all**. Two columns on large screens, stacked on mobile.

## Imports (`/imports`) — `Imports\Index`

> Status: implemented (2026-10-13).

- Table of all batches, newest first, 25 per page: name (link), created, track count, status badge (`To review` amber, `Failed` red, others accent), actions.
- **Delete** (2026-10-17): checkbox per row + select-all on the page (selection kept across pages) → bar "N selected · **Delete selected** · Clear selection"; **Delete** per row. Both open the **Delete imports** modal: "Delete “name”?" / "Delete N imports?" + "This removes N tracks and N export files (TXT)." + "This cannot be undone." → **Cancel** / red **Delete**. Afterwards: notice "Deleted N imports."
- Actions: **Review** (Parse Review) always; **YouTube links** (YouTube Results, no new search) once the batch is `searched` / `exported`. The name links to YouTube Results when searched, otherwise to Parse Review.
- **Track list** per row (2026-10-18, disabled without tracks): opens the **Export track list** modal (see below).
- Empty state "No imports yet" + **New YouTube Export** button.

## New Import (`/imports/create`) — `Imports\Create`

> Status: implemented (2026-10-13); YouTube playlist tab (2026-10-18).

- Name (auto-generated in `Europe/Warsaw` time, e.g. `YouTube Export 2026-10-13 18:30`, editable, required, max 255).
- Optional year (2000–2100), month (select January–December), default genre (free text). Empty by default.
- Track list textarea (monospace). Live counter below it: "12 tracks detected · 2 without artist (fix them in review)" (debounced 400 ms, same parser as on save).
- Validation: at least 1 and at most 200 detected tracks.
- **AI mode** (AI key set): subtitle "Paste your track list in any format"; instead of the counter: "AI parsing on · paste any format…"; validation only max 100,000 characters; Parse queues AI parsing and redirects to Parse Review immediately.
- **Parse** button (shows "Parsing…" while saving) → `save()` → items get the default genre → redirect to Parse Review with flash "Imported N tracks. Review them before searching YouTube."
- **Tabs** (2026-10-18): **Paste list** (above) and **YouTube playlist**, kept in the URL (`?source=playlist`); name, year, month and default genre are shared. Switching tabs clears the field errors.
- **YouTube playlist tab:** "Playlist link" field (playlist link, watch link with `list=`, or a bare ID) with the hint "Up to 200 videos · about 1 YouTube quota unit per 50 videos · no YouTube search needed afterwards" (+ "AI cleans up the video titles after the import" with AI). Without a YouTube API key the field and button are disabled and an amber hint links to YouTube Settings. **Import playlist** ("Importing…") reads the videos in the request; errors show under the field (not a playlist link, not found / private, quota used up, no playable videos, connection). Success → Parse Review with flash "Imported N tracks from the playlist, each with its video as the link." + "Skipped N deleted or private videos." + "This link is a YouTube Mix (auto-generated and endless), so only its first page of videos is imported." + "Skipped N repeated videos." + "Only the first 200 videos are imported; N more were left out." when relevant. With AI, Parse Review shows "Cleaning up the video titles with AI… The links are already in place" while the job runs.

## Parse Review (`/imports/{batch}/review`) — `Imports\Review`

> Status: editing + removing implemented (2026-10-13), BPM/key/label editing (2026-10-14). Duplicates (2026-10-14). Search YouTube pending.

- Always shown after parse (New Import redirects here).
- While AI parsing runs: spinner panel "Parsing your track list with AI…" (polls every 2 s), then the table appears.
- If AI parsing failed: red panel with **Try again with AI** and **Use simple parser**.
- Header (read-only): batch name, track count, "N without artist", month/year, default genre.
- Editable table in line order: #, artist, title (raw line shown below in grey), genre.
- **Metadata line** under the title (grey): `128 BPM · E Minor (9A) · tszr` (Camelot in brackets unless the key is already Camelot; empty parts skipped), or `+ BPM, key, label` when empty.
- **Clicking a row** (outside inputs/buttons) or the metadata line toggles a panel under the row: BPM (whole number 40–300), Key (as written; the Camelot code is shown next to the label and recomputed on save; amber hint when the key is not recognized), Label. The panel opens automatically when one of its fields has a validation error.
- **Autosave on blur**: each field saves when it loses focus; "Saved" flashes next to the row for 1.5 s (Livewire event `item-saved` → Alpine); validation errors inline (title required, max 255).
- Saving recomputes `normalized_key`; filling in an artist clears "Artist not detected", clearing it sets the notice again. Rows without artist have an amber border.
- **Remove** per row with a browser confirm ("Remove this track?").
- Exact duplicates (same artist + title after normalization) → **Duplicate modal** "Duplicates found", opened **once right after parsing** (line parser, AI parsing finished, or "Use simple parser"). Lists each group: `#1 Artist - Title · also #3, #7`. Buttons:
  - **Remove duplicates**: keeps the first of each group, deletes the rest, notice "Removed N duplicates."
  - **Keep all**: closes the modal and hides the banner until reload.
  - **Review manually**: closes the modal.
- Later visits: amber banner "N exact duplicates in this import." with **Review duplicates** (opens the modal). Duplicate rows have an amber background and a "Duplicate of #N" badge under the title.
- Similar tracks (other version, artists in another order, small typo in artist or title) → note above the table "N tracks look similar … nothing is removed" and "Similar to #N" under the title. Non-blocking.
- Marks update after every edit or removal.
- **Save & close** button (hidden while parsing / failed): validates all fields, marks the batch `reviewed` (a later status is kept), flash "Saved \"name\".", redirects to the imports list.
- **YouTube links →** link (once searched): opens YouTube Results without searching again.
- **Add tracks** button (hidden while parsing / failed / searching) → modal "Add tracks" (2026-10-17): textarea (any format with AI, one `Artist - Title` per line without), **Cancel** / **Add tracks** (spinner text while parsing), **Use simple parser** after an AI failure. New tracks go to the end, tracks already in the import are skipped; notice "Added N tracks. N already in this import were skipped." Also on YouTube Results (header, disabled while searching), where the new tracks are searched right away.
- **Search YouTube** button (hidden while parsing / failed, disabled with no tracks) → `searchYoutube()`: validates all fields, generates search URLs, redirects to YouTube Results. Can be run again after edits (URLs are regenerated). With `YOUTUBE_DATA_API_KEY` (2026-10-17) it also queues the API search for tracks without a link; YouTube Results shows the preselected links in the row field after a page refresh (progress bar and candidate list: step 4). Tracks that already have a link (pasted or chosen) are not searched again; clear the field to search one again.

## YouTube Results (`/imports/{batch}`) — `Imports\Show`

> Status: step A implemented (2026-10-14): header (name, count, month/year, genre), **Back to review**, **Generate TXT** disabled ("Coming soon"), table #, track (artist bold + title, grey line `BPM · Camelot · label`), **Search YouTube ↗** link per row (new tab, `rel="noopener noreferrer"`). Reachable directly from the imports list (**YouTube links**) and from Parse Review (**YouTube links →**) once the batch is `searched` / `exported`.
> Step B implemented (2026-10-14): header also shows "N with a link · N in export". Per row: **YouTube link** field (placeholder "Paste YouTube link"; saves on blur, and pasting blurs the field so it saves right away). Valid link → normalized watch URL shown in the field, **Watch ↗** link, "Saved" flash, Include ticked. Invalid → red border + error, the previous link stays. Emptying the field removes the link and unticks Include. **Export** checkbox per row (disabled until a link is set).

> Step 4a implemented (2026-10-17): search progress. While the batch is `searching`: banner "Searching YouTube… N of M tracks ready" + progress bar (ready = tracks not `search_pending`), polled every 2 s (`wire:poll.2s="checkSearchProgress"`, only rendered while searching, so polling stops by itself). Pending rows show a spinner "Searching…" instead of the link field (Export checkbox disabled). Each poll fills in only the fields of tracks finished since the last poll, so typing in another row is not overwritten. When the batch finishes: dismissible summary "YouTube search finished: N linked and included · N to check (link found, not included) · N not found · N failed (use the search link)" (only if the search was watched on this screen). Search failure notices (failed / quota) appear under the row's link field.

- While the API search batch is running: progress (e.g. 12/30) via `wire:poll`, which stops when the batch finishes. (implemented, see above)
> Step 4b implemented (2026-10-17): candidates. Under the link field: "score N" of the selected API link and a **N candidates ▾** toggle (Alpine; open by default when the track has candidates but is not included in the export, i.e. 60–79 or not found). Each candidate: radio, score badge (green ≥ 80, amber 60–79, grey < 60), video title (link ↗, new tab), channel. Choosing a radio → `selectCandidate()`: the video becomes the track's link (field updated, "Saved" flash) and the track is included in the export. A pasted custom link keeps the list; no radio is selected unless it is the same video. Rows are keyed by item id + status, so a row that finishes searching renders fresh (list open when it needs checking).

- API mode: best candidate preselected; expand a row (Alpine) to see the 3 candidates; select another. (implemented, see above)
- No-key mode (or API fallback): **Search YouTube ↗** + inline **YouTube link** field (replaces the planned custom link modal, see DECISIONS 2026-10-14).
- Include-in-export checkbox per item.
- All links `target="_blank"`.

## Export (on YouTube Results)

> Status: implemented (2026-10-16).

- **Generate TXT** button (header; disabled with "Paste at least one link first" until a track is included with a link) → `export()`.
- **Missing links modal** "Some tracks have no link": "N tracks have no YouTube link and will be skipped. The TXT will contain N links.", list of those tracks; **Add links first** (closes) / **Export anyway**. Close (×, Escape, backdrop) = cancel.
- **Export version modal** "Export already exists" (filename, date, link count): **Cancel** / **Overwrite latest** / **Create new version**.
- After export: the file downloads automatically, and a green bar shows "Exported N links · filename · date · **Download again**" (latest export of the batch, also on later visits).

## Export track list (Parse Review, YouTube Results, imports list)

> Status: implemented (2026-10-18).

- **Export track list** button: first in the Parse Review header, after "Back to review" on YouTube Results; **Track list** as a row action on the imports list. Disabled when the import has no tracks.
- Modal "Export track list" (Alpine only, no request): "Download all N tracks of “name”, with or without YouTube links." + two options: **TXT** (`Artist - Title`, one per line, like a paste) and **CSV** (Artist, Title, Genre, BPM, Key, Camelot, Label, YouTube URL; opens in Numbers, Excel or Google Sheets) → the file downloads and the modal closes. **Cancel**.
- Not listed on `/exports`; the file always reflects the current state of the import.

## Library (`/library`) — `Library\Index`

> Status: list, filters and sorting (1c-i), expanded row editing (1c-ii), removing tracks (1c-iii) and merge on an edit collision (1c-iv) implemented (2026-10-19). and merge on an edit collision (1c-iv) follow; decisions in DECISIONS "Library screen (step 1c)".

- Header "Library" + "N tracks. Add more from an import with Add to library." (with filters: "X of N tracks match the filters.").
- Filter panel: **Search** (artist, title or label; accents ignored), **Genre** and **Key** (Camelot) selects with the library's values, **BPM** from–to, **Year** from–to; **Availability** chips **POF** / **PON** / **Wanted** / **None** (combined: a track must match all chosen; None = none of the three). **Clear filters** when any is set. Filters and sorting are kept in the URL.
- Table, 50 per page: Artist, Title (label below), Genre, BPM, Key (Camelot; full key on hover), Year, badges **POF** (green) / **PON** (link to the YouTube video) / **Wanted** (amber), Added. Sortable headers: Artist (default, A–Z), Title, BPM, Key (wheel order), Year, Added; clicking the active header flips the direction (↑/↓). Empty values last.
- **POF** (2026-10-19, user request): in every row a **POF** button: dashed grey when off, green when on; a click switches it (turning it on clears Wanted). Checkbox column + "select all on this page" in the header (selection kept across pages and filters); when tracks are ticked a bar shows "N selected" · **Mark as POF** · **Unmark POF** · Clear selection → notice "Marked N tracks as playable offline (POF)." / "Removed POF from N tracks.". Delete selected comes with 1c-iii.
- **Expanded row** (1c-ii): clicking a row (not a checkbox, button or link) opens a panel under it; one row open at a time, a second click closes it. Fields autosaved on blur ("Saved" flash): Artist, Title, Genre, BPM, Year, Key (Camelot shown; hint when not recognized), Label, YouTube link (PON; normalized, empty = no link, **Open** link), Notes; checkboxes **POF** "I have the file" and **Wanted** "I want the file" (saved right away; Wanted disabled while POF). Errors under the field (BPM 40–300, year 1900–next year, YouTube link format). An artist/title that matches another library track opens the merge modal (below). **Sources**: each import item linked to the track (import name → Parse Review / YouTube Results, `#line Artist - Title`), or "Not linked to any import."
- **Rating and tags** (1d, 2026-10-19): **Rating** column with 5 clickable stars (amber; click sets, clicking the current rating clears; sortable); same stars in the expanded row. Tags as `#tag` chips under the title. Expanded row: **Tags** chips with × and an "Add tag + Enter" field with suggestions (`<datalist>` of existing tags). Selection bar: tag field + **Add tag** / **Remove tag** (Enter = Add tag) → "Tagged N tracks with “tag”." / "Removed “tag” from N tracks." ("No such tag." for an unknown name). Filters: **Rating** select (Any, ★+ … ★★★★★) and **Tags (all of)** select "Add tag…" → chosen tags as accent chips with × next to the availability chips (a track must have all of them; in the URL as `tags[]`). Tags match regardless of case; a tag left on no track disappears.
- **Remove** (1c-iii): **Remove from library** in the expanded row, **Remove selected** (red) in the selection bar. Modal "Remove from library": "Remove “Artist - Title” from the library?" / "Remove N tracks from the library?", then "N tracks in your imports will lose the “In library” mark; the imports stay and you can add them again." (or "Not linked to any import.") + "Their POF, Wanted, notes and other details are lost." → **Cancel** / red **Remove** → notice "Removed N tracks from the library."; the open row closes if it was removed, removed ids leave the selection, an emptied last page goes back a page.
- **Merge modal** "Already in the library" (1c-iv): "This artist and title make it the same track as “…”. Merge them into one track?" Table Field / **This track (edited)** / **Already in the library** for Artist, Title, Genre, BPM, Key, Label, Year, YouTube link; rows where both differ are highlighted with radio buttons (default: Artist/Title = edited, others = existing); other rows show both values (empty = —) and the merged track takes the one that is there. Notes: "POF and Wanted are kept if either track has them (Wanted stays off when POF); notes are joined." and how many import tracks move. **Cancel** (also Escape / backdrop; `wire:model.live`) restores the artist/title in the panel, nothing saved. **Merge** → the edited track is removed, the existing one opens in the panel, notice "Merged into “…”. N import tracks moved to it."
- Empty library: "No tracks in the library yet" + hint to use Add to library + link to imports. No match: "No tracks match the filters." + Clear filters.

## Add to library (Parse Review, YouTube Results)

> Status: implemented (2026-10-19, Milestone 2 step 1b). Trait `App\Livewire\Concerns\AddsToLibrary`, partial `imports/partials/add-to-library-modal`.

- **Add to library** button: Parse Review header after "Add tracks" (hidden while parsing / failed / searching); YouTube Results header after "Add tracks" (disabled while searching). Disabled when the import has no tracks.
- Modal step 1 "Add to library": list of all tracks with checkboxes; tracks not in the library are preselected, tracks already there are unticked and show **In library** (ticking one only fills its track's empty fields / marks it wanted). **Select all not in library** / **Select none**. Checkbox **Mark as wanted** ("You want a file of these tracks. Tracks you already have offline (POF) stay as they are."). **Cancel** / **Add N tracks** (count updates live). No selection → "Select at least one track."
- Step 2 (only when selected tracks look like library tracks: other version or artist order, no exact match): per track the radio choice **New track** (default) or **Same track as {artist - title}** (one option per similar track). **Back** / **Add to library**.
- After adding: modal closes, notice "Added N tracks to the library: X new, Y linked to tracks already there."; rows show the **In library** badge (`<x-in-library-badge>`) and the header "· N in library".
- Edits on the import after adding do not change the library track (DECISIONS 2026-10-19).

## Exports (`/exports`) — `Exports\Index`

> Status: implemented (2026-10-17).

- Table of all export files (every version), newest first, 25 per page: filename (mono) + **Latest** badge on the highest version of each import, import name (link to YouTube Results), version (`v2`), link count, exported date (`updated_at`, so an overwrite shows its new date), **Download** (`/exports/{export}/download`).
- **Delete** (2026-10-17): same selection pattern as the imports list; the **Delete export files** modal lists the filenames, says "The imports and their links stay." → **Cancel** / red **Delete**; notice "Deleted N export files."
- Empty state "No exports yet".
- Sidebar **Exports** is now active (the `<x-nav-link>` shows "Coming soon" only for routes that do not exist).

## Modals summary

All modals use `<x-modal>` (`resources/views/components/modal.blade.php`), bound to a boolean property on the page component:

```blade
<x-modal wire:model="showDuplicateModal" title="Duplicates found">
    ...
    <x-slot:footer>...buttons...</x-slot:footer>
</x-modal>
```

Open/close without a request via `$wire.showDuplicateModal = true`, or from PHP by setting the property. Closes on Escape, backdrop click and the × button; focus is trapped and page scroll locked while open.

| Modal                | Screen          | Trigger                          |
| -------------------- | --------------- | -------------------------------- |
| How it works         | Dashboard       | "How it works" button          |
| Duplicate modal      | Parse Review    | Exact duplicates right after parse; "Review duplicates" in the banner |
| Delete imports       | Imports         | Delete (row) / Delete selected   |
| Delete export files  | Exports         | Delete (row) / Delete selected   |
| Missing links modal  | YouTube Results | Generate TXT with tracks without a link |
| Export version modal | YouTube Results | Export already exists for batch  |
| Merge tracks         | Library         | Artist/title edit that matches another library track |
| Remove from library  | Library         | Remove from library (expanded row) / Remove selected |
| Add to library       | Parse Review, YouTube Results | Add to library (two steps: choose tracks + Mark as wanted, then Same track / New track for possible matches) |
| Export track list    | Parse Review, YouTube Results, Imports | Export track list / Track list (bound with `x-model` to Alpine state, not a Livewire property) |

## YouTube quota line (`<x-youtube-quota>`)

> Status: implemented (2026-10-17).

- One line under the page header on Dashboard, Parse Review and YouTube Results, only when a YouTube API key is set: "YouTube quota: 1,000 / 10,000 units used today (~90 searches left) · resets 09:00". Amber from 80% used; red "YouTube quota used up — resets 09:00" when less than one search is left or the API reported `quotaExceeded`. On YouTube Results it refreshes with the search progress polling.

## Settings → YouTube (`/settings/youtube`) — `Settings\Youtube`

> Status: implemented (2026-10-17).

- **Quota today** card: used units of the daily limit, searches left, reset time (midnight Pacific Time in local time), progress bar (accent / amber ≥ 80% / red used up), the quota line, note that it is an estimate and the limit is `YOUTUBE_DAILY_QUOTA`.
- **API key** card: whether `YOUTUBE_DATA_API_KEY` is set (the key is never shown); without a key, Search YouTube creates search links.

## Settings → AI (`/settings/ai`) — `Settings\Ai`

> Status: implemented (2026-10-13).

- **This month** card: spent (USD, 4 decimals) vs budget, calls (+ failed count), tokens in/out; progress bar (accent < 80%, amber ≥ 80%, red at 100% with "new imports use the simple line parser").
- **API key** card: provider; key source (saved here / `.env` / none) with masked key (`sk-…abcd`); password field + **Save key**; **Test key** (OpenAI only, free); **Remove saved key** (confirm; `.env` key is used again). The full key is never rendered.
- **Model and budget** form: model (text with suggestions from priced models; warning when the model has no price), monthly budget USD (empty = no limit). Save → flash.
- **Recent calls**: last 20 (date, import link, model, tokens in/out, cost, time, OK/Failed with error as tooltip).
- New Import shows an amber banner at ≥ 80% and when the budget is reached (with link here). Review's **Try again with AI** shows an error when AI is off.

