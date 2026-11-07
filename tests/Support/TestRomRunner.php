<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Cartridge\Loader;
use App\Cartridge\UnsupportedCartridgeException;
use App\Emulation\NesSystem;
use App\Emulation\NesSystemFactory;
use InvalidArgumentException;

class TestRomRunner
{
    /**
     * Runs a compatible iNES test with an explicit list of hardware prerequisites.
     *
     * @param list<string> $requires
     */
    public function runFile(string $path, int $maxCpuCycles = 1_000_000, array $requires = []): TestRomResult
    {
        foreach ($requires as $feature) {
            if (! in_array($feature, ['cpu', 'nrom', 'ppu-registers', 'prg-ram'], true)) {
                return new TestRomResult(TestRomStatus::Unsupported, null, "Unsupported prerequisite: {$feature}.", 0);
            }
        }

        try {
            $cartridge = new Loader($path, debugEnabled: false)->load();
            $system = new NesSystemFactory()->create($cartridge);
        } catch (UnsupportedCartridgeException $exception) {
            return new TestRomResult(TestRomStatus::Unsupported, null, $exception->getMessage(), 0);
        }

        return $this->runSystem($system, $maxCpuCycles);
    }

    /**
     * Runs until a signed running-to-completion protocol or an emulated-clock timeout.
     * The budget is checked between whole instructions, so the final instruction may exceed it.
     */
    public function runSystem(NesSystem $system, int $maxCpuCycles = 1_000_000): TestRomResult
    {
        if ($maxCpuCycles <= 0) {
            throw new InvalidArgumentException('The CPU cycle budget must be positive.');
        }

        $cycles = 0;
        $running = false;

        while ($cycles < $maxCpuCycles) {
            if ($system->dma->isDmaProcessing()) {
                return new TestRomResult(TestRomStatus::Unsupported, null, 'Cycle-ordered DMA is required by this test.', $cycles);
            }

            $elapsed = $system->cpu->run();
            $cycles += $elapsed;
            $system->ppu->run($elapsed * 3);

            if ($system->cpuBus->readByCpu(0x6001) !== 0xDE
                || $system->cpuBus->readByCpu(0x6002) !== 0xB0
                || $system->cpuBus->readByCpu(0x6003) !== 0x61) {
                continue;
            }

            $code = $system->cpuBus->readByCpu(0x6000);

            if ($code === 0x80) {
                $running = true;
            } elseif ($running && $code === 0x81) {
                return new TestRomResult(TestRomStatus::ResetRequired, $code, $this->readOutput($system), $cycles);
            } elseif ($running && $code < 0x80) {
                return new TestRomResult(
                    $code === 0 ? TestRomStatus::Passed : TestRomStatus::Failed,
                    $code,
                    $this->readOutput($system),
                    $cycles,
                );
            }
        }

        $detail = $running ? $this->readOutput($system) : 'No valid running protocol was observed.';

        return new TestRomResult(TestRomStatus::Timeout, null, $detail, $cycles);
    }

    /**
     * Reads bounded null-terminated diagnostic text from cartridge RAM.
     */
    private function readOutput(NesSystem $system): string
    {
        $output = '';

        for ($address = 0x6004; $address < 0x7004; $address++) {
            $byte = $system->cpuBus->readByCpu($address);

            if ($byte === 0) {
                break;
            }

            $output .= chr($byte);
        }

        return $output;
    }
}
