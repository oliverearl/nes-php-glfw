<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Emulator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Emulator::class)]
final class EmulatorTest extends TestCase
{
    #[Test]
    public function it_defaults_to_slow_motion_frame_pacing(): void
    {
        $this::assertFalse(Emulator::shouldDropFramesToMaintainRealtime(['bin/start.php', 'game.nes']));
        $this::assertSame(1, Emulator::maxUpdatesPerRenderForArgs(['bin/start.php', 'game.nes']));
    }

    #[Test]
    public function it_allows_dropping_visual_frames_for_realtime_pacing(): void
    {
        $args = ['bin/start.php', 'game.nes', '--drop-frames'];

        $this::assertTrue(Emulator::shouldDropFramesToMaintainRealtime($args));
        $this::assertSame(10, Emulator::maxUpdatesPerRenderForArgs($args));
    }

    #[Test]
    public function it_accepts_the_frame_pacing_flag_case_insensitively(): void
    {
        $this::assertTrue(Emulator::shouldDropFramesToMaintainRealtime(['bin/start.php', '--DROP-FRAMES']));
    }
}
