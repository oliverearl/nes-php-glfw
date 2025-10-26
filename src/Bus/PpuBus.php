<?php

declare(strict_types=1);

namespace App\Bus;

readonly class PpuBus
{
    /**
     * Create a new PpuBus instance.
     */
    public function __construct(public Ram $characterRam) {}

    /**
     * Read data by PPU.
     */
    public function readByPpu(int $addr): int
    {
        return $this->characterRam->read($addr);
    }

    /**
     * Write data by PPU.
     */
    public function writeByPpu(int $addr, int $data): void
    {
        $this->characterRam->write($addr, $data);
    }
}
