# Design: `rolland97/filament-tours`

**Date**: 2026-08-03 · **Status**: approved (brainstorming, 2026-08-03) · **Package**: `rolland97/filament-tours`

Guided product tours for Filament v5 panels, powered by [driver.js](https://driverjs.com/).

First consumer: the **procurement** app, backlog **#18** ("guided product tours"), which
gets its own spec-kit cycle in that repo. This document is the package's design only.

---

## 1. Purpose and boundary

The package answers one question: *"which tour, if any, should run on the page this
user just opened, and how does it get on screen?"*

It owns three things:

1. **Definition** — a fluent PHP API for declaring tours and their steps.
2. **Resolution** — deciding which tours apply to the current page and user.
3. **Delivery** — bundling driver.js, registering it with Filament, and driving it.

It deliberately does **not** own persistence. Whether "this user has seen the
orientation tour" lives in a column, a JSON blob, or the browser is the host
application's decision, so **no migration ships in this package** (§5).

## 2. Decisions taken, with their reasons

Each was chosen against alternatives during brainstorming; the reasons are recorded
because the alternatives will look attractive again later.

| # | Decision | Why, and what was rejected |
|---|---|---|
| D1 | **Tours are registered centrally, keyed by page** | Tour text is *copy*, and copy is rewritten by whoever owns the wording, not the page class. A central registry keeps it reviewable, translatable and diffable in one place, and lets a tour attach to a page the host does not own (Filament's own dashboard, a vendor package's page). Rejected: page-declared tours via a `HasTour` trait — scatters copy across page classes and cannot reach foreign pages. |
| D2 | **Auto-start is opt-in per tour (`->once()`), with a localStorage default and a server-side contract available** | A tour nobody sees is not onboarding, so trigger-only was too thin; requiring server state was too heavy for "dismissed a popup". localStorage means the package works on install; the contract means a host can upgrade without the package changing. ⚠️ Accepted cost: localStorage is per-browser, so a second device replays the tour. Fine for hints, **not** fine for anything compliance-shaped — a host needing "confirm you have read this" must use the server driver. |
| D3 | **Page matching by class (`->for(CreateItrf::class)`), with a `->when()` predicate as the escape hatch. No route names.** | A class name is the one identifier an IDE rename actually updates, and it can be validated at boot so a typo fails loudly instead of silently showing no tour. Route-name strings add fragility (they rot on a panel-id or slug change) without adding reach the predicate lacks. ⚠️ Consequence: "every list page in this panel" is a predicate, not a wildcard — no glob syntax over class names is invented. |
| D4 | **A step whose target is missing is skipped, not fatal** | Users never see a broken tour. Rot is made visible to developers instead: `console.warn` naming tour and selector when `app.debug`, plus `php artisan tours:list`. Rejected: aborting the tour, which punishes the user for a moved button. |
| D5 | **Raw CSS selectors, `[data-tour="…"]` as the documented convention** | Helpers that know Filament's internal markup (`Step::forField('title')`) would read better and break on point releases. The package must not encode Filament's DOM. |
| D6 | **Copy takes strings, not translation keys** | `->title(__('tours.x'))` is the host's call, so the package ships no lang file and never owns anyone's `ms` wording. |
| D7 | **driver.js is bundled into the package's own `resources/dist`** | Registered via `FilamentAsset`, so consuming apps touch no npm config and the dependency is pinned in exactly one place. Rejected: an npm peer dependency (every consumer re-does vite wiring) and a CDN (an external runtime dependency, against environment parity). |

## 3. Architecture

Four units, each independently testable, none reaching into another's internals.

| Unit | Responsibility | Depends on |
|---|---|---|
| `Tour`, `Step` | Value objects. Fluent definition plus self-validation. No behaviour. | nothing |
| `TourRegistry` | Holds tours; answers *"which apply to this page, for this user?"* | `Tour`, `TourState` |
| `FilamentToursPlugin` | Per-panel wiring: accepts tours, adds the render hook. | `TourRegistry` |
| `TourState` + `LocalStorageState` | Answers/records "seen". | nothing |

`FilamentToursServiceProvider` (already scaffolded) registers the built asset through
`FilamentAsset::register([AlpineComponent::make('tours', …dist/tours.js)], 'rolland97/filament-tours')`
and binds the registry as a singleton.

`resources/js/index.js` imports driver.js and exports one Alpine component; `bin/build.js`
(esbuild, from the skeleton) builds it into `resources/dist`, which is committed.

## 4. Public API

```php
// A panel provider. Tours are per-panel because the plugin is per-panel.
FilamentToursPlugin::make()
    ->tours([
        Tour::make('itrf-create')
            ->for(CreateItrf::class)
            ->once()
            ->steps([
                Step::make('#data\\.title')
                    ->title(__('tours.itrf.title.heading'))
                    ->body(__('tours.itrf.title.body')),
                Step::make('[data-tour="items"]')
                    ->title(__('tours.itrf.items.heading'))
                    ->body(__('tours.itrf.items.body'))
                    ->side('left'),
            ]),

        Tour::make('office-orientation')
            ->when(fn (): bool => auth()->user()->isNewThisWeek())
            ->steps([/* … */]),
    ])
```

Replay from anywhere:

- `StartTourAction::make('itrf-create')` — a Filament action for a header or page toolbar.
- `$dispatch('filament-tours:start', { tour: 'itrf-create' })` — from any Alpine or Livewire context.

**Surface, in full**: `Tour::make(string $id)`, `->for(string $pageClass)`,
`->when(Closure $predicate)`, `->once()`, `->steps(array $steps)`;
`Step::make(string $selector)`, `->title(string)`, `->body(string)`,
`->side('top'|'right'|'bottom'|'left')`, `->align('start'|'center'|'end')`.

Nothing else is public in v1. `side`/`align` pass straight through to driver.js.

## 5. State drivers

```php
interface TourState
{
    public function hasSeen(string $tourId): bool;

    public function markSeen(string $tourId): void;
}
```

`config('filament-tours.state')` is `'local'` (default) or a class-string.

- **`'local'`** — no PHP state at all. The `once` flag is sent to the browser and
  localStorage decides, under key `filament-tours:{panel}:{tour}`. No route registered.
- **A host class-string** — the registry filters seen tours out of the payload *before*
  render, and the package registers exactly one route,
  `POST filament-tours/{tour}/seen`, behind the panel's auth middleware, which calls
  `markSeen()`. **That route exists only when a server driver is configured.**

Procurement starts on `'local'` and can move later without a package change.

## 6. Runtime flow

1. A request reaches a page in a panel where the plugin is registered.
2. The plugin's `BODY_END` render hook renders one blade.
3. `TourRegistry` resolves applicable tours **server-side** — page-class match or
   `when()` predicate, minus anything a server-side `TourState` reports as seen.
   Non-matching tours are never serialised, so a user's HTML never contains tour copy
   for pages they cannot reach.
4. The blade emits an Alpine component with that payload (tour id, steps, `once`).
5. Alpine boots driver.js: drops steps whose selector does not resolve, starts only if
   steps remain, and on finish or dismiss marks the tour seen (localStorage write, or a
   POST to the seen route).
6. On `livewire:navigating`, any live driver instance is destroyed, so an overlay cannot
   survive an SPA navigation onto a page it does not describe.

⚠️ **The fragile step is page-class resolution.** Filament registers pages as routed
Livewire components, so the class is read off the current route's action — an internal
detail that a Filament upgrade could move. It gets a dedicated test asserting a known
page class resolves, so an upgrade fails loudly here instead of silently showing no
tours anywhere.

## 7. Errors and diagnostics

| Situation | Behaviour |
|---|---|
| Step selector not on the page | Skipped. `console.warn` naming tour + selector when `app.debug`. |
| No steps survive filtering | Tour does not start. Nothing is shown. |
| Duplicate tour id | `InvalidArgumentException` at registration. |
| Tour with zero steps | `InvalidArgumentException` at registration. |
| `->for()` names a class that does not exist | `InvalidArgumentException` at registration. |
| `php artisan tours:list` | Table of every registered tour: id, page or `when()`, step count, `once`. |

The command is the only static check possible: the package can verify a page class and a
step count, and **cannot** verify a CSS selector without a browser. An honest listing is
preferred to a validator that implies more than it checks.

## 8. Testing

Pest 4 + Orchestra Testbench (both already in the skeleton), with a minimal panel.

- **Value objects** — validation rules in §7, fluent defaults.
- **Registry** — class match, predicate match, non-match, server-driver filtering.
- **Render hook** — payload present on a matching page, absent otherwise; asset registered.
- **Page-class resolution** — a known page class resolves (the §6 tripwire).
- **Seen route** — marks through the driver; **absent** under `'local'`.
- **Arch** — the skeleton's `ArchTest` conventions.

⚠️ **Named gap: no JS test suite in v1.** The Alpine wrapper (selector filtering,
`livewire:navigating` teardown, localStorage keys) would need vitest plus a DOM — a
second toolchain for ~60 lines of JS. The mitigation is that **procurement's #18 browser
journey is the real proof**, running driver.js in actual Chromium against actual Filament
markup. If the JS grows beyond driving driver.js, vitest should be added rather than
stretching this justification.

## 9. Non-goals for v1

- Steps that drive interaction (open a modal, switch a tab) — v1 targets only elements
  **present when the page loads**. Scripting interactions is a much larger design and
  much easier to get subtly wrong.
- A tour-authoring UI.
- Completion analytics.
- Tours spanning multiple pages.
- Shipped translation files (D6).
- Any migration (§1).

## 10. Success criteria

- **SC-1** A tour defined with `->for(SomePage::class)->once()` runs on first visit to
  that page and not on the second, with no host code beyond the definition.
- **SC-2** A tour whose page does not match contributes **nothing** to the rendered HTML.
- **SC-3** A step whose selector is absent is skipped, the remaining steps still run, and
  the skip is reported in the console under `app.debug`.
- **SC-4** A consuming app installs the package and sees a working tour **without touching
  npm, vite, or its panel theme**.
- **SC-5** Switching `config('filament-tours.state')` from `'local'` to a host driver moves
  persistence server-side with no change to any tour definition.
- **SC-6** `php artisan tours:list` lists every registered tour with its page and step count.
- **SC-7** A duplicate id, an empty step list, or an unknown page class fails at
  registration with a message naming the offending tour.
