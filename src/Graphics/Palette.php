<?php

declare(strict_types=1);

namespace App\Graphics;

use App\Bus\Ram;

class Palette
{
    /**
     * Internal palette RAM storage.
     */
    private readonly Ram $paletteRam;

    /**
     * Creates a new palette instance.
     */
    public function __construct()
    {
        $this->paletteRam = new Ram(0x20);
    }

    /**
     * Reads the entire palette with mirroring applied.
     *
     * @return list<int>
     */
    public function read(): array
    {
        $return = [];

        foreach ($this->paletteRam->getRam() as $i => $value) {
            if ($this->isSpriteMirror($i)) {
                $return[$i] = $this->paletteRam->read($i - 0x10);
            } elseif ($this->isBackgroundMirror($i)) {
                $return[$i] = $this->paletteRam->read(0x00);
            } else {
                $return[$i] = $value;
            }
        }

        return $return;
    }

    /**
     * Writes a value to the palette at the specified address.
     */
    public function write(int $addr, int $data): void
    {
        $this->paletteRam->write($this->getPaletteAddress($addr), $data);
    }

    /**
     * Calculates the actual palette address accounting for mirroring.
     */
    private function getPaletteAddress(int $addr): int
    {
        $mirrorDowned = (($addr & 0xFF) % 0x20);

        return $this->isSpriteMirror($mirrorDowned)
            ? $mirrorDowned - 0x10
            : $mirrorDowned;
    }

    /**
     * Checks if an address is a sprite palette mirror.
     */
    private function isSpriteMirror(int $addr): bool
    {
        return in_array($addr, [0x10, 0x14, 0x18, 0x1c], true);
    }

    /**
     * Checks if an address is a background palette mirror.
     */
    private function isBackgroundMirror(int $addr): bool
    {
        return in_array($addr, [0x04, 0x08, 0x0c], true);
    }
}
