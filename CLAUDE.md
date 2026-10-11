# CLAUDE.md

This file provides guidance to Claude Code when working in this repository.

## Project: DJ Library Agent

App name: **TTTrackTracker** (`APP_NAME`). Database: `tttracktracker`.

### Current MVP

We are building a local-first Laravel application for a DJ workflow.

The MVP is:

```text
Manual track import
→ parse Artist/Title
→ review/edit
→ search YouTube or generate manual YouTube search URLs
→ select/customize links
→ generate Supersimple TXT export
```

The output TXT file contains only YouTube URLs, one per line:

```txt
https://www.youtube.com/watch?v=abc123
https://www.youtube.com/watch?v=def456
https://www.youtube.com/watch?v=ghi789
```

No audio downloading is implemented in this app.

## Important Product Decisions

### MVP Scope

In scope:

- Laravel app running locally with Sail.
- Own PostgreSQL container inside this project.
- No auth for MVP.
- Blade pages with Vue components.
- Manual import via textarea.
- Parse review is always shown.
- Editable artist, title, and optional genre.
- No separate version/remix field in MVP.
- Remix/version stays inside title.
- Exact duplicate modal.
- Similar duplicate warning.
- YouTube API mode if API key exists.
- No-key fallback mode if API key is missing.
- 3 YouTube candidates per track in API mode.
- Best candidate preselected.
- `include_in_export` checkbox.
- Clickable YouTube links opening in new tab.
- Custom YouTube link input.
- Supersimple TXT export.
- Export history.
- Export versioning using `_v2`, `_v3`, etc.
- YouTube search cache.
- Database queue for MVP.

Out of scope for MVP:

- Audio downloading.
- Spotify sync.
- SoundCloud sync.
- Beatport integration.
- Rekordbox XML.
- Local file scanning.
- BPM/key detection.
- Fingerprinting.
- User accounts/auth.

### Roadmap

1. **Milestone 1:** YouTube Supersimple TXT Exporter
2. **Milestone 2:** Beatport integration/import helper
3. **Milestone 3:** Local music inventory
4. **Milestone 4:** Rekordbox export

## Working Rules

- **Execute tasks atomically** unless explicitly instructed otherwise: do exactly the requested task, nothing more.
- Do not chain into follow-up steps (next setup stage, extra features, refactors) without being asked — propose them instead.
- Report what was done and suggest the next step; wait for confirmation before continuing.
- **At the start of every session, read `docs/START-TUTAJ.md`** (status + session handoff notes) before doing anything else.
- At the end of a session (or when asked to save progress), update the handoff notes in `docs/START-TUTAJ.md`.
- Use **Conventional Commits** for commit messages: `type(scope): description`, e.g. `feat(import): add parser service`, `docs: update DATABASE.md`, `build(sail): add queue service`. Types: `feat`, `fix`, `docs`, `build`, `chore`, `refactor`, `test`, `style`, `perf`, `ci`.
- Commit only when asked.

## Working Directory

Claude Code is expected to work in the folder where it is currently launched.

Assume this directory is the project root.

Before making large changes, inspect the repository:

```bash
pwd
ls -la
find . -maxdepth 2 -type f | sort
```

Do not assume a fresh Laravel install unless confirmed.

## Documentation Policy

All project documentation must live in:

```text
docs/
```

If `docs/` does not exist, create it.

Claude should maintain documentation continuously while working.

After each meaningful change, update the relevant docs.

This policy overrides the Laravel Boost guideline "only create documentation files if explicitly requested".

### Required Documentation Files

The following documentation files should exist or be created as the project evolves.

#### `docs/START-TUTAJ.md`

Purpose: human-friendly starting point.

Should contain:

- What this project is.
- How to run locally.
- Most important commands.
- Current status.
- Links to other docs.

#### `docs/PRD.md`

Purpose: product requirements document.

Should contain:

- MVP definition.
- User flow.
- Functional requirements.
- Acceptance criteria.
- Out-of-scope features.
- Roadmap.

#### `docs/ARCHITECTURE.md`

Purpose: system architecture.

Should contain:

- Main modules.
- Laravel structure.
- Data flow.
- Services/jobs/controllers overview.
- Queue strategy.
- YouTube API/fallback strategy.
- Export generation architecture.

