<?php

declare(strict_types=1);

namespace App\Debug;

trait Profiler
{
    /**
     * Whether debug logging is enabled.
     */
    private bool $debugEnabled = false;

    /**
     * Last time we logged performance stats.
     */
    private float $debugLastLogTime = 0.0;

    /**
     * Accumulated time spent in update() this second.
     */
    private float $debugUpdateTime = 0.0;

    /**
     * Accumulated time spent in draw() this second.
     */
    private float $debugDrawTime = 0.0;

    /**
     * Number of update() calls this second.
     */
    private int $debugUpdateCount = 0;

    /**
     * Number of draw() calls this second.
     */
    private int $debugDrawCount = 0;

    /**
     * Number of NES frames produced this second.
     */
    private int $debugNesFrames = 0;

    /**
     * Total iterations in update() this second.
     */
    private int $debugIterations = 0;

    /**
     * Time spent in renderer->render() this second.
     */
    private float $debugRenderTime = 0.0;

    /**
     * Time spent in CPU execution this second.
     */
    private float $debugCpuTime = 0.0;

    /**
     * Time spent in PPU execution this second.
     */
    private float $debugPpuTime = 0.0;

    /**
     * Enables or disables debug logging.
     */
    public function setDebugEnabled(bool $enabled): void
    {
        $this->debugEnabled = $enabled;

        if ($enabled && $this->debugLastLogTime === 0.0) {
            $this->debugLastLogTime = microtime(true);
        }
    }

    /**
     * Records the start time of an update cycle.
     */
    private function debugStartUpdate(): float
    {
        return $this->debugEnabled ? microtime(true) : 0.0;
    }

    /**
     * Records the end of an update cycle and accumulates timing data.
     */
    private function debugEndUpdate(float $startTime): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugUpdateTime += microtime(true) - $startTime;
        $this->debugUpdateCount++;
    }

    /**
     * Records the start time of a draw cycle.
     */
    private function debugStartDraw(): float
    {
        return $this->debugEnabled ? microtime(true) : 0.0;
    }

    /**
     * Records the end of a draw cycle and accumulates timing data.
     */
    private function debugEndDraw(float $startTime): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugDrawTime += microtime(true) - $startTime;
        $this->debugDrawCount++;
    }

    /**
     * Records the start time of CPU execution.
     */
    private function debugStartCpu(): float
    {
        return $this->debugEnabled ? microtime(true) : 0.0;
    }

    /**
     * Records the end of CPU execution and accumulates timing data.
     */
    private function debugEndCpu(float $startTime): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugCpuTime += microtime(true) - $startTime;
    }

    /**
     * Records the start time of PPU execution.
     */
    private function debugStartPpu(): float
    {
        return $this->debugEnabled ? microtime(true) : 0.0;
    }

    /**
     * Records the end of PPU execution and accumulates timing data.
     */
    private function debugEndPpu(float $startTime): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugPpuTime += microtime(true) - $startTime;
    }

    /**
     * Records the start time of rendering.
     */
    private function debugStartRender(): float
    {
        return $this->debugEnabled ? microtime(true) : 0.0;
    }

    /**
     * Records the end of rendering and accumulates timing data.
     */
    private function debugEndRender(float $startTime): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugRenderTime += microtime(true) - $startTime;
    }

    /**
     * Records that a NES frame was produced.
     */
    private function debugRecordNesFrame(): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugNesFrames++;
    }

    /**
     * Records the number of iterations in an update cycle.
     */
    private function debugRecordIterations(int $iterations): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $this->debugIterations += $iterations;
    }

    /**
     * Logs debug performance statistics once per second.
     */
    private function debugLog(): void
    {
        if (! $this->debugEnabled) {
            return;
        }

        $now = microtime(true);
        if ($now - $this->debugLastLogTime < 1.0) {
            return;
        }

        $avgUpdate = $this->debugUpdateCount > 0 ? ($this->debugUpdateTime / $this->debugUpdateCount) * 1000 : 0;
        $avgDraw = $this->debugDrawCount > 0 ? ($this->debugDrawTime / $this->debugDrawCount) * 1000 : 0;
        $avgCpu = $this->debugUpdateCount > 0 ? ($this->debugCpuTime / $this->debugUpdateCount) * 1000 : 0;
        $avgPpu = $this->debugUpdateCount > 0 ? ($this->debugPpuTime / $this->debugUpdateCount) * 1000 : 0;
        $avgRender = $this->debugNesFrames > 0 ? ($this->debugRenderTime / $this->debugNesFrames) * 1000 : 0;
        $avgIterations = $this->debugUpdateCount > 0 ? $this->debugIterations / $this->debugUpdateCount : 0;

        /** @noinspection ForgottenDebugOutputInspection */
        error_log(sprintf(
            '[NES Debug] Updates: %d (avg %.2fms) | Draws: %d (avg %.2fms) | NES frames: %d | Iters/update: %.0f',
            $this->debugUpdateCount,
            $avgUpdate,
            $this->debugDrawCount,
            $avgDraw,
            $this->debugNesFrames,
            $avgIterations,
        ));

        /** @noinspection ForgottenDebugOutputInspection */
        error_log(sprintf(
            '[NES Debug] Breakdown: CPU %.2fms | PPU %.2fms | Render %.2fms (per update avg)',
            $avgCpu,
            $avgPpu,
            $avgRender,
        ));

        $this->debugLastLogTime = $now;
        $this->debugUpdateTime = 0.0;
        $this->debugDrawTime = 0.0;
        $this->debugCpuTime = 0.0;
        $this->debugPpuTime = 0.0;
        $this->debugRenderTime = 0.0;
        $this->debugUpdateCount = 0;
        $this->debugDrawCount = 0;
        $this->debugNesFrames = 0;
        $this->debugIterations = 0;
    }
}
