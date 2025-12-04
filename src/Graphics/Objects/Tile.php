<?php

declare(strict_types=1);

namespace App\Graphics\Objects;

readonly class Tile
{
    /**
     * Creates a new background tile with pattern data and scroll information.
     *
     * @param list<int[]> $pattern
     */
    public function __construct(
        public array $pattern,
        public int $paletteId,
        public int $scrollX,
        public int $scrollY,
    ) {}
}
