<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics;

use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Cpu\Interrupts;
use App\Graphics\Objects\RenderingData;
use App\Graphics\Ppu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ppu::class)]
final class PpuTest extends TestCase
{
    /**
     * Checks that PPUSCROLL and PPUADDR use the same first/second-write latch.
     */
    #[Test]
    public function it_shares_the_scroll_and_address_write_latch(): void
    {
        $characterRam = new Ram(0x2000);
        $ppu = new Ppu(new PpuBus($characterRam), new Interrupts(), true);
        $ppu->write(0x05, 0x08);
        $ppu->write(0x06, 0x34);
        $ppu->write(0x07, 0x55);

        $this::assertSame(0x55, $characterRam->read(0x0034));

        $ppu->write(0x06, 0x21);
        $ppu->write(0x05, 0x18);
        $this->writeVramAddress($ppu, 0x2010);
        $ppu->write(0x07, 0x77);

        $this::assertSame(0x77, $this->readVram($ppu, 0x2010));
    }

    /**
     * Checks that reading PPUSTATUS restarts both address and scroll writes.
     */
    #[Test]
    public function it_resets_the_shared_write_latch_on_status_read(): void
    {
        $ppu = $this->createPpu();
        $ppu->write(0x06, 0x21);
        $ppu->read(0x02);
        $this->writeVramAddress($ppu, 0x3F01);
        $ppu->write(0x07, 0x2A);

        $this->writeVramAddress($ppu, 0x3F01);
        $this::assertSame(0x2A, $ppu->read(0x07));

        $ppu->write(0x05, 8);
        $ppu->read(0x02);
        $ppu->write(0x05, 16);
        $ppu->write(0x05, 24);
        $ppu->write(0x01, 0x08);
        $frame = $this->runUntilFrameCompletes($ppu);

        $this::assertNotNull($frame->background);
        $this::assertSame(16, $frame->background[0]->scrollX);
        $this::assertSame(24, $frame->background[0]->scrollY);
    }

    /**
     * Checks PPUCTRL supplies nametable bits to the shared temporary address.
     */
    #[Test]
    public function it_preserves_control_nametable_bits_in_interleaved_address_writes(): void
    {
        $characterRam = new Ram(0x2000);
        $ppu = new Ppu(new PpuBus($characterRam), new Interrupts(), true);
        $ppu->write(0, 0x03);
        $ppu->write(0x05, 0x80);
        $ppu->write(0x06, 0x34);
        $ppu->write(0x07, 0x55);

        $this::assertSame(0x55, $characterRam->read(0x0C34));
        $this::assertSame(0, $characterRam->read(0x0034));
    }

    /**
     * Checks the first PPUADDR write changes temporary storage without changing PPUDATA access.
     */
    #[Test]
    public function it_commits_the_address_only_after_the_second_address_write(): void
    {
        $characterRam = new Ram(0x2000);
        $ppu = new Ppu(new PpuBus($characterRam), new Interrupts(), true);
        $this->writeVramAddress($ppu, 0x0100);
        $ppu->write(0x07, 0x11);
        $ppu->write(0x06, 0x02);
        $ppu->write(0x07, 0x55);
        $ppu->write(0x06, 0x20);
        $ppu->write(0x07, 0x77);

        $this::assertSame(0x11, $characterRam->read(0x0100));
        $this::assertSame(0x55, $characterRam->read(0x0101));
        $this::assertSame(0x77, $characterRam->read(0x0220));
    }

    /**
     * Checks that writes cannot assign the read-only PPUSTATUS flag bits.
     */
    #[Test]
    public function it_ignores_writes_to_status_and_clears_vblank_only_on_read(): void
    {
        $ppu = $this->createPpu();
        $ppu->write(0x02, 0xE0);
        $this::assertSame(0, $ppu->read(0x02) & 0xE0);

        $ppu->run(341 * 241);
        $ppu->write(0x02, 0);
        $this::assertSame(0x80, $ppu->read(0x02) & 0x80);
        $this::assertSame(0, $ppu->read(0x02) & 0x80);
    }

