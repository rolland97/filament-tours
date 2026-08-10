<?php

namespace Rolland\FilamentTours;

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
            $this->tours[$tour->getId()] = $tour;
        }

        return $this;
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
