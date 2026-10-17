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

Stan na 2026-10-17:

- Laravel 13, PHP 8.4, Sail (`compose.yaml`): app, PostgreSQL 16 (`tttracktracker`, port 5433 na hoście), worker kolejki jako serwis `queue`. Aplikacja: http://localhost:8000.
- Frontend: Blade + **Livewire 4.4** (full-page, class-based, jeden komponent na ekran), Tailwind 4, własny `<x-modal>`. Sidebar, motyw jasny/ciemny wg systemu, akcent z `APP_ACCENT`.
- Model danych: tabele importu/YouTube/eksportu + `ai_usage_logs`, `settings`; enumy statusów, modele, factories. `import_items` ma też `bpm`, `musical_key`, `camelot_key`, `label`.
- **Działa:** **Dashboard** (Needs attention, ostatnie importy i eksporty), **New Import** (`/imports/create`), **Parse Review** (`/imports/{batch}/review`: edycja z autosave, BPM/tonacja/label po kliknięciu w wiersz, usuwanie wierszy, **Save & close**), **lista importów** (`/imports`).
- **Parsowanie:** z kluczem AI (OpenAI, `gpt-4.1-mini`, Laravel AI SDK) dowolny format w tle, także BPM, tonacja (+ Camelot) i label; bez klucza parser linii `Artist - Title`.
- **Duplikaty** w obrębie importu: modal raz po parsowaniu + banner, oznaczenia podobnych (inna wersja, kolejność artystów, drobna literówka).
- **YouTube (bez klucza API):** Search YouTube na Parse Review → ekran **YouTube Results** (`/imports/{batch}`) z linkami wyszukiwania, polem na własny link (normalizacja, autosave) i checkboxem Export.
- **Eksport Supersimple TXT** na YouTube Results: modal brakujących linków, modal wersji (nowa / nadpisz), auto-pobieranie, „Download again”.
- **Lista eksportów** (`/exports`): wszystkie wersje, „Latest”, pobieranie.
- **Usuwanie** importów (z trackami i plikami TXT) i pojedynczych eksportów: wiersz albo zaznaczenie wielu, modal z podsumowaniem.
- **Tryb YouTube API (w trakcie):** Search YouTube z kluczem szuka w tle (kolejka), wybiera najlepszy link wg punktów (≥80 + eksport, 60–79 tylko link). Pasek postępu i podsumowanie na YouTube Results działają; brak jeszcze listy kandydatów (krok 4b) i wyświetlania zużycia limitu.
- Zużycie AI zapisywane w `ai_usage_logs` (tokeny, szacowany koszt). Ekran **AI Settings** (`/settings/ai`): statystyki miesiąca, klucz API w bazie (szyfrowany, ma pierwszeństwo przed `.env`), model, miesięczny budżet (po przekroczeniu parser linii).
- Testy: 280, wszystkie przechodzą.

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
- **Zmiany UI (pola, autosave, nawigacja) najpierw sprawdzamy sami w headless Chrome** (puppeteer-core w scratchpadzie sesji, `/Applications/Google Chrome.app`, tymczasowy profil), dopiero potem test ręczny użytkownika. Bez rozszerzenia Claude in Chrome (użytkownik nie chce). Dane testowe tworzymy jako „ZZ …” i usuwamy po teście.
- Po zmianach w joby/serwisy używane przez kolejkę: `sail artisan queue:restart`. Po nowych klasach Tailwind: `sail npm run build`.

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

**Po teście ręcznym (ta sama sesja, commity):**
11. `chore(ai)`: gpt-4.1-mini + plan w DECISIONS.
12. `feat(import)`: Save & close na Parse Review (status `reviewed`) + lista importów `/imports`.
13. `feat(ai)`: logowanie zużycia AI (`ai_usage_logs`, listener na zdarzenia SDK, ceny w `config/services.php`).
14. `feat(settings)`: Settings → AI (`/settings/ai`): statystyki, klucz w bazie (szyfrowany), model, miesięczny budżet.
15. `feat(import)`: kolumny `bpm`, `musical_key`, `camelot_key`, `label` + `MusicalKeyService` (tonacja → Camelot).