    /**
     * Checks delayed PPUDATA reads across address changes and intervening writes.
     */
    #[Test]
    public function it_preserves_the_read_buffer_until_the_next_data_read(): void
    {
        $ppu = $this->createPpu();
        $this->writeVramAddress($ppu, 0x0000);
        $ppu->write(0x07, 0x12);
        $ppu->write(0x07, 0x34);
        $this->writeVramAddress($ppu, 0x2000);
        $ppu->write(0x07, 0x56);

        $this->writeVramAddress($ppu, 0x0000);
        $this::assertSame(0, $ppu->read(0x07));
        $this::assertSame(0x12, $ppu->read(0x07));
        $this->writeVramAddress($ppu, 0x2000);
        $ppu->write(0x07, 0x78);
        $this->writeVramAddress($ppu, 0x2000);
        $this::assertSame(0x34, $ppu->read(0x07));
        $this::assertSame(0x78, $ppu->read(0x07));
    }

    /**
     * Checks immediate palette reads and the shadow nametable read-buffer refill.
     */
    #[Test]
    public function it_bypasses_the_read_buffer_for_palettes_and_refills_from_nametables(): void
    {
        $ppu = $this->createPpu();
        $this->writeVramAddress($ppu, 0x2F01);
        $ppu->write(0x07, 0x66);
        $this->writeVramAddress($ppu, 0x3F01);
        $ppu->write(0x07, 0x2A);
        $ppu->write(0x07, 0x19);

        $this->writeVramAddress($ppu, 0x3F01);
        $this::assertSame(0x2A, $ppu->read(0x07) & 0x3F);
        $this->writeVramAddress($ppu, 0x0000);
        $this::assertSame(0x66, $ppu->read(0x07));
        $this->writeVramAddress($ppu, 0x3F02);
        $this::assertSame(0x19, $ppu->read(0x07) & 0x3F);
    }

    /**
     * Checks that palette aliases share storage without aliasing unused background entries.
     */
    #[Test]
    public function it_reads_palette_mirrors_and_independent_background_entries(): void
    {
        $ppu = $this->createPpu();
        $this->writeVramAddress($ppu, 0x3F00);
        $ppu->write(0x07, 0x15);
        $this->writeVramAddress($ppu, 0x3F04);
        $ppu->write(0x07, 0x23);

        foreach ([0x3F00, 0x3F10, 0x3F20, 0x3FF0] as $address) {
            $this->writeVramAddress($ppu, $address);
            $this::assertSame(0x15, $ppu->read(0x07) & 0x3F);
        }

        foreach ([0x3F04, 0x3F14, 0x3F24] as $address) {
            $this->writeVramAddress($ppu, $address);
            $this::assertSame(0x23, $ppu->read(0x07) & 0x3F);
        }
    }

    /**
     * Checks grayscale affects palette reads without destroying stored color bits.
     */
    #[Test]
    public function it_masks_palette_reads_in_grayscale_mode(): void
    {
        $ppu = $this->createPpu();
        $this->writeVramAddress($ppu, 0x3F01);
        $ppu->write(0x07, 0x2B);
        $ppu->write(0x01, 0x01);
        $this->writeVramAddress($ppu, 0x3F01);
        $this::assertSame(0x20, $ppu->read(0x07) & 0x3F);

        $ppu->write(0x01, 0);
        $this->writeVramAddress($ppu, 0x3F01);
        $this::assertSame(0x2B, $ppu->read(0x07) & 0x3F);
    }

    /**
     * Checks hardware nametable aliases through the CPU-facing data port.
     *
     * @param list<int> $expected
     */
    #[Test]
    #[DataProvider('nametableMirroringModes')]
    public function it_applies_nametable_mirroring_to_all_data_accesses(bool $horizontal, array $expected): void
    {
        $ppu = $this->createPpu($horizontal);

        foreach ([0x2000, 0x2400, 0x2800, 0x2C00] as $index => $address) {
            $this->writeVramAddress($ppu, $address + 0x3C0);
            $ppu->write(0x07, $index + 1);
        }

        foreach ([0x2000, 0x2400, 0x2800, 0x2C00] as $index => $address) {
            $this::assertSame($expected[$index], $this->readVram($ppu, $address + 0x3C0));
            $this->writeVramAddress($ppu, $address);
            $ppu->write(0x07, $index + 0x10);
            $this::assertSame($index + 0x10, $this->readVram($ppu, $address + 0x1000));
        }
    }

    /**
     * Supplies expected final values for horizontal and vertical CIRAM wiring.
     *
     * @return array<string, array{bool, list<int>}>
     */
    public static function nametableMirroringModes(): array
    {
        return ['horizontal' => [true, [2, 2, 4, 4]], 'vertical' => [false, [3, 4, 3, 4]]];
    }

