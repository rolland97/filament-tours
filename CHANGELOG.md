# Changelog

All notable changes to `filament-tours` will be documented in this file.

## Unreleased

- **Added `Tour::manual()`** — a tour that belongs to a page and reaches the browser but does not
  start uninvited; `StartTourAction` and the `filament-tours:start` event run it as before. Added
  after the first real consumer found that an auto-starting tour covers a form someone came to fill
  in, and blocks the automated journeys that walk those pages. `tours:list` reports it as
  `starts: when asked`.

## 1.0.0 - 202X-XX-XX

- initial release
