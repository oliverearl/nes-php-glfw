<?php

declare(strict_types=1);

namespace App\Graphics;

use App\Bus\Ram;

class Palette
{
    private readonly Ram $paletteRam;

    public function __construct()
    {
        $this->paletteRam = new Ram(0x20);
    }

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

    public function write(int $addr, int $data): void
    {
        $this->paletteRam->write($this->getPaletteAddress($addr), $data);
    }

    private function getPaletteAddress(int $addr): int
    {
        $mirrorDowned = (($addr & 0xFF) % 0x20);

        return $this->isSpriteMirror($mirrorDowned)
            ? $mirrorDowned - 0x10
            : $mirrorDowned;
    }

    private function isSpriteMirror(int $addr): bool
    {
        return ($addr === 0x10) || ($addr === 0x14) || ($addr === 0x18) || ($addr === 0x1c);
    }

    private function isBackgroundMirror(int $addr): bool
    {
        return ($addr === 0x04) || ($addr === 0x08) || ($addr === 0x0c);
    }
}
