<?php

declare(strict_types=1);

namespace App\Cpu\Objects;

readonly class PayloadWithAdditionalCycle
{
    public function __construct(
        public int $payload,
        public int $additionalCycle,
    ) {}
}
