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

- Name (auto-generated, e.g. `YouTube Export 2026-10-11 18:30`, editable).
- Optional year, month, default genre.
- Track list textarea.
- **Parse** button → `save()` → redirect to Parse Review.

## Parse Review (`/imports/{batch}/review`) — `Imports\Review`

- Always shown after parse.
- Editable table: artist, title, genre (`wire:model` on each row).
- Exact duplicates → **Duplicate modal**: Remove duplicates / Keep all / Review manually.
- Similar duplicates → inline warning, non-blocking.
- **Search YouTube** button → `searchYoutube()` → redirect to YouTube Results.

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
