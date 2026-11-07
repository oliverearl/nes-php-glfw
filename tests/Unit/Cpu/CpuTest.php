<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu;

use App\Cpu\Cpu;
use App\Cpu\CpuState;
use App\Cpu\Interrupts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Cpu\FlatMemory;

#[CoversClass(Cpu::class)]
#[CoversClass(CpuState::class)]
final class CpuTest extends TestCase
{
    /**
     * Runs pinned independent RP2A03 vectors against state, memory, and elapsed cycles.
     *
     * @param array<string, mixed> $vector
     */
    #[Test]
    #[DataProvider('instructionVectors')]
    public function it_matches_independent_instruction_vectors(array $vector): void
    {
        $initial = $vector['initial'];
        $final = $vector['final'];
        $memory = new FlatMemory($initial['ram']);
        $cpu = new Cpu($memory, new Interrupts());
        $cpu->restoreState(new CpuState(
            $initial['a'],
            $initial['x'],
            $initial['y'],
            $initial['p'],
            $initial['s'],
            $initial['pc'],
        ));

        $this::assertSame(count($vector['cycles']), $cpu->run());
        // B is a stack-copy marker, not a stored processor flag.
        $this::assertEquals(new CpuState(
            $final['a'],
            $final['x'],
            $final['y'],
            ($final['p'] | 0x20) & 0xEF,
            $final['s'],
            $final['pc'],
        ), $cpu->snapshot());

        foreach ($final['ram'] as [$address, $expected]) {
            $this::assertSame($expected, $memory->readByCpu($address), sprintf('Memory $%04X.', $address));
        }
    }

    /**
     * Provides the first four upstream records for each supported non-KIL opcode.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function instructionVectors(): iterable
    {
        $path = __DIR__ . '/../../Support/Cpu/SingleStepTests/vectors.json';
        $vectors = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($vectors as $index => $vector) {
            yield $vector['name'] . ' #' . $index => [$vector];
        }
    }

    /**
     * Checks all byte operands and incoming carries against signed arithmetic.
     */
    #[Test]
    public function it_performs_binary_adc_and_sbc_exhaustively_even_with_decimal_set(): void
    {
        $memory = new FlatMemory();
        $cpu = new Cpu($memory, new Interrupts());

        foreach ([0x69, 0xE9] as $opcode) {
            $memory->writeByCpu(0x8000, $opcode);

            for ($accumulator = 0; $accumulator <= 0xFF; $accumulator++) {
                for ($operand = 0; $operand <= 0xFF; $operand++) {
                    $memory->writeByCpu(0x8001, $operand);
                    $memory->writes = [];

                    for ($carry = 0; $carry <= 1; $carry++) {
                        $cpu->restoreState(new CpuState($accumulator, 0, 0, 0x2C | $carry, 0xFD, 0x8000));
                        $cycles = $cpu->run();
                        $state = $cpu->snapshot();
                        $signedA = $accumulator < 128 ? $accumulator : $accumulator - 256;
                        $signedOperand = $operand < 128 ? $operand : $operand - 256;
                        $sum = $opcode === 0x69 ? $accumulator + $operand + $carry : $accumulator - $operand - (1 - $carry);
                        $signed = $opcode === 0x69 ? $signedA + $signedOperand + $carry : $signedA - $signedOperand - (1 - $carry);
                        $result = $sum & 0xFF;
                        $flags = 0x2C | ($result & 0x80) | (($signed < -128 || $signed > 127) ? 0x40 : 0)
                            | ($result === 0 ? 2 : 0) | (($opcode === 0x69 ? $sum > 255 : $sum >= 0) ? 1 : 0);

                        $this::assertSame(
                            [$result, $flags, 0x8002, 2],
                            [$state->a, $state->p, $state->pc, $cycles],
                            sprintf('Opcode $%02X A=$%02X operand=$%02X carry=%d.', $opcode, $accumulator, $operand, $carry),
                        );
                    }
                }
            }
        }
    }

