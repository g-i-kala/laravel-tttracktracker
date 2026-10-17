# Testing

## How to run tests

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --filter=ParserTest
```

Stack: PHPUnit 12, Mockery, Faker. Code style: `./vendor/bin/sail bin pint`.

## Test strategy

- **AI**: never call a real provider in tests. `phpunit.xml` sets `OPENAI_API_KEY` empty; AI tests set `config(['ai.providers.openai.key' => 'test-key'])` and use `TrackListParser::fake([...])->preventStrayPrompts()`.

- **Unit** — pure services (parser, normalizer, search URL, scoring, filename versioning).
- **Feature** — Livewire components via `Livewire::test()` (create import, review/edit, select link, custom link, toggle, export modals) + HTTP test for the download route.
- YouTube API mocked via `Http::fake()` + `Http::preventStrayRequests()`; no real API calls in tests. `phpunit.xml` sets `YOUTUBE_DATA_API_KEY` empty (no-key mode by default, even with a real key in `.env`); API tests set `config(['services.youtube.api_key' => …])`.

## Existing tests

- `tests/Feature/ImportModelsTest.php`: factories, relationships, enum casts + defaults, restrict-on-delete FK, singular `youtube_search_cache` table.
- `tests/Feature/DashboardTest.php`: `/` renders inside the layout (title, `data-accent`), nav shows "Coming soon" for missing routes, accent from config, modal bound via `wire:model`.
- `tests/Feature/Imports/CreateImportTest.php`: auto name, batch + items created (line numbers, default genre, missing-artist notice, status), optional fields empty, 0 / >200 tracks rejected, name/year/month validation, live detected counter.
- `tests/Feature/Imports/ParseReviewTest.php`: line order, autosave + key recompute, missing-artist notice set/cleared, blank genre → null, title required, remove item, items of another batch rejected (404).
- `tests/Feature/Settings/AiSettingsTest.php`: usage stats + recent calls, key encrypted/masked/overrides `.env`, remove falls back to `.env`, parse uses saved key + model and restores config, key test (`Http::fakeSequence`), model/budget validation, budget reached → line parser, 80% warning, retry blocked.
- `tests/Feature/Ai/AiUsageLoggingTest.php`: shipped price of the dotted model name `gpt-4.1-mini` is found (regression), success logs tokens/cached tokens/cost/batch/duration, failure logged with no cost, call outside a batch, model without price (cost null), cost formula. Token counts come from a fake `StructuredTextResponse` with `TextUsage`.
- `tests/Feature/Ai/AiTrackListParsingTest.php`: enabled only with a key, AI tracks → parsed lines, New Import queues the job (AI mode), input length limit, job stores items (genre fallback) / skips non-parsing batch / `failed()` marks batch failed, Review polling + retry + simple parser. Agent faked with `preventStrayPrompts()`.
- `tests/Unit/MusicalKeyServiceTest.php`: key → Camelot for major/minor, ♯/♭ and ASCII, short forms (`Am`, `Bbm`, `min`, `maj`), note only = major, Camelot input normalized, invalid input → null (data provider).
- `tests/Unit/ImportParserServiceTest.php`: separators, numbering/timestamp stripping, `50 Cent`/`808 State` kept, empty lines + original line numbers, missing artist, `normalized_key` rules (data providers).
- `tests/Feature/Youtube/YoutubeSearchServiceTest.php`: API call (key in `X-Goog-Api-Key` header, never in the URL; `type=video`, `maxResults=10`), mapping + HTML entity decoding, non-video items skipped, cache write (TTL from config), cache hit (case/whitespace-insensitive, no request, works without key), expired cache refreshed, `quotaExceeded` → quota exception + nothing cached, other API errors, connection failure, no key + no cache → exception without request; call log: success (100 units, results count, batch from `Context`), cache hit not logged, `quotaExceeded` 0 units, other error 100 units, connection failure 0 units, missing key not logged.

- `tests/Unit/YoutubeScoringServiceTest.php`: score per signal (official audio, Topic + "Provided to YouTube", re-upload, live, several keywords, keyword in the track title, VEVO, remix found / missing / other remix, original vs remix, extended mix on the label channel, one of several artists, feat., diacritics, artist only in the channel, no artist, partial title, unrelated, clamp to 0), `rank()` order, ties and limit (data provider).

- `tests/Feature/Youtube/YoutubeApiSearchTest.php`: with key one job per item without a link (`Bus::fake`, `allowFailures`), batch `searching`; without key no-key mode; nothing queued when all items have links; end-to-end on the sync queue with `Http::fake` by query: candidates ranked (top 3), ≥80 selected + included, 60–79 selected only, <60 no link, custom link untouched, batch `searched`, API calls logged with the batch; re-search replaces candidates; quota error → one request, notice on all items; other API error only for that item; missing-artist notice kept; exported batch back to `exported`; job skips non-pending items; `failed()` fallback; `searching` opens YouTube Results; progress UI: poll + "N of M" + "Searching…" only while searching, quota notice in the row, poll fills finished rows without touching a field being edited, summary counts when done + dismiss; candidates listed by rank with scores, choosing one sets link/title/channel/score + includes, candidate of another item → 404. `YoutubeSearchServiceTest` also covers the quota flag (blocks until midnight Pacific Time, not set by rate limits).

## Important test cases

- Import parser: `Artist - Title`, remix kept in title, extra spaces, en/em dashes, lines without separator, empty lines.
- Duplicate detection: exact match (case/whitespace-insensitive), similar match (other version, swapped artists, small typo in one part; typos in both parts or short words differing by half are not similar). Unit: `DuplicateDetectionServiceTest`, `ImportParserServiceTest` (similar key). Feature: `ParseReviewDuplicatesTest` (modal once after parse, banner, remove/keep all, similar marks, marks after edit).
- `ImportItemFactory` computes `normalized_key` from the final artist/title with `ImportParserService`, so overriding artist/title in a test gives a realistic key.
- YouTube URL normalizer: `watch?v=`, `youtu.be/`, `shorts/`, `embed/`, `live/`, m/music hosts, no scheme, extra query params; rejected: other sites, look-alike hosts, search/channel/playlist pages, bad IDs, `javascript:` (`YoutubeUrlNormalizerTest`). Feature (`YoutubeResultsTest`): paste → normalized + included, custom link clears API title/channel/score, invalid keeps the saved link, empty clears + excludes, toggle only with a link, foreign items 404.
- YouTube search URL generator: proper encoding, `(Original Mix)` removed, versions/remixes/feat. kept (`YoutubeSearchUrlServiceTest`). Feature: `YoutubeResultsTest` (Search YouTube stores URLs + statuses, blocked by invalid field, re-run keeps exported, results page links in new tab, numeric route, imports list Review / YouTube links, Review → YouTube links).
- Supersimple TXT export: only included items with URLs, line order, no trailing newline/empty lines/metadata (`SupersimpleTxtExportTest` with `Storage::fake('local')`: missing links modal + export anyway, version modal new/overwrite, statuses, disabled without links, download route headers + 404). Filenames: `ExportFilenameServiceTest`. Exports list: `ExportsIndexTest` (order, Latest per import, download/import links, empty state). Navigation: `DashboardTest` (all sidebar links; `<x-nav-link>` coming-soon state via `$this->blade()`). Deleting: `DeleteImportsTest` (service removes candidates/items/exports/files, keeps AI logs; modal summary; cancel; bulk; select all; empty selection), `DeleteExportsTest` (one version vs last export → back to searched; modal; bulk; select all). Dashboard: `DashboardTest` (empty state, to review, missing links, outdated export after a link/tick change, not outdated after title/BPM edits, 10 recent imports/exports + View all).
- Export filename versioning: base, `_v2`, `_v3`, overwrite latest.
- YouTube scoring: penalties (live set, podcast, lyrics, sped up, slowed, cover, reaction, tutorial), thresholds 80/60.

## Manual QA checklist

- [ ] Layout: sidebar on desktop, Menu toggle on mobile width; active link highlighted.
- [ ] Theme follows macOS light/dark; `APP_ACCENT=indigo` changes buttons/active link color.
- [ ] "How it works" modal: opens, closes with Esc, backdrop click, × and "Got it".

- [ ] With `OPENAI_API_KEY`: paste a Beatport list → "Parsing with AI…" → table with artist / title (Original Mix) / genre.
- [ ] AI failure (e.g. wrong key) → red panel; "Use simple parser" and "Try again with AI" work.
- [ ] Create import with auto-generated name (Warsaw time); edit name.
- [ ] Live counter updates while typing; lines without artist are flagged.
- [ ] Empty list / >200 tracks shows an error; Parse shows "Parsing…" and redirects with a flash message.
- [ ] Paste 10–30 tracks; parse.
- [ ] Parse review shown after Parse; edit artist/title/genre, "Saved" appears on blur; reload keeps the edit.
- [ ] Fill in a missing artist → amber notice disappears; empty title → error.
- [ ] Remove a row → confirm dialog → row gone.
- [ ] Exact duplicate modal appears; each option works.
- [ ] Similar duplicate warning appears, does not block.
- [ ] No-key mode: search URL opens in new tab.
- [ ] Paste a link in the row (all 3 formats) → saved, normalized, Include ticked; invalid link rejected; empty field removes the link.
- [ ] API mode: 3 candidates, best preselected, include rules by score.
- [ ] Toggle include in export.
- [ ] Export with missing links → warning modal.
- [ ] Re-export → version modal; `_v2` created; overwrite works.
- [ ] Download TXT; contains only URLs.
