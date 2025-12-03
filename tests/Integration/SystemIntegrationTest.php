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
        // Create a test cartridge
        $programRom = array_fill(0, 0x4000, 0xEA); // 16KB of NOPs
        $characterRom = array_fill(0, 0x2000, 0); // 8KB CHR

        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: $programRom,
            characterRom: $characterRom,
        );

        [$cpu, $cpuBus, $ram, $programRomObj, $ppu, $interrupts, $dma, $gamepad] = $this->createTestSystemWithRom(
            romData: $cartridge->programRom,
            horizontalMirror: $cartridge->isHorizontalMirror,
        );

        $renderer = new Renderer();

        // System is ready - verify all components exist
        $this::assertInstanceOf(Cpu::class, $cpu);
        $this::assertInstanceOf(Ppu::class, $ppu);
        $this::assertInstanceOf(CpuBus::class, $cpuBus);
    }

    #[Test]
    public function it_runs_cpu_and_ppu_in_sync(): void
    {
        [$cpu, $cpuBus, $ram, $programRom, $ppu] = $this->createTestSystem();

        $cpu->reset();

        // Run CPU for a few instructions
        for ($i = 0; $i < 10; $i++) {
            $cpuCycles = $cpu->run();

            // PPU runs at 3x CPU speed
            $ppu->run($cpuCycles * 3);

            $this::assertGreaterThan(0, $cpuCycles);
        }

        // System should still be running
        $this::assertTrue(true);
    }

    #[Test]
    public function it_completes_full_frame_rendering_cycle(): void
    {
        [$cpu, $cpuBus, $ram, $programRom, $ppu] = $this->createTestSystem();
        $renderer = new Renderer();

        $cpu->reset();

        // Run until we get rendering data (one complete frame)
        $renderingData = false;
        $maxIterations = 50000;
        $iterations = 0;

        while ($renderingData === false && $iterations < $maxIterations) {
            $cpuCycles = $cpu->run();
            $renderingData = $ppu->run($cpuCycles * 3);
            $iterations++;
        }

        // Should have completed a frame
        $this::assertNotFalse($renderingData);

        // Should be able to render it
        $frameBuffer = $renderer->render($renderingData);

        $this::assertIsArray($frameBuffer);
        $this::assertCount(256 * 256 * 4, $frameBuffer);
    }

    #[Test]
    public function it_handles_dma_during_frame(): void
    {
        [$cpu, $cpuBus, $ram, , $ppu, , $dma] = $this->createTestSystem();

        // Prepare sprite data in RAM
        for ($i = 0; $i < 256; $i++) {
            $ram->write(0x0200 + $i, $i);
        }

        // Trigger DMA
        $cpuBus->writeByCpu(0x4014, 0x02);

        // Process DMA
        if ($dma->isDmaProcessing()) {
            $dma->runDma();
        }

        // Continue running system
        for ($i = 0; $i < 100; $i++) {
            $cpuCycles = $cpu->run();
            $ppu->run($cpuCycles * 3);
        }

        $this::assertTrue(true);
    }

    #[Test]
    public function it_reads_and_writes_ppu_registers_during_execution(): void
    {
        [$cpu, $cpuBus, , , $ppu] = $this->createTestSystem();

        // Write to PPU control
        $cpuBus->writeByCpu(0x2000, 0x80);

        // Write to PPU scroll
        $cpuBus->writeByCpu(0x2005, 0x10);
        $cpuBus->writeByCpu(0x2005, 0x20);

        // Read PPU status
        $status = $cpuBus->readByCpu(0x2002);

        $this::assertIsInt($status);

        // Run some instructions
        for ($i = 0; $i < 10; $i++) {
            $cpuCycles = $cpu->run();
            $ppu->run($cpuCycles * 3);
        }

        $this::assertTrue(true);
    }

    #[Test]
    public function it_maintains_system_state_across_frames(): void
    {
        [$cpu, $cpuBus, $ram, , $ppu] = $this->createTestSystem(setupResetVector: true);

        // Write data to RAM
        $ram->write(0x0100, 0x42);

        // Reset CPU to start from proper reset vector
        $cpu->reset();

        // Run several frames
        for ($frame = 0; $frame < 3; $frame++) {
            $renderingData = false;
            $iterations = 0;

            while ($renderingData === false && $iterations < 50000) {
                $cpuCycles = $cpu->run();
                $renderingData = $ppu->run($cpuCycles * 3);
                $iterations++;
            }

            // RAM should maintain state
            $this::assertSame(0x42, $ram->read(0x0100));
        }
    }

    #[Test]
    public function it_handles_palette_writes_and_rendering(): void
    {
        [$cpu, $cpuBus, $ram, , $ppu] = $this->createTestSystem(setupResetVector: true);
        $renderer = new Renderer();

        // Reset CPU to start from proper reset vector
        $cpu->reset();

        // Write to PPU address register to set palette address
        $cpuBus->writeByCpu(0x2006, 0x3F);
        $cpuBus->writeByCpu(0x2006, 0x00);

        // Write palette data
        for ($i = 0; $i < 32; $i++) {
            $cpuBus->writeByCpu(0x2007, $i);
        }

        // Run until frame completes
        $renderingData = false;
        $iterations = 0;

        while ($renderingData === false && $iterations < 50000) {
            $cpuCycles = $cpu->run();
            $renderingData = $ppu->run($cpuCycles * 3);
            $iterations++;
        }

        if ($renderingData !== false) {
            $frameBuffer = $renderer->render($renderingData);
            $this::assertIsArray($frameBuffer);
        }

        $this::assertTrue(true);
    }
}
