<?php

namespace Rolland\FilamentTours;

use Closure;
use InvalidArgumentException;

/**
 * A named, ordered walkthrough of one page.
 *
 * Holds definition only. Whether it applies to the current request is the
 * registry's question, and whether it has been seen is the state driver's —
 * a Tour knows neither.
 *
 * Note that `->for()` does not check the class exists. That check needs the
 * tour's id to name the offender usefully, and the id is only meaningful once
 * the tour joins a panel, so it lives in TourRegistry::register() instead.
 */
final class Tour
{
    protected ?string $pageClass = null;

    protected ?Closure $predicate = null;

    protected bool $once = false;

    /** @var list<Step> */
    protected array $steps = [];

    protected function __construct(protected string $id) {}

    public static function make(string $id): static
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A tour needs an id, but was given an empty one.');
        }

        return new self($id);
    }

    /**
     * @param  class-string  $pageClass
     */
    public function for(string $pageClass): static
    {
        $this->pageClass = $pageClass;

        return $this;
    }

    /**
     * @param  Closure(): bool  $predicate
     */
    public function when(Closure $predicate): static
    {
        $this->predicate = $predicate;

        return $this;
    }

    public function once(bool $condition = true): static
    {
        $this->once = $condition;

        return $this;
    }

    /**
     * Typed as mixed on purpose: a docblock is not enforcement, and a host
     * passing the wrong thing should get a message naming the tour rather than
     * a TypeError from somewhere deeper.
     *
     * @param  array<int, mixed>  $steps
     */
    public function steps(array $steps): static
    {
        $validated = [];

        foreach ($steps as $step) {
            if (! $step instanceof Step) {
                throw new InvalidArgumentException(sprintf(
                    'Tour [%s] was given something that is not a Step: [%s].',
                    $this->id,
                    get_debug_type($step),
                ));
            }

            $validated[] = $step;
        }

        $this->steps = $validated;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return class-string|null
     */
    public function getPageClass(): ?string
    {
        return $this->pageClass;
    }

    /**
     * @return Closure(): bool|null
     */
    public function getPredicate(): ?Closure
    {
        return $this->predicate;
    }

    public function isOnce(): bool
    {
        return $this->once;
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