#### `docs/TECH-STACK.md`

Purpose: technical stack decisions.

Should contain:

- Laravel version.
- PHP version.
- PostgreSQL.
- Laravel Sail.
- Blade + Vue components.
- Vite.
- Queue connection.
- Testing stack.
- Important Composer/npm packages.

#### `docs/ROADMAP.md`

Purpose: staged development plan.

Should contain:

- Milestone 1 MVP tasks.
- Milestone 2 Beatport.
- Milestone 3 Local inventory.
- Milestone 4 Rekordbox.
- Known future enhancements.

#### `docs/DATABASE.md`

Purpose: database schema documentation.

Should contain:

- Tables.
- Important columns.
- Relationships.
- Status values.
- Indexes.
- Migration notes.

#### `docs/API.md`

Purpose: routes/endpoints documentation.

Should contain:

- Web routes.
- JSON endpoints used by Vue components.
- Request/response examples where useful.
- Controller/action mapping.

#### `docs/UI-FLOW.md`

Purpose: frontend/user flow documentation.

Should contain:

- Dashboard flow.
- New Import flow.
- Parse Review flow.
- YouTube Results flow.
- Export flow.
- Modals.

#### `docs/DOCKER.md`

Purpose: local environment documentation.

Should contain:

- Sail setup.
- PostgreSQL service.
- `.env` requirements.
- Useful Docker/Sail commands.
- Queue worker commands.
- Troubleshooting.

#### `docs/DECISIONS.md`

Purpose: architecture/product decision log.

Use ADR-like entries.

Example:

```md
## 2026-10-11 — Use separate PostgreSQL container

**Decision:**
Use a project-local PostgreSQL container instead of shared pg-dev.

**Reason:**
Better isolation, easier deployment, simpler local setup.

**Consequences:**
Project docker-compose owns database service.
```

#### `docs/DEVELOPMENT-LOG.md`

Purpose: chronological log of work done.

Append entries after meaningful work.

Example:

```md
## 2026-10-11

- Created Laravel Sail setup.
- Added ImportBatch and ImportItem models.
- Added initial parse flow.
- Updated DATABASE.md and API.md.
```

#### `docs/TODO.md`

Purpose: current actionable task list.

Use checkboxes:

```md
# TODO

## MVP

- [ ] Create migrations
- [ ] Build import create page
- [ ] Implement parser
```

#### `docs/TESTING.md`

Purpose: testing notes.

Should contain:

- How to run tests.
- Test strategy.
- Important test cases.
- Manual QA checklist.

### Optional docs later

Create if useful:

```text
docs/YOUTUBE.md
docs/BEATPORT.md
docs/EXPORTS.md
docs/REKORDBOX.md
docs/LOCAL-INVENTORY.md
docs/CHANGELOG.md
```

### Documentation Update Rules

When changing code, update docs as follows:

| Change type                | Docs to update                          |
| -------------------------- | --------------------------------------- |
| New feature                | PRD, ROADMAP, DEVELOPMENT-LOG, TODO     |
| New table/migration        | DATABASE, DEVELOPMENT-LOG               |
| New route/controller       | API, ARCHITECTURE, DEVELOPMENT-LOG      |
| New Docker/Sail config     | DOCKER, TECH-STACK, DEVELOPMENT-LOG     |
| New UI screen/component    | UI-FLOW, ARCHITECTURE, DEVELOPMENT-LOG  |
| New architectural decision | DECISIONS                               |
| New test strategy/command  | TESTING                                 |
| Finished TODO              | TODO, DEVELOPMENT-LOG                   |

Do not leave docs stale.

## Tech Stack

### Backend

- PHP
- Laravel
- Laravel Sail
- PostgreSQL
- Database queue for MVP

### Frontend

- Blade pages
- Vue components mounted inside Blade
- Vite
- No Inertia
- No full SPA

### Auth

MVP has no auth.

This is a single-user local app.

Do not add Breeze, Jetstream, Sanctum auth, or user login unless explicitly requested.

### Database

Use a project-local PostgreSQL container.

