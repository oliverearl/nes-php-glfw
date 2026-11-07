<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Unit;

use App\Emulator;
use App\Input\Gamepad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\HardwareTestRom;
use VISU\OS\InputContextMap;
use VISU\OS\Key;
use VISU\OS\Window;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;

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

    /**
     * Reuses keyboard bindings while rebuilding fresh machine and controller state.
     */
    #[Test]
    public function it_loads_another_rom_without_registering_keyboard_controls_again(): void
    {
        $path = HardwareTestRom::write([0xEA]);
        $emulator = new ReflectionClass(Emulator::class)->newInstanceWithoutConstructor();
        $emulator->inputContext = new InputContextMap(new Dispatcher());
        $emulator->selectedRom = $path;
        $load = new ReflectionMethod(Emulator::class, 'load');
        $controllerProperty = new ReflectionProperty(Emulator::class, 'gamepad');
        $cpuProperty = new ReflectionProperty(Emulator::class, 'cpu');

        try {
            $load->invoke($emulator);
            $controller = $controllerProperty->getValue($emulator);
            $this::assertInstanceOf(Gamepad::class, $controller);
            $previousCpu = $cpuProperty->getValue($emulator);
            $window = $this->createStub(Window::class);
            $emulator->inputContext->handleKeySignal(new KeySignal($window, Key::Z, 0, GLFW_PRESS, 0));
            $controller->fetch();
            $controller->write(1);
            $controller->write(0);
            $this::assertTrue($controller->read());
            $controller->write(1);

            $load->invoke($emulator);

            $this::assertTrue($emulator->isEmulatorRunning);
            $this::assertNotSame($previousCpu, $cpuProperty->getValue($emulator));
            $this::assertSame($controller, $controllerProperty->getValue($emulator));
            $controller->write(0);

            for ($button = 0; $button < 8; $button++) {
                $this::assertFalse($controller->read());
            }

            $controller->fetch();
            $controller->write(1);
            $controller->write(0);
            $this::assertTrue($controller->read());
            $this::assertFalse($controller->read());
        } finally {
            unlink($path);
        }
    }

}
