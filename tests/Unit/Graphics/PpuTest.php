<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics;

use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Cpu\Interrupts;
use App\Graphics\Objects\RenderingData;
use App\Graphics\Ppu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ppu::class)]
final class PpuTest extends TestCase
{
    #[Test]
    public function it_prepares_sprite_data_once_per_frame(): void
    {
        $ppu = $this->createPpu();
        $ppu->write(0x01, 0x10);
        $this->writeSprite($ppu, 0x00, 16, 3, 0, 20);

        $this::assertFalse($ppu->run(1));

        $this->writeSprite($ppu, 0x00, 16, 7, 0, 40);

        $renderingData = $this->runUntilFrameCompletes($ppu);

        $this::assertNotNull($renderingData->sprites);
        $this::assertNotEmpty($renderingData->sprites);
        $this::assertSame(3, $renderingData->sprites[0]->id);
    }

    #[Test]
    public function it_skips_offscreen_sprites_without_dropping_later_visible_sprites(): void
    {
        $ppu = $this->createPpu();
        $ppu->write(0x01, 0x10);
        $this->writeSprite($ppu, 0x00, 0, 1, 0, 10);
        $this->writeSprite($ppu, 0x04, 24, 4, 0, 40);

        $renderingData = $this->runUntilFrameCompletes($ppu);

        $this::assertNotNull($renderingData->sprites);
        $this::assertCount(1, $renderingData->sprites);
        $this::assertSame(4, $renderingData->sprites[0]->id);
    }

    #[Test]
    public function it_keeps_vram_address_progression_when_writing_to_nametable_mirrors(): void
    {
        $ppu = $this->createPpu();

        $this->writeVramAddress($ppu, 0x3000);
        $ppu->write(0x07, 0x11);
        $ppu->write(0x07, 0x22);

        $this->writeVramAddress($ppu, 0x2000);
        $ppu->read(0x07);

        $this::assertSame(0x11, $ppu->read(0x07));
        $this::assertSame(0x22, $ppu->read(0x07));
    }

    /**
     * Creates a PPU instance with blank character RAM.
     */
    private function createPpu(): Ppu
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);
        $interrupts = new Interrupts();

        return new Ppu($ppuBus, $interrupts, true);
    }

    /**
     * Writes a single sprite entry to OAM.
     */
    private function writeSprite(Ppu $ppu, int $offset, int $y, int $id, int $attribute, int $x): void
    {
        $ppu->write(0x03, $offset);
        $ppu->write(0x04, $y);
        $ppu->write(0x04, $id);
        $ppu->write(0x04, $attribute);
        $ppu->write(0x04, $x);
    }

    /**
     * Writes a complete 16-bit VRAM address through PPUADDR.
     */
    private function writeVramAddress(Ppu $ppu, int $address): void
    {
        $ppu->write(0x06, ($address >> 8) & 0xFF);
        $ppu->write(0x06, $address & 0xFF);
    }

    /**
     * Runs the PPU until a complete frame is available.
     */
    private function runUntilFrameCompletes(Ppu $ppu): RenderingData
    {
        $renderingData = false;
        $iterations = 0;

        while ($renderingData === false && $iterations < 400) {
            $renderingData = $ppu->run(341);
            $iterations++;
        }

        $this::assertNotFalse($renderingData);

        return $renderingData;
    }
}
