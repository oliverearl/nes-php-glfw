<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use App\Bus\Rom;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rom::class)]
final class RomTest extends TestCase
{
    #[Test]
    public function it_initializes_with_data_array(): void
    {
        $data = [0x01, 0x02, 0x03, 0x04, 0x05];
        $rom = new Rom($data);

        $this::assertSame(0x01, $rom->read(0));
        $this::assertSame(0x03, $rom->read(2));
        $this::assertSame(0x05, $rom->read(4));
    }

    #[Test]
    public function it_returns_correct_size(): void
    {
        $data = array_fill(0, 1024, 0);
        $rom = new Rom($data);

        $this::assertSame(1024, $rom->size());
    }

    #[Test]
    public function it_returns_size_for_empty_rom(): void
    {
        $rom = new Rom([]);

        $this::assertSame(0, $rom->size());
    }

    #[Test]
    public function it_reads_data_at_various_addresses(): void
    {
        $data = [0xAA, 0xBB, 0xCC, 0xDD, 0xEE, 0xFF];
        $rom = new Rom($data);

        $this::assertSame(0xAA, $rom->read(0));
        $this::assertSame(0xBB, $rom->read(1));
        $this::assertSame(0xCC, $rom->read(2));
        $this::assertSame(0xDD, $rom->read(3));
        $this::assertSame(0xEE, $rom->read(4));
        $this::assertSame(0xFF, $rom->read(5));
    }

    #[Test]
    public function it_handles_large_rom(): void
    {
        // Create a 32KB ROM (typical NES program ROM bank)
        $data = array_fill(0, 32768, 0);
        $data[0] = 0x4C; // JMP instruction
        $data[32767] = 0xFF; // Last byte

        $rom = new Rom($data);

        $this::assertSame(32768, $rom->size());
        $this::assertSame(0x4C, $rom->read(0));
        $this::assertSame(0xFF, $rom->read(32767));
    }

    #[Test]
    public function it_preserves_data_immutability(): void
    {
        $data = [0x10, 0x20, 0x30];
        $rom = new Rom($data);

        // Read multiple times to ensure data doesn't change
        $this::assertSame(0x20, $rom->read(1));
        $this::assertSame(0x20, $rom->read(1));
        $this::assertSame(0x20, $rom->read(1));
    }

    #[Test]
    public function it_handles_zero_values(): void
    {
        $data = [0x00, 0x00, 0x00];
        $rom = new Rom($data);

        $this::assertSame(0, $rom->read(0));
        $this::assertSame(0, $rom->read(1));
        $this::assertSame(0, $rom->read(2));
    }

    #[Test]
    public function it_handles_maximum_8bit_values(): void
    {
        $data = [0xFF, 0xFF, 0xFF];
        $rom = new Rom($data);

        $this::assertSame(0xFF, $rom->read(0));
        $this::assertSame(0xFF, $rom->read(1));
        $this::assertSame(0xFF, $rom->read(2));
    }
}

