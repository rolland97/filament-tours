# Resume Handoff — `rolland97/filament-tours`

Rewritten 2026-08-11. **Read this first when picking the work up.**

## Status: **v1 is functionally complete. 86 of 86 tasks done, on `001-tours-v1`.**

A tour defined on a panel provider runs on the page it targets, once, degrades when the page
changes underneath it, replays on demand, moves its persistence server-side on one config value,
and leaks nothing onto pages it does not target.

| | |
|---|---|
| Branch | `001-tours-v1`, 20 commits ahead of `main`, **pushed and in sync** |
| CI | ✅ green — 24/24 test legs, phpstan, code style |
| Suite | 84 passed, 258 assertions |
| Requirements | 28 FR + 10 SC, **all covered and verified** |
| Packagist | ⚠️ **not submitted, no tag exists.** Deliberate — see below |

## ▶ Next action: three decisions, none of them mine

The work is done. What remains is release judgement.

1. **Review the PR** — open against `main`.
2. **Merge**, when you are satisfied.
3. **Tag and submit to Packagist** — ⚠️ **irreversible.** A published version cannot be retracted.
   Nothing forces this to happen with the merge, and there is no harm in letting the package sit on
   `main` unpublished until a real consumer needs it.

The first consumer is intended to be the **procurement** app's backlog **#18**, which gets its own
spec-kit cycle in that repository.

```bash
cd ~/projects/filament-tours
git switch 001-tours-v1
composer install && npm install     # both gitignored
./vendor/bin/pest --no-coverage     # expect 84 passed
```

## What v1 does, in one place

- Tours are declared **centrally on the panel**, not on the pages they describe, so copy stays
  reviewable and a tour can attach to a page you do not own.
- Applicability is decided **server-side**. A tour that does not apply is never serialised, so its
  text is not in the HTML of a page the user is on.
- A step whose selector misses is **skipped**, the rest still run, and the skip is reported to the
  console under debug. A tour with no surviving steps does not start.
- `->once()` controls **persistence, not triggering**. Without it a tour runs every visit.
- Several tours can apply; **exactly one auto-starts**, first in registration order. The rest are
  replayable via `StartTourAction` or the `filament-tours:start` event.
- Persistence is `localStorage` by default, or a host `TourState` driver — at which point exactly
  one route is registered, inside the panel's own middleware group.

## ⚠️ Traps this cycle paid for — do not re-introduce

1. **Server-side escaping alone protects nothing.** This was live, and the whole PHP suite reported
   green while it was broken. `JSON.parse` hands JavaScript the original characters back, and
   driver.js assigns popover copy with `innerHTML`, so `<img src=x onerror=…>` **executed in
   Chromium**. `onPopoverRender` is not a fix — it runs after the assignment. The client escapes
   before the string reaches the engine. **`quickstart.md` carries a required XSS probe: run it
   whenever the client code or the driver.js version changes.** No PHP test can catch this.
2. **A green local suite is not evidence unless local and CI agree.** `APP_ENV=local` in my shell
   made Filament permit a user model that does not implement `FilamentUser`; CI had no `APP_ENV`,
   got `production`, and 22 tests returned 403. `phpunit.xml.dist` now pins `APP_ENV=testing`.
3. **The test panel must declare what a real panel declares.** Three separate bugs came from it not
   doing so — no auth middleware (endpoint appeared unauthenticated), no session middleware (the
   seen-write returned 204 and recorded nothing), no `FilamentUser` (above).
4. **A test that passes proves nothing until you watch it fail.** The FR-003 arch guard was written
   as `->not->toUse('Filament')`, which does not match on namespace prefix — it passed with the
   forbidden helper planted in `Step`. It is now an allowlist. Same for `DistributionTest`, which
   first read `HEAD` instead of the working tree.
5. **Windows is a real CI leg.** `cd X && … | tar` is not valid under `cmd.exe`. Shell out with
   `git -C` and output files, never chained commands or pipes.
6. **`config/tours.php` was never loaded** — `shortName()` does not strip a `filament-` prefix, so
   the provider looked for `config/filament-tours.php`. Renamed.
7. **Rebuilding is not publishing.** `npm run build` then `testbench filament:assets`, or the
   served app runs a stale bundle.
8. **`composer test` exits 1 locally** — no Xdebug or PCOV, and coverage is requested. Not a
   failure. Use `./vendor/bin/pest --no-coverage`.

## Verifying in a browser

`testbench.yaml` is **gitignored** and must be recreated — `quickstart.md` § Reproducing Gate 0 has
the contents and commands. Playwright against `testbench serve` covers everything the PHP suite
cannot: driver.js booting, styling, selector skipping, SPA teardown, replay, the server-driver
round trip, and the XSS probe.

Design §8's "no JavaScript test suite in v1" still stands. Trap 1 is what that costs.

## Decisions recorded rather than hidden

- **`FilamentToursPlugin::registryKey()`** extends the v1 surface `AGENTS.md` **R-020** freezes.
  Documented in `contracts/php-api.md` with the reasoning — `tours:list` needs to reach a panel's
  registry from the console.
- **`env('FILAMENT_TOURS_STATE')` in the shipped config** was not a design decision. It arrived for
  browser-testing convenience and `/speckit-analyze` caught that it had widened the configuration
  contract silently. Kept and documented, because env-configurable state is reasonable to want.
- **The seen route inherits the panel's auth and only that.** Filament applies none on its own. A
  panel declaring no `authMiddleware` leaves the endpoint open, along with all its own pages. The
  security review flagged it; it is stated in `contracts/http.md` and in the README.

## Toolchain notes

- `AGENTS.md` (27 rules) is a **mandatory** `before_plan` gate. `.specify/memory/constitution.md`
  is ratified at **v1.0.0** — five principles, mapped to the rules that implement them.
- **The superb bridge can break silently.** `~/.agents/skills/*` symlinks pointed at superpowers
  6.1.1 after 6.2.0 replaced it, and every skill reported `available: false` — indistinguishable
  from never installed. Run `/speckit-superb-check` if bridge commands misbehave.

## Commits

`git log --oneline main..HEAD` is authoritative — 20 commits, from the constitution through to the
final analysis.