16. `feat(ai)`: agent AI zwraca `bpm`, `key`, `label`; mapowanie (BPM zaokrąglany, poza 40–300 → null) + Camelot. Test na prawdziwej wklejce z Beatportu poprawny. W tym samym commicie **poprawka kosztu**: `config("services.ai_pricing.openai.gpt-4.1-mini")` traktował kropki w nazwie modelu jak zagnieżdżenie, więc koszt nigdy się nie liczył; teraz `config('services.ai_pricing.openai')[$model]` + test regresji. 3 stare logi przeliczone ręcznie (tinker) 2026-10-14.

**Otwarte pytania do użytkownika:** brak. („Saved” na Parse Review: przyczyna znaleziona 2026-10-15, patrz niżej `wire:model.live.blur`.)

**Wyniki testu ręcznego (2026-10-13):** Save & close, lista importów, AI Settings (klucz, Test key, fałszywy klucz, Failed w Recent calls, budżet 0 → parser linii) działają. Przycisk na New Import to „Parse” (nie „Save”).

**Decyzje z tej części sesji** (szczegóły w DECISIONS.md):
- Save & close → status `reviewed` → `/imports`.
- Klucz AI w bazie (szyfrowany, `APP_KEY`), ma pierwszeństwo przed `.env`; miesięczny budżet USD, ostrzeżenie od 80%, po przekroczeniu parser linii; ekran pod `/settings/ai`.
- Metadane tracków: BPM, key (jak w źródle + Camelot), label. **Bez** daty wydania, nazwy EP i ceny. UI: szara linia pod tytułem, edycja po kliknięciu w wiersz. Wrócić do zestawu pól przy imporcie playlist z Rekordboxa/Traktora.

### Sesja 2026-10-14 – 2026-10-17: od metadanych do pełnego MVP bez klucza YouTube

**Zrobione (commity po kolei):**
1. `a6ba8a2 feat(review)`: BPM / key / label na Parse Review: szara linia pod tytułem, panel po kliknięciu w wiersz, Camelot przeliczany przy zapisie.
2. `592a904 feat(review)`: duplikaty. Dokładne (ten sam `normalized_key`): modal raz po parsowaniu (Remove zostawia pierwszy / Keep all / Review manually), potem banner i etykieta „Duplicate of #N”. Podobne (inna wersja, kolejność artystów, drobna literówka w jednej części): ostrzeżenie „Similar to #N”. Liczone na bieżąco, bez migracji.
3. `acf927c feat(youtube)`: tryb bez klucza. Search YouTube na Parse Review → link wyszukiwania (artysta + tytuł bez „(Original Mix)”) → ekran **YouTube Results** `/imports/{batch}`.
4. `4a5a61f feat(youtube)`: własny link w wierszu (`YoutubeUrlNormalizer`: watch, youtu.be, shorts, embed, live) z autosave; poprawny link zaznacza Export; checkbox Export. Bezpośrednie wejście: „YouTube links” na liście importów i „YouTube links →” na Parse Review.
5. `389b2ca feat(export)`: Generate TXT: modal brakujących linków, modal wersji (nowa `_v2` / nadpisz), auto-pobieranie przez `ExportController@download`, pasek „Download again”, status `exported`.
6. `3b48b10 feat(export)`: lista eksportów `/exports` (wszystkie wersje, „Latest”, Download), „Exports” w menu aktywne.
7. `5e8d196 feat(dashboard)`: Needs attention (do przejrzenia, brakujące linki, nieaktualny TXT, czyli plik inny niż obecne linki), ostatnie 10 importów i eksportów.
8. `4ead924 feat(imports)`: usuwanie importów (z trackami, eksportami, plikami) i pojedynczych eksportów: wiersz albo zaznaczenie wielu, modal z podsumowaniem. Usunięcie ostatniego eksportu przywraca status `searched`.

**Ważne poprawki / lekcje:**
- **Livewire ≥ 4.1:** `wire:model.blur` tylko odkłada wartość w przeglądarce i **nie wysyła żądania**. Do autosave zawsze `wire:model.live.blur` (reguła w DECISIONS „Livewire conventions”, testy sprawdzają wyrenderowany atrybut). To była przyczyna „Saved” nie migało i wklejone linki się nie zapisywały.
- Wklejanie: pole traci fokus po `input` z `inputType === 'insertFromPaste'` (nie w `paste`, bo wtedy tekstu jeszcze nie ma).
- `ImportItemFactory` liczy `normalized_key` z końcowego artysty/tytułu (`ImportParserService`).
- Testy Livewire nie łapią problemów z przeglądarką (bindingi, paste), stąd sprawdzanie w headless Chrome.

