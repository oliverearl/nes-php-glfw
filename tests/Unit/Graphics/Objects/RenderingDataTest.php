<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics\Objects;

use App\Graphics\Objects\RenderingData;
use App\Graphics\Objects\Sprite;
use App\Graphics\Objects\Tile;
use GL\Math\Vec2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderingData::class)]
final class RenderingDataTest extends TestCase
{
    #[Test]
    public function it_creates_with_all_properties(): void
    {
        $palette = array_fill(0, 32, 0);
        $background = [
            new Tile(array_fill(0, 8, array_fill(0, 8, 0)), 0, 0, 0),
        ];
        $sprites = [
            new Sprite(array_fill(0, 8, array_fill(0, 8, 0)), new Vec2(0, 0), 0, 0),
        ];

        $renderingData = new RenderingData(
            palette: $palette,
            background: $background,
            sprites: $sprites,
        );

        $this::assertSame($palette, $renderingData->palette);
        $this::assertSame($background, $renderingData->background);
        $this::assertSame($sprites, $renderingData->sprites);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null,
        );

        $reflection = new \ReflectionClass($renderingData);
        $this::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function it_handles_null_background(): void
    {
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null,
        );

        $this::assertNull($renderingData->background);
    }

    #[Test]
    public function it_handles_null_sprites(): void
    {
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: null,
        );

        $this::assertNull($renderingData->sprites);
    }

    #[Test]
    public function it_handles_empty_background_array(): void
    {
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: [],
            sprites: null,
        );

        $this::assertIsArray($renderingData->background);
        $this::assertCount(0, $renderingData->background);
    }

    #[Test]
    public function it_handles_empty_sprites_array(): void
    {
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [],
        );

        $this::assertIsArray($renderingData->sprites);
        $this::assertCount(0, $renderingData->sprites);
    }

    #[Test]
    public function it_stores_32_palette_entries(): void
    {
        $palette = range(0, 31);

        $renderingData = new RenderingData(
            palette: $palette,
            background: null,
            sprites: null,
        );

        $this::assertCount(32, $renderingData->palette);
        $this::assertSame($palette, $renderingData->palette);
    }

    #[Test]
    public function it_stores_multiple_background_tiles(): void
    {
        $tiles = [];
        for ($i = 0; $i < 33 * 30; $i++) { // Full screen of tiles
            $tiles[] = new Tile(
                array_fill(0, 8, array_fill(0, 8, 0)),
                0,
                0,
                0,
            );
        }

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: $tiles,
            sprites: null,
        );

        $this::assertCount(33 * 30, $renderingData->background);
    }

    #[Test]
    public function it_stores_multiple_sprites(): void
    {
        $sprites = [];
        for ($i = 0; $i < 64; $i++) { // Maximum OAM sprites
            $sprites[] = new Sprite(
                array_fill(0, 8, array_fill(0, 8, 0)),
                new Vec2($i * 4, $i * 4),
                0,
                $i,
            );
        }

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: $sprites,
        );

        $this::assertCount(64, $renderingData->sprites);
    }

    #[Test]
    public function it_can_have_both_background_and_sprites(): void
    {
        $background = [
            new Tile(array_fill(0, 8, array_fill(0, 8, 0)), 0, 0, 0),
        ];
        $sprites = [
            new Sprite(array_fill(0, 8, array_fill(0, 8, 0)), new Vec2(0, 0), 0, 0),
        ];

        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: $background,
            sprites: $sprites,
        );

        $this::assertIsArray($renderingData->background);
        $this::assertIsArray($renderingData->sprites);
        $this::assertCount(1, $renderingData->background);
        $this::assertCount(1, $renderingData->sprites);
    }

    #[Test]
    public function it_represents_disabled_background_with_null(): void
    {
        // When background rendering is disabled, it should be null
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: null,
            sprites: [
                new Sprite(array_fill(0, 8, array_fill(0, 8, 0)), new Vec2(0, 0), 0, 0),
            ],
        );

        $this::assertNull($renderingData->background);
        $this::assertNotNull($renderingData->sprites);
    }

    #[Test]
    public function it_represents_disabled_sprites_with_null(): void
    {
        // When sprite rendering is disabled, it should be null
        $renderingData = new RenderingData(
            palette: array_fill(0, 32, 0),
            background: [
                new Tile(array_fill(0, 8, array_fill(0, 8, 0)), 0, 0, 0),
            ],
            sprites: null,
        );

        $this::assertNotNull($renderingData->background);
        $this::assertNull($renderingData->sprites);
    }
}
