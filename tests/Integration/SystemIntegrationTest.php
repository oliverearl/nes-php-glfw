<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bus\CpuBus;
use App\Cartridge\Cartridge;
use App\Cpu\Cpu;
use App\Graphics\Ppu;
use App\Graphics\Renderer;
use PHPUnit\Framework\Attributes\Test;

final class SystemIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function it_initializes_complete_system_from_cartridge(): void
    {
        $programRom = array_fill(0, 0x4000, 0xEA);
        $characterRom = array_fill(0, 0x2000, 0);

        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: $programRom,
            characterRom: $characterRom,
        );

        [$cpu, $cpuBus, $ram, $programRomObj, $ppu, $interrupts, $dma, $gamepad] = $this->createTestSystemWithRom(
            romData: $cartridge->programRom,
            horizontalMirror: $cartridge->isHorizontalMirror,
        );

        $this::assertInstanceOf(Cpu::class, $cpu);
        $this::assertInstanceOf(Ppu::class, $ppu);
        $this::assertInstanceOf(CpuBus::class, $cpuBus);
    }

    /**
     * Preserves residual PPU clocks while the CPU executes a fixed-duration loop.
     */
    #[Test]
    public function it_runs_cpu_and_ppu_in_sync(): void
    {
        [$cpu, , , , $ppu] = $this->createTestSystem(setupResetVector: true);
        $cpu->reset();
        $elapsed = 0;

        for ($frame = 1; $frame <= 3; $frame++) {
            $complete = false;

            for ($instruction = 0; $instruction < 10000; $instruction++) {
                $cycles = $cpu->run();
                $elapsed += $cycles;

                if ($ppu->run($cycles * 3) !== false) {
                    $complete = true;
                    break;
                }
            }

            $this::assertTrue($complete);
            $this::assertSame(intdiv($frame * 341 * 262 + 8, 9) * 3, $elapsed);
            $this::assertSame(0x8000, $cpu->snapshot()->pc);
        }
    }

    #[Test]
    public function it_completes_full_frame_rendering_cycle(): void
    {
        [$cpu, $cpuBus, $ram, $programRom, $ppu] = $this->createTestSystem();
        $renderer = new Renderer();

        $cpu->reset();

        $renderingData = false;
        $maxIterations = 50000;
        $iterations = 0;

        while ($renderingData === false && $iterations < $maxIterations) {
            $cpuCycles = $cpu->run();
            $renderingData = $ppu->run($cpuCycles * 3);
            $iterations++;
        }

        $this::assertNotFalse($renderingData);

        $frameBuffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $frameBuffer);
    }

    #[Test]
    public function it_handles_dma_during_frame(): void
    {
        [$cpu, $cpuBus, $ram, , $ppu, , $dma] = $this->createTestSystem();

        for ($i = 0; $i < 256; $i++) {
            $ram->write(0x0200 + $i, $i);
        }

        $cpuBus->writeByCpu(0x4014, 0x02);

        if ($dma->isDmaProcessing()) {
            $dma->runDma();
        }

        for ($i = 0; $i < 100; $i++) {
            $cpuCycles = $cpu->run();
            $ppu->run($cpuCycles * 3);
        }

        $this::assertFalse($dma->isDmaProcessing());
    }

    /**
     * Executes CPU stores and loads through PPU registers, including OAM address wrap.
     */
    #[Test]
    public function it_reads_and_writes_ppu_registers_during_execution(): void
    {
        $program = [0xA9, 0xFE, 0x8D, 0x03, 0x20,
            0xA9, 0xA5, 0x8D, 0x04, 0x20,
            0xA9, 0x5A, 0x8D, 0x04, 0x20,
            0xA9, 0x11, 0x8D, 0x04, 0x20,
            0xA9, 0xFF, 0x8D, 0x03, 0x20,
            0xAD, 0x04, 0x20, 0x8D, 0x00, 0x02];
        $rom = array_fill(0, 0x8000, 0xEA);
        foreach ($program as $offset => $byte) {
            $rom[$offset] = $byte;
        }
        $rom[0x7FFC] = 0;
        $rom[0x7FFD] = 0x80;
        [$cpu, $bus, $ram] = $this->createTestSystemWithRom($rom);
        $cpu->reset();
        $cycles = [];

        for ($instruction = 0; $instruction < 12; $instruction++) {
            $cycles[] = $cpu->run();
        }

        $this::assertSame([2, 4, 2, 4, 2, 4, 2, 4, 2, 4, 4, 4], $cycles);
        $this::assertSame(0x5A, $ram->read(0x0200));

        foreach ([0xFE => 0xA5, 0xFF => 0x5A, 0 => 0x11] as $address => $expected) {
            $bus->writeByCpu(0x2003, $address);
            $this::assertSame($expected, $bus->readByCpu(0x2004));
        }
    }

    #[Test]
    public function it_maintains_system_state_across_frames(): void
    {
        [$cpu, $cpuBus, $ram, , $ppu] = $this->createTestSystem(setupResetVector: true);

        $ram->write(0x0100, 0x42);
        $cpu->reset();

        for ($frame = 0; $frame < 3; $frame++) {
            $renderingData = false;
            $iterations = 0;

            while ($renderingData === false && $iterations < 50000) {
                $cpuCycles = $cpu->run();
                $renderingData = $ppu->run($cpuCycles * 3);
                $iterations++;
            }

            $this::assertSame(0x42, $ram->read(0x0100));
        }
    }

    #[Test]
    public function it_handles_palette_writes_and_rendering(): void
    {
        [$cpu, $cpuBus, $ram, , $ppu] = $this->createTestSystem(setupResetVector: true);
        $renderer = new Renderer();

        $cpu->reset();

        $cpuBus->writeByCpu(0x2006, 0x3F);
        $cpuBus->writeByCpu(0x2006, 0x00);

        for ($i = 0; $i < 32; $i++) {
            $cpuBus->writeByCpu(0x2007, $i);
        }

        $renderingData = false;
        $iterations = 0;

        while ($renderingData === false && $iterations < 50000) {
            $cpuCycles = $cpu->run();
            $renderingData = $ppu->run($cpuCycles * 3);
            $iterations++;
        }

        if ($renderingData !== false) {
            $frameBuffer = $renderer->render($renderingData);
            $this::assertCount(256 * 224 * 4, $frameBuffer);
        }

        $this::assertNotFalse($renderingData);
    }
}
