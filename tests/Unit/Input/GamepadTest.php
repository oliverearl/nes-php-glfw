<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use App\Input\Gamepad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use VISU\OS\InputContextMap;
use VISU\OS\Key;
use VISU\OS\Window;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\KeySignal;

#[CoversClass(Gamepad::class)]
final class GamepadTest extends TestCase
{
    /**
     * Keeps the initial serial report inactive until a strobe captures input.
     */
    #[Test]
    public function it_reads_inactive_input_before_strobe_latches_state(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0xFF);

        $this::assertSame(array_fill(0, 8, false), $this->readReport($gamepad));
    }

    /**
     * Clears live input, the latched report, strobe, and read index for a new machine.
     */
    #[Test]
    public function it_resets_controller_state_between_cartridges(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0xFF);
        $gamepad->write(1);
        $gamepad->write(0);
        $this::assertTrue($gamepad->read());
        $gamepad->write(1);

        $gamepad->reset();
        $gamepad->write(0);

        $this::assertSame(array_fill(0, 8, false), $this->readReport($gamepad));
        $this::assertTrue($gamepad->read());
        $gamepad->write(1);
        $this::assertFalse($gamepad->read());
    }

    /**
     * Supplies the standard NES bit order and the existing keyboard defaults.
     *
     * @return iterable<string, array{int, int, list<bool>}>
     */
    public static function buttonReports(): iterable
    {
        yield 'A' => [0x01, Key::Z, [true, false, false, false, false, false, false, false]];
        yield 'B' => [0x02, Key::X, [false, true, false, false, false, false, false, false]];
        yield 'Select' => [0x04, Key::BACKSPACE, [false, false, true, false, false, false, false, false]];
        yield 'Start' => [0x08, Key::ENTER, [false, false, false, true, false, false, false, false]];
        yield 'Up' => [0x10, Key::UP, [false, false, false, false, true, false, false, false]];
        yield 'Down' => [0x20, Key::DOWN, [false, false, false, false, false, true, false, false]];
        yield 'Left' => [0x40, Key::LEFT, [false, false, false, false, false, false, true, false]];
        yield 'Right' => [0x80, Key::RIGHT, [false, false, false, false, false, false, false, true]];
    }

    /**
     * Reports each button in its specified serial position.
     *
     * @param list<bool> $expected
     */
    #[Test]
    #[DataProvider('buttonReports')]
    public function it_reports_buttons_in_standard_serial_order(int $buttons, int $key, array $expected): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState($buttons);
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertSame($expected, $this->readReport($gamepad));
    }

    /**
     * Preserves the frontend keyboard adapter without requiring a window resource.
     *
     * @param list<bool> $expected
     */
    #[Test]
    #[DataProvider('buttonReports')]
    public function it_fetches_default_keyboard_bindings(int $buttons, int $key, array $expected): void
    {
        $context = new InputContextMap(new Dispatcher());
        $gamepad = new Gamepad($context);
        $window = $this->createStub(Window::class);

        $context->handleKeySignal(new KeySignal($window, $key, 0, GLFW_PRESS, 0));
        $gamepad->fetch();
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertSame($expected, $this->readReport($gamepad));

        $context->handleKeySignal(new KeySignal($window, $key, 0, GLFW_RELEASE, 0));
        $gamepad->fetch();
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertSame(array_fill(0, 8, false), $this->readReport($gamepad));
    }

    /**
     * Reads the live A button repeatedly while the shared strobe is high.
     */
    #[Test]
    public function it_keeps_reading_live_a_while_strobe_is_high(): void
    {
        $gamepad = new Gamepad();
        $gamepad->write(1);
        $gamepad->setButtonState(0x01);

        $this::assertTrue($gamepad->read());
        $this::assertTrue($gamepad->read());

        $gamepad->setButtonState(0xFE);

        $this::assertFalse($gamepad->read());
        $this::assertFalse($gamepad->read());

        $gamepad->setButtonState(0x01);

        $this::assertTrue($gamepad->read());
    }

    /**
     * Captures the button state at the falling edge rather than the rising edge.
     */
    #[Test]
    public function it_latches_current_input_when_strobe_falls(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0x01);
        $gamepad->write(1);
        $gamepad->setButtonState(0x42);
        $gamepad->write(0);

        $this::assertSame([false, true, false, false, false, false, true, false], $this->readReport($gamepad));
    }

    /**
     * Holds the latched report unchanged when live input changes during reading.
     */
    #[Test]
    public function it_keeps_latched_input_stable_while_live_input_changes(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0xA5);
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertTrue($gamepad->read());
        $this::assertFalse($gamepad->read());
        $this::assertTrue($gamepad->read());

        $gamepad->setButtonState(0x5A);
        $gamepad->fetch();

        $this::assertFalse($gamepad->read());
        $this::assertFalse($gamepad->read());
        $this::assertTrue($gamepad->read());
        $this::assertFalse($gamepad->read());
        $this::assertTrue($gamepad->read());
    }

    /**
     * Ignores repeated low writes rather than restarting or replacing the report.
     */
    #[Test]
    public function it_does_not_relatch_on_a_low_to_low_write(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0xA5);
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertTrue($gamepad->read());

        $gamepad->setButtonState(0x5A);
        $gamepad->write(0);

        $this::assertFalse($gamepad->read());
        $this::assertTrue($gamepad->read());
        $this::assertFalse($gamepad->read());
    }

    /**
     * Starts a fresh report after another high-to-low strobe sequence.
     */
    #[Test]
    public function it_restarts_serial_reading_when_input_is_latched_again(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0x01);
        $gamepad->write(1);
        $gamepad->write(0);
        $gamepad->read();
        $gamepad->read();

        $gamepad->setButtonState(0x5A);
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertSame([false, true, false, true, true, false, true, false], $this->readReport($gamepad));
    }

    /**
     * Uses only bit zero of the written strobe value.
     */
    #[Test]
    public function it_ignores_the_upper_strobe_bits(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0x02);
        $gamepad->write(0xFF);

        $this::assertFalse($gamepad->read());
        $this::assertFalse($gamepad->read());

        $gamepad->write(0xFE);

        $this::assertFalse($gamepad->read());
        $this::assertTrue($gamepad->read());
    }

    /**
     * Samples only the eight buttons present in a standard controller report.
     */
    #[Test]
    public function it_ignores_live_button_bits_above_the_standard_report(): void
    {
        $gamepad = new Gamepad();
        $gamepad->setButtonState(0xFF00);
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertSame(array_fill(0, 8, false), $this->readReport($gamepad));

        $gamepad->setButtonState(0xFFFF);
        $gamepad->write(1);
        $gamepad->write(0);

        $this::assertSame(array_fill(0, 8, true), $this->readReport($gamepad));
    }

    /**
     * Keeps returning one after the eight-button report is exhausted.
     */
    #[Test]
    public function it_returns_true_after_all_latched_buttons_are_read(): void
    {
        $gamepad = new Gamepad();

        for ($i = 0; $i < 8; $i++) {
            $this::assertFalse($gamepad->read());
        }

        for ($i = 0; $i < 32; $i++) {
            $this::assertTrue($gamepad->read());
        }
    }

    /**
     * Reads a complete standard-controller report in serial order.
     *
     * @return list<bool>
     */
    private function readReport(Gamepad $gamepad): array
    {
        $report = [];

        for ($i = 0; $i < 8; $i++) {
            $report[] = $gamepad->read();
        }

        return $report;
    }
}
