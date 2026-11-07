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

        // Write data to RAM.
        $ram->write(0x0200, 0x42);

        // CPU should be able to read it through the bus.
        $data = $cpuBus->readByCpu(0x0200);

        $this::assertSame(0x42, $data);
    }

    #[Test]
    public function it_writes_to_ram_through_bus(): void
    {
        [$cpu, $cpuBus, $ram] = $this->createTestSystem();

        // CPU writes through bus.
        $cpuBus->writeByCpu(0x0300, 0xAA);

        // RAM should have the data.
        $this::assertSame(0xAA, $ram->read(0x0300));
    }

    #[Test]
    public function it_reads_from_rom_through_bus(): void
    {
        [, $cpuBus, , $programRom] = $this->createTestSystem();

        // Read from ROM address space (0x8000+).
        $data = $cpuBus->readByCpu(0x8000);

        // Should get first byte of ROM (NOP = 0xEA).
        $this::assertSame(0xEA, $data);
    }

    #[Test]
    public function it_mirrors_ram_addresses(): void
    {
        [$cpu, $cpuBus, $ram] = $this->createTestSystem();

        // Write to base RAM address.
        $ram->write(0x0100, 0x55);

        // Read from mirrored address (0x0800+ mirrors 0x0000-0x07FF).
        $data = $cpuBus->readByCpu(0x0900);

        $this::assertSame(0x55, $data);
    }

    #[Test]
    public function it_handles_rom_mirroring_for_16kb_roms(): void
    {
        // Create 16KB ROM (should mirror at 0xC000).
        $romData = array_fill(0, 0x4000, 0);
        $romData[0] = 0x4C; // First byte.
        $romData[0x3FFF] = 0xFF; // Last byte.

        [, $cpuBus] = $this->createTestSystemWithRom($romData);

        // Read from 0x8000 (start of ROM).
        $this::assertSame(0x4C, $cpuBus->readByCpu(0x8000));

        // Read from 0xC000 (should mirror to 0x8000 for 16KB ROM).
        $this::assertSame(0x4C, $cpuBus->readByCpu(0xC000));

        // Read last byte.
        $this::assertSame(0xFF, $cpuBus->readByCpu(0xBFFF));
        $this::assertSame(0xFF, $cpuBus->readByCpu(0xFFFF));
    }

    #[Test]
    public function it_handles_32kb_rom_without_mirroring(): void
    {
        // Create 32KB ROM (no mirroring needed).
        $romData = array_fill(0, 0x8000, 0);
        $romData[0] = 0x4C; // First byte of low bank.
        $romData[0x4000] = 0x6C; // First byte of high bank.

        [, $cpuBus] = $this->createTestSystemWithRom($romData);

        // Read from low bank.
        $this::assertSame(0x4C, $cpuBus->readByCpu(0x8000));

        // Read from high bank.
        $this::assertSame(0x6C, $cpuBus->readByCpu(0xC000));
    }

    #[Test]
    public function it_ignores_writes_to_rom(): void
    {
        [$cpu, $cpuBus, , $programRom] = $this->createTestSystem();

        $original = $cpuBus->readByCpu(0x8000);

        // Attempt to write to ROM (should be ignored).
        $cpuBus->writeByCpu(0x8000, 0xFF);

        // ROM should be unchanged.
        $this::assertSame($original, $cpuBus->readByCpu(0x8000));
    }

    #[Test]
    public function it_handles_apu_register_reads_without_error(): void
    {
        [$cpu, $cpuBus] = $this->createTestSystem();

        // APU registers (0x4000-0x4017) should return 0 (not crash).
        $this::assertSame(0, $cpuBus->readByCpu(0x4000));
        $this::assertSame(0, $cpuBus->readByCpu(0x4017));
    }

    /**
     * Keeps currently unimplemented APU writes isolated from RAM and ROM.
     */
    #[Test]
    public function it_ignores_unimplemented_apu_writes_without_mutating_memory(): void
    {
        [, $cpuBus, $ram] = $this->createTestSystem();
        $ram->write(0x0200, 0x42);

        $cpuBus->writeByCpu(0x4000, 0xFF);
        $cpuBus->writeByCpu(0x4001, 0xAA);

        $this::assertSame(0x42, $ram->read(0x0200));
        $this::assertSame(0xEA, $cpuBus->readByCpu(0x8000));
        $this::assertSame(0, $cpuBus->readByCpu(0x4000));
        $this::assertSame(0, $cpuBus->readByCpu(0x4001));
    }

    /**
     * Executes a ROM program and checks exact instruction timing, state, and RAM effects.
     */
    #[Test]
    public function it_executes_simple_instruction_sequence(): void
    {
        // Create a simple program: LDA #$42, STA $0200.
        $program = [
            0xA9, 0x42, // LDA #$42 (Load 0x42 into accumulator).
            0x8D, 0x00, 0x02, // STA $0200 (Store accumulator at $0200).
            0xEA, // NOP.
        ];

        // Write program to ROM.
        $modifiedRom = array_fill(0, 0x8000, 0xEA);
        foreach ($program as $i => $byte) {
            $modifiedRom[$i] = $byte;
        }
        $modifiedRom[0x7FFC] = 0x00;
        $modifiedRom[0x7FFD] = 0x80;

        [$cpu, , $ram] = $this->createTestSystemWithRom($modifiedRom);

        $cpu->reset();
        $resetState = $cpu->snapshot();

        $loadCycles = $cpu->run();
        $loadedState = $cpu->snapshot();
        $beforeStore = $ram->read(0x0200);

        $storeCycles = $cpu->run();
        $storedState = $cpu->snapshot();
        $afterStore = $ram->read(0x0200);

        $nopCycles = $cpu->run();
        $nopState = $cpu->snapshot();

        $this::assertSame([2, 4, 2], [$loadCycles, $storeCycles, $nopCycles]);
        $this::assertSame(0x8000, $resetState->pc);
        $this::assertSame(0x42, $loadedState->a);
        $this::assertSame(0x8002, $loadedState->pc);
        $this::assertSame(0x00, $beforeStore);
        $this::assertSame(0x42, $afterStore);
        $this::assertSame(0x8005, $storedState->pc);
        $this::assertSame(0x8006, $nopState->pc);
        $this::assertSame(0x42, $ram->read(0x0200));
    }

    /**
     * Loads and stores mirrored RAM through actual CPU instructions.
     */
    #[Test]
    public function it_executes_loads_and_stores_through_ram_mirrors(): void
    {
        $rom = array_fill(0, 0x8000, 0xEA);

        // LDA $0900 reads $0100; STA $0A00 writes $0200.
        foreach ([0xAD, 0x00, 0x09, 0x8D, 0x00, 0x0A] as $offset => $byte) {
            $rom[$offset] = $byte;
        }
        $rom[0x7FFC] = 0x00;
        $rom[0x7FFD] = 0x80;

        [$cpu, $bus, $ram] = $this->createTestSystemWithRom($rom);
        $ram->write(0x0100, 0xA5);
        $cpu->reset();

        $loadCycles = $cpu->run();
        $loadedState = $cpu->snapshot();
        $storeCycles = $cpu->run();
        $storedState = $cpu->snapshot();

        $this::assertSame([4, 4], [$loadCycles, $storeCycles]);
        $this::assertSame(0xA5, $loadedState->a);
        $this::assertSame(0x8003, $loadedState->pc);
        $this::assertSame(0xA5, $ram->read(0x0200));
        $this::assertSame(0xA5, $bus->readByCpu(0x0A00));
        $this::assertSame(0x8006, $storedState->pc);
    }

    /**
     * Writes and reads sprite RAM through the CPU-visible PPU registers.
     */
    #[Test]
    public function it_accesses_ppu_registers_through_bus(): void
    {
        [, $bus] = $this->createTestSystem();
        $bus->writeByCpu(0x2003, 0x20);
        $bus->writeByCpu(0x2004, 0xA5);
        $bus->writeByCpu(0x2004, 0x5A);

        $bus->writeByCpu(0x2003, 0x20);
        $first = $bus->readByCpu(0x2004);
        $repeated = $bus->readByCpu(0x2004);

        $bus->writeByCpu(0x2003, 0x21);

        $this::assertSame([0xA5, 0xA5], [$first, $repeated]);
        $this::assertSame(0x5A, $bus->readByCpu(0x2004));
    }

    /**
     * Decodes mirrored OAMADDR and OAMDATA accesses to the same PPU state.
     */
    #[Test]
    public function it_handles_ppu_register_mirroring(): void
    {
        [, $bus] = $this->createTestSystem();
        $bus->writeByCpu(0x3FFB, 0xF0);
        $bus->writeByCpu(0x3FFC, 0x5A);

        $bus->writeByCpu(0x2003, 0xF0);
        $this::assertSame(0x5A, $bus->readByCpu(0x200C));

        $bus->writeByCpu(0x200B, 0xF1);
        $bus->writeByCpu(0x200C, 0xA5);
        $bus->writeByCpu(0x3FFB, 0xF1);
        $this::assertSame(0xA5, $bus->readByCpu(0x2004));
        $this::assertSame(0xA5, $bus->readByCpu(0x3FFC));
    }
}
