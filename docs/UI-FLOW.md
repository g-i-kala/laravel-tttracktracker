# UI Flow

> Status: planned. Blade + Livewire full-page components (one per screen), no SPA. UI language: English.

Each screen below is one class-based Livewire component (`app/Livewire/...`). Rows are Blade partials. Modals use our `<x-modal>` Blade component (Alpine).

## Layout (implemented)

- Left sidebar: app name, Dashboard / Imports / Exports, **+ New YouTube Export** button. Screens not built yet are shown disabled ("Coming soon"). On mobile the sidebar collapses behind a **Menu** button.
- Theme follows the OS (light/dark). Accent color from `APP_ACCENT`.
- Flash message area above the page content.

## Dashboard (`/`) — `Dashboard`

> Status: placeholder (empty state + "How it works" modal). Recent imports/exports come in "Build dashboard".

- New YouTube Export button.
- Recent imports, recent exports.
- Items needing attention.

## New Import (`/imports/create`) — `Imports\Create`

> Status: implemented (2026-10-13).

- Name (auto-generated in `Europe/Warsaw` time, e.g. `YouTube Export 2026-10-13 18:30`, editable, required, max 255).
- Optional year (2000–2100), month (select January–December), default genre (free text). Empty by default.
- Track list textarea (monospace). Live counter below it: "12 tracks detected · 2 without artist (fix them in review)" (debounced 400 ms, same parser as on save).
- Validation: at least 1 and at most 200 detected tracks.
- **Parse** button (shows "Parsing…" while saving) → `save()` → items get the default genre → redirect to Parse Review with flash "Imported N tracks. Review them before searching YouTube."

## Parse Review (`/imports/{batch}/review`) — `Imports\Review`

> Status: editing + removing implemented (2026-10-13). Duplicates and Search YouTube pending.

- Always shown after parse (New Import redirects here).
- Header (read-only): batch name, track count, "N without artist", month/year, default genre.
- Editable table in line order: #, artist, title (raw line shown below in grey), genre.
- **Autosave on blur**: each field saves when it loses focus; "Saved" flashes next to the row; validation errors inline (title required, max 255).
- Saving recomputes `normalized_key`; filling in an artist clears "Artist not detected", clearing it sets the notice again. Rows without artist have an amber border.
- **Remove** per row with a browser confirm ("Remove this track?").
- Exact duplicates → **Duplicate modal**: Remove duplicates / Keep all / Review manually.
- Similar duplicates → inline warning, non-blocking.
- **Search YouTube** button → `searchYoutube()` → redirect to YouTube Results. *Now: disabled, "Coming soon".*

## YouTube Results (`/imports/{batch}`) — `Imports\Show`

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
| Duplicate modal      | Parse Review    | Exact duplicates after parse     |
| Custom link modal    | YouTube Results | Add custom link                  |
| Missing links modal  | YouTube Results | Export with missing links        |
| Export version modal | YouTube Results | Export already exists for batch  |
