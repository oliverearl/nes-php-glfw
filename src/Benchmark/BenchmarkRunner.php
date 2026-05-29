<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\Cartridge\Loader;
use App\Emulation\NesSystem;
use App\Emulation\NesSystemFactory;
use App\Graphics\Objects\RenderingData;
use App\Graphics\Renderer;
use RuntimeException;

class BenchmarkRunner
{
    /**
     * Factory used to create headless NES systems.
     */
    private readonly NesSystemFactory $systems;

    /**
     * Creates a benchmark runner.
     */
    public function __construct(?NesSystemFactory $systems = null)
    {
        $this->systems = $systems ?? new NesSystemFactory();
    }

    /**
     * Runs a headless benchmark for the configured ROM.
     *
     * @throws RuntimeException
     */
    public function run(BenchmarkOptions $options): BenchmarkResult
    {
        $romHash = hash_file('sha256', $options->romPath);
        $romBytes = filesize($options->romPath);

        if ($romHash === false || $romBytes === false) {
            throw new RuntimeException("Unable to inspect ROM file: {$options->romPath}.");
        }

        $cartridge = (new Loader($options->romPath, debugEnabled: false))->load();
        $system = $this->systems->create($cartridge);
        $renderer = $options->render ? new Renderer() : null;

        for ($i = 0; $i < $options->warmupFrames; $i++) {
            $this->runFrame($system, $renderer, $options->maxIterationsPerFrame);
        }

        $iterations = 0;
        $cpuCycles = 0;
        $cpuNanoseconds = 0;
        $ppuNanoseconds = 0;
        $renderNanoseconds = 0;
        $renderedFrames = 0;
        $framebufferChecksum = null;
        $start = hrtime(true);

        for ($i = 0; $i < $options->frames; $i++) {
            $frame = $this->runFrame($system, $renderer, $options->maxIterationsPerFrame);
            $iterations += $frame->iterations;
            $cpuCycles += $frame->cpuCycles;
            $cpuNanoseconds += $frame->cpuNanoseconds;
            $ppuNanoseconds += $frame->ppuNanoseconds;
            $renderNanoseconds += $frame->renderNanoseconds;

            if ($frame->framebufferChecksum !== null) {
                $renderedFrames++;
                $framebufferChecksum = $frame->framebufferChecksum;
            }
        }

        $totalNanoseconds = hrtime(true) - $start;

        return new BenchmarkResult(
            romPath: realpath($options->romPath) ?: $options->romPath,
            romSha256: $romHash,
            romBytes: $romBytes,
            warmupFrames: $options->warmupFrames,
            measuredFrames: $options->frames,
            renderedFrames: $renderedFrames,
            totalNanoseconds: $totalNanoseconds,
            cpuNanoseconds: $cpuNanoseconds,
            ppuNanoseconds: $ppuNanoseconds,
            renderNanoseconds: $renderNanoseconds,
            iterations: $iterations,
            cpuCycles: $cpuCycles,
            peakMemoryBytes: memory_get_peak_usage(true),
            framebufferChecksum: $framebufferChecksum,
        );
    }

    /**
     * Runs the system until one complete NES frame is produced.
     *
     * @throws RuntimeException
     */
    private function runFrame(NesSystem $system, ?Renderer $renderer, int $maxIterations): BenchmarkFrameResult
    {
        $iterations = 0;
        $cpuCycles = 0;
        $cpuNanoseconds = 0;
        $ppuNanoseconds = 0;

        while ($iterations < $maxIterations) {
            $cycle = 0;

            if ($system->dma->isDmaProcessing()) {
                $system->dma->runDma();
                $cycle = 514;
            }

            $cpuStart = hrtime(true);
            $cycle += $system->cpu->run();
            $cpuNanoseconds += hrtime(true) - $cpuStart;
            $cpuCycles += $cycle;

            $ppuStart = hrtime(true);
            $renderingData = $system->ppu->run($cycle * 3);
            $ppuNanoseconds += hrtime(true) - $ppuStart;

            $iterations++;

            if ($renderingData !== false) {
                return $this->completeFrame($renderer, $renderingData, $iterations, $cpuCycles, $cpuNanoseconds, $ppuNanoseconds);
            }
        }

        throw new RuntimeException("Frame did not complete within {$maxIterations} CPU iterations.");
    }

    /**
     * Completes a frame result, optionally rendering framebuffer data.
     */
    private function completeFrame(
        ?Renderer $renderer,
        RenderingData $renderingData,
        int $iterations,
        int $cpuCycles,
        int $cpuNanoseconds,
        int $ppuNanoseconds,
    ): BenchmarkFrameResult {
        if ($renderer === null) {
            return new BenchmarkFrameResult($iterations, $cpuCycles, $cpuNanoseconds, $ppuNanoseconds);
        }

        $renderStart = hrtime(true);
        $framebuffer = $renderer->render($renderingData);
        $renderNanoseconds = hrtime(true) - $renderStart;

        return new BenchmarkFrameResult(
            iterations: $iterations,
            cpuCycles: $cpuCycles,
            cpuNanoseconds: $cpuNanoseconds,
            ppuNanoseconds: $ppuNanoseconds,
            renderNanoseconds: $renderNanoseconds,
            framebufferChecksum: $this->checksum($framebuffer),
        );
    }

    /**
     * Calculates a stable checksum for a framebuffer.
     *
     * @param list<int> $framebuffer
     */
    private function checksum(array $framebuffer): string
    {
        $bytes = '';

        foreach ($framebuffer as $byte) {
            $bytes .= chr($byte);
        }

        return hash('sha256', $bytes);
    }
}
