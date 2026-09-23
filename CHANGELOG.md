# Changelog

All notable changes to `filament-tours` will be documented in this file.

## 1.0.0 - 2026-09-23

First release. v1 was written in August 2026 and deliberately held back until a real application
had been built against it — the entries below are what that application found, and each one is a
gap no test in this repository could have reported.

- **`Step::title()` and `->body()` accept closures**, resolved when the payload is built. A panel
  is configured before request middleware runs, so copy declared with `__()` froze to the
  application's default locale and every reader saw that one language; hosts were writing a
  middleware to rebuild their tours because of it. Plain strings still work unchanged.

- **Fixed: the engine's ARIA is repaired on elements that cannot carry it.** driver.js sets
  `aria-haspopup`, `aria-expanded` and `aria-controls` on whatever it highlights; those are valid on
  a handful of widget roles and on nothing else, so on the ordinary target — a div, a section, a
  table — they are a **critical** `aria-allowed-attr` violation, and any accessibility audit of a
  page with a running tour fails. They are now kept where the element's role permits them and
  removed where it does not.
- **Fixed: a dismissal during the opening sequence is recorded.** The package recorded "seen" from
  driver's destroy hook, which fires only once the state it sets at the end of that sequence is in
  place — so a reader who dismissed inside that window was not remembered and met the tour again.
  Teardown is now observed directly, and recording happens once whichever path ends the tour.

- **Added `FilamentToursPlugin::buttonLabels()`** — host wording for the engine's own Next /
  Previous / Done buttons, which were the only strings on the popover a host could not translate.
  Accepts closures, resolved when the payload is built, because a panel is configured before request
  middleware runs and a plain `__()` there freezes to the default locale.
- **Added a dark variant for the popover**, scoped to the `.dark` class Filament toggles (never a
  `prefers-color-scheme` media query, which paints for the operating system while the panel believes
  otherwise). Colours are custom properties with defaults.

- **Added `Tour::manual()`** — a tour that belongs to a page and reaches the browser but does not
  start uninvited; `StartTourAction` and the `filament-tours:start` event run it as before. Added
  after the first real consumer found that an auto-starting tour covers a form someone came to fill
  in, and blocks the automated journeys that walk those pages. `tours:list` reports it as
  `starts: when asked`.

## Before 1.0.0

- The v1 design: tours declared on the panel, `Tour`/`Step`, run-once with a browser-local default
  and a server-side `TourState` contract, replay via `StartTourAction` or a DOM event, steps that
  skip a missing target, and a bundled engine so a host configures no bundler.
