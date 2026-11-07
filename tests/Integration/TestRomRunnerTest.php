<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Cartridge\Cartridge;
use App\Cartridge\Loader;
use App\Emulation\NesSystemFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\HardwareTestRom;
use Tests\Support\TestRomRunner;
use Tests\Support\TestRomStatus;

final class TestRomRunnerTest extends IntegrationTestCase
{
    /**
     * Checks that the runner executes a rom and checks its signed completion.
     */
    #[Test]
    #[DataProvider('completionCodes')]
    public function it_executes_a_rom_and_checks_its_signed_completion(int $code, TestRomStatus $status): void
    {
        $path = HardwareTestRom::write($this->reportProgram($code));

        try {
            $result = new TestRomRunner()->runFile($path, 1000);

            $this::assertSame($status, $result->status);
            $this::assertSame($code, $result->code);
            $this::assertSame('OK', $result->output);
            $this::assertGreaterThan(0, $result->cpuCycles);
            $this::assertLessThan(1000, $result->cpuCycles);
        } finally {
            unlink($path);
        }
    }

    /**
     * Supplies protocol outcomes that must remain distinct.
     *
     * @return iterable<string, array{int, TestRomStatus}>
     */
    public static function completionCodes(): iterable
    {
        yield 'passed' => [0, TestRomStatus::Passed];
        yield 'failed with diagnostic code' => [3, TestRomStatus::Failed];
        yield 'requires hardware reset' => [0x81, TestRomStatus::ResetRequired];
    }

    /**
     * Checks that the runner never accepts zero from unmapped status memory as a pass.
     */
    #[Test]
    public function it_never_accepts_zero_from_unmapped_status_memory_as_a_pass(): void
    {
        $rom = array_fill(0, 0x4000, 0xEA);
        $rom[0] = 0x4C;
        $rom[1] = 0x00;
        $rom[2] = 0x80;
        $rom[0x3FFD] = 0x80;
        $system = new NesSystemFactory()->create(new Cartridge(true, $rom, []));

        $result = new TestRomRunner()->runSystem($system, 100);

        $this::assertSame(TestRomStatus::Timeout, $result->status);
        $this::assertNull($result->code);
        $this::assertStringContainsString('No valid running protocol', $result->output);
    }

    /**
     * Checks that the runner requires running state even with a valid signature and zero status.
     */
    #[Test]
    public function it_requires_running_state_even_with_a_valid_signature_and_zero_status(): void
    {
        $path = HardwareTestRom::write($this->reportProgram(0, running: false));

        try {
            $result = new TestRomRunner()->runFile($path, 1000);
            $this::assertSame(TestRomStatus::Timeout, $result->status);
        } finally {
            unlink($path);
        }
    }

    /**
     * Checks that the runner rejects a wrong signature and times out an unfinished test.
     */
    #[Test]
    public function it_rejects_a_wrong_signature_and_times_out_an_unfinished_test(): void
    {
        foreach ([[0, false], [0x80, true]] as [$code, $signature]) {
            $path = HardwareTestRom::write($this->reportProgram($code, signature: $signature));

            try {
                $result = new TestRomRunner()->runFile($path, 1000);
                $this::assertSame(TestRomStatus::Timeout, $result->status);
                $this::assertGreaterThanOrEqual(1000, $result->cpuCycles);
                $this::assertLessThan(1007, $result->cpuCycles);
            } finally {
                unlink($path);
            }
        }
    }

    /**
     * Checks that the runner reports missing hardware prerequisites instead of claiming pass or failure.
     */
    #[Test]
    public function it_reports_missing_hardware_prerequisites_instead_of_claiming_pass_or_failure(): void
    {
        $result = new TestRomRunner()->runFile('/not/opened.nes', requires: ['apu-length-counter']);

        $this::assertSame(TestRomStatus::Unsupported, $result->status);
        $this::assertSame(0, $result->cpuCycles);
        $this::assertStringContainsString('apu-length-counter', $result->output);
    }

