# Tech Stack

App name: **TTTrackTracker** (project: DJ Library Agent).

## Current (installed)

| Area           | Choice / version                                    |
| -------------- | --------------------------------------------------- |
| PHP            | 8.4 (Sail runtime and host; composer `^8.3`, Symfony 8 needs >= 8.4.1) |
| Laravel        | 13.35.0                                             |
| Local env      | Laravel Sail (`compose.yaml`)                       |
| Database       | PostgreSQL 16, project-local `pgsql` container      |
| Queue          | `database` connection, worker as `queue` service    |
| Cache store    | `database`                                          |
| Testing        | PHPUnit 12.5, Mockery, Faker, Collision; `testing` DB on PostgreSQL |
| Code style     | Laravel Pint 1.32                                   |
| AI tooling     | Laravel Boost 2.10.3 (dev)                          |
| Other dev      | Laravel Pail, Laravel Pao, Tinker                   |
| Frontend build | Vite 8, laravel-vite-plugin 3                       |
| CSS            | Tailwind CSS 4 (`@tailwindcss/vite`)                |
| Editor         | VS Code                                             |

## Planned (not installed yet)

| Area     | Choice                                        |
| -------- | --------------------------------------------- |
| Frontend | Blade pages + Vue 3 components (one `createApp` per page) |

## Conventions

- UI language: **English**.
- Statuses as PHP backed enums (`app/Enums/`).
- YouTube settings via `config('services.youtube.*')` in `config/services.php`; never `env()` in app code.

## Not used (by decision)

- Inertia / full SPA, Livewire.
- Breeze / Jetstream / Sanctum / any auth.
- Redis / RabbitMQ (RabbitMQ maybe later).

## Laravel Boost

- Installed with guidelines, skills and MCP server (`php artisan boost:mcp`).
- Currently configured for Claude Code, Cursor, OpenCode. Cursor/OpenCode files are to be removed (VS Code only).
- `boost.json` has `"sail": false`; re-run `php artisan boost:install` now that Sail is installed.
