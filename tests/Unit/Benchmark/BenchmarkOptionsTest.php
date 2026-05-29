<?php

declare(strict_types=1);

namespace Tests\Unit\Benchmark;

use App\Benchmark\BenchmarkOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BenchmarkOptions::class)]
final class BenchmarkOptionsTest extends TestCase
{
    #[Test]
    public function it_parses_default_options(): void
    {
        $options = BenchmarkOptions::fromArgv(['bin/benchmark.php', 'test.nes']);

        $this::assertSame('test.nes', $options->romPath);
        $this::assertSame(300, $options->frames);
        $this::assertSame(30, $options->warmupFrames);
        $this::assertSame(30000, $options->maxIterationsPerFrame);
        $this::assertFalse($options->render);
        $this::assertFalse($options->json);
        $this::assertFalse($options->help);
    }

    #[Test]
    public function it_parses_explicit_options(): void
    {
        $options = BenchmarkOptions::fromArgv([
            'bin/benchmark.php',
            'test.nes',
            '--frames=10',
            '--warmup',
            '2',
            '--max-iterations=1234',
            '--render',
            '--json',
        ]);

        $this::assertSame(10, $options->frames);
        $this::assertSame(2, $options->warmupFrames);
        $this::assertSame(1234, $options->maxIterationsPerFrame);
        $this::assertTrue($options->render);
        $this::assertTrue($options->json);
    }

    #[Test]
    public function it_allows_help_without_a_rom_path(): void
    {
        $options = BenchmarkOptions::fromArgv(['bin/benchmark.php', '--help']);

        $this::assertTrue($options->help);
        $this::assertSame('', $options->romPath);
    }

    #[Test]
    public function it_rejects_missing_rom_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A ROM path is required.');

        BenchmarkOptions::fromArgv(['bin/benchmark.php']);
    }

    #[Test]
    public function it_rejects_unknown_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option: --bogus.');

        BenchmarkOptions::fromArgv(['bin/benchmark.php', 'test.nes', '--bogus']);
    }

    #[Test]
    public function it_rejects_non_integer_frame_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--frames must be an integer.');

        BenchmarkOptions::fromArgv(['bin/benchmark.php', 'test.nes', '--frames=abc']);
    }
}
