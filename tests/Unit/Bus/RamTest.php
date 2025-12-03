<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use App\Bus\Ram;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ram::class)]
final class RamTest extends TestCase
{
    #[Test]
    public function it_initializes_with_correct_size(): void
    {
        $ram = new Ram(2048);

        // All bytes should be initialized to 0
        for ($i = 0; $i < 2048; $i++) {
            $this::assertSame(0, $ram->read($i));
        }
    }

    #[Test]
    public function it_initializes_with_default_size(): void
    {
        $ram = new Ram();

        // Default size is 2048
        $this::assertSame(0, $ram->read(2047));
    }

    #[Test]
    public function it_writes_and_reads_data(): void
    {
        $ram = new Ram(256);

        $ram->write(0, 0xFF);
        $ram->write(100, 0x42);
        $ram->write(255, 0xAB);

        $this::assertSame(0xFF, $ram->read(0));
        $this::assertSame(0x42, $ram->read(100));
        $this::assertSame(0xAB, $ram->read(255));
    }

    #[Test]
    public function it_overwrites_existing_data(): void
    {
        $ram = new Ram(128);

        $ram->write(50, 0x11);
        $this::assertSame(0x11, $ram->read(50));

        $ram->write(50, 0x22);
        $this::assertSame(0x22, $ram->read(50));
    }

    #[Test]
    public function it_resets_all_memory_to_zero(): void
    {
        $ram = new Ram(512);

        // Write some data
        $ram->write(0, 0xFF);
        $ram->write(100, 0xAA);
        $ram->write(511, 0x55);

        // Reset
        $ram->reset();

        // All should be zero
        $this::assertSame(0, $ram->read(0));
        $this::assertSame(0, $ram->read(100));
        $this::assertSame(0, $ram->read(511));
    }

    #[Test]
    public function it_returns_entire_ram_array(): void
    {
        $ram = new Ram(16);

        $ram->write(0, 0x10);
        $ram->write(5, 0x50);
        $ram->write(15, 0xF0);

        $ramArray = $ram->getRam();

        $this::assertIsArray($ramArray);
        $this::assertCount(16, $ramArray);
        $this::assertSame(0x10, $ramArray[0]);
        $this::assertSame(0x50, $ramArray[5]);
        $this::assertSame(0xF0, $ramArray[15]);
    }

    #[Test]
    public function it_handles_boundary_addresses(): void
    {
        $ram = new Ram(256);

        $ram->write(0, 0x00);
        $ram->write(255, 0xFF);

        $this::assertSame(0x00, $ram->read(0));
        $this::assertSame(0xFF, $ram->read(255));
    }

    #[Test]
    public function it_stores_8bit_values_correctly(): void
    {
        $ram = new Ram(128);

        // Test various 8-bit values
        $testValues = [0x00, 0x01, 0x7F, 0x80, 0xFF, 0xAA, 0x55];

        foreach ($testValues as $index => $value) {
            $ram->write($index, $value);
            $this::assertSame($value, $ram->read($index));
        }
    }
}

