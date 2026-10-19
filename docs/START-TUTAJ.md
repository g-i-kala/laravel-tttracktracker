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

Stan na 2026-10-18:

- Laravel 13, PHP 8.4, Sail (`compose.yaml`): app, PostgreSQL 16 (`tttracktracker`, port 5433 na hoście), worker kolejki jako serwis `queue`. Aplikacja: http://localhost:8000.
- Frontend: Blade + **Livewire 4.4** (full-page, class-based, jeden komponent na ekran), Tailwind 4, własny `<x-modal>`. Sidebar, motyw jasny/ciemny wg systemu, akcent z `APP_ACCENT`.
- Model danych: tabele importu/YouTube/eksportu + `ai_usage_logs`, `youtube_api_calls`, `settings`; enumy statusów, modele, factories. `import_items` ma też `bpm`, `musical_key`, `camelot_key`, `label`.
- **Działa:** **Dashboard** (Needs attention, ostatnie importy i eksporty), **New Import** (`/imports/create`), **Parse Review** (`/imports/{batch}/review`: edycja z autosave, BPM/tonacja/label po kliknięciu w wiersz, usuwanie wierszy, **Save & close**), **lista importów** (`/imports`).
- **Parsowanie:** z kluczem AI (OpenAI, `gpt-4.1-mini`, Laravel AI SDK) dowolny format w tle, także BPM, tonacja (+ Camelot) i label; bez klucza parser linii `Artist - Title`.
- **Duplikaty** w obrębie importu: modal raz po parsowaniu + banner, oznaczenia podobnych (inna wersja, kolejność artystów, drobna literówka).
- **Import z playlisty YouTube** (zakładka „YouTube playlist” na New Import, wymaga klucza YouTube): każdy film = track z linkiem i zaznaczonym Export, bez wyszukiwania; Topic → artysta z kanału; AI (osobny agent) poprawia artystę/tytuł w tle. Do 200 filmów, pominięte usunięte/prywatne i powtórzone; YouTube Mix (`list=RD…`) tylko pierwsza strona (≤ 50).
- **YouTube bez klucza API (fallback):** Search YouTube na Parse Review → ekran **YouTube Results** (`/imports/{batch}`) z linkami wyszukiwania, polem na własny link (normalizacja, autosave) i checkboxem Export.
- **Eksport Supersimple TXT** na YouTube Results: modal brakujących linków, modal wersji (nowa / nadpisz), auto-pobieranie, „Download again”.
- **Lista eksportów** (`/exports`): wszystkie wersje, „Latest”, pobieranie.
- **Add tracks:** dopisywanie tracków do istniejącego importu (Parse Review i YouTube Results; duplikaty pomijane, na YouTube Results od razu wyszukiwane).
- **Usuwanie** importów (z trackami i plikami TXT) i pojedynczych eksportów: wiersz albo zaznaczenie wielu, modal z podsumowaniem.
- **Tryb YouTube API (działa, klucz ustawiony):** Search YouTube z kluczem szuka w tle (kolejka), wybiera najlepszy link wg punktów (≥80 + eksport, 60–79 tylko link). Pasek postępu i podsumowanie na YouTube Results działają; lista 3 kandydatów z punktami i wybór innego też. **Licznik limitu** (linia na Dashboard, Parse Review, YouTube Results + ekran **YouTube Settings** `/settings/youtube`). Strojenie wag (krok 5) odłożone.
- Zużycie AI zapisywane w `ai_usage_logs` (tokeny, szacowany koszt). Ekran **AI Settings** (`/settings/ai`): statystyki miesiąca, klucz API w bazie (szyfrowany, ma pierwszeństwo przed `.env`), model, miesięczny budżet (po przekroczeniu parser linii).
- **Export track list** (Parse Review, YouTube Results, lista importów): modal TXT (`Artist - Title`) albo CSV (wszystkie pola + link YouTube), wszystkie tracki, tylko pobranie.
- Testy: 367, wszystkie przechodzą.

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

### Sesja 2026-10-17 (wieczór): tryb YouTube API, Add tracks, plan biblioteki