    /**
     * Checks address masking, increment selection, and wrapping through PPUDATA.
     */
    #[Test]
    public function it_masks_address_writes_and_wraps_data_accesses(): void
    {
        $ppu = $this->createPpu();
        $this->writeVramAddress($ppu, 0x6000);
        $ppu->write(0x07, 0x55);
        $this::assertSame(0x55, $this->readVram($ppu, 0x2000));

        $ppu->write(0x00, 0x04);
        $this->writeVramAddress($ppu, 0x3FE0);
        $ppu->write(0x07, 0x15);
        $ppu->write(0x07, 0x77);
        $this::assertSame(0x77, $this->readVram($ppu, 0x0000));
    }

    /**
     * Checks OAMDATA wraps its byte address after writing the last OAM entry.
     */
    #[Test]
    public function it_wraps_the_oam_data_address_after_the_last_byte(): void
    {
        $ppu = $this->createPpu();
        $ppu->write(0x03, 0xFF);
        $ppu->write(0x04, 0x55);
        $ppu->write(0x04, 0x77);
        $ppu->write(0x03, 0xFF);
        $this::assertSame(0x55, $ppu->read(0x04));
        $ppu->write(0x03, 0);
        $this::assertSame(0x77, $ppu->read(0x04));
    }

    /**
     * Checks that CHR RAM writes replace previously decoded pattern data.
     */
    #[Test]
    public function it_invalidates_cached_patterns_after_character_ram_writes(): void
    {
        $ppu = $this->createPpu();
        $ppu->write(0x01, 0x08);
        $this->writeVramAddress($ppu, 0);
        $ppu->write(0x07, 0x80);
        $first = $this->runUntilFrameCompletes($ppu);
        $this::assertNotNull($first->background);
        $this::assertSame(1, $first->background[0]->pattern[0][0]);

        $this->writeVramAddress($ppu, 0);
        $ppu->write(0x07, 0);
        $this->writeVramAddress($ppu, 8);
        $ppu->write(0x07, 0x80);
        $second = $this->runUntilFrameCompletes($ppu);
        $this::assertNotNull($second->background);
        $this::assertSame(2, $second->background[0]->pattern[0][0]);
        $this::assertSame(1, $first->background[0]->pattern[0][0]);
    }

    /**
     * Checks that clocks beyond frame completion are retained for subsequent events.
     */
    #[Test]
    public function it_keeps_elapsed_clocks_across_frame_boundaries(): void
    {
        $ppu = $this->createPpu();
        $this::assertInstanceOf(RenderingData::class, $ppu->run(341 * 262 + 340));
        $this::assertFalse($ppu->run(341 * 240));
        $this::assertSame(0, $ppu->read(0x02) & 0x80);
        $this::assertFalse($ppu->run(1));
        $this::assertSame(0x80, $ppu->read(0x02) & 0x80);
        $this::assertInstanceOf(RenderingData::class, $ppu->run(341 * 21));
    }

    /**
     * Checks the same elapsed clocks produce the same frame boundaries for varied chunks.
     */
    #[Test]
    public function it_advances_frames_independently_of_cycle_chunk_sizes(): void
    {
        $whole = $this->createPpu();
        $chunked = $this->createPpu();
        $elapsed = 341 * 262 * 2 + 341 * 241;
        $this::assertInstanceOf(RenderingData::class, $whole->run($elapsed));

        for ($remaining = $elapsed; $remaining > 0; $remaining -= $chunk) {
            $chunk = min(997, $remaining);
            $chunked->run($chunk);
        }

        $this::assertSame(0x80, $whole->read(0x02) & 0x80);
        $this::assertSame(0x80, $chunked->read(0x02) & 0x80);
        $this::assertInstanceOf(RenderingData::class, $whole->run(341 * 21));
        $this::assertInstanceOf(RenderingData::class, $chunked->run(341 * 21));
    }

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
    private function createPpu(bool $horizontalMirror = true): Ppu
    {
        $characterRam = new Ram(0x2000);
        $ppuBus = new PpuBus($characterRam);
        $interrupts = new Interrupts();

        return new Ppu($ppuBus, $interrupts, $horizontalMirror);
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
     * Reads an ordinary PPU memory byte after priming the delayed data buffer.
     */
    private function readVram(Ppu $ppu, int $address): int
    {
        $this->writeVramAddress($ppu, $address);
        $ppu->read(0x07);

        return $ppu->read(0x07);
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
