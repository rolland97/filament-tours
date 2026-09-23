# Changelog

All notable changes to `filament-tours` will be documented in this file.

## Unreleased

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

## 1.0.0 - 202X-XX-XX

- initial release
