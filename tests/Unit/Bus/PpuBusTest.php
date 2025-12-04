<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use App\Bus\PpuBus;
use App\Bus\Ram;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PpuBus::class)]
final class PpuBusTest extends TestCase
{
    #[Test]
    public function it_reads_from_character_ram(): void
    {
        $characterRam = new Ram(0x2000);
        $characterRam->write(0x0000, 0x42);
        $characterRam->write(0x1000, 0xAA);
        $characterRam->write(0x1FFF, 0xFF);

        $ppuBus = new PpuBus($characterRam);

        $this::assertSame(0x42, $ppuBus->readByPpu(0x0000));
        $this::assertSame(0xAA, $ppuBus->readByPpu(0x1000));
        $this::assertSame(0xFF, $ppuBus->readByPpu(0x1FFF));
    }

    #[Test]
    public function it_writes_to_character_ram(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $ppuBus->writeByPpu(0x0500, 0x33);
        $ppuBus->writeByPpu(0x1500, 0x77);

        $this::assertSame(0x33, $characterRam->read(0x0500));
        $this::assertSame(0x77, $characterRam->read(0x1500));
    }

    #[Test]
    public function it_reads_pattern_table_0(): void
    {
        $characterRam = new Ram(0x2000);
        // Pattern table 0: 0x0000-0x0FFF.
        $characterRam->write(0x0000, 0x81);
        $characterRam->write(0x0FFF, 0x7E);

        $ppuBus = new PpuBus($characterRam);

        $this::assertSame(0x81, $ppuBus->readByPpu(0x0000));
        $this::assertSame(0x7E, $ppuBus->readByPpu(0x0FFF));
    }

    #[Test]
    public function it_reads_pattern_table_1(): void
    {
        $characterRam = new Ram(0x2000);
        // Pattern table 1: 0x1000-0x1FFF.
        $characterRam->write(0x1000, 0x3C);
        $characterRam->write(0x1FFF, 0xC3);

        $ppuBus = new PpuBus($characterRam);

        $this::assertSame(0x3C, $ppuBus->readByPpu(0x1000));
        $this::assertSame(0xC3, $ppuBus->readByPpu(0x1FFF));
    }

    #[Test]
    public function it_writes_to_pattern_tables(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        // Write to pattern table 0.
        $ppuBus->writeByPpu(0x0100, 0xAA);
        // Write to pattern table 1.
        $ppuBus->writeByPpu(0x1100, 0x55);

        $this::assertSame(0xAA, $characterRam->read(0x0100));
        $this::assertSame(0x55, $characterRam->read(0x1100));
    }

    #[Test]
    public function it_handles_sprite_data(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        // Write 8x8 sprite pattern (16 bytes).
        for ($i = 0; $i < 16; $i++) {
            $ppuBus->writeByPpu(0x0000 + $i, 0xFF);
        }

        // Verify sprite data.
        for ($i = 0; $i < 16; $i++) {
            $this::assertSame(0xFF, $ppuBus->readByPpu(0x0000 + $i));
        }
    }

    #[Test]
    public function it_provides_access_to_character_ram(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $this::assertSame($characterRam, $ppuBus->characterRam);
    }

    #[Test]
    public function it_handles_zero_values(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $ppuBus->writeByPpu(0x0500, 0x00);
        $this::assertSame(0x00, $ppuBus->readByPpu(0x0500));
    }

    #[Test]
    public function it_handles_maximum_8bit_values(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $ppuBus->writeByPpu(0x0500, 0xFF);
        $this::assertSame(0xFF, $ppuBus->readByPpu(0x0500));
    }

    #[Test]
    public function it_reads_and_writes_multiple_addresses(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $testData = [
            0x0000 => 0x11,
            0x0100 => 0x22,
            0x0500 => 0x33,
            0x1000 => 0x44,
            0x1500 => 0x55,
            0x1FFF => 0x66,
        ];

        foreach ($testData as $address => $value) {
            $ppuBus->writeByPpu($address, $value);
        }

        foreach ($testData as $address => $value) {
            $this::assertSame($value, $ppuBus->readByPpu($address));
        }
    }

    #[Test]
    public function it_handles_boundary_addresses(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        // Test first and last addresses
        $ppuBus->writeByPpu(0x0000, 0xAA);
        $ppuBus->writeByPpu(0x1FFF, 0x55);

        $this::assertSame(0xAA, $ppuBus->readByPpu(0x0000));
        $this::assertSame(0x55, $ppuBus->readByPpu(0x1FFF));
    }

    #[Test]
    public function it_preserves_data_across_reads(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $ppuBus->writeByPpu(0x0800, 0xBB);

        // Multiple reads should return same value.
        $this::assertSame(0xBB, $ppuBus->readByPpu(0x0800));
        $this::assertSame(0xBB, $ppuBus->readByPpu(0x0800));
        $this::assertSame(0xBB, $ppuBus->readByPpu(0x0800));
    }

    #[Test]
    public function it_overwrites_existing_data(): void
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);

        $ppuBus->writeByPpu(0x0500, 0x11);
        $this::assertSame(0x11, $ppuBus->readByPpu(0x0500));

        $ppuBus->writeByPpu(0x0500, 0x22);
        $this::assertSame(0x22, $ppuBus->readByPpu(0x0500));
    }
}