    /**
     * Checks a DMA request cannot be mistaken for a completed supported execution.
     */
    #[Test]
    public function it_reports_dma_as_unsupported_before_charging_fictitious_elapsed_cycles(): void
    {
        $path = HardwareTestRom::write([0xA9, 0x02, 0x8D, 0x14, 0x40, 0x4C, 0x05, 0x80]);

        try {
            $result = new TestRomRunner()->runFile($path, 1000);

            $this::assertSame(TestRomStatus::Unsupported, $result->status);
            $this::assertNull($result->code);
            $this::assertSame(6, $result->cpuCycles);
            $this::assertStringContainsString('DMA', $result->output);
        } finally {
            unlink($path);
        }
    }

    /**
     * Checks a missing text terminator cannot read beyond the diagnostic byte limit.
     */
    #[Test]
    public function it_bounds_unterminated_diagnostic_output(): void
    {
        $path = HardwareTestRom::write($this->reportProgram(0, output: null));

        try {
            $system = new NesSystemFactory()->create(new Loader($path, debugEnabled: false)->load());

            for ($index = 0; $index < 4096; $index++) {
                $system->cpuBus->writeByCpu(0x6004 + $index, ord('A'));
            }

            $system->cpuBus->writeByCpu(0x7004, ord('B'));
            $result = new TestRomRunner()->runSystem($system, 1000);

            $this::assertSame(TestRomStatus::Passed, $result->status);
            $this::assertSame(str_repeat('A', 4096), $result->output);
        } finally {
            unlink($path);
        }
    }

    /**
     * Checks the first NUL byte terminates diagnostics even when later bytes are nonzero.
     */
    #[Test]
    public function it_stops_diagnostic_output_at_the_first_nul(): void
    {
        $path = HardwareTestRom::write($this->reportProgram(3, output: "bad\0ignored"));

        try {
            $result = new TestRomRunner()->runFile($path, 1000);

            $this::assertSame(TestRomStatus::Failed, $result->status);
            $this::assertSame('bad', $result->output);
        } finally {
            unlink($path);
        }
    }

    /**
     * Checks that the runner reports unsupported mappers separately from invalid files.
     */
    #[Test]
    public function it_reports_unsupported_mappers_separately_from_invalid_files(): void
    {
        $path = HardwareTestRom::write([0xEA]);

        try {
            $contents = file_get_contents($path);
            $contents[6] = "\x10";
            file_put_contents($path, $contents);
            $result = new TestRomRunner()->runFile($path);

            $this::assertSame(TestRomStatus::Unsupported, $result->status);
            $this::assertStringContainsString('mapper: 1', $result->output);

            file_put_contents($path, 'INVALID');
            $this->expectException(RuntimeException::class);
            new TestRomRunner()->runFile($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * Checks that the runner requires a positive emulated time budget.
     */
    #[Test]
    public function it_requires_a_positive_emulated_time_budget(): void
    {
        $path = HardwareTestRom::write([0xEA]);

        try {
            $this->expectException(InvalidArgumentException::class);
            new TestRomRunner()->runFile($path, 0);
        } finally {
            unlink($path);
        }
    }

    /**
     * Produces a small original CPU program using the blargg status protocol.
     *
     * @return list<int>
     */
    private function reportProgram(int $code, bool $running = true, bool $signature = true, ?string $output = 'OK'): array
    {
        $program = [];
        $writes = [0x6000 => $running ? 0x80 : 0, 0x6001 => 0xDE,
            0x6002 => 0xB0, 0x6003 => $signature ? 0x61 : 0];

        if ($output !== null) {
            foreach (str_split($output . "\0") as $offset => $byte) {
                $writes[0x6004 + $offset] = ord($byte[0]);
            }
        }

        foreach ($writes as $address => $byte) {
            $program[] = 0xA9;
            $program[] = $byte;
            $program[] = 0x8D;
            $program[] = $address & 0xFF;
            $program[] = $address >> 8;
        }
        $program[] = 0xA9;
        $program[] = $code;
        $program[] = 0x8D;
        $program[] = 0x00;
        $program[] = 0x60;
        $loop = 0x8000 + count($program);
        $program[] = 0x4C;
        $program[] = $loop & 0xFF;
        $program[] = $loop >> 8;

        return $program;
    }
}
