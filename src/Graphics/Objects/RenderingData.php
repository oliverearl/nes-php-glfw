<?php

declare(strict_types=1);

namespace App\Graphics\Objects;

readonly class RenderingData
{
    /**
     * Creates a new RenderingData instance.
     *
     * @param list<int> $palette
     * @param list<\App\Graphics\Objects\Tile>|null $background
     * @param list<\App\Graphics\Objects\Sprite>|null $sprites
     */
    public function __construct(
        public array $palette,
        public ?array $background,
        public ?array $sprites,
    ) {}
}
