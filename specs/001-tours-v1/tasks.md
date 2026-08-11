---
description: "Task list for 001-tours-v1 — guided product tours for Filament panels"
---

# Tasks: Guided Product Tours for Filament Panels (v1)

**Input**: Design documents from `/specs/001-tours-v1/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: **Required, not optional.** Constitution **Principle I** (test-first, NON-NEGOTIABLE), `AGENTS.md` **R-006**, and the `before_implement` gate in `.specify/extensions.yml` (`optional: false`). Every behaviour task is preceded by the test that would catch its absence.

**Organization**: Grouped by user story so each is independently implementable and testable.

> **Revised twice on 2026-08-10.** First after `/speckit-analyze`: five tasks added for the missing
> driver.js stylesheet and for the FR-025, FR-026 and SC-004 coverage gaps. Then after the
> `before_implement` gate, which found two test-first violations in the list itself — the asset
> test was ordered *after* the code it tests, and `LocalStorageState` had no focused test. Task IDs
> from earlier versions are **not** stable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: `[US1]`…`[US6]`, mapping to the user stories in spec.md
- Exact file paths in every description

## Path Conventions

Single Composer package. Source at `src/`, tests at `tests/`, client at `resources/js/`, built output committed to `resources/dist/`. PSR-4 root is `Rolland\FilamentTours\`.

---

## Phase 1: Setup

**Purpose**: Get the toolchain to a state where the spike can run at all.

- [X] T001 Run `npm install` to create `node_modules/` — it does not exist in this checkout (research R4)
- [X] T002 Add `driver.js` as a pinned devDependency in `package.json` and install it (design D7, research R4)
- [X] T003 [P] Replace the empty `config/tours.php` with `config/filament-tours.php` holding `['state' => 'local']` per `contracts/php-api.md`. **The rename is required, not cosmetic**: the provider guards on `file_exists(config/{shortName}.php)` and `shortName()` returns `filament-tours` (there is no `laravel-` prefix to strip), so `config/tours.php` never matched, `hasConfigFile()` never fired, and `config('filament-tours.state')` was null — research R8 #11
- [X] T004 [P] Create the minimal Testbench panel harness in `tests/Panel/TestPanelProvider.php` with two pages, `tests/Panel/Pages/PageA.php` and `tests/Panel/Pages/PageB.php`, so matching and non-matching pages both exist

**Checkpoint**: `npm run build` executes and a Testbench panel boots.

---

## Phase 2: Spike — the blocking gate 🚧

**Purpose**: Prove the Filament seams everything else rests on, **before** any value object or registry exists.

**⚠️ THIS PHASE BLOCKS EVERYTHING BELOW IT.** This is CD-3 and **SC-009**. Use a hardcoded tour, inline in the hook — no `Tour`, no `Step`, no registry. If T007 fails, **stop and re-plan**: the resolution strategy in research R2 is the assumption the rest of the plan rests on.

- [X] T005 [P] Write the failing test asserting a `BODY_END` render hook registered by the plugin emits markup into the response on a panel page, in `tests/RenderHookTest.php`
- [X] T006 [P] Write the failing test asserting the hook emits **nothing** on a page the hardcoded tour does not target, in `tests/RenderHookTest.php`
- [X] T007 Write the failing test asserting the hook callback receives `array $scopes` containing the **current page's class** — the design §6 tripwire in its verified form (research R2) — in `tests/PageClassResolutionTest.php`
- [X] T008 Register a `BODY_END` render hook in `FilamentToursPlugin::register()` in `src/FilamentToursPlugin.php`, emitting a hardcoded payload, to make T005–T007 pass
- [X] T009 Write a minimal Alpine component importing driver.js in `resources/js/index.js`, which is currently empty (0 bytes)
- [X] T010 Settle the asset path conflict: `bin/build.js` writes `resources/dist/filament-tours.js` while the provider's commented registration expects `resources/dist/components/filament-tours.js` (research R3). Change `outfile` in `bin/build.js` to the `components/` path
- [X] T011 Uncomment and correct the `AlpineComponent` registration in `FilamentToursServiceProvider::getAssets()` in `src/FilamentToursServiceProvider.php`, pointing at the path settled in T010
- [X] T012 Write the failing test asserting **both** registered assets — component and stylesheet — resolve to files that exist on disk after a build, in `tests/AssetRegistrationTest.php`. It must fail on the stylesheet, which does not exist yet
- [X] T013 Import the tour engine's stylesheet in `resources/js/index.js` and confirm `bin/build.js` emits it alongside the component — **an unstyled tour is not a working tour** (SC-004, `contracts/payload.md` § Styling). Actual output is `resources/dist/components/filament-tours.css`, **not** the `resources/dist/filament-tours.css` this task originally predicted: esbuild places a CSS import's output next to the entry point's `outfile`
- [X] T014 Register the stylesheet as a `Css` asset in `FilamentToursServiceProvider::getAssets()` in `src/FilamentToursServiceProvider.php`, alongside the `AlpineComponent`, making T012 pass
- [X] T015 Run `npm run build` and commit the built output under `resources/dist/`
- [X] T016 Verify driver.js boots **and is styled** in a real browser against real Filament markup, using the Testbench panel — the one step no PHP test can cover (design §8 names this gap). **Done via Playwright against `testbench serve`**, not by a human at a screen: on PageA the popover, overlay and `.driver-active-element` are present with `driver.css` applied (`position: fixed`, `z-index: 1000000000`); on PageB none of them appear and the HTML contains no trace of the tour. See `quickstart.md` § Reproducing Gate 0 in a browser

**Checkpoint / Gate 0**: Hook fires, scopes carry the page class, both assets resolve, the engine runs styled. **SC-009 satisfied.** Only now may value objects and the registry be built.

---

## Phase 3: Foundational — remove the skeleton's contradictions

**Purpose**: Delete what the skeleton shipped that the design forbids. Constitution **Principle II**; `AGENTS.md` **R-024** — their presence is not precedent.

**⚠️ Sequencing**: T017, T018 and T020 are **one atomic change**. Removing the migration stub without also removing `defineDatabaseMigrations()` breaks the suite (research R8).

- [X] T017 Delete `database/migrations/create_tours_table.php.stub` — the package ships no migration (design §1, R-017)
- [X] T018 Remove `defineDatabaseMigrations()` and the `Factory::guessFactoryNamesUsing()` call from `tests/TestCase.php` (atomic with T017)
- [X] T019 Delete `database/factories/ModelFactory.php` — a factory for a model that does not and will not exist
- [X] T020 Remove `hasMigrations()`, `getMigrations()`, `publishMigrations()` and `askToRunMigrations()` from `src/FilamentToursServiceProvider.php`. Note `getMigrations()` currently returns `'create_filament-tours_table'`, naming a file that does not exist — **broken today**, moot once removed (research R8 #2)
- [X] T021 [P] Delete `resources/lang/en/tours.php` and remove the `hasTranslations()` call from `src/FilamentToursServiceProvider.php` — no lang files ship (design D6, R-018)
- [X] T022 [P] Delete `src/FilamentTours.php` and `src/Facades/FilamentTours.php`, and remove the `extra.laravel.aliases` entry from `composer.json` — a facade over an empty class
- [X] T023 [P] Delete the skeleton stubs `tests/ExampleTest.php` and `tests/DebugTest.php`
- [X] T024 Run `composer test && composer analyse` and confirm the suite is green after the removals

**Checkpoint**: The package no longer contradicts its own design. Suite green.

---

## Phase 4: User Story 1 — Define a tour and have it run itself (P1) 🎯 MVP

**Goal**: A tour declared for a page class, marked run-once, auto-starts on first visit and stays quiet on the second — with no host code beyond the definition and no npm on the consumer's side.

**Independent Test**: Register one tour for `PageA` in the test panel. Visit `PageA` → payload present, tour runs. Visit `PageB` → response contains nothing of the tour. Visit `PageA` again with seen recorded → does not run.

**Covers**: SC-001, SC-002, SC-004.

### Tests for User Story 1 ⚠️ write first, confirm they fail

- [X] T025 [P] [US1] Test `Step` fluent defaults and `side`/`align` value validation in `tests/StepTest.php`
- [X] T026 [P] [US1] Test `Tour` fluent defaults — `once` defaults false, steps ordered — in `tests/TourTest.php`
- [X] T027 [P] [US1] Test `TourRegistry::resolveFor()` for page-class match, predicate match, and non-match, in `tests/TourRegistryTest.php`
- [X] T028 [P] [US1] Test that a non-matching tour contributes **nothing** to the response — not its id, not its copy, not its selectors (SC-002) — in `tests/RenderHookTest.php`
- [X] T029 [P] [US1] Test the payload shape against `contracts/payload.md`: `panel`, `debug`, `seenEndpoint`, ordered `tours`, and that predicates and page classes are **absent**, in `tests/PayloadTest.php`
- [X] T030 [P] [US1] Test that tour copy is escaped — markup in `title`/`body` appears literally (FR-028, SC-010) — in `tests/PayloadTest.php`
- [X] T031 [P] [US1] Test that `LocalStorageState::hasSeen()` returns false for **any** tour id and that `markSeen()` writes nothing — the browser holds the answer under this driver, so the server answering otherwise would be a lie (FR-020, data-model.md) — in `tests/StateDriverTest.php`

### Implementation for User Story 1

- [X] T032 [P] [US1] Create the `Step` value object in `src/Step.php` per `data-model.md` and `contracts/php-api.md`
- [X] T033 [P] [US1] Create the `Tour` value object in `src/Tour.php` per `data-model.md` and `contracts/php-api.md`
- [X] T034 [P] [US1] Create the `TourState` interface in `src/Contracts/TourState.php`
- [X] T035 [US1] Create `LocalStorageState` in `src/State/LocalStorageState.php` — `hasSeen()` returns false unconditionally by design, `markSeen()` is a no-op (data-model.md), making T031 pass
- [X] T036 [US1] Create `TourRegistry` in `src/TourRegistry.php` with `register()`, `all()`, and `resolveFor(array $scopes)`, preserving registration order (FR-011)
- [X] T037 [US1] Add `->tours(array $tours)` to `src/FilamentToursPlugin.php` and bind the registry as a per-panel singleton in `register()`
- [X] T038 [US1] Create the render-hook view in `resources/views/tours.blade.php`, emitting the escaped payload as the Alpine component's initial state. **No Tailwind utility classes** — they would force consumers to edit their panel theme, contradicting SC-004 (`contracts/payload.md` § Styling)
- [X] T039 [US1] Emit the `debug` flag in the payload from `resources/views/tours.blade.php`, mirroring the application's debug state — the browser cannot read it itself and every console diagnostic depends on it (FR-015)
- [X] T040 [US1] Replace the spike's hardcoded payload in `src/FilamentToursPlugin.php` with `TourRegistry::resolveFor($scopes)`, reading scopes from the hook callback (research R2)
- [X] T041 [US1] Implement the Alpine component startup sequence in `resources/js/index.js` per `contracts/js-events.md`: empty payload → do nothing; otherwise auto-start the first eligible tour
- [X] T042 [US1] Implement localStorage seen handling under key `filament-tours:{panel}:{tour}` in `resources/js/index.js`, writing on finish **and** on dismiss
- [X] T043 [US1] Run `npm run build` and commit the updated `resources/dist/`

### Verification for User Story 1

- [X] T044 [P] [US1] Test that a tour **without** the run-once flag remains in the payload and auto-starts on a repeat visit, while a run-once tour does not — the flag is persistence, **not** a trigger (FR-026) — in `tests/PayloadTest.php`
- [X] T045 [US1] Verify the consumer install path end to end (SC-004): install the package into a scratch Laravel + Filament application, register the plugin and one tour, run `php artisan filament:assets`, and confirm a working, styled tour appears **without touching npm, a bundler, or the panel theme**. **Done for real, not simulated**: the release archive was extracted and `composer require`d into a separate project via a path repository with `symlink: false`, so the app used only the 20 files a consumer receives. Registering the plugin and one tour, then `filament:assets`, produced a working styled tour — popover, correct element highlighted, `position: fixed` / `z-index: 1000000000` from the shipped stylesheet. The consumer app has no `node_modules`, no bundler config and no theme edit. See `quickstart.md` § Proving SC-004 from a real install

**Checkpoint**: MVP. A tour defined in a panel provider runs once and only once, leaks onto nothing, and installs clean.

---

## Phase 5: User Story 2 — A tour survives the page changing underneath it (P2)

**Goal**: A moved or renamed element degrades the tour gracefully instead of breaking it, and the rot is visible to developers.

**Independent Test**: Three-step tour whose **middle** selector is absent → steps 1 and 3 run, step 2 skipped, console warning under debug names tour and selector.

**Covers**: SC-003.

### Tests for User Story 2 ⚠️ write first, confirm they fail

- [X] T046 [P] [US2] Test that a tour with no resolvable steps does not start and shows nothing (FR-016), in `tests/RenderHookTest.php`

> The remaining US2 behaviour is client-side and has **no PHP test**. This is the named gap in design §8, constitution **Principle I**, and **R-009** — verified manually via T050 and by the first consumer's browser journey. Do not add a JS test toolchain for it without an explicit decision.

### Implementation for User Story 2

- [X] T047 [US2] Implement selector filtering in `resources/js/index.js` — drop steps whose selector does not resolve, keep the rest running (FR-014)
- [X] T048 [US2] Implement the debug-gated console warning naming tour and unmatched selector in `resources/js/index.js`, reading the payload's `debug` flag (FR-015, SC-003)
- [X] T049 [US2] Implement `livewire:navigating` teardown in `resources/js/index.js` — destroy any live tour, and do **not** mark it seen (FR-012)
- [X] T050 [US2] Run `npm run build`, commit `resources/dist/`, and manually verify Gate 2 in [quickstart.md](./quickstart.md) in a browser: missing middle step, no-steps-resolve, and mid-tour navigation

**Checkpoint**: A rotted tour degrades quietly for users and loudly for developers.

---

## Phase 6: User Story 3 — Replay a tour on demand (P3)

**Goal**: A dismissed or already-seen tour can be started again, and a page carrying several tours starts exactly one.

**Independent Test**: Place `StartTourAction` on a page, activate it → the named tour runs even though it is marked seen. Register two tours matching one page → only the first auto-starts, the second runs on demand.

**Covers**: FR-013, FR-024, FR-025.

### Tests for User Story 3 ⚠️ write first, confirm they fail

- [X] T051 [P] [US3] Test that two tours matching one page both appear in the payload, in registration order (FR-011, FR-024), in `tests/PayloadTest.php`
- [X] T052 [P] [US3] Test that `StartTourAction::make('id')` renders and dispatches the `filament-tours:start` event with the correct tour id, in `tests/StartTourActionTest.php`

### Implementation for User Story 3

- [X] T053 [P] [US3] Create `StartTourAction` in `src/Actions/StartTourAction.php` per `contracts/php-api.md`, dispatching `filament-tours:start`
- [X] T054 [US3] Implement the `filament-tours:start` listener in `resources/js/index.js` per `contracts/js-events.md`: run regardless of seen state, destroy any running tour first, do not re-mark seen
- [X] T055 [US3] Handle an unknown tour id in `resources/js/index.js` — nothing starts, no user-facing error, console warning under debug
- [X] T056 [US3] Enforce single auto-start in `resources/js/index.js` — scan the ordered payload and start only the first eligible tour (FR-024, FR-027)
- [X] T057 [US3] Run `npm run build` and commit the updated `resources/dist/`

### Verification for User Story 3

- [X] T058 [US3] Manually verify Gate 3 in [quickstart.md](./quickstart.md) in a browser: with two tours matching one page, the first auto-starts and the **second** can then be started by id without reloading (FR-025, spec User Story 3 scenario 4)

**Checkpoint**: Replay works; two overlays are never open at once.

---

## Phase 7: User Story 4 — Move "seen" from the browser to the server (P3)

**Goal**: A host switches one config value and persistence moves server-side, with tour definitions untouched.

**Independent Test**: Under `'local'`, assert **no** route is registered and `seenEndpoint` is `null`. Under a host driver, assert seen tours are filtered before render and exactly one authenticated route exists. Definitions byte-identical across both.

**Covers**: SC-005.

### Tests for User Story 4 ⚠️ write first, confirm they fail

- [X] T059 [P] [US4] Test that **no** seen route is registered under `'state' => 'local'`, by inspecting the route list (FR-020) — the absence assertion — in `tests/SeenRouteTest.php`
- [X] T060 [P] [US4] Test that exactly one route is registered under a host driver, behind the panel's auth middleware (FR-021), in `tests/SeenRouteTest.php`
- [X] T061 [P] [US4] Test that an unknown tour id returns 404 and **never** reaches the driver (`contracts/http.md`), in `tests/SeenRouteTest.php`
- [X] T062 [P] [US4] Test that a driver reporting a tour seen causes it to be filtered from the payload **before render**, so its copy never reaches the browser (FR-008), in `tests/TourRegistryTest.php`
- [X] T063 [P] [US4] Test that an invalid `filament-tours.state` value fails loudly at boot rather than silently falling back to `'local'` (`contracts/php-api.md`), in `tests/StateDriverTest.php`

### Implementation for User Story 4

- [X] T064 [US4] Implement state-driver resolution from `config('filament-tours.state')` in `src/FilamentToursServiceProvider.php`, resolving host class-strings from the container
- [X] T065 [US4] Add server-driver filtering to `TourRegistry::resolveFor()` in `src/TourRegistry.php` — drop run-once tours the driver reports seen (FR-008)
- [X] T066 [US4] Create `MarkTourSeenController` in `src/Http/Controllers/MarkTourSeenController.php` — validate the tour id against the registry, call `markSeen()`, return 204
- [X] T067 [US4] Register the single `POST filament-tours/{tour}/seen` route in `FilamentToursPlugin::boot()` in `src/FilamentToursPlugin.php`, **only** when a server driver is configured, behind the panel's auth middleware
- [X] T068 [US4] Emit `seenEndpoint` in the payload — a URL under a server driver, `null` under browser-local — in `resources/views/tours.blade.php`
- [X] T069 [US4] Implement the client POST on finish and dismiss in `resources/js/index.js`, branching on `seenEndpoint` nullness per `contracts/js-events.md`
- [X] T070 [US4] Implement fail-open POST failure handling in `resources/js/index.js` per research R6: suppress for this page session only, no retry, warn under debug
- [X] T071 [US4] Run `npm run build` and commit the updated `resources/dist/`

**Checkpoint**: Persistence moves server-side by config alone. No definition changed.

---

## Phase 8: User Story 5 — Find out what tours exist, catch a bad definition early (P4)

**Goal**: An inventory command, and registration failures that name the offender.

**Independent Test**: `php artisan tours:list` reports every tour with page-or-predicate, step count, and `once`. Each of the three invalid definitions throws with the tour named.

**Covers**: SC-006, SC-007.

### Tests for User Story 5 ⚠️ write first, confirm they fail

- [X] T072 [P] [US5] Test that a duplicate tour id throws `InvalidArgumentException` naming the offending tour (FR-017), in `tests/TourRegistryTest.php`
- [X] T073 [P] [US5] Test that a tour with zero steps throws, naming the tour, in `tests/TourRegistryTest.php`
- [X] T074 [P] [US5] Test that `->for()` naming a non-existent class throws, naming the tour and the missing class, in `tests/TourRegistryTest.php`
- [X] T075 [P] [US5] Test that `tours:list` outputs id, page or `when()`, step count, and `once` for every registered tour, **and that its output makes no claim about selector validity** — a selector cannot be checked without a browser (SC-006, design §7) — in `tests/ListToursCommandTest.php`

### Implementation for User Story 5

- [X] T076 [US5] Add registration-time validation to `TourRegistry::register()` in `src/TourRegistry.php` — duplicate id, empty steps, `class_exists()` — each throwing with the tour named
- [X] T077 [US5] Create `ListToursCommand` in `src/Commands/ListToursCommand.php` as `tours:list`, and delete the placeholder `src/Commands/FilamentToursCommand.php`
- [X] T078 [US5] Update `getCommands()` in `src/FilamentToursServiceProvider.php` to register `ListToursCommand`

**Checkpoint**: Typos fail loudly at registration; the panel's tours are inventoried.

---

## Phase 9: User Story 6 — The package does not ship the workshop (P4)

**Goal**: A consumer's `vendor/` receives runtime code and nothing else.

**Independent Test**: `git archive --format=tar HEAD | tar -t` contains no development tooling or build sources, and still contains `src/`, `config/`, `resources/views/`, `resources/dist/`.

**Covers**: SC-008. **CD-4 — must land before the first release tag.**

- [X] T079 [P] [US6] Add `/.specify`, `/.claude`, `/AGENTS.md`, `/specs`, `/bin`, `/resources/js`, `/resources/css` as `export-ignore` entries in `.gitattributes`. Measured: 70 tracked files across `.specify` + `.claude` alone, currently bound for every consumer's `vendor/`
- [X] T080 [US6] Verify with `git archive --format=tar HEAD | tar -t | sort` that development tooling and build sources are absent and that `src/`, `config/`, `resources/views/`, and `resources/dist/` are **present** — `resources/dist` must stay shipped, it is the built asset consumers need

**Checkpoint**: SC-008 satisfied. Safe to tag.

---

## Phase 10: Polish & Cross-Cutting

- [X] T081 [P] Add `tests/ArchTest.php` asserting: no `dd`/`dump`/`ray` calls in `src/`; value objects are `final`; everything in `src/Contracts/` is an interface; and — enforcing FR-003 — that no public method on `Step` or `Tour` references Filament component or field classes, so DOM-encoding helpers cannot be added by accident (R-008, constitution Principle III)
- [X] T082 [P] Rewrite `README.md`: replace the skeleton boilerplate ("This is where your description should go"), remove the "publish and run the migrations" section that constitution Principle II forbids, fix the **Filament 4.x** docs link, drop the `@source` theme instruction that contradicts SC-004, and document installation plus the frozen public API from `contracts/php-api.md` and the `[data-tour="…"]` convention (D5)
- [X] T083 [P] Document the state-driver upgrade path in `README.md`, including the ⚠️ that browser-local is per-browser and unsuitable for compliance-shaped requirements (D2)
- [X] T084 Run the full check: `composer test && composer analyse && composer test:lint && npm run build`, and confirm `resources/dist` is committed
- [X] T085 Walk every gate in [quickstart.md](./quickstart.md) end to end and record the result honestly — if something fails, say so with the output (constitution Principle IV, **R-027**). **Walked 2026-08-11**, all gates held: the required XSS probe shows no execution and zero injected nodes with the client as it stands after US3 and US4; a three-step tour with a missing middle selector shows "First" and "Third"; a tour whose selectors all miss shows no popover and no overlay; `livewire:navigating` destroys a running tour and leaves localStorage untouched. Server-driver flow (Gate 4) was walked during T059–T071 and is unchanged since
- [ ] T086 Re-run `/speckit-analyze` against `specs/001-tours-v1/` and confirm the gaps closed by these revisions — FR-025, FR-026, SC-004, the CSS asset, and the two test-first orderings — now map to tasks, and that no new drift appeared

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — no dependencies
- **Phase 2 (Spike)** — depends on Phase 1. **BLOCKS EVERYTHING BELOW** (CD-3, SC-009)
- **Phase 3 (Foundational)** — depends on Phase 2. Blocks all user stories
- **Phase 4 (US1)** — depends on Phase 3. The MVP
- **Phases 5–9 (US2–US6)** — depend on Phase 4
- **Phase 10 (Polish)** — depends on the stories you intend to ship

### User Story Dependencies

- **US1 (P1)** — the vertical slice everything else refines. Not independent of the foundation, by nature
- **US2 (P2)** — needs US1's client component to degrade
- **US3 (P3)** — needs US1's payload to replay from
- **US4 (P3)** — needs US1's registry to filter. Independent of US2 and US3
- **US5 (P4)** — needs US1's registry to validate. Independent of US2, US3, US4
- **US6 (P4)** — **fully independent.** Touches only `.gitattributes` and can be done at any point after Phase 1

### Within Each User Story

Tests first, confirmed failing, then implementation. Value objects before the registry; registry before the plugin wiring; server before client. Every client change ends with a rebuild and a commit of `resources/dist`.

### Parallel Opportunities

- **Phase 1**: T003, T004 in parallel
- **Phase 2**: T005, T006 in parallel; T007 after them. T012 must fail before T013/T014 make it pass
- **Phase 3**: T021, T022, T023 in parallel; T017/T018/T020 are atomic and sequential
- **Phase 4**: all tests T025–T031 in parallel; then T032, T033, T034 in parallel
- **Phases 5–9**: US4, US5 and US6 can proceed in parallel once US1 is done
- **US6 (T079, T080)** can be pulled forward at any time — it blocks a tag, not a feature

⚠️ **`resources/js/index.js` is a single file touched by T009, T013, T041, T042, T047, T048, T049, T054, T055, T056, T069 and T070.** These are **never** parallel with each other, regardless of which story they belong to. This is the sharpest conflict point in the plan.

---

## Parallel Example: User Story 1

```bash
# All US1 tests together — different files, no shared state:
Task: "Test Step fluent defaults and value validation in tests/StepTest.php"
Task: "Test Tour fluent defaults in tests/TourTest.php"
Task: "Test TourRegistry resolveFor matching in tests/TourRegistryTest.php"
Task: "Test LocalStorageState answers false for any id in tests/StateDriverTest.php"

