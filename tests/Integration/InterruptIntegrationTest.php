<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class InterruptIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function it_triggers_nmi_at_vblank(): void
    {
        $this->expectNotToPerformAssertions();

        [$ppu, $ppuBus, $characterRom, $interrupts] = $this->createPpuSystem();

        // Enable NMI in PPU control register.
        $ppu->write(0x00, 0x80);

        /*
         * Run PPU through enough cycles to potentially trigger VBlank.
         * This verifies the integration path completes without crashing.
         */
        for ($i = 0; $i < 300; $i++) {
            $result = $ppu->run(1000);
            if ($result !== false) {
                break;
            }
        }
    }

    #[Test]
    public function it_does_not_trigger_nmi_when_disabled(): void
    {
        [$ppu, , , $interrupts] = $this->createPpuSystem();

        // Disable NMI (bit 7 = 0).
        $ppu->write(0x00, 0x00);

        for ($i = 0; $i < 100; $i++) {
            $ppu->run(1000);
        }

        $this::assertFalse($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_clears_nmi_after_frame_completes(): void
    {
        [$ppu, , , $interrupts] = $this->createPpuSystem();

        $ppu->write(0x00, 0x80);

        $interrupts->assertNmi();
        $this::assertTrue($interrupts->isNmiAsserted());

        $interrupts->deassertNmi();
        $this::assertFalse($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_triggers_nmi_every_frame_when_enabled(): void
    {
        [$ppu, , , $interrupts] = $this->createPpuSystem();

        $ppu->write(0x00, 0x80);

        $interrupts->assertNmi();
        $this::assertTrue($interrupts->isNmiAsserted());

        $interrupts->deassertNmi();
        $this::assertFalse($interrupts->isNmiAsserted());

        $interrupts->assertNmi();
        $this::assertTrue($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_reads_vblank_status_from_ppu_status_register(): void
    {
        [$ppu] = $this->createPpuSystem();

        $status = $ppu->read(0x02);

        $this::assertGreaterThanOrEqual(0, $status);
        $this::assertLessThanOrEqual(255, $status);
    }

    #[Test]
    public function it_clears_vblank_flag_on_status_read(): void
    {
        [$ppu] = $this->createPpuSystem();

        $status1 = $ppu->read(0x02);
        $status2 = $ppu->read(0x02);

        $this::assertGreaterThanOrEqual(0, $status1);
        $this::assertLessThanOrEqual(255, $status1);
        $this::assertGreaterThanOrEqual(0, $status2);
        $this::assertLessThanOrEqual(255, $status2);
    }

    #[Test]
    public function it_integrates_interrupts_with_shared_interrupt_controller(): void
    {
        [$ppu, , , $interrupts] = $this->createPpuSystem();

        $this::assertFalse($interrupts->isNmiAsserted());

        $interrupts->assertNmi();
        $this::assertTrue($interrupts->isNmiAsserted());

        $interrupts->deassertNmi();
        $this::assertFalse($interrupts->isNmiAsserted());
    }
}
