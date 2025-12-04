<?php

declare(strict_types=1);

namespace App\Bus;

readonly class PpuBus
{
    /**
     * Creates a new PPU bus instance.
     */
    public function __construct(public Ram $characterRam) {}

    /**
     * Reads a byte from character RAM via the PPU.
     */
    public function readByPpu(int $addr): int
    {
        return $this->characterRam->read($addr);
    }

    /**
     * Writes a byte to character RAM via the PPU.
     */
    public function writeByPpu(int $addr, int $data): void
    {
        $this->characterRam->write($addr, $data);
    }
}
