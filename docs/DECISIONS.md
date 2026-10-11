# Decisions

## 2026-10-11 — Use separate PostgreSQL container

**Decision:**
Use a project-local PostgreSQL container (Sail `pgsql`) instead of shared `pg-dev`.

**Reason:**
Better isolation, easier deployment, simpler local setup.

**Consequences:**
Project docker-compose owns database service. `.env` must be switched from `pg-dev`.

## 2026-10-11 — Blade + Vue components, no SPA

**Decision:**
Blade pages with Vue components mounted inside; no Inertia, no SPA.

**Reason:**
Simple local app; interactivity needed only in tables and modals.

**Consequences:**
Routes are classic web routes; Vue components call JSON endpoints.

## 2026-10-11 — No auth in MVP

**Decision:**
No user accounts or login.

**Reason:**
Single-user local app.

**Consequences:**
No Breeze/Jetstream/Sanctum.

## 2026-10-11 — Database queue

**Decision:**
`QUEUE_CONNECTION=database` for MVP.

**Reason:**
No extra infrastructure. RabbitMQ may come later.

## 2026-10-11 — YouTube API with no-key fallback

**Decision:**
Use official YouTube Data API when key exists; otherwise generate search URLs and accept custom links. No scraping.

**Reason:**
App must work without an API key.

## 2026-10-11 — Install Laravel Boost

**Decision:**
Install `laravel/boost` (dev) with guidelines, skills and MCP server.

**Reason:**
Better AI-assisted development context (docs search, DB schema, Artisan tools).

**Consequences:**
Boost appends its guidelines to `CLAUDE.md` / `AGENTS.md`. Boost's rule "only create documentation files if explicitly requested" is overridden by the project's Documentation Policy in `CLAUDE.md`.

## 2026-10-11 — Atomic task execution

**Decision:**
Claude executes tasks atomically unless instructed otherwise.

**Reason:**
Keep changes small and reviewable; user drives the next step.

## 2026-10-11 — Architecture review (Q&A)

Decisions from the architecture review of `CLAUDE.md`, made point by point.

### A1 — YouTube quota fallback

**Decision:** On `quotaExceeded` or an API error, the item falls back to no-key mode (search URL + notice). No-key mode is the primary mode at start; API mode is built in parallel.
**Reason:** `search.list` costs 100 of the 10,000 daily units (~100 searches/day).
**Consequences:** The search cache is mandatory; the batch never fails as a whole.

### A2 — No video duration

**Decision:** No `duration_seconds`, no `videos.list` call. Scoring is title-based only.
**Reason:** Keep the API integration simple at start.
**Consequences:** "Full/long version" is judged by title keywords only. Can be revisited.

### A3 — Selected link: snapshot on item

**Decision:** `import_items.selected_youtube_*` is the single source of truth; `youtube_candidates.is_selected` is removed.
**Reason:** Two places storing the selection can drift apart.
**Consequences:** Selected candidate = `video_id` match (accessor). Custom links are stored only on the item.

### A4 — Pipeline-only statuses as enums

**Decision:** Batch: `draft, parsed, searching, searched, exported, failed`. Item: `parsed, duplicate, search_pending, searched, exported, failed`. Implemented as PHP backed enums.
**Reason:** States like selected/no_link/excluded/ready_to_export duplicated data.
**Consequences:** Derived states are accessors/scopes.

### A5 — No stored counters on batches

**Decision:** Remove `items_count`, `exportable_count`, `missing_links_count`; use `withCount()`.
**Reason:** Avoid counters drifting from data; the scale is small.

### B1 — Synchronous export

**Decision:** No `GenerateSupersimpleTxtExportJob`; export runs in the request.
**Reason:** Writing ~30 lines is instant; the modal flow needs an immediate result.

### B2 — Bus::batch() for YouTube search

**Decision:** Replace `SearchYoutubeForBatchJob` with `Bus::batch()` of `SearchYoutubeForImportItemJob`.
**Reason:** Built-in progress tracking and failure handling.
**Consequences:** `job_batches` table; UI polls progress.

### B3 — Queue worker as a compose service

**Decision:** A `queue` service in `compose.yaml` runs `queue:work`.
**Reason:** Prevents "search hangs" when no worker is running.
**Consequences:** Run `queue:restart` after code changes.

### B4 — Own search cache table

**Decision:** Keep `youtube_search_cache` instead of Laravel Cache.
**Reason:** Search history is visible, useful while learning the API quota.

### B5 — YouTube config via config/services.php

**Decision:** `config('services.youtube.*')`, never `env()` in app code.
**Reason:** Laravel convention; `env()` breaks with `config:cache`.

### C1 — normalized_key now, cross-batch duplicates later

**Decision:** Add `import_items.normalized_key` (indexed) in MVP; cross-batch duplicate warning after MVP.
**Reason:** Cheap now, avoids a later migration.

### C2 — Keep Vue in Blade

**Decision:** No Livewire; Vue 3 with one `createApp` per page and `data-props`.

### C3 — year/month for filtering

**Decision:** Keep optional `year`/`month` on batches for filtering/organizing.

### C4 — English UI

**Decision:** UI language is English.

### D1 — Slim down CLAUDE.md

**Decision:** CLAUDE.md is to keep rules, stack, safety, a short MVP summary and a docs map; the specification lives in `docs/`.
**Status:** Pending (separate task).

### D2 — Keep 13 doc files

**Decision:** No merging of doc files.

### D3 — VS Code only

**Decision:** Remove Boost's Cursor/OpenCode files, add `.vscode/extensions.json`, re-run `boost:install` after Sail.
**Status:** Pending.

## 2026-10-11 — Sail runtime: PHP 8.4, PostgreSQL 16

**Decision:** Sail runtime `8.4` (not 8.3/8.5), image `postgres:16`, `FORWARD_DB_PORT=5433`, `APP_PORT=8000`.
**Reason:** Symfony 8 in `composer.lock` requires PHP >= 8.4.1 and matches the host PHP. Host port 5432 is taken by `pg-dev`.
**Consequences:** Connect to the DB from the host on `localhost:5433`.

## 2026-10-11 — App name and database name

**Decision:** `APP_NAME=TTTrackTracker`, database `tttracktracker` (instead of `dj_library_agent`).
