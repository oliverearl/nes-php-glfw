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
        // Row 0.
        0x80, 0x80, 0x80,  0x00, 0x3D, 0xA6,  0x00, 0x12, 0xB0,  0x44, 0x00, 0x96,
        0xA1, 0x00, 0x5E,  0xC7, 0x00, 0x28,  0xBA, 0x06, 0x00,  0x8C, 0x17, 0x00,
        0x5C, 0x2F, 0x00,  0x10, 0x45, 0x00,  0x05, 0x4A, 0x00,  0x00, 0x47, 0x2E,
        0x00, 0x41, 0x66,  0x00, 0x00, 0x00,  0x05, 0x05, 0x05,  0x05, 0x05, 0x05,
        // Row 1.
        0xC7, 0xC7, 0xC7,  0x00, 0x77, 0xFF,  0x21, 0x55, 0xFF,  0x82, 0x37, 0xFA,
        0xEB, 0x2F, 0xB5,  0xFF, 0x29, 0x50,  0xFF, 0x22, 0x00,  0xD6, 0x32, 0x00,
        0xC4, 0x62, 0x00,  0x35, 0x80, 0x00,  0x05, 0x8F, 0x00,  0x00, 0x8A, 0x55,
        0x00, 0x99, 0xCC,  0x21, 0x21, 0x21,  0x09, 0x09, 0x09,  0x09, 0x09, 0x09,
        // Row 2.
        0xFF, 0xFF, 0xFF,  0x0F, 0xD7, 0xFF,  0x69, 0xA2, 0xFF,  0xD4, 0x80, 0xFF,
        0xFF, 0x45, 0xF3,  0xFF, 0x61, 0x8B,  0xFF, 0x88, 0x33,  0xFF, 0x9C, 0x12,
        0xFA, 0xBC, 0x20,  0x9F, 0xE3, 0x0E,  0x2B, 0xF0, 0x35,  0x0C, 0xF0, 0xA4,
        0x05, 0xFB, 0xFF,  0x5E, 0x5E, 0x5E,  0x0D, 0x0D, 0x0D,  0x0D, 0x0D, 0x0D,
        // Row 3.
        0xFF, 0xFF, 0xFF,  0xA6, 0xFC, 0xFF,  0xB3, 0xEC, 0xFF,  0xDA, 0xAB, 0xEB,
        0xFF, 0xA8, 0xF9,  0xFF, 0xAB, 0xB3,  0xFF, 0xD2, 0xB0,  0xFF, 0xEF, 0xA6,
        0xFF, 0xF7, 0x9C,  0xD7, 0xE8, 0x95,  0xA6, 0xED, 0xAF,  0xA2, 0xF2, 0xDA,
        0x99, 0xFF, 0xFC,  0xDD, 0xDD, 0xDD,  0x11, 0x11, 0x11,  0x11, 0x11, 0x11,
    ];

    /**
     * Screen width in pixels.
     */
    private const int SCREEN_WIDTH = 256;

    /**
     * Screen height in pixels.
     */
    private const int SCREEN_HEIGHT = 224;

    /**
     * Number of bytes in the RGBA framebuffer.
     */
    private const int FRAMEBUFFER_SIZE = 256 * 224 * 4;

    /**
     * Number of colors exposed by the NES palette RAM.
     */
    private const int PALETTE_ENTRY_COUNT = 32;

    /**
     * Number of RGBA bytes in the cached palette.
     */
    private const int PALETTE_RGBA_SIZE = 32 * 4;

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
     * The palette values used to build the current RGBA cache.
     *
     * @var list<int>
     */
    private array $cachedPalette = [];

    /**
     * Pre-computed RGBA colors for current palette (32 entries × 4 components).
     *
     * @var list<int>
     */
    private array $paletteRgba;

    /**
     * Creates a new renderer and initializes the framebuffer.
     */
    public function __construct()
    {
        $this->frameBuffer = array_fill(0, self::FRAMEBUFFER_SIZE, 0);
        $this->paletteRgba = array_fill(0, self::PALETTE_RGBA_SIZE, 0);
    }

    /**
     * Renders NES graphics data to an RGBA framebuffer.
     *
     * @return list<int>
     */
    public function render(RenderingData $data): array
    {
        $background = $data->background;
        $hasBackground = $background !== null && $background !== [];

        if (! $this->hasCompleteBackgroundCoverage($background)) {
            $this->clearFrameBuffer();
        }

        $this->buildPaletteRgba($data->palette);

        if ($hasBackground) {
            $this->renderBackground($background);
        } else {
            $this->background = [];
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
        if ($palette === $this->cachedPalette) {
            return;
        }

        $this->cachedPalette = $palette;

        for ($i = 0; $i < self::PALETTE_ENTRY_COUNT; $i++) {
            $colorIdx = ($palette[$i] ?? 0) * 3;
            $paletteOffset = $i << 2;

            $this->paletteRgba[$paletteOffset] = self::COLORS_FLAT[$colorIdx];
            $this->paletteRgba[$paletteOffset + 1] = self::COLORS_FLAT[$colorIdx + 1];
            $this->paletteRgba[$paletteOffset + 2] = self::COLORS_FLAT[$colorIdx + 2];
            $this->paletteRgba[$paletteOffset + 3] = 0xFF;
        }
    }

    /**
     * Clears the reusable framebuffer without allocating a new array.
     */
    private function clearFrameBuffer(): void
    {
        for ($i = 0; $i < self::FRAMEBUFFER_SIZE; $i++) {
            $this->frameBuffer[$i] = 0;
        }
    }

    /**
     * Checks whether background rendering will overwrite every visible pixel.
     *
     * @param list<Tile>|null $background
     */
    private function hasCompleteBackgroundCoverage(?array $background): bool
    {
        if ($background === null || $background === []) {
            return false;
        }

        $visibleTileRows = intdiv(self::SCREEN_HEIGHT, 8);
        $requiredRows = $visibleTileRows + (int) (($background[0]->scrollY % 8) !== 0);

        return count($background) >= 33 * $requiredRows;
    }

    /**
     * Renders all background tiles to the framebuffer.
     *
     * @param list<Tile> $background
     */
    private function renderBackground(array $background): void
    {
        $this->background = $background;
        $frameBuffer = &$this->frameBuffer;
        $paletteRgba = $this->paletteRgba;
        $tileColumn = 0;
        $tileTop = 0;

        foreach ($background as $tile) {
            $tileLeft = $tileColumn << 3;
            $offsetX = $tile->scrollX % 8;
            $offsetY = $tile->scrollY % 8;
            $paletteBase = $tile->paletteId << 4; // 4 colors × 4 RGBA components.
            $sourceStartX = max(0, $offsetX - $tileLeft);
            $sourceEndX = min(8, self::SCREEN_WIDTH + $offsetX - $tileLeft);
            $sourceStartY = max(0, $offsetY - $tileTop);
            $sourceEndY = min(8, self::SCREEN_HEIGHT + $offsetY - $tileTop);

            for ($i = $sourceStartY; $i < $sourceEndY; $i++) {
                $y = $tileTop + $i - $offsetY;
                $x = $tileLeft + $sourceStartX - $offsetX;
                $frameIndex = ($y << 10) + ($x << 2);

                $patternRow = $tile->pattern[$i];
                for ($j = $sourceStartX; $j < $sourceEndX; $j++) {
                    $colorOffset = $paletteBase + ($patternRow[$j] << 2);

                    $frameBuffer[$frameIndex] = $paletteRgba[$colorOffset];
                    $frameBuffer[$frameIndex + 1] = $paletteRgba[$colorOffset + 1];
                    $frameBuffer[$frameIndex + 2] = $paletteRgba[$colorOffset + 2];
                    $frameBuffer[$frameIndex + 3] = 0xFF;
                    $frameIndex += 4;
                }
            }

            $tileColumn++;

            if ($tileColumn === 33) {
                $tileColumn = 0;
                $tileTop += 8;
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
        $frameBuffer = &$this->frameBuffer;
        $paletteRgba = $this->paletteRgba;

        foreach ($sprites as $sprite) {
            $isVerticalReverse = ($sprite->attribute & 0x80) !== 0;
            $isHorizontalReverse = ($sprite->attribute & 0x40) !== 0;
            $isLowPriority = ($sprite->attribute & 0x20) !== 0;
            $paletteBase = (($sprite->attribute & 0x03) + 4) << 4; // Sprite palettes start at index 4.

            $baseX = (int) $sprite->coordinates->x;
            $baseY = (int) $sprite->coordinates->y;

            for ($i = 0; $i < 8; $i++) {
                $y = $baseY + ($isVerticalReverse ? 7 - $i : $i);

                if ($y < 0 || $y >= self::SCREEN_HEIGHT) {
                    continue;
                }

                $patternRow = $sprite->sprite[$i];

                for ($j = 0; $j < 8; $j++) {
                    $patternValue = $patternRow[$j];
                    if ($patternValue === 0) {
                        continue;
                    }

                    $x = $baseX + ($isHorizontalReverse ? 7 - $j : $j);

                    if ($x < 0 || $x >= self::SCREEN_WIDTH) {
                        continue;
                    }

                    if ($isLowPriority && $this->isBackgroundPixelOpaque($x, $y)) {
                        continue;
                    }

                    $frameIndex = ($y << 10) + ($x << 2);
                    $colorOffset = $paletteBase + ($patternValue << 2);

                    $frameBuffer[$frameIndex] = $paletteRgba[$colorOffset];
                    $frameBuffer[$frameIndex + 1] = $paletteRgba[$colorOffset + 1];
                    $frameBuffer[$frameIndex + 2] = $paletteRgba[$colorOffset + 2];
                    $frameBuffer[$frameIndex + 3] = 0xFF;
                }
            }
        }
    }

    /**
     * Checks if the background pixel at the given position is opaque.
     */
    private function isBackgroundPixelOpaque(int $x, int $y): bool
    {
        $backgroundIndex = ($y >> 3) * 33 + ($x >> 3);

        if (!isset($this->background[$backgroundIndex])) {
            return false;
        }

        return ($this->background[$backgroundIndex]->pattern[$y & 0x07][$x & 0x07] & 0x03) !== 0;
    }
}
