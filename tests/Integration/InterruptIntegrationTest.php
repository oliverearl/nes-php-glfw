<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class InterruptIntegrationTest extends IntegrationTestCase
{
    /**
     * Delivers a PPU-generated NMI through the shared line and executes its CPU handler.
     */
    #[Test]
    public function it_executes_the_nmi_handler_after_ppu_vblank(): void
    {
        [$cpu, $bus, $ram, , $ppu, $interrupts] = $this->createTestSystemWithRom($this->createNmiRom());
        $cpu->reset();
        $bus->writeByCpu(0x2000, 0x80);

        $ppu->run(241 * 341 - 1);
        $this::assertFalse($interrupts->isNmiAsserted());
        $this::assertSame(0x8000, $cpu->snapshot()->pc);

        $ppu->run(1);
        $this::assertTrue($interrupts->isNmiAsserted());

        $this::assertSame(7, $cpu->run());
        $this::assertFalse($interrupts->isNmiAsserted());
        $this::assertSame(0x9000, $cpu->snapshot()->pc);
        $this::assertSame(0xFA, $cpu->snapshot()->sp);
        $this::assertSame(0x80, $ram->read(0x01FD));
        $this::assertSame(0x00, $ram->read(0x01FC));
        $this::assertSame(0x24, $ram->read(0x01FB));
        $this::assertSame(0x00, $ram->read(0x0200));

        $this::assertSame(2, $cpu->run());
        $this::assertSame(0x42, $cpu->snapshot()->a);
        $this::assertSame(0x9002, $cpu->snapshot()->pc);

        $this::assertSame(4, $cpu->run());
        $this::assertSame(0x42, $ram->read(0x0200));

        $this::assertSame(6, $cpu->run());
        $this::assertSame(0x8000, $cpu->snapshot()->pc);
        $this::assertSame(0xFD, $cpu->snapshot()->sp);
    }

    /**
     * Sets vblank without requesting NMI when PPUCTRL disables it.
     */
    #[Test]
    public function it_does_not_trigger_nmi_when_disabled(): void
    {
        [$ppu, , , $interrupts] = $this->createPpuSystem();
        $ppu->write(0x00, 0x00);

        $ppu->run(241 * 341);

        $this::assertSame(0x80, $ppu->read(0x02) & 0x80);
        $this::assertFalse($interrupts->isNmiAsserted());
    }

    /**
     * Clears the vblank status flag when the current frame completes.
     */
    #[Test]
    public function it_clears_vblank_after_frame_completes(): void
    {
        [$ppu, , , $interrupts] = $this->createPpuSystem();
        $ppu->write(0x00, 0x80);
        $ppu->run(241 * 341);
        $this::assertTrue($interrupts->isNmiAsserted());

        $this::assertNotFalse($ppu->run(21 * 341));
        $this::assertSame(0x00, $ppu->read(0x02) & 0x80);
    }

    /**
     * Produces a fresh interrupt on successive frame boundaries without manual assertion.
     */
    #[Test]
    public function it_triggers_nmi_every_frame_when_enabled(): void
    {
        [$cpu, $bus, , , $ppu, $interrupts] = $this->createTestSystemWithRom($this->createNmiRom());
        $cpu->reset();
        $bus->writeByCpu(0x2000, 0x80);

        for ($frame = 0; $frame < 3; $frame++) {
            $ppu->run(241 * 341);
            $this::assertTrue($interrupts->isNmiAsserted());
            $this::assertSame(7, $cpu->run());
            $this::assertSame(0x9000, $cpu->snapshot()->pc);
            $this::assertFalse($interrupts->isNmiAsserted());

            $this::assertSame(2, $cpu->run());
            $this::assertSame(4, $cpu->run());
            $this::assertSame(6, $cpu->run());
            $this::assertSame(0x8000, $cpu->snapshot()->pc);

            $this::assertNotFalse($ppu->run(21 * 341));
        }
    }

    /**
     * Changes the vblank flag at the current scanline scheduler's boundary.
     */
    #[Test]
    public function it_reads_vblank_status_from_ppu_status_register(): void
    {
        [$ppu] = $this->createPpuSystem();
        $ppu->run(241 * 341 - 1);
        $this::assertSame(0x00, $ppu->read(0x02) & 0x80);

        $ppu->run(1);

        $this::assertSame(0x80, $ppu->read(0x02) & 0x80);
    }

    /**
     * Returns the set vblank bit once and clears it for subsequent status reads.
     */
    #[Test]
    public function it_clears_vblank_flag_on_status_read(): void
    {
        [$ppu] = $this->createPpuSystem();
        $ppu->run(241 * 341);

        $this::assertSame(0x80, $ppu->read(0x02) & 0x80);
        $this::assertSame(0x00, $ppu->read(0x02) & 0x80);
    }

    /**
     * Does not request another NMI merely because vblank remains active.
     */
    #[Test]
    public function it_does_not_retrigger_nmi_within_the_same_vblank(): void
    {
        [$cpu, $bus, , , $ppu, $interrupts] = $this->createTestSystemWithRom($this->createNmiRom());
        $cpu->reset();
        $bus->writeByCpu(0x2000, 0x80);
        $ppu->run(241 * 341);

        $this::assertTrue($interrupts->isNmiAsserted());
        $this::assertSame(7, $cpu->run());

        $ppu->run(2 * 341);

        $this::assertFalse($interrupts->isNmiAsserted());
        $this::assertSame(0x80, $bus->readByCpu(0x2002) & 0x80);
        $this::assertSame(2, $cpu->run());
        $this::assertSame(0x9002, $cpu->snapshot()->pc);
    }

    /**
     * Builds reset and NMI vectors with an LDA, STA, RTI handler in NROM space.
     *
     * @return list<int>
     */
    private function createNmiRom(): array
    {
        $rom = array_fill(0, 0x8000, 0xEA);
        $rom[0x7FFA] = 0x00;
        $rom[0x7FFB] = 0x90;
        $rom[0x7FFC] = 0x00;
        $rom[0x7FFD] = 0x80;

        foreach ([0xA9, 0x42, 0x8D, 0x00, 0x02, 0x40] as $offset => $byte) {
            $rom[0x1000 + $offset] = $byte;
        }

        return $rom;
    }
}
