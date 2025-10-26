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
use App\Input\Gamepad;
use GL\Buffer\UByteBuffer;
use GL\Texture\Texture2D;
use GL\VectorGraphics\VGImage;
use RuntimeException;
use VISU\Geo\Transform;
use VISU\Graphics\{Camera, CameraProjectionMode, RenderTarget};
use VISU\Graphics\Rendering\RenderContext;
use VISU\Quickstart\QuickstartApp;

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
     * Internal tracker of CPU cycle.
     */
    private static int $cycle = 0;

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
     * The loaded cartridge.
     */
    private Cartridge $cartridge;

    /**
     * Input. Currently limited to a single default-style gamepad.
     */
    private Gamepad $gamepad;

    /**
     * The cartridge loader.
     */
    private Loader $cartridgeLoader;

    /**
     * The primary RAM of the NES system.
     */
    private Ram $ram;

    /**
     * The character RAM of the NES system.
     */
    private Ram $characterRam;

    /**
     * The program ROM of the NES system.
     */
    private Rom $programRom;

    private PpuBus $ppuBus;

    private Interrupts $interrupts;

    private Ppu $ppu;

    private Dma $dma;

    private CpuBus $cpuBus;

    private Cpu $cpu;

    /**
     * @inheritDoc
     *
     * @throws \RuntimeException
     * @throws \VISU\OS\Exception\InputMappingException
     */
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
    public function draw(RenderContext $context, RenderTarget $renderTarget): void
    {
        // If the emulator is not running, show a placeholder animation. Or skip if we're running but not yet ready.
        if (! $this->isEmulatorRunning) {
            $rawBuffer = $this->generateWaitingAnimation($this->frameIndex);
        } elseif (! $this->isReadyToRender()) {
            return;
        }

        // TODO: Make this a configuration value.
        $preserveAspect = false;

        // 1. Clear screen, setup camera/view.
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);
        $viewport = $this->camera->getViewport($renderTarget);
        $this->camera->transformVGSpace($viewport, $this->vg);

        // 2. Perform PPU rendering.
        // TODO: Replace with actual NES PPU frame buffer.
        $rawBuffer ??= $this->generateWaitingAnimation($this->frameIndex);

        // 3. Upload to texture.
        $buffer = new UByteBuffer($rawBuffer);
        $texture = Texture2D::fromBuffer(self::NES_MAX_X, self::NES_MAX_Y, $buffer);
        $image = $this->vg->imageFromTexture($texture, VGImage::REPEAT_NONE, VGImage::FILTER_NEAREST);

        // 4. Compute scaling factors.
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

        // 5. Begin VG draw
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
    public function update(): void
    {
        parent::update();

        if (! $this->isEmulatorRunning) {
            return;
        }

        if ($this->dma->isDmaProcessing()) {
            $this->dma->runDma();
            static::$cycle = 514;
        }

        static::$cycle += $this->cpu->run();
        static::$renderingData = $this->ppu->run(static::$cycle * 3);

        $this->gamepad->fetch();
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
        $this->cartridgeLoader = new Loader($this->selectedRom);
        $this->cartridge = $this->cartridgeLoader->load();

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
        $this->ram = new Ram();
        $this->characterRam = new Ram(0x4000);

        for ($i = 0, $iMax = $this->cartridge->getCharacterRomSize(); $i < $iMax; $i++) {
            $this->characterRam->write($i, $this->cartridge->characterRom[$i]);
        }

        $this->programRom = new Rom($this->cartridge->programRom);
        $this->ppuBus = new PpuBus($this->characterRam);
        $this->interrupts = new Interrupts();
        // TODO: Needs implementing.
        $this->ppu = new Ppu($this->ppuBus, $this->interrupts, $this->cartridge->isHorizontalMirror);
        $this->dma = new Dma($this->ram, $this->ppu);
        // TODO: Needs implementing
        $this->cpuBus = new CpuBus($this->ram, $this->programRom, $this->ppu, $this->gamepad, $this->dma);
        // TODO: Needs implementing
        $this->cpu = new Cpu($this->cpuBus, $this->interrupts);
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

        $fontPath = VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inconsolata/Inconsolata-Regular.ttf';

        if ($this->vg->createFont('inconsolata', $fontPath) === -1) {
            throw new RuntimeException('Inconsolata font could not be loaded.');
        }
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
        return static::$renderingData !== false;
    }
}
