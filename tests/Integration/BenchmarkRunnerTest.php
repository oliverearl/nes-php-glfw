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
            $this::assertNotNull($result->framebufferChecksum);
        } finally {
            unlink($romPath);
        }
    }
}
