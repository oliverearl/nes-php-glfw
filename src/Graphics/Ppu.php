<?php

declare(strict_types=1);

namespace App\Graphics;

use App\Bus\PpuBus;
use App\Cpu\Interrupts;
use App\Graphics\Objects\RenderingData;

class Ppu
{
    /**
     * Creates a new PPU instance.
     */
    public function __construct(
        private PpuBus $bus,
        private Interrupts $interrupts,
        private bool $isHorizontalMirror,
    ) {
        // TODO: Implement PPU constructor.
    }

    /**
     * Executes a single PPU cycle.
     */
    public function run(int $cycle): false|RenderingData
    {
        // TODO: Implement PPU run logic.
        return false;
    }
}