**Zrobione (commity po kolei):**
1. `d4e59ae docs`: plan biblioteki muzyki (Milestone 2: biblioteka, playlisty, skan folderu z muzyką zamiast arkuszy Numbers; Beatport → M3, Rekordbox → M4), usunięty nieużywany `database/database.sqlite`.
2. `bdbcafb feat(youtube)`: `YoutubeSearchService` (search.list, 10 wyników, cache `youtube_search_cache` 30 dni, klucz w nagłówku `X-Goog-Api-Key`, nigdy w URL), `YoutubeSearchException`. `phpunit.xml` czyści `YOUTUBE_DATA_API_KEY`.
3. `1a6bccb feat(youtube)`: `YoutubeScoringService` (tytuł 40, artysta 30, kanał = artysta/label 15, oficjalne 5, wersja ±10/−25, słowa typu live/lyrics −30; progi 80/60).
4. `e2112c2 feat(youtube)`: log `youtube_api_calls` (100 jednostek za zapytanie, 0 przy quota/sieci, cache nie liczony) pod przyszły licznik limitu.
5. `b8b1c40 feat(youtube)`: Search YouTube z kluczem → `Bus::batch()` jobów `SearchYoutubeForImportItemJob` (tylko tracki bez linku), kandydaci top 3, auto-wybór wg progów, fallback na link wyszukiwania z komunikatem, flaga quota do północy czasu pacyficznego.
6. `828468d feat(youtube)`: postęp na żywo na YouTube Results (`wire:poll.2s`, pasek „N of M”, „Searching…” w wierszach, podsumowanie na końcu). Wybrana opcja A z trzech (B: blokujący modal, C: websockety/Reverb — na później).
7. `df0787c feat(youtube)`: lista 3 kandydatów z punktami i wybór innego (radio).
8. `76b0229 feat(imports)`: **Add tracks** na Parse Review i YouTube Results (wklejka jak w New Import, AI w żądaniu, duplikaty pomijane, na YouTube Results od razu wyszukiwanie). Trait `app/Livewire/Concerns/AddsTracks.php`.

**Testy ręczne użytkownika:** wyszukiwanie przez API, postęp, kandydaci i Add tracks działają (2026-10-17).

**Lekcje:**
- Postgres `jsonb` zmienia kolejność kluczy: w testach porównywać wyniki z cache przez `assertEquals`, nie `assertSame`.
- Kolumny `timestamp` mają sekundy: w testach `freezeSecond()` zamiast `freezeTime()`.
- Blade: `@endif` przyklejony do słowa (`AI@endif`) nie jest dyrektywą → błąd składni.
- `PHPUnit\Framework\TestCase::result()` jest finalne: nie nazywać tak helperów w testach.
- Testy UI z „Searching…”: zatrzymać worker (`sail stop queue`), uruchomić wyszukiwanie, potem `sail start queue`. Tracki z cache (Bicep – Glue, Midas Field – What We Got, Daft Punk – Around the World) nie zużywają limitu.

**Decyzje** (DECISIONS.md, wpisy 2026-10-17): biblioteka po MVP, tryb API (zachowanie + szczegóły), wagi scoringu, log limitu, postęp przez polling, Add tracks.

**Stan danych lokalnych (2026-10-17, koniec sesji, bez zmian w nocnej części):** 1 import, 1 eksport, 11 wpisów cache YouTube, w logu `youtube_api_calls` 1000 jednostek (limit dzienny 10 000, reset o 9:00 czasu PL). Testowe importy „ZZ …” usunięte.

### Sesja 2026-10-17 (noc): przegląd scoringu, licznik limitu YouTube

**Zrobione:**
1. Przegląd punktów na prawdziwym imporcie („YouTube Export 2026-10-17 21:39”, 8 tracków) i w cache. Użytkownik: „na dziś działa super”, strojenie później. Plan z decyzjami zapisany w [TODO.md](TODO.md) pod „Step 5”.
2. `4c49823 feat(youtube)`: **licznik limitu** `YoutubeQuotaService::today()` (jednostki od północy czasu pacyficznego, `YOUTUBE_DAILY_QUOTA` = 10 000), linia `<x-youtube-quota>` na Dashboard / Parse Review / YouTube Results (tylko z kluczem), ekran **YouTube Settings** `/settings/youtube` (tylko licznik + czy klucz ustawiony). Test ręczny użytkownika OK.

**Lekcja:** porównując `created_at` z momentem w innej strefie (np. północ PT), najpierw `setTimezone(config('app.timezone'))`: Eloquent zapisuje Carbon bez konwersji strefy.

**Na start następnej sesji:** patrz sesja 2026-10-18 niżej.

