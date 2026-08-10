# Quickstart: validating `001-tours-v1`

**Feature**: `001-tours-v1` · **Date**: 2026-08-10

How to prove the feature works, in the order the proofs become possible. This is a run-and-verify
guide, not an implementation guide — code belongs in `tasks.md` and the implementation phase.

---

## Prerequisites

```bash
composer install
npm install          # node_modules/ does not exist yet — see research R4
```

Local toolchain: PHP 8.3+ and Node. The CI matrix is wider (PHP 8.3/8.4 × Laravel 11/12/13 ×
`prefer-lowest`/`prefer-stable` × Ubuntu **and Windows**, AGENTS.md **R-003**) — passing locally is
necessary, not sufficient.

---

## The commands

| Command | What it proves |
|---|---|
| `composer test` | Pest suite. Random order, fails on warnings, risky tests, and stray output (R-007). |
| `composer analyse` | PHPStan level 4 over `src`, `config`, `database`. Do not baseline new errors (R-010). |
| `composer test:lint` | Pint check. Do not hand-format — CI reformats and commits back (R-011). |
| `npm run build` | esbuild → `resources/dist`. Output is **committed**. |
| `npm run dev` | Same, in watch mode. |
| `php artisan tours:list` | Registered tours: id, page or `when()`, step count, `once` (SC-006). |

---

## Gate 0 — the spike, before anything else

**This gate is SC-009 and CD-3.** Nothing below it may be built until it passes. It exists because
page-class resolution and the asset path are the two seams that depend on Filament internals — if
either behaves differently than [research](./research.md) predicts, value objects and a registry
built on top would be rework.

Prove, in a Testbench panel, with a hardcoded tour and no value objects and no registry:

1. A `BODY_END` render hook registered by the plugin **fires** on a panel page, and the emitted
   markup reaches the response.
2. The hook callback receives `array $scopes` containing the **current page's class**
   (research R2 — this is the design's §6 tripwire, in its verified form).
3. `FilamentAsset::register()` resolves **both** assets to files that exist on disk after
   `npm run build` — the `AlpineComponent` **and** the tour engine's stylesheet as a `Css` asset.
   The skeleton's build output path and its commented registration path disagree today (research
   R3), and the stylesheet was missing from the plan entirely until `/speckit-analyze` found it.
4. driver.js bundles through `bin/build.js` and boots in a browser against real Filament markup,
   **styled** — an unstyled tour is not a working tour (SC-004).

**If step 2 fails**, stop and re-plan. It is the assumption everything else rests on.

**Expected outcome**: a committed `resources/dist/…` artifact and a passing Testbench test that
asserts the hook output is present on a matching page.

### Reproducing Gate 0 in a browser

Step 4 needs a running app, not a test harness. `testbench.yaml` is **gitignored**, so recreate it:

```yaml
providers:
  - Rolland\FilamentTours\FilamentToursServiceProvider
  - Rolland\FilamentTours\Tests\Panel\TestPanelProvider
env:
  - APP_KEY="base64:<any 32-byte base64 key>"
  - APP_DEBUG=true
  - DB_CONNECTION=sqlite
  - DB_DATABASE=":memory:"
```

```bash
npm run build
./vendor/bin/testbench filament:assets     # publishes BOTH the component and the stylesheet
./vendor/bin/testbench serve --host=127.0.0.1 --port=8123
```

Then open `/testing/page-a` and `/testing/page-b`.

| Page | Expected |
|---|---|
| `page-a` | `.driver-popover`, `.driver-overlay` and `.driver-active-element` present; popover computed style shows `position: fixed`, `z-index: 1000000000` — i.e. `driver.css` loaded |
| `page-b` | none of the above, and the HTML contains no trace of the tour |

#### Required: the escaping probe

**Run this whenever the client code or the tour engine version changes.** The PHP suite cannot
catch this class of bug, and it was live once (see [research R9](./research.md)).

```js
// In the browser console on page-a:
window.__xssFired = false
const cmp = Alpine.$data(document.querySelector('[data-filament-tours]'))
cmp.tours = [{ id: 'probe', once: false, steps: [{
  selector: '[data-tour="thing"]',
  body: '<img src=x onerror="window.__xssFired = true">',
}]}]
cmp.stopTour(); cmp.start(cmp.tours[0])   // stopTour(), not destroy(): destroy() is Alpine's hook
// then, after the popover appears:
window.__xssFired                                             // must be false
document.querySelector('.driver-popover-description').textContent  // must show the tag literally
```

`window.__xssFired === true` means a stored-XSS hole is open in every consuming panel. Stop and fix
before anything else.

⚠️ **This is a one-off check, not automated.** It was driven by Playwright rather than by hand,
which is worth knowing — the plan had assumed this step needed a human at a screen and it did
not. But it does **not** run in CI, so design §8's named gap (no JavaScript test suite) still
stands unchanged. Re-run this by hand whenever the client code changes materially.

---

## Gate 1 — the MVP (User Story 1, P1)

Proves SC-001, SC-002, SC-004.

```php
// In a test panel provider
FilamentToursPlugin::make()->tours([
    Tour::make('demo')
        ->for(SomeTestPage::class)
        ->once()
        ->steps([Step::make('[data-tour="thing"]')->title('Thing')->body('This is the thing.')]),
])
```

