<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

final class PpuBusIntegrationTest extends IntegrationTestCase
{
    /**
     * Checks rendering and PPUDATA access select the same physical nametable.
     */
    #[Test]
    #[DataProvider('renderedNametableModes')]
    public function it_renders_the_same_mirrored_nametable_that_the_data_port_reads(bool $horizontal, int $table, int $expectedPattern): void
    {
        [$ppu, $ppuBus] = $this->createPpuSystem(horizontalMirror: $horizontal);
        $ppuBus->writeByPpu(0x10, 0x80);
        $ppuBus->writeByPpu(0x28, 0x80);
        $ppuBus->writeByPpu(0x30, 0x80);
        $ppuBus->writeByPpu(0x38, 0x80);

        foreach ([0x2020, 0x2420, 0x2820, 0x2C20] as $index => $address) {
            $ppu->write(6, $address >> 8);
            $ppu->write(6, $address & 0xFF);
            $ppu->write(7, $index);
        }

        $ppu->write(0, $table);
        $ppu->write(1, 0x08);
        $frame = $ppu->run(341 * 262);

        $this::assertNotFalse($frame);
        $this::assertNotNull($frame->background);
        $this::assertSame($expectedPattern, $frame->background[0]->pattern[0][0]);

        $address = 0x2020 + $table * 0x400;
        $ppu->write(6, $address >> 8);
        $ppu->write(6, $address & 0xFF);
        $ppu->read(7);
        $this::assertSame($expectedPattern, $ppu->read(7));
    }

    /**
     * Supplies each logical table's final pattern after writes through all aliases.
     *
     * @return array<string, array{bool, int, int}>
     */
    public static function renderedNametableModes(): array
    {
        return [
            'horizontal table zero' => [true, 0, 1],
            'horizontal table one' => [true, 1, 1],
            'horizontal table two' => [true, 2, 3],
            'horizontal table three' => [true, 3, 3],
            'vertical table zero' => [false, 0, 2],
            'vertical table one' => [false, 1, 3],
            'vertical table two' => [false, 2, 2],
            'vertical table three' => [false, 3, 3],
        ];
    }

    #[Test]
    public function it_reads_pattern_table_through_bus(): void
    {
        [$ppu, $ppuBus, $characterRom, $interrupts] = $this->createPpuSystem();

        // Write pattern data to character ROM.
        for ($i = 0; $i < 16; $i++) {
            $characterRom->write($i, $i * 0x10);
        }

        // PPU should be able to read it through the bus.
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

        // Write to pattern table 0 (0x0000-0x0FFF).
        $characterRom->write(0x0000, 0xAA);

        // Write to pattern table 1 (0x1000-0x1FFF).
        $characterRom->write(0x1000, 0xBB);

        // Read from both tables.
        $this::assertSame(0xAA, $ppuBus->readByPpu(0x0000));
        $this::assertSame(0xBB, $ppuBus->readByPpu(0x1000));
    }

    #[Test]
    public function it_writes_to_character_ram(): void
    {
        [$ppu, $ppuBus] = $this->createPpuSystem();

        // Some games use CHR-RAM instead of CHR-ROM.
        $ppuBus->writeByPpu(0x0100, 0x55);

        // Should be able to read it back.
        $this::assertSame(0x55, $ppuBus->readByPpu(0x0100));
    }

    #[Test]
    public function it_handles_8kb_character_memory(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Write to end of character memory.
        $characterRom->write(0x1FFF, 0xFF);

        $this::assertSame(0xFF, $ppuBus->readByPpu(0x1FFF));
    }

    #[Test]
    public function it_reads_sprite_patterns(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        /*
         * Sprite pattern at tile index 0 in pattern table 0.
         * Each tile is 16 bytes (8 bytes for low bit plane, 8 for high).
         */
        for ($i = 0; $i < 16; $i++) {
            $characterRom->write($i, $i);
        }

        // Read the pattern data.
        for ($i = 0; $i < 16; $i++) {
            $this::assertSame($i, $ppuBus->readByPpu($i));
        }
    }

    #[Test]
    public function it_reads_background_patterns(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Background tile pattern in pattern table 1.
        $baseAddr = 0x1000;
        for ($i = 0; $i < 16; $i++) {
            $characterRom->write($baseAddr + $i, 0xFF - $i);
        }

        // Read back.
        for ($i = 0; $i < 16; $i++) {
            $this::assertSame(0xFF - $i, $ppuBus->readByPpu($baseAddr + $i));
        }
    }

    #[Test]
    public function it_handles_sequential_pattern_reads(): void
    {
        [$ppu, $ppuBus, $characterRom] = $this->createPpuSystem();

        // Write a sequence.
        for ($i = 0; $i < 256; $i++) {
            $characterRom->write($i, $i);
        }

        // Read sequentially.
        for ($i = 0; $i < 256; $i++) {
            $this::assertSame($i, $ppuBus->readByPpu($i));
        }
    }

    #[Test]
    public function it_supports_chr_ram_writes_for_programmable_graphics(): void
    {
        // Some games use CHR-RAM to update graphics dynamically.
        [$ppu, $ppuBus] = $this->createPpuSystem();

        // Write a tile pattern.
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

        // Read it back.
        foreach ($tileData as $i => $byte) {
            $this::assertSame($byte, $ppuBus->readByPpu($i));
        }
    }
}
