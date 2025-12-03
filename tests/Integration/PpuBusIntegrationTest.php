<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class PpuBusIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function it_reads_pattern_table_through_bus(): void
    {
        [$ppu, $ppuBus, $characterRom, $interrupts] = $this->createPpuSystem();

        // Write pattern data to character ROM
        for ($i = 0; $i < 16; $i++) {
            $characterRom->write($i, $i * 0x10);
        }

        // PPU should be able to read it through the bus
        $data = $ppuBus->readByPpu(0x0000);
        $this::assertSame(0x00, $data);

        $data = $ppuBus->readByPpu(0x0001);
        $this::assertSame(0x10, $data);

        $data = $ppuBus->readByPpu(0x000F);
        $this::assertSame(0xF0, $data);
    }

    #[Test]
    public function it_accesses_both_pattern_tables(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Write to pattern table 0 (0x0000-0x0FFF)
        $characterRom->write(0x0000, 0xAA);

        // Write to pattern table 1 (0x1000-0x1FFF)
        $characterRom->write(0x1000, 0xBB);

        // Read from both tables
        $this::assertSame(0xAA, $ppuBus->readByPpu(0x0000));
        $this::assertSame(0xBB, $ppuBus->readByPpu(0x1000));
    }

    #[Test]
    public function it_writes_to_character_ram(): void
    {
        [$ppu, $ppuBus] = $this->createPpuSystem();

        // Some games use CHR-RAM instead of CHR-ROM
        $ppuBus->writeByPpu(0x0100, 0x55);

        // Should be able to read it back
        $this::assertSame(0x55, $ppuBus->readByPpu(0x0100));
    }

    #[Test]
    public function it_handles_8kb_character_memory(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Write to end of character memory
        $characterRom->write(0x1FFF, 0xFF);

        $this::assertSame(0xFF, $ppuBus->readByPpu(0x1FFF));
    }

    #[Test]
    public function it_reads_sprite_patterns(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Sprite pattern at tile index 0 in pattern table 0
        // Each tile is 16 bytes (8 bytes for low bit plane, 8 for high)
        for ($i = 0; $i < 16; $i++) {
            $characterRom->write($i, $i);
        }

        // Read the pattern data
        for ($i = 0; $i < 16; $i++) {
            $this::assertSame($i, $ppuBus->readByPpu($i));
        }
    }

    #[Test]
    public function it_reads_background_patterns(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Background tile pattern in pattern table 1
        $baseAddr = 0x1000;
        for ($i = 0; $i < 16; $i++) {
            $characterRom->write($baseAddr + $i, 0xFF - $i);
        }

        // Read back
        for ($i = 0; $i < 16; $i++) {
            $this::assertSame(0xFF - $i, $ppuBus->readByPpu($baseAddr + $i));
        }
    }

    #[Test]
    public function it_handles_sequential_pattern_reads(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Write a sequence
        for ($i = 0; $i < 256; $i++) {
            $characterRom->write($i, $i);
        }

        // Read sequentially
        for ($i = 0; $i < 256; $i++) {
            $this::assertSame($i, $ppuBus->readByPpu($i));
        }
    }

    #[Test]
    public function it_supports_chr_ram_writes_for_programmable_graphics(): void
    {
        // Some games use CHR-RAM to update graphics dynamically
        [$ppu, $ppuBus] = $this->createPpuSystem();

        // Write a tile pattern
        $tileData = [
            0b01111110,
            0b01000010,
            0b01000010,
            0b01111110,
            0b01000010,
            0b01000010,
            0b01000010,
            0b00000000,
        ];

        foreach ($tileData as $i => $byte) {
            $ppuBus->writeByPpu($i, $byte);
        }

        // Read it back
        foreach ($tileData as $i => $byte) {
            $this::assertSame($byte, $ppuBus->readByPpu($i));
        }
    }
}
