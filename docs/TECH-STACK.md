# Tech Stack

## Current (installed)

| Area          | Choice / version                          |
| ------------- | ----------------------------------------- |
| PHP (host)    | 8.4.6 (composer requires `^8.3`)          |
| Laravel       | 13.35.0                                   |
| Testing       | PHPUnit 12.5, Mockery, Faker, Collision   |
| Code style    | Laravel Pint 1.32                         |
| AI tooling    | Laravel Boost 2.10.3 (dev)                |
| Other dev     | Laravel Pail, Laravel Pao, Tinker         |
| Frontend build| Vite 8, laravel-vite-plugin 3             |
| CSS           | Tailwind CSS 4 (`@tailwindcss/vite`)      |
| Queue         | `database` (set in `.env`)                |

## Planned (not installed yet)

| Area          | Choice                                    |
| ------------- | ----------------------------------------- |
| Local env     | Laravel Sail                              |
| Database      | PostgreSQL in project-local Sail container|
| Frontend      | Blade pages + Vue 3 components            |

## Not used (by decision)

- Inertia / full SPA.
- Breeze / Jetstream / Sanctum / any auth.
- Redis / RabbitMQ (RabbitMQ maybe later).

## Laravel Boost

- Installed with guidelines, skills and MCP server (`php artisan boost:mcp`).
- Configured for Claude Code, Cursor, OpenCode (`.mcp.json`, `.cursor/`, `opencode.json`, `.claude/skills`, `.agents/skills`).
- `boost.json` has `"sail": false` — re-run `php artisan boost:install` after Sail is installed so the MCP server runs through Sail.
