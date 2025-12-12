<?php

declare(strict_types=1);

namespace App;

use App\Bus\CpuBus;
use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Bus\Rom;
use App\Cartridge\Cartridge;
use App\Cartridge\Loader;
use App\Cpu\Cpu;
use App\Cpu\Dma;
use App\Cpu\Interrupts;
use App\Debug\Profiler;
use App\Graphics\Ppu;
use App\Graphics\Renderer;
use App\Input\Gamepad;
use GL\Buffer\UByteBuffer;
use GL\Texture\Texture2D;
use GL\VectorGraphics\VGImage;
use Override;
use RuntimeException;
use VISU\Geo\Transform;
use VISU\Graphics\{Camera, CameraProjectionMode, RenderTarget};
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\Input;
use VISU\Quickstart\QuickstartApp;
use VISU\Signals\Input\DropSignal;

class Emulator extends QuickstartApp
{
    use Profiler;

    /**
     * NES display dimensions in pixels. (Width)
     */
    public const int NES_MAX_X = 256;

    /**
     * NES display dimensions in pixels. (Height)
     */
    public const int NES_MAX_Y = 224;

    /**
     * Indicates whether the NES emulator is currently running.
     */
    public bool $isEmulatorRunning = false;

    /**
     * The ROM file selected for loading.
     */
    public ?string $selectedRom = null;

    /**
     * Camera used for rendering the scene.
     */
    private Camera $camera;

    /**
     * The renderer for converting PPU data to framebuffer.
     */
    private Renderer $renderer;

    /**
     * The loaded cartridge.
     */
    private Cartridge $cartridge;

    /**
     * Input handler for gamepad input.
     */
    private Gamepad $gamepad;

    /**
     * The PPU (Picture Processing Unit).
     */
    private Ppu $ppu;

    /**
     * The DMA (Direct Memory Access) controller.
     */
    private Dma $dma;

    /**
     * The CPU (Central Processing Unit).
     */
    private Cpu $cpu;

    /**
     * Cached framebuffer from last completed NES frame.
     *
     * @var list<int>|null
     */
    private ?array $cachedFrameBuffer = null;


    /**
     * Initializes the emulator and loads the ROM if available.
     *
     * @inheritDoc
     * @throws \RuntimeException
     * @throws \VISU\OS\Exception\InputMappingException
     */
    #[Override]
    public function ready(): void
    {
        parent::ready();

        $this->setDebugEnabled(in_array('--profile', $this->getArgs(), strict: true));

        $this->initializeEngine();
        $this->checkForInitialRom();
        $this->load();
    }

    /**
     * Renders the current frame to the screen.
     *
     * @inheritDoc
     * @throws \VISU\Exception\VISUException
     */
    #[Override]
    public function draw(RenderContext $context, RenderTarget $renderTarget): void
    {
        $debugStart = $this->debugStartDraw();

        if (! $this->isEmulatorRunning) {
            $rawBuffer = $this->generateWaitingAnimation($this->frameIndex);
        } elseif ($this->cachedFrameBuffer === null) {
            // No frame ready yet, skip drawing.
            return;
        } else {
            // Use the pre-rendered framebuffer from update().
            $rawBuffer = $this->cachedFrameBuffer;
        }

        // TODO: Make this a configuration value.
        $preserveAspect = false;

        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);
        $viewport = $this->camera->getViewport($renderTarget);
        $this->camera->transformVGSpace($viewport, $this->vg);

        $buffer = new UByteBuffer($rawBuffer);
        $texture = Texture2D::fromBuffer(self::NES_MAX_X, self::NES_MAX_Y, $buffer);
        $image = $this->vg->imageFromTexture($texture, VGImage::REPEAT_NONE, VGImage::FILTER_NEAREST);

        $scaleX = $viewport->width / self::NES_MAX_X;
        $scaleY = $viewport->height / self::NES_MAX_Y;

        if ($preserveAspect) {
            $scale = min($scaleX, $scaleY);
            $scaleX = $scaleY = $scale;
        }

        $offsetX = ($viewport->width - (self::NES_MAX_X * $scaleX)) / 2;
        $offsetY = ($viewport->height - (self::NES_MAX_Y * $scaleY)) / 2;
        $topLeft = $viewport->getTopLeft();

        $this->vg->save();

        $this->vg->translate($topLeft->x + $offsetX, $topLeft->y + $offsetY);
        $this->vg->scale($scaleX, $scaleY);

        $this->vg->beginPath();
        $this->vg->rect(0.0, 0.0, self::NES_MAX_X, self::NES_MAX_Y);

        $paint = $image->makePaint(0.0, 0.0, self::NES_MAX_X, self::NES_MAX_Y);
        $this->vg->fillPaint($paint);
        $this->vg->fill();

        $this->vg->restore();

