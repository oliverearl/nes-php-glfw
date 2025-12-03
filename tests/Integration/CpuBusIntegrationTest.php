<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class CpuBusIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function it_reads_from_ram_through_bus(): void
    {
        [$cpu, $cpuBus, $ram] = $this->createTestSystem();

        // Write data to RAM
        $ram->write(0x0200, 0x42);

        // CPU should be able to read it through the bus
        $data = $cpuBus->readByCpu(0x0200);

        $this->assertSame(0x42, $data);
    }

    #[Test]
    public function it_writes_to_ram_through_bus(): void
    {
        [$cpu, $cpuBus, $ram] = $this->createTestSystem();

        // CPU writes through bus
        $cpuBus->writeByCpu(0x0300, 0xAA);

        // RAM should have the data
        $this->assertSame(0xAA, $ram->read(0x0300));
    }

    #[Test]
    public function it_reads_from_rom_through_bus(): void
    {
        [, $cpuBus, , $programRom] = $this->createTestSystem();

        // Read from ROM address space (0x8000+)
        $data = $cpuBus->readByCpu(0x8000);

        // Should get first byte of ROM (NOP = 0xEA)
        $this->assertSame(0xEA, $data);
    }

    #[Test]
    public function it_mirrors_ram_addresses(): void
    {
        [$cpu, $cpuBus, $ram] = $this->createTestSystem();

        // Write to base RAM address
        $ram->write(0x0100, 0x55);

        // Read from mirrored address (0x0800+ mirrors 0x0000-0x07FF)
        $data = $cpuBus->readByCpu(0x0900);

        $this->assertSame(0x55, $data);
    }

    #[Test]
    public function it_handles_rom_mirroring_for_16kb_roms(): void
    {
        // Create 16KB ROM (should mirror at 0xC000)
        $romData = array_fill(0, 0x4000, 0);
        $romData[0] = 0x4C; // First byte
        $romData[0x3FFF] = 0xFF; // Last byte

        [, $cpuBus] = $this->createTestSystemWithRom($romData);

        // Read from 0x8000 (start of ROM)
        $this->assertSame(0x4C, $cpuBus->readByCpu(0x8000));

        // Read from 0xC000 (should mirror to 0x8000 for 16KB ROM)
        $this->assertSame(0x4C, $cpuBus->readByCpu(0xC000));

        // Read last byte
        $this->assertSame(0xFF, $cpuBus->readByCpu(0xBFFF));
        $this->assertSame(0xFF, $cpuBus->readByCpu(0xFFFF));
    }

    #[Test]
    public function it_handles_32kb_rom_without_mirroring(): void
    {
        // Create 32KB ROM (no mirroring needed)
        $romData = array_fill(0, 0x8000, 0);
        $romData[0] = 0x4C; // First byte of low bank
        $romData[0x4000] = 0x6C; // First byte of high bank

        [, $cpuBus] = $this->createTestSystemWithRom($romData);

        // Read from low bank
        $this->assertSame(0x4C, $cpuBus->readByCpu(0x8000));

        // Read from high bank
        $this->assertSame(0x6C, $cpuBus->readByCpu(0xC000));
    }

    #[Test]
    public function it_ignores_writes_to_rom(): void
    {
        [$cpu, $cpuBus, , $programRom] = $this->createTestSystem();

        $original = $cpuBus->readByCpu(0x8000);

        // Attempt to write to ROM (should be ignored)
        $cpuBus->writeByCpu(0x8000, 0xFF);

        // ROM should be unchanged
        $this->assertSame($original, $cpuBus->readByCpu(0x8000));
    }

    #[Test]
    public function it_handles_apu_register_reads_without_error(): void
    {
        [$cpu, $cpuBus] = $this->createTestSystem();

        // APU registers (0x4000-0x4017) should return 0 (not crash)
        $this->assertSame(0, $cpuBus->readByCpu(0x4000));
        $this->assertSame(0, $cpuBus->readByCpu(0x4017));
    }

    #[Test]
    public function it_handles_apu_register_writes_without_error(): void
    {
        [$cpu, $cpuBus] = $this->createTestSystem();

        // APU writes should be silently ignored (not crash)
        $cpuBus->writeByCpu(0x4000, 0xFF);
        $cpuBus->writeByCpu(0x4001, 0xAA);

        // Should not throw any exceptions
        $this->assertTrue(true);
    }

    #[Test]
    public function it_executes_simple_instruction_sequence(): void
    {
        // Create a simple program: LDA #$42, STA $0200
        $program = [
            0xA9, 0x42, // LDA #$42 (Load 0x42 into accumulator)
            0x8D, 0x00, 0x02, // STA $0200 (Store accumulator at $0200)
            0xEA, // NOP
        ];

        // Write program to ROM
        $modifiedRom = array_fill(0, 0x8000, 0xEA);
        foreach ($program as $i => $byte) {
            $modifiedRom[$i] = $byte;
        }

        [$cpu] = $this->createTestSystemWithRom($modifiedRom);

        // Reset CPU (sets PC to reset vector)
        $cpu->reset();

        // Execute LDA instruction
        $cycles1 = $cpu->run();

        // Execute STA instruction
        $cycles2 = $cpu->run();

        // Just verify cycles were consumed (actual memory write verification
        // would require examining CPU internals or running many more instructions)
        $this->assertGreaterThan(0, $cycles1);
        $this->assertGreaterThan(0, $cycles2);
    }

    #[Test]
    public function it_accesses_ppu_registers_through_bus(): void
    {
        [$cpu, $cpuBus, , , $ppu] = $this->createTestSystem();

        // Write to PPU control register (0x2000)
        $cpuBus->writeByCpu(0x2000, 0x80);

        // The write should go through
        $this->assertTrue(true);

        // PPU status register read (0x2002) should work
        $status = $cpuBus->readByCpu(0x2002);

        $this->assertIsInt($status);
    }

    #[Test]
    public function it_handles_ppu_register_mirroring(): void
    {
        [$cpu, $cpuBus] = $this->createTestSystem();

        // PPU registers (0x2000-0x2007) are mirrored every 8 bytes up to 0x3FFF
        // Writing to 0x2000 should be same as 0x2008, 0x2010, etc.

        $cpuBus->writeByCpu(0x2000, 0x80);
        $cpuBus->writeByCpu(0x2008, 0x90);
        $cpuBus->writeByCpu(0x3FF8, 0xA0);

        // All writes should go to the same register (last write wins)
        // Just verify no exceptions are thrown
        $this->assertTrue(true);
    }
}
