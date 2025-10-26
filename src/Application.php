<?php

declare(strict_types=1);

namespace App;

use Error;
use GL\Buffer\UByteBuffer;
use GL\Texture\Texture2D;
use GL\VectorGraphics\VGImage;
use VISU\Graphics\{RenderTarget, Viewport, Camera, CameraProjectionMode};
use VISU\Graphics\Rendering\RenderContext;
use VISU\Geo\Transform;
use VISU\OS\{InputActionMap, Key};
use VISU\Quickstart\QuickstartApp;

class Application extends QuickstartApp
{
    private Camera $camera;

    /**
     * A function that is invoked once the app is ready to run.
     * This happens exactly just before the game loop starts.
     *
     * Here you can prepare your game state, register services, callbacks etc.
     */
    public function ready() : void
    {
        parent::ready();

        // You can bind actions to keys in VISU 
        // this way you can decouple your game logic from the actual key bindings
        // and provides a comfortable way to access input state
        $actions = new InputActionMap;
        $actions->bindButton('bounce', Key::SPACE);
        $actions->bindButton('pushRight', Key::D);
        $actions->bindButton('pushLeft', Key::A);

        $this->inputContext->registerAndActivate('main', $actions);

        // again you don't have to use a camera at all
        // we use one because in this example we don't want to couple 
        // the viewport to the actual window size
        $this->camera = new Camera(CameraProjectionMode::orthographicStaticWorld, new Transform);
        // in this quickstart example we use VG which with a camera 
        // this forces us to flip the viewport in Y direction so that -y is up
        $this->camera->flipViewportY = true;

        // load the inconsolata font to display the current score
        if ($this->vg->createFont('inconsolata', VISU_PATH_FRAMEWORK_RESOURCES_FONT . '/inconsolata/Inconsolata-Regular.ttf') === -1) {
            throw new Error('Inconsolata font could not be loaded.');
        }
    }

    private function generateTestCanvasBuffer(int $frame): array
    {
        $width = 256;
        $height = 224;
        $buffer = [];

        // Animate by shifting colors based on frame.
        $shift = ($frame * 5) % 256; // Change 5 per frame, wrap at 256.

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Create a moving gradient
                $r = ($x + $shift) % 256;
                $g = ($y + $shift) % 256;
                $b = (128 + $shift) % 256;
                $a = 255;

                $buffer[] = $r;
                $buffer[] = $g;
                $buffer[] = $b;
                $buffer[] = $a;
            }
        }

        return $buffer;
    }

    /**
     * Draw the scene. (You most definetly want to use this)
     *
     * This is called from within the Quickstart render pass where the pipeline is already
     * prepared, a VG frame is also already started.
     *
     * @throws \VISU\Exception\VISUException
     */
    public function draw(RenderContext $context, RenderTarget $renderTarget): void
    {
        $preserveAspect = false;

        // 1. Clear screen, setup camera/view.
        $renderTarget->framebuffer()->clear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);
        $viewport = $this->camera->getViewport($renderTarget);
        $this->camera->transformVGSpace($viewport, $this->vg);

        // 2. Generate test buffer.
        $rawBuffer = $this->generateTestCanvasBuffer($this->frameIndex);

        // 3. Upload to texture.
        $width = 256;
        $height = 224;

        $buffer = new UByteBuffer($rawBuffer);
        $texture = Texture2D::fromBuffer($width, $height, $buffer);
        $image = $this->vg->imageFromTexture($texture, VGImage::REPEAT_NONE, VGImage::FILTER_NEAREST);

        // 4. Compute scaling factors.
        $scaleX = $viewport->width / $width;
        $scaleY = $viewport->height / $height;

        if ($preserveAspect) {
            // Scale uniformly to fit within viewport.
            $scale = min($scaleX, $scaleY);
            $scaleX = $scaleY = $scale;
        }

        // Center within viewport.
        $offsetX = ($viewport->width - ($width * $scaleX)) / 2;
        $offsetY = ($viewport->height - ($height * $scaleY)) / 2;
        $topLeft = $viewport->getTopLeft();

        // 5. Begin VG draw
        $this->vg->save();

        // Translate and scale VG space so NES image fills or fits viewport.
        $this->vg->translate($topLeft->x + $offsetX, $topLeft->y + $offsetY);
        $this->vg->scale($scaleX, $scaleY);

        // Draw the image in NES native coordinates. (0–256, 0–224)
        $this->vg->beginPath();
        $this->vg->rect(0.0, 0.0, $width, $height);

        // Make a paint that maps 1:1 onto this rect.
        $paint = $image->makePaint(0.0, 0.0, $width, $height);
        $this->vg->fillPaint($paint);
        $this->vg->fill();

        $this->vg->restore();
    }

    /**
     * Update the games state
     * This method might be called multiple times per frame, or not at all if
     * the frame rate is very high.
     * 
     * The update method should step the game forward in time, this is the place
     * where you would update the position of your game objects, check for collisions
     * and so on. 
     */
    public function update() : void
    {
        parent::update();

        // handle key presses
//        if ($this->inputContext->actions->didButtonPress('bounce')) {
//            $this->ballVelocity->y = -3.0;
//        }
//
//        if ($this->inputContext->actions->isButtonDown('pushRight')) {
//            $this->ballVelocity->x = $this->ballVelocity->x + 0.1;
//        }
//
//        if ($this->inputContext->actions->isButtonDown('pushLeft')) {
//            $this->ballVelocity->x = $this->ballVelocity->x - 0.1;
//        }
//
//        // apply gravity
//        $this->ballVelocity = $this->ballVelocity + new Vec2(0.0, 0.1);
//
//        // apply friction
//        $this->ballVelocity = $this->ballVelocity * 0.99;
//
//        // apply velocity to position
//        $this->ballPosition = $this->ballPosition + $this->ballVelocity;
//
//        // we can only continue with a valid viewport
//        if ($this->viewport === null) {
//            return;
//        }
//
//        // check bottom boundary
//        if ($this->ballPosition->y > $this->viewport->bottom - $this->ballRadius) {
//            $this->ballPosition->y = $this->viewport->bottom - $this->ballRadius;
//            $this->ballVelocity->y = -$this->ballVelocity->y * 0.8;
//        }
//
//        // check top boundary
//        if ($this->ballPosition->y < $this->viewport->top + $this->ballRadius) {
//            $this->ballPosition->y = $this->viewport->top + $this->ballRadius;
//            $this->ballVelocity->y = -$this->ballVelocity->y * 0.8;
//        }
//
//        // check left boundary
//        if ($this->ballPosition->x < $this->viewport->left + $this->ballRadius) {
//            $this->ballPosition->x = $this->viewport->left + $this->ballRadius;
//            $this->ballVelocity->x = -$this->ballVelocity->x * 0.8;
//        }
//
//        // check right boundary
//        if ($this->ballPosition->x > $this->viewport->right - $this->ballRadius) {
//            $this->ballPosition->x = $this->viewport->right - $this->ballRadius;
//            $this->ballVelocity->x = -$this->ballVelocity->x * 0.8;
//        }
    }
}
