<?php

namespace Rolland\FilamentTours;

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

    protected ?string $title = null;

    protected ?string $body = null;

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

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): static
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
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
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
