# UI Flow

> Status: planned. Blade + Livewire full-page components (one per screen), no SPA. UI language: English.

Each screen below is one class-based Livewire component (`app/Livewire/...`). Rows are Blade partials. Modals use our `<x-modal>` Blade component (Alpine).

## Layout (implemented)

- Left sidebar: app name, Dashboard / Imports / Exports, **+ New YouTube Export** button, **AI Settings** at the bottom. Screens not built yet are shown disabled ("Coming soon"). On mobile the sidebar collapses behind a **Menu** button.
- Theme follows the OS (light/dark). Accent color from `APP_ACCENT`.
- Flash message area above the page content.

## Dashboard (`/`) — `Dashboard`

> Status: placeholder (empty state + "How it works" modal). Recent imports/exports come in "Build dashboard".

- New YouTube Export button.
- Recent imports, recent exports.
- Items needing attention.

## Imports (`/imports`) — `Imports\Index`

> Status: implemented (2026-10-13).

- Table of all batches, newest first, 25 per page: name (link), created, track count, status badge (`To review` amber, `Failed` red, others accent), **Continue**.
- **Continue** opens Parse Review for now; later it will open the screen matching the status (YouTube Results after searching).
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
- **Search YouTube** button (hidden while parsing / failed, disabled with no tracks) → `searchYoutube()`: validates all fields, generates search URLs, redirects to YouTube Results. Can be run again after edits (URLs are regenerated).

## YouTube Results (`/imports/{batch}`) — `Imports\Show`

> Status: step A implemented (2026-10-14): header (name, count, month/year, genre), **Back to review**, **Generate TXT** disabled ("Coming soon"), table #, track (artist bold + title, grey line `BPM · Camelot · label`), **Search YouTube ↗** link per row (new tab, `rel="noopener noreferrer"`). Imports list **Continue** opens this screen once the batch is `searched` / `exported`.
> Step B (next): inline **Paste YouTube link** field per row instead of the custom link modal (autosave on paste/blur; a valid link ticks Include in export), Include-in-export checkbox.

- While the API search batch is running: progress (e.g. 12/30) via `wire:poll`, which stops when the batch finishes.
- API mode: best candidate preselected; expand a row (Alpine) to see the 3 candidates; select another.
- No-key mode (or API fallback): **Open YouTube Search** + **Add custom link**.
- **Custom link modal**: validates/normalizes the URL, shows the validation error inline.
- Include-in-export checkbox per item.
- All links `target="_blank"`.

## Export (on YouTube Results)

- **Generate Supersimple TXT** button → `export()`.
- **Missing links modal**: Add custom links / Export anyway and skip missing / Cancel.
- **Export version modal** (export already exists): Create new version / Overwrite latest / Cancel.
- After export: download link.

## Exports (`/exports`) — `Exports\Index`

- Export history with download links (`/exports/{export}/download`).

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
| Custom link modal    | YouTube Results | Add custom link                  |
| Missing links modal  | YouTube Results | Export with missing links        |
| Export version modal | YouTube Results | Export already exists for batch  |

## Settings → AI (`/settings/ai`) — `Settings\Ai`

> Status: implemented (2026-10-13).

- **This month** card: spent (USD, 4 decimals) vs budget, calls (+ failed count), tokens in/out; progress bar (accent < 80%, amber ≥ 80%, red at 100% with "new imports use the simple line parser").
- **API key** card: provider; key source (saved here / `.env` / none) with masked key (`sk-…abcd`); password field + **Save key**; **Test key** (OpenAI only, free); **Remove saved key** (confirm; `.env` key is used again). The full key is never rendered.
- **Model and budget** form: model (text with suggestions from priced models; warning when the model has no price), monthly budget USD (empty = no limit). Save → flash.
- **Recent calls**: last 20 (date, import link, model, tokens in/out, cost, time, OK/Failed with error as tooltip).
- New Import shows an amber banner at ≥ 80% and when the budget is reached (with link here). Review's **Try again with AI** shows an error when AI is off.

