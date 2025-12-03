<?php

declare(strict_types=1);

namespace App\Input;

use VISU\OS\InputActionMap;
use VISU\OS\InputContextMap;
use VISU\OS\Key;

/**
 * Class Keypad
 *
 * Emulates an NES controller for use in a PHP emulator.
 *
 * The NES controller uses a **serial shift register** mechanism:
 *  - CPU writes to $4016 to **strobe/latch** the current button state.
 *  - CPU reads from $4016 repeatedly to get one button state at a time (A → Right).
 */
class Gamepad
{
    /**
     * Input context key for the default keyboard mapping.
     */
    public const string INPUT_CONTEXT_DEFAULT_KEYBOARD = 'default_keyboard';

    /** A */
    public const string BUTTON_A = 'A';

    /** B */
    public const string BUTTON_B = 'B';

    /** SELECT */
    public const string BUTTON_SELECT = 'SELECT';

    /** START */
    public const string BUTTON_START = 'START';

    /** ^ */
    public const string BUTTON_UP = 'UP';

    /** v */
    public const string BUTTON_DOWN = 'DOWN';

    /** <- */
    public const string BUTTON_LEFT = 'LEFT';

    /** -> */
    public const string BUTTON_RIGHT = 'RIGHT';

    /**
     * Default button names and their associated keys.
     *
     * @var array<string, int>
     */
    public const array BUTTON_NAMES_AND_DEFAULT_KEYS = [
        self::BUTTON_A => Key::Z,
        self::BUTTON_B => Key::X,
        self::BUTTON_SELECT => Key::BACKSPACE,
        self::BUTTON_START => Key::ENTER,
        self::BUTTON_UP => Key::UP,
        self::BUTTON_DOWN => Key::DOWN,
        self::BUTTON_LEFT => Key::LEFT,
        self::BUTTON_RIGHT => Key::RIGHT,
    ];

    /**
     * The Visu input system used to poll keyboard or gamepad state.
     */
    private readonly InputContextMap $input;

    /**
     * The current live button states.
     * Indexed as: [A, B, SELECT, START, UP, DOWN, LEFT, RIGHT]
     *
     * @var list<bool>
     */
    private array $keyBuffer;

    /**
     * The latched button states.
     * This is the snapshot of keyBuffer captured on strobe write from CPU.
     *
     * @var list<bool>
     */
    private array $keyRegisters = [];

    /**
     * Whether the strobe line is high (CPU is telling controller to latch).
     */
    private bool $isSet = false;

    /**
     * Current bit index for read() — 0 = A, 7 = Right.
     */
    private int $index = 0;

    /**
     * Creates a new Gamepad instance.
     *
     * @throws \VISU\OS\Exception\InputMappingException
     */
    public function __construct(InputContextMap $input)
    {
        $this->input = $input;
        $this->keyBuffer = array_fill(0, 8, false);

        $actions = new InputActionMap();

        foreach (self::BUTTON_NAMES_AND_DEFAULT_KEYS as $button => $key) {
            $actions->bindButton($button, $key);
        }

        $this->input->registerAndActivate(self::INPUT_CONTEXT_DEFAULT_KEYBOARD, $actions);
    }

    /**
     * Poll the current input state from Visu and update the live key buffer.
     *
     * Should be called once per frame before CPU execution.
     */
    public function fetch(): void
    {
        $this->keyBuffer = [
            $this->input->actions->didButtonPress(self::BUTTON_A),
            $this->input->actions->didButtonPress(self::BUTTON_B),
            $this->input->actions->didButtonPress(self::BUTTON_SELECT),
            $this->input->actions->didButtonPress(self::BUTTON_START),
            $this->input->actions->didButtonPress(self::BUTTON_UP),
            $this->input->actions->didButtonPress(self::BUTTON_DOWN),
            $this->input->actions->didButtonPress(self::BUTTON_LEFT),
            $this->input->actions->didButtonPress(self::BUTTON_RIGHT),
        ];
    }

    /**
     * Emulate writing to the NES controller strobe register.
     *
     * On the real NES:
     *  - Writing a 1 to bit 0 of $4016 keeps the controller in "latch" mode.
     *  - Writing a 0 after a 1 captures the current button states into a shift register.
     */
    public function write(int $data): void
    {
        if (($data & 0x01) !== 0) {
            // Strobe line high: controller should keep tracking live button states.
            $this->isSet = true;
        } elseif ($this->isSet && !($data & 0x01)) {
            // Strobe line fell from 1 → 0: latch current button states.
            $this->isSet = false;

            // Reset the shift index so the next read starts from the first button. (A)
            $this->index = 0;

            // Copy live keys to the latched register for serial reading.
            $this->keyRegisters = $this->keyBuffer;
        }
    }

    /**
     * Emulate reading one button state from the NES controller.
     *
     * The CPU reads from $4016 repeatedly:
     *  - Each read returns the next bit from the latched register
     *  - Bit order: A, B, SELECT, START, UP, DOWN, LEFT, RIGHT
     *  - After 8 reads, real hardware returns 1 for any further reads.
     */
    public function read(): bool
    {
        if ($this->index >= 8) {
            return true;
        }

        return $this->keyRegisters[$this->index++];
    }
}
