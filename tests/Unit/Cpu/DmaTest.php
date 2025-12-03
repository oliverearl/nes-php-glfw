<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu;

use App\Bus\Ram;
use App\Cpu\Dma;
use App\Graphics\Ppu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dma::class)]
final class DmaTest extends TestCase
{
    #[Test]
    public function it_initializes_without_processing(): void
    {
        $ram = new Ram(0x800);
        $ppu = $this->createMock(Ppu::class);

        $dma = new Dma($ram, $ppu);

        $this::assertFalse($dma->isDmaProcessing());
    }

    #[Test]
    public function it_starts_processing_on_write(): void
    {
        $ram = new Ram(0x800);
        $ppu = $this->createMock(Ppu::class);

        $dma = new Dma($ram, $ppu);

        $dma->write(0x02);

        $this::assertTrue($dma->isDmaProcessing());
    }

    #[Test]
    public function it_transfers_256_bytes_to_ppu(): void
    {
        $ram = new Ram(0x800);

        // Write test data to RAM starting at 0x0200
        for ($i = 0; $i < 0x100; $i++) {
            $ram->write(0x0200 + $i, $i);
        }

        $ppu = $this->createMock(Ppu::class);

        // Expect transferSprite to be called 256 times
        $ppu->expects($this->exactly(256))
            ->method('transferSprite')
            ->willReturnCallback(function (int $index, int $data): void {
                $this::assertSame($index, $data);
            });

        $dma = new Dma($ram, $ppu);

        $dma->write(0x02); // Start DMA from 0x0200
        $dma->runDma();
    }

    #[Test]
    public function it_stops_processing_after_run(): void
    {
        $ram = new Ram(0x800);
        $ppu = $this->createMock(Ppu::class);

        $ppu->method('transferSprite');

        $dma = new Dma($ram, $ppu);

        $dma->write(0x02);
        $this::assertTrue($dma->isDmaProcessing());

        $dma->runDma();
        $this::assertFalse($dma->isDmaProcessing());
    }

    #[Test]
    public function it_does_nothing_when_not_processing(): void
    {
        $ram = new Ram(0x800);
        $ppu = $this->createMock(Ppu::class);

        // Should not call transferSprite if not processing
        $ppu->expects($this->never())
            ->method('transferSprite');

        $dma = new Dma($ram, $ppu);

        $dma->runDma(); // No write() called before
    }

    #[Test]
    public function it_calculates_address_from_page(): void
    {
        $ram = new Ram(0x10000);

        // Write test data to page 0x03
        for ($i = 0; $i < 0x100; $i++) {
            $ram->write(0x0300 + $i, 0xAA);
        }

        $ppu = $this->createMock(Ppu::class);

        $ppu->expects($this->exactly(256))
            ->method('transferSprite')
            ->with(
                $this->anything(),
                0xAA, // All data should be 0xAA
            );

        $dma = new Dma($ram, $ppu);

        $dma->write(0x03); // Page 0x03 -> address 0x0300
        $dma->runDma();
    }

    #[Test]
    public function it_handles_page_zero(): void
    {
        $ram = new Ram(0x800);

        for ($i = 0; $i < 0x100; $i++) {
            $ram->write($i, $i);
        }

        $ppu = $this->createMock(Ppu::class);

        $ppu->expects($this->exactly(256))
            ->method('transferSprite');

        $dma = new Dma($ram, $ppu);

        $dma->write(0x00); // Page 0x00 -> address 0x0000
        $dma->runDma();
    }

    #[Test]
    public function it_handles_high_page(): void
    {
        $ram = new Ram(0x10000);

        // Write data to page 0xFF
        for ($i = 0; $i < 0x100; $i++) {
            $ram->write(0xFF00 + $i, 0x55);
        }

        $ppu = $this->createMock(Ppu::class);

        $ppu->expects($this->exactly(256))
            ->method('transferSprite')
            ->with(
                $this->anything(),
                0x55,
            );

        $dma = new Dma($ram, $ppu);

        $dma->write(0xFF); // Page 0xFF -> address 0xFF00
        $dma->runDma();
    }

    #[Test]
    public function it_can_run_multiple_dma_transfers(): void
    {
        $ram = new Ram(0x10000);

        // First transfer data
        for ($i = 0; $i < 0x100; $i++) {
            $ram->write(0x0200 + $i, 0x11);
        }

        // Second transfer data
        for ($i = 0; $i < 0x100; $i++) {
            $ram->write(0x0300 + $i, 0x22);
        }

        $ppu = $this->createMock(Ppu::class);

        $ppu->expects($this->exactly(512)) // 256 * 2
            ->method('transferSprite');

        $dma = new Dma($ram, $ppu);

        // First DMA
        $dma->write(0x02);
        $dma->runDma();

        // Second DMA
        $dma->write(0x03);
        $dma->runDma();
    }
}