**Decyzje** (szczegóły w DECISIONS.md, wpisy 2026-10-14 – 2026-10-17): duplikaty, wyszukiwanie bez klucza + link w wierszu zamiast modala, eksport (modal wersji, auto-pobieranie, plik bez końcowej nowej linii), Dashboard „Needs attention”, usuwanie.

**Stan danych lokalnych (2026-10-17):** 6 importów użytkownika, 1 eksport (`exports/1`). Testowe importy „ZZ …” usunięte.

**Incydent: klucz OpenAI w logu sesji**
- 2026-10-13 pełny `OPENAI_API_KEY` wyświetlił się w wyniku polecenia, bo maskowanie `sed` nie działa na macOS (BSD sed). Nie ma go w gicie ani w plikach projektu.
- Decyzja użytkownika: **bez rotacji**, bo klucz ma z góry limit $4. Ryzyko zaakceptowane.
- Opcjonalnie: wyczyścić klucz z transkryptów Claude Code w `~/.claude/projects/-Volumes-data-dev-VibeCode-tt-tracktracker/*.jsonl`.

**Lokalny `.env` (poza gitem):** `APP_ACCENT=navy`, `APP_TIMEZONE=Europe/Warsaw`, `OPENAI_API_KEY` ustawiony, `AI_PARSER_MODEL=gpt-4.1-mini`, `DB_QUEUE_RETRY_AFTER=300`. Po zmianach `.env` uruchom `sail artisan config:clear` i `sail artisan queue:restart`.

**Do zrobienia przez użytkownika:**
- Sprawdzić ceny `gpt-4.1-mini` na stronie OpenAI (wpisane z pamięci: $0.40 in / $0.10 cached / $1.60 out za 1M tokenów, `config/services.php` → `ai_pricing`).

**Następne kroki (po kolei):**
1. **Tryb YouTube API** (klucz już jest w `.env`, 2026-10-17): `YoutubeSearchService` (search.list, cache `youtube_search_cache`, fallback przy quota), `SearchYoutubeForImportItemJob` przez `Bus::batch()`, `YoutubeScoringService` (progi 80/60), wybór kandydata na YouTube Results, postęp `wire:poll`. 5 atomowych kroków: 1) `YoutubeSearchService` + cache, 2) scoring, 3) job + `Bus::batch()` + fallback, 4) UI kandydatów + postęp, 5) test na prawdziwym API i strojenie wag. Kroki 0–3 i 4a zrobione (klucz działa, `YoutubeSearchService` + cache, `YoutubeScoringService`, log zapytań `youtube_api_calls`, wyszukiwanie w kolejce `Bus::batch()`, postęp na żywo na YouTube Results, 2026-10-17). Decyzje: DECISIONS „YouTube API mode: behaviour” (API automatycznie z kluczem, postęp z itemów, bez miniatur, ręczne linki pomijane). **Teraz: test ręczny kroku 4a przez użytkownika** (Search YouTube → pasek postępu, wiersze same się uzupełniają, podsumowanie). **Potem krok 4b** (lista 3 kandydatów z punktami + wybór innego), krok 5 (strojenie wag), wyświetlanie zużycia limitu. Zapisane na później: lista linków z playlisty YouTube (TODO „After MVP”), websockety (ROADMAP).
2. **Biblioteka muzyki (Milestone 2, po MVP):** zamiast arkuszy (Numbers), z playlistami, import przez skan folderu z muzyką. Najpierw Q&A (model danych, montowanie folderu w Sail, czytnik tagów). Patrz DECISIONS 2026-10-17.
3. Drobne: `.vscode/extensions.json`.
4. Po MVP (TODO.md „After MVP”): ustawienia akcentu, przełącznik jasny/ciemny, ostrzeżenie o duplikatach między importami.
5. Opcjonalnie: „TXT out of date” także na YouTube Results (dziś tylko na Dashboardzie); lista eksportów sortuje po `created_at`, a Dashboard po `updated_at` (do ujednolicenia, jeśli przeszkadza).
