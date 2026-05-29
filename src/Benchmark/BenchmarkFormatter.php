<?php

declare(strict_types=1);

namespace App\Benchmark;

class BenchmarkFormatter
{
    /**
     * Formats command usage text.
     */
    public function usage(): string
    {
        return <<<'TEXT'
Usage:
  php bin/benchmark.php path/to/homebrew-or-test-rom.nes [options]

Options:
  --frames=N           Number of measured NES frames. Default: 300.
  --warmup=N           Number of warmup frames excluded from results. Default: 30.
  --max-iterations=N   Safety limit for CPU instructions per frame. Default: 30000.
  --render             Include software framebuffer rendering in the benchmark.
  --json               Emit machine-readable JSON.
  --help, -h           Show this help text.

TEXT;
    }

    /**
     * Formats a benchmark result as human-readable text.
     */
    public function human(BenchmarkResult $result): string
    {
        $data = $result->toArray();

        return sprintf(
            <<<'TEXT'
Headless NES benchmark
ROM: %s
SHA-256: %s
Frames: %d measured, %d warmup
FPS: %.2f
Total: %.4fs
CPU: %.4fs (%.3fms/frame)
PPU: %.4fs (%.3fms/frame)
Render: %.4fs (%.3fms/rendered frame, %d rendered frames)
Iterations/frame: %.0f
CPU cycles/frame: %.0f
Peak memory: %d bytes
Framebuffer checksum: %s

TEXT,
            $data['rom_path'],
            $data['rom_sha256'],
            $data['measured_frames'],
            $data['warmup_frames'],
            $data['nes_frames_per_second'],
            $data['total_seconds'],
            $data['cpu_seconds'],
            $data['avg_cpu_ms_per_frame'],
            $data['ppu_seconds'],
            $data['avg_ppu_ms_per_frame'],
            $data['render_seconds'],
            $data['avg_render_ms_per_frame'],
            $data['rendered_frames'],
            $data['avg_iterations_per_frame'],
            $data['avg_cpu_cycles_per_frame'],
            $data['peak_memory_bytes'],
            $data['framebuffer_checksum'] ?? 'n/a',
        );
    }

    /**
     * Formats a benchmark result as pretty-printed JSON.
     */
    public function json(BenchmarkResult $result): string
    {
        return json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    }
}
