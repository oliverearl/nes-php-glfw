<?php

declare(strict_types=1);

namespace Tests\Unit\Benchmark;

use App\Benchmark\BenchmarkFormatter;
use App\Benchmark\BenchmarkResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BenchmarkFormatter::class)]
#[CoversClass(BenchmarkResult::class)]
final class BenchmarkFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_json_results(): void
    {
        $formatter = new BenchmarkFormatter();
        $json = $formatter->json($this->benchmarkResult());

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this::assertSame('/tmp/test.nes', $data['rom_path']);
        $this::assertSame(2, $data['measured_frames']);
        $this::assertSame(1, $data['rendered_frames']);
        $this::assertSame('abc123', $data['framebuffer_checksum']);
    }

    #[Test]
    public function it_formats_human_results(): void
    {
        $formatter = new BenchmarkFormatter();
        $output = $formatter->human($this->benchmarkResult());

        $this::assertStringContainsString('Headless NES benchmark', $output);
        $this::assertStringContainsString('Frames: 2 measured, 1 warmup', $output);
        $this::assertStringContainsString('Framebuffer checksum: abc123', $output);
    }

    #[Test]
    public function it_formats_usage(): void
    {
        $formatter = new BenchmarkFormatter();

        $this::assertStringContainsString('php bin/benchmark.php path/to/homebrew-or-test-rom.nes', $formatter->usage());
    }

    /**
     * Creates a deterministic benchmark result for formatter tests.
     */
    private function benchmarkResult(): BenchmarkResult
    {
        return new BenchmarkResult(
            romPath: '/tmp/test.nes',
            romSha256: str_repeat('a', 64),
            romBytes: 24592,
            warmupFrames: 1,
            measuredFrames: 2,
            renderedFrames: 1,
            totalNanoseconds: 1_000_000_000,
            cpuNanoseconds: 200_000_000,
            ppuNanoseconds: 300_000_000,
            renderNanoseconds: 100_000_000,
            iterations: 100,
            cpuCycles: 500,
            peakMemoryBytes: 123456,
            framebufferChecksum: 'abc123',
        );
    }
}
