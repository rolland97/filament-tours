# AGENTS.md

Core rules for agents working in `rolland97/filament-tours`.

Every rule below is derived from something already in this repository — a config file, a
workflow, the commit history, or the approved design document — and cites its source. Nothing
here is invented preference. If a rule and its source ever disagree, the source wins and this
file is the bug.

**Scope**: this file governs planning and implementation in this repository. It is loaded as a
mandatory gate before `/speckit-plan` (`.specify/extensions.yml` → `hooks.before_plan`).

---

## Language, framework, and tooling

- **R-001** — Target **PHP 8.3+** and **Filament v5**. Do not use syntax or APIs unavailable in
  PHP 8.3, and do not reach for Filament v4 idioms. *Source: `composer.json` → `require`.*
- **R-002** — All source lives under the `Rolland\FilamentTours\` namespace in `src/`, PSR-4.
  Factories, if any, under `Rolland\FilamentTours\Database\Factories\`. Tests under
  `Rolland\FilamentTours\Tests\`. *Source: `composer.json` → `autoload` / `autoload-dev`.*
- **R-003** — Support the **full CI matrix**, not just the local version: PHP 8.3 and 8.4 ×
  Laravel 11, 12, and 13 × both `prefer-lowest` and `prefer-stable`, on Ubuntu **and Windows**.
  A change that only works on the newest combination is a broken change. Watch for
  Windows-hostile assumptions in particular — hardcoded `/` separators, `realpath()` casing.
  *Source: `.github/workflows/tests.yml`, `.github/workflows/phpstan.yml`.*
- **R-004** — Front-end assets are built with **esbuild via `bin/build.js`** (`npm run build`),
  output committed to `resources/dist/`. There is no other bundler and no vite config. Consuming
  applications must never need npm. *Source: `package.json`, design D7.*

## Testing

- **R-005** — Tests are **Pest** (v3 or v4) on **Orchestra Testbench**, run with `composer test`.
  `tests/Pest.php` applies `TestCase` to the whole directory, so new test files need no `uses()`
  line. *Source: `composer.json` → `scripts.test`, `tests/Pest.php`.*
- **R-006** — Write the test first. A behaviour change lands with the test that would have caught
  it. *Source: `.specify/extensions.yml` → `hooks.before_implement` declares test-first a required
  discipline; `.specify/extensions/superb/superb-config.template.yml` → `disciplines.required`.*
- **R-007** — The suite runs in **random order** and fails on warnings, risky tests, empty suites,
  and any stray output. Tests must not depend on execution order or print anything.
  *Source: `phpunit.xml.dist` → `executionOrder="random"`, `failOnWarning`, `failOnRisky`,
  `failOnEmptyTestSuite`, `beStrictAboutOutputDuringTests`.*
- **R-008** — Keep the **arch tests** passing (`pestphp/pest-plugin-arch` is installed). Treat an
  arch failure as a real failure, not a formality. *Source: `composer.json` → `require-dev`.*
- **R-009** — There is **no JavaScript test suite**, deliberately. Do not add vitest or a DOM
  harness for the current ~60 lines of client code; the first consumer's browser journey is the
  proof. If the client code grows past driving the tour engine, propose adding a JS toolchain
  explicitly rather than stretching this exemption. *Source: design §8 (named gap),
  `specs/001-tours-v1/spec.md` → Out of Scope.*

## Static analysis and code style

- **R-010** — **PHPStan level 4** over `src`, `config`, `database`, via `composer analyse`. Do not
  lower the level. Do not add to `phpstan-baseline.neon` to silence a new error — baselines are
  for inherited debt, and new code should not create any. *Source: `phpstan.neon.dist`.*
- **R-011** — Formatting is **Pint** (`laravel` preset plus the repo's five explicit rules), run
  with `composer lint` / checked with `composer test:lint`. Do not hand-format against it: a push
  touching any `.php` file triggers a workflow that reformats and commits back, so manual styling
  is churn. *Source: `pint.json`, `.github/workflows/fix-code-style.yml`.*
- **R-012** — When a config or workaround is non-obvious, **leave a comment saying why**, not
  what. This repo already does it in `phpstan.neon.dist` (why `checkOctaneCompatibility` is gone
  in larastan v3) and `.gitignore` (why `docs/*` and not `docs`). Match that density — comment the
  surprising, not the routine. *Source: `phpstan.neon.dist`, `.gitignore`.*

## Git, CI, and releases

- **R-013** — Commit messages follow **Conventional Commits** with a lowercase, descriptive
  subject: `fix:`, `docs:`, `ci:`, `chore:`, `chore(deps):`. Say what changed and why it mattered,
  not just which files moved. *Source: `git log`.*
- **R-014** — Pin every GitHub Action to a **full commit SHA** with the human version in a
  trailing comment (`uses: actions/checkout@3d3c42e… # v7.0.1`). Give every workflow an explicit
  least-privilege `permissions:` block. `zizmor` audits this and will fail the build.
  *Source: all files in `.github/workflows/`, `.github/workflows/zizmor.yml`.*
- **R-015** — `CHANGELOG.md` is maintained **automatically on release**. Do not hand-edit it.
  *Source: `.github/workflows/update-changelog.yml`.*
- **R-016** — Do not commit, push, tag, or release unless asked. Branch before committing if on
  `main`.

## Package boundary — what this package must not become

These come from the approved design (`docs/superpowers/specs/2026-08-03-filament-tours-design.md`)
and are the constraints most likely to be eroded by a well-meaning change.

- **R-017** — **Ship no migration.** Persistence of "has this user seen this tour" is the host
  application's decision, and the package holds no table. *Source: design §1, D2.*
- **R-018** — **Ship no translation files.** Copy is accepted as plain strings; the host calls
  `__()` if it wants to. The package never owns anyone's wording. *Source: design D6.*
- **R-019** — **Never encode Filament's DOM.** Step targets are raw CSS selectors. Do not add
  helpers like `Step::forField()` that depend on Filament's internal markup — they read better and
  break on point releases. *Source: design D5.*
- **R-020** — **Do not grow the public API.** v1's surface is frozen at `Tour::make/for/when/once/
  steps` and `Step::make/title/body/side/align`. Additions need a design amendment, not a commit.
  *Source: design §4 ("Nothing else is public in v1").*
- **R-021** — **A missing step target is skipped, never fatal.** Users must not see a broken tour;
  rot is surfaced to developers via a console warning under `app.debug` and `tours:list`.
  *Source: design D4, §7.*
- **R-022** — **Escape all tour copy.** Headings and bodies render as text; markup appears
  literally. Hosts interpolate values into copy, and the underlying tour engine would otherwise
  interpret them — unescaped rendering would be stored XSS inside an authenticated admin panel.
  No raw-markup opt-out in v1. *Source: `specs/001-tours-v1/spec.md` → FR-028, SC-010.*
- **R-023** — The **seen-recording route exists only when a server-side state driver is
  configured**, and only behind the panel's own auth middleware. Under the default browser-local
  driver the package registers no route and holds no server state. *Source: design §5.*

## Skeleton residue

- **R-024** — This repository was scaffolded from `filamentphp/plugin-skeleton`, and the skeleton
  left artifacts that the design forbids — a migration stub, a lang file, placeholder commands and
  tests. **Their presence is not precedent.** When work touches them, remove them rather than
  building on them, and check the removal against R-017 and R-018 rather than assuming the
  skeleton was right. *Source: `git log` → `3f65d1d chore: scaffold from filamentphp/plugin-skeleton`;
  contradictions listed in the plan handoff.*

## Working agreements

- **R-025** — **Adopt the design; do not re-derive it.** The design document is approved. Decisions
  D1–D7, the non-goals in §9, and the success criteria in §10 are settled. Reopening them requires
  saying so explicitly, not quietly specifying something different.
  *Source: `specs/001-tours-v1/spec.md` → CD-1, Adoption notice.*
- **R-026** — **Prove the fragile path first.** Page-class resolution and the render-hook plus
  asset-registration path are the identified tripwires: they depend on Filament internals that an
  upgrade could move. Demonstrate them end to end in a Testbench panel before building value
  objects or the registry on top. *Source: design §6, `specs/001-tours-v1/spec.md` → CD-3, SC-009.*
- **R-027** — **Report honestly.** If tests fail, say so with the output. If a step was skipped,
  say that. Do not describe work as verified that was not run.
