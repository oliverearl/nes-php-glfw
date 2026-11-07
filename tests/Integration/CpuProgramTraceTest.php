<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Cartridge\Loader;
use App\Emulation\NesSystemFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\HardwareTestRom;

final class CpuProgramTraceTest extends IntegrationTestCase
{
    /**
     * Checks that the emulator matches a program trace across arithmetic branches stack and memory.
     */
    #[Test]
    public function it_matches_a_program_trace_across_arithmetic_branches_stack_and_memory(): void
    {
        $program = [0xA9, 0x7F, 0x18, 0x69, 0x01, 0x8D, 0x00, 0x02,
            0xA2, 0x03, 0xCA, 0xD0, 0xFD, 0x20, 0x20, 0x80,
            0x8D, 0x01, 0x02, 0x4C, 0x13, 0x80];
        $program = array_pad($program, 0x20, 0xEA);
        array_push($program, 0xA9, 0x00, 0x60);
        $path = HardwareTestRom::write($program);

        try {
            $system = new NesSystemFactory()->create(new Loader($path, debugEnabled: false)->load());

            /* Expected post-instruction states follow RP2A03 instruction semantics.
             * The trace exercises cumulative state rather than isolated opcodes. */
            $expected = [
                [0x8002, 0x7F, 0, 0x24, 0xFD, 2],
                [0x8003, 0x7F, 0, 0x24, 0xFD, 2],
                [0x8005, 0x80, 0, 0xE4, 0xFD, 2],
                [0x8008, 0x80, 0, 0xE4, 0xFD, 4],
                [0x800A, 0x80, 3, 0x64, 0xFD, 2],
                [0x800B, 0x80, 2, 0x64, 0xFD, 2],
                [0x800A, 0x80, 2, 0x64, 0xFD, 3],
                [0x800B, 0x80, 1, 0x64, 0xFD, 2],
                [0x800A, 0x80, 1, 0x64, 0xFD, 3],
                [0x800B, 0x80, 0, 0x66, 0xFD, 2],
                [0x800D, 0x80, 0, 0x66, 0xFD, 2],
                [0x8020, 0x80, 0, 0x66, 0xFB, 6],
                [0x8022, 0x00, 0, 0x66, 0xFB, 2],
                [0x8010, 0x00, 0, 0x66, 0xFD, 6],
                [0x8013, 0x00, 0, 0x66, 0xFD, 4],
            ];
            $elapsed = 0;

            foreach ($expected as $index => $row) {
                $cycles = $system->cpu->run();
                $state = $system->cpu->snapshot();
                $elapsed += $cycles;
                $this::assertSame($row, [$state->pc, $state->a, $state->x, $state->p, $state->sp, $cycles], "Trace row {$index}.");
            }

            $this::assertSame(44, $elapsed);
            $this::assertSame(0x80, $system->ram->read(0x0200));
            $this::assertSame(0, $system->ram->read(0x0201));
            $this::assertSame(0x80, $system->ram->read(0x1FD));
            $this::assertSame(0x0F, $system->ram->read(0x1FC));
        } finally {
            unlink($path);
        }
    }
}
