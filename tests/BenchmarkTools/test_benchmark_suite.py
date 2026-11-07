"""Check saved benchmark comparisons without running PHP or timed workloads."""

import argparse
from contextlib import redirect_stdout
from copy import deepcopy
import importlib.util
import io
import json
from pathlib import Path
import tempfile
from types import SimpleNamespace
import unittest


SCRIPT = Path(__file__).resolve().parents[2] / "tools" / "benchmark-suite.py"
SPEC = importlib.util.spec_from_file_location("benchmark_suite", SCRIPT)
SUITE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(SUITE)


def sample(milliseconds=10.0, render=True):
    """Create the work/output fields emitted by a deterministic PHP run."""
    return {
        "rom_sha256": "a" * 64,
        "measured_frames": 300,
        "warmup_frames": 30,
        "rendered_frames": 300 if render else 0,
        "iterations": 2_000_000,
        "cpu_cycles": 8_950_000,
        "framebuffer_checksum": "b" * 64 if render else None,
        "framebuffer_sequence_checksum": "c" * 64 if render else None,
        "timing_mode": "throughput",
        "render_enabled": render,
        "avg_total_ms_per_frame_excluding_checksum": milliseconds,
        "peak_memory_bytes": 16_000_000,
    }


def report(values=(10.0, 10.0, 10.0), render=True):
    """Build independent captures with matching runtime and harness metadata."""
    key = "graphics/render" if render else "cpu/emulation"
    return {
        "protocol": SUITE.PROTOCOL,
        "metadata": {
            "runtime": {"version": "configured", "opcache.jit": "0"},
            "host": {"node": "benchmark-host", "machine": "test-architecture"},
            "suite_sha256": "d" * 64,
            "php_command": ["/configured/php", "-d", "opcache.jit=0"],
            "commit": "old-source",
            "source_sha256": {"src/Emulator.php": "old-source-hash", "composer.lock": "locked-dependencies"},
        },
        "cases": {key: {"samples": [sample(value, render) for value in values]}},
    }


class SummaryTest(unittest.TestCase):
    def test_reports_median_spread_and_memory_across_repetitions(self):
        samples = [sample(value) for value in (10, 30, 11, 9, 12)]
        for index, entry in enumerate(samples):
            entry["peak_memory_bytes"] = 1_000 * (index + 1)
        summary = SUITE.summarize(samples)
        self.assertEqual(11, summary["median_ms_per_frame"])
        self.assertEqual(9, summary["min_ms_per_frame"])
        self.assertEqual(30, summary["max_ms_per_frame"])
        self.assertEqual(1, summary["mad_ms_per_frame"])
        self.assertEqual(3_000, summary["median_peak_memory_bytes"])
        self.assertEqual(SUITE.invariants(samples[0]), summary["identity"])

    def test_rejects_any_work_or_output_change_between_repetitions(self):
        for field in SUITE.INVARIANTS:
            with self.subTest(field=field):
                samples = [sample(), sample()]
                value = samples[1][field]
                samples[1][field] = not value if isinstance(value, bool) else str(value) + "changed"
                with self.assertRaisesRegex(ValueError, "between repetitions"):
                    SUITE.summarize(samples)

    def test_accepts_null_video_checksums_when_rendering_is_disabled(self):
        summary = SUITE.summarize([sample(render=False), sample(render=False)])
        self.assertIsNone(summary["identity"]["framebuffer_checksum"])
        self.assertIsNone(summary["identity"]["framebuffer_sequence_checksum"])
        self.assertEqual(0, summary["identity"]["rendered_frames"])

    def test_requires_the_complete_work_and_output_schema(self):
        entry = sample()
        del entry["framebuffer_sequence_checksum"]
        with self.assertRaises((KeyError, ValueError)):
            SUITE.summarize([entry])

    def test_rejects_empty_repetition_sets(self):
        with self.assertRaises(ValueError):
            SUITE.summarize([])

    def test_rejects_nonpositive_or_nonfinite_timings(self):
        for value in (0, -1, float("nan"), float("inf"), float("-inf")):
            with self.subTest(value=value), self.assertRaises(ValueError):
                SUITE.summarize([sample(value)])

    def test_repetition_count_parser_requires_positive_counts(self):
        self.assertEqual(5, SUITE.positive("5"))
        for value in ("0", "-1"):
            with self.subTest(value=value), self.assertRaises(argparse.ArgumentTypeError):
                SUITE.positive(value)


