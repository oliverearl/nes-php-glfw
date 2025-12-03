<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics\Objects;

use ReflectionClass;
use App\Graphics\Objects\Tile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tile::class)]
final class TileTest extends TestCase
{
    #[Test]
    public function it_creates_tile_with_all_properties(): void
    {
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));

        $tile = new Tile(
            pattern: $pattern,
            paletteId: 2,
            scrollX: 10,
            scrollY: 20,
        );

        $this::assertSame($pattern, $tile->pattern);
        $this::assertSame(2, $tile->paletteId);
        $this::assertSame(10, $tile->scrollX);
        $this::assertSame(20, $tile->scrollY);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $tile = new Tile(
            pattern: array_fill(0, 8, array_fill(0, 8, 0)),
            paletteId: 0,
            scrollX: 0,
            scrollY: 0,
        );

        $reflection = new ReflectionClass($tile);
        $this::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function it_stores_8x8_pattern(): void
    {
        $pattern = [
            [1, 0, 0, 0, 0, 0, 0, 1],
            [0, 1, 0, 0, 0, 0, 1, 0],
            [0, 0, 1, 0, 0, 1, 0, 0],
            [0, 0, 0, 1, 1, 0, 0, 0],
            [0, 0, 0, 1, 1, 0, 0, 0],
            [0, 0, 1, 0, 0, 1, 0, 0],
            [0, 1, 0, 0, 0, 0, 1, 0],
            [1, 0, 0, 0, 0, 0, 0, 1],
        ];

        $tile = new Tile(
            pattern: $pattern,
            paletteId: 1,
            scrollX: 0,
            scrollY: 0,
        );

        $this::assertSame($pattern, $tile->pattern);
        $this::assertCount(8, $tile->pattern);
        $this::assertCount(8, $tile->pattern[0]);
    }

    #[Test]
    public function it_handles_different_palette_ids(): void
    {
        for ($paletteId = 0; $paletteId < 4; $paletteId++) {
            $tile = new Tile(
                pattern: array_fill(0, 8, array_fill(0, 8, 0)),
                paletteId: $paletteId,
                scrollX: 0,
                scrollY: 0,
            );

            $this::assertSame($paletteId, $tile->paletteId);
        }
    }

    #[Test]
    public function it_handles_various_scroll_values(): void
    {
        $tile = new Tile(
            pattern: array_fill(0, 8, array_fill(0, 8, 0)),
            paletteId: 0,
            scrollX: 255,
            scrollY: 239,
        );

        $this::assertSame(255, $tile->scrollX);
        $this::assertSame(239, $tile->scrollY);
    }

    #[Test]
    public function it_handles_zero_scroll(): void
    {
        $tile = new Tile(
            pattern: array_fill(0, 8, array_fill(0, 8, 0)),
            paletteId: 0,
            scrollX: 0,
            scrollY: 0,
        );

        $this::assertSame(0, $tile->scrollX);
        $this::assertSame(0, $tile->scrollY);
    }
}
