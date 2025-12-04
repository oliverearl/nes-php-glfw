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
     */
    public function __construct(
        public bool $isHorizontalMirror,
        public array $programRom,
        public array $characterRom,
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
