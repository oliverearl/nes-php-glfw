<?php

declare(strict_types=1);

namespace App\Cpu;

readonly class CpuState
{
    /**
     * Captures register values without exposing mutable CPU internals.
     */
    public function __construct(
        /**
         * Accumulator register.
         */
        public int $a,
        /**
         * X index register.
         */
        public int $x,
        /**
         * Y index register.
         */
        public int $y,
        /**
         * Packed status flags, with the unused bit set and the synthetic B bit clear.
         */
        public int $p,
        /**
         * Eight-bit stack pointer within page one.
         */
        public int $sp,
        /**
         * Sixteen-bit program counter.
         */
        public int $pc,
    ) {}
}
