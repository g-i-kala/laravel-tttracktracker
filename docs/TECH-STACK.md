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
| AI in app      | Laravel AI SDK `laravel/ai` ^1.2 (`config/ai.php`); provider OpenAI by default, configurable via `AI_PARSER_PROVIDER` / `AI_PARSER_MODEL` |
| Other dev      | Laravel Pail, Laravel Pao, Tinker                   |
| Frontend build | Vite 8, laravel-vite-plugin 3                       |
| CSS            | Tailwind CSS 4 (`@tailwindcss/vite`)                |
| Frontend       | Livewire 4.4 (+ Alpine.js bundled); full-page, class-based components, one per screen (`config/livewire.php`: `make_command.type = class`, layout `layouts::app`) |
| Editor         | VS Code                                             |
| Timezone       | `APP_TIMEZONE` (`Europe/Warsaw`); `config/app.php` reads it from `.env` |
| UI theme       | OS light/dark; accent palette from `APP_ACCENT` (`config('app.accent')`) |

## Conventions

- UI language: **English**.
- Statuses as PHP backed enums (`app/Enums/`).
- YouTube settings via `config('services.youtube.*')` in `config/services.php`; never `env()` in app code.

## Not used (by decision)

- Inertia / full SPA, Vue.
- Modal / UI component packages (Flux, wire-elements/modal): own `<x-modal>` instead. Flux may be reconsidered later for nicer UI.
- Breeze / Jetstream / Sanctum / any auth.
- Redis / RabbitMQ (RabbitMQ maybe later).

## Laravel Boost

- Installed with guidelines, skills and MCP server.
- Configured for Sail (`boost.json` `"sail": true`, MCP via `vendor/bin/sail`) and Claude Code only.
- Skills (`.claude/skills/`): infer-conventions, deploying-to-cloud, laravel-best-practices, testing-best-practices, livewire-development, tailwindcss-development.
- After installing a new ecosystem package, run `./vendor/bin/sail artisan boost:update` to pull its guidelines/skills.
- The `livewire-development` skill shows single-file components as the v4 default; **our convention is class-based** (DECISIONS.md "Livewire conventions", `config/livewire.php`).
