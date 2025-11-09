<?php

declare(strict_types=1);

namespace App\Cpu\Objects;

use App\Cpu\Enums\Addressing;

readonly class OpcodeProps
{
    public function __construct(
        public string $baseName,
        public Addressing $mode,
        public int $cycle,
    ) {}
}
