<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics;

use Iterator;
use App\Graphics\Objects\RenderingData;
use App\Graphics\Objects\Sprite;
use App\Graphics\Objects\Tile;
use App\Graphics\Renderer;
use GL\Math\Vec2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertPixel($buffer, 0, 0, [0xFF, 0xFF, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 1, 0, [0x80, 0x80, 0x80, 0xFF]);
        $this->assertPixel($buffer, 8, 0, [0, 0, 0, 0]);
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
        $this->assertPixel($buffer, 104, 104, [0xFF, 0xFF, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 100, 100, [0, 0, 0, 0]);
        $this->assertPixel($buffer, 108, 104, [0, 0, 0, 0]);
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
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[1][2] = 1;

        $sprite = new Sprite(
            sprite: $pattern,
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
        $this->assertPixel($buffer, 55, 51, [0x80, 0x80, 0x80, 0xFF]);
        $this->assertPixel($buffer, 52, 51, [0, 0, 0, 0]);
    }

    #[Test]
    public function it_handles_sprite_with_vertical_flip(): void
    {
        $renderer = new Renderer();
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[1][2] = 1;

        $sprite = new Sprite(
            sprite: $pattern,
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
        $this->assertPixel($buffer, 52, 56, [0x80, 0x80, 0x80, 0xFF]);
        $this->assertPixel($buffer, 52, 51, [0, 0, 0, 0]);
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
     * Checks tile columns and rows map to exact framebuffer coordinates.
     */
    #[Test]
    public function it_places_background_tiles_in_their_expected_columns_and_rows(): void
    {
        $blank = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern = $blank;
        $pattern[2][3] = 1;
        $tiles = array_fill(0, 34, new Tile($blank, 0, 0, 0));
        $tiles[1] = new Tile($pattern, 0, 0, 0);
        $tiles[33] = new Tile($pattern, 0, 0, 0);
        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x16;
        $buffer = new Renderer()->render(new RenderingData($palette, $tiles, null));

        $this->assertPixel($buffer, 11, 2, [0xFF, 0x22, 0, 0xFF]);
        $this->assertPixel($buffer, 3, 10, [0xFF, 0x22, 0, 0xFF]);
        $this->assertPixel($buffer, 3, 2, [0x80, 0x80, 0x80, 0xFF]);
        $this->assertPixel($buffer, 11, 10, [0, 0, 0, 0]);
    }

    /**
     * Checks background pattern zero selects the universal backdrop across palettes.
     */
    #[Test]
    public function it_uses_the_universal_background_color_for_transparent_pattern_pixels(): void
    {
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[0][1] = 1;
        $palette = array_fill(0, 32, 0);
        $palette[0] = 0x21;
        $palette[4] = 0x30;
        $palette[5] = 0x16;
        $buffer = new Renderer()->render(new RenderingData($palette, [new Tile($pattern, 1, 0, 0)], null));

        $this->assertPixel($buffer, 0, 0, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 1, 0, [0xFF, 0x22, 0, 0xFF]);
    }

    /**
     * Checks transparent sprite pixels leave the underlying background unchanged.
     */
    #[Test]
    public function it_preserves_background_under_transparent_sprite_pixels(): void
    {
        $background = new Tile(array_fill(0, 8, array_fill(0, 8, 1)), 0, 0, 0);
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[0][0] = 1;
        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x16;
        $palette[0x10] = 0x30;
        $palette[0x11] = 0x21;
        $sprite = new Sprite($pattern, new Vec2(2, 3), 0, 0);
        $buffer = new Renderer()->render(new RenderingData($palette, [$background], [$sprite]));

        $this->assertPixel($buffer, 2, 3, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 3, 3, [0xFF, 0x22, 0, 0xFF]);
    }

    /**
     * Checks palette cache changes update every RGB channel without altering saved frames.
     */
    #[Test]
    public function it_rebuilds_colors_when_the_palette_changes_between_frames(): void
    {
        $renderer = new Renderer();
        $tile = new Tile(array_fill(0, 8, array_fill(0, 8, 1)), 0, 0, 0);
        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x21;
        $first = $renderer->render(new RenderingData($palette, [$tile], null));
        $palette[1] = 0x16;
        $second = $renderer->render(new RenderingData($palette, [$tile], null));

        $this->assertPixel($first, 0, 0, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($second, 0, 0, [0xFF, 0x22, 0, 0xFF]);
        $this->assertPixel($first, 7, 7, [0x0F, 0xD7, 0xFF, 0xFF]);
    }

    /**
     * Checks partial tiles at the scrolled top and left boundaries select correct source pixels.
     */
    #[Test]
    public function it_clips_background_tiles_after_fine_scrolling(): void
    {
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $pattern[3][2] = 1;
        $pattern[7][7] = 2;
        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x21;
        $palette[2] = 0x16;
        $buffer = new Renderer()->render(new RenderingData($palette, [new Tile($pattern, 0, 2, 3)], null));

        $this->assertPixel($buffer, 0, 0, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 5, 4, [0xFF, 0x22, 0, 0xFF]);
        $this->assertPixel($buffer, 6, 4, [0, 0, 0, 0]);
        $this->assertPixel($buffer, 5, 5, [0, 0, 0, 0]);
    }

    /**
     * Checks all four framebuffer boundaries clip sprite pixels without row wrapping.
     */
    #[Test]
    public function it_clips_sprites_at_all_screen_boundaries(): void
    {
        $pattern = array_fill(0, 8, array_fill(0, 8, 1));
        $palette = array_fill(0, 32, 0);
        $palette[0x11] = 0x21;
        $sprites = [new Sprite($pattern, new Vec2(-6, -5), 0, 0), new Sprite($pattern, new Vec2(254, 222), 0, 1)];
        $buffer = new Renderer()->render(new RenderingData($palette, null, $sprites));

        $this::assertCount(256 * 224 * 4, $buffer);
        $this->assertPixel($buffer, 0, 0, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 1, 2, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 2, 2, [0, 0, 0, 0]);
        $this->assertPixel($buffer, 1, 3, [0, 0, 0, 0]);
        $this->assertPixel($buffer, 255, 223, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 253, 223, [0, 0, 0, 0]);
        $this->assertPixel($buffer, 0, 223, [0, 0, 0, 0]);
    }

    /**
     * Checks priority uses the visible background pixel after both fine scroll offsets.
     */
    #[Test]
    public function it_tests_low_priority_sprites_against_scrolled_background_pixels(): void
    {
        $background = array_fill(0, 8, array_fill(0, 8, 0));
        $background[1][1] = 1;
        $sprite = array_fill(0, 8, array_fill(0, 8, 1));
        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x16;
        $palette[0x11] = 0x21;
        $data = new RenderingData($palette, [new Tile($background, 0, 1, 1)], [new Sprite($sprite, new Vec2(0, 0), 0x20, 0)]);
        $buffer = new Renderer()->render($data);

        $this->assertPixel($buffer, 0, 0, [0xFF, 0x22, 0, 0xFF]);
        $this->assertPixel($buffer, 1, 1, [0x0F, 0xD7, 0xFF, 0xFF]);
        $this->assertPixel($buffer, 7, 7, [0x0F, 0xD7, 0xFF, 0xFF]);
    }

    /**
     * Checks the first nontransparent OAM sprite wins before background priority is applied.
     *
     * @param list<int> $expected
     */
    #[Test]
    #[DataProvider('spritePriorityModes')]
    public function it_selects_the_first_opaque_sprite_before_applying_background_priority(int $firstAttribute, array $expected): void
    {
        $background = array_fill(0, 8, array_fill(0, 8, 1));
        $first = array_fill(0, 8, array_fill(0, 8, 0));
        $first[0][0] = 1;
        $second = array_fill(0, 8, array_fill(0, 8, 1));
        $palette = array_fill(0, 32, 0);
        $palette[1] = 0x16;
        $palette[0x11] = 0x21;
        $palette[0x15] = 0x30;
        $sprites = [new Sprite($first, new Vec2(0, 0), $firstAttribute, 9), new Sprite($second, new Vec2(0, 0), 1, 2)];
        $buffer = new Renderer()->render(new RenderingData($palette, [new Tile($background, 0, 0, 0)], $sprites));

        $this->assertPixel($buffer, 0, 0, $expected);
        $this->assertPixel($buffer, 1, 0, [0xFF, 0xFF, 0xFF, 0xFF]);
    }

    /**
     * Supplies expected colors when the selected first sprite is in front of or behind background.
     *
     * @return \Iterator<string, array{int, list<int>}>
     */
    public static function spritePriorityModes(): Iterator
    {
        yield 'in front' => [0, [0x0F, 0xD7, 0xFF, 0xFF]];
        yield 'behind' => [0x20, [0xFF, 0x22, 0, 0xFF]];
    }

    /**
     * Asserts exact red, green, blue, and alpha bytes for a visible pixel.
     *
     * @param list<int> $buffer
     * @param list<int> $expected
     */
    private function assertPixel(array $buffer, int $x, int $y, array $expected): void
    {
        $this::assertSame($expected, array_slice($buffer, $this->pixelOffset($x, $y), 4), "Pixel ({$x}, {$y}).");
    }

    /**
     * Calculates the RGBA framebuffer offset for a pixel.
     */
    private function pixelOffset(int $x, int $y): int
    {
        return ($y * 256 + $x) * 4;
    }
}
