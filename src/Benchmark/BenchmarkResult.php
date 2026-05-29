<?php

declare(strict_types=1);

namespace App\Benchmark;

class BenchmarkResult
{
    /**
     * Path to the benchmarked ROM.
     */
    public readonly string $romPath;

    /**
     * SHA-256 hash of the benchmarked ROM.
     */
    public readonly string $romSha256;

    /**
     * Size of the benchmarked ROM in bytes.
     */
    public readonly int $romBytes;

    /**
     * Number of warmup frames excluded from measurement.
     */
    public readonly int $warmupFrames;

    /**
     * Number of measured frames.
     */
    public readonly int $measuredFrames;

    /**
     * Number of frames converted through the renderer.
     */
    public readonly int $renderedFrames;

    /**
     * Total measured wall time in nanoseconds.
     */
    public readonly int $totalNanoseconds;

    /**
     * Time spent in CPU execution in nanoseconds.
     */
    public readonly int $cpuNanoseconds;

    /**
     * Time spent in PPU execution in nanoseconds.
     */
    public readonly int $ppuNanoseconds;

    /**
     * Time spent converting frame data to framebuffers in nanoseconds.
     */
    public readonly int $renderNanoseconds;

    /**
     * Time spent checksumming rendered framebuffers in nanoseconds.
     */
    public readonly int $checksumNanoseconds;

    /**
     * Total CPU instructions executed during measured frames.
     */
    public readonly int $iterations;

    /**
     * Total CPU cycles consumed during measured frames.
     */
    public readonly int $cpuCycles;

    /**
     * Peak memory usage in bytes.
     */
    public readonly int $peakMemoryBytes;

    /**
     * Checksum of the last rendered framebuffer, when rendering is enabled.
     */
    public readonly ?string $framebufferChecksum;

    /**
     * Creates a benchmark result.
     */
    public function __construct(
        string $romPath,
        string $romSha256,
        int $romBytes,
        int $warmupFrames,
        int $measuredFrames,
        int $renderedFrames,
        int $totalNanoseconds,
        int $cpuNanoseconds,
        int $ppuNanoseconds,
        int $renderNanoseconds,
        int $checksumNanoseconds,
        int $iterations,
        int $cpuCycles,
        int $peakMemoryBytes,
        ?string $framebufferChecksum,
    ) {
        $this->romPath = $romPath;
        $this->romSha256 = $romSha256;
        $this->romBytes = $romBytes;
        $this->warmupFrames = $warmupFrames;
        $this->measuredFrames = $measuredFrames;
        $this->renderedFrames = $renderedFrames;
        $this->totalNanoseconds = $totalNanoseconds;
        $this->cpuNanoseconds = $cpuNanoseconds;
        $this->ppuNanoseconds = $ppuNanoseconds;
        $this->renderNanoseconds = $renderNanoseconds;
        $this->checksumNanoseconds = $checksumNanoseconds;
        $this->iterations = $iterations;
        $this->cpuCycles = $cpuCycles;
        $this->peakMemoryBytes = $peakMemoryBytes;
        $this->framebufferChecksum = $framebufferChecksum;
    }

    /**
     * Converts the result to a serializable array.
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'rom_path' => $this->romPath,
            'rom_sha256' => $this->romSha256,
            'rom_bytes' => $this->romBytes,
            'warmup_frames' => $this->warmupFrames,
            'measured_frames' => $this->measuredFrames,
            'rendered_frames' => $this->renderedFrames,
            'render_enabled' => $this->renderedFrames > 0,
            'total_seconds' => $this->seconds($this->totalNanoseconds),
            'total_seconds_excluding_checksum' => $this->seconds($this->totalNanosecondsExcludingChecksum()),
            'nes_frames_per_second' => $this->framesPerSecond(),
            'nes_frames_per_second_excluding_checksum' => $this->framesPerSecondExcludingChecksum(),
            'cpu_seconds' => $this->seconds($this->cpuNanoseconds),
            'ppu_seconds' => $this->seconds($this->ppuNanoseconds),
            'render_seconds' => $this->seconds($this->renderNanoseconds),
            'checksum_seconds' => $this->seconds($this->checksumNanoseconds),
            'avg_cpu_ms_per_frame' => $this->millisecondsPerFrame($this->cpuNanoseconds),
            'avg_ppu_ms_per_frame' => $this->millisecondsPerFrame($this->ppuNanoseconds),
            'avg_render_ms_per_frame' => $this->renderedFrames === 0 ? 0.0 : $this->seconds($this->renderNanoseconds) * 1000 / $this->renderedFrames,
            'avg_checksum_ms_per_rendered_frame' => $this->renderedFrames === 0 ? 0.0 : $this->seconds($this->checksumNanoseconds) * 1000 / $this->renderedFrames,
            'iterations' => $this->iterations,
            'avg_iterations_per_frame' => $this->iterations / $this->measuredFrames,
            'cpu_cycles' => $this->cpuCycles,
            'avg_cpu_cycles_per_frame' => $this->cpuCycles / $this->measuredFrames,
            'peak_memory_bytes' => $this->peakMemoryBytes,
            'framebuffer_checksum' => $this->framebufferChecksum,
        ];
    }

    /**
     * Calculates measured NES frames per second.
     */
    public function framesPerSecond(): float
    {
        if ($this->totalNanoseconds === 0) {
            return 0.0;
        }

        return $this->measuredFrames / $this->seconds($this->totalNanoseconds);
    }

    /**
     * Calculates measured NES frames per second without checksum overhead.
     */
    public function framesPerSecondExcludingChecksum(): float
    {
        $nanoseconds = $this->totalNanosecondsExcludingChecksum();

        if ($nanoseconds === 0) {
            return 0.0;
        }

        return $this->measuredFrames / $this->seconds($nanoseconds);
    }

    /**
     * Returns total measured time without framebuffer checksum overhead.
     */
    private function totalNanosecondsExcludingChecksum(): int
    {
        return max(0, $this->totalNanoseconds - $this->checksumNanoseconds);
    }

    /**
     * Converts nanoseconds to seconds.
     */
    private function seconds(int $nanoseconds): float
    {
        return $nanoseconds / 1_000_000_000;
    }

    /**
     * Converts total nanoseconds to average milliseconds per measured frame.
     */
    private function millisecondsPerFrame(int $nanoseconds): float
    {
        return $this->seconds($nanoseconds) * 1000 / $this->measuredFrames;
    }
}
