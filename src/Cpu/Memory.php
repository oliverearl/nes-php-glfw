<?php

declare(strict_types=1);

namespace App\Cpu;

interface Memory
{
    /**
     * Reads a byte from the CPU's memory map.
     */
    public function readByCpu(int $address): int;

    /**
     * Writes a byte to the CPU's memory map.
     */
    public function writeByCpu(int $address, int $data): void;
}
