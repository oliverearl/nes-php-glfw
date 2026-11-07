#!/usr/bin/env python3
"""Capture repeatable headless samples and compare saved performance baselines."""

import argparse
import datetime
import hashlib
import json
import math
import os
import platform
from pathlib import Path
import statistics
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[1]
PROTOCOL = "nes-frame-throughput-v1"
INVARIANTS = (
    "rom_sha256", "measured_frames", "warmup_frames", "rendered_frames",
    "iterations", "cpu_cycles", "framebuffer_checksum",
    "framebuffer_sequence_checksum", "timing_mode", "render_enabled",
)


def run(command, cwd=ROOT, env=None):
    """Run an argument vector without shell interpretation and require success."""
    result = subprocess.run(command, cwd=cwd, env=env, text=True,
                            capture_output=True, check=False)
    if result.returncode:
        raise ValueError(f"Command failed ({result.returncode}): {command!r}\n{result.stderr}")
    return result.stdout


def digest(path):
    """Fingerprint a file without recording its contents."""
    return hashlib.sha256(path.read_bytes()).hexdigest()


def metadata(php, env):
    """Record runtime, host, source, and harness identity outside timed work."""
    probe = '''$extensions = get_loaded_extensions(); sort($extensions);
echo json_encode([
    'binary' => PHP_BINARY, 'version' => PHP_VERSION,
    'extensions' => $extensions,
    'extension_versions' => array_combine($extensions, array_map(phpversion(...), $extensions)),
    'ini' => php_ini_loaded_file(),
    'scanned_ini' => php_ini_scanned_files(),
    'opcache.enable_cli' => ini_get('opcache.enable_cli'),
    'opcache.jit' => ini_get('opcache.jit'),
    'xdebug_mode_env' => getenv('XDEBUG_MODE'),
], JSON_THROW_ON_ERROR);'''
    runtime = json.loads(run(php + ["-r", probe], env=env))
    ini_paths = ([runtime["ini"]] if runtime["ini"] else [])
    ini_paths += [p.strip() for p in (runtime["scanned_ini"] or "").split(",") if p.strip()]
    runtime["ini_sha256"] = {p: digest(Path(p)) for p in ini_paths}
    paths = list((ROOT / "src").rglob("*.php"))
    paths += list((ROOT / "bin").glob("*.php"))
    paths += list((ROOT / "tests/Support").glob("*.php"))
    paths += [ROOT / "composer.lock"]
    return {
        "runtime": runtime,
        "host": {"node": platform.node(), "system": platform.system(),
                 "release": platform.release(), "machine": platform.machine(),
                 "logical_cpus": os.cpu_count()},
        "commit": run(["git", "rev-parse", "HEAD"]).strip(),
        "working_tree": run(["git", "status", "--short"]),
        "source_sha256": {str(p.relative_to(ROOT)): digest(p) for p in sorted(paths)},
        "suite_sha256": digest(Path(__file__)),
        "php_command": php,
    }


def invariants(sample):
    """Extract work and output identity, requiring the current result schema."""
    return {key: sample[key] for key in INVARIANTS}


def summarize(samples):
    """Summarize independent process runs without assuming normal timing noise."""
    if not samples:
        raise ValueError("A capture must contain at least one sample.")
    identity = invariants(samples[0])
    if any(invariants(sample) != identity for sample in samples[1:]):
        raise ValueError("Work counts or framebuffer output changed between repetitions.")
    values = [sample["avg_total_ms_per_frame_excluding_checksum"] for sample in samples]
    if any(not isinstance(value, (int, float)) or not math.isfinite(value) or value <= 0
           for value in values):
        raise ValueError("Frame timings must be finite positive numbers.")
    median = statistics.median(values)
    return {
        "median_ms_per_frame": median,
        "min_ms_per_frame": min(values), "max_ms_per_frame": max(values),
        "mad_ms_per_frame": statistics.median(abs(value - median) for value in values),
        "median_peak_memory_bytes": statistics.median(s["peak_memory_bytes"] for s in samples),
        "identity": identity,
    }


def capture(args):
    """Run each workload with and without rasterization in fresh PHP processes."""
    if args.output.exists():
        raise ValueError(f"Refusing to overwrite existing capture: {args.output}")
    env = dict(os.environ, XDEBUG_MODE="off")
    php = [str(args.php.resolve()), "-d", "opcache.enable_cli=0", "-d", "opcache.jit=0"]
    cases = {}
    for spec in args.rom:
        name, separator, path = spec.partition("=")
        if not separator or not name or not Path(path).is_file():
            raise ValueError(f"Expected --rom name=/path/to/file.nes; received {spec!r}")
        for render in (False, True):
            key = name + ("/render" if render else "/emulation")
            if key in cases:
                raise ValueError(f"Duplicate workload name: {name}")
            cases[key] = {"rom": str(Path(path).resolve()), "render": render, "samples": []}
    report = {"protocol": PROTOCOL, "label": args.label,
              "recorded_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
              "metadata": metadata(php, env), "cases": cases}
    for repetition in range(args.repeats):
        # Reverse case order on alternate rounds to reduce order and thermal bias.
        order = list(cases.items())
        if repetition % 2:
            order.reverse()
        for key, case in order:
            command = php + [str(ROOT / "bin/benchmark.php"), case["rom"],
                             f"--frames={args.frames}", f"--warmup={args.warmup}",
                             f"--timing={args.timing}", "--json"]
            if case["render"]:
                command.append("--render")
            sample = json.loads(run(command, env=env))
            case["samples"].append(sample)
            print(f"{args.label}: {key} {repetition + 1}/{args.repeats}: "
                  f"{sample['avg_total_ms_per_frame_excluding_checksum']:.3f} ms/frame",
                  flush=True)
    for case in cases.values():
        case["summary"] = summarize(case["samples"])
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("x") as output:
        json.dump(report, output, indent=2)
        output.write("\n")
    print(f"Saved {args.output}")
    return 0


