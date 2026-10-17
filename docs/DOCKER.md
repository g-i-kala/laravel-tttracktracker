# Docker / Sail

Laravel Sail is installed (`compose.yaml` in project root, not `docker-compose.yml`).

## Services

| Service        | Image / runtime          | Purpose                                   | Host port |
| -------------- | ------------------------ | ----------------------------------------- | --------- |
| `laravel.test` | `sail-8.4/app` (PHP 8.4) | Web app + Vite dev server                 | `APP_PORT` (8000), `VITE_PORT` (5173) |
| `queue`        | `sail-8.4/app`           | `php artisan queue:work --tries=3 --timeout=90` | — |
| `pgsql`        | `postgres:16`            | Project-local PostgreSQL                  | `FORWARD_DB_PORT` (5433) |

Notes:

- **PHP 8.4**, not 8.3: `composer.lock` contains Symfony 8 packages that require PHP >= 8.4.1. The host also runs PHP 8.4.
- `queue` reuses the image built for `laravel.test` and has `restart: unless-stopped`.
- `laravel.test` and `queue` wait for `pgsql` to be healthy (`pg_isready`).
- `pgsql` mounts Sail's `create-testing-database.sql`, so a `testing` database is created on first start. PHPUnit uses it (`phpunit.xml` → `DB_DATABASE=testing`).
- Data volume: `sail-pgsql` → `/var/lib/postgresql/data`.

## `.env` requirements

```env
APP_NAME=TTTrackTracker
APP_ACCENT=red          # UI accent: red, indigo, emerald, amber, sky, violet, navy (navy light / light blue dark)
APP_TIMEZONE=Europe/Warsaw

OPENAI_API_KEY=           # set → AI parsing on (any pasted format); empty → line parser
AI_PARSER_PROVIDER=openai # any provider from config/ai.php (anthropic needs ANTHROPIC_API_KEY)
AI_PARSER_MODEL=gpt-4.1-mini  # small model is enough for extraction; empty = provider default
AI_PARSER_TIMEOUT=180
DB_QUEUE_RETRY_AFTER=300  # must exceed the AI job timeout (240 s)
APP_URL=http://localhost:8000
APP_PORT=8000

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=tttracktracker
DB_USERNAME=sail
DB_PASSWORD=password
FORWARD_DB_PORT=5433

QUEUE_CONNECTION=database

YOUTUBE_DATA_API_KEY=
YOUTUBE_AUTO_INCLUDE_THRESHOLD=80
YOUTUBE_PRESELECT_THRESHOLD=60
YOUTUBE_CANDIDATES_LIMIT=3
YOUTUBE_CACHE_TTL_DAYS=30
YOUTUBE_DAILY_QUOTA=10000
```

- `DB_HOST=pgsql` / `DB_PORT=5432` are used **inside** Docker.
- From the host (TablePlus, pgAdmin, `psql`), connect to `localhost:5433`.
- `FORWARD_DB_PORT=5433` because the shared `pg-dev` container already binds host port 5432.
- `WWWUSER` / `WWWGROUP` are set by the `sail` script. Warnings about them from plain `docker compose` are harmless.
- The Postgres user and database are created only on the **first** start of an empty volume. Changing `DB_*` later requires recreating the volume (destructive, ask first).

## `sail` alias

Commands below use `./vendor/bin/sail`. With a shell alias you can type just `sail` (run from the project root):

```zsh
# ~/.zshrc (already present on the main dev machine)
alias sail='bash vendor/bin/sail'
```

## First run

```bash
./vendor/bin/sail build
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build   # or `npm run dev` while working on the UI
```

App: http://localhost:8000

## Useful commands

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail ps
./vendor/bin/sail logs -f
./vendor/bin/sail logs -f queue
./vendor/bin/sail artisan migrate
./vendor/bin/sail psql
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

## Queue worker

The worker runs automatically in the `queue` service.

```bash
./vendor/bin/sail artisan queue:restart     # after code changes (worker keeps old code in memory)
./vendor/bin/sail artisan queue:failed
./vendor/bin/sail artisan queue:retry all
./vendor/bin/sail logs -f queue
```

## After Sail setup

Re-run `php artisan boost:install` so Boost's MCP server runs through Sail (`boost.json` currently has `"sail": false`).

## Troubleshooting

- **Port 5432 already in use** → `FORWARD_DB_PORT` must not be 5432 (`pg-dev` uses it).
- **Port 8000 already in use** → change `APP_PORT` and `APP_URL` together.
- **Queue jobs not processed** → `./vendor/bin/sail ps` (is `queue` running?), `./vendor/bin/sail logs queue`.
- **`queue` restarting with `relation "cache" does not exist`** → migrations have not run yet; run `./vendor/bin/sail artisan migrate`, the worker recovers by itself (`restart: unless-stopped`).
- **`Unable to locate file in Vite manifest`** or unstyled page → `./vendor/bin/sail npm run build` (or keep `./vendor/bin/sail npm run dev` running).
- **Accent color change not visible** → after editing `APP_ACCENT`, run `./vendor/bin/sail artisan config:clear` if config is cached.
- **AI parsing stuck on "Parsing…"** → is the `queue` service running? `./vendor/bin/sail logs -f queue`; after code or `.env` changes `./vendor/bin/sail artisan queue:restart`. Errors are logged (`storage/logs/laravel.log`, "AI track list parsing failed").
- **Code changes not visible in jobs** → `./vendor/bin/sail artisan queue:restart`.
- Destructive commands (`docker volume rm`, `docker system prune`, `migrate:fresh`, `db:wipe`) require explicit permission.
