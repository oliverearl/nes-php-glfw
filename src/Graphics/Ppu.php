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
    /**
     * Total number of sprites in sprite RAM.
     */
    public const int SPRITE_NUMBER = 0x100;

    /**
     * PPU control and status registers.
     *
     * @var list<int>
     */
    private array $registers;

    /**
     * The current PPU cycle within a scanline.
     */
    private int $cycle = 0;

    /**
     * The current scanline being processed.
     */
    private int $scanline = 0;

    /**
     * Indicates whether the next VRAM address write is the lower byte.
     */
    private bool $isLowerVramAddress = false;

    /**
     * The current address in sprite RAM.
     */
    private int $spriteRamAddress = 0;

    /**
     * The current VRAM address pointer.
     */
    private int $vramAddress = 0x0000;

    /**
     * Internal VRAM storage.
     */
    private readonly Ram $vram;

    /**
     * Buffer for VRAM read operations.
     */
    private int $vramReadBuffer = 0;

    /**
     * Sprite RAM storage (OAM - Object Attribute Memory).
     */
    private readonly Ram $spriteRam;

    /**
     * The rendered background tiles for the current frame.
     *
     * @var list<int>
     */
    private array $background = [];

    /**
     * The sprites to be rendered for the current frame.
     *
     * @var list<Sprite>
     */
    private array $sprites = [];

    /**
     * The palette handler for color mapping.
     */
    private readonly Palette $palette;

    /**
     * Indicates whether the next scroll write is horizontal.
     */
    private bool $isHorizontalScroll = true;

    /**
     * The horizontal scroll position.
     */
    private int $scrollX = 0;

    /**
     * The vertical scroll position.
     */
    private int $scrollY = 0;

    /**
     * Cache for sprite patterns to avoid redundant builds.
     * Key is (spriteId << 16) | offset, value is the 8x8 pattern array.
     *
     * @var array<int, list<list<int>>>
     */
    private array $spriteCache = [];

    /**
     * Creates a new PPU instance and initializes internal state.
     */
    public function __construct(private readonly PpuBus $bus, private readonly Interrupts $interrupts, private readonly bool $isHorizontalMirror)
    {
        $this->registers = array_fill(0, 8, 0);
        $this->vram = new Ram(0x2000);
        $this->spriteRam = new Ram(0x100);
        $this->palette = new Palette();
    }

    /**
     * Runs the PPU for the specified number of cycles and returns rendering data when a frame is complete.
     */
    public function run(int $cycle): false|RenderingData
    {
        $this->cycle += $cycle;

        if ($this->scanline === 0) {
            $this->background = [];
            $this->buildSprites();
        }

        /*
         * Process all complete scanlines that fit within accumulated cycles.
         * This loop ensures we don't lose timing by only processing one scanline per call.
         */
        while ($this->cycle >= 341) {
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
                $this->cycle = 0;
                $this->interrupts->deassertNmi();

                return new RenderingData($this->getPalette(), $this->isBackgroundEnabled() ? $this->background : null, $this->isSpriteEnabled() ? $this->sprites : null, );
            }
        }

        return false;
    }

    /**
     * Reads data from a PPU register.
     */
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

    /**
     * Writes data to a PPU register.
     */
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

    /**
     * Transfers a byte to sprite RAM during DMA operation.
     */
    public function transferSprite(int $index, int $data): void
    {
        $address = $index + $this->spriteRamAddress;

        $this->spriteRam->write($address % 0x100, $data);
    }

    /**
     * Builds all sprites from sprite RAM.
     */
    private function buildSprites(): void
    {
        $offset = (($this->registers[0] & 0x08) !== 0) ? 0x1000 : 0x0000;

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

    /**
     * Clears the VBlank flag in the status register.
     */
    private function clearVblank(): void
    {
        $this->registers[0x02] &= 0x7F;
    }

    /**
     * Reads data from VRAM with buffering.
     */
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

    /**
     * Calculates the actual VRAM address accounting for mirroring.
     */
    private function calculateVramAddress(): int
    {
        return ($this->vramAddress >= 0x3000 && $this->vramAddress < 0x3f00) ? $this->vramAddress -= 0x3000 : $this->vramAddress - 0x2000;
    }

    /**
     * Returns the VRAM address increment based on PPU control register.
     */
    private function vramOffset(): int
    {
        return (($this->registers[0x00] & 0x04) !== 0) ? 32 : 1;
    }

    /**
     * Reads a byte from character RAM via the PPU bus.
     */
    private function readCharacterRam(int $address): int
    {
        return $this->bus->readByPpu($address);
    }

    /**
     * Builds a 8x8 sprite pattern from character RAM with caching.
     *
     * @return list<list<int>>
     */
    private function buildSprite(int $spriteId, int $offset): array
    {
        $cacheKey = ($spriteId << 16) | $offset;

        if (isset($this->spriteCache[$cacheKey])) {
            return $this->spriteCache[$cacheKey];
        }

        $sprite = [
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
        ];

        for ($i = 0; $i < 16; $i++) {
            $address = $spriteId * 16 + $i + $offset;
            $ram = $this->readCharacterRam($address);
            $row = $i % 8;
            $plane = (int) ($i >= 8);

            for ($j = 0; $j < 8; $j++) {
                if (($ram & (0x80 >> $j)) !== 0) {
                    $sprite[$row][$j] += (0x01 << $plane);
                }
            }
        }

        $this->spriteCache[$cacheKey] = $sprite;

        return $sprite;
    }

    /**
     * Checks if sprite 0 hit occurred on the current scanline.
     */
    private function hasSpriteHit(): bool
    {
        $y = $this->spriteRam->read(0);

        return ($y === $this->scanline) && $this->isBackgroundEnabled() && $this->isSpriteEnabled();
    }

    /**
     * Checks if background rendering is enabled.
     */
    private function isBackgroundEnabled(): bool
    {
        return (bool) ($this->registers[0x01] & 0x08);
    }

    /**
     * Checks if sprite rendering is enabled.
     */
    private function isSpriteEnabled(): bool
    {
        return (bool) ($this->registers[0x01] & 0x10);
    }

    /**
     * Sets the sprite 0 hit flag in the status register.
     */
    private function setSpriteHit(): void
    {
        $this->registers[0x02] |= 0x40;
    }

    /**
     * Builds background tiles for the current scanline.
     */
    private function buildBackground(): void
    {
        $clampedTileY = $this->tileY() % 30;
        $tableIdOffset = ((int) floor($this->tileY() / 30) % 2 !== 0) ? 2 : 0;

        for ($x = 0; $x < 32 + 1; $x = ($x + 1) | 0) {
            $tileX = ($x + $this->scrollTileX());
            $clampedTileX = $tileX % 32;
            $nameTableId = ((int) floor($tileX / 32) % 2) + $tableIdOffset;
            $offsetAddrByNameTable = $nameTableId * 0x400;

            $tile = $this->buildTile($clampedTileX, $clampedTileY, $offsetAddrByNameTable);

            $this->background[] = $tile;
        }
    }

    /**
     * Calculates the current tile Y position.
     */
    private function tileY(): int
    {
        return (int) floor($this->scanline / 8) + $this->scrollTileY();
    }

    /**
     * Calculates the scroll-adjusted tile Y position.
     */
    private function scrollTileY(): int
    {
        return (int) floor(($this->scrollY + ((int) floor($this->nameTableId() / 2) * 240)) / 8);
    }

    /**
     * Gets the current nametable ID from the control register.
     */
    private function nameTableId(): int
    {
        return $this->registers[0x00] & 0x03;
    }

    /**
     * Calculates the scroll-adjusted tile X position.
     */
    private function scrollTileX(): int
    {
        return (int) floor(($this->scrollX + (($this->nameTableId() % 2) * 256)) / 8);
    }

    /**
     * Builds a single background tile with sprite pattern and palette information.
     */
    private function buildTile(int $tileX, int $tileY, int $offset): Tile
    {
        $blockId = $this->getBlockId($tileX, $tileY);
        $spriteId = $this->getSpriteId($tileX, $tileY, $offset);
        $attr = $this->getAttribute($tileX, $tileY, $offset);
        $paletteId = ($attr >> ($blockId * 2)) & 0x03;
        $sprite = $this->buildSprite($spriteId, $this->backgroundTableOffset());

        return new Tile($sprite, $paletteId, $this->scrollX, $this->scrollY, );
    }

    /**
     * Gets the block ID for attribute table lookup.
     */
    private function getBlockId(int $tileX, int $tileY): int
    {
        return (int) floor(($tileX % 4) / 2) + ((int) floor(($tileY % 4) / 2)) * 2;
    }

    /**
     * Gets the sprite ID from the nametable.
     */
    private function getSpriteId(int $tileX, int $tileY, int $offset): int
    {
        $tileNumber = $tileY * 32 + $tileX;
        $spriteAddress = $this->mirrorDownSpriteAddress($tileNumber + $offset);

        return $this->vram->read($spriteAddress);
    }

    /**
     * Applies nametable mirroring based on horizontal/vertical mirror mode.
     */
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

    /**
     * Gets the attribute byte for a tile.
     */
    private function getAttribute(int $tileX, int $tileY, int $offset): int
    {
        $address = (int) floor($tileX / 4) + ((int) floor($tileY / 4) * 8) + 0x03C0 + $offset;

        return $this->vram->read($this->mirrorDownSpriteAddress($address));
    }

    /**
     * Gets the background pattern table offset from the control register.
     */
    private function backgroundTableOffset(): int
    {
        return (($this->registers[0] & 0x10) !== 0) ? 0x1000 : 0x0000;
    }

    /**
     * Sets the VBlank flag in the status register.
     */
    private function setVblank(): void
    {
        $this->registers[0x02] |= 0x80;
    }

    /**
     * Checks if VBlank NMI is enabled.
     */
    private function hasVblankIrqEnabled(): bool
    {
        return (bool) ($this->registers[0] & 0x80);
    }

    /**
     * Clears the sprite 0 hit flag.
     */
    private function clearSpriteHit(): void
    {
        $this->registers[0x02] &= 0xbf;
    }

    /**
     * Gets the current palette data.
     *
     * @return list<int>
     */
    private function getPalette(): array
    {
        return $this->palette->read();
    }

    /**
     * Writes to the sprite RAM address register.
     */
    private function writeSpriteRamAddress(int $data): void
    {
        $this->spriteRamAddress = $data;
    }

    /**
     * Writes data to sprite RAM and increments the address.
     */
    private function writeSpriteRamData(int $data): void
    {
        $this->spriteRam->write($this->spriteRamAddress, $data);
        ++$this->spriteRamAddress;
    }

    /**
     * Writes scroll position data (alternates between X and Y).
     */
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

    /**
     * Writes VRAM address data (alternates between high and low byte).
     */
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

    /**
     * Writes data to VRAM or palette RAM.
     */
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

    /**
     * Writes a byte to VRAM.
     */
    private function writeVram(int $address, int $data): void
    {
        $this->vram->write($address, $data);
    }

    /**
     * Writes a byte to character RAM via the PPU bus.
     */
    private function writeCharacterRam(int $address, int $data): void
    {
        $this->bus->writeByPpu($address, $data);
    }
}
