<?php

declare(strict_types=1);

namespace App\Graphics\Objects;

use GL\Math\Vec2;

readonly class Sprite
{
    /**
     * Creates a new Sprite instance.
     *
     * @param list<int[]> $sprite
     */
    public function __construct(
        public array $sprite,
        public Vec2 $coordinates,
        public int $attribute,
        public int $id,
    ) {}
}
