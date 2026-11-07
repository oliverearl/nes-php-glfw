#!/usr/bin/env python3
"""Capture repeatable, instrumented Visu diagnostics using the existing profiler."""

from __future__ import annotations

import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import platform
import queue
import re
import statistics
import subprocess
import sys
import threading
import time


NUMBER = r"\d+(?:\.\d+)?"
COUNTS_PATTERN = re.compile(
    rf"\[NES Debug\] Updates: (\d+) \(avg ({NUMBER})ms\) \| "
    rf"Draws: (\d+) \(avg ({NUMBER})ms\) \| NES frames: (\d+) \| "
    rf"Rendered frames: (\d+) \| Iters/update: ({NUMBER})\s*$"
)
BREAKDOWN_PATTERN = re.compile(
    rf"\[NES Debug\] Breakdown: CPU ({NUMBER})ms \| PPU ({NUMBER})ms \| "
    rf"Render ({NUMBER})ms \(per rendered frame avg\)\s*$"
)


class BenchmarkError(RuntimeError):
    """Indicate an unusable capture or invalid profiler record."""


class ProfilerParser:
    """Join counts and breakdown lines while excluding warm-up intervals."""

    def __init__(self, warmup_seconds: float):
        self.warmup_seconds = warmup_seconds
        self.pending = None
        self.previous_complete_elapsed = None

    def feed(self, elapsed: float, line: str) -> dict | None:
        """Require a preceding complete interval boundary after warm-up."""
        if "[NES Debug] Updates:" in line:
            if self.pending is not None:
                self.previous_complete_elapsed = None
            self.pending = None
            match = COUNTS_PATTERN.search(line)
            if match is None:
                self.previous_complete_elapsed = None
                raise BenchmarkError("Malformed profiler counts record.")
            self.pending = (elapsed, match.groups())
            return None

        if "[NES Debug] Breakdown:" not in line:
            return None

        match = BREAKDOWN_PATTERN.search(line)
        if match is None:
            self.pending = None
            self.previous_complete_elapsed = None
            raise BenchmarkError("Malformed profiler breakdown record.")

        pending = self.pending
        self.pending = None
        if pending is None:
            self.previous_complete_elapsed = None
            return None

        previous_complete_elapsed = self.previous_complete_elapsed
        self.previous_complete_elapsed = elapsed
        if previous_complete_elapsed is None or previous_complete_elapsed < self.warmup_seconds:
            return None
        if pending[0] < self.warmup_seconds or elapsed < self.warmup_seconds:
            return None

        counts = pending[1]
        return {
            "received_after_seconds": elapsed,
            "previous_interval_received_after_seconds": previous_complete_elapsed,
            "updates": int(counts[0]),
            "update_ms_per_update": float(counts[1]),
            "draws": int(counts[2]),
            "draw_ms_per_draw": float(counts[3]),
            "nes_frames": int(counts[4]),
            "rendered_frames": int(counts[5]),
            "iterations_per_update": float(counts[6]),
            "cpu_ms_per_update": float(match[1]),
            "ppu_ms_per_update": float(match[2]),
            "render_ms_per_rendered_frame": float(match[3]),
        }


def summarize_samples(samples: list[dict]) -> dict:
    """Summarize profiler intervals without interpreting them as precise FPS."""
    if not samples:
        raise BenchmarkError("No complete profiler samples after warm-up.")

    summary = {
        "sample_count": len(samples),
        "total_nes_frames": sum(sample["nes_frames"] for sample in samples),
        "total_rendered_frames": sum(sample["rendered_frames"] for sample in samples),
        "median_nes_frames_per_interval": statistics.median(sample["nes_frames"] for sample in samples),
        "median_rendered_frames_per_interval": statistics.median(sample["rendered_frames"] for sample in samples),
    }
    for name in (
        "update_ms_per_update", "draw_ms_per_draw", "cpu_ms_per_update",
        "ppu_ms_per_update", "render_ms_per_rendered_frame", "iterations_per_update",
    ):
        summary[f"median_{name}"] = statistics.median(sample[name] for sample in samples)
    return summary


def stop_child(process: subprocess.Popen) -> None:
    """Terminate the owned process, allowing five seconds before killing it."""
    if process.poll() is None:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=5)


