# Implementation Plan: Guided Product Tours for Filament Panels (v1)

**Branch**: `001-tours-v1` | **Date**: 2026-08-10 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-tours-v1/spec.md`

**Design of record**: [`docs/superpowers/specs/2026-08-03-filament-tours-design.md`](../../docs/superpowers/specs/2026-08-03-filament-tours-design.md) — approved 2026-08-03, adopted not re-derived (CD-1, AGENTS.md **R-025**).

## Summary

Ship a Filament v5 plugin that declares guided tours centrally per panel, resolves which apply to
the current page **server-side**, and delivers them through a `BODY_END` render hook plus a bundled
driver.js asset — with no npm, no migration, and no translation files reaching the consumer.

The technical approach is settled by the design (D1–D7). Phase 0 research changed exactly one
mechanism: **page-class resolution reads Filament's render-hook scopes rather than the current
route's action**, which is a published interface rather than the internal detail the design worried
about. Behaviour is unchanged; the design's mandated tripwire test is kept in full.

Work is ordered **spike first** (CD-3 / SC-009): the render hook and asset path are proven end to
end in a Testbench panel before any value object or registry exists, because everything else rests
on assumptions only the spike can confirm.

## Technical Context

**Language/Version**: PHP 8.3+ (`composer.json` → `require`). ES2020 for the client bundle
(`bin/build.js` → `target`).

**Primary Dependencies**: `filament/filament ^5.0`, `spatie/laravel-package-tools ^1.15`. Client:
driver.js — **not yet installed**, see [research R4](./research.md). Build: esbuild ^0.28.

**Storage**: **None.** The package ships no migration (design §1, R-017). Persistence of "seen" is
the host's, behind a two-method `TourState` contract; the default holds no server state at all.

**Testing**: Pest 3/4 on Orchestra Testbench 9/10/11, with `pest-plugin-arch`, `-laravel`,
`-livewire`. Run via `composer test`. **No JS test suite in v1** — a named, accepted gap (design §8,
R-009), mitigated by the first consumer's browser journey.

**Target Platform**: Laravel 11/12/13 applications running Filament v5 panels. CI covers PHP
8.3/8.4 × Laravel 11/12/13 × `prefer-lowest`/`prefer-stable` × Ubuntu **and Windows** (R-003).

**Project Type**: Composer library — a Filament plugin package. Not an application.

**Performance Goals**: None meaningful, and none invented. Resolution is a filter over a handful of
in-memory value objects per request. If a panel ever registers enough tours for resolution to show
up in a profile, that is a design conversation, not a tuning one.

**Constraints**:

- Consumers touch **no npm, no bundler, no panel theme** (SC-004, D7).
- The public API is **frozen** at the v1 surface (R-020, design §4).
- No migration, no lang files (R-017, R-018).
- Tour copy is **escaped**, no raw-markup opt-out (FR-028, SC-010).
- The seen route exists **only** under a server-side driver (FR-020/021).

**Scale/Scope**: ~10 new source files, 4 removed skeleton files, one client entry point of roughly
sixty lines. Single package, single panel plugin, no parallel feature slices (CD-2 — **no
`RESERVED.md`**).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Checked against `.specify/memory/constitution.md` **v1.0.0** (ratified 2026-08-03, amended
2026-08-10). `AGENTS.md` loaded and passed as the mandatory `before_plan` gate
(sha256 `7e3799…d43e3`, 27 rules) and is the operational expression of these principles.

> **History**: this section was originally an explicitly-labelled `AGENTS.md` substitute, because
> the constitution was still an unfilled template when this plan was written. It now checks
> against the real thing.

| Gate | Constitution | Pre-Phase 0 | Post-Phase 1 |
|---|---|---|---|
| Test-first, failing test before code | I | ✅ | ✅ Every gate in [quickstart](./quickstart.md) is a test before it is code; tasks.md marks tests required, not optional |
| Untestable surface **named**, not hidden | I | ✅ | ✅ The JS gap is named in spec Out of Scope, plan, `contracts/js-events.md`, and tasks T040 |
| No migration ships | II | ✅ | ✅ Data model has no tables; research R8 schedules the stub's removal (T015–T018) |
| No lang files ship | II | ✅ | ✅ T019 removes `resources/lang` and `hasTranslations()` |
| Auth inherited, never reimplemented | II | ✅ | ✅ [`contracts/http.md`](./contracts/http.md) — the route sits behind the panel's own middleware |
| Public surface enumerable and frozen | III | ✅ | ✅ [`contracts/php-api.md`](./contracts/php-api.md) is exactly the design's §4 surface, nothing added |
| No helpers encoding Filament's DOM | III | ✅ | ✅ Raw selectors only; no field or component helpers |
| Copy escaped, no opt-out | IV | ✅ | ✅ Escaped server-side, rendered as text client-side — two independent guards |
| Degrade rather than break | IV | ✅ | ✅ Missing target skipped (FR-014); seen-POST fails **open** per research R6 |
| Errors name the offender; no overclaimed validation | IV | ✅ | ✅ FR-017 names the tour; `tours:list` explicitly does not imply selector validation |
| Consumers touch no npm, bundler, or theme | V | ✅ | ✅ Built asset committed and registered; `filament:assets` is Filament's own step (research R3) |
| Tooling excluded from the archive **pre-tag** | V | ✅ | ✅ T074/T075, flagged in tasks.md as release blockers |
| Full CI matrix supported | Platform | ✅ | ⚠️ Windows path handling in the asset path is untested until the spike runs |
| PHPStan level held, no new baseline entries | Platform | ✅ | ✅ No new baseline entries planned |
| Fragile seams proven before dependent code | Workflow | ✅ | ✅ Phase 2 spike blocks everything below it (CD-3, SC-009) |

**Violations requiring justification**: none.

Two items recorded rather than assumed:

- The one deviation from the design — reading page class from render-hook scopes instead of the
  route action (research R2) — is a *how*, not a *what*. No principle is touched.
- The Windows row is ⚠️ not ❌: the constraint is satisfied by CI, but this plan cannot claim it
  verified until the spike runs. Principle IV forbids reporting unverified checks as passing.

## Project Structure

### Documentation (this feature)

```text
specs/001-tours-v1/
├── plan.md              # This file
├── spec.md              # Adopted spec + 4 clarifications
├── research.md          # Phase 0 — verified against vendor/, not from memory
├── data-model.md        # Phase 1 — value objects, registry, payload, transitions
├── quickstart.md        # Phase 1 — 7 validation gates, spike first
├── contracts/           # Phase 1
│   ├── php-api.md       #   frozen public surface
│   ├── payload.md       #   server → browser shape + escaping
│   ├── http.md          #   the single route, security posture
│   └── js-events.md     #   browser events, startup sequence
├── checklists/
│   └── requirements.md  # 16/16, re-validated after clarification
└── tasks.md             # /speckit-tasks — NOT created by /speckit-plan
```

**No `RESERVED.md`** — single-feature repository, no parallel slices (CD-2).

### Source Code (repository root)

```text
src/
├── FilamentToursPlugin.php          # MODIFY  per-panel wiring: ->tours(), render hook, seen route
├── FilamentToursServiceProvider.php # MODIFY  asset registration; strip migrations + translations
├── Tour.php                         # NEW     value object
├── Step.php                         # NEW     value object
├── TourRegistry.php                 # NEW     resolution
├── Contracts/
│   └── TourState.php                # NEW     hasSeen / markSeen
├── State/
│   └── LocalStorageState.php        # NEW     the no-server-state default
├── Actions/
│   └── StartTourAction.php          # NEW     replay from a page header
├── Http/Controllers/
│   └── MarkTourSeenController.php   # NEW     the one route, server driver only
├── Commands/
│   └── ListToursCommand.php         # NEW     tours:list  (replaces FilamentToursCommand)
├── FilamentTours.php                # DELETE  facade over an empty class
├── Facades/FilamentTours.php        # DELETE  ditto (+ composer.json extra.laravel.aliases)
└── Commands/FilamentToursCommand.php# DELETE  placeholder