    /**
     * Verifies every branch condition with positive and negative page boundaries.
     */
    #[Test]
    public function it_counts_only_taken_branch_page_crossings(): void
    {
        foreach ([[0x10, 0x80, false], [0x30, 0x80, true], [0x50, 0x40, false], [0x70, 0x40, true],
            [0x90, 1, false], [0xB0, 1, true], [0xD0, 2, false], [0xF0, 2, true]] as [$opcode, $flag, $whenSet]) {
            foreach ([[0x8000, 2, 0x8004, false], [0x80FD, 2, 0x8101, true],
                [0x8000, 0xFC, 0x7FFE, true], [0x8004, 0xFC, 0x8002, false]] as [$pc, $offset, $target, $crosses]) {
                foreach ([false, true] as $taken) {
                    $status = 0x20 | ($taken === $whenSet ? $flag : 0);
                    [$cpu] = $this->createCpu([[$pc, $opcode], [$pc + 1, $offset]], new CpuState(0, 0, 0, $status, 0xFD, $pc));

                    $this::assertSame($taken ? 3 + (int) $crosses : 2, $cpu->run());
                    $this::assertSame($taken ? $target : $pc + 2, $cpu->snapshot()->pc);
                }
            }
        }
    }

    /**
     * Verifies zero-page pointer wrapping and the fixed indexed-indirect duration.
     */
    #[Test]
    public function it_wraps_indexed_indirect_pointers_within_zero_page(): void
    {
        [$cpu] = $this->createCpu([[0x8000, 0xA1], [0x8001, 0xFE], [0xFF, 0x34],
            [0, 0x12], [0x100, 0x56], [0x1234, 0xAB]], new CpuState(0, 1, 0, 0x24, 0xFD, 0x8000));

        $this::assertSame(6, $cpu->run());
        $this::assertEquals(new CpuState(0xAB, 1, 0, 0xA4, 0xFD, 0x8002), $cpu->snapshot());
    }

    /**
     * Verifies the RP2A03's indirect-JMP page-wrap behavior.
     */
    #[Test]
    public function it_wraps_the_high_byte_of_an_indirect_jump_within_its_page(): void
    {
        [$cpu] = $this->createCpu([[0x8000, 0x6C], [0x8001, 0xFF], [0x8002, 0x12],
            [0x12FF, 0x78], [0x1200, 0x56], [0x1300, 0xAB]]);

        $this::assertSame(5, $cpu->run());
        $this::assertSame(0x5678, $cpu->snapshot()->pc);
    }

    /**
     * Keeps instruction and operand fetches in the sixteen-bit address space.
     */
    #[Test]
    public function it_wraps_the_program_counter_and_word_operand_fetches(): void
    {
        [$cpu] = $this->createCpu(
            [[0xFFFE, 0xAD], [0xFFFF, 0x34], [0, 0x12], [0x1234, 0x42]],
            new CpuState(0, 0, 0, 0x24, 0xFD, 0xFFFE),
        );

        $this::assertSame(4, $cpu->run());
        $this::assertSame(1, $cpu->snapshot()->pc);
        $this::assertSame(0x42, $cpu->snapshot()->a);
    }

    /**
     * Keeps push and pull operations in page one while wrapping the eight-bit pointer.
     */
    #[Test]
    public function it_wraps_the_stack_pointer_and_preserves_snapshot_isolation(): void
    {
        [$cpu, $memory] = $this->createCpu(
            [[0x8000, 0x48], [0x8001, 0x68]],
            new CpuState(0x80, 0, 0, 0x24, 0, 0x8000),
        );
        $before = $cpu->snapshot();

        $this::assertSame(3, $cpu->run());
        $this::assertSame(0x80, $memory->readByCpu(0x100));
        $this::assertSame(0xFF, $cpu->snapshot()->sp);
        $this::assertSame(4, $cpu->run());
        $this::assertEquals(new CpuState(0x80, 0, 0, 0xA4, 0, 0x8002), $cpu->snapshot());
        $this::assertEquals(new CpuState(0x80, 0, 0, 0x24, 0, 0x8000), $before);
    }

