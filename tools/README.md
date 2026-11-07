# Profiling and benchmarking

These optional developer tools are separate from normal emulator startup. Python is needed only for repeated captures
and GUI diagnostics; the single-run benchmark uses PHP and Composer.

Keep generated ROMs, captures, logs, and machine-specific notes in `var/storage/benchmarks/`, which is gitignored.
Results are local to the measured machine and are not distributed with the emulator.

Run the commands below from the repository root. Set `BENCHMARK_PHP` to the full path of your intended PHP executable
with PHP-GLFW enabled:

```sh
BENCHMARK_PHP=/absolute/path/to/php
```

## Single-run benchmark

The existing Composer command accepts a local ROM:

```sh
composer benchmark -- path/to/rom.nes --frames=300 --warmup=30
composer benchmark -- path/to/rom.nes --render --timing=throughput --json
composer benchmark:help
```

Ensure Composer uses the intended PHP runtime. You can also invoke it explicitly:

```sh
"$BENCHMARK_PHP" bin/benchmark.php path/to/rom.nes --render --timing=throughput --json
```

The benchmark defaults to 300 measured frames and 30 warm-up frames. `composer benchmark:help` lists all options.

Detailed timing is the default and reports CPU/PPU breakdowns. Throughput timing omits instruction-level timestamps; its
CPU/PPU fields are `null`. `--render` includes software framebuffer conversion. Checksum timing is reported separately.

## Repeated captures and comparison

With Python 3.10+ and the project's development dependencies installed, generate original, deterministic NROM fixtures:

```sh
"$BENCHMARK_PHP" tools/benchmark-fixtures.php var/storage/benchmarks/fixtures
```

The CPU fixture exercises arithmetic, branches, stack operations, and indexed RAM. The graphics fixture exercises
backgrounds, sprites, scrolling, NMI, DMA, and controller reads. Headless input has all buttons released.

Capture a local baseline before changing the code:

```sh
python3 tools/benchmark-suite.py capture \
  --php "$BENCHMARK_PHP" \
  --rom cpu=var/storage/benchmarks/fixtures/cpu.nes \
  --rom graphics=var/storage/benchmarks/fixtures/graphics.nes \
  --frames=120 --warmup=30 --repeats=5 \
  --label baseline --output var/storage/benchmarks/baseline.json
```

After a change, repeat the capture with
`--label candidate --output var/storage/benchmarks/candidate.json`, then compare:

```sh
python3 tools/benchmark-suite.py compare \
  var/storage/benchmarks/baseline.json \
  var/storage/benchmarks/candidate.json
```

Each repetition starts a fresh PHP process and uses the same warm-up and frame count. The supervisor defaults to
throughput timing, disables Xdebug through `XDEBUG_MODE=off`, and disables CLI OPcache and JIT for its children without
editing configuration files. Keep runtime, machine, power settings, workloads, and input identical. Avoid concurrent
tests or other demanding work during collection.

Captures retain raw samples, timing spread, memory, runtime/INI metadata, source hashes, and output checksums.
Comparison rejects incompatible environments, changed work counts, and changed rendered output. Correctness repairs can intentionally change
these identities: verify the change independently, record the expected difference, and capture a new baseline before comparing later optimisations.
The comparison flags a 5% increase in median frame time by default (`--threshold` changes this). Exit statuses are 0 for within budget, 1 for review, and 2
for invalid or incomparable captures. A warning needs investigation; overlapping timing ranges are reported, and small
differences may need longer, alternating baseline/candidate runs.

The primary summary excludes measured checksum time, although checksum allocation/cache effects can remain. Peak memory
includes loading and warm-up; startup is outside the timed interval. Detailed timing is also available with
`--timing=detailed`; compare matching timing modes and measurement harnesses.

## Interactive profiling

To print update, draw, CPU, PPU, rendering, and frame-count diagnostics once per second:

```sh
"$BENCHMARK_PHP" bin/start.php path/to/rom.nes --profile
```

For repeated diagnostic captures of normal and `--drop-frames` pacing:

```sh
python3 tools/benchmark-gui.py \
  --php "$BENCHMARK_PHP" \
  --rom var/storage/benchmarks/fixtures/graphics.nes \
  --warmup-seconds=3 --seconds=8 --repeats=3 \
  --output var/storage/benchmarks/gui-candidate
```

This opens the application, saves raw logs and `summary.json`, and stops only the processes it launches. Keep physical
input idle and window, display, and Vsync settings unchanged. A native display and usable OpenGL context are required.

These are instrumented diagnostics. Profiler intervals are approximate, and draw timings exclude later render-pipeline
work and buffer swap. They can reveal changes in emulated/rasterized frame counts, but do not measure uninstrumented
end-to-end frame times, GPU completion, or percentiles. Failed captures preserve logs and must not be treated as
baselines.

## Tool tests

```sh
python3 -m unittest discover -s tests/BenchmarkTools -v
```

The normal PHPUnit suite (`composer test`) covers the PHP benchmark and generated fixtures. The Python tests run separately and do not need
a display or PHP-GLFW. GUI captures are manual diagnostics and require the graphical runtime.
