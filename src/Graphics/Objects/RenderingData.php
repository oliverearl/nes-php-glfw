<?php

declare(strict_types=1);

namespace App\Graphics\Objects;

readonly class RenderingData
{
    /**
     * Creates a new RenderingData instance.
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
