"""Exercise profiler parsing and GUI process supervision without opening a window."""

import importlib.util
from pathlib import Path
import sys
import tempfile
import unittest


SCRIPT = Path(__file__).resolve().parents[2] / "tools" / "benchmark-gui.py"
SPEC = importlib.util.spec_from_file_location("benchmark_gui", SCRIPT)
GUI = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GUI)

COUNTS = (
    "[NES Debug] Updates: 30 (avg 12.50ms) | Draws: 20 (avg 4.25ms) | "
    "NES frames: 29 | Rendered frames: 19 | Iters/update: 9000"
)
BREAKDOWN = (
    "[NES Debug] Breakdown: CPU 7.25ms | PPU 3.50ms | "
    "Render 2.75ms (per rendered frame avg)"
)


class ProfilerParserTest(unittest.TestCase):
    def test_pairs_complete_records_and_maps_their_units(self):
        parser = GUI.ProfilerParser(warmup_seconds=3)
        self.assertIsNone(parser.feed(3.1, "[date] " + COUNTS))
        self.assertIsNone(parser.feed(3.2, "[date] " + BREAKDOWN))
        parser.feed(4.1, "[date] " + COUNTS)
        sample = parser.feed(4.2, "[date] " + BREAKDOWN)
        self.assertEqual(29, sample["nes_frames"])
        self.assertEqual(19, sample["rendered_frames"])
        self.assertEqual(12.5, sample["update_ms_per_update"])
        self.assertEqual(4.25, sample["draw_ms_per_draw"])
        self.assertEqual(7.25, sample["cpu_ms_per_update"])
        self.assertEqual(3.5, sample["ppu_ms_per_update"])
        self.assertEqual(2.75, sample["render_ms_per_rendered_frame"])
        self.assertEqual(4.2, sample["received_after_seconds"])
        self.assertEqual(3.2, sample["previous_interval_received_after_seconds"])

    def test_excludes_a_pair_straddling_warmup(self):
        parser = GUI.ProfilerParser(warmup_seconds=3)
        parser.feed(2.9, COUNTS)
        self.assertIsNone(parser.feed(3.1, BREAKDOWN))
        parser.feed(4.0, COUNTS)
        self.assertIsNotNone(parser.feed(4.1, BREAKDOWN))

    def test_excludes_an_interval_ending_just_after_warmup(self):
        parser = GUI.ProfilerParser(warmup_seconds=3)
        parser.feed(2, COUNTS)
        parser.feed(2.1, BREAKDOWN)
        parser.feed(3.01, COUNTS)
        self.assertIsNone(parser.feed(3.02, BREAKDOWN))
        parser.feed(4.01, COUNTS)
        self.assertIsNotNone(parser.feed(4.02, BREAKDOWN))

    def test_a_breakdown_without_counts_cannot_form_a_sample(self):
        parser = GUI.ProfilerParser(warmup_seconds=0)
        self.assertIsNone(parser.feed(1, BREAKDOWN))

    def test_a_missing_breakdown_does_not_mix_two_intervals(self):
        parser = GUI.ProfilerParser(warmup_seconds=0)
        parser.feed(1, COUNTS)
        parser.feed(2, COUNTS.replace("NES frames: 29", "NES frames: 15"))
        self.assertIsNone(parser.feed(2.1, BREAKDOWN))
        parser.feed(3, COUNTS.replace("NES frames: 29", "NES frames: 15"))
        self.assertEqual(15, parser.feed(3.1, BREAKDOWN)["nes_frames"])

    def test_rejects_malformed_profiler_records(self):
        parser = GUI.ProfilerParser(warmup_seconds=0)
        for line in (
            "[NES Debug] Updates: broken",
            "[NES Debug] Breakdown: CPU nanms | PPU 1ms | Render 1ms",
        ):
            with self.subTest(line=line), self.assertRaises(GUI.BenchmarkError):
                parser.feed(1, line)

    def test_rejects_missing_measurements(self):
        with self.assertRaisesRegex(GUI.BenchmarkError, "No complete"):
            GUI.summarize_samples([])

    def test_summarizes_counts_separately_from_timing_averages(self):
        parser = GUI.ProfilerParser(warmup_seconds=0)
        parser.feed(0.1, COUNTS)
        parser.feed(0.2, BREAKDOWN)
        parser.feed(1, COUNTS)
        sample = parser.feed(1.1, BREAKDOWN)
        summary = GUI.summarize_samples([sample, sample])
        self.assertEqual(2, summary["sample_count"])
        self.assertEqual(58, summary["total_nes_frames"])
        self.assertEqual(38, summary["total_rendered_frames"])
        self.assertEqual(7.25, summary["median_cpu_ms_per_update"])


class ProcessCaptureTest(unittest.TestCase):
    def test_collects_stderr_samples_and_stops_the_owned_child(self):
        code = (
            "import sys,time\n"
            "while True:\n"
            f" print({COUNTS!r}, file=sys.stderr, flush=True)\n"
            f" print({BREAKDOWN!r}, file=sys.stderr, flush=True)\n"
            " time.sleep(0.02)\n"
        )
        with tempfile.TemporaryDirectory() as directory:
            result = GUI.capture_run(
                [sys.executable, "-c", code],
                Path(directory), Path(directory), "test", 0.04, 0.12,
            )
            self.assertGreater(result["summary"]["sample_count"], 0)
            self.assertTrue(result["terminated_by_supervisor"])
            self.assertIn(COUNTS, Path(result["stderr_log"]).read_text())

    def test_rejects_a_process_that_exits_early(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(GUI.BenchmarkError, "exited.*7"):
                GUI.capture_run(
                    [sys.executable, "-c", "raise SystemExit(7)"],
                    Path(directory), Path(directory), "test", 0, 0.2,
                )

    def test_rejects_a_process_with_no_profiler_samples(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(GUI.BenchmarkError, "No complete"):
                GUI.capture_run(
                    [sys.executable, "-c", "import time; time.sleep(10)"],
                    Path(directory), Path(directory), "test", 0, 0.08,
                )


if __name__ == "__main__":
    unittest.main()
