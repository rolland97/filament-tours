# Contract: rendered payload (server → browser)

**Feature**: `001-tours-v1`

The only data crossing the server/browser boundary. Emitted once per page render by the
`BODY_END` render hook, consumed by the Alpine component.

---

## Shape

```json
{
  "panel": "admin",
  "debug": false,
  "seenEndpoint": null,
  "tours": [
    {
      "id": "itrf-create",
      "once": true,
      "steps": [
        {
          "selector": "#data\\.title",
          "title": "Title",
          "body": "Name the request.",
          "side": null,
          "align": null
        },
        {
          "selector": "[data-tour=\"items\"]",
          "title": "Items",
          "body": "Add lines here.",
          "side": "left",
          "align": null
        }
      ]
    }
  ]
}
```

| Field | Type | Notes |
|---|---|---|
| `panel` | `string` | Panel id. Scopes the localStorage key: `filament-tours:{panel}:{tour}`. |
| `debug` | `boolean` | Mirrors the application's debug state. Gates **all** console diagnostics — the skipped-step warning (FR-015), unknown-tour-id warnings, and seen-POST failure warnings. The browser cannot read `app.debug` on its own, so the server states it. |
| `seenEndpoint` | `string \| null` | Seen-route URL under a server driver; `null` under browser-local. **Its nullness is the driver signal** — the client needs no separate mode flag. |
| `tours` | `array` | Applicable tours **in registration order** (FR-011). May be empty. |
| `tours[].id` | `string` | Replay handle and localStorage key fragment. |
| `tours[].once` | `boolean` | Persistence only, not a trigger (FR-026). |
| `tours[].steps` | `array` | At least one, ordered. |
| `steps[].selector` | `string` | Raw CSS selector, unvalidated server-side. |
| `steps[].title` / `body` | `string \| null` | **Already escaped** (FR-028). |
| `steps[].side` / `align` | `string \| null` | Pass-through to the tour engine. |

---

## Guarantees

1. **Nothing that does not apply is present.** Non-matching tours are not in `tours`, not in a
   filtered-out list, not anywhere. A user's HTML never contains copy for a page they cannot reach
   (FR-007, SC-002).
2. **Order is meaningful.** `tours[0]` is the first registered applicable tour, and the client
   auto-starts the first *eligible* entry scanning forward (FR-024).
3. **Copy is escaped before it lands here** (FR-028, SC-010). The client renders it as text; it does
   not re-escape and must not un-escape.
4. **No predicates, no page classes.** Predicates are server-side closures. Page classes would tell
   the browser something it already knows and leak class names into HTML for no gain.
5. **An empty `tours` array renders nothing at all** — no component, no engine boot.

---

## Escaping, precisely

Escaping happens **server-side, once**, before serialisation. The client treats `title` and `body`
as text content, never as markup.

This matters because the tour engine's own default is to interpret descriptions as HTML. The
package must not rely on the engine's defaults staying put — it escapes on the way out and renders
as text on the way in, so a change in the engine's default cannot silently open the hole.

> **Security note.** Copy is developer-authored, which makes it *look* trusted. It is not: hosts
> interpolate values into it — a record title, a user's name, a tenant label. Rendering unescaped
> would turn any host-interpolated user data into stored XSS inside an authenticated admin panel.
> There is no raw-markup opt-out in v1 (FR-028), and adding one later is a security decision, not a
> convenience one.

---

## Transport

Serialised into the `BODY_END` hook output as the Alpine component's initial state. Not an
endpoint, not fetched. One render, one payload, no round trip before the tour can start.

---

## Styling — a second asset, not part of this payload

The tour engine ships its own stylesheet, and **an unstyled tour is not a working tour** (SC-004).
Two registered assets are therefore required, not one:

| Asset | Source | Purpose |
|---|---|---|
| `AlpineComponent` | `resources/dist/components/filament-tours.js` | The component and the bundled engine |
| `Css` | `resources/dist/filament-tours.css` | The engine's stylesheet, emitted by the same build |

**The blade template MUST NOT depend on the host's Tailwind build.** If the render-hook markup uses
utility classes, consumers must add a `@source` line to their panel theme to make them survive
purging — which contradicts SC-004's "without touching its panel theme". The markup is a script
tag and a data attribute; all visible styling comes from the engine's own stylesheet.

This is verified during the spike, not assumed: the build emitting a `.css` file and Filament
registering it are both part of proving the asset path works.
