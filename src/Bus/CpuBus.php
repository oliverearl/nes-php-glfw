<?php

declare(strict_types=1);

namespace App\Bus;

use App\Cpu\Dma;
use App\Graphics\Ppu;
use App\Input\Gamepad;

readonly class CpuBus
{
    /**
     * Creates a new CPU bus instance.
     */
    public function __construct(
        public Ram $ram,
        public Rom $programRom,
        public Ppu $ppu,
        public Gamepad $gamepad,
        public Dma $dma,
    ) {}

    /**
     * Reads data via the CPU bus at the specified address.
     */
    public function readByCpu(int $address): int
    {
        // TODO: Implement readByCpu method.
        return 0;
    }

    /**
     * Writes data via the CPU bus at the specified address.
     */
    public function writeByCpu(int $address, int $data): void
    {
        // TODO: Implement writeByCpu method.
    }
}