Expected `.env` style:

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=tttracktracker
DB_USERNAME=sail
DB_PASSWORD=password
FORWARD_DB_PORT=5433
```

`FORWARD_DB_PORT=5433` avoids a host port conflict with the shared `pg-dev` container on 5432. Inside Docker the app still uses `pgsql:5432`.

Sail runs PHP 8.4 (same as host; Symfony 8 requires PHP >= 8.4.1) with PostgreSQL 16. Prefer running commands through `./vendor/bin/sail`.

### Queue

MVP default:

```env
QUEUE_CONNECTION=database
```

- YouTube search uses Laravel job batching (`Bus::batch()`, `job_batches` table) with one `SearchYoutubeForImportItemJob` per item. The UI polls batch progress.
- The queue worker runs as a dedicated `queue` service in `compose.yaml`, so `sail up -d` starts it. After code changes run `./vendor/bin/sail artisan queue:restart`.
- Exports are generated synchronously, without a job.

Do not introduce Redis/RabbitMQ unless requested.

RabbitMQ may be added later.

## YouTube

The app supports two modes.

### API mode

If this exists:

```env
YOUTUBE_DATA_API_KEY=...
```

then search YouTube through the official YouTube Data API.

### No-key fallback mode

If `YOUTUBE_DATA_API_KEY` is empty, the app must still work.

It should generate YouTube search URLs like:

```text
https://www.youtube.com/results?search_query=Adam+Beyer+Teach+Me+Original+Mix
```

and allow user to paste a custom YouTube link.

## Product Concepts

### Import Batch

A group of imported tracks.

Example:

```text
YouTube Export 2026-10-11 18:30
```

### Import Item

One track/line inside a batch.

Example raw line:

```text
Adam Beyer - Teach Me (Original Mix)
```

MVP parsed result:

```text
artist = Adam Beyer
title  = Teach Me (Original Mix)
```

No separate version field in MVP.

### YouTube Candidate

A possible YouTube result for an item.

In API mode, store up to 3 candidates.

### Export File

A generated TXT file.

Supersimple export includes only selected URLs.

## Data Model Guidance

Expected core models:

```text
ImportBatch
ImportItem
YoutubeCandidate
ExportFile
YoutubeSearchCache
```

Expected tables:

```text
import_batches
import_items
youtube_candidates
export_files
youtube_search_cache
```

### `import_batches`

Important fields:

```text
id
name
source_type
default_genre
year
month
status
raw_input
items_count
exportable_count
missing_links_count
created_at
updated_at
```

### `import_items`

Important fields:

```text
id
import_batch_id
line_number
raw_line
artist
title
genre
status
selected_youtube_url
selected_youtube_video_id
selected_youtube_title
selected_youtube_channel
youtube_score
youtube_search_url
include_in_export
error_message
created_at
updated_at
```

### `youtube_candidates`

Important fields:

```text
id
import_item_id
video_id
url
title
channel_title
description
published_at
score
rank
is_selected
created_at
updated_at
```

### `export_files`

Important fields:

```text
id
import_batch_id
type
filename
path
version
items_count
options
created_at
updated_at
```

### `youtube_search_cache`

Important fields:

```text
id
query_hash
query
results
provider
expires_at
created_at
updated_at
```

## Status Values

### Import Batch statuses

Use simple status values:

```text
draft
parsed
duplicates_found
ready_for_search
searching
searched
ready_to_export
exported
failed
```

### Import Item statuses

Use simple status values:

```text
draft
parsed
duplicate
possible_duplicate
search_pending
searched
selected
no_link
excluded
exported
```

Avoid overcomplicating statuses.

## Main User Flow

### 1. Dashboard

User sees:

- New YouTube Export button.
- Recent imports.
- Recent exports.
- Items needing attention.

### 2. New YouTube Export

User enters:

- Name, auto-generated but editable.
- Optional year.
- Optional month.
- Optional default genre.
- Track list textarea.

Then clicks **Parse**.

### 3. Parse Review

Always show parse review.

User can edit:

- Artist.
- Title.
- Genre.

Exact duplicates — show modal:

- Remove duplicates.
- Keep all.
- Review manually.

Similar duplicates:

- Show warning.
- Do not block.

Then user clicks **Search YouTube**.

### 4. YouTube Results

If API key exists:

- Fetch 3 candidates per track.
- Score them.
- Preselect best candidate.
- If score >= 80, set `include_in_export = true`.

If API key does not exist:

- Generate `youtube_search_url`.
- Show **Open YouTube Search**.
- Allow **Add custom link**.

User can:

- Open selected link in new tab.
- Expand candidates.
- Select different candidate.
- Add custom YouTube link.
- Toggle include in export.

### 5. Supersimple TXT Export

User clicks **Generate Supersimple TXT**.

If some tracks have no link, show modal:

```text
Some tracks have no link.

