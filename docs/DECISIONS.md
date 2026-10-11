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
