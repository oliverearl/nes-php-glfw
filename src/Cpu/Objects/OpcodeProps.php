<?php

declare(strict_types=1);

namespace App\Cpu\Objects;

use App\Cpu\Enums\Addressing;

readonly class OpcodeProps
{
    /**
     * Creates a new opcode property set.
     */
    public function __construct(
        public string $baseName,
        public Addressing $mode,
        public int $cycle,
    ) {}
}
