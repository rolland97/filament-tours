<?php

namespace Rolland\FilamentTours;

use InvalidArgumentException;
use Rolland\FilamentTours\Contracts\TourState;

/**
 * Holds a panel's tours and answers "which apply to this page, for this user?".
 *
 * Registration order is meaningful and preserved throughout: it decides which
 * tour auto-starts when several apply to the same page (FR-024).
 */
class TourRegistry
{
    /** @var array<string, Tour> */
    protected array $tours = [];

    public function __construct(protected TourState $state) {}

    public function register(Tour ...$tours): static
    {
        foreach ($tours as $tour) {
            $this->validate($tour);

            $this->tours[$tour->getId()] = $tour;
        }

        return $this;
    }

    /**
     * Fail at registration, not at render.
     *
     * Every message names the offending tour, because a panel provider can hold
     * dozens and "duplicate tour id" on its own just starts a grep. These are
     * the only checks possible without a browser: a selector cannot be verified
     * here, and pretending otherwise would be worse than not trying.
     */
    protected function validate(Tour $tour): void
    {
        $id = $tour->getId();

        if (isset($this->tours[$id])) {
            throw new InvalidArgumentException(
                "Two tours share the id [{$id}]. Tour ids must be unique within a panel.",
            );
        }

        if ($tour->getSteps() === []) {
            throw new InvalidArgumentException(
                "Tour [{$id}] has no steps. A tour with nothing to show cannot run.",
            );
        }

        $pageClass = $tour->getPageClass();

        if ($pageClass !== null && ! class_exists($pageClass)) {
            throw new InvalidArgumentException(
                "Tour [{$id}] targets [{$pageClass}], which does not exist. Check the class name.",
            );
        }
    }

    /**
     * @return list<Tour>
     */
    public function all(): array
    {
        return array_values($this->tours);
    }

    /**
     * Which tours apply to the page currently being rendered.
     *
     * $scopes is Filament's render-hook scope list, normally [TheCurrentPageClass].
     * An empty list means no page-class match is possible; predicate tours can
     * still apply.
     *
     * @param  array<string>  $scopes
     * @return list<Tour>
     */
    public function resolveFor(array $scopes): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Tour $tour): bool => $this->applies($tour, $scopes),
        ));
    }

    /**
     * @param  array<string>  $scopes
     */
    protected function applies(Tour $tour, array $scopes): bool
    {
        $pageClass = $tour->getPageClass();

        if ($pageClass !== null && in_array($pageClass, $scopes, true)) {
            return true;
        }

        $predicate = $tour->getPredicate();

        // A tour with neither a page nor a predicate applies nowhere. That is
        // deliberate: "everywhere" is a predicate the host writes, not a default
        // this package assumes.
        return $predicate !== null && $predicate();
    }
}
