<?php

declare(strict_types=1);

namespace App\Graphics\Objects;

readonly class RenderingData
{
    /**
     * Creates a new RenderingData instance.
     *
     * @param list<int> $palette
     * @param list<\App\Graphics\Objects\Tile> $background
     * @param list<\App\Graphics\Objects\Sprite> $sprites
     */
    public function __construct(
        public array $palette,
        public array $background,
        public array $sprites,
    ) {}
}
