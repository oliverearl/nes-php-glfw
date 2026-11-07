<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Graphics\Ppu;
use PHPUnit\Framework\Attributes\Test;

final class DmaIntegrationTest extends IntegrationTestCase
{
    /**
     * Checks that the emulator transfers every byte and wraps at the current oam address.
     */
    #[Test]
    public function it_transfers_every_byte_and_wraps_at_the_current_oam_address(): void
    {
        [, $bus, $ram, , $ppu, , $dma] = $this->createTestSystem();

        for ($i = 0; $i < 256; $i++) {
            $ram->write(0x0200 + $i, $i ^ 0xA5);
        }

        $ppu->write(3, 0xF0);
        $bus->writeByCpu(0x4014, 0x02);
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();

        $this::assertFalse($dma->isDmaProcessing());
        $this->assertOam($ppu, 0xF0);
    }

    /**
     * Checks that the emulator uses cpu ram mirrors for dma source addresses.
     */
    #[Test]
    public function it_uses_cpu_ram_mirrors_for_dma_source_addresses(): void
    {
        [, $bus, $ram, , $ppu, , $dma] = $this->createTestSystem();

        for ($i = 0; $i < 256; $i++) {
            $ram->write($i, $i ^ 0xA5);
        }

        $bus->writeByCpu(0x4014, 0x08);
        $dma->runDma();

        $this->assertOam($ppu);
    }

    /**
     * Checks that the emulator reads a cartridge mapped page during dma.
     */
    #[Test]
    public function it_reads_a_cartridge_mapped_page_during_dma(): void
    {
        $rom = array_fill(0, 0x8000, 0xEA);

        for ($i = 0; $i < 256; $i++) {
            $rom[$i] = $i ^ 0xA5;
        }

        [, $bus, , , $ppu, , $dma] = $this->createTestSystemWithRom($rom);
        $bus->writeByCpu(0x4014, 0x80);
        $dma->runDma();

        $this->assertOam($ppu);
    }

    /**
     * Checks that the emulator triggers dma from an executed store instruction.
     */
    #[Test]
    public function it_triggers_dma_from_an_executed_store_instruction(): void
    {
        $rom = array_fill(0, 0x8000, 0xEA);
        foreach ([0xA9, 0x02, 0x8D, 0x14, 0x40] as $offset => $byte) {
            $rom[$offset] = $byte;
        }
        $rom[0x7FFC] = 0;
        $rom[0x7FFD] = 0x80;
        [$cpu, , $ram, , $ppu, , $dma] = $this->createTestSystemWithRom($rom);
        for ($i = 0; $i < 256; $i++) {
            $ram->write(0x0200 + $i, $i ^ 0xA5);
        }
        $cpu->reset();

        $this::assertSame(2, $cpu->run());
        $this::assertFalse($dma->isDmaProcessing());
        $this::assertSame(4, $cpu->run());
        $this::assertTrue($dma->isDmaProcessing());
        $dma->runDma();

        $this->assertOam($ppu);
    }

    /**
     * Checks all transferred bytes through CPU-visible OAM registers.
     */
    private function assertOam(Ppu $ppu, int $start = 0): void
    {
        for ($i = 0; $i < 256; $i++) {
            $ppu->write(3, ($start + $i) & 0xFF);
            $this::assertSame($i ^ 0xA5, $ppu->read(4), "DMA byte {$i}.");
        }
    }
}
