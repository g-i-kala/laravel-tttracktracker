# Start tutaj

## Co to jest

**TTTrackTracker** (projekt: DJ Library Agent) to lokalna aplikacja Laravel dla workflow DJ-a.

MVP (Milestone 1) działa tak:
1. Wklejasz listę utworów w dowolnym formacie (z kluczem AI także np. wklejkę z Beatportu), a aplikacja wyciąga artystę, tytuł i gatunek.
2. Przeglądasz i edytujesz wynik.
3. Aplikacja generuje linki wyszukiwania YouTube (na start bez klucza API; tryb API dochodzi równolegle).
4. Wybierasz linki.
5. Dostajesz plik TXT „Supersimple” z samymi URL-ami YouTube.

Brak pobierania audio. Brak logowania (aplikacja jednoosobowa, lokalna). UI po angielsku.

## Aktualny status

Stan na 2026-10-13:

- Laravel 13, PHP 8.4, Sail (`compose.yaml`): app, PostgreSQL 16 (`tttracktracker`, port 5433 na hoście), worker kolejki jako serwis `queue`. Aplikacja: http://localhost:8000.
- Frontend: Blade + **Livewire 4.4** (full-page, class-based, jeden komponent na ekran), Tailwind 4, własny `<x-modal>`. Sidebar, motyw jasny/ciemny wg systemu, akcent z `APP_ACCENT`.
- Model danych gotowy: 5 tabel, enumy statusów, modele, factories.
- **Działa:** Dashboard (placeholder), **New Import** (`/imports/create`), **Parse Review** (`/imports/{batch}/review`: edycja z autosave, usuwanie wierszy, **Save & close**), **lista importów** (`/imports`).
- **Parsowanie:** z kluczem AI (OpenAI, `gpt-4.1-mini`, Laravel AI SDK) dowolny format w tle; bez klucza parser linii `Artist - Title`.
- **Jeszcze nie ma:** duplikatów, wyszukiwania YouTube, eksportu TXT, listy eksportów, ustawień AI.
- Testy: 72, wszystkie przechodzą.

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

### Zasady współpracy (ważne)

- Pracujemy **atomowo**: jedno zadanie naraz, potem raport i propozycja następnego kroku. Czekamy na zgodę.
- **Pytamy**, gdy coś jest niejasne albo decyzji jest kilka. Opcje podajemy z rekomendacją, „tworzymy team”.
- Commit tylko na prośbę (Conventional Commits). Docs aktualizujemy przy każdej zmianie.
- **Nigdy nie wyświetlamy sekretów z `.env`** (`cat` / `grep` na liniach z kluczami). Patrz incydent niżej.

### Sesja 2026-10-11: setup i przegląd architektury

- Sail, PostgreSQL, worker kolejki, Laravel Boost, `docs/` (13 plików), git.
- Przegląd architektury (Q&A), decyzje w [DECISIONS.md](DECISIONS.md) „Architecture review”.
- Decyzja: Livewire zamiast Vue.

### Sesja 2026-10-13: od docs do działającego importu

**Zrobione (commity po kolei):**
1. `CLAUDE.md` odchudzony (specyfikacja tylko w `docs/`, `docs/` są źródłem prawdy).
2. Livewire 4.4 i konwencje (full-page, class-based, `<x-modal>`, jeden komponent na ekran). `boost:update` dodał skill `livewire-development`.
3. Enumy, migracje, modele, factories. Klucze obce `restrictOnDelete`, statusy jako string + enum cast.
4. `config/services.php`: `youtube` (progi 80/60, limit 3, cache 30 dni) i `track_list_ai`.
5. `ImportParserService`: parser linii i `normalized_key`.
6. Layout (sidebar), `<x-modal>`, `<x-nav-link>` („Coming soon”), placeholder Dashboard. Akcent `navy` (granat w trybie jasnym, błękit w ciemnym), lokalnie `APP_ACCENT=navy`.
7. New Import: auto-nazwa w czasie `Europe/Warsaw`, licznik na żywo, walidacja 1–200 tracków.
8. Parse Review: autosave po wyjściu z pola, przeliczanie klucza, usuwanie z potwierdzeniem, Search YouTube na razie wyszarzony.
9. **Parsowanie AI** (Laravel AI SDK): agent `TrackListParser`, job `ParseImportBatchWithAiJob`, status `parsing`, polling na review, przyciski retry i „simple parser”. Powód: wklejka z Beatportu (jeden track zajmuje ~8 linii) rozpadała się na śmieciowe itemy.
10. Model AI `gpt-4.1-mini`. Test na prawdziwym API: 2 tracki z Beatportu poprawnie w 3,4 s.

**Niezacommitowane (do commita na początku następnej sesji):**
- `.env.example` z `AI_PARSER_MODEL=gpt-4.1-mini` plus docs (DECISIONS, ARCHITECTURE, DOCKER, TODO, DEVELOPMENT-LOG, START-TUTAJ). Proponowany commit: `chore(ai): use gpt-4.1-mini for track list parsing`.

**Incydent: klucz OpenAI w logu sesji**
- 2026-10-13 pełny `OPENAI_API_KEY` wyświetlił się w wyniku polecenia, bo maskowanie `sed` nie działa na macOS (BSD sed). Nie ma go w gicie ani w plikach projektu.
- Decyzja użytkownika: **bez rotacji**, bo klucz ma z góry limit $4. Ryzyko zaakceptowane.
- Opcjonalnie: wyczyścić klucz z transkryptów Claude Code w `~/.claude/projects/-Volumes-data-dev-VibeCode-tt-tracktracker/*.jsonl`.

**Lokalny `.env` (poza gitem):** `APP_ACCENT=navy`, `APP_TIMEZONE=Europe/Warsaw`, `OPENAI_API_KEY` ustawiony, `AI_PARSER_MODEL=gpt-4.1-mini`, `DB_QUEUE_RETRY_AFTER=300`. Po zmianach `.env` uruchom `sail artisan config:clear` i `sail artisan queue:restart`.

**Do zrobienia przez użytkownika:**
- Przetestować w UI pełną wklejkę z Beatportu. Jeśli AI coś źle parsuje, dostroić instrukcje agenta (`app/Ai/Agents/TrackListParser.php`).
- Klucz YouTube Data API (na razie nie jest potrzebny, tryb bez klucza jest pierwszy).

**Następne kroki (po kolei):**
1. Commit niezacommitowanych zmian (wyżej).
2. **Duplikaty**: `DuplicateDetectionService`, modal dla dokładnych duplikatów (Remove duplicates / Keep all / Review manually), ostrzeżenie dla podobnych. Najpierw pytania o to, co liczy się jako „podobny”.
3. Wyszukiwanie YouTube w trybie bez klucza (search URL), ekran YouTube Results (`Imports\Show`), custom link z normalizacją.
4. Eksport Supersimple TXT, wersjonowanie, historia, download.
5. Dashboard z ostatnimi importami i eksportami, lista importów (`/imports`).
6. Drobne: `.vscode/extensions.json`, decyzja o `database/database.sqlite`.
