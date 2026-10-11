# Docker / Sail

> Status: Sail **not installed yet**.

## Sail setup (planned)

```bash
composer require laravel/sail --dev
php artisan sail:install --with=pgsql
./vendor/bin/sail up -d
```

After Sail is installed, re-run `php artisan boost:install` so Boost's MCP server uses Sail.

## PostgreSQL service

Project-local `pgsql` container (not the shared `pg-dev`).

## `.env` requirements

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=dj_library_agent
DB_USERNAME=sail
DB_PASSWORD=password

QUEUE_CONNECTION=database

YOUTUBE_DATA_API_KEY=
YOUTUBE_AUTO_INCLUDE_THRESHOLD=80
YOUTUBE_CANDIDATES_LIMIT=3
```

Current `.env` still points to `DB_HOST=pg-dev` / `DB_DATABASE=tttracktracker` — to be changed during Sail setup.

## Useful commands

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail ps
./vendor/bin/sail logs -f
./vendor/bin/sail artisan migrate
./vendor/bin/sail psql
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

## Queue worker

```bash
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan queue:failed
./vendor/bin/sail artisan queue:retry all
```

## Troubleshooting

- Port 5432 conflict with host `pg-dev` container → set `FORWARD_DB_PORT` in `.env` to another port.
- Destructive commands (`docker volume rm`, `docker system prune`, `migrate:fresh`) require explicit permission.
