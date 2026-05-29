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