resources/
├── js/index.js                      # MODIFY  currently EMPTY — Alpine component + driver.js
├── views/tours.blade.php            # NEW     render-hook output
├── dist/                            # BUILT + COMMITTED (path settled by the spike, research R3)
└── lang/                            # DELETE  design D6 / R-018

database/
├── migrations/create_tours_table.php.stub  # DELETE  design §1 / R-017
└── factories/ModelFactory.php              # DELETE  no model exists

config/tours.php                     # MODIFY  currently empty — add 'state' => 'local'
bin/build.js                         # MODIFY  outfile must match the registered asset path
package.json                         # MODIFY  add driver.js
.gitattributes                       # MODIFY  export-ignore (CD-4) — pre-tag

tests/
├── TestCase.php                     # MODIFY  drop defineDatabaseMigrations + factory guessing
├── Panel/                           # NEW     minimal Testbench panel + pages for the spike
├── RenderHookTest.php               # NEW     the SC-009 spike
├── PageClassResolutionTest.php      # NEW     the §6 tripwire — scopes carry the page class
├── AssetRegistrationTest.php        # NEW     JS + CSS assets resolve to files on disk
├── TourTest.php / StepTest.php      # NEW     validation + fluent defaults
├── TourRegistryTest.php             # NEW     class match, predicate, non-match, driver filtering
├── PayloadTest.php                  # NEW     shape, ordering, escaping
├── StartTourActionTest.php          # NEW     replay action dispatches the start event
├── SeenRouteTest.php                # NEW     present under driver, ABSENT under 'local'
├── StateDriverTest.php              # NEW     config resolution; invalid value fails loudly
├── ListToursCommandTest.php         # NEW
├── ArchTest.php                     # NEW     conventions + the FR-003 guard (R-008)
├── ExampleTest.php                  # DELETE  skeleton stub
└── DebugTest.php                    # DELETE  skeleton stub
```

**Two assets, not one.** The tour engine ships a stylesheet; an unstyled tour is not a working
tour. `bin/build.js` must emit `resources/dist/filament-tours.css` alongside the component, and
`getAssets()` must register both. The blade markup must not depend on the host's Tailwind build —
utility classes there would force consumers to edit their panel theme, contradicting SC-004. See
[`contracts/payload.md`](./contracts/payload.md) § Styling.

**Structure Decision**: Single Composer package, flat `src/` with shallow namespaces
(`Contracts/`, `State/`, `Actions/`, `Http/Controllers/`, `Commands/`) matching the existing PSR-4
root `Rolland\FilamentTours\`. No `models/`–`services/` layering: the design's four units
(value objects, registry, plugin, state) are the architecture, and inventing a second one on top
would add indirection without adding a seam.

Deletions are load-bearing. Research R8 lists 10 skeleton artifacts that contradict adopted
decisions or are simply broken — including a `getMigrations()` entry naming a file that does not
exist. **R-024**: their presence is not precedent.

## Complexity Tracking

> No Constitution Check violations to justify. Table intentionally empty.

The one item worth naming is **not** a violation but a known unknown: `resources/dist` is a
committed build artifact, which means a stale commit can ship a stale bundle. Mitigated by the
`npm run build` step in the quickstart's final check. The alternative — building on the consumer's
machine — is exactly what D7 rejects.

## Phase status

| Phase | Status | Output |
|---|---|---|
| 0 — Research | ✅ Complete | [research.md](./research.md) — 8 findings, verified against `vendor/` |
| 1 — Design & Contracts | ✅ Complete | [data-model.md](./data-model.md), [contracts/](./contracts/) ×4, [quickstart.md](./quickstart.md) |
| 2 — Tasks | ⬜ Not started | `/speckit-tasks` — must order spike first (CD-3) |

**Unresolved NEEDS CLARIFICATION**: none. The one item deferred from `/speckit-clarify` — seen-POST
failure behaviour — is decided in [research R6](./research.md) and specified in
[`contracts/http.md`](./contracts/http.md).