def compare_reports(baseline, candidate, threshold):
    """Reject incomparable work before reporting median time and memory changes."""
    if not math.isfinite(threshold) or threshold < 0:
        raise ValueError("The review threshold must be finite and nonnegative.")
    if baseline["protocol"] != candidate["protocol"]:
        raise ValueError("Measurement protocols differ; recapture both revisions.")
    for field in ("runtime", "host", "suite_sha256", "php_command"):
        if baseline["metadata"][field] != candidate["metadata"][field]:
            raise ValueError(f"Incompatible {field}; use matching measurement environments.")
    if (baseline["metadata"]["source_sha256"]["composer.lock"]
            != candidate["metadata"]["source_sha256"]["composer.lock"]):
        raise ValueError("Composer dependencies differ; establish a matching dependency baseline.")
    if baseline["cases"].keys() != candidate["cases"].keys():
        raise ValueError("Workload sets differ.")
    rows = []
    for key, old_case in baseline["cases"].items():
        old = summarize(old_case["samples"])
        new = summarize(candidate["cases"][key]["samples"])
        if old["identity"] != new["identity"]:
            raise ValueError(f"{key}: emulated work or output differs; check correctness first.")
        change = (new["median_ms_per_frame"] / old["median_ms_per_frame"] - 1) * 100
        overlap = (new["min_ms_per_frame"] <= old["max_ms_per_frame"]
                   and old["min_ms_per_frame"] <= new["max_ms_per_frame"])
        rows.append({"case": key, "baseline_ms": old["median_ms_per_frame"],
                     "candidate_ms": new["median_ms_per_frame"], "change_percent": change,
                     "memory_change_bytes": new["median_peak_memory_bytes"] - old["median_peak_memory_bytes"],
                     "observed_ranges_overlap": overlap, "review": change >= threshold})
    return rows


def compare(args):
    """Print a review table; exceeding the selected budget returns exit status one."""
    rows = compare_reports(json.loads(args.baseline.read_text()),
                           json.loads(args.candidate.read_text()), args.threshold)
    print("Case | Baseline ms/frame | Candidate ms/frame | Change | Memory delta | Review")
    print("--- | ---: | ---: | ---: | ---: | ---")
    for row in rows:
        note = "YES" if row["review"] else ""
        if row["observed_ranges_overlap"]:
            note += " (timing ranges overlap)"
        print(f"{row['case']} | {row['baseline_ms']:.3f} | {row['candidate_ms']:.3f} | "
              f"{row['change_percent']:+.2f}% | {row['memory_change_bytes']:+.0f} B | {note}")
    return int(any(row["review"] for row in rows))


def positive(value):
    """Parse a positive integer for measured work and repetition counts."""
    number = int(value)
    if number < 1:
        raise argparse.ArgumentTypeError("must be positive")
    return number


def nonnegative(value):
    """Parse a nonnegative number of warm-up frames."""
    number = int(value)
    if number < 0:
        raise argparse.ArgumentTypeError("must be nonnegative")
    return number


def main():
    """Dispatch capture or comparison without selecting an implicit PHP runtime."""
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    record = commands.add_parser("capture", help="save independent repeated PHP runs")
    record.add_argument("--php", type=Path, required=True)
    record.add_argument("--rom", action="append", required=True, metavar="NAME=PATH")
    record.add_argument("--output", type=Path, required=True)
    record.add_argument("--label", required=True)
    record.add_argument("--frames", type=positive, default=300)
    record.add_argument("--warmup", type=nonnegative, default=30)
    record.add_argument("--repeats", type=positive, default=5)
    record.add_argument("--timing", choices=("throughput", "detailed"), default="throughput")
    record.set_defaults(action=capture)
    comparison = commands.add_parser("compare", help="compare matching saved captures")
    comparison.add_argument("baseline", type=Path)
    comparison.add_argument("candidate", type=Path)
    comparison.add_argument("--threshold", type=float, default=5.0,
                            help="percentage increase in median time requiring review (default: 5)")
    comparison.set_defaults(action=compare)
    args = parser.parse_args()
    try:
        return args.action(args)
    except (ValueError, OSError, KeyError) as error:
        print(f"Benchmark error: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