def capture_run(
    command: list[str], cwd: Path, output: Path, name: str,
    warmup_seconds: float, seconds: float,
) -> dict:
    """Capture timestamped stderr until the wall-clock measurement ends."""
    stderr_path = output / f"{name}.stderr.log"
    stdout_path = output / f"{name}.stdout.log"
    parser = ProfilerParser(warmup_seconds)
    samples = []
    events = queue.Queue()
    environment = dict(os.environ, XDEBUG_MODE="off")
    started = time.monotonic()
    deadline = started + warmup_seconds + seconds
    terminated_by_supervisor = False

    with stdout_path.open("x", encoding="utf-8") as stdout_log, stderr_path.open("x", encoding="utf-8") as stderr_log:
        process = subprocess.Popen(
            command, cwd=cwd, env=environment, stdin=subprocess.DEVNULL,
            stdout=stdout_log, stderr=subprocess.PIPE, text=True,
            encoding="utf-8", errors="replace", bufsize=1,
        )

        def read_stderr() -> None:
            """Timestamp records as they arrive without blocking supervision."""
            for line in process.stderr:
                events.put((time.monotonic() - started, line))

        reader = threading.Thread(target=read_stderr, daemon=True)
        reader.start()

        def consume_event(event: tuple) -> None:
            """Preserve every line and parse samples inside the time window."""
            elapsed, line = event
            stderr_log.write(line)
            stderr_log.flush()
            if elapsed <= warmup_seconds + seconds:
                sample = parser.feed(elapsed, line)
                if sample is not None:
                    samples.append(sample)

        try:
            while time.monotonic() < deadline:
                if process.poll() is not None:
                    raise BenchmarkError(f"GUI process exited early with code {process.returncode}; see {stderr_path}.")
                try:
                    event = events.get(timeout=min(0.1, max(0.001, deadline - time.monotonic())))
                except queue.Empty:
                    continue
                consume_event(event)

            if process.poll() is not None:
                raise BenchmarkError(f"GUI process exited early with code {process.returncode}; see {stderr_path}.")
            terminated_by_supervisor = True
        finally:
            stop_child(process)
            reader.join(timeout=1)
            while not events.empty():
                consume_event(events.get_nowait())
            process.stderr.close()

    return {
        "name": name,
        "command": command,
        "warmup_seconds": warmup_seconds,
        "measurement_seconds": seconds,
        "process_wall_seconds": time.monotonic() - started,
        "terminated_by_supervisor": terminated_by_supervisor,
        "returncode_after_termination": process.returncode,
        "stderr_log": str(stderr_path),
        "stdout_log": str(stdout_path),
        "samples": samples,
        "summary": summarize_samples(samples),
    }


def installed_window_defaults(root: Path) -> dict:
    """Read defaults from installed Visu rather than guessing display settings."""
    path = root / "vendor/phpgl/visu/src/Quickstart/QuickstartOptions.php"
    if not path.is_file():
        return {"source": str(path), "available": False}
    source = path.read_text()
    defaults = {"source": str(path), "available": True}
    for key in ("windowWidth", "windowHeight", "windowVsync", "windowHeadless", "gameLoopTickRate"):
        match = re.search(rf"public\s+\w+\s+\${key}\s*=\s*([^;]+);", source)
        if match is not None:
            value = match[1].strip()
            defaults[key] = {"true": True, "false": False}.get(value, value)
    return defaults


def source_identity(root: Path) -> dict:
    """Record the source revision and PHP tree digest beside the measurements."""
    revision = subprocess.run(
        ["git", "rev-parse", "HEAD"], cwd=root, capture_output=True, text=True, check=True,
    ).stdout.strip()
    status = subprocess.run(
        ["git", "status", "--short"], cwd=root, capture_output=True, text=True, check=True,
    ).stdout.splitlines()
    digest = hashlib.sha256()
    paths = sorted((root / "src").rglob("*.php")) + sorted((root / "bin").glob("*.php"))
    for path in paths:
        digest.update(path.relative_to(root).as_posix().encode() + b"\0" + path.read_bytes() + b"\0")
    return {"git_revision": revision, "git_status": status, "php_source_sha256": digest.hexdigest()}


def positive_float(value: str) -> float:
    """Reject invalid durations before starting native application processes."""
    number = float(value)
    if not 0 < number < float("inf"):
        raise argparse.ArgumentTypeError("Must be a finite number greater than zero.")
    return number


