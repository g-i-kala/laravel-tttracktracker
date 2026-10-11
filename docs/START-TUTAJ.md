# Start tutaj

## Co to jest

**DJ Library Agent** — lokalna aplikacja Laravel dla workflow DJ-a.

MVP (Milestone 1): wklejasz listę utworów → aplikacja parsuje `Artist - Title` → przeglądasz/edytujesz → szuka YouTube (API lub ręczne linki wyszukiwania) → wybierasz linki → generuje plik TXT „Supersimple” z samymi URL-ami YouTube.

Brak pobierania audio. Brak logowania (aplikacja jednoosobowa, lokalna).

## Aktualny status

- Szkielet Laravel 13 zainstalowany.
- Laravel Boost zainstalowany (guidelines, skills, MCP).
- Folder `docs/` utworzony.
- **Brak** Sail, własnego kontenera PostgreSQL, Vue, migracji i logiki aplikacji.

Szczegóły: [TODO.md](TODO.md), [DEVELOPMENT-LOG.md](DEVELOPMENT-LOG.md).

## Uruchomienie lokalne

Sail nie jest jeszcze zainstalowany. Docelowo:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan queue:work
```

Instrukcja środowiska: [DOCKER.md](DOCKER.md).

## Najważniejsze komendy

| Cel               | Komenda                                  |
| ----------------- | ---------------------------------------- |
| Start kontenerów  | `./vendor/bin/sail up -d`                |
| Stop kontenerów   | `./vendor/bin/sail down`                 |
| Migracje          | `./vendor/bin/sail artisan migrate`      |
| Worker kolejki    | `./vendor/bin/sail artisan queue:work`   |
| Testy             | `./vendor/bin/sail artisan test`         |
| Frontend (dev)    | `./vendor/bin/sail npm run dev`          |

## Dokumentacja

- [PRD.md](PRD.md) — wymagania produktu
- [ARCHITECTURE.md](ARCHITECTURE.md) — architektura
- [TECH-STACK.md](TECH-STACK.md) — stack technologiczny
- [ROADMAP.md](ROADMAP.md) — plan etapów
- [DATABASE.md](DATABASE.md) — schemat bazy
- [API.md](API.md) — trasy i endpointy
- [UI-FLOW.md](UI-FLOW.md) — przepływ UI
- [DOCKER.md](DOCKER.md) — środowisko lokalne
- [DECISIONS.md](DECISIONS.md) — log decyzji
- [DEVELOPMENT-LOG.md](DEVELOPMENT-LOG.md) — dziennik prac
- [TODO.md](TODO.md) — bieżące zadania
- [TESTING.md](TESTING.md) — testy i QA
