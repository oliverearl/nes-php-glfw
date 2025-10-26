<?php

declare(strict_types=1);

namespace App\Cpu;

use App\Bus\CpuBus;

class Cpu
{
    /**
     * Creates a new CPU instance.
     */
    public function __construct(private CpuBus $bus, private Interrupts $interrupts) {}

    /**
     * Executes a single CPU cycle.
     */
    public function run(): int
    {
        // TODO: Implement CPU run logic.
        return 1;
    }

    /**
     * Resets the CPU to its initial state.
     */
    public function reset(): void
    {
        // TODO: Implement CPU reset logic.
    }
}
