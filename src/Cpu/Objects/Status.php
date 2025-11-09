<?php

declare(strict_types=1);

namespace App\Cpu\Objects;

class Status
{
    public function __construct(
        public bool $negative,
        public bool $overflow,
        public bool $reserved,
        public bool $breakMode,
        public bool $decimalMode,
        public bool $interrupt,
        public bool $zero,
        public bool $carry
    ) {}
}
