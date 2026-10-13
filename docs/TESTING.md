# Testing

## How to run tests

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --filter=ParserTest
```

Stack: PHPUnit 12, Mockery, Faker. Code style: `./vendor/bin/sail bin pint`.

## Test strategy

- **Unit** — pure services (parser, normalizer, search URL, scoring, filename versioning).
- **Feature** — Livewire components via `Livewire::test()` (create import, review/edit, select link, custom link, toggle, export modals) + HTTP test for the download route.
- YouTube API mocked via `Http::fake()`; no real API calls in tests.

## Existing tests

- `tests/Feature/ImportModelsTest.php`: factories, relationships, enum casts + defaults, restrict-on-delete FK, singular `youtube_search_cache` table.

## Important test cases

- Import parser: `Artist - Title`, remix kept in title, extra spaces, en/em dashes, lines without separator, empty lines.
- Duplicate detection: exact match (case/whitespace-insensitive), similar match.
- YouTube URL normalizer: `watch?v=`, `youtu.be/`, `shorts/`, extra query params, non-YouTube rejected.
- YouTube search URL generator: proper encoding.
- Supersimple TXT export: only included items with URLs; no empty lines/metadata.
- Export filename versioning: base, `_v2`, `_v3`, overwrite latest.
- YouTube scoring: penalties (live set, podcast, lyrics, sped up, slowed, cover, reaction, tutorial), thresholds 80/60.

## Manual QA checklist

- [ ] Create import with auto-generated name; edit name.
- [ ] Paste 10–30 tracks; parse.
- [ ] Parse review shown; edit artist/title/genre.
- [ ] Exact duplicate modal appears; each option works.
- [ ] Similar duplicate warning appears, does not block.
- [ ] No-key mode: search URL opens in new tab.
- [ ] Add custom link (all 3 formats); invalid link rejected.
- [ ] API mode: 3 candidates, best preselected, include rules by score.
- [ ] Toggle include in export.
- [ ] Export with missing links → warning modal.
- [ ] Re-export → version modal; `_v2` created; overwrite works.
- [ ] Download TXT; contains only URLs.
