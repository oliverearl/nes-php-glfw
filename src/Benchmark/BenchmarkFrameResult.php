<?php

declare(strict_types=1);

namespace App\Benchmark;

class BenchmarkFrameResult
{
    /**
     * Number of CPU instructions executed while completing the frame.
     */
    public readonly int $iterations;

    /**
     * Number of CPU cycles consumed while completing the frame.
     */
    public readonly int $cpuCycles;

    /**
     * Time spent in CPU execution in nanoseconds.
     */
    public readonly int $cpuNanoseconds;

    /**
     * Time spent in PPU execution in nanoseconds.
     */
    public readonly int $ppuNanoseconds;

    /**
     * Time spent converting rendering data to a framebuffer in nanoseconds.
     */
    public readonly int $renderNanoseconds;

    /**
     * Time spent checksumming the final framebuffer in nanoseconds.
     */
    public readonly int $checksumNanoseconds;

    /**
     * Checksum of the final rendered framebuffer, when rendering is enabled.
     */
    public readonly ?string $framebufferChecksum;

    /**
     * Creates a benchmark frame result.
     */
    public function __construct(
        int $iterations,
        int $cpuCycles,
        int $cpuNanoseconds,
        int $ppuNanoseconds,
        int $renderNanoseconds = 0,
        int $checksumNanoseconds = 0,
        ?string $framebufferChecksum = null,
    ) {
        $this->iterations = $iterations;
        $this->cpuCycles = $cpuCycles;
        $this->cpuNanoseconds = $cpuNanoseconds;
        $this->ppuNanoseconds = $ppuNanoseconds;
        $this->renderNanoseconds = $renderNanoseconds;
        $this->checksumNanoseconds = $checksumNanoseconds;
        $this->framebufferChecksum = $framebufferChecksum;
    }
}
