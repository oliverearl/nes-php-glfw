<?php

declare(strict_types=1);

namespace App\Bus;

readonly class PpuBus
{
    /**
     * Creates a new PPU bus instance.
     */
    public function __construct(
        /**
         * Cartridge pattern memory backing CHR-ROM or CHR-RAM.
         */
        public Ram $characterRam,
        /**
         * Prevents PPU writes when the cartridge supplies CHR-ROM.
         */
        private bool $readOnly = false,
    ) {}

    /**
     * Reads a byte from character RAM via the PPU.
     */
    public function readByPpu(int $addr): int
    {
        return $this->characterRam->read($addr);
    }

    /**
     * Writes cartridge character RAM and ignores writes to character ROM.
     */
    public function writeByPpu(int $addr, int $data): void
    {
        if (! $this->readOnly) {
            $this->characterRam->write($addr, $data);
        }
    }
}
