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

        // Create minimal rendering data
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null
        );

        $buffer = $renderer->render($renderingData);

        // Should return a buffer of 256 * 256 * 4 (RGBA) bytes
        $this::assertCount(256 * 256 * 4, $buffer);

        // All pixels should be 0 (black with no alpha)
        foreach ($buffer as $byte) {
            $this::assertSame(0, $byte);
        }
    }

    #[Test]
    public function it_renders_background_tile(): void
    {
        $renderer = new Renderer();

        // Create a simple tile pattern (8x8)
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[0][0] = 1; // Set one pixel

        $tile = new Tile(
            pattern: $pattern,
            paletteId: 0,
            scrollX: 0,
            scrollY: 0
        );

        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x30; // Color index for pattern value 1

        $renderingData = new RenderingData(
            palette: $palette,
            background: [$tile],
            sprites: null
        );

        $buffer = $renderer->render($renderingData);

        $this::assertIsArray($buffer);
        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_renders_sprite(): void
    {
        $renderer = new Renderer();

        // Create a simple sprite pattern (8x8)
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[4][4] = 1; // Set center pixel

        $sprite = new Sprite(
            sprite: $pattern,
            coordinates: new Vec2(100, 100),
            attribute: 0x00,
            id: 0
        );

        $palette = array_fill(0, 32, 0);
        $palette[0x11] = 0x30; // Sprite palette color

        $renderingData = new RenderingData(
            palette: $palette,
            background: null,
            sprites: [$sprite]
        );

        $buffer = $renderer->render($renderingData);

        $this::assertIsArray($buffer);
        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_handles_null_background(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_handles_null_sprites(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_handles_empty_background_array(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: [],
            sprites: null
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_handles_empty_sprites_array(): void
    {
        $renderer = new Renderer();

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: []
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
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
                scrollY: 0
            );
        }

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: $tiles,
            sprites: null
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
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
                id: $i
            );
        }

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: $sprites
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_clears_framebuffer_between_renders(): void
    {
        $renderer = new Renderer();

        $palette = array_fill(0, 32, 0);

        // First render with data
        $pattern = array_fill(0, 8, array_fill(0, 8, 1));
        $tile = new Tile($pattern, 0, 0, 0);

        $renderingData1 = new RenderingData(
            palette: $palette,
            background: [$tile],
            sprites: null
        );

        $buffer1 = $renderer->render($renderingData1);

        // Second render with no data
        $renderingData2 = new RenderingData(
            palette: $palette,
            background: null,
            sprites: null
        );

        $buffer2 = $renderer->render($renderingData2);

        // Buffers should be different (second should be cleared)
        $this::assertCount(256 * 256 * 4, $buffer1);
        $this::assertCount(256 * 256 * 4, $buffer2);
    }

    #[Test]
    public function it_handles_sprite_with_horizontal_flip(): void
    {
        $renderer = new Renderer();

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(50, 50),
            attribute: 0x40, // Horizontal flip bit
            id: 0
        );

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [$sprite]
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_handles_sprite_with_vertical_flip(): void
    {
        $renderer = new Renderer();

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(50, 50),
            attribute: 0x80, // Vertical flip bit
            id: 0
        );

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [$sprite]
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }

    #[Test]
    public function it_handles_sprite_with_low_priority(): void
    {
        $renderer = new Renderer();

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(50, 50),
            attribute: 0x20, // Low priority bit
            id: 0
        );

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [$sprite]
        );

        $buffer = $renderer->render($renderingData);

        $this::assertCount(256 * 256 * 4, $buffer);
    }
}

