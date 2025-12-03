<?php

declare(strict_types=1);

namespace Tests\Unit\Graphics\Objects;

use ReflectionClass;
use App\Graphics\Objects\Sprite;
use GL\Math\Vec2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sprite::class)]
final class SpriteTest extends TestCase
{
    #[Test]
    public function it_creates_sprite_with_all_properties(): void
    {
        $pattern = array_fill(0, 8, array_fill(0, 8, 0));
        $coordinates = new Vec2(100, 50);

        $sprite = new Sprite(
            sprite: $pattern,
            coordinates: $coordinates,
            attribute: 0x42,
            id: 5,
        );

        $this::assertSame($pattern, $sprite->sprite);
        $this::assertSame($coordinates, $sprite->coordinates);
        $this::assertSame(0x42, $sprite->attribute);
        $this::assertSame(5, $sprite->id);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(0, 0),
            attribute: 0,
            id: 0,
        );

        $reflection = new ReflectionClass($sprite);
        $this::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function it_stores_sprite_coordinates(): void
    {
        $coords = new Vec2(123.5, 67.8);

        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: $coords,
            attribute: 0,
            id: 0,
        );

        // Use delta for floating point comparison
        $this::assertEqualsWithDelta(123.5, $sprite->coordinates->x, 0.001);
        $this::assertEqualsWithDelta(67.8, $sprite->coordinates->y, 0.001);
    }

    #[Test]
    public function it_handles_attribute_flags(): void
    {
        $testCases = [
            0x00, // No flags
            0x20, // Priority
            0x40, // Horizontal flip
            0x80, // Vertical flip
            0xE3, // All flags + palette
        ];

        foreach ($testCases as $attribute) {
            $sprite = new Sprite(
                sprite: array_fill(0, 8, array_fill(0, 8, 0)),
                coordinates: new Vec2(0, 0),
                attribute: $attribute,
                id: 0,
            );

            $this::assertSame($attribute, $sprite->attribute);
        }
    }

    #[Test]
    public function it_handles_sprite_ids(): void
    {
        for ($id = 0; $id < 64; $id++) {
            $sprite = new Sprite(
                sprite: array_fill(0, 8, array_fill(0, 8, 0)),
                coordinates: new Vec2(0, 0),
                attribute: 0,
                id: $id,
            );

            $this::assertSame($id, $sprite->id);
        }
    }

    #[Test]
    public function it_stores_8x8_sprite_pattern(): void
    {
        $pattern = [
            [0, 0, 1, 1, 1, 1, 0, 0],
            [0, 1, 2, 2, 2, 2, 1, 0],
            [1, 2, 3, 3, 3, 3, 2, 1],
            [1, 2, 3, 3, 3, 3, 2, 1],
            [1, 2, 3, 3, 3, 3, 2, 1],
            [1, 2, 3, 3, 3, 3, 2, 1],
            [0, 1, 2, 2, 2, 2, 1, 0],
            [0, 0, 1, 1, 1, 1, 0, 0],
        ];

        $sprite = new Sprite(
            sprite: $pattern,
            coordinates: new Vec2(0, 0),
            attribute: 0,
            id: 0,
        );

        $this::assertSame($pattern, $sprite->sprite);
        $this::assertCount(8, $sprite->sprite);
        $this::assertCount(8, $sprite->sprite[0]);
    }

    #[Test]
    public function it_handles_negative_coordinates(): void
    {
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(-8, -8),
            attribute: 0,
            id: 0,
        );

        $this::assertSame(-8.0, $sprite->coordinates->x);
        $this::assertSame(-8.0, $sprite->coordinates->y);
    }

    #[Test]
    public function it_handles_boundary_coordinates(): void
    {
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(255, 239),
            attribute: 0,
            id: 0,
        );

        $this::assertSame(255.0, $sprite->coordinates->x);
        $this::assertSame(239.0, $sprite->coordinates->y);
    }

    #[Test]
    public function it_extracts_palette_from_attribute(): void
    {
        // Palette is in lower 2 bits of attribute
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(0, 0),
            attribute: 0x03, // Palette 3
            id: 0,
        );

        $paletteId = $sprite->attribute & 0x03;
        $this::assertSame(3, $paletteId);
    }

    #[Test]
    public function it_detects_horizontal_flip_flag(): void
    {
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(0, 0),
            attribute: 0x40,
            id: 0,
        );

        $isHorizontalFlip = (bool) ($sprite->attribute & 0x40);
        $this::assertTrue($isHorizontalFlip);
    }

    #[Test]
    public function it_detects_vertical_flip_flag(): void
    {
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(0, 0),
            attribute: 0x80,
            id: 0,
        );

        $isVerticalFlip = (bool) ($sprite->attribute & 0x80);
        $this::assertTrue($isVerticalFlip);
    }

    #[Test]
    public function it_detects_priority_flag(): void
    {
        $sprite = new Sprite(
            sprite: array_fill(0, 8, array_fill(0, 8, 0)),
            coordinates: new Vec2(0, 0),
            attribute: 0x20,
            id: 0,
        );

        $isLowPriority = (bool) ($sprite->attribute & 0x20);
        $this::assertTrue($isLowPriority);
    }
}
