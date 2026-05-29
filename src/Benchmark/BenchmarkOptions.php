<?php

declare(strict_types=1);

namespace App\Benchmark;

use InvalidArgumentException;

class BenchmarkOptions
{
    /**
     * Default number of measured NES frames.
     */
    private const int DEFAULT_FRAMES = 300;

    /**
     * Default number of warmup NES frames.
     */
    private const int DEFAULT_WARMUP_FRAMES = 30;

    /**
     * Default safety limit for CPU instructions per NES frame.
     */
    private const int DEFAULT_MAX_ITERATIONS_PER_FRAME = 30000;

    /**
     * Path to the ROM being benchmarked.
     */
    public readonly string $romPath;

    /**
     * Number of frames included in measured results.
     */
    public readonly int $frames;

    /**
     * Number of frames run before measurement starts.
     */
    public readonly int $warmupFrames;

    /**
     * Maximum CPU instructions to execute before considering a frame stuck.
     */
    public readonly int $maxIterationsPerFrame;

    /**
     * Whether frame data should be converted through the software renderer.
     */
    public readonly bool $render;

    /**
     * Whether output should be emitted as JSON.
     */
    public readonly bool $json;

    /**
     * Whether help text was requested.
     */
    public readonly bool $help;

    /**
     * Creates benchmark options.
     */
    public function __construct(
        string $romPath,
        int $frames = self::DEFAULT_FRAMES,
        int $warmupFrames = self::DEFAULT_WARMUP_FRAMES,
        int $maxIterationsPerFrame = self::DEFAULT_MAX_ITERATIONS_PER_FRAME,
        bool $render = false,
        bool $json = false,
        bool $help = false,
    ) {
        if (! $help && $romPath === '') {
            throw new InvalidArgumentException('A ROM path is required.');
        }

        if ($frames <= 0) {
            throw new InvalidArgumentException('Frames must be greater than zero.');
        }

        if ($warmupFrames < 0) {
            throw new InvalidArgumentException('Warmup frames must be zero or greater.');
        }

        if ($maxIterationsPerFrame <= 0) {
            throw new InvalidArgumentException('Max iterations per frame must be greater than zero.');
        }

        $this->romPath = $romPath;
        $this->frames = $frames;
        $this->warmupFrames = $warmupFrames;
        $this->maxIterationsPerFrame = $maxIterationsPerFrame;
        $this->render = $render;
        $this->json = $json;
        $this->help = $help;
    }

    /**
     * Parses benchmark options from command-line arguments.
     *
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        $romPath = '';
        $frames = self::DEFAULT_FRAMES;
        $warmupFrames = self::DEFAULT_WARMUP_FRAMES;
        $maxIterationsPerFrame = self::DEFAULT_MAX_ITERATIONS_PER_FRAME;
        $render = false;
        $json = false;
        $help = false;

        for ($i = 1, $iMax = count($argv); $i < $iMax; $i++) {
            $arg = $argv[$i];

            if ($arg === '--help' || $arg === '-h') {
                $help = true;
                continue;
            }

            if ($arg === '--render') {
                $render = true;
                continue;
            }

            if ($arg === '--json') {
                $json = true;
                continue;
            }

            if (str_starts_with($arg, '--frames=')) {
                $frames = self::parseIntegerOption('--frames', substr($arg, strlen('--frames=')));
                continue;
            }

            if ($arg === '--frames') {
                $frames = self::parseIntegerOption('--frames', self::nextValue($argv, ++$i));
                continue;
            }

            if (str_starts_with($arg, '--warmup=')) {
                $warmupFrames = self::parseIntegerOption('--warmup', substr($arg, strlen('--warmup=')));
                continue;
            }

            if ($arg === '--warmup') {
                $warmupFrames = self::parseIntegerOption('--warmup', self::nextValue($argv, ++$i));
                continue;
            }

            if (str_starts_with($arg, '--max-iterations=')) {
                $maxIterationsPerFrame = self::parseIntegerOption('--max-iterations', substr($arg, strlen('--max-iterations=')));
                continue;
            }

            if ($arg === '--max-iterations') {
                $maxIterationsPerFrame = self::parseIntegerOption('--max-iterations', self::nextValue($argv, ++$i));
                continue;
            }

            if (str_starts_with($arg, '--')) {
                throw new InvalidArgumentException("Unknown option: {$arg}.");
            }

            if ($romPath !== '') {
                throw new InvalidArgumentException('Only one ROM path may be provided.');
            }

            $romPath = $arg;
        }

        return new self($romPath, $frames, $warmupFrames, $maxIterationsPerFrame, $render, $json, $help);
    }

    /**
     * Parses a positive integer option value.
     */
    private static function parseIntegerOption(string $name, string $value): int
    {
        if ($value === '' || ! ctype_digit($value)) {
            throw new InvalidArgumentException("{$name} must be an integer.");
        }

        return (int) $value;
    }

    /**
     * Reads the next argument as an option value.
     *
     * @param list<string> $argv
     */
    private static function nextValue(array $argv, int $index): string
    {
        if (! isset($argv[$index]) || str_starts_with($argv[$index], '--')) {
            throw new InvalidArgumentException('Option value is missing.');
        }

        return $argv[$index];
    }
}
