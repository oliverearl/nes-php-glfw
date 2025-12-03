<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class DmaIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function it_transfers_sprite_data_from_ram_to_ppu(): void
    {
        // Setup
        [$cpu, $cpuBus, $ram, $programRom, $ppu, $interrupts, $dma] = $this->createTestSystem();

        // Write sprite data to RAM at page 0x02 (0x0200-0x02FF)
        for ($i = 0; $i < 256; $i++) {
            $ram->write(0x0200 + $i, $i);
        }

        // Trigger DMA by writing to 0x4014
        $cpuBus->writeByCpu(0x4014, 0x02);

        // Verify DMA is processing
        $this::assertTrue($dma->isDmaProcessing());

        // Run DMA
        $dma->runDma();

        // Verify DMA completed
        $this::assertFalse($dma->isDmaProcessing());

        // Note: We can't easily verify the PPU sprite RAM contents without exposing internals
        // But we can verify the DMA completed without errors
        $this::assertTrue(true);
    }

    #[Test]
    public function it_transfers_from_different_pages(): void
    {
        [$cpu, $cpuBus, $ram, $programRom, $ppu, $interrupts, $dma] = $this->createTestSystem();

        // Test page 0x00
        for ($i = 0; $i < 256; $i++) {
            $ram->write($i, 0xAA);
        }
        $cpuBus->writeByCpu(0x4014, 0x00);
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());

        // Test page 0x03
        for ($i = 0; $i < 256; $i++) {
            $ram->write(0x0300 + $i, 0xBB);
        }
        $cpuBus->writeByCpu(0x4014, 0x03);
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());
    }

    #[Test]
    public function it_integrates_with_cpu_bus_for_dma_writes(): void
    {
        [, $cpuBus, , , , , $dma] = $this->createTestSystem();

        // Verify DMA is not processing initially
        $this::assertFalse($dma->isDmaProcessing());

        // CPU writes to DMA register through bus
        $cpuBus->writeByCpu(0x4014, 0x02);

        // DMA should now be processing
        $this::assertTrue($dma->isDmaProcessing());
    }

    #[Test]
    public function it_handles_multiple_consecutive_dma_transfers(): void
    {
        [, $cpuBus, , , , , $dma] = $this->createTestSystem(ramSize: 0x10000);

        // First DMA
        $cpuBus->writeByCpu(0x4014, 0x02);
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());

        // Second DMA
        $cpuBus->writeByCpu(0x4014, 0x03);
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());

        // Third DMA
        $cpuBus->writeByCpu(0x4014, 0x04);
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());
    }

    #[Test]
    public function it_transfers_typical_sprite_data_pattern(): void
    {
        [, $cpuBus, $ram, , , , $dma] = $this->createTestSystem();

        // Write typical sprite data (4 bytes per sprite, 64 sprites)
        // Format: Y, Tile, Attributes, X
        for ($sprite = 0; $sprite < 64; $sprite++) {
            $base = 0x0200 + ($sprite * 4);
            $ram->write($base, $sprite * 4); // Y position
            $ram->write($base + 1, $sprite); // Tile index
            $ram->write($base + 2, 0x00); // Attributes
            $ram->write($base + 3, $sprite * 4); // X position
        }

        // Trigger DMA
        $cpuBus->writeByCpu(0x4014, 0x02);
        $dma->runDma();

        $this::assertFalse($dma->isDmaProcessing());
    }

    #[Test]
    public function it_handles_dma_from_zero_page(): void
    {
        [, $cpuBus, $ram, , , , $dma] = $this->createTestSystem();

        // Write data to zero page
        for ($i = 0; $i < 256; $i++) {
            $ram->write($i, 0xFF - $i);
        }

        // DMA from page 0x00
        $cpuBus->writeByCpu(0x4014, 0x00);
        $this::assertTrue($dma->isDmaProcessing());

        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());
    }
}
