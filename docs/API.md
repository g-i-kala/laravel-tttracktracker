# API / Routes

> Status: `/` (Dashboard placeholder), `/imports`, `/imports/create`, `/imports/{batch}/review`, `/imports/{batch}` (YouTube Results, no-key mode) and `/settings/ai` implemented; other routes planned.

The app uses **full-page Livewire components** (see [ARCHITECTURE.md](ARCHITECTURE.md#frontend)). There is no JSON API: user actions are Livewire component methods, called over Livewire's own endpoint (session + CSRF handled by Livewire).

## Web routes

| Method | URI                            | Target (planned)                          | Name (planned)     |
| ------ | ------------------------------ | ----------------------------------------- | ------------------ |
| GET    | `/`                            | `App\Livewire\Dashboard`                  | `dashboard`        |
| GET    | `/imports`                     | `App\Livewire\Imports\Index`              | `imports.index`    |
| GET    | `/imports/create`              | `App\Livewire\Imports\Create`             | `imports.create`   |
| GET    | `/imports/{batch}/review`      | `App\Livewire\Imports\Review`             | `imports.review`   |
| GET    | `/imports/{batch}`             | `App\Livewire\Imports\Show` (`whereNumber('batch')`) | `imports.show`     |
| GET    | `/settings/ai`                 | `App\Livewire\Settings\Ai`                | `settings.ai`      |
| GET    | `/exports`                     | `App\Livewire\Exports\Index`              | `exports.index`    |
| GET    | `/exports/{export}/download`   | `ExportController@download`               | `exports.download` |

Page routes are registered with `Route::livewire()`. Route-model binding: `{batch}` → ImportBatch, `{export}` → ExportFile.

## Livewire component actions

These replace the POST/PATCH routes from the original plan.

| Component         | Action (planned)                       | Replaces                                       | What it does                                                        |
| ----------------- | -------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------- |
| `Imports\Create`  | `save()` **(implemented)**             | `POST /imports`, `POST /imports/{batch}/parse` | Validate form; AI on: `createForAiParsing()` (batch `parsing` + queued job); AI off: `createFromRawInput()` (batch + items, `parsed`, flash); redirect to review |
| `Imports\Review`  | `updatedItems()` hook **(implemented)** | `PATCH /items/{item}`                         | Autosave one field on blur (`wire:model.blur="items.{id}.{field}"`), `ImportItemService::updateTrackDetails()` |
| `Imports\Review`  | `removeItem($itemId)` **(implemented)** | —                                             | Delete one item (with `wire:confirm`)                               |
| `Imports\Review`  | `checkParsing()` **(implemented)**     | —                                              | Polled every 2 s while the batch is `parsing`; loads items when done |
| `Imports\Review`  | `retryAiParsing()` / `useLineParser()` **(implemented)** | —                            | After AI failure: queue AI again (blocked with an error when AI is off / budget reached), or parse `raw_input` line by line |
| `Imports\Review`  | `saveAndClose()` **(implemented)**     | —                                              | Validate all fields, `ImportBatchService::markReviewed()` (`parsed` → `reviewed`; later statuses kept), flash, redirect to `/imports` |
| `Settings\Ai`     | `saveApiKey()` / `removeApiKey()` **(implemented)** | —                                     | Store the key encrypted (`AiSettingsService`) / delete it (falls back to `.env`); flash + redirect |
| `Settings\Ai`     | `testApiKey()` **(implemented)**       | —                                              | `GET https://api.openai.com/v1/models` with the key (no tokens); `valid` / `invalid` / `unsupported` |
| `Settings\Ai`     | `saveSettings()` **(implemented)**     | —                                              | Validate + save model and monthly budget (empty = no limit)         |
| `Imports\Review`  | `removeDuplicates()` / `keepAllDuplicates()` **(implemented)** | —                      | Exact-duplicate modal choices (`ImportBatchService::removeExactDuplicates()` keeps the first of each group) |
| `Imports\Review`  | `searchYoutube()` **(implemented, no-key mode)** | `POST /imports/{batch}/youtube/search` | Validate all fields, `ImportBatchService::generateYoutubeSearchUrls()` (sync), redirect to results. API mode (`Bus::batch()`) later |
| `Imports\Show`    | `selectCandidate($itemId, $candidateId)` | `POST /items/{item}/youtube/select`          | Copy candidate to `selected_youtube_*`                              |
| `Imports\Show`    | `saveCustomLink($itemId)` (step B: inline field per row, autosave) | `POST /items/{item}/youtube/custom` | Validate + normalize the YouTube URL, store as selected, set `include_in_export` |
| `Imports\Show`    | `toggleExport($itemId)`                | `POST /items/{item}/toggle-export`             | Flip `include_in_export`                                            |
| `Imports\Show`    | `export()` / `confirmExport($mode)`    | `POST /imports/{batch}/exports/supersimple-txt`| Check missing links / existing export (modals), generate TXT sync   |

Item actions always check that the item belongs to the page's batch.

To be filled in with details as components are implemented.
