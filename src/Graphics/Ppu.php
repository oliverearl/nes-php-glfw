<?php

declare(strict_types=1);

namespace App\Graphics;

use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Cpu\Interrupts;
use App\Graphics\Objects\RenderingData;
use App\Graphics\Objects\Sprite;
use App\Graphics\Objects\Tile;
use GL\Math\Vec2;

class Ppu
{
    public const int SPRITE_NUMBER = 0x100;

    /** @var list<int> */
    private array $registers = [];

    private int $cycle = 0;

    private int $scanline = 0;

    private bool $isLowerVramAddress = false;

    private int $spriteRamAddress = 0;

    private int $vramAddress = 0x0000;

    private Ram $vram;

    private int $vramReadBuffer = 0;

    private Ram $spriteRam;

    /** @var list<int> */
    private array $background = [];

    /** @var list<\App\Graphics\Objects\Sprite> */
    private array $sprites = [];

    private Palette $palette;

    private bool $isHorizontalScroll = true;

    private int $scrollX = 0;

    private int $scrollY = 0;

    /**
     * Creates a new PPU instance.
     */
    public function __construct(private PpuBus $bus, private Interrupts $interrupts, private bool $isHorizontalMirror)
    {
        $this->registers = array_fill(0, 8, 0);
        $this->vram = new Ram(0x2000);
        $this->spriteRam = new Ram(0x100);
        $this->palette = new Palette();
    }

    /**
     * Executes a single PPU cycle.
     */
    public function run(int $cycle): false|RenderingData
    {
        $this->cycle += $cycle;

        if ($this->scanline === 0) {
            $this->background = [];
            $this->buildSprites();
        }

        if ($this->cycle >= 341) {
            $this->cycle -= 341;
            $this->scanline++;

            if ($this->hasSpriteHit()) {
                $this->setSpriteHit();
            }

            if ($this->scanline <= 240 && $this->scanline % 8 === 0 && $this->scrollY <= 240) {
                $this->buildBackground();
            }

            if ($this->scanline === 241) {
                $this->setVblank();

                if ($this->hasVblankIrqEnabled()) {
                    $this->interrupts->assertNmi();
                }
            }

            if ($this->scanline === 262) {
                $this->clearVblank();
                $this->clearSpriteHit();
                $this->scanline = 0;
                $this->interrupts->deassertNmi();

                return new RenderingData($this->getPalette(), $this->isBackgroundEnabled() ? $this->background : null, $this->isSpriteEnabled() ? $this->sprites : null, );
            }
        }

        return false;
    }

    public function read(int $address): int
    {
        if ($address === 0x0002) {
            $this->isHorizontalScroll = true;
            $data = $this->registers[0x02];
            $this->clearVblank();

            return $data;
        }

        if ($address === 0x0004) {
            return $this->spriteRam->read($this->spriteRamAddress);
        }

        if ($address === 0x0007) {
            return $this->readVram();
        }

        return 0;
    }

    public function write(int $address, int $data): void
    {
        if ($address === 0x0003) {
            $this->writeSpriteRamAddress($data);
        }

        if ($address === 0x0004) {
            $this->writeSpriteRamData($data);
        }

        if ($address === 0x0005) {
            $this->writeScrollData($data);
        }

        if ($address === 0x0006) {
            $this->writeVramAddress($data);
        }

        if ($address === 0x0007) {
            $this->writeVramData($data);
        }

        $this->registers[$address] = $data;
    }

    public function transferSprite(int $index, int $data): void
    {
        $address = $index + $this->spriteRamAddress;

        $this->spriteRam->write($address % 0x100, $data);
    }

    private function buildSprites(): void
    {
        $offset = ($this->registers[0] & 0x08) ? 0x1000 : 0x0000;

        for ($i = 0; $i < self::SPRITE_NUMBER; $i = ($i + 4) | 0) {

            $y = $this->spriteRam->read($i) - 8;

            if ($y < 0) {
                return;
            }

            $spriteId = $this->spriteRam->read($i + 1);
            $attr = $this->spriteRam->read($i + 2);
            $x = $this->spriteRam->read($i + 3);
            $sprite = $this->buildSprite($spriteId, $offset);

            $this->sprites[$i / 4] = new Sprite($sprite, new Vec2($x, $y), $attr, $spriteId);
        }
    }

    private function clearVblank(): void
    {
        $this->registers[0x02] &= 0x7F;
    }

    private function readVram(): int
    {
        $buf = $this->vramReadBuffer;

        if ($this->vramAddress >= 0x2000) {
            $address = $this->calculateVramAddress();
            $this->vramAddress += $this->vramOffset();

            if ($address >= 0x3F00) {
                return $this->vram->read($address);
            }

            $this->vramReadBuffer = $this->vram->read($address);
        } else {
            $this->vramReadBuffer = $this->readCharacterRam($this->vramAddress);
            $this->vramAddress += $this->vramOffset();
        }
        return $buf;
    }

    private function calculateVramAddress(): int
    {
        return ($this->vramAddress >= 0x3000 && $this->vramAddress < 0x3f00) ? $this->vramAddress -= 0x3000 : $this->vramAddress - 0x2000;
    }

    private function vramOffset(): int
    {
        return ($this->registers[0x00] & 0x04) ? 32 : 1;
    }

    private function readCharacterRam(int $address): int
    {
        return $this->bus->readByPpu($address);
    }

    /** @return list<list<int>> */
    private function buildSprite(int $spriteId, int $offset): array
    {
        $sprite = array_fill(0, 8, array_fill(0, 8, 0));

        for ($i = 0; $i < 16; $i = ($i + 1) | 0) {
            for ($j = 0; $j < 8; $j = ($j + 1) | 0) {
                $address = $spriteId * 16 + $i + $offset;
                $ram = $this->readCharacterRam($address);

                if ($ram & (0x80 >> $j)) {
                    $sprite[$i % 8][$j] += 0x01 << (int) floor($i / 8);
                }
            }
        }

        return $sprite;
    }