| Check | Expected |
|---|---|
| Visit `SomeTestPage` | Payload present in the response; the tour runs (SC-001) |
| Visit a **different** page | Response contains **no** trace of the tour — not its id, not its copy, not its selectors (SC-002) |
| Visit `SomeTestPage` again, seen recorded | Tour does not start (SC-001) |
| Consumer install path | A working, **styled** tour with **no npm, no bundler, no theme change** by the consumer — `php artisan filament:assets` is Filament's own step, not this package's (SC-004, research R3). Verified against a scratch app, not asserted from the package's own suite |
| Repeat visit, tour **without** run-once | Tour runs **again** — the flag is persistence, not a trigger (FR-026) |

---

### Proving SC-004 from a real install

Serving this repo's own harness does **not** prove SC-004 — it proves the working tree works. The
claim is about what a consumer receives, so test the archive:

```bash
# 1. Extract exactly what a release ships
git archive --format=tar HEAD | tar -x -C /tmp/pkg

# 2. Require it from a separate project. symlink:false copies, so the app
#    genuinely cannot reach the working tree.
#    composer.json → repositories: [{ "type": "path", "url": "/tmp/pkg",
#                                     "options": { "symlink": false } }]
composer require rolland97/filament-tours:@dev

# 3. Register a panel with one tour, then Filament's own asset step
php artisan filament:assets
```

| Check | Expected |
|---|---|
| Tour on the targeted page | Runs, correct element highlighted |
| Popover computed style | `position: fixed`, `z-index: 1000000000` — the shipped stylesheet published too |
| `node_modules/` in the consumer | **Absent.** No npm, no bundler config, no theme `@source` line |

Verified 2026-08-10: 20 files installed, tour ran styled, no npm anywhere in the consumer.

## Gate 2 — degradation (User Story 2, P2)

Proves SC-003.

Define a three-step tour whose **middle** selector is absent.

Serve the panel (see § Reproducing Gate 0) and use the seeded fixtures:

| URL | Check | Expected |
|---|---|---|
| `/testing/page-b?partial=1` | Tour runs | Steps "First" and "Third" shown, the missing middle step skipped, nothing errors for the user |
| `/testing/page-b?partial=1` | `app.debug` on | **Exactly one** console warning, naming the tour **and** the unmatched selector. Two warnings for one missing selector means the resolve path is being walked twice |
| `/testing/page-b?missing=1` | **No** selector resolves | No popover, no overlay, no `driver` class on `<body>` |
| `/testing/page-a` | Navigate away mid-tour | Dispatch `livewire:navigating`: overlay destroyed **and** localStorage still empty — leaving is not finishing (FR-012) |

The last row is the subtle one. Tearing the tour down fires driver's `onDestroyed`, which is also
how a user finishing it is detected — so without a guard, navigating away silently marks a
run-once tour seen and the user never sees it again.

---

## Gate 3 — replay and multiple tours (User Story 3, P3)

Proves FR-024, FR-025.

| Check | Expected |
|---|---|
| Two tours match one page | Both in the payload; **only the first** auto-starts |
| Trigger the second by id | It runs, without a page reload (FR-025) |
| Replay a seen tour | It runs; seen is not re-marked |
| Dispatch an unknown tour id | Nothing starts, no user-facing error |

---

## Gate 4 — state drivers (User Story 4, P3)

Proves SC-005. The tour definitions must be **byte-identical** across both runs — that is the point.

| Config | Expected |
|---|---|
| `'state' => 'local'` (default) | No server state. **No route registered** — assert against the route list. `seenEndpoint` is `null` in the payload |
| `'state' => SomeHostDriver::class` | Seen tours filtered out **before render**, so their copy never reaches the browser. Exactly one route, behind panel auth. Finish or dismiss records through the driver |
| POST fails (offline/500) | Tour suppressed for this page session only, no retry, warning under debug; runs again next load ([research R6](./research.md)) |

---

## Gate 5 — diagnostics and guardrails (User Story 5, P4)

Proves SC-006, SC-007.

| Check | Expected |
|---|---|
| `php artisan tours:list` | Every registered tour, with page or `when()`, step count, `once` |
| Duplicate tour id | `InvalidArgumentException` **naming the offending tour** |
| Tour with zero steps | Same |
| `->for()` on a class that does not exist | Same |

The listing must not imply selector validation. A selector cannot be checked without a browser.

---

## Gate 6 — packaging (User Story 6, P4)

Proves SC-008. **Before the first release tag** (CD-4).

```bash
git archive --format=tar HEAD | tar -t | sort
```

| Expected absent | Expected present |
|---|---|
| `.specify/`, `.claude/`, `AGENTS.md`, `tests/`, `docs/`, `specs/` | `src/`, `config/`, `resources/views/`, `resources/dist/` |

Measured on this branch: 70 tracked files under `.specify` + `.claude`, roughly half a megabyte,
currently bound for every consumer's `vendor/`. Also unlisted today: `/bin`, `/resources/js`,
`/resources/css`. `resources/dist` **must stay shipped** — it is the built asset consumers need.

---

## Full check before calling it done

```bash
composer test && composer analyse && composer test:lint && npm run build
```

Then confirm `resources/dist` changes are committed. Report honestly: if something failed, say so
with the output (**R-027**).
