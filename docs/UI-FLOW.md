# UI Flow

> Status: planned. Blade pages + Vue components, no SPA.

## Dashboard (`/`)

- New YouTube Export button.
- Recent imports, recent exports.
- Items needing attention.

## New Import (`/imports/create`)

- Name (auto-generated, e.g. `YouTube Export 2026-10-11 18:30`, editable).
- Optional year, month, default genre.
- Track list textarea (`ImportTextarea.vue`).
- **Parse** button.

## Parse Review (`/imports/{batch}/review`)

- Always shown after parse.
- Editable table: artist, title, genre (`ParseReviewTable.vue`).
- Exact duplicates → `DuplicateModal.vue`: Remove duplicates / Keep all / Review manually.
- Similar duplicates → inline warning, non-blocking.
- **Search YouTube** button.

## YouTube Results (`/imports/{batch}`)

- `YoutubeResultsTable.vue`, `YoutubeCandidateList.vue`.
- API mode: best candidate preselected; expand to see 3 candidates; select another.
- No-key mode: **Open YouTube Search** + **Add custom link**.
- `CustomYoutubeLinkModal.vue` — validates/normalizes.
- Include-in-export checkbox per item.
- All links `target="_blank"`.

## Export

- **Generate Supersimple TXT** button.
- `ExportWarningModal.vue` (missing links): Add custom links / Export anyway and skip missing / Cancel.
- `ExportVersionModal.vue` (already exists): Create new version / Overwrite latest / Cancel.
- Export history + download (`/exports`).

## Modals summary

| Modal                     | Trigger                          |
| ------------------------- | -------------------------------- |
| DuplicateModal            | Exact duplicates after parse     |
| CustomYoutubeLinkModal    | Add custom link                  |
| ExportWarningModal        | Export with missing links        |
| ExportVersionModal        | Export already exists for batch  |
