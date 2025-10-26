<?php

declare(strict_types=1);

namespace App\Cartridge;

readonly class Cartridge
{
    /**
     * Create a new NES cartridge.
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
     * Get the size of the program ROM.
     */
    public function getCharacterRomSize(): int
    {
        return count($this->characterRom);
    }

    /**
     * Get the size of the character ROM.
     */
    public function getProgramRomSize(): int
    {
        return count($this->programRom);
    }
}
