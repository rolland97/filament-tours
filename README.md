# Guided product tours for Filament panels

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rolland97/filament-tours.svg?style=flat-square)](https://packagist.org/packages/rolland97/filament-tours)
[![Tests](https://img.shields.io/github/actions/workflow/status/rolland97/filament-tours/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rolland97/filament-tours/actions?query=workflow%3Atests+branch%3Amain)
[![Code Style](https://img.shields.io/github/actions/workflow/status/rolland97/filament-tours/fix-code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rolland97/filament-tours/actions?query=workflow%3Afix-code-style+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rolland97/filament-tours.svg?style=flat-square)](https://packagist.org/packages/rolland97/filament-tours)

Point a tour at a page, describe a few elements, and it walks the user through them the first time
they arrive. Powered by [driver.js](https://driverjs.com/), which is bundled — you configure no
npm, no bundler, and no theme.

```php
FilamentToursPlugin::make()->tours([
    Tour::make('create-request')
        ->for(CreateRequest::class)
        ->once()
        ->steps([
            Step::make('#data\\.title')
                ->title('Give it a name')
                ->body('Something your team will recognise in a list.'),

            Step::make('[data-tour="items"]')
                ->title('Add your items')
                ->body('One row per thing you need.')
                ->side('left'),
        ]),
])
```

Tours are declared centrally, on the panel, rather than on the pages they describe. Tour text is
copy, and copy gets rewritten by whoever owns the wording — keeping it in one place makes it
reviewable and translatable, and lets a tour attach to a page you do not own, including Filament's
own dashboard.

## Requirements

- PHP 8.3+
- Filament v5

## Installation

```bash
composer require rolland97/filament-tours
php artisan filament:assets
```

That is the whole installation. `filament:assets` is Filament's own step, and most applications
already run it after `composer update`.

> [!IMPORTANT]
> **Tagged `v1.0.0`, but not submitted to Packagist yet**, so `composer require` does not resolve
> from the default repository. Point Composer at this repository:
>
> ```jsonc
> // composer.json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/rolland97/filament-tours" }
> ]
> ```
>
> ```bash
> composer require rolland97/filament-tours:^1.0
> php artisan filament:assets
> ```
>
> ⚠️ **Prefer the tag to `dev-main`.** Filament versions package assets as `?v={package version}`,
> so a consumer tracking a branch keeps one unchanging version string and updated assets sit behind
> URLs the browser already holds until it revalidates. A real version per release restores that.
>
> The hold before v1.0.0 was deliberate rather than a gap in the work: publishing a version cannot
> be undone, and building a real application against the package first is how we found out whether
> the v1 API survived contact with use. It did not, entirely — four releases' worth of gaps came out
> of that first consumer, and they are in the changelog.

**No custom theme is needed.** driver.js and its stylesheet are bundled into the package and
registered through Filament's asset system, so there is no `@source` line to add and nothing for
your build to compile. A **dark variant** is bundled too, scoped to the `.dark` class Filament
toggles; override `--filament-tours-surface`, `--filament-tours-text` and `--filament-tours-heading`
if your palette differs. If you have seen those instructions in other Filament plugins, they do not
apply here.

**No migration ships with this package**, so there is nothing to publish or run. Where "this user
has seen this tour" is recorded is your decision — see [Remembering what a user has seen](#remembering-what-a-user-has-seen).

Publishing the config file is optional:

```bash
php artisan vendor:publish --tag="filament-tours-config"
```

## Registering tours

Add the plugin to a panel and hand it your tours:

```php
use Filament\Panel;
use Rolland\FilamentTours\FilamentToursPlugin;
use Rolland\FilamentTours\Step;
use Rolland\FilamentTours\Tour;

public function panel(Panel $panel): Panel
{
    return $panel
        // …
        ->plugin(
            FilamentToursPlugin::make()->tours([
                Tour::make('dashboard-intro')
                    ->for(Dashboard::class)
                    ->once()
                    ->steps([
                        Step::make('[data-tour="stats"]')
                            ->title('Your numbers')
                            ->body('Updated hourly.'),
                    ]),
            ]),
        );
}
```

### The full API

That is all of it. Nothing else is public in v1.

**`Tour`**

| Method | Effect |
|---|---|
| `Tour::make(string $id)` | Creates a tour. The id must be unique within the panel. |
| `->for(string $pageClass)` | Applies on that page. Validated at registration — a typo fails loudly. |
| `->when(Closure $predicate)` | Applies when the predicate returns true. Evaluated per request, server-side. |
| `->once()` | Records that the user has seen it, so it does not run again. |
| `->manual()` | Belongs to the page and reaches the browser, but does not start uninvited — only `StartTourAction` or the start event runs it. |
| `->steps(array $steps)` | The steps, in order. At least one is required. |

**`Step`**

| Method | Effect |
|---|---|
| `Step::make(string $selector)` | A raw CSS selector for the element to highlight. |
| `->title(Closure\|string)` | Heading text, or a closure resolved at render. |
| `->body(Closure\|string)` | Body text, or a closure resolved at render. |
| `->side('top'\|'right'\|'bottom'\|'left')` | Which side of the element the popover sits on. |
| `->align('start'\|'center'\|'end')` | How the popover aligns along that side. |

A tour with neither `->for()` nor `->when()` applies nowhere. That is deliberate: "everywhere" is a
predicate you write, not a default this package assumes.

### Tours that wait to be asked

`->manual()` is for a tour describing a page someone came to *use*. An overlay over a form the
moment it opens is in the way of the task — and, on a page with an automated journey, in the way of
the test too. A manual tour still belongs to its page and still reaches the browser; it simply does
not start by itself:

```php
Tour::make('create-request')
    ->for(CreateRequest::class)
    ->manual()
    ->once()
    ->steps([...])
```

Pair it with a replay control (below) so there is something to ask with. `->manual()` and `->once()`
are orthogonal: "has it been seen" and "does it start by itself" are different questions, and a
manual tour that is also `once()` records itself when finished — which stops nothing, because
nothing was starting it.

`php artisan tours:list` reports this as `starts: when asked`, because a manual tour that never
appears looks exactly like a broken selector until you know it was never going to start.

### Targeting elements

Selectors are raw CSS, and the recommended convention is a dedicated attribute:

```blade
<div data-tour="items">…</div>
```

```php
Step::make('[data-tour="items"]')
```

Using `data-tour` rather than a class or a Filament-generated id means your tours survive a
restyle, and this package deliberately ships no helpers that know Filament's internal markup —
they would read better and break on point releases.

### The engine's own buttons

driver.js labels its buttons **Next**, **Previous** and **Done**. If your
application is translated, those three are the only words on the popover this
package cannot see — so hand them over:

```php
FilamentToursPlugin::make()
    ->buttonLabels([
        'next' => fn (): string => __('tours.next'),
        'previous' => fn (): string => __('tours.previous'),
        'done' => fn (): string => __('tours.done'),
    ])
    ->tours([...])
```

> [!IMPORTANT]
> **Pass closures if you translate.** A panel is configured *before* any request
> middleware runs, so `__('tours.next')` evaluated there resolves in your
> application's default locale and stays there — every reader sees that one
> language. A closure is resolved when the payload is built, by which time the
> request's locale is set. Plain strings are fine for wording that never varies.

Keys you leave out keep driver's own defaults. This package ships no wording.

### Copy and translation

Copy is plain strings, so translate it however your application already does:

```php
Step::make('#data\\.title')
    ->title(fn (): string => __('tours.request.title.heading'))
    ->body(fn (): string => __('tours.request.title.body'))
```

> [!IMPORTANT]
> **Pass closures if you translate.** A panel is configured *before* any request
> middleware runs, so `__('tours.request.title.heading')` evaluated there resolves in your
> application's default locale and stays there — every reader sees that one language,
> whatever their preference. A closure is resolved when the payload is built, by which
> point the request's locale is set. Plain strings are right for wording that never varies.

No language files ship with this package, and it will never own your wording.

## Accessibility

The tour engine marks the element it highlights with `aria-haspopup`, `aria-expanded` and
`aria-controls`. Those attributes are only valid on certain roles, so this package removes them
again from elements that cannot carry them — otherwise every page with a running tour fails an
`aria-allowed-attr` audit, and a highlighted `<div>` is the ordinary case rather than the exception.

## When a tour cannot find its target

A step whose selector matches nothing is **skipped**, and the rest of the tour still runs. Users
never meet a broken tour because someone moved a button.

Developers find out instead: with `APP_DEBUG=true`, each skipped step logs a console warning naming
the tour and the selector that missed. If no step survives, the tour does not start at all.

To see what is registered:

```bash
php artisan tours:list
```

It reports every tour with its page or predicate, step count, and run-once flag. It does **not**
check whether your selectors match anything — that needs a browser, and a listing that implied
otherwise would be lying.

## Letting users replay a tour

Put the action in a page header:

```php
use Rolland\FilamentTours\Actions\StartTourAction;

protected function getHeaderActions(): array
{
    return [
        StartTourAction::make('dashboard-intro')->label('Show me around'),
    ];
}
```

Or dispatch the event from anywhere on the page:

```blade
<button x-on:click="$dispatch('filament-tours:start', { tour: 'dashboard-intro' })">
    Replay the tour
</button>
```

Replay ignores whether the tour has been seen — that is the point of asking for it — and finishing
a replay does not re-record it. Requesting a tour that does not apply to the current page does
nothing and shows the user no error.

## Remembering what a user has seen

Only tours marked `->once()` need this. Two options.

### The default: the browser remembers

Out of the box, `config('filament-tours.state')` is `'local'` and the browser records what it has
seen in `localStorage`, under `filament-tours:{panel}:{tour}`. Nothing is stored on your server and
**no route is registered**.

> ⚠️ This is per-browser. The same person on a second device, or after clearing site data, sees the
> tour again. That is fine for hints and onboarding nudges. It is **not** suitable for anything
> shaped like "confirm you have read this" — for that, use a server-side driver.

### Server-side: you remember

Implement the contract and point the config at it. Tour definitions do not change.

```php
use Rolland\FilamentTours\Contracts\TourState;

class DatabaseTourState implements TourState
{
    public function hasSeen(string $tourId): bool
    {
        return auth()->user()->seen_tours[$tourId] ?? false;
    }

    public function markSeen(string $tourId): void
    {
        $user = auth()->user();
        $user->seen_tours = [...$user->seen_tours, $tourId => true];
        $user->save();
    }
}
```

```php
// config/filament-tours.php
'state' => \App\Support\DatabaseTourState::class,
```

Or leave the config alone and set it per environment:

```dotenv
FILAMENT_TOURS_STATE="App\Support\DatabaseTourState"
```

Which user "seen" refers to is your driver's business — it reads `auth()->user()` itself, so this
package never forms an opinion about identity.

With a driver configured, tours you report as seen are filtered out **before the page renders**, so
their text never reaches the browser at all. One route is registered,
`POST filament-tours/{tour}/seen`, inside the panel's own middleware group, and it only records —
there is no endpoint to read seen state back.

> ⚠️ **The route inherits your panel's authentication, and only that.** Filament does not apply auth
> middleware on its own; your panel declares it, as generated panels do with
> `->authMiddleware([Authenticate::class])`. A panel that declares none leaves this endpoint open —
> along with every page in that panel. If you are switching to a server-side driver, it is worth
> confirming your panel's middleware is what you think it is.

If the browser cannot reach the endpoint — offline, a 500 — the tour is suppressed for the rest of
that page visit and may run once more next time. Failing that way round is deliberate: a tour
appearing twice is visible and self-correcting, whereas silently retiring one after a network blip
is not.

## What this package deliberately does not do

- **Drive interactions.** v1 highlights elements that are on the page when it loads. It does not
  open modals or switch tabs.
- **Span multiple pages.** One tour, one page.
- **Ship a migration, a language file, or a tour-authoring UI.**
- **Collect analytics.**

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Rolland Son](https://github.com/rolland97)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
