<?php

declare(strict_types=1);

namespace App\Graphics\Objects;

readonly class RenderingData
{
    /**
     * Creates a new rendering data object containing all information needed to render a frame.
     *
     * @param list<int> $palette
     * @param list<Tile>|null $background
     * @param list<Sprite>|null $sprites
     */
    public function __construct(
        public array $palette,
        public ?array $background,
        public ?array $sprites,
    ) {}
}
