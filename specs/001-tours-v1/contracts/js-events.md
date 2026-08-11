# Contract: browser events and client behaviour

**Feature**: `001-tours-v1`

The client half of the package: one Alpine component, driving the tour engine. Roughly sixty lines
of JavaScript, deliberately untested by a JS suite in v1 (design §8, AGENTS.md **R-009**) — which
is a reason to keep its contract small and explicit, not a reason to leave it undocumented.

---

## Events the package listens for

### `filament-tours:start`

Starts a tour by id, on demand. Works from any Alpine or Livewire context on a page where the
plugin is active.

```js
$dispatch('filament-tours:start', { tour: 'itrf-create' })
```

| Detail | Type | Notes |
|---|---|---|
| `tour` | `string` | Tour id. Must be present in the current page's payload. |

**Behaviour** (implemented as `startById()`):

- Runs the named tour **regardless of seen state** — replay is the whole point (User Story 3). It
  bypasses the `isSeen()` check the auto-start path applies: a user who asked for the tour has
  overridden whatever "seen" said.
- A tour id not in the current payload: **nothing starts, no error surfaces to the user**
  (User Story 3, scenario 3). Console warning under debug.
- An empty or missing `tour` detail is ignored silently.
- A tour already running is destroyed first, so a replay cannot stack overlays on top of a live one.
- Replay does **not** re-mark the tour seen. `start()` takes a `replay` flag that suppresses the
  `markSeen()` call in `onDestroyed` — the tour is already seen, so the write would be wasted, and
  under a server driver it would be a wasted HTTP request as well.
- The listener is bound on `window` in `init()` and unbound in Alpine's `destroy()`.

`StartTourAction` dispatches exactly this event and does nothing else. The tour id passes through
`Js::from()` on the PHP side, so an id containing a quote cannot close the Alpine expression and
append its own code — defence in depth, since ids are developer-authored.

`StartTourAction` dispatches exactly this event — the PHP action is a convenience over it, not a
second mechanism.

---

## Events the package reacts to

### `livewire:navigating`

Filament panels run as an SPA. On this event, any live tour instance is destroyed (FR-012).

**Why it is not optional**: without it, an overlay describing page A survives onto page B, pointing
at elements that no longer exist or — worse — at different elements that happen to match the same
selector. The teardown is what stops a tour from lying about the page it is on.

Destroying on navigation does **not** mark the tour seen. The user neither finished nor dismissed
it; they left. A run-once tour will offer itself again on the next visit, which is correct.

---

## Startup sequence

On component init, given the payload from [`payload.md`](./payload.md):

1. If `tours` is empty, do nothing. No engine boot, no listeners beyond the start event.
2. Scan `tours` **in order** for the first **eligible** entry:
   - every step whose selector does not resolve in the DOM is dropped (FR-014);
   - if no step survives, the tour is not eligible (FR-016);
   - if `once` is true and the tour is recorded seen, it is not eligible (FR-027);
     – under a server driver, seen tours were already filtered out server-side, so this check is
       the localStorage one and applies when `seenEndpoint` is `null`.
3. Auto-start that one tour, and **only** that one (FR-024). The rest stay available for replay
   (FR-025).
4. Each dropped step logs a console warning naming the tour and the unmatched selector, **under
   `app.debug` only** (FR-015, SC-003).

---

## Marking seen

On finish **or** dismiss, when the tour has `once`:

| Driver | Action |
|---|---|
| Browser-local (`seenEndpoint === null`) | Write `filament-tours:{panel}:{tour}` to localStorage. |
| Server (`seenEndpoint` is a URL) | `POST` to it. Failure handling in [`http.md`](./http.md). |

Dismiss counts the same as finish. A user who closed the tour has made a decision, and re-showing
it would override that decision.

---

## Copy rendering

⚠️ **The client-side guard is the load-bearing one.** This was implemented wrong once and the
browser proved it, so the mechanism is spelled out rather than summarised.

**Server-side escaping alone does not protect anything.** It makes the payload safe to embed in an
HTML attribute, and that is all. `JSON.parse` hands JavaScript the original characters straight
back, so by the time the client has the string, every server-side escape is undone. A tour body of
`<img src=x onerror=…>` arrives at the client as exactly that.

**driver.js assigns popover copy with `innerHTML`.** Passing it a raw string therefore creates real
DOM: an `<img onerror>` fires, and this is stored XSS inside an authenticated admin panel. Verified
by driving it in Chromium — the handler executed.

**`onPopoverRender` is NOT a fix.** It runs *after* the assignment, so the payload has already
executed by the time the hook could overwrite anything.

**The fix**: HTML-escape `title` and `body` in the client, before handing them to the engine. The
engine's `innerHTML` then renders them as visible literal text, and no nodes are created. If the
engine ever switches to `textContent`, this surfaces as entities showing on screen — a visible
display bug, not a silent hole, which is the right direction to fail in.

Both guards remain required: the server-side one for safe transport into the attribute, the
client-side one for safe rendering. Neither substitutes for the other.

---

## localStorage keys

```
filament-tours:{panel}:{tour}
```

Panel-scoped so the same tour id in two panels does not collide (design §5).

⚠️ **Per-browser by design.** A second device replays the tour. Accepted cost of the default (D2).
A host that needs "this person has acknowledged this" must configure a server driver — localStorage
is not a record of anything, it is a hint that a hint was shown.

---

## What the client never does

- **Never fetches the payload.** It arrives with the page render.
- **Never evaluates predicates.** Those are server-side closures and never cross the boundary.
- **Never validates selectors ahead of time.** It resolves them at start, drops what is missing, and
  warns.
- **Never starts more than one tour at once.**