[Add custom links]
[Export anyway and skip missing]
[Cancel]
```

If export already exists, show modal:

```text
Export already exists.

[Create new version]
[Overwrite latest]
[Cancel]
```

The generated file contains only URLs.

## Export Rules

A URL is included in TXT only if:

```text
include_in_export = true
selected_youtube_url is not null
```

Do not include:

- comments,
- titles,
- metadata,
- empty lines,
- placeholders.

Filename style:

```text
{slugged_import_name}_youtube_links.txt
{slugged_import_name}_youtube_links_v2.txt
{slugged_import_name}_youtube_links_v3.txt
```

Example:

```text
youtube_export_2026_10_11_1830_youtube_links.txt
youtube_export_2026_10_11_1830_youtube_links_v2.txt
```

## YouTube URL Validation

Custom link should accept:

```text
https://www.youtube.com/watch?v=abc123
https://youtu.be/abc123
https://www.youtube.com/shorts/abc123
```

Normalize to:

```text
https://www.youtube.com/watch?v=abc123
```

Reject non-YouTube URLs.

## YouTube Scoring Guidance

Score candidates from 0 to 100.

Prefer:

- Full/long version.
- Strong title match.
- Official/Topic/label channel.

Penalize:

```text
live set
podcast
lyrics
sped up
slowed
cover
reaction
tutorial
```

Default threshold:

```env
YOUTUBE_AUTO_INCLUDE_THRESHOLD=80
YOUTUBE_CANDIDATES_LIMIT=3
```

Behavior:

| Score  | Behavior                                  |
| ------ | ----------------------------------------- |
| >= 80  | preselect + include in export             |
| 60–79  | preselect, but do not include by default  |
| < 60   | do not include by default                 |

## Expected Laravel Structure

Use this structure unless the existing project suggests otherwise:

```text
app/
  Models/
    ImportBatch.php
    ImportItem.php
    YoutubeCandidate.php
    ExportFile.php
    YoutubeSearchCache.php

  Http/
    Controllers/
      DashboardController.php
      ImportBatchController.php
      ImportItemController.php
      YoutubeSearchController.php
      ExportController.php

  Services/
    ImportParserService.php
    DuplicateDetectionService.php
    YoutubeSearchService.php
    YoutubeSearchUrlService.php
    YoutubeScoringService.php
    YoutubeUrlNormalizer.php
    SupersimpleTxtExportService.php
    ExportFilenameService.php

  Jobs/
    SearchYoutubeForBatchJob.php
    SearchYoutubeForImportItemJob.php
    GenerateSupersimpleTxtExportJob.php
```

Frontend:

```text
resources/views/
  dashboard.blade.php
  imports/
    index.blade.php
    create.blade.php
    show.blade.php
    review.blade.php
  exports/
    index.blade.php

resources/js/components/
  ImportTextarea.vue
  ParseReviewTable.vue
  DuplicateModal.vue
  YoutubeResultsTable.vue
  YoutubeCandidateList.vue
  CustomYoutubeLinkModal.vue
  ExportWarningModal.vue
  ExportVersionModal.vue