    /**
     * Pushes BRK's padding-byte return address and vectors even when I is set.
     */
    #[Test]
    public function it_vectors_brk_regardless_of_interrupt_disable_and_rti_returns_after_padding(): void
    {
        foreach ([0x20, 0x24] as $status) {
            [$cpu, $memory] = $this->createCpu(
                [[0x8000, 0], [0xFFFE, 0], [0xFFFF, 0x90], [0x9000, 0x40]],
                new CpuState(0, 0, 0, $status, 0xFD, 0x8000),
            );

            $this::assertSame(7, $cpu->run());
            $this::assertSame([[0x1FD, 0x80], [0x1FC, 2], [0x1FB, $status | 0x10]], $memory->writes);
            $this::assertSame(0x9000, $cpu->snapshot()->pc);
            $this::assertSame(6, $cpu->run());
            $this::assertEquals(new CpuState(0, 0, 0, $status, 0xFD, 0x8002), $cpu->snapshot());
        }
    }

    /**
     * Performs interrupt entry as its own seven-cycle step and prioritizes NMI.
     */
    #[Test]
    public function it_services_nmi_without_executing_the_handler_opcode_or_clearing_irq(): void
    {
        [$cpu, $memory, $interrupts] = $this->createCpu(
            [[0xFFFA, 0], [0xFFFB, 0x90], [0x9000, 0xE8]],
            new CpuState(0, 3, 0, 0x20, 0xFD, 0x8123),
        );
        $interrupts->assertNmi();
        $interrupts->assertIrq();

        $this::assertSame(7, $cpu->run());
        $this::assertEquals(new CpuState(0, 3, 0, 0x24, 0xFA, 0x9000), $cpu->snapshot());
        $this::assertSame([[0x1FD, 0x81], [0x1FC, 0x23], [0x1FB, 0x20]], $memory->writes);
        $this::assertFalse($interrupts->isNmiAsserted());
        $this::assertTrue($interrupts->isIrqAsserted());
        $this::assertSame(2, $cpu->run());
        $this::assertSame(4, $cpu->snapshot()->x);
    }

    /**
     * Leaves a device-owned IRQ asserted after entry and observes the I mask.
     */
    #[Test]
    public function it_services_an_unmasked_irq_without_acknowledging_its_device(): void
    {
        [$cpu, $memory, $interrupts] = $this->createCpu(
            [[0x8000, 0xEA], [0xFFFE, 0x34], [0xFFFF, 0x90]],
            new CpuState(0, 0, 0, 0x20, 0xFD, 0x8000),
        );
        $interrupts->assertIrq();

        $this::assertSame(7, $cpu->run());
        $this::assertEquals(new CpuState(0, 0, 0, 0x24, 0xFA, 0x9034), $cpu->snapshot());
        $this::assertSame([[0x1FD, 0x80], [0x1FC, 0], [0x1FB, 0x20]], $memory->writes);
        $this::assertTrue($interrupts->isIrqAsserted());

        $cpu->restoreState(new CpuState(0, 0, 0, 0x24, 0xFD, 0x8000));
        $this::assertSame(2, $cpu->run());
        $this::assertSame(0x8001, $cpu->snapshot()->pc);
        $this::assertTrue($interrupts->isIrqAsserted());
    }

    /**
     * Models CLI's delayed IRQ mask effect at the next instruction poll.
     */
    #[Test]
    public function it_delays_an_irq_for_one_instruction_after_cli(): void
    {
        [$cpu, , $interrupts] = $this->createCpu([[0x8000, 0x58], [0x8001, 0xEA],
            [0xFFFE, 0], [0xFFFF, 0x90]]);
        $interrupts->assertIrq();

        $cycles = [$cpu->run()];
        $this::assertSame(0x20, $cpu->snapshot()->p);
        $cycles[] = $cpu->run();
        $this::assertSame(0x8002, $cpu->snapshot()->pc);
        $cycles[] = $cpu->run();
        $this::assertSame(0x9000, $cpu->snapshot()->pc);
        $this::assertSame([2, 2, 7], $cycles);
    }

