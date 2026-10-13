# Start tutaj

## Co to jest

**TTTrackTracker** (projekt: DJ Library Agent) to lokalna aplikacja Laravel dla workflow DJ-a.

MVP (Milestone 1) działa tak:
1. Wklejasz listę utworów, a aplikacja parsuje `Artist - Title`.
2. Przeglądasz i edytujesz wynik.
3. Aplikacja generuje linki wyszukiwania YouTube (na start bez klucza API; tryb API dochodzi równolegle).
4. Wybierasz linki.
5. Dostajesz plik TXT „Supersimple” z samymi URL-ami YouTube.

Brak pobierania audio. Brak logowania (aplikacja jednoosobowa, lokalna). UI po angielsku.

## Aktualny status

- Laravel 13 + Laravel Boost.
- Sail skonfigurowany (`compose.yaml`): PHP 8.4, PostgreSQL 16, worker kolejki jako serwis `queue`.
- `.env` ustawiony pod Sail (baza `tttracktracker`, port 5433 na hoście).
- **Kontenery Sail działają, aplikacja jest online** (http://localhost:8000), potwierdzone 2026-10-11.
- Przegląd architektury zakończony, decyzje w [DECISIONS.md](DECISIONS.md).
- Frontend: **Livewire 4.4** zainstalowany (2026-10-13): komponenty full-page, class-based, jeden na ekran, własny `<x-modal>`. Boost ma wytyczne i skill `livewire-development`.
- Model danych gotowy (2026-10-13): enumy statusów, 5 migracji, modele, factories, test `ImportModelsTest`. **Brak** logiki biznesowej i UI.

Szczegóły: [TODO.md](TODO.md), [DEVELOPMENT-LOG.md](DEVELOPMENT-LOG.md).

## Uruchomienie lokalne

Pierwsze uruchomienie:

```bash
./vendor/bin/sail build
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Zamiast `./vendor/bin/sail` możesz pisać samo `sail` (alias w `~/.zshrc`, działa z root projektu, patrz [DOCKER.md](DOCKER.md)).

Aplikacja: http://localhost:8000. Baza z hosta: `localhost:5433` (user `sail`, hasło `password`).

Instrukcja środowiska: [DOCKER.md](DOCKER.md).

## Najważniejsze komendy

| Cel                  | Komenda                                    |
| -------------------- | ------------------------------------------ |
| Start kontenerów     | `./vendor/bin/sail up -d`                  |
| Stop kontenerów      | `./vendor/bin/sail down`                   |
| Migracje             | `./vendor/bin/sail artisan migrate`        |
| Restart workera      | `./vendor/bin/sail artisan queue:restart`  |
| Logi workera         | `./vendor/bin/sail logs -f queue`          |
| Testy                | `./vendor/bin/sail artisan test`           |
| Frontend (dev)       | `./vendor/bin/sail npm run dev`            |

## Dokumentacja

- [PRD.md](PRD.md): wymagania produktu
- [ARCHITECTURE.md](ARCHITECTURE.md): architektura
- [TECH-STACK.md](TECH-STACK.md): stack technologiczny
- [ROADMAP.md](ROADMAP.md): plan etapów
- [DATABASE.md](DATABASE.md): schemat bazy
- [API.md](API.md): trasy i endpointy
- [UI-FLOW.md](UI-FLOW.md): przepływ UI
- [DOCKER.md](DOCKER.md): środowisko lokalne
- [DECISIONS.md](DECISIONS.md): log decyzji
- [DEVELOPMENT-LOG.md](DEVELOPMENT-LOG.md): dziennik prac
- [TODO.md](TODO.md): bieżące zadania
- [TESTING.md](TESTING.md): testy i QA

---

## Notatki z sesji (handoff)

> Do przeczytania na początku nowej sesji, po `/clear`. Aktualizować na końcu każdej sesji.

### Sesja 2026-10-11: setup i przegląd architektury

**Zrobione:**
- `CLAUDE.md` przeformatowany do poprawnego Markdown. Dodana sekcja **Working Rules**: pracujemy **atomowo**, czyli robimy tylko to, o co poproszono, kolejne kroki proponujemy i czekamy na zgodę.
- Laravel Boost 2.10.3 zainstalowany (guidelines, skills, MCP). Jego wytyczne są doklejone na końcu `CLAUDE.md` i `AGENTS.md`. Nasza polityka docs ma pierwszeństwo przed regułą Boosta „docs tylko na prośbę”.
- `docs/` z 13 wymaganymi plikami.
- Git zainicjowany (commity: `first commit`, `init: init`).
- Sail: `compose.yaml` (nie `docker-compose.yml`) z PHP 8.4, `postgres:16` i serwisem `queue`. PHP 8.3 odpada, bo Symfony 8 wymaga >= 8.4.1.
- `.env` / `.env.example`: `APP_NAME=TTTrackTracker`, `APP_PORT=8000`, baza `tttracktracker` (user `sail` / hasło `password`), `FORWARD_DB_PORT=5433` (bo `pg-dev` zajmuje 5432), klucze `YOUTUBE_*` (klucz API na razie pusty).
- Przegląd architektury w formie Q&A. Wszystkie decyzje są w [DECISIONS.md](DECISIONS.md) („Architecture review”). W skrócie:
  - na start tryb no-key, API dochodzi równolegle, przy wyczerpaniu quoty item przechodzi na search URL;
  - scoring tylko po tytule, bez `duration_seconds`;
  - wybrany link: snapshot `selected_youtube_*` na itemie, bez `is_selected`;
  - statusy tylko jako etapy pipeline'u, w PHP enums; bez liczników na batchu;
  - eksport synchroniczny; wyszukiwanie przez `Bus::batch()`; worker jako serwis w compose;
  - własna tabela `youtube_search_cache`; konfiguracja przez `config('services.youtube.*')`;
  - kolumna `normalized_key` od razu, duplikaty między batchami po MVP;
  - `year`/`month` służą do filtrowania; UI po angielsku; frontend to Vue w Blade (później zmienione na Livewire).
- Alias `sail` jest w `~/.zshrc` i działa z root projektu.

**Koniec sesji:**
- Pierwsze uruchomienie zrobione: kontenery działają, aplikacja jest online.
- Decyzja: **Livewire zamiast Vue** (wpis w [DECISIONS.md](DECISIONS.md), C2 oznaczone jako zastąpione).
- ~~Pozostałe docs i `CLAUDE.md` jeszcze wspominają Vue~~ (poprawione 2026-10-13).
- Dalsze prace: następna sesja (2026-10-12).

**W toku (robi użytkownik):**
- Zdobycie klucza YouTube Data API.

**Uwaga, znana niespójność:**
- Specyfikacja w `CLAUDE.md` (statusy, schemat tabel, lista jobów, struktura `app/`) jest **starsza** niż decyzje z Q&A. **Źródłem prawdy są `docs/`** (DATABASE, ARCHITECTURE, DECISIONS). Naprawi to zadanie „odchudzenie CLAUDE.md”.
- Zmiany z tej sesji są zacommitowane (`build(sail): ...`, `docs: ...`). `.env` jest w `.gitignore`.

**Następne kroki (do wyboru, po kolei):**
1. ~~Sprawdzić `sail up`, `migrate` i worker~~ **(zrobione)**: 3 kontenery działają, migracje przeszły, worker działa (przed `migrate` restartował się z błędem braku tabeli `cache`, to normalne).
2. ~~Ponowić `boost:install` pod Sail~~ **(zrobione**: `sail: true`, MCP przez `vendor/bin/sail`, tylko Claude Code). `.cursor/` i `opencode.json` usunięte. Zostało: dodać `.vscode/extensions.json`.
3. ~~Odchudzić `CLAUDE.md`~~ **(zrobione 2026-10-13**: 1253 → 370 linii, specyfikacja tylko w `docs/`, słowniczek przeniesiony do PRD.md, `CLAUDE.md` mówi już o Livewire).
4. ~~Zaktualizować docs i `CLAUDE.md` pod Livewire, potem zainstalować Livewire~~ **(zrobione 2026-10-13**, konwencje w [DECISIONS.md](DECISIONS.md) „Livewire conventions”).
5. ~~Start MVP: enumy, migracje, modele i factories~~ **(zrobione 2026-10-13**, konwencje w [DECISIONS.md](DECISIONS.md) „Schema conventions”).
6. ~~`config/services.php` (YouTube)~~ **(zrobione 2026-10-13**: 5 kluczy, patrz [ARCHITECTURE.md](ARCHITECTURE.md#youtube-api--fallback-strategy)).
7. ~~Parser + `normalized_key`~~ **(zrobione 2026-10-13**: `ImportParserService`, reguły w [ARCHITECTURE.md](ARCHITECTURE.md#import-parsing-implemented)).
8. ~~Layout + `<x-modal>`~~ **(zrobione 2026-10-13**: sidebar, motyw wg systemu, akcent z `APP_ACCENT`, placeholder Dashboard).
9. ~~Ekran New Import~~ **(zrobione 2026-10-13**: `/imports/create`, po zapisie na razie redirect na Dashboard).
10. ~~Parse Review~~ **(zrobione 2026-10-13**: edycja z autosave, usuwanie wierszy, New Import przekierowuje na review).
11. ~~Parsowanie AI~~ **(zrobione 2026-10-13**: Laravel AI SDK, OpenAI, job w kolejce; bez klucza zwykły parser). **Do zrobienia przez użytkownika:** wpisać `OPENAI_API_KEY` w `.env`, `sail artisan queue:restart`, przetestować wklejkę z Beatportu.
12. Dalej: **duplikaty** (`DuplicateDetectionService`, modal exact duplicates, ostrzeżenie similar).