```

## Routes Guidance

Expected web routes:

```text
GET    /
GET    /imports
GET    /imports/create
POST   /imports
GET    /imports/{batch}
GET    /imports/{batch}/review
POST   /imports/{batch}/parse
POST   /imports/{batch}/youtube/search
PATCH  /items/{item}
POST   /items/{item}/youtube/select
POST   /items/{item}/youtube/custom
POST   /items/{item}/toggle-export
POST   /imports/{batch}/exports/supersimple-txt
GET    /exports/{export}/download
```

Use route-model binding where appropriate.

## Code Style Rules

Follow Laravel conventions.

Prefer:

- Small controllers.
- Business logic in services.
- Jobs for slower/background operations.
- Form requests for validation if forms grow.
- Eloquent relationships.
- Clear enum-like constants for statuses if helpful.
- Tests for parser, URL normalizer, export generation, scoring.

Avoid:

- Huge controllers.
- Business logic directly in Blade.
- Adding auth in MVP.
- Adding full SPA architecture.
- Adding unnecessary packages.
- Implementing audio download.
- Scraping YouTube unless explicitly requested.

## Safety Rules

Before destructive actions, ask for confirmation.

Do not run destructive commands without explicit permission:

```bash
rm -rf
php artisan migrate:fresh
php artisan db:wipe
docker volume rm
docker system prune
```

For migrations:

- Prefer `php artisan migrate`.
- Ask before resetting DB.

For generated files:

- Do not overwrite user-created docs without preserving important content.
- If rewriting docs, keep useful prior information.

## Commands

Use Sail when available.

Common commands:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan test
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
./vendor/bin/sail composer install
```

If Sail is not installed yet, inspect the project first and then propose setup steps.

## Initial Setup Tasks If Project Is Empty

If this directory does not yet contain a Laravel project, do not blindly create one without confirming.

Recommended plan:

1. Confirm empty/non-empty directory.
2. Create or install Laravel.
3. Install Sail.
4. Configure PostgreSQL service.
5. Create docs directory.
6. Create initial docs.
7. Create migrations/models.
8. Build minimal dashboard/import flow.

## Testing Strategy

Prioritize tests for:

- Import parser.
- Duplicate detection.
- YouTube URL normalizer.
- YouTube search URL generator.
- Supersimple TXT export generation.
- Export filename versioning.
- YouTube scoring.

Manual QA checklist should live in:

```text
docs/TESTING.md
```

## Development Log Requirement

After every meaningful task, append to:

```text
docs/DEVELOPMENT-LOG.md
```

Use format:

```md
## YYYY-MM-DD

### Changed
- ...

### Added
- ...

### Notes
- ...
```

## Current MVP Acceptance Criteria

MVP is complete when:

- [ ] App runs locally via Sail.
- [ ] App has own PostgreSQL container.
- [ ] No auth is required.
- [ ] User can create new import.
- [ ] Import name auto-generates and can be edited.
- [ ] User can paste 10–30 tracks.
- [ ] System parses `Artist - Title`.
- [ ] Parse review is always shown.
- [ ] User can edit artist/title/genre.
- [ ] Exact duplicates show modal.
- [ ] Similar duplicates show warning.
- [ ] API mode fetches 3 YouTube candidates.
- [ ] No-key mode generates YouTube search URL.
- [ ] Links open in new tab.
- [ ] User can select candidate.
- [ ] User can paste custom YouTube link.
- [ ] Custom link is validated and normalized.
- [ ] User can toggle `include_in_export`.
- [ ] Export creates TXT with only URLs.
- [ ] Missing links are skipped after warning.
- [ ] Export is stored in history.
- [ ] Re-export can create `_v2`, `_v3`, etc.
- [ ] User can download TXT.

## Communication Style

When reporting progress:

- Be concise.
- Mention files changed.
- Mention docs updated.
- Mention commands run.
- Mention tests run or not run.
- Mention next recommended task.

Example final update:

```text
Done:
- Added ImportBatch and ImportItem migrations/models.
- Added parser service.
- Added initial parse review route.
- Updated docs/DATABASE.md and docs/DEVELOPMENT-LOG.md.

Tests:
- Added parser unit test.
- Ran ./vendor/bin/sail artisan test.

Next:
- Build duplicate detection modal.
```

## Do Not Implement Yet

Do not implement these unless explicitly requested:

- Beatport integration.
- Spotify integration.
- SoundCloud integration.
- Rekordbox export.
- Local music file scanner.
- Audio download.
- User auth.
- RabbitMQ.
- Full SPA/Inertia migration.
- AI parsing.
- Audio fingerprinting.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
