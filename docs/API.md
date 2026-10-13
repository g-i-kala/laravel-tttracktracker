# API / Routes

> Status: `/` (Dashboard placeholder) and `/imports/create` implemented; other routes planned.

The app uses **full-page Livewire components** (see [ARCHITECTURE.md](ARCHITECTURE.md#frontend)). There is no JSON API: user actions are Livewire component methods, called over Livewire's own endpoint (session + CSRF handled by Livewire).

## Web routes

| Method | URI                            | Target (planned)                          | Name (planned)     |
| ------ | ------------------------------ | ----------------------------------------- | ------------------ |
| GET    | `/`                            | `App\Livewire\Dashboard`                  | `dashboard`        |
| GET    | `/imports`                     | `App\Livewire\Imports\Index`              | `imports.index`    |
| GET    | `/imports/create`              | `App\Livewire\Imports\Create`             | `imports.create`   |
| GET    | `/imports/{batch}/review`      | `App\Livewire\Imports\Review`             | `imports.review`   |
| GET    | `/imports/{batch}`             | `App\Livewire\Imports\Show`               | `imports.show`     |
| GET    | `/exports`                     | `App\Livewire\Exports\Index`              | `exports.index`    |
| GET    | `/exports/{export}/download`   | `ExportController@download`               | `exports.download` |

Page routes are registered with `Route::livewire()`. Route-model binding: `{batch}` → ImportBatch, `{export}` → ExportFile.

## Livewire component actions

These replace the POST/PATCH routes from the original plan.

| Component         | Action (planned)                       | Replaces                                       | What it does                                                        |
| ----------------- | -------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------- |
| `Imports\Create`  | `save()` **(implemented)**             | `POST /imports`, `POST /imports/{batch}/parse` | Validate form, `ImportBatchService::createFromRawInput()` (batch + items in a transaction, status `parsed`), flash + redirect (Dashboard for now, review later) |
| `Imports\Review`  | `removeDuplicates()` / `keepAll()`     | —                                              | Exact-duplicate modal choices                                       |
| `Imports\Review`  | `searchYoutube()`                      | `PATCH /items/{item}`, `POST /imports/{batch}/youtube/search` | Save artist/title/genre edits, start search (no-key: sync URLs; API: `Bus::batch()`), redirect to results |
| `Imports\Show`    | `selectCandidate($itemId, $candidateId)` | `POST /items/{item}/youtube/select`          | Copy candidate to `selected_youtube_*`                              |
| `Imports\Show`    | `saveCustomLink($itemId)`              | `POST /items/{item}/youtube/custom`            | Validate + normalize the YouTube URL, store as selected             |
| `Imports\Show`    | `toggleExport($itemId)`                | `POST /items/{item}/toggle-export`             | Flip `include_in_export`                                            |
| `Imports\Show`    | `export()` / `confirmExport($mode)`    | `POST /imports/{batch}/exports/supersimple-txt`| Check missing links / existing export (modals), generate TXT sync   |

Item actions always check that the item belongs to the page's batch.

To be filled in with details as components are implemented.
