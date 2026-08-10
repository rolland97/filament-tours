# Resume Handoff — `rolland97/filament-tours`

Rewritten 2026-08-10. The previous version said "parked after design, no package code written" —
that is now wrong by about 3,000 lines. **Read this first when picking the work up.**

## Status: **the package works. 58 of 86 tasks done, on branch `001-tours-v1`.**

A tour defined on a panel provider runs on the page it targets, once, degrades when the page
changes underneath it, and leaks nothing onto pages it does not target. Verified in a real browser
and in a separate application installed from the release archive.

⚠️ **12 commits exist only on this machine.** `001-tours-v1` has never been pushed. `git ls-remote`
shows only `main` and a dependabot branch. Losing this disk loses the work.

| | |
|---|---|
| Branch | `001-tours-v1`, 12 commits ahead of `main`, **unpushed** |
| Suite | 59 passed, 176 assertions |
| Static analysis | PHPStan level 4, clean |
| Formatting | Pint, clean |
| Built assets | `resources/dist/components/` — committed and current |
| Packagist | ⚠️ still not submitted, still no tag. Correct: v1 is not finished |

## ▶ Next action

```bash
cd ~/projects/filament-tours
git switch 001-tours-v1
composer install && npm install     # both are gitignored
./vendor/bin/pest --no-coverage     # expect 59 passed
```

Then in Claude Code, continue `/speckit-implement`. `specs/001-tours-v1/tasks.md` is the live
checklist — `[X]` means done and verified.

**Remaining work, in the order I would take it:**

1. **Phase 7 / US4** (T059–T071) — server-side state driver. The largest phase, and **the only one
   that adds a write endpoint.** `contracts/http.md` has the security posture: the route exists
   only when a host driver is configured, sits behind the panel's own auth middleware, and
   validates the tour id against the registry before the driver is touched. Follow it rather than
   improvising.
2. **Phase 6 / US3** (T051–T058) — replay via `StartTourAction` and the `filament-tours:start`
   event. Note T056 (single auto-start) is already satisfied by the `for … break` loop in
   `init()`; verify rather than rebuild.
3. **Phase 10** (T081–T086) — polish. **T082 is not cosmetic**: `README.md` is still skeleton
   boilerplate, including "This is where your description should go", a Filament **4.x** docs
   link, and a "publish and run the migrations" section for a migration this package deliberately
   does not have. It is the first thing a consumer reads.

## What is already true, and should not be re-litigated

All seven of the design's original success criteria (SC-1 … SC-7) are implemented and verified.
Two later additions, SC-008 (packaging) and SC-010 (escaping), are also verified. **SC-009**
(spike-first) was satisfied by construction.

The design's identified fragile step turned out to be less fragile than feared. §6 assumed the
page class had to be read off the current route's action, an internal detail. It does not:
Filament hands it to render hooks through `HasRenderHookScopes`, a published interface.
`tests/PageClassResolutionTest.php` is the tripwire and will fail loudly if an upgrade changes it.

## ⚠️ Traps found this cycle — do not re-introduce these

1. **Server-side escaping alone is not protection.** This was live, and the whole PHP suite
   reported green while it was broken. `JSON.parse` hands JavaScript the original characters
   straight back, and driver.js assigns popover copy with `innerHTML`, so a tour body of
   `<img src=x onerror=…>` **executed in Chromium**. `onPopoverRender` is not a fix either — it
   runs after the assignment. The client escapes before the string reaches the engine.
   **`quickstart.md` has a required XSS probe. Run it whenever the client code or driver.js
   version changes.** No PHP test can catch this class of bug.
2. **`config/tours.php` was never loaded.** The provider guards on
   `file_exists(config/{shortName}.php)`, and `shortName()` is `Str::after($name, 'laravel-')` —
   `filament-tours` has no such prefix, so it returns unchanged and the guard looked for
   `config/filament-tours.php`. The file is now named to match.
3. **`destroy()` is Alpine's lifecycle hook.** Defining a method by that name means the framework
   silently calls yours. The package's own teardown is `stopTour()`.
4. **Tearing a tour down fires driver's `onDestroyed`**, which is also how user completion is
   detected — so without the `suppressSeen` guard, navigating away silently marks a run-once tour
   seen and the user never sees it again.
5. **The archive shipped 109 files / 1.18MB.** Now 20 files / 80KB. Two skeleton defects caused
   it: `/.package-lock.json` had a leading dot and matched nothing, and `composer.lock` was never
   listed at all.
6. **`composer test` exits 1 on this machine** — no Xdebug or PCOV, and `phpunit.xml.dist`
   requests coverage. Not a failure. Use `./vendor/bin/pest --no-coverage` locally.
7. **`$this->table()` wraps cells to terminal width**, splitting a fully-qualified class name
   across lines. `tours:list` prints plain lines for exactly this reason.

## Verifying in a browser

`testbench.yaml` is **gitignored**, so it must be recreated — `quickstart.md` § Reproducing Gate 0
has the contents and the commands. Playwright against `testbench serve` covers everything the PHP
suite cannot: driver.js booting, styling, selector skipping, SPA teardown, and the XSS probe.

Design §8's "no JavaScript test suite in v1" still stands. Trap 1 above is what that costs.

## Toolchain notes

- **The superb bridge broke mid-session.** `~/.agents/skills/*` symlinks pointed at superpowers
  **6.1.1**, which was replaced on disk by **6.2.0**, leaving 14 dangling links. Every skill
  silently reported `available: false`. Repointed. Run `/speckit-superb-check` if bridge commands
  start misbehaving — a missing skill and a broken symlink look identical.
- `AGENTS.md` (27 rules) is a **mandatory** `before_plan` gate. `.specify/memory/constitution.md`
  is ratified at **v1.0.0**; five principles, mapped to the AGENTS.md rules that implement them.
- **A public API addition needs declaring.** `FilamentToursPlugin::registryKey()` extends the
  surface R-020 freezes; it is documented in `contracts/php-api.md` with the reasoning rather than
  left implicit. Worth a second opinion.

## Commits on this branch

`git log --oneline main..HEAD` is authoritative. Shape of it:

```text
8cd18ce  feat: fail loudly at registration and inventory what is registered (T072-T078)
5028daf  test: prove SC-004 by installing the archive into a separate app (T045)
68ebd18  feat: ship the runtime, keep the workshop (T079-T080)
299d570  feat: degrade a rotted tour instead of breaking it (T046-T050)
31fb198  feat: define, resolve and deliver tours end to end (T025-T044)
0ce67ee  feat: verify the spike in a real browser with Playwright (T016)
a232b43  feat: remove the skeleton artifacts the design forbids (T017-T024)
c5968b8  feat: prove the render hook and asset path in a Testbench panel (T005-T015)
3c13075  feat: set up toolchain and test panel harness (T001-T004)
b88b1a2  docs: fix two test-first violations the implementation gate caught
3dbc8ab  docs: plan 001-tours-v1 through spec, clarify, plan, tasks, analyze
c5aff09  docs: ratify constitution v1.0.0 and add AGENTS.md
```
