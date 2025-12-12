<?php

declare(strict_types=1);

namespace App\Graphics;

use App\Graphics\Objects\RenderingData;
use App\Graphics\Objects\Sprite;
use App\Graphics\Objects\Tile;

class Renderer
{
    /**
     * NES color palette mapping palette indices to RGB values.
     * Flattened for faster access: index * 3 gives R, +1 gives G, +2 gives B.
     *
     * @var list<int>
     */
    private const array COLORS_FLAT = [
        // Row 0
        0x80, 0x80, 0x80,  0x00, 0x3D, 0xA6,  0x00, 0x12, 0xB0,  0x44, 0x00, 0x96,
        0xA1, 0x00, 0x5E,  0xC7, 0x00, 0x28,  0xBA, 0x06, 0x00,  0x8C, 0x17, 0x00,
        0x5C, 0x2F, 0x00,  0x10, 0x45, 0x00,  0x05, 0x4A, 0x00,  0x00, 0x47, 0x2E,
        0x00, 0x41, 0x66,  0x00, 0x00, 0x00,  0x05, 0x05, 0x05,  0x05, 0x05, 0x05,
        // Row 1
        0xC7, 0xC7, 0xC7,  0x00, 0x77, 0xFF,  0x21, 0x55, 0xFF,  0x82, 0x37, 0xFA,
        0xEB, 0x2F, 0xB5,  0xFF, 0x29, 0x50,  0xFF, 0x22, 0x00,  0xD6, 0x32, 0x00,
        0xC4, 0x62, 0x00,  0x35, 0x80, 0x00,  0x05, 0x8F, 0x00,  0x00, 0x8A, 0x55,
        0x00, 0x99, 0xCC,  0x21, 0x21, 0x21,  0x09, 0x09, 0x09,  0x09, 0x09, 0x09,
        // Row 2
        0xFF, 0xFF, 0xFF,  0x0F, 0xD7, 0xFF,  0x69, 0xA2, 0xFF,  0xD4, 0x80, 0xFF,
        0xFF, 0x45, 0xF3,  0xFF, 0x61, 0x8B,  0xFF, 0x88, 0x33,  0xFF, 0x9C, 0x12,
        0xFA, 0xBC, 0x20,  0x9F, 0xE3, 0x0E,  0x2B, 0xF0, 0x35,  0x0C, 0xF0, 0xA4,
        0x05, 0xFB, 0xFF,  0x5E, 0x5E, 0x5E,  0x0D, 0x0D, 0x0D,  0x0D, 0x0D, 0x0D,
        // Row 3
        0xFF, 0xFF, 0xFF,  0xA6, 0xFC, 0xFF,  0xB3, 0xEC, 0xFF,  0xDA, 0xAB, 0xEB,
        0xFF, 0xA8, 0xF9,  0xFF, 0xAB, 0xB3,  0xFF, 0xD2, 0xB0,  0xFF, 0xEF, 0xA6,
        0xFF, 0xF7, 0x9C,  0xD7, 0xE8, 0x95,  0xA6, 0xED, 0xAF,  0xA2, 0xF2, 0xDA,
        0x99, 0xFF, 0xFC,  0xDD, 0xDD, 0xDD,  0x11, 0x11, 0x11,  0x11, 0x11, 0x11,
    ];

    /**
     * The framebuffer storing RGBA pixel data.
     *
     * @var list<int>
     */
    private array $frameBuffer;

    /**
     * Cached background tiles for sprite priority checking.
     *
     * @var list<Tile>
     */
    private array $background = [];

    /**
     * Pre-computed RGBA colors for current palette (32 entries × 4 components).
     *
     * @var list<int>
     */
    private array $paletteRgba = [];

    /**
     * Creates a new renderer and initializes the framebuffer.
     */
    public function __construct()
    {
        $this->frameBuffer = array_fill(0, 256 * 256 * 4, 0);
    }

    /**
     * Renders NES graphics data to an RGBA framebuffer.
     *
     * @return list<int>
     */
    public function render(RenderingData $data): array
    {
        // Use array_fill which is implemented in C and much faster than a PHP loop.
        $this->frameBuffer = array_fill(0, 256 * 256 * 4, 0);

        // Pre-compute RGBA values for the current palette.
        $this->buildPaletteRgba($data->palette);

        if ($data->background !== null && $data->background !== []) {
            $this->renderBackground($data->background);
        }

        if ($data->sprites !== null && $data->sprites !== []) {
            $this->renderSprites($data->sprites);
        }

        return $this->frameBuffer;
    }

