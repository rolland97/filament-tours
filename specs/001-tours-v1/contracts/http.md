# Contract: HTTP route

**Feature**: `001-tours-v1`

The package registers **exactly one** route, and **only** when a server-side state driver is
configured. Under the default browser-local driver it registers none (FR-020, FR-021, design §5).

---

## `POST filament-tours/{tour}/seen`

| Aspect | Value |
|---|---|
| Method | `POST` |
| Path | `filament-tours/{tour}/seen`, under the panel's path prefix |
| Route name | Declared as `filament-tours.seen`; **resolves as `filament.{panelId}.filament-tours.seen`** because Filament prefixes panel route names with both `filament.` and the panel id |
| Middleware | **The panel's own authentication middleware stack** — registered via `Panel::authenticatedRoutes()`, not `Panel::routes()`, which would publish this write endpoint unauthenticated |
| Request body | None |
| Success | `204 No Content` |
| Unknown tour id | `404`, and **no** call to the driver |
| Unauthenticated | Whatever the panel's middleware already does (redirect or `401`) — the package adds no behaviour of its own |

**Handler**: resolves the configured `TourState` implementation and calls `markSeen($tourId)`.
Nothing else. It does not decide *who* has seen it — the driver reads `auth()->user()` itself if
identity matters to it.

---

## Security posture

> Stated plainly rather than tersely, because this is the package's only write endpoint.

- **Authentication is inherited, never reimplemented.** The route sits behind the panel's existing
  middleware. The package adds no auth of its own and must not weaken what the panel applies.

  ⚠️ **Inherited means inherited, including when there is nothing to inherit.** Filament does not
  apply auth middleware on its own — a panel declares it (`->authMiddleware([Authenticate::class])`,
  which every generated panel has). A panel that declares none protects nothing, and this endpoint
  is then no more exposed than the panel's own pages, which are also public. That is the host's
  configuration, not this package's to override. It was found the honest way: the test panel
  declared no auth, an anonymous POST returned 204, and the assertion failed until the harness was
  made realistic.
- **CSRF applies**, as it does to any stateful POST in a Laravel web middleware group. The client
  sends the token the panel already exposes.
- **The `{tour}` segment is validated against the registry before use.** An id that names no
  registered tour is a `404` and never reaches the driver — the segment must not be treated as a
  free-form key that a caller can write arbitrary values into.
- **The route exists only under a server driver.** Under browser-local there is nothing to write
  server-side, so there is no endpoint to attack. Verified by a test that inspects the route list
  under the default config (FR-020, User Story 4 scenario 3).
- **The endpoint records; it never reads.** There is no `GET` counterpart. "Has this user seen it"
  is answered during render, server-side, and never exposed as a queryable endpoint.

**Worst case if abused**: an authenticated user marks their own tour seen early, and stops seeing
their own onboarding tour. No other user's state is reachable, since the driver scopes by the
authenticated identity. That is a nuisance, not a vulnerability — and it is exactly why D2 routes
compliance-shaped requirements away from this mechanism.

---

## Failure behaviour (client side)

Decided in [research R6](../research.md). When the POST fails — offline, 500, network error:

- The client suppresses the tour for the **rest of the page session only**, in memory.
- It does **not** retry.
- It logs a console warning under `app.debug`.
- On the next page load the server is authoritative: if it still reports the tour unseen, the tour
  runs again.

Failing open (tour may replay once) is preferred over failing closed (tour silently never appears
again after one transient blip). The replay is visible and self-correcting; the silence is not.