class ComparisonTest(unittest.TestCase):
    def test_rejects_changed_work_even_when_performance_improves(self):
        baseline = report()
        for field in ("rom_sha256", "measured_frames", "warmup_frames", "iterations", "cpu_cycles"):
            candidate = report((5, 5, 5))
            for entry in candidate["cases"]["graphics/render"]["samples"]:
                entry[field] = str(entry[field]) + "changed"
            with self.subTest(field=field), self.assertRaisesRegex(ValueError, "work or output differs"):
                SUITE.compare_reports(baseline, candidate, 5)

    def test_rejects_changed_intermediate_output_with_identical_final_frame(self):
        baseline = report()
        candidate = deepcopy(baseline)
        for entry in candidate["cases"]["graphics/render"]["samples"]:
            entry["framebuffer_sequence_checksum"] = "e" * 64
        with self.assertRaisesRegex(ValueError, "work or output differs"):
            SUITE.compare_reports(baseline, candidate, 5)

    def test_rejects_runtime_host_command_or_harness_mismatches(self):
        baseline = report()
        for field in ("runtime", "host", "suite_sha256", "php_command"):
            candidate = deepcopy(baseline)
            candidate["metadata"][field] = "changed"
            with self.subTest(field=field), self.assertRaisesRegex(ValueError, field):
                SUITE.compare_reports(baseline, candidate, 5)

    def test_allows_source_changes_under_matching_measurement_conditions(self):
        baseline = report()
        candidate = deepcopy(baseline)
        candidate["metadata"]["commit"] = "new-source"
        candidate["metadata"]["source_sha256"]["src/Emulator.php"] = "new-source-hash"
        row = SUITE.compare_reports(baseline, candidate, 5)[0]
        self.assertEqual(0, row["change_percent"])
        self.assertFalse(row["review"])

    def test_rejects_changed_composer_dependencies(self):
        baseline = report()
        candidate = deepcopy(baseline)
        candidate["metadata"]["source_sha256"]["composer.lock"] = "changed-dependencies"
        with self.assertRaisesRegex(ValueError, "Composer dependencies differ"):
            SUITE.compare_reports(baseline, candidate, 5)

    def test_rejects_protocol_and_workload_set_changes(self):
        baseline = report()
        candidate = deepcopy(baseline)
        candidate["protocol"] = "another-protocol"
        with self.assertRaisesRegex(ValueError, "protocols differ"):
            SUITE.compare_reports(baseline, candidate, 5)
        with self.assertRaisesRegex(ValueError, "Workload sets differ"):
            SUITE.compare_reports(baseline, report(render=False), 5)

    def test_respects_the_selected_regression_threshold_and_memory_delta(self):
        baseline = report()
        candidate = report((10.8, 10.8, 10.8))
        for entry in candidate["cases"]["graphics/render"]["samples"]:
            entry["peak_memory_bytes"] += 4_096
        strict = SUITE.compare_reports(baseline, candidate, 5)[0]
        relaxed = SUITE.compare_reports(baseline, candidate, 10)[0]
        self.assertAlmostEqual(8, strict["change_percent"])
        self.assertEqual(4_096, strict["memory_change_bytes"])
        self.assertTrue(strict["review"])
        self.assertFalse(relaxed["review"])

    def test_marks_overlapping_noise_ranges_without_hiding_a_review(self):
        row = SUITE.compare_reports(report((9, 10, 11)), report((10, 11, 12)), 5)[0]
        self.assertTrue(row["observed_ranges_overlap"])
        self.assertTrue(row["review"])

    def test_reports_improvement_without_regression_review(self):
        row = SUITE.compare_reports(report(), report((8, 8, 8)), 5)[0]
        self.assertAlmostEqual(-20, row["change_percent"])
        self.assertFalse(row["review"])

    def test_recomputes_summaries_from_raw_samples(self):
        baseline = report()
        candidate = report()
        baseline["cases"]["graphics/render"]["summary"] = {"median_ms_per_frame": 1}
        candidate["cases"]["graphics/render"]["summary"] = {"median_ms_per_frame": 100}
        row = SUITE.compare_reports(baseline, candidate, 5)[0]
        self.assertEqual(0, row["change_percent"])

    def test_rejects_nonfinite_or_negative_thresholds(self):
        for value in (-1, float("nan"), float("inf")):
            with self.subTest(value=value), self.assertRaises(ValueError):
                SUITE.compare_reports(report(), report(), value)

    def test_prints_review_and_returns_failure_only_for_exceeded_budget(self):
        with tempfile.TemporaryDirectory() as directory:
            baseline = Path(directory) / "baseline.json"
            candidate = Path(directory) / "candidate.json"
            baseline.write_text(json.dumps(report((9, 10, 11))))
            candidate.write_text(json.dumps(report((10, 10.8, 11))))
            arguments = SimpleNamespace(baseline=baseline, candidate=candidate, threshold=5)
            output = io.StringIO()
            with redirect_stdout(output):
                status = SUITE.compare(arguments)
            self.assertEqual(1, status)
            self.assertIn("+8.00%", output.getvalue())
            self.assertIn("YES", output.getvalue())
            self.assertIn("timing ranges overlap", output.getvalue())
            arguments.threshold = 10
            with redirect_stdout(io.StringIO()):
                self.assertEqual(0, SUITE.compare(arguments))


if __name__ == "__main__":
    unittest.main()
