<?php

declare(strict_types=1);

namespace App\Cpu\Objects;

readonly class PayloadWithAdditionalCycle
{
    /**
     * Creates a new payload with additional cycle information.
     */
    public function __construct(
        public int $payload,
        public int $additionalCycle,
    ) {}
}
