<?php

namespace Rolland\FilamentTours;

use Closure;
use InvalidArgumentException;

/**
 * One stop in a tour.
 *
 * Deliberately dumb: it holds a selector and some copy, validates what it can,
 * and has no behaviour. Side and alignment pass straight through to the tour
 * engine, which is why their permitted values live here as plain strings
 * rather than as an enum the host would have to import.
 */
final class Step
{
    public const SIDES = ['top', 'right', 'bottom', 'left'];

    public const ALIGNMENTS = ['start', 'center', 'end'];

    /** @var (Closure(): string)|string|null */
    protected Closure | string | null $title = null;

    /** @var (Closure(): string)|string|null */
    protected Closure | string | null $body = null;

    protected ?string $side = null;

    protected ?string $align = null;

    protected function __construct(protected string $selector) {}

    public static function make(string $selector): static
    {
        if (trim($selector) === '') {
            throw new InvalidArgumentException('A tour step needs a selector, but was given an empty one.');
        }

        return new self($selector);
    }

    /**
     * The heading, as a string or as a closure resolved when the step is read.
     *
     * 🚨 **Pass a closure if you translate.** A Filament panel is configured
     * before any request middleware runs, so `__('...')` evaluated at
     * declaration resolves in the application's default locale and stays there —
     * every reader sees that one language whatever their preference. A closure
     * is resolved when the payload is built, by which point the request's locale
     * is set. Plain strings remain right for wording that never varies.
     *
     * @param  (Closure(): string)|string  $title
     */
    public function title(Closure | string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The body copy. Takes a closure for the same reason as {@see title()}.
     *
     * @param  (Closure(): string)|string  $body
     */
    public function body(Closure | string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function side(string $side): static
    {
        if (! in_array($side, static::SIDES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Step side must be one of [%s], got [%s].',
                implode(', ', static::SIDES),
                $side,
            ));
        }

        $this->side = $side;

        return $this;
    }

    public function align(string $align): static
    {
        if (! in_array($align, static::ALIGNMENTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Step alignment must be one of [%s], got [%s].',
                implode(', ', static::ALIGNMENTS),
                $align,
            ));
        }

        $this->align = $align;

        return $this;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getTitle(): ?string
    {
        return $this->resolve($this->title);
    }

    public function getBody(): ?string
    {
        return $this->resolve($this->body);
    }

    /**
     * @param  (Closure(): string)|string|null  $copy
     */
    protected function resolve(Closure | string | null $copy): ?string
    {
        return $copy instanceof Closure ? $copy() : $copy;
    }

    public function getSide(): ?string
    {
        return $this->side;
    }

    public function getAlign(): ?string
    {
        return $this->align;
    }
}