        $this->debugEndDraw($debugStart);
    }

    /**
     * Updates the emulator state incrementally without blocking.
     *
     * Runs a fixed budget of CPU cycles per tick to avoid blocking the game loop.
     * The NES produces ~60 frames per second. Each update() call processes one
     * NES frame worth of cycles, but we limit iterations to prevent blocking.
     *
     * @inheritDoc
     */
    #[Override]
    public function update(): void
    {
        $debugUpdateStart = $this->debugStartUpdate();

        parent::update();

        if (! $this->isEmulatorRunning) {
            $this->debugLog();
            return;
        }

        $this->gamepad->fetch();

        /*
         * Run emulator until one NES frame completes.
         * A frame takes roughly 29,780 CPU cycles (341*262/3).
         * We run in a loop but with a safety limit.
         */
        $maxIterations = 30000;
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $cycle = 0;

            if ($this->dma->isDmaProcessing()) {
                $this->dma->runDma();
                $cycle = 514;
            }

            $debugCpuStart = $this->debugStartCpu();
            $cycle += $this->cpu->run();
            $this->debugEndCpu($debugCpuStart);

            $debugPpuStart = $this->debugStartPpu();
            $renderingData = $this->ppu->run($cycle * 3);
            $this->debugEndPpu($debugPpuStart);

            $iterations++;

            if ($renderingData !== false) {
                $debugRenderStart = $this->debugStartRender();
                $this->cachedFrameBuffer = $this->renderer->render($renderingData);
                $this->debugEndRender($debugRenderStart);

                $this->debugRecordNesFrame();
                break;
            }
        }

        $this->debugRecordIterations($iterations);
        $this->debugEndUpdate($debugUpdateStart);
        $this->debugLog();
    }

    /**
     * Loads the ROM and initializes the emulator components.
     *
     * @throws \RuntimeException
     * @throws \VISU\OS\Exception\InputMappingException
     */
    protected function load(): void
    {
        if ($this->selectedRom === null) {
            return;
        }

        $this->isEmulatorRunning = false;
        $cartridgeLoader = new Loader($this->selectedRom);
        $this->cartridge = $cartridgeLoader->load();

        $this->reset();
    }


    /**
     * Resets the emulator to its initial state with the loaded cartridge.
     *
     * @throws \VISU\OS\Exception\InputMappingException
     */
    protected function reset(): void
    {
        $this->isEmulatorRunning = false;

        $this->gamepad = new Gamepad($this->inputContext);
        $ram = new Ram();
        $characterRam = new Ram(0x4000);

        for ($i = 0, $iMax = $this->cartridge->getCharacterRomSize(); $i < $iMax; $i++) {
            $characterRam->write($i, $this->cartridge->characterRom[$i]);
        }

        $programRom = new Rom($this->cartridge->programRom);
        $ppuBus = new PpuBus($characterRam);
        $interrupts = new Interrupts();
        $this->ppu = new Ppu($ppuBus, $interrupts, $this->cartridge->isHorizontalMirror);
        $this->dma = new Dma($ram, $this->ppu);
        $cpuBus = new CpuBus($ram, $programRom, $this->ppu, $this->gamepad, $this->dma);
        $this->cpu = new Cpu($cpuBus, $interrupts);
        $this->cpu->reset();

        $this->isEmulatorRunning = true;
    }

    /**
     * Initializes the rendering engine and input handlers.
     *
     * @throws \RuntimeException
     */
    private function initializeEngine(): void
    {
        $this->camera = new Camera(CameraProjectionMode::orthographicStaticWorld, new Transform());
        $this->camera->flipViewportY = true;
        $this->renderer = new Renderer();

        $fontPath = VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inconsolata/Inconsolata-Regular.ttf';

        if ($this->vg->createFont('inconsolata', $fontPath) === -1) {
            throw new RuntimeException('Inconsolata font could not be loaded.');
        }

        $this->dispatcher->register(Input::EVENT_DROP, function (DropSignal $signal): void {
            $this->selectedRom = $signal->paths[0] ?? null;
            $this->load();
        });
    }

    /**
     * Checks if a ROM file was passed via command-line argument.
     */
    private function checkForInitialRom(): void
    {
        $args = $this->getArgs();

        // Filter out flags and find the ROM file argument.
        foreach ($args as $arg) {
            // Skip the script name and any flags.
            if ($arg === $args[0] || str_starts_with($arg, '--')) {
                continue;
            }

            // Found a potential ROM file.
            if (file_exists($arg)) {
                $this->selectedRom = realpath($arg);
                break;
            }
        }
    }

    /**
     * Retrieves the command-line arguments passed to the application.
     *
     * @return list<string>
     */
    private function getArgs(): array
    {
        global $argv;

        return array_map('strtolower', $argv ?? $_SERVER['argv'] ?? []);
    }

    /**
     * Generates a test canvas buffer with an animated pattern for display when no game is loaded.
     *
     * @return list<int>
     */
    private function generateWaitingAnimation(int $frame): array
    {
        $buffer = [];

        // Animate by shifting colours based on frame.
        $shift = ($frame * 5) % 256;

        for ($y = 0; $y < self::NES_MAX_Y; $y++) {
            for ($x = 0; $x < self::NES_MAX_X; $x++) {
                $r = ($x + $shift) % 256;
                $g = ($y + $shift) % 256;
                $b = (128 + $shift) % 256;

                $buffer[] = $r;
                $buffer[] = $g;
                $buffer[] = $b;
                $buffer[] = 255;
            }
        }

        return $buffer;
    }
}
