<?php

declare(strict_types=1);

namespace App\Input;

use VISU\OS\InputActionMap;
use VISU\OS\InputContextMap;
use VISU\OS\Key;

class Gamepad
{
    /**
     * Input context key for the default keyboard mapping.
     */
    public const string INPUT_CONTEXT_DEFAULT_KEYBOARD = 'default_keyboard';

    /**
     * A button constant.
     */
    public const string BUTTON_A = 'A';

    /**
     * B button constant.
     */
    public const string BUTTON_B = 'B';

    /**
     * SELECT button constant.
     */
    public const string BUTTON_SELECT = 'SELECT';

    /**
     * START button constant.
     */
    public const string BUTTON_START = 'START';

    /**
     * UP button constant.
     */
    public const string BUTTON_UP = 'UP';

    /**
     * DOWN button constant.
     */
    public const string BUTTON_DOWN = 'DOWN';

    /**
     * LEFT button constant.
     */
    public const string BUTTON_LEFT = 'LEFT';

    /**
     * RIGHT button constant.
     */
    public const string BUTTON_RIGHT = 'RIGHT';

    /**
     * Default button names mapped to their keyboard keys.
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
     * The input context for reading keyboard/gamepad state.
     */
    private readonly InputContextMap $input;

    /**
     * Live button state buffer updated each frame.
     *
     * @var list<bool>
     */
    private array $keyBuffer;

    /**
     * Latched button states captured during strobe operation.
     *
     * @var list<bool>
     */
    private array $keyRegisters = [];

    /**
     * Indicates whether the strobe line is currently high.
     */
    private bool $isSet = false;

    /**
     * Current read index for serial reads (0-7).
     */
    private int $index = 0;

    /**
     * Creates a new gamepad instance and binds default keyboard controls.
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
     * Polls the current input state and updates the live key buffer.
     */
    public function fetch(): void
    {
        $this->keyBuffer = [
            $this->input->actions->isButtonDown(self::BUTTON_A),
            $this->input->actions->isButtonDown(self::BUTTON_B),
            $this->input->actions->isButtonDown(self::BUTTON_SELECT),
            $this->input->actions->isButtonDown(self::BUTTON_START),
            $this->input->actions->isButtonDown(self::BUTTON_UP),
            $this->input->actions->isButtonDown(self::BUTTON_DOWN),
            $this->input->actions->isButtonDown(self::BUTTON_LEFT),
            $this->input->actions->isButtonDown(self::BUTTON_RIGHT),
        ];
    }

    /**
     * Handles writes to the controller strobe register.
     *
     * On the real NES:
     *  - Writing a 1 to bit 0 of $4016 keeps the controller in "latch" mode.
     *  - Writing a 0 after a 1 captures the current button states into a shift register.
     */
    public function write(int $data): void
    {
        if (($data & 0x01) !== 0) {
            $this->isSet = true;
        } elseif ($this->isSet && !($data & 0x01)) {
            $this->isSet = false;
            $this->index = 0;
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
