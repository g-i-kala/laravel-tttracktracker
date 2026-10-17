# UI Flow

> Status: planned. Blade + Livewire full-page components (one per screen), no SPA. UI language: English.

Each screen below is one class-based Livewire component (`app/Livewire/...`). Rows are Blade partials. Modals use our `<x-modal>` Blade component (Alpine).

## Layout (implemented)

- Left sidebar: app name, Dashboard / Imports / Exports, **+ New YouTube Export** button, **AI Settings** at the bottom. Screens not built yet are shown disabled ("Coming soon"). On mobile the sidebar collapses behind a **Menu** button.
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
- Empty state "No imports yet" + **New YouTube Export** button.

## New Import (`/imports/create`) — `Imports\Create`

> Status: implemented (2026-10-13).

- Name (auto-generated in `Europe/Warsaw` time, e.g. `YouTube Export 2026-10-13 18:30`, editable, required, max 255).
- Optional year (2000–2100), month (select January–December), default genre (free text). Empty by default.
- Track list textarea (monospace). Live counter below it: "12 tracks detected · 2 without artist (fix them in review)" (debounced 400 ms, same parser as on save).
- Validation: at least 1 and at most 200 detected tracks.
- **AI mode** (AI key set): subtitle "Paste your track list in any format"; instead of the counter: "AI parsing on · paste any format…"; validation only max 100,000 characters; Parse queues AI parsing and redirects to Parse Review immediately.
- **Parse** button (shows "Parsing…" while saving) → `save()` → items get the default genre → redirect to Parse Review with flash "Imported N tracks. Review them before searching YouTube."

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

## Settings → AI (`/settings/ai`) — `Settings\Ai`

> Status: implemented (2026-10-13).

- **This month** card: spent (USD, 4 decimals) vs budget, calls (+ failed count), tokens in/out; progress bar (accent < 80%, amber ≥ 80%, red at 100% with "new imports use the simple line parser").
- **API key** card: provider; key source (saved here / `.env` / none) with masked key (`sk-…abcd`); password field + **Save key**; **Test key** (OpenAI only, free); **Remove saved key** (confirm; `.env` key is used again). The full key is never rendered.
- **Model and budget** form: model (text with suggestions from priced models; warning when the model has no price), monthly budget USD (empty = no limit). Save → flash.
- **Recent calls**: last 20 (date, import link, model, tokens in/out, cost, time, OK/Failed with error as tooltip).
- New Import shows an amber banner at ≥ 80% and when the budget is reached (with link here). Review's **Try again with AI** shows an error when AI is off.

