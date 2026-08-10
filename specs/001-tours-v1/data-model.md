# Phase 1 Data Model: Guided Product Tours for Filament Panels (v1)

**Feature**: `001-tours-v1` · **Date**: 2026-08-10 · **Spec**: [spec.md](./spec.md)

No database tables. The package ships no migration (design §1, AGENTS.md **R-017**), so everything
here is an in-memory value object, a registry, or a serialised payload. "Persistence" means
whatever the host's state driver does, which this package does not model.

---

## `Step`

An immutable value object. One stop in a tour.

| Field | Type | Required | Default | Notes |
|---|---|---|---|---|
| `selector` | `string` | yes | — | Raw CSS selector. Never validated server-side — a selector cannot be checked without a browser (design §7). |
| `title` | `?string` | no | `null` | Heading copy. Escaped on render (FR-028). |
| `body` | `?string` | no | `null` | Body copy. Escaped on render (FR-028). |
| `side` | `?string` | no | `null` | One of `top`, `right`, `bottom`, `left`. Passes through to the tour engine untouched. |
| `align` | `?string` | no | `null` | One of `start`, `center`, `end`. Passes through untouched. |

**Validation** (at construction, throwing `InvalidArgumentException`):

- `selector` is non-empty after trimming.
- `side`, when given, is one of the four permitted values.
- `align`, when given, is one of the three permitted values.

**Explicitly not validated**: whether the selector matches anything. That is the browser's job, and
claiming otherwise would be the "validator that implies more than it checks" the design rejects.

**Fluent surface** (frozen — R-020): `Step::make(string $selector)`, `->title()`, `->body()`,
`->side()`, `->align()`.

---

## `Tour`

An immutable value object. A named, ordered walkthrough.

| Field | Type | Required | Default | Notes |
|---|---|---|---|---|
| `id` | `string` | yes | — | Unique within a panel. Also the localStorage key fragment and the replay handle. |
| `pageClass` | `?class-string` | no | `null` | Set by `->for()`. Validated to exist at registration. |
| `predicate` | `?Closure(): bool` | no | `null` | Set by `->when()`. Evaluated server-side, per request. |
| `once` | `bool` | no | `false` | Persistence only, **not** a trigger flag (FR-026). |
| `steps` | `list<Step>` | yes | — | At least one. Order is meaningful. |

**Validation** (at registration, throwing `InvalidArgumentException` naming the tour — FR-017):

| Rule | Message must name |
|---|---|
| `id` is non-empty and unique within the panel | the duplicated id |
| `steps` is non-empty | the tour id |
| `pageClass`, when given, resolves via `class_exists()` | the tour id and the missing class |

**Fluent surface** (frozen — R-020): `Tour::make(string $id)`, `->for()`, `->when()`, `->once()`,
`->steps()`.

**Relationships**: a `Tour` holds many `Step`s by composition. Nothing holds a `Tour` except the
registry.

**A note on `id`**: it travels to the browser and becomes part of a localStorage key
(`filament-tours:{panel}:{tour}` — design §5) and part of the seen-route URL. Treat it as a
URL-safe slug; a tour id with a slash or a space is a defect waiting to happen. Not enforced in
v1 — flagged so the tasks phase can decide whether a slug check is worth one line.

---

## `TourRegistry`

Holds a panel's tours; answers *"which apply to this page, for this user?"*. One instance per
panel, bound as a singleton.

**State**: `array<string, Tour>` keyed by id, preserving insertion order. Insertion order **is**
registration order and therefore decides auto-start priority (FR-011, FR-024).

**Operations**:

| Operation | Behaviour |
|---|---|
| `register(Tour ...$tours)` | Validates per the table above. Throws on the first offender. |
| `all(): list<Tour>` | Registration order. Feeds `tours:list` (FR-018). |
| `resolveFor(array $scopes): list<Tour>` | The request-time query. See below. |

**`resolveFor` semantics** — order matters and is testable:

1. Start from all registered tours, in registration order.
2. Keep a tour if **either** its `pageClass` appears in `$scopes` **or** its `predicate` returns
   true. A tour with neither never applies. A tour with both applies if either matches.
3. If the configured state driver is server-side, drop tours where `once === true` and
   `hasSeen($id) === true` (FR-008). Under the browser-local driver, drop nothing — the browser
   decides.
4. Return what remains, still in registration order.

`$scopes` is the render-hook scope array from Filament, normally `[TheCurrentPageClass]`
(research R2). An empty array means no page-class match is possible; predicate tours still apply.

**Invariant worth a test**: a tour that fails step 2 is not merely hidden — it is never serialised,
so its copy cannot appear in the response (FR-007, SC-002).

---

## `TourState` (contract) and its implementations

```php
interface TourState
{
    public function hasSeen(string $tourId): bool;

    public function markSeen(string $tourId): void;
}
```

Selected by `config('filament-tours.state')`: the string `'local'` (default) or a host class-string.

| Implementation | `hasSeen` | `markSeen` | Registers a route? |
|---|---|---|---|
| Browser-local (default) | Always `false` — the server does not know | No-op | **No** (FR-020) |
| Host class-string | Whatever the host says | Whatever the host does | **Yes**, exactly one (FR-021) |

The browser-local implementation answering `false` unconditionally is deliberate, not a stub: under
that driver the *browser* holds the answer, so the server filtering on it would be a lie. The
`once` flag rides along in the payload and localStorage decides.

---

## Rendered payload (server → browser)

The only thing that crosses the boundary. Shape is a contract — see
[`contracts/payload.md`](./contracts/payload.md).

| Field | Type | Notes |
|---|---|---|
| `panel` | `string` | Panel id, for localStorage key scoping. |
| `debug` | `bool` | Mirrors the application's debug state; gates every console diagnostic (FR-015). The browser cannot read `app.debug` itself, so the server states it. |
| `tours` | `list<PayloadTour>` | **Registration order preserved** (FR-011). Empty list is valid and means "render nothing". |
| `seenEndpoint` | `?string` | The seen-route URL, or `null` under the browser-local driver. Its nullness *is* how the client knows which driver is active. |

`PayloadTour`: `{ id: string, once: bool, steps: list<PayloadStep> }`
`PayloadStep`: `{ selector: string, title: ?string, body: ?string, side: ?string, align: ?string }`

**Never in the payload**: predicates (server-side closures, not serialisable and not the browser's
business), page classes (the browser already is on the page), or any tour that did not apply.

---

## State transitions

A tour has one meaningful lifecycle, and only when `once` is set:

```
unseen ──auto-start──> running ──finish or dismiss──> seen ──replay──> running
                          │                                              │
                          └──────── navigate away (destroyed) ───────────┘
```

- **unseen → running**: only for the first *eligible* tour on the page (FR-024, FR-027).
- **running → seen**: on finish **or** dismiss. Both count; a dismissed tour is not shown again.
- **running → destroyed**: on `livewire:navigating` (FR-012). Does **not** mark seen — the user
  neither finished nor dismissed it, they left.
- **seen → running**: replay only (FR-025, User Story 3). Replay does **not** re-mark seen; it is
  already seen and marking again is a wasted write.

A tour without `once` has no persisted state at all: it auto-starts every visit and the "seen"
column of this diagram never applies to it.