# Then the three independent value objects:
Task: "Create Step value object in src/Step.php"
Task: "Create Tour value object in src/Tour.php"
Task: "Create TourState interface in src/Contracts/TourState.php"
```

---

## Implementation Strategy

### The spike is not optional

Phase 2 exists because page-class resolution and the asset path depend on Filament internals. [Research R2](./research.md) verified the mechanism against the installed `vendor/` tree, but verified-by-reading is not verified-by-running. If T007 fails, the resolution strategy is wrong and every value object built on it would be rework. **Stop and re-plan rather than working around it.**

The stylesheet tasks (T012–T014) live here for the same reason: `/speckit-analyze` found it missing from the entire plan, and an unstyled tour fails SC-004 just as surely as a missing one. Proving both assets at once costs nothing extra now and a rebuild later.

### MVP scope

Phases 1 → 2 → 3 → 4. That is T001–T045: a tour that defines, resolves, delivers, runs once, leaks onto nothing, and installs clean. Stop there and validate against Gate 1 in [quickstart.md](./quickstart.md) before continuing.

### Incremental delivery after MVP

US2 (degradation) is the highest-value follow-on — rot is certain, and without it users meet broken tours. Then US4 or US3 depending on whether the first consumer needs server-side persistence or replay first. US5 and US6 are cheap and can land any time.

### Before the first tag

**T079 and T080 are release blockers**, not polish. Tag without them and every consumer gets development tooling in their `vendor/` — and it cannot be retracted from a published tag.

---

## Notes

- `[P]` = different files, no dependencies
- Commit after each task or logical group. Do not push or tag unless asked (**R-016**)
- Conventional Commits, lowercase descriptive subject (**R-013**)
- Do not hand-format PHP — Pint reformats and commits back on push (**R-011**)
- Do not add to `phpstan-baseline.neon` to silence new errors (**R-010**)
- Every `resources/js/` change needs `npm run build` **and** the `resources/dist` commit, or the package ships a stale bundle
- The suite runs in random order and fails on stray output (**R-007**)
- The constitution is `.specify/memory/constitution.md` **v1.0.0**, ratified 2026-08-10. [plan.md](./plan.md)'s Constitution Check evaluates against it directly. Principle I is what makes the test tasks above mandatory rather than optional
