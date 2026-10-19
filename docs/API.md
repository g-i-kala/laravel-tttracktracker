# API / Routes

> Status: `/` (Dashboard placeholder), `/imports`, `/imports/create`, `/imports/{batch}/review`, `/imports/{batch}` (YouTube Results, no-key mode), `/exports`, `/exports/{export}/download` `/settings/ai` and `/settings/youtube` implemented. All planned routes exist.

The app uses **full-page Livewire components** (see [ARCHITECTURE.md](ARCHITECTURE.md#frontend)). There is no JSON API: user actions are Livewire component methods, called over Livewire's own endpoint (session + CSRF handled by Livewire).

## Web routes

| Method | URI                            | Target (planned)                          | Name (planned)     |
| ------ | ------------------------------ | ----------------------------------------- | ------------------ |
| GET    | `/`                            | `App\Livewire\Dashboard`                  | `dashboard`        |
| GET    | `/library`                     | `App\Livewire\Library\Index` (filters/sort in the query string) | `library.index` |
| GET    | `/imports`                     | `App\Livewire\Imports\Index`              | `imports.index`    |
| GET    | `/imports/create`              | `App\Livewire\Imports\Create`             | `imports.create`   |
| GET    | `/imports/{batch}/review`      | `App\Livewire\Imports\Review`             | `imports.review`   |
| GET    | `/imports/{batch}`             | `App\Livewire\Imports\Show` (`whereNumber('batch')`) | `imports.show`     |
| GET    | `/settings/ai`                 | `App\Livewire\Settings\Ai`                | `settings.ai`      |
| GET    | `/settings/youtube`            | `App\Livewire\Settings\Youtube`           | `settings.youtube` |
| GET    | `/exports`                     | `App\Livewire\Exports\Index`              | `exports.index`    |
| GET    | `/exports/{export}/download`   | `ExportController@download`               | `exports.download` |
| GET    | `/imports/{batch}/track-list/{format}` | `TrackListController@download` (`format`: `txt` / `csv`) | `imports.track-list` |

Page routes are registered with `Route::livewire()`. Route-model binding: `{batch}` → ImportBatch, `{export}` → ExportFile.

## Livewire component actions

These replace the POST/PATCH routes from the original plan.

| Component         | Action (planned)                       | Replaces                                       | What it does                                                        |
| ----------------- | -------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------- |
| `Imports\Create`  | `save()` **(implemented)**             | `POST /imports`, `POST /imports/{batch}/parse` | Validate form; AI on: `createForAiParsing()` (batch `parsing` + queued job); AI off: `createFromRawInput()` (batch + items, `parsed`, flash); redirect to review |
| `Imports\Review`  | `updatedItems()` hook **(implemented)** | `PATCH /items/{item}`                         | Autosave one field on blur (`wire:model.live.blur="items.{id}.{field}"`), `ImportItemService::updateTrackDetails()` |
| `Imports\Review`  | `removeItem($itemId)` **(implemented)** | —                                             | Delete one item (with `wire:confirm`)                               |
| `Imports\Review`  | `checkParsing()` **(implemented)**     | —                                              | Polled every 2 s while the batch is `parsing`; loads items when done |
| `Imports\Review`  | `retryAiParsing()` / `useLineParser()` **(implemented)** | —                            | After AI failure: queue AI again (blocked with an error when AI is off / budget reached), or parse `raw_input` line by line |
| `Imports\Review`  | `saveAndClose()` **(implemented)**     | —                                              | Validate all fields, `ImportBatchService::markReviewed()` (`parsed` → `reviewed`; later statuses kept), flash, redirect to `/imports` |
| `Imports\Index`   | `toggleAllOnPage()` / `confirmDelete(?$batchId)` / `delete()` **(implemented)** | `DELETE /imports/{batch}` | Selection (`selected`, `wire:model.live`), delete modal, `ImportBatchService::deleteBatches()` |
| `Exports\Index`   | `toggleAllOnPage()` / `confirmDelete(?$exportId)` / `delete()` **(implemented)** | `DELETE /exports/{export}` | Same pattern, `SupersimpleTxtExportService::deleteExports()` |
| `Settings\Ai`     | `saveApiKey()` / `removeApiKey()` **(implemented)** | —                                     | Store the key encrypted (`AiSettingsService`) / delete it (falls back to `.env`); flash + redirect |
| `Settings\Ai`     | `testApiKey()` **(implemented)**       | —                                              | `GET https://api.openai.com/v1/models` with the key (no tokens); `valid` / `invalid` / `unsupported` |
| `Settings\Ai`     | `saveSettings()` **(implemented)**     | —                                              | Validate + save model and monthly budget (empty = no limit)         |
| `Imports\Review`  | `removeDuplicates()` / `keepAllDuplicates()` **(implemented)** | —                      | Exact-duplicate modal choices (`ImportBatchService::removeExactDuplicates()` keeps the first of each group) |
| `Imports\Review`  | `searchYoutube()` **(implemented)** | `POST /imports/{batch}/youtube/search` | Validate all fields, `ImportBatchService::searchYoutube()`: no key → search URLs (sync); key → search URLs + queued API search for items without a link (`Bus::batch()`); redirect to results |
| `Imports\Show`    | `selectCandidate($itemId, $candidateId)` **(implemented)** | `POST /items/{item}/youtube/select` | `ImportItemService::selectYoutubeCandidate()`: candidate → `selected_youtube_*` + score, included; candidate must belong to the item (404 otherwise); dispatches `item-saved` |
| `Imports\Show`    | `checkSearchProgress()` / `dismissSearchSummary()` **(implemented)** | — | Polled every 2 s while the batch is `searching`: fills finished rows, summary when done |
| `Imports\Review`, `Imports\Show` | `addTracks($useLineParser = false)` **(implemented, trait `AddsTracks`)** | — | "Add tracks" modal: validate, `ImportBatchService::parseAdditionalTracks()` (AI in the request when enabled, usage logged with the batch; on AI failure an error + "Use simple parser") and `appendParsedLines()` (after the last line, tracks already in the batch skipped; max 200 tracks per import). Review: notice + rows reloaded. Show: notice + `searchYoutube()` right away (new tracks only, progress shown) |
| `Imports\Show`    | `updatedLinks()` hook **(implemented)** | `POST /items/{item}/youtube/custom`           | Autosave the pasted link (`wire:model.live.blur="links.{id}"`, blur on paste): empty → `ImportItemService::clearYoutubeLink()` (excluded); invalid → error, nothing saved; valid → `YoutubeUrlNormalizer` + `selectCustomYoutubeLink()` (included); dispatches `item-saved` |
| `Imports\Show`    | `toggleExport($itemId)` **(implemented)** | `POST /items/{item}/toggle-export`          | Flip `include_in_export`; no-op without a selected link             |
| `Imports\Show`    | `export()` / `exportAnyway()` / `confirmExport($mode)` **(implemented)** | `POST /imports/{batch}/exports/supersimple-txt` | `export()`: no-op without exportable links; tracks without a link → missing links modal (`exportAnyway()` continues); existing export → version modal; `confirmExport('new'|'overwrite')` → `SupersimpleTxtExportService::export()`, dispatches `download-export` |
| `Imports\Review`, `Imports\Show` | `openLibraryModal()` / `addToLibrary()` / `backToLibrarySelection()` **(implemented, trait `AddsToLibrary`)** | — | Add to library modal: preselect items without a track; possible matches → "Same track / New track" step; `LibraryService::addItems()` |
| `Library\Index`   | `togglePlayableOffline($trackId)` / `toggleAllOnPage()` / `setSelectedPlayableOffline(bool)` **(implemented)** | — | POF per row and for the ticked tracks (`selected`), `LibraryService::setPlayableOffline()` |
| `Library\Index`   | `toggleTrack($trackId)` / `updatedEdit()` hook **(implemented)** | — | Expand/collapse a row; autosave one field of the expanded track (`edit.*`, validated), artist/title collision check, `LibraryService::updateTrackDetails()`, dispatches `track-saved` |
| `Library\Index`   | `toggleAvailability($value)` / `sortBy($column)` / `clearFilters()` **(implemented)** | — | Filters (`search`, `availability`, `genre`, `camelot`, `bpmFrom`/`bpmTo`, `yearFrom`/`yearTo`) and `sort`/`direction` as `#[Url]` properties; any filter change resets the page |

Item actions always check that the item belongs to the page's batch.

To be filled in with details as components are implemented.

### `TrackListController@download` (implemented 2026-10-18)

`GET /imports/{batch}/track-list/{txt|csv}` → `TrackListExportService::content()` streamed as a download (`{slug}_tracks.{format}`, `text/plain` / `text/csv; charset=UTF-8`). Other formats and unknown imports → 404. Nothing is stored. Linked from the Export track list modal.

### `ExportController@download` (implemented)

`GET /exports/{export}/download` → `Storage::disk('local')->download($export->path, $export->filename)` with `Content-Type: text/plain; charset=UTF-8`; 404 when the file is missing on disk. Used by the automatic download after Generate TXT (`download-export` browser event → `window.location`) and by "Download again".