def main(argv: list[str] | None = None) -> int:
    """Run both existing presentation modes and save raw diagnostic evidence."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php", required=True, type=Path, help="Configured PHP executable.")
    parser.add_argument("--rom", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path, help="Directory for logs and summary.json.")
    parser.add_argument("--repeats", type=int, default=3)
    parser.add_argument("--warmup-seconds", type=positive_float, default=3)
    parser.add_argument("--seconds", type=positive_float, default=8)
    args = parser.parse_args(argv)
    if args.repeats < 1:
        parser.error("--repeats must be greater than zero.")
    if not args.php.is_file() or not args.rom.is_file():
        parser.error("--php and --rom must identify existing files.")

    root = Path(__file__).resolve().parent.parent
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)
    summary_path = output / "summary.json"
    if summary_path.exists():
        parser.error(f"Refusing to overwrite {summary_path}.")

    report = {
        "schema_version": 1,
        "kind": "instrumented_visu_diagnostic",
        "created_at_utc": datetime.now(timezone.utc).isoformat(),
        "platform": platform.platform(),
        "machine": platform.machine(),
        "source": source_identity(root),
        "rom_path": str(args.rom.resolve()),
        "rom_sha256": hashlib.sha256(args.rom.read_bytes()).hexdigest(),
        "php_path": str(args.php.resolve()),
        "environment_overrides": {"XDEBUG_MODE": "off"},
        "display_environment": {name: os.environ.get(name) for name in ("DISPLAY", "WAYLAND_DISPLAY")},
        "installed_visu_defaults": installed_window_defaults(root),
        "notes": [
            "Uses the existing --profile instrumentation, including timestamps around CPU instructions.",
            "Profiler intervals are approximately one second; their frame counts are not exact FPS.",
            "The first complete profiler pair after warm-up establishes a boundary and is discarded; later complete intervals are measured.",
            "Interval boundaries use stderr arrival timestamps, not exact timestamps from inside the profiler.",
            "Draw timing excludes subsequent render-pipeline execution and buffer swap.",
            "These diagnostics do not measure uninstrumented performance, GPU completion, or frame-time percentiles.",
            "No input is injected. Keep physical keyboard input idle and display/window configuration unchanged.",
            "Window geometry and Vsync are installed defaults; actual display scale and refresh are not measured.",
        ],
        "complete": False,
        "runs": [],
    }

    def save_report() -> None:
        """Keep completed runs available if a later capture fails."""
        temporary = summary_path.with_suffix(".json.tmp")
        temporary.write_text(json.dumps(report, indent=2) + "\n")
        temporary.replace(summary_path)

    save_report()
    try:
        for repeat in range(1, args.repeats + 1):
            modes = (False, True) if repeat % 2 else (True, False)
            for drop_frames in modes:
                mode = "drop_frames" if drop_frames else "normal"
                command = [
                    str(args.php.resolve()), "-d", "opcache.enable_cli=0", "-d", "opcache.jit=0",
                    "-d", "error_log=/dev/stderr", str(root / "bin/start.php"),
                    str(args.rom.resolve()), "--profile",
                ]
                if drop_frames:
                    command.append("--drop-frames")
                print(f"Capturing {mode}, repeat {repeat}/{args.repeats}...", flush=True)
                run = capture_run(command, root, output, f"{mode}-{repeat}", args.warmup_seconds, args.seconds)
                run["mode"] = mode
                run["repeat"] = repeat
                report["runs"].append(run)
                save_report()
        report["mode_summaries"] = {}
        for mode in ("normal", "drop_frames"):
            runs = [run for run in report["runs"] if run["mode"] == mode]
            report["mode_summaries"][mode] = {
                "repeat_count": len(runs),
                "median_of_run_medians": {
                    name: statistics.median(run["summary"][name] for run in runs)
                    for name in runs[0]["summary"] if name.startswith("median_")
                },
            }
        report["complete"] = True
        save_report()
    except (BenchmarkError, OSError, KeyboardInterrupt) as error:
        report["error"] = str(error) or "Interrupted."
        save_report()
        print(f"GUI benchmark failed: {report['error']}", file=sys.stderr)
        return 1
    print(f"Saved instrumented GUI diagnostics to {summary_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
