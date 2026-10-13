# CLAUDE.md

This file provides guidance to Claude Code when working in this repository.

It holds **rules and a map of the docs**. The product/technical specification lives in `docs/`, which is the **source of truth** (see [Documentation map](#documentation-map)).

## Project

**TTTrackTracker** (`APP_NAME`), project: DJ Library Agent. Database: `tttracktracker`.

A local-first, single-user Laravel app for a DJ workflow. Current milestone (MVP) — YouTube Supersimple TXT Exporter:

```text
Manual track import
→ parse Artist/Title
→ review/edit
→ search YouTube or generate manual YouTube search URLs
→ select/customize links
→ generate Supersimple TXT export (YouTube URLs only, one per line)
```

No audio downloading. No auth. Details: `docs/PRD.md`, roadmap: `docs/ROADMAP.md`.

## Working Rules

- **Execute tasks atomically** unless explicitly instructed otherwise: do exactly the requested task, nothing more.
- Do not chain into follow-up steps (next setup stage, extra features, refactors) without being asked — propose them instead.
- Report what was done and suggest the next step; wait for confirmation before continuing.
- **We work as a team: ask questions** whenever something is unclear or a task involves several decisions. Present the options with a recommendation instead of deciding silently.
- **At the start of every session, read `docs/START-TUTAJ.md`** (status + session handoff notes) before doing anything else.
- At the end of a session (or when asked to save progress), update the handoff notes in `docs/START-TUTAJ.md`.
- Use **Conventional Commits** for commit messages: `type(scope): description`, e.g. `feat(import): add parser service`, `docs: update DATABASE.md`, `build(sail): add queue service`. Types: `feat`, `fix`, `docs`, `build`, `chore`, `refactor`, `test`, `style`, `perf`, `ci`.
- Commit only when asked.

## Documentation Policy

- All project documentation lives in `docs/`. Maintain it continuously; after each meaningful change, update the relevant docs. Do not leave docs stale.
- This policy overrides the Laravel Boost guideline "only create documentation files if explicitly requested".
- If `CLAUDE.md` and `docs/` disagree, `docs/` wins (especially `DECISIONS.md`, `DATABASE.md`, `ARCHITECTURE.md`). Fix the inconsistency.

### Documentation map

| File                        | Purpose                                                              |
| --------------------------- | -------------------------------------------------------------------- |
| `docs/START-TUTAJ.md`       | Entry point: what it is, how to run, status, **session handoff notes** |
| `docs/PRD.md`               | MVP definition, glossary, user flow, requirements, acceptance criteria, out of scope |
| `docs/ARCHITECTURE.md`      | Modules, Laravel structure, data flow, queue, YouTube API/fallback, scoring, export |
| `docs/TECH-STACK.md`        | Versions and packages, conventions, what is not used                 |
| `docs/ROADMAP.md`           | Milestones 1–4, future enhancements                                  |
| `docs/DATABASE.md`          | Tables, columns, relationships, status enums, indexes                |
| `docs/API.md`               | Web routes, endpoints, controller/action mapping                     |
| `docs/UI-FLOW.md`           | Screens, flows, modals                                               |
| `docs/DOCKER.md`            | Sail, PostgreSQL, `.env`, queue worker, troubleshooting              |
| `docs/DECISIONS.md`         | ADR-like decision log                                                |
| `docs/DEVELOPMENT-LOG.md`   | Chronological work log                                               |
| `docs/TODO.md`              | Current actionable checklist                                         |
| `docs/TESTING.md`           | How to run tests, strategy, important cases, manual QA checklist     |

Optional later (create when useful): `YOUTUBE.md`, `BEATPORT.md`, `EXPORTS.md`, `REKORDBOX.md`, `LOCAL-INVENTORY.md`, `CHANGELOG.md`.

New ADR entries in `DECISIONS.md` use: `## YYYY-MM-DD — Title`, then **Decision:**, **Reason:**, **Consequences:**.

### Documentation update rules

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

### Development log format

After every meaningful task, append to `docs/DEVELOPMENT-LOG.md`:

```md
## YYYY-MM-DD

### Changed
- ...

### Added
- ...

### Notes
- ...
```

## Tech Stack (summary)

Full details: `docs/TECH-STACK.md`, `docs/DOCKER.md`.

- Laravel 13, PHP 8.4, Laravel Sail (`compose.yaml`), PostgreSQL 16 in a project-local `pgsql` container (`FORWARD_DB_PORT=5433`, because shared `pg-dev` uses 5432).
- Frontend: **Blade pages + Livewire** components, Vite, Tailwind CSS 4. No Inertia, no full SPA, no Vue.
- Queue: `database` connection, worker runs as the `queue` service in `compose.yaml`. After code changes: `./vendor/bin/sail artisan queue:restart`. Do not introduce Redis/RabbitMQ unless requested.
- Auth: none (single-user local app). Do not add Breeze, Jetstream, Sanctum or login unless explicitly requested.
- YouTube: works without an API key (search URLs + custom links); API mode when `YOUTUBE_DATA_API_KEY` is set. Read config via `config('services.youtube.*')`, never `env()` in app code.

## Code Style Rules

Follow Laravel conventions.

Prefer:

- Small controllers.
- Business logic in services.
- Jobs for slower/background operations.
- Form requests for validation if forms grow.
- Eloquent relationships.
- PHP backed enums for statuses (`app/Enums/`).
- Tests for parser, URL normalizer, export generation, scoring.

Avoid:

- Huge controllers.
- Business logic directly in Blade.
- Adding unnecessary packages.
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

Run everything through Sail:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:restart
./vendor/bin/sail logs -f queue
./vendor/bin/sail artisan test
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
./vendor/bin/sail composer install
```

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
- PHP packages: run `vendor/bin/sail composer show --direct` to list direct dependencies with versions, or `vendor/bin/sail composer show <vendor/package>` for a single package.
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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

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

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

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

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `vendor/bin/sail artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
