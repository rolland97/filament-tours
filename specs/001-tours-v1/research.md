# Phase 0 Research: Guided Product Tours for Filament Panels (v1)

**Feature**: `001-tours-v1` · **Date**: 2026-08-10 · **Spec**: [spec.md](./spec.md)

Every finding below was verified against the **installed** `vendor/filament` tree in this
repository, not from memory or documentation. File and line references are to that tree and are
reproducible with `grep`. Where something could not be verified offline, it says so.

---

## R1 — The render hook exists and is stable

**Decision**: Inject through `\Filament\View\PanelsRenderHook::BODY_END`.

**Verified**:

- `vendor/filament/filament/src/View/PanelsRenderHook.php:23` — `const BODY_END = 'panels::body.end';`
- `vendor/filament/filament/resources/views/components/layout/base.blade.php:165` — the panel layout
  renders it: `FilamentView::renderHook(PanelsRenderHook::BODY_END, scopes: $renderHookScopes)`.

**Rationale**: End-of-body places the payload after the panel markup exists, which the tour engine
needs. It is a published constant on a published class, not a string we guess.

**Alternatives considered**: `HEAD_END` (fires before the DOM the tour targets exists);
a Blade `@push` (requires the host's layout to cooperate; a render hook does not).

---

## R2 — Page-class resolution does **not** need the route action *(supersedes the design's stated mechanism)*

**Decision**: Read the page class from the **render-hook scopes** Filament passes into the hook
callback. Do not read `Route::current()->getAction()`.

**Verified**:

- `vendor/filament/filament/src/Pages/BasePage.php:207-210`
  ```php
  public function getRenderHookScopes(): array
  {
      return [static::class];
  }
  ```
- `vendor/filament/schemas/src/Contracts/HasRenderHookScopes.php` — this is a **published
  interface**, not an internal detail.
- `vendor/filament/filament/resources/views/components/layout/base.blade.php:6` —
  `$renderHookScopes = $livewire?->getRenderHookScopes();`
- `vendor/filament/support/src/View/ViewManager.php:89` —
  `app()->call($hook, ['data' => $data, 'scopes' => $scopes]);`
  The hook callback may declare an `array $scopes` parameter and receive `[TheCurrentPageClass]`.

**Why this matters**: design §6 flags page-class resolution as *"the fragile step"* precisely
because it assumed the class must be read off the current route's action — *"an internal detail
that a Filament upgrade could move."* Filament v5 hands us the page class directly, through an
interface it publishes for that purpose. The mechanism changes; **the risk assessment does not**:
this is still the seam most likely to move on upgrade, so the design's mandated tripwire test
(§6) is kept in full and simply asserts against scopes instead of the route action.

**This is a deviation from the design's §6 mechanism.** It is a *how*, not a *what* — no decision
D1–D7 is reopened, and observable behaviour is identical. Recorded here rather than assumed.

**Alternatives considered**:

| Approach | Rejected because |
|---|---|
| Read the class off `Route::current()->getAction()` | The design's own stated fragility. Works, but reaches for an internal where a published one exists. |
| Register one scoped hook per tour, via `Panel::renderHook(..., scopes: [PageClass])` | Verified possible (`vendor/filament/filament/src/Panel/Concerns/HasRenderHooks.php:18-33`), but cannot express predicate-only tours, which have no page class to scope by. Would need a second unscoped hook anyway, so it doubles the mechanism for no gain. |

**Residual risk**: `base.blade.php:6` calls `$livewire?->getRenderHookScopes()` — null-safe on the
component, not on the method. A panel page that is not a `BasePage` descendant is the untested
case. The resolver must treat an empty or absent scope list as "no page-class match" and fall
through to predicates rather than erroring.

---

## R3 — Asset delivery, and the mismatch the skeleton left

**Decision**: Register one `AlpineComponent` from `resources/dist/components/filament-tours.js`,
and **fix `bin/build.js` to emit there**.

**Verified**:

- `vendor/filament/support/src/Assets/AlpineComponent.php:12-17` — the *public* path is derived as
  `js/{package}/components/{id}.js`; the constructor path is the **source** file on disk.
- `vendor/filament/support/src/Assets/AssetManager.php:62` — `register(array $assets, string $package = 'app')`.
- `src/FilamentToursServiceProvider.php:95-99` — all three asset registrations are **commented out**,
  and the commented `AlpineComponent` line points at `resources/dist/components/filament-tours.js`.
- `bin/build.js:48` — `outfile: './resources/dist/filament-tours.js'` — **no `components/` segment**.

So the skeleton's commented example and its build script disagree about where the built file lives.
Uncommenting the registration as-shipped would register a path that does not exist. Either the
build's `outfile` moves into `components/`, or the registration path drops the segment; they must
be decided together. **Chosen**: move the build output, so the on-disk layout matches Filament's
own `components/` convention for Alpine components.

**Consumer impact on SC-004**: Filament copies registered assets into the application's public
directory via `php artisan filament:assets`, which the standard Filament install wires into
`post-autoload-dump`. The consumer runs **no npm and no bundler** — SC-004 holds. The quickstart
states the `filament:assets` step explicitly rather than assuming it.

---

## R4 — driver.js is not installed yet

**Decision**: Add `driver.js` as an npm **devDependency** and bundle it into `resources/dist`.
Pin an exact version; commit the built output.

**Verified**: `package.json` devDependencies are `esbuild ^0.28.0` and `prettier ^3.5.3` only.
`node_modules/` does not exist and `resources/js/index.js` is **empty** (0 bytes).

**Not verifiable offline**: the current driver.js version, its ESM entry shape, and its bundled
size. `bin/build.js` sets `platform: 'neutral'`, `mainFields: ['module', 'main']`, `target: es2020`
— an ESM-first library should bundle cleanly, but this is exactly what the spike must prove rather
than assume.

**Rationale for devDependency, not dependency**: consumers install via Composer and receive the
**built** artifact. Nothing in `package.json` reaches them, so a runtime dependency there would be
fiction. This is design D7 restated.

---

## R5 — Multiple tours, and which one starts

**Decision**: The server sends every applicable tour in registration order; the client auto-starts
the first *eligible* one and holds the rest for replay.

**Source**: spec FR-024…FR-027, from the clarification session. Eligibility = survived resolution,
has at least one resolvable step, and — if run-once — not seen.

**Note on ordering**: eligibility depends on **step resolution**, which is only knowable in the
browser. So the server cannot pre-compute "the one that starts"; it sends the ordered list and the
client picks. This is why FR-011 requires the payload to preserve registration order.

---

## R6 — Seen-recording failure behaviour *(deferred from `/speckit-clarify`, decided here)*

**Decision**: If the POST to the seen route fails — offline, 500, network error — the client
suppresses the tour for the **rest of the page session only** (in-memory), does not retry, and
logs a console warning under `app.debug`. On the next page load, the server's answer is
authoritative: if it still reports the tour unseen, the tour runs again.

**Rationale**: The two failure directions are not symmetric. Failing *closed* (treat as seen) means
a tour silently never appears again after one transient network blip, and the user has no way to
know. Failing *open* means the tour may replay once — mildly annoying, self-correcting, and
visible. D2 already establishes that this package is not for compliance-shaped requirements, so
"shown twice" is an acceptable worst case while "never shown again" is not.

**Alternatives considered**: retry with backoff (queues work in a page the user is about to leave);
`navigator.sendBeacon` (fire-and-forget, no failure signal at all — reconsider only if the POST
proves unreliable in practice); optimistic localStorage write alongside the server record
(reintroduces per-browser state under a driver whose whole point is to escape it).

---

## R7 — Constitution status

**At the time this research was written**, `.specify/memory/constitution.md` was the unmodified
template — every principle still a `[PRINCIPLE_N_NAME]` placeholder — so no project gates could be
derived from it, and `plan.md` substituted an explicitly-labelled `AGENTS.md` rule check.

**Resolved 2026-08-10**: the constitution was ratified as **v1.0.0**, with five principles
(test-first; the host owns its data and its words; a small frozen public surface; safe and honest
by default; ship runtime only). `plan.md`'s Constitution Check now evaluates against it directly,
and the substitution note is kept as history rather than deleted.

---

## R8 — Skeleton residue that this plan must remove

Found while reading configs. Each contradicts an adopted design decision or is simply broken.
Listed here because the tasks phase must delete them, not build on them (AGENTS.md **R-024**).

| # | Artifact | Problem |
|---|---|---|
| 1 | `database/migrations/create_tours_table.php.stub` | Design §1 / R-017: the package ships **no** migration. |
| 2 | `FilamentToursServiceProvider::getMigrations()` returns `'create_filament-tours_table'` | Names a file that does not exist — the actual stub is `create_tours_table.php.stub`. **Broken today**, and moot once #1 is deleted. |
| 3 | `->publishMigrations()->askToRunMigrations()` in the install command | Same violation as #1, user-facing. |
| 4 | `->hasTranslations()` + `resources/lang/en/tours.php` | Design D6 / R-018: the package ships **no** lang files. |
| 5 | `database/factories/ModelFactory.php` + `Factory::guessFactoryNamesUsing` in `tests/TestCase.php` | A factory for a model that does not and will not exist. |
| 6 | `tests/TestCase.php::defineDatabaseMigrations()` loads `database/migrations` | Depends on #1; must go with it. |
| 7 | `src/Commands/FilamentToursCommand.php` | Placeholder. Becomes the `tours:list` command (FR-018). |
| 8 | `src/FilamentTours.php` + `Facades/FilamentTours.php` + the `extra.laravel.aliases` entry | A facade over an empty class. The public surface is the plugin and the value objects (R-020). |
| 9 | `tests/ExampleTest.php`, `tests/DebugTest.php` | Skeleton stubs. |
| 10 | `resources/js/index.js` empty, `resources/dist/` holds only `.gitkeep` | Nothing is built yet — the spike's starting point. |

**Sequencing note**: #1, #3, #6 and the `hasMigrations()` call are one atomic change — removing the
stub without removing `defineDatabaseMigrations()` breaks the suite.

---

## Open items carried into implementation

| Item | Where it lands |
|---|---|
| driver.js version, ESM shape, bundle size | The spike (SC-009) proves it or the plan changes. |
| Non-`BasePage` panel components and empty scope arrays | Resolver treats as "no class match"; covered by a test. |
| Constitution unfilled | `/speckit-constitution`, before implementation. |
