<?php

declare(strict_types=1);

namespace App\Benchmark;

class BenchmarkFrameResult
{
    /**
     * Creates a benchmark frame result.
     */
    public function __construct(
        /**
         * Number of CPU instructions executed while completing the frame.
         */
        public readonly int $iterations,
        /**
         * Number of CPU cycles consumed while completing the frame.
         */
        public readonly int $cpuCycles,
        /**
         * Time spent in CPU execution in nanoseconds.
         */
        public readonly int $cpuNanoseconds,
        /**
         * Time spent in PPU execution in nanoseconds.
         */
        public readonly int $ppuNanoseconds,
        /**
         * Time spent converting rendering data to a framebuffer in nanoseconds.
         */
        public readonly int $renderNanoseconds = 0,
        /**
         * Time spent checksumming the final framebuffer in nanoseconds.
         */
        public readonly int $checksumNanoseconds = 0,
        /**
         * Checksum of the final rendered framebuffer, when rendering is enabled.
         */
        public readonly ?string $framebufferChecksum = null,
    ) {}
}
