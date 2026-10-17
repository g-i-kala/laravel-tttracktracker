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
- YouTube API mocked via `Http::fake()`; no real API calls in tests.

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

## Important test cases

- Import parser: `Artist - Title`, remix kept in title, extra spaces, en/em dashes, lines without separator, empty lines.
- Duplicate detection: exact match (case/whitespace-insensitive), similar match (other version, swapped artists, small typo in one part; typos in both parts or short words differing by half are not similar). Unit: `DuplicateDetectionServiceTest`, `ImportParserServiceTest` (similar key). Feature: `ParseReviewDuplicatesTest` (modal once after parse, banner, remove/keep all, similar marks, marks after edit).
- `ImportItemFactory` computes `normalized_key` from the final artist/title with `ImportParserService`, so overriding artist/title in a test gives a realistic key.
- YouTube URL normalizer: `watch?v=`, `youtu.be/`, `shorts/`, `embed/`, `live/`, m/music hosts, no scheme, extra query params; rejected: other sites, look-alike hosts, search/channel/playlist pages, bad IDs, `javascript:` (`YoutubeUrlNormalizerTest`). Feature (`YoutubeResultsTest`): paste → normalized + included, custom link clears API title/channel/score, invalid keeps the saved link, empty clears + excludes, toggle only with a link, foreign items 404.
- YouTube search URL generator: proper encoding, `(Original Mix)` removed, versions/remixes/feat. kept (`YoutubeSearchUrlServiceTest`). Feature: `YoutubeResultsTest` (Search YouTube stores URLs + statuses, blocked by invalid field, re-run keeps exported, results page links in new tab, numeric route, imports list Review / YouTube links, Review → YouTube links).
- Supersimple TXT export: only included items with URLs, line order, no trailing newline/empty lines/metadata (`SupersimpleTxtExportTest` with `Storage::fake('local')`: missing links modal + export anyway, version modal new/overwrite, statuses, disabled without links, download route headers + 404). Filenames: `ExportFilenameServiceTest`. Exports list: `ExportsIndexTest` (order, Latest per import, download/import links, empty state). Navigation: `DashboardTest` (all sidebar links; `<x-nav-link>` coming-soon state via `$this->blade()`). Dashboard: `DashboardTest` (empty state, to review, missing links, outdated export after a link/tick change, not outdated after title/BPM edits, 10 recent imports/exports + View all).
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
