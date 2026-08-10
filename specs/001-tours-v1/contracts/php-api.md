# Contract: PHP public API

**Feature**: `001-tours-v1` · Frozen for v1 by design §4 and AGENTS.md **R-020**.

This is the entire surface a host application may depend on. Anything not listed here is internal
and may change without a major version. Adding to this list requires a design amendment, not a
commit.

---

## Definition

```php
namespace Rolland\FilamentTours;

final class Tour
{
    public static function make(string $id): static;

    /** @param class-string $pageClass */
    public function for(string $pageClass): static;

    /** @param Closure(): bool $predicate */
    public function when(Closure $predicate): static;

    public function once(bool $condition = true): static;

    /** @param list<Step> $steps */
    public function steps(array $steps): static;
}

final class Step
{
    public static function make(string $selector): static;

    public function title(string $title): static;

    public function body(string $body): static;

    /** @param 'top'|'right'|'bottom'|'left' $side */
    public function side(string $side): static;

    /** @param 'start'|'center'|'end' $align */
    public function align(string $align): static;
}
```

**Throws** `InvalidArgumentException`, with a message naming the offending tour, when: an id is
duplicated within a panel, a tour has zero steps, or `for()` names a class that does not exist
(FR-017, SC-007). Registration is where these fire — not construction — because uniqueness is only
knowable once a tour joins a panel.

---

## Registration

```php
namespace Rolland\FilamentTours;

final class FilamentToursPlugin implements \Filament\Contracts\Plugin
{
    public static function make(): static;

    public static function get(): static;

    /** @param list<Tour> $tours */
    public function tours(array $tours): static;

    public function getId(): string;              // 'filament-tours'
    public function register(\Filament\Panel $panel): void;
    public function boot(\Filament\Panel $panel): void;
}
```

Usage, from a panel provider:

```php
$panel->plugin(
    FilamentToursPlugin::make()->tours([
        Tour::make('itrf-create')
            ->for(CreateItrf::class)
            ->once()
            ->steps([
                Step::make('#data\\.title')->title('Title')->body('Name the request.'),
                Step::make('[data-tour="items"]')->title('Items')->body('Add lines here.')->side('left'),
            ]),
    ]),
);
```

`register()` binds the registry and adds the `BODY_END` render hook. `boot()` is where the seen
route is registered, and **only** when a server-side state driver is configured (FR-021).

---

## Replay

```php
namespace Rolland\FilamentTours\Actions;

final class StartTourAction extends \Filament\Actions\Action
{
    public static function make(?string $name = null): static;
}
```

`StartTourAction::make('itrf-create')` — the tour id is the action name. Placed by the host in a
page header or toolbar. Dispatches the same browser event described in
[`js-events.md`](./js-events.md).

---

## State driver

```php
namespace Rolland\FilamentTours\Contracts;

interface TourState
{
    public function hasSeen(string $tourId): bool;

    public function markSeen(string $tourId): void;
}
```

A host implements this and points `config('filament-tours.state')` at the class-string. The
package resolves it from the container, so constructor injection works.

**The contract is deliberately per-tour and stateless about users.** Which user "seen" refers to is
the implementation's business — it can read `auth()->user()` itself. Passing a user in would force
an opinion about identity that the package has no right to hold.

---

## Configuration

```php
// config/filament-tours.php
return [
    'state' => 'local',
];
```

| Value | Meaning |
|---|---|
| `'local'` | Browser-local. No server state, **no route registered** (FR-020). |
| A class-string implementing `TourState` | Server-side. Registry filters seen tours before render; one route registered behind panel auth (FR-021). |

Any other value is a configuration error and should fail loudly at boot rather than silently
falling back to `'local'` — a host that typed its driver class wrong must not quietly get
browser-local persistence.

---

## Console

```
php artisan tours:list [--panel=]
```

Every registered tour: id, page class or `when()`, step count, `once` (FR-018, SC-006).
`--panel` defaults to the panel Filament reports as default.

**Not rendered as a table.** Symfony wraps table cells to the terminal width, which splits a
fully-qualified page class across lines and makes it impossible to copy or grep — the one thing a
developer reading this list wants. One contiguous line per field instead.

**Does not validate selectors** and must not imply it does. A selector cannot be checked without a
browser; an honest listing beats a validator that overpromises (design §7).

---

## Addition to the frozen surface

`FilamentToursPlugin::registryKey(string $panelId): string` — the container key under which a
panel's `TourRegistry` is bound.

**This extends the v1 surface** that AGENTS.md **R-020** freezes, so it is recorded here rather
than left implicit. It exists because `tours:list` must reach a panel's registry from the console,
and every alternative was worse: exposing the tours array widens the definition API that hosts
actually use, and binding `TourRegistry::class` unqualified would silently collide the moment an
application registers the plugin on a second panel.

Hosts have no reason to call it. It is public because the command is a separate class, not because
it is offered as a feature.
