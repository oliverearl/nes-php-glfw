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
     * Get the path to a test ROM file.
     */
    protected function getTestRomPath(string $filename = 'HelloWorld.nes'): string
    {
        return __DIR__ . '/../TestRoms/' . $filename;
    }

    /**
     * Create a complete test system with all components initialized.
     *
     * @return array{0: Cpu, 1: CpuBus, 2: Ram, 3: Rom, 4: Ppu, 5: Interrupts, 6: Dma, 7: Gamepad}
     */
    protected function createTestSystem(
        ?int $ramSize = 0x800,
        ?int $romSize = 0x8000,
        ?int $characterRomSize = 0x2000,
        bool $horizontalMirror = true
    ): array {
        $ram = new Ram($ramSize);
        $programRom = new Rom(array_fill(0, $romSize, 0xEA)); // Fill with NOPs
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
        bool $horizontalMirror = true
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
        bool $horizontalMirror = true
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

