<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Benchmark\BenchmarkOptions;
use App\Benchmark\BenchmarkRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\SyntheticNromRom;

#[CoversClass(BenchmarkRunner::class)]
final class BenchmarkRunnerTest extends TestCase
{
    #[Test]
    public function it_runs_a_headless_benchmark_with_a_generated_rom(): void
    {
        $romPath = SyntheticNromRom::writeToTemporaryFile();

        try {
            $result = (new BenchmarkRunner())->run(new BenchmarkOptions($romPath, frames: 2, warmupFrames: 1));

            $this::assertSame(2, $result->measuredFrames);
            $this::assertSame(1, $result->warmupFrames);
            $this::assertSame(0, $result->renderedFrames);
            $this::assertGreaterThan(0, $result->iterations);
            $this::assertGreaterThan(0, $result->cpuCycles);
            $this::assertGreaterThan(0, $result->framesPerSecond());
            $this::assertSame(0, $result->checksumNanoseconds);
            $this::assertNull($result->framebufferChecksum);
        } finally {
            unlink($romPath);
        }
    }

    #[Test]
    public function it_can_include_software_rendering_in_the_benchmark(): void
    {
        $romPath = SyntheticNromRom::writeToTemporaryFile();

        try {
            $result = (new BenchmarkRunner())->run(new BenchmarkOptions($romPath, frames: 1, warmupFrames: 0, render: true));

            $this::assertSame(1, $result->renderedFrames);
            $this::assertGreaterThanOrEqual(0, $result->renderNanoseconds);
            $this::assertGreaterThanOrEqual(0, $result->checksumNanoseconds);
            $this::assertGreaterThanOrEqual($result->framesPerSecond(), $result->framesPerSecondExcludingChecksum());
            $this::assertNotNull($result->framebufferChecksum);
        } finally {
            unlink($romPath);
        }
    }

    #[Test]
    public function it_preserves_execution_and_output_with_throughput_timing(): void
    {
        $romPath = SyntheticNromRom::writeToTemporaryFile();

        try {
            $runner = new BenchmarkRunner();
            $detailed = $runner->run(new BenchmarkOptions($romPath, frames: 3, warmupFrames: 1, render: true));
            $throughput = $runner->run(new BenchmarkOptions($romPath, frames: 3, warmupFrames: 1, render: true, timingMode: BenchmarkOptions::TIMING_THROUGHPUT));

            $this::assertSame($detailed->iterations, $throughput->iterations);
            $this::assertSame($detailed->cpuCycles, $throughput->cpuCycles);
            $this::assertSame($detailed->framebufferChecksum, $throughput->framebufferChecksum);
            $this::assertSame($detailed->framebufferSequenceChecksum, $throughput->framebufferSequenceChecksum);
            $checksum = $throughput->framebufferChecksum;
            $this::assertNotNull($checksum);
            $this::assertSame(hash('sha256', str_repeat($checksum, 3)), $throughput->framebufferSequenceChecksum);
            $this::assertSame(3, $throughput->renderedFrames);
            $this::assertGreaterThan(0, $throughput->totalNanoseconds);
            $this::assertSame(0, $throughput->cpuNanoseconds);
            $this::assertSame(0, $throughput->ppuNanoseconds);
            $this::assertSame('throughput', $throughput->toArray()['timing_mode']);
            $this::assertNull($throughput->toArray()['cpu_seconds']);
            $this::assertNull($throughput->toArray()['ppu_seconds']);
        } finally {
            unlink($romPath);
        }
    }

    #[Test]
    public function it_runs_throughput_timing_without_rendering(): void
    {
        $romPath = SyntheticNromRom::writeToTemporaryFile();

        try {
            $result = (new BenchmarkRunner())->run(new BenchmarkOptions($romPath, frames: 2, warmupFrames: 0, timingMode: BenchmarkOptions::TIMING_THROUGHPUT));

            $this::assertSame(2, $result->measuredFrames);
            $this::assertSame(0, $result->renderedFrames);
            $this::assertNull($result->framebufferSequenceChecksum);
            $this::assertSame(0, $result->checksumNanoseconds);
            $this::assertNull($result->toArray()['avg_cpu_ms_per_frame']);
            $this::assertNull($result->toArray()['avg_ppu_ms_per_frame']);
        } finally {
            unlink($romPath);
        }
    }
}