    /**
     * Uses the pre-instruction I flag when SEI or PLP changes the mask.
     */
    #[Test]
    public function it_polls_irq_using_the_old_mask_after_sei_and_plp(): void
    {
        foreach ([[0x78, 2], [0x28, 4]] as [$opcode, $cycles]) {
            [$cpu, , $interrupts] = $this->createCpu([[0x8000, $opcode], [0x8001, 0xEA], [0x1FD, 0x24],
                [0xFFFE, 0], [0xFFFF, 0x90]], new CpuState(0, 0, 0, 0x20, 0xFC, 0x8000));

            $this::assertSame($cycles, $cpu->run());
            $this::assertSame(0x24, $cpu->snapshot()->p);
            $interrupts->assertIrq();
            $this::assertSame(7, $cpu->run());
            $this::assertSame(0x9000, $cpu->snapshot()->pc);
        }
    }

    /**
     * Ensures NMOS read-modify-write instructions write the old value before the result.
     */
    #[Test]
    public function it_preserves_original_value_writes_for_official_and_unofficial_rmw(): void
    {
        foreach ([[0x06, 0x80, 0], [0x46, 1, 0], [0x26, 0x80, 1], [0x66, 1, 0x80],
            [0xC6, 0, 0xFF], [0xE6, 0xFF, 0], [0xC7, 1, 0], [0xE7, 0x7F, 0x80],
            [0x07, 0x80, 0], [0x27, 0x80, 1], [0x47, 1, 0], [0x67, 1, 0x80]] as [$opcode, $old, $new]) {
            [$cpu, $memory] = $this->createCpu(
                [[0x8000, $opcode], [0x8001, 0x10], [0x10, $old]],
                new CpuState(0x7F, 0, 0, 0x21, 0xFD, 0x8000),
            );

            $this::assertSame(5, $cpu->run());
            $this::assertSame([[0x10, $old], [0x10, $new]], $memory->writes, sprintf('Opcode $%02X.', $opcode));
        }
    }

    /**
     * Normalizes imported register widths while keeping snapshots detached.
     */
    #[Test]
    public function it_normalizes_restored_registers_and_synthetic_status_bits(): void
    {
        [$cpu] = $this->createCpu([], new CpuState(0x1FF, 0x101, -1, 0xFF, 0x100, 0x10002));

        $this::assertEquals(new CpuState(0xFF, 1, 0xFF, 0xEF, 0, 2), $cpu->snapshot());
    }

    /**
     * Verifies reset reads its vector and restores the documented initial CPU state.
     */
    #[Test]
    public function it_loads_the_reset_vector_without_mutating_memory(): void
    {
        [$cpu, $memory] = $this->createCpu(
            [[0xFFFC, 0x34], [0xFFFD, 0x92]],
            new CpuState(0xAA, 0xBB, 0xCC, 0xFF, 0, 0),
        );

        $cpu->reset();

        $this::assertEquals(new CpuState(0, 0, 0, 0x24, 0xFD, 0x9234), $cpu->snapshot());
        $this::assertSame([], $memory->writes);
    }

    /**
     * Creates an isolated CPU with controlled memory and initial registers.
     *
     * @param list<array{int, int}> $bytes
     * @return array{Cpu, FlatMemory, Interrupts}
     */
    private function createCpu(array $bytes, ?CpuState $state = null): array
    {
        $memory = new FlatMemory($bytes);
        $interrupts = new Interrupts();
        $cpu = new Cpu($memory, $interrupts);
        $cpu->restoreState($state ?? new CpuState(0, 0, 0, 0x24, 0xFD, 0x8000));

        return [$cpu, $memory, $interrupts];
    }
}
