<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use App\Input\Gamepad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use VISU\OS\InputContextMap;
use VISU\Signal\Dispatcher;

#[CoversClass(Gamepad::class)]
final class GamepadTest extends TestCase
{
    #[Test]
    public function it_reads_inactive_input_before_strobe_latches_state(): void
    {
        $gamepad = new Gamepad($this->createInputContext());

        $this::assertFalse($gamepad->read());
    }

    #[Test]
    public function it_returns_true_after_all_latched_buttons_are_read(): void
    {
        $gamepad = new Gamepad($this->createInputContext());

        for ($i = 0; $i < 8; $i++) {
            $this::assertFalse($gamepad->read());
        }

        $this::assertTrue($gamepad->read());
    }

    /**
     * Creates an input context for default gamepad action mapping.
     */
    private function createInputContext(): InputContextMap
    {
        return new InputContextMap(new Dispatcher());
    }
}