**Incydent: klucz OpenAI w logu sesji**
- 2026-10-13 pełny `OPENAI_API_KEY` wyświetlił się w wyniku polecenia, bo maskowanie `sed` nie działa na macOS (BSD sed). Nie ma go w gicie ani w plikach projektu.
- Decyzja użytkownika: **bez rotacji**, bo klucz ma z góry limit $4. Ryzyko zaakceptowane.
- Opcjonalnie: wyczyścić klucz z transkryptów Claude Code w `~/.claude/projects/-Volumes-data-dev-VibeCode-tt-tracktracker/*.jsonl`.

**Lokalny `.env` (poza gitem):** `APP_ACCENT=navy`, `APP_TIMEZONE=Europe/Warsaw`, `OPENAI_API_KEY` ustawiony, `AI_PARSER_MODEL=gpt-4.1-mini`, `DB_QUEUE_RETRY_AFTER=300`, `YOUTUBE_DATA_API_KEY` ustawiony (2026-10-17, działa). Po zmianach `.env` uruchom `sail artisan config:clear` i `sail artisan queue:restart`.

**Do zrobienia przez użytkownika:**
- **Test ręczny importu z playlisty** (patrz sesja 2026-10-18).
- ~~Sprawdzić ceny `gpt-4.1-mini`~~ potwierdzone 2026-10-18 ($0.40 in / $0.10 cached / $1.60 out za 1M tokenów, `config/services.php` → `ai_pricing`), zgodne z configiem.

### Sesja 2026-10-18: import z playlisty YouTube

**Zrobione (commity po kolei):**
1. `3e192a9 feat(youtube)`: `YoutubePlaylistService` (ID playlisty z linku, `playlistItems.list` po 50, limit, pominięte usunięte/prywatne, log 1 jednostka/strona, wspólna flaga limitu przez `YoutubeSearchService::markQuotaExceeded()`), `YoutubePlaylistException::userMessage()`.
2. `f5e345a feat(imports)`: `PlaylistTrackService` (Topic, czyszczenie „(Official Video)” itp., `Artist - Title`, link + Export), agent AI `PlaylistTitleParser` (numer → artysta/tytuł), job `ParsePlaylistTitlesWithAiJob` (poprawia na miejscu; przy błędzie zostaje prosty wynik), `ImportBatchService::createFromPlaylist()`, `source_type = youtube_playlist`.
3. `2b1e755 feat(imports)`: zakładki **Paste list / YouTube playlist** na New Import (`?source=playlist`), komunikaty (pominięte, ponad 200, błędy pod polem), komunikat AI na Parse Review. Sprawdzone w headless Chrome (bez prawdziwej playlisty z filmami).
4. `26c7442 fix(youtube)`: **YouTube Mix i powtórki.** Użytkownik wkleił link z miksem (`watch?v=…&list=RD…&start_radio=1`) i dostał 200 tracków z masą duplikatów. Miks nie ma końca, API w kółko zwraca te same ~50 filmów. Teraz każdy film trafia raz (komunikat „Skipped N repeated videos.”), a miks (`RD…`, oprócz `RDCLAK…`) pobiera się tylko z 1. strony (≤ 50) z komunikatem. Test ręczny użytkownika OK.
- Potwierdzone przez użytkownika: ceny `gpt-4.1-mini` zgodne z configiem; pełna wklejka z Beatportu działa (2026-10-17).

**Decyzje** (DECISIONS 2026-10-18): import z playlisty (zakładka, tylko z kluczem, public/unlisted, link z playlisty = wybrany, pierwsze 200 + komunikat); osobny agent AI w tle, bez AI tytuł bez myślnika → brak artysty; **Export track list** (TXT `Artist - Title` lub CSV ze wszystkimi polami, modal wyboru, na Parse Review u góry + YouTube Results + lista importów, tylko pobranie, bez historii).

**Cel użytkownika (ważny kontekst):** baza na **imprezę lat 80 (PL i zagraniczne)** z wielu playlist i źródeł, z której ma powstać jedna wspólna lista. Potrzebne: eksport listy bez linków, porównanie playlist/importów, usuwanie duplikatów między nimi, dodanie do biblioteki. Zapisane w TODO (Library).

**Lekcja:** `Queue::fake()` przechwytuje też `dispatchSync()`: w testach jobów nie fake'ować kolejki.

**Stan danych lokalnych (2026-10-18):** 4 importy, 2 eksporty (dane użytkownika), limit YouTube dziś 1 jednostka.

