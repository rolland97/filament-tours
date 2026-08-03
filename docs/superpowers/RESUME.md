# Resume Handoff — `rolland97/filament-tours`

Written 2026-08-03. **Read this first when picking the work up**, on this machine or another.

## Status: **PARKED after design. No package code written yet.**

The design is approved and committed. The repo is a configured Filament plugin skeleton
with spec-kit installed. Nothing in `src/` implements the design yet — the files there are
the skeleton's own stubs.

⚠️ **There is no git remote.** Three commits exist locally on `main` and nowhere else.
`gh repo create rolland97/filament-tours` has **not** been run, and nothing is on GitHub or
Packagist. Until that happens this work exists only in this folder — back it up or push it
before wiping the machine.

## What this package is

Guided product tours for Filament v5 panels, wrapping [driver.js](https://driverjs.com/).
First consumer will be the **procurement** app's backlog **#18**, which gets its own
spec-kit cycle in that repo — this package is deliberately app-agnostic.

**Read the design before writing anything**:
`docs/superpowers/specs/2026-08-03-filament-tours-design.md` — 10 sections, including the
seven decisions with the alternatives each beat, and two costs stated plainly (localStorage
replays a tour on a second device; no JS test suite in v1).

## ▶ Next action

The brainstorming flow stopped at its terminal step, which is to turn the design into a
plan. Nothing else should be done first.

```bash
cd ~/projects/filament-tours
git log --oneline -3        # 85e5afe docs: design spec  ← the approved design
```

Then, in Claude Code:

1. **`/superpowers:writing-plans`** pointed at
   `docs/superpowers/specs/2026-08-03-filament-tours-design.md`, writing the plan to
   `docs/superpowers/plans/2026-08-03-filament-tours.md`.
2. Execute it test-first. The design's §8 lists the intended test surface; §10 has seven
   success criteria (SC-1 … SC-7) to hold the plan to.

⚠️ **Owner input still owed on one thing**: whether to create the GitHub repo now (so CI
and Packagist exist from the first release) or keep it local until v1 is implemented.

## Baseline on this machine, measured 2026-08-03

| Check | Command | Result |
|---|---|---|
| Tests | `php vendor/bin/pest` | ✅ **2 passed** (4 assertions) — the skeleton's own `DebugTest` + `ExampleTest` |
| Static analysis | `php vendor/bin/phpstan analyse` | ✅ `[OK] No errors` — **after** the fix below |
| Composer deps | installed | ✅ `vendor/` present (`configure.php` ran `composer install`) |

⚠️ **`composer analyse` was broken on a fresh clone and is now fixed.** The skeleton's
`phpstan.neon.dist` sets `checkOctaneCompatibility` and `checkModelProperties`, both
**removed in larastan v3** (this package installs v3.10), and phpstan rejects unknown
parameters outright:

```text
Invalid configuration: Unexpected item 'parameters › checkOctaneCompatibility'.
```

Both lines are gone, with a comment saying why. If you re-pull the skeleton or diff against
upstream, do not "restore" them.

## Traps already paid for — do not re-pay

1. ⚠️ **`.gitignore` had `docs` and swallowed the spec silently.** Git **cannot re-include a
   file whose parent directory is excluded**, so `!docs/superpowers/` under a `docs` rule is
   dead — the first commit that claimed to add the design spec actually contained only the
   ignore change, and `git ls-files docs/` was empty. Now `docs/*` plus
   `!docs/superpowers/`, which works because the *contents* are excluded rather than the
   directory. **Check `git ls-files` after committing anything under `docs/`.**
   (The sibling package hit the same rule and worked around it with force-add on a branch;
   this repo fixes the pattern instead.)
2. **`specify init` uses `--integration claude`, not `--ai claude`** in 0.12.4. Procurement's
   `.specify/init-options.json` records an `ai` key, which does not exist as a CLI flag here.
3. **`configure.php` is interactive and deletes itself.** It was driven with piped answers;
   Ray and Rector were declined to keep dev dependencies near the sibling package. If you
   want either, add it by hand — the script is gone.
4. **Host PHP on this box has no `gd`.** Irrelevant to this package today (no image work),
   but remember it if a test ever fabricates an image; procurement hits 7 errors from this.

## Toolchain installed here

- **spec-kit 0.12.4** with the **claude** integration: `.specify/`, plus 20 skills in
  `.claude/skills/`.
- **Extensions**: `superb` + `memorylint`, registered in `.specify/extensions.yml` with the
  same hook chain procurement uses — `memorylint load-agents` mandatory before plan,
  `superb implementation-gate` mandatory before implement, `critique` after.
  The catalog (`.specify/extension-catalogs.yml`) was copied from procurement, because
  `specify extension add <name>` resolves names through it.
- The superb bridge depends on globally installed superpowers skills at `~/.agents/skills/`;
  verify with `/speckit-superb-check` if bridge commands start failing.

## Commits so far

```text
85e5afe docs: design spec for filament-tours
019f6f4 chore: install spec-kit 0.12.4 + superb and memorylint extensions
3f65d1d chore: scaffold from filamentphp/plugin-skeleton
```