    /**
     * Pre-computes RGBA values for all 32 palette entries.
     *
     * @param list<int> $palette
     */
    private function buildPaletteRgba(array $palette): void
    {
        $this->paletteRgba = [];
        for ($i = 0; $i < 32; $i++) {
            $colorId = $palette[$i] ?? 0;
            $colorIdx = $colorId * 3;
            $this->paletteRgba[] = self::COLORS_FLAT[$colorIdx];
            $this->paletteRgba[] = self::COLORS_FLAT[$colorIdx + 1];
            $this->paletteRgba[] = self::COLORS_FLAT[$colorIdx + 2];
            $this->paletteRgba[] = 0xFF;
        }
    }

    /**
     * Renders all background tiles to the framebuffer.
     *
     * @param list<Tile> $background
     */
    private function renderBackground(array $background): void
    {
        $this->background = $background;
        $count = count($background);

        for ($idx = 0; $idx < $count; $idx++) {
            $tile = $background[$idx];
            $tileX = ($idx % 33) * 8;
            $tileY = (int) ($idx / 33) * 8;
            $offsetX = $tile->scrollX % 8;
            $offsetY = $tile->scrollY % 8;
            $paletteBase = $tile->paletteId * 4 * 4; // 4 colors × 4 components (RGBA).
            $pattern = $tile->pattern;

            for ($i = 0; $i < 8; $i++) {
                $y = $tileY + $i - $offsetY;
                if ($y < 0 || $y >= 224) {
                    continue;
                }

                $rowBase = $y * 256 * 4;
                $patternRow = $pattern[$i];

                for ($j = 0; $j < 8; $j++) {
                    $x = $tileX + $j - $offsetX;
                    if ($x < 0 || $x > 255) {
                        continue;
                    }

                    $colorOffset = $paletteBase + $patternRow[$j] * 4;
                    $index = $rowBase + $x * 4;

                    $this->frameBuffer[$index] = $this->paletteRgba[$colorOffset];
                    $this->frameBuffer[$index + 1] = $this->paletteRgba[$colorOffset + 1];
                    $this->frameBuffer[$index + 2] = $this->paletteRgba[$colorOffset + 2];
                    $this->frameBuffer[$index + 3] = 0xFF;
                }
            }
        }
    }

    /**
     * Renders all sprites to the framebuffer.
     *
     * @param list<Sprite> $sprites
     */
    private function renderSprites(array $sprites): void
    {
        foreach ($sprites as $sprite) {
            if ($sprite !== null) {
                $this->renderSprite($sprite);
            }
        }
    }

    /**
     * Renders a single sprite to the framebuffer.
     */
    private function renderSprite(Sprite $sprite): void
    {
        $isVerticalReverse = ($sprite->attribute & 0x80) !== 0;
        $isHorizontalReverse = ($sprite->attribute & 0x40) !== 0;
        $isLowPriority = ($sprite->attribute & 0x20) !== 0;
        $paletteBase = (($sprite->attribute & 0x03) * 4 + 16) * 4; // Sprite palette starts at 16.

        $baseX = (int) $sprite->coordinates->x;
        $baseY = (int) $sprite->coordinates->y;
        $spritePattern = $sprite->sprite;

        for ($i = 0; $i < 8; $i++) {
            $y = $baseY + ($isVerticalReverse ? 7 - $i : $i);
            if ($y < 0 || $y >= 224) {
                continue;
            }

            $rowBase = $y * 256 * 4;
            $patternRow = $spritePattern[$i];

            for ($j = 0; $j < 8; $j++) {
                $patternValue = $patternRow[$j];
                if ($patternValue === 0) {
                    continue; // Transparent pixel.
                }

                $x = $baseX + ($isHorizontalReverse ? 7 - $j : $j);
                if ($x < 0 || $x > 255) {
                    continue;
                }

                if ($isLowPriority && $this->isBackgroundPixelOpaque($x, $y)) {
                    continue;
                }

                $colorOffset = $paletteBase + $patternValue * 4;
                $index = $rowBase + $x * 4;

                $this->frameBuffer[$index] = $this->paletteRgba[$colorOffset];
                $this->frameBuffer[$index + 1] = $this->paletteRgba[$colorOffset + 1];
                $this->frameBuffer[$index + 2] = $this->paletteRgba[$colorOffset + 2];
                $this->frameBuffer[$index + 3] = 0xFF;
            }
        }
    }

    /**
     * Checks if the background pixel at the given position is opaque (non-zero pattern value).
     */
    private function isBackgroundPixelOpaque(int $x, int $y): bool
    {
        $tileX = (int) ($x / 8);
        $tileY = (int) ($y / 8);
        $backgroundIndex = $tileY * 33 + $tileX;

        if (!isset($this->background[$backgroundIndex])) {
            return false;
        }

        $patternValue = $this->background[$backgroundIndex]->pattern[$y % 8][$x % 8];

        return ($patternValue % 4) !== 0;
    }
}
