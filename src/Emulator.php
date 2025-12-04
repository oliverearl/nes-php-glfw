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
use App\Graphics\Objects\RenderingData;
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
    /**
     * NES display dimensions in pixels. (Width)
     */
    public const int NES_MAX_X = 256;

    /**
     * NES display dimensions in pixels. (Height)
     */
    public const int NES_MAX_Y = 224;

    /**
     * The rendering data produced by the PPU for the current frame.
     * False if not ready to render.
     */
    private static false|RenderingData $renderingData = false;

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
     * Input. Currently limited to a single default-style gamepad.
     */
    private Gamepad $gamepad;

    private Ppu $ppu;

    private Dma $dma;

    private Cpu $cpu;

    /**
     * @inheritDoc
     *
     * @throws \RuntimeException
     * @throws \VISU\OS\Exception\InputMappingException
 */
    #[Override]
    public function ready(): void
    {
        parent::ready();

        $this->initializeEngine();
        $this->checkForInitialRom();
        $this->load();
    }

    /**
     * @inheritDoc
     * @throws \VISU\Exception\VISUException
     */
    #[Override]
    public function draw(RenderContext $context, RenderTarget $renderTarget): void
    {
        // If the emulator is not running, show a placeholder animation.
        if (! $this->isEmulatorRunning) {
            $rawBuffer = $this->generateWaitingAnimation($this->frameIndex);
        } elseif (! $this->isReadyToRender()) {
            // Skip if we're running but not yet ready.
            return;
        } else {
            // Convert RenderingData to framebuffer using the renderer
            $rawBuffer = $this->renderer->render(self::$renderingData);
        }

        // TODO: Make this a configuration value.
        $preserveAspect = false;

        // 1. Clear screen, setup camera/view.
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);
        $viewport = $this->camera->getViewport($renderTarget);
        $this->camera->transformVGSpace($viewport, $this->vg);

        // 2. Upload framebuffer to texture.
        $buffer = new UByteBuffer($rawBuffer);
        $texture = Texture2D::fromBuffer(self::NES_MAX_X, self::NES_MAX_Y, $buffer);
        $image = $this->vg->imageFromTexture($texture, VGImage::REPEAT_NONE, VGImage::FILTER_NEAREST);

        // 3. Compute scaling factors.
        $scaleX = $viewport->width / self::NES_MAX_X;
        $scaleY = $viewport->height / self::NES_MAX_Y;

        if ($preserveAspect) {
            // Scale uniformly to fit within viewport.
            $scale = min($scaleX, $scaleY);
            $scaleX = $scaleY = $scale;
        }

        // Center within viewport.
        $offsetX = ($viewport->width - (self::NES_MAX_X * $scaleX)) / 2;
        $offsetY = ($viewport->height - (self::NES_MAX_Y * $scaleY)) / 2;
        $topLeft = $viewport->getTopLeft();

        // 4. Begin VG draw
        $this->vg->save();

        // Translate and scale VG space so NES image fills or fits viewport.
        $this->vg->translate($topLeft->x + $offsetX, $topLeft->y + $offsetY);
        $this->vg->scale($scaleX, $scaleY);

        // Draw the image in NES native coordinates. (0–256, 0–224)
        $this->vg->beginPath();
        $this->vg->rect(0.0, 0.0, self::NES_MAX_X, self::NES_MAX_Y);

        // Make a paint that maps 1:1 onto this rect.
        $paint = $image->makePaint(0.0, 0.0, self::NES_MAX_X, self::NES_MAX_Y);
        $this->vg->fillPaint($paint);
        $this->vg->fill();

        $this->vg->restore();
    }

    /** @inheritDoc */
    #[Override]
    public function update(): void
    {
        parent::update();

        if (! $this->isEmulatorRunning) {
            return;
        }

        // Run the emulator until a complete frame is rendered
        while (true) {
            $cycle = 0;

            if ($this->dma->isDmaProcessing()) {
                $this->dma->runDma();
                $cycle = 514;
            }

            $cycle += $this->cpu->run();
            $renderingData = $this->ppu->run($cycle * 3);

            if ($renderingData !== false) {
                self::$renderingData = $renderingData;
                $this->gamepad->fetch();
                break;
            }
        }
    }

    /**
     * Fire up the emulator.
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
     * Reset the emulator state.
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
     * Performs some initial setup for the game engine.
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
     * Check if a ROM was passed via argument.
     */
    private function checkForInitialRom(): void
    {
        $args = $this->container->getParameter('argv');

        if ($rom = $args[0] ?? false) {
            $this->selectedRom = realpath($rom);
        }
    }

    /**
     * Generate a test canvas buffer with an animated pattern.
     * This is used when there is no game loaded.
     *
     * @return list<int>
     */
    private function generateWaitingAnimation(int $frame): array
    {
        $buffer = [];

        // Animate by shifting colors based on frame.
        $shift = ($frame * 5) % 256; // Change 5 per frame, wrap at 256.

        for ($y = 0; $y < self::NES_MAX_Y; $y++) {
            for ($x = 0; $x < self::NES_MAX_X; $x++) {
                // Create a moving gradient.
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

    /**
     * Check whether the emulator is ready to render a frame.
     */
    private function isReadyToRender(): bool
    {
        return self::$renderingData !== false;
    }
}