**Następne kroki (po kolei):**
0. **Test ręczny użytkownika: import playlisty z lat 80** (z AI): import z miksu działa (2026-10-18). Do sprawdzenia przy okazji: artyści/tytuły (też polskie) i Generate TXT. Po teście ewentualne poprawki czyszczenia tytułów/instrukcji agenta.
1. ~~**Export track list**~~ zrobione 2026-10-18 (`191b862`): modal TXT/CSV na Parse Review, YouTube Results i liście importów, wszystkie tracki, CSV z przecinkiem w UTF-8 z BOM. Test ręczny użytkownika OK („CSV eleganckie”).
2. ~~Rozmowa o bibliotece~~ zrobiona 2026-10-19 (DECISIONS „Music library (Milestone 2): model and scope”, kroki w TODO „Library”). Krok 1a zrobiony 2026-10-19 (`tracks`, `LibraryService`, POF / PON / Wanted zamiast statusu posiadania). Krok 1b zrobiony 2026-10-19 (Add to library na Parse Review i YouTube Results, Mark as wanted, Same track / New track, znaczek In library); czeka na test ręczny użytkownika. **Następny: krok 1c** (ekran biblioteki). Wszystkie decyzje 1c ustalone 2026-10-19 (DECISIONS „Library screen (step 1c)”): rozwijany wiersz z autosave, filtry, 50 na stronę, sortowanie artysta A–Z + klikalne kolumny, usuwanie pojedyncze i zaznaczonych, przy kolizji edycji modal merge z porównaniem różnic. Podkroki 1c-i … 1c-iv w TODO. **1c-i zrobione 2026-10-19** (ekran `/library`: lista, filtry, sortowanie); POF na liście (przycisk w wierszu + Mark as POF, test użytkownika OK); **1c-ii zrobione** (rozwijany wiersz: edycja z autosave, POF / Wanted, link YouTube, źródła; kolizja tytułu = błąd do czasu 1c-iv). **1c-iii zrobione** (usuwanie: z wiersza i zaznaczonych, modal). **1c-iv zrobione** (modal merge z porównaniem różnic). Krok 1c zamknięty. **1d zrobione** (ocena 1–5 gwiazdkami, tagi: panel / zaznaczone / filtr „all of”). Krok 1 (biblioteka) gotowy; następny krok 2 (playlisty), najpierw Q&A (m.in. eksport playlisty, otwarte w TODO 2b). Pierwotny opis: Użytkownik chce zacząć ogólnie od biblioteki, a dopiero potem ustalić, jak w niej zrobić porównanie i duplikaty między źródłami. Ważne rozróżnienie od użytkownika: **import to nie playlista**. Import to sposób dodania tracków, a playlisty (np. „impreza lat 80”) mają być osobnym bytem w bibliotece. Do przemyślenia razem: unikalne tracki w bibliotece vs itemy importów, playlisty (ręczne, z importu, z playlisty YouTube), duplikaty między źródłami, skan folderu z muzyką, co zastępuje arkusze w Numbers. Zacząć od pytań, opcje z rekomendacją.

**Wcześniejsza lista kroków (2026-10-17):**
1. **Krok 5 trybu API (odłożony, użytkownik: „na dziś działa super”):** przegląd prawdziwego importu zrobiony 2026-10-17, plan poprawek z decyzjami użytkownika w [TODO.md](TODO.md) pod „Step 5” (artyści z opisu kanałów Topic, „NOT official”, lista znaczników mashup/medley/festiwal −30, wersja przed oficjalnym kanałem, bez powtórek kandydatów). Wagi to stałe w `YoutubeScoringService`.
2. ~~Licznik limitu YouTube~~ zrobiony 2026-10-17 (decyzje w DECISIONS „YouTube quota counter”). Test ręczny użytkownika OK (2026-10-17).
3. ~~Lista linków z playlisty YouTube~~ zrobione 2026-10-18 (import z playlisty).
4. **Biblioteka muzyki (Milestone 2, po MVP):** zamiast arkuszy (Numbers), z playlistami, import przez skan folderu z muzyką. Najpierw Q&A (model danych, montowanie folderu w Sail, czytnik tagów). Patrz DECISIONS 2026-10-17.
5. Drobne: `.vscode/extensions.json`; po MVP (TODO „After MVP”): ustawienia akcentu, przełącznik jasny/ciemny, ostrzeżenie o duplikatach między importami; opcjonalnie „TXT out of date” na YouTube Results, ujednolicenie sortowania eksportów.
