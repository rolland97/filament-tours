# Resume Handoff — `rolland97/filament-tours`

Written 2026-08-03, refreshed the same day after the repo went public and CI went green.
**Read this first when picking the work up**, on this machine or another.

## Status: **PARKED after design. No package code written yet.**

The design is approved and committed. The repo is a configured Filament plugin skeleton with
spec-kit installed and **green CI on GitHub**. Nothing in `src/` implements the design yet —
those files are the skeleton's own stubs.

- **Remote**: https://github.com/rolland97/filament-tours — public, `main`, pushed.
- **CI**: `tests`, `phpstan` and `zizmor` all ✅ green on `main`.
- **Packagist**: ⚠️ **not submitted, and deliberately so.** No tag exists.
  `composer require rolland97/filament-tours` will **not** resolve. The package currently does
  nothing, and a published version is effectively permanent — tag and submit when v1 implements
  the design, so the README describes something true.

## What this package is

Guided product tours for Filament v5 panels, wrapping [driver.js](https://driverjs.com/).
First consumer will be the **procurement** app's backlog **#18**, which gets its own spec-kit
cycle in that repo — this package is deliberately app-agnostic.

**Read the design before writing anything**:
`docs/superpowers/specs/2026-08-03-filament-tours-design.md` — 10 sections, including seven
decisions with the alternatives each beat, and two costs stated plainly (localStorage replays a
tour on a second device; no JS test suite in v1).

## ▶ Next action

The brainstorming flow stopped at its terminal step: turn the design into a plan. Do this before
anything else.

```bash
cd ~/projects/filament-tours
git pull
```

Then, in Claude Code:

1. **`/superpowers:writing-plans`** against
   `docs/superpowers/specs/2026-08-03-filament-tours-design.md`, writing to
   `docs/superpowers/plans/2026-08-03-filament-tours.md`.
2. Execute test-first. Design §8 lists the intended test surface; §10 has seven success criteria
   (SC-1 … SC-7) to hold the plan to.

## Baseline on this machine, measured 2026-08-03 (post-fix)

| Check | Command | Result |
|---|---|---|
| Tests | `php vendor/bin/pest --ci --no-coverage` | ✅ **2 passed** (4 assertions) — the skeleton's `DebugTest` + `ExampleTest` |
| Static analysis | `php vendor/bin/phpstan --error-format=github` | ✅ exit 0, no errors |
| CI on `main` | GitHub Actions | ✅ tests + phpstan + zizmor green |

Both commands are the **exact** ones CI runs, which is the point — the last round of red was
caused by local and CI commands differing.

## ⚠️ Four defects the skeleton shipped, all fixed — do not "restore" them

Every one of these was reproduced locally before being fixed. If you re-pull the skeleton or
diff against upstream, these differences are deliberate.

1. **`phpstan.neon.dist` used larastan v2 parameters.** `checkOctaneCompatibility` and
   `checkModelProperties` were removed in v3 (v3.10 installs here), and phpstan errors on
   unknown parameters, so `composer analyse` failed on a fresh clone:
   `Unexpected item 'parameters › checkOctaneCompatibility'`. Both lines removed.
2. **The PHP floor was wrong.** `composer.json` said `php: ^8.2` while
   `pestphp/pest-plugin-livewire` v4 requires `^8.3`, so every 8.2 matrix job died at *Install
   dependencies*. Floor is now `^8.3` (matching the sibling package) and 8.2 is out of both
   matrices.
3. **`pest --ci` implies coverage, but `setup-php` sets `coverage: none`.** The step printed
   *"No code coverage driver available"* and exited 1 **having run zero tests** — a red CI that
   said nothing about the tests. The command is now `pest --ci --no-coverage`. Enabling coverage
   is a later decision; with two stub tests the number is meaningless.
4. ⚠️ **An empty `exclude:` key breaks the workflow while remaining valid YAML.** Removing the
   8.2 exclude entry left a bare `exclude:`; `yaml.safe_load` parses it as null, and **GitHub
   Actions rejects the workflow outright**. *The tell: a run whose name is the workflow FILE
   path instead of the job name, failing instantly with no steps.* This one was self-inflicted
   while fixing #2.

## Other traps already paid for

1. ⚠️ **`.gitignore` had `docs` and silently swallowed the design spec.** Git **cannot
   re-include a file whose parent directory is excluded**, so `!docs/superpowers/` under a
   `docs` rule is dead — the first commit claiming to add the spec contained only the ignore
   change, and `git ls-files docs/` was empty. Now `docs/*` plus `!docs/superpowers/`, which
   works because the *contents* are excluded rather than the directory.
   **Check `git ls-files` after committing anything under `docs/`.**
2. **`specify init` uses `--integration claude`, not `--ai claude`** in 0.12.4. Procurement's
   `.specify/init-options.json` records an `ai` key that is not a CLI flag here.
3. **`configure.php` is interactive and deletes itself.** It was driven with piped answers; Ray
   and Rector were declined to keep dev dependencies near the sibling package. Add either by
   hand if wanted — the script is gone.
4. **Host PHP on this box has no `gd`.** Irrelevant here today, but remember it if a test ever
   fabricates an image; procurement hits 7 errors from exactly this.

## Toolchain installed here

- **spec-kit 0.12.4**, `claude` integration: `.specify/` plus 20 skills in `.claude/skills/`.
- **Extensions** `superb` + `memorylint` in `.specify/extensions.yml`, same hook chain as
  procurement: `memorylint load-agents` mandatory before plan, `superb implementation-gate`
  mandatory before implement, `critique` after. The catalog
  (`.specify/extension-catalogs.yml`) was copied from procurement, because
  `specify extension add <name>` resolves names through it.
- The superb bridge depends on globally installed superpowers skills at `~/.agents/skills/`;
  verify with `/speckit-superb-check` if bridge commands start failing.

## Commits so far

```text
84ab419  ci: fix three defects the skeleton shipped
9de4e81  docs: park after design — resume handoff + larastan v3 phpstan fix
85e5afe  docs: design spec for filament-tours
019f6f4  chore: install spec-kit 0.12.4 + superb and memorylint extensions
3f65d1d  chore: scaffold from filamentphp/plugin-skeleton
```

Plus the empty-`exclude` fix and this refresh. `git log --oneline` is authoritative.
