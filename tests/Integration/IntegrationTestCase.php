<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bus\CpuBus;
use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Bus\Rom;
use App\Cpu\Cpu;
use App\Cpu\Dma;
use App\Cpu\Interrupts;
use App\Graphics\Ppu;
use App\Input\Gamepad;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class IntegrationTestCase extends BaseTestCase
{
    /**
     * Get the path to a test ROM file and skip the test if it doesn't exist.
     */
    protected function requireTestRom(string $filename = 'HelloWorld.nes'): string
    {
        $path = __DIR__ . '/../TestRoms/' . $filename;

        if (!file_exists($path)) {
            $this::markTestSkipped("Test ROM file not found at: {$path}");
        }

        return $path;
    }

    /**
     * Get the path to a test ROM file without checking existence.
     * Use this when you need the path but don't want to skip the test.
     */
    protected function getTestRomPath(string $filename): string
    {
        return __DIR__ . '/../TestRoms/' . $filename;
    }

    /**
     * Create a complete test system with all components initialized.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @return array{0: Cpu, 1: CpuBus, 2: Ram, 3: Rom, 4: Ppu, 5: Interrupts, 6: Dma, 7: Gamepad}
     */
    protected function createTestSystem(
        ?int $ramSize = 0x800,
        ?int $romSize = 0x8000,
        ?int $characterRomSize = 0x2000,
        bool $horizontalMirror = true,
        bool $setupResetVector = false,
    ): array {
        $ram = new Ram($ramSize);

        // Create ROM data
        $romData = array_fill(0, $romSize, 0xEA); // Fill with NOPs

        if ($setupResetVector) {
            // Set up reset vector to point to 0x8000 (start of ROM)
            // Reset vector is at 0xFFFC-0xFFFD (last 4 bytes of address space)
            // In ROM space, that's offset 0x7FFC-0x7FFD (for 32KB ROM starting at 0x8000)
            $romData[0x7FFC] = 0x00; // Low byte of 0x8000
            $romData[0x7FFD] = 0x80; // High byte of 0x8000

            // Put an infinite loop at 0x8000: JMP $8000 (0x4C 0x00 0x80)
            $romData[0x0000] = 0x4C; // JMP absolute
            $romData[0x0001] = 0x00; // Low byte of target address
            $romData[0x0002] = 0x80; // High byte of target address
        }

        $programRom = new Rom($romData);
        $interrupts = new Interrupts();
        $characterRom = new Ram($characterRomSize);
        $ppuBus = new PpuBus($characterRom);
        $ppu = new Ppu($ppuBus, $interrupts, $horizontalMirror);
        $gamepad = $this->createMock(Gamepad::class);
        $dma = new Dma($ram, $ppu);
        $cpuBus = new CpuBus($ram, $programRom, $ppu, $gamepad, $dma);
        $cpu = new Cpu($cpuBus, $interrupts);

        return [$cpu, $cpuBus, $ram, $programRom, $ppu, $interrupts, $dma, $gamepad];
    }

    /**
     * Create a minimal PPU setup for testing PPU-specific functionality.
     *
     * @return array{0: Ppu, 1: PpuBus, 2: Ram, 3: Interrupts}
     */
    protected function createPpuSystem(
        ?int $characterRomSize = 0x2000,
        bool $horizontalMirror = true,
    ): array {
        $characterRom = new Ram($characterRomSize);
        $ppuBus = new PpuBus($characterRom);
        $interrupts = new Interrupts();
        $ppu = new Ppu($ppuBus, $interrupts, $horizontalMirror);

        return [$ppu, $ppuBus, $characterRom, $interrupts];
    }

    /**
     * Create a custom ROM with specific data.
     *
     * @param array<int> $data The ROM data
     */
    protected function createRomWithData(array $data): Rom
    {
        return new Rom($data);
    }

    /**
     * Create a test system with custom ROM data.
     *
     * @param array<int> $romData Custom ROM data
     * @return array{0: Cpu, 1: CpuBus, 2: Ram, 3: Rom, 4: Ppu, 5: Interrupts, 6: Dma, 7: Gamepad}
     */
    protected function createTestSystemWithRom(
        array $romData,
        ?int $ramSize = 0x800,
        ?int $characterRomSize = 0x2000,
        bool $horizontalMirror = true,
    ): array {
        $ram = new Ram($ramSize);
        $programRom = new Rom($romData);
        $interrupts = new Interrupts();
        $characterRom = new Ram($characterRomSize);
        $ppuBus = new PpuBus($characterRom);
        $ppu = new Ppu($ppuBus, $interrupts, $horizontalMirror);
        $gamepad = $this->createMock(Gamepad::class);
        $dma = new Dma($ram, $ppu);
        $cpuBus = new CpuBus($ram, $programRom, $ppu, $gamepad, $dma);
        $cpu = new Cpu($cpuBus, $interrupts);

        return [$cpu, $cpuBus, $ram, $programRom, $ppu, $interrupts, $dma, $gamepad];
    }
}
