<?php

declare(strict_types=1);

namespace App\Cartridge;

readonly class Cartridge
{
    /**
     * Creates a new NES cartridge with ROM data and mirroring configuration.
     *
     * @param list<int> $programRom
     * @param list<int> $characterRom
     * @param list<int> $trainer
     */
    public function __construct(
        /** Whether the cartridge uses horizontal nametable mirroring. */
        public bool $isHorizontalMirror,
        /** Program ROM bytes. */
        public array $programRom,
        /** Character ROM bytes, empty for character RAM. */
        public array $characterRom,
        /** Mapper number declared by the cartridge header. */
        public int $mapper = 0,
        /** Mapped program RAM capacity in bytes. */
        public int $programRamSize = 0,
        /** Optional trainer bytes initialised at CPU address $7000. */
        public array $trainer = [],
    ) {}

    /**
     * Gets the size of the character ROM in bytes.
     */
    public function getCharacterRomSize(): int
    {
        return count($this->characterRom);
    }

    /**
     * Gets the size of the program ROM in bytes.
     */
    public function getProgramRomSize(): int
    {
        return count($this->programRom);
    }
}