    private function hasSpriteHit(): bool
    {
        $y = $this->spriteRam->read(0);

        return ($y === $this->scanline) && $this->isBackgroundEnabled() && $this->isSpriteEnabled();
    }

    private function isBackgroundEnabled(): bool
    {
        return (bool) ($this->registers[0x01] & 0x08);
    }

    private function isSpriteEnabled(): bool
    {
        return (bool) ($this->registers[0x01] & 0x10);
    }

    private function setSpriteHit(): void
    {
        $this->registers[0x02] |= 0x40;
    }

    private function buildBackground(): void
    {
        $clampedTileY = $this->tileY() % 30;
        $tableIdOffset = ((int) floor($this->tileY() / 30) % 2) ? 2 : 0;

        for ($x = 0; $x < 32 + 1; $x = ($x + 1) | 0) {
            $tileX = ($x + $this->scrollTileX());
            $clampedTileX = $tileX % 32;
            $nameTableId = ((int) floor($tileX / 32) % 2) + $tableIdOffset;
            $offsetAddrByNameTable = $nameTableId * 0x400;

            $tile = $this->buildTile($clampedTileX, $clampedTileY, $offsetAddrByNameTable);

            $this->background[] = $tile;
        }
    }

    private function tileY(): int
    {
        return (int) floor($this->scanline / 8) + $this->scrollTileY();
    }

    private function scrollTileY(): int
    {
        return (int) floor(($this->scrollY + ((int) floor($this->nameTableId() / 2) * 240)) / 8);
    }

    private function nameTableId(): int
    {
        return $this->registers[0x00] & 0x03;
    }

    private function scrollTileX(): int
    {
        return (int) floor(($this->scrollX + (($this->nameTableId() % 2) * 256)) / 8);
    }

    private function buildTile(int $tileX, int $tileY, int $offset): Tile
    {
        $blockId = $this->getBlockId($tileX, $tileY);
        $spriteId = $this->getSpriteId($tileX, $tileY, $offset);
        $attr = $this->getAttribute($tileX, $tileY, $offset);
        $paletteId = ($attr >> ($blockId * 2)) & 0x03;
        $sprite = $this->buildSprite($spriteId, $this->backgroundTableOffset());

        return new Tile($sprite, $paletteId, $this->scrollX, $this->scrollY, );
    }

    private function getBlockId(int $tileX, int $tileY): int
    {
        return (int) floor(($tileX % 4) / 2) + ((int) floor(($tileY % 4) / 2)) * 2;
    }

    private function getSpriteId(int $tileX, int $tileY, int $offset): int
    {
        $tileNumber = $tileY * 32 + $tileX;
        $spriteAddress = $this->mirrorDownSpriteAddress($tileNumber + $offset);

        return $this->vram->read($spriteAddress);
    }

    private function mirrorDownSpriteAddress(int $address): int
    {
        if (!$this->isHorizontalMirror) {
            return $address;
        }

        if (($address >= 0x0400 && $address < 0x0800) || $address >= 0x0C00) {
            return $address - 0x400;
        }

        return $address;
    }

    private function getAttribute(int $tileX, int $tileY, int $offset): int
    {
        $address = (int) floor($tileX / 4) + ((int) floor($tileY / 4) * 8) + 0x03C0 + $offset;

        return $this->vram->read($this->mirrorDownSpriteAddress($address));
    }

    private function backgroundTableOffset(): int
    {
        return ($this->registers[0] & 0x10) ? 0x1000 : 0x0000;
    }

    private function setVblank(): void
    {
        $this->registers[0x02] |= 0x80;
    }

    private function hasVblankIrqEnabled(): bool
    {
        return (bool) ($this->registers[0] & 0x80);
    }

    private function clearSpriteHit(): void
    {
        $this->registers[0x02] &= 0xbf;
    }

    /** @return list<int> */
    private function getPalette(): array
    {
        return $this->palette->read();
    }

    private function writeSpriteRamAddress(int $data): void
    {
        $this->spriteRamAddress = $data;
    }

    private function writeSpriteRamData(int $data): void
    {
        $this->spriteRam->write($this->spriteRamAddress, $data);
        ++$this->spriteRamAddress;
    }

    private function writeScrollData(int $data): void
    {
        if ($this->isHorizontalScroll) {
            $this->isHorizontalScroll = false;
            $this->scrollX = $data & 0xFF;
        } else {
            $this->scrollY = $data & 0xFF;
            $this->isHorizontalScroll = true;
        }
    }

    private function writeVramAddress(int $data): void
    {
        if ($this->isLowerVramAddress) {
            $this->vramAddress += $data;
            $this->isLowerVramAddress = false;
        } else {
            $this->vramAddress = $data << 8;
            $this->isLowerVramAddress = true;
        }
    }

    private function writeVramData(int $data): void
    {
        if ($this->vramAddress >= 0x2000) {
            if ($this->vramAddress >= 0x3f00 && $this->vramAddress < 0x4000) {
                $this->palette->write($this->vramAddress - 0x3f00, $data);
            } else {
                $this->writeVram($this->calculateVramAddress(), $data);
            }
        } else {
            $this->writeCharacterRam($this->vramAddress, $data);
        }

        $this->vramAddress += $this->vramOffset();
    }

    private function writeVram(int $address, int $data): void
    {
        $this->vram->write($address, $data);
    }

    private function writeCharacterRam(int $address, int $data): void
    {
        $this->bus->writeByPpu($address, $data);
    }
}
