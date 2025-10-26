<?php

declare(strict_types=1);

namespace App;

use GL\Buffer\UByteBuffer;
use GL\Texture\Texture2D;
use GL\VectorGraphics\VGImage;
use VISU\Graphics\{RenderTarget, Camera, CameraProjectionMode};
use RuntimeException;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Geo\Transform;
use VISU\OS\{InputActionMap, Key};
use VISU\Quickstart\QuickstartApp;

class Emulator extends QuickstartApp
{
    public const int NES_MAX_X = 256;
    public const int NES_MAX_Y = 224;

    /**
     * Indicates whether the NES emulator is currently running.
     */
    public bool $isEmulatorRunning = false;

    /**
     * Camera used for rendering the scene.
     */
    private Camera $camera;

    /** @inheritDoc */
    public function ready() : void
    {
        parent::ready();

        $this->initializeEngine();

        // You can bind actions to keys in VISU 
        // this way you can decouple your game logic from the actual key bindings
        // and provides a comfortable way to access input state
        $actions = new InputActionMap;
        $actions->bindButton('bounce', Key::SPACE);
        $actions->bindButton('pushRight', Key::D);
        $actions->bindButton('pushLeft', Key::A);

        $this->inputContext->registerAndActivate('main', $actions);
    }

    /**
     * @inheritDoc
     * @throws \VISU\Exception\VISUException
     */
    public function draw(RenderContext $context, RenderTarget $renderTarget): void
    {
        // TODO: Make this a configuration value.
        $preserveAspect = false;

        // 1. Clear screen, setup camera/view.
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);
        $viewport = $this->camera->getViewport($renderTarget);
        $this->camera->transformVGSpace($viewport, $this->vg);

        // 2. Perform PPU rendering.
        if ($this->isEmulatorRunning) {
            // TODO: Replace with actual NES PPU frame buffer.
            $rawBuffer = $this->generateTestCanvasBuffer($this->frameIndex);
        } else {
            // If the emulator is not running, show a placeholder animation.
            $rawBuffer = $this->generateWaitingAnimation($this->frameIndex);
        }

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
    public function update() : void
    {
        parent::update();
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
                // Create a moving gradient
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
