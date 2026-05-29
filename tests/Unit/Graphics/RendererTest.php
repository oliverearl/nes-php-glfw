<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics;

use App\Graphics\Objects\RenderingData;
use App\Graphics\Objects\Sprite;
use App\Graphics\Objects\Tile;
use App\Graphics\Renderer;
use GL\Math\Vec2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Renderer::class)]
final class RendererTest extends TestCase
{
    #[Test]
    public function it_initializes_with_empty_framebuffer(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);

        foreach ($buffer as $byte) {
            $this::assertSame(0, $byte);
        }
    }

    #[Test]
    public function it_renders_background_tile(): void
    {
        $renderer = new Renderer();

        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[0][0] = 1;

        $tile = new Tile(
            pattern: $pattern,
            paletteId: 0,
            scrollX: 0,
            scrollY: 0,
        );

        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x30;

        $renderingData = new RenderingData(
            palette: $palette,
            background: [$tile],
            sprites: null,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_renders_sprite(): void
    {
        $renderer = new Renderer();

        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[4][4] = 1;

        $sprite = new Sprite(
            sprite: $pattern,
            coordinates: new Vec2(100, 100),
            attribute: 0x00,
            id: 0,
        );

        $palette = array_fill(0, 32, 0);
        $palette[0x11] = 0x30;

        $renderingData = new RenderingData(
            palette: $palette,
            background: null,
            sprites: [$sprite],
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_handles_null_background(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_handles_null_sprites(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_handles_empty_background_array(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: [],
            sprites: null,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_handles_empty_sprites_array(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [],
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_renders_multiple_tiles(): void
    {
        $renderer = new Renderer();

        $tiles = [];
        for ($i = 0; $i < 10; $i++) {
            $tiles[] = new Tile(
                pattern: array_fill(0, 8, array_fill(0, 8, 0)),
                paletteId: 0,
                scrollX: 0,
                scrollY: 0,
            );
        }

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: $tiles,
            sprites: null,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_renders_multiple_sprites(): void
    {
        $renderer = new Renderer();

        $sprites = [];
        for ($i = 0; $i < 10; $i++) {
            $sprites[] = new Sprite(
                sprite: array_fill(0, 8, array_fill(0, 8, 0)),
                coordinates: new Vec2($i * 10, $i * 10),
                attribute: 0x00,
                id: $i,
            );
        }

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: $sprites,
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_clears_framebuffer_between_renders(): void
    {
        $renderer = new Renderer();

        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x30;

        $pattern = array_fill(0, 8, array_fill(0, 8, 1));
        $tile = new Tile($pattern, 0, 0, 0);

        $renderingData1 = new RenderingData(
            palette: $palette,
            background: [$tile],
            sprites: null,
        );

        $buffer1 = $renderer->render($renderingData1);

        $renderingData2 = new RenderingData(
            palette: $palette,
            background: null,
            sprites: null,
        );

        $buffer2 = $renderer->render($renderingData2);

        $this::assertCount(256 * 224 * 4, $buffer1);
        $this::assertCount(256 * 224 * 4, $buffer2);
        $this::assertSame(0xFF, $buffer1[$this->pixelOffset(0, 0)]);
        $this::assertSame(0, $buffer2[$this->pixelOffset(0, 0)]);
    }

    #[Test]
    public function it_clears_pixels_not_covered_by_partial_background_between_renders(): void
    {
        $renderer = new Renderer();

        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x30;

        $opaquePattern = array_fill(0, 8, array_fill(0, 8, 1));
        $transparentPattern = array_fill(0, 8, array_fill(0, 8, 0));
        $fullBackground = array_fill(0, 33 * 28, new Tile($opaquePattern, 0, 0, 0));

        $buffer1 = $renderer->render(new RenderingData(
            palette: $palette,
            background: $fullBackground,
            sprites: null,
        ));

        $buffer2 = $renderer->render(new RenderingData(
            palette: $palette,
            background: [new Tile($transparentPattern, 0, 0, 0)],
            sprites: null,
        ));

        $this::assertSame(0xFF, $buffer1[$this->pixelOffset(20, 20)]);
        $this::assertSame(0, $buffer2[$this->pixelOffset(20, 20)]);
        $this::assertSame(0, $buffer2[$this->pixelOffset(20, 20) + 3]);
    }

    #[Test]
    public function it_does_not_use_previous_background_when_rendering_low_priority_sprites_without_background(): void
    {
        $renderer = new Renderer();

        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x30;
        $palette[0x11] = 0x30;

        $backgroundPattern = array_fill(0, 8, array_fill(0, 8, 1));
        $spritePattern = array_fill(0, 8, array_fill(0, 8, 0));
        $spritePattern[0][0] = 1;

        $renderer->render(new RenderingData(
            palette: $palette,
            background: [new Tile($backgroundPattern, 0, 0, 0)],
            sprites: null,
        ));

        $buffer = $renderer->render(new RenderingData(
            palette: $palette,
            background: null,
            sprites: [
                new Sprite(
                    sprite: $spritePattern,
                    coordinates: new Vec2(0, 0),
                    attribute: 0x20,
                    id: 0,
                ),
            ],
        ));

        $this::assertSame(0xFF, $buffer[$this->pixelOffset(0, 0)]);
        $this::assertSame(0xFF, $buffer[$this->pixelOffset(0, 0) + 3]);
    }

    #[Test]
    public function it_handles_sprite_with_horizontal_flip(): void
    {
        $renderer = new Renderer();

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(50, 50),
            attribute: 0x40, // Horizontal flip bit.
            id: 0,
        );

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [$sprite],
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_handles_sprite_with_vertical_flip(): void
    {
        $renderer = new Renderer();

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(50, 50),
            attribute: 0x80, // Vertical flip bit.
            id: 0,
        );

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [$sprite],
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    #[Test]
    public function it_handles_sprite_with_low_priority(): void
    {
        $renderer = new Renderer();

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(50, 50),
            attribute: 0x20, // Low priority bit.
            id: 0,
        );

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [$sprite],
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 224 * 4, $buffer);
    }

    /**
     * Calculates the RGBA framebuffer offset for a pixel.
     */
    private function pixelOffset(int $x, int $y): int
    {
        return ($y * 256 + $x) * 4;
    }
}
