<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Objects;

use App\Cpu\Enums\Addressing;
use App\Cpu\Objects\OpcodeProps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(OpcodeProps::class)]
final class OpcodePropsTest extends TestCase
{
    #[Test]
    public function it_creates_opcode_props_with_all_properties(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'LDA',
            mode: Addressing::Immediate,
            cycle: 2,
        );

        $this::assertSame('LDA', $opcodeProps->baseName);
        $this::assertSame(Addressing::Immediate, $opcodeProps->mode);
        $this::assertSame(2, $opcodeProps->cycle);
    }

    #[Test]
    public function it_stores_various_instruction_names(): void
    {
        $instructions = ['LDA', 'STA', 'JMP', 'BNE', 'ADC', 'SBC', 'NOP', 'BRK'];

        foreach ($instructions as $instruction) {
            $opcodeProps = new OpcodeProps(
                baseName: $instruction,
                mode: Addressing::Implied,
                cycle: 2,
            );

            $this::assertSame($instruction, $opcodeProps->baseName);
        }
    }

    #[Test]
    public function it_stores_different_addressing_modes(): void
    {
        $modes = [
            Addressing::Immediate,
            Addressing::ZeroPage,
            Addressing::Absolute,
            Addressing::AbsoluteX,
            Addressing::AbsoluteY,
            Addressing::Implied,
            Addressing::Accumulator,
            Addressing::Relative,
            Addressing::PreIndexedIndirect,
            Addressing::PostIndexedIndirect,
            Addressing::IndirectAbsolute,
        ];

        foreach ($modes as $mode) {
            $opcodeProps = new OpcodeProps(
                baseName: 'TEST',
                mode: $mode,
                cycle: 2,
            );

            $this::assertSame($mode, $opcodeProps->mode);
        }
    }

    #[Test]
    public function it_stores_various_cycle_counts(): void
    {
        $cycleCounts = [2, 3, 4, 5, 6, 7, 8];

        foreach ($cycleCounts as $cycles) {
            $opcodeProps = new OpcodeProps(
                baseName: 'TEST',
                mode: Addressing::Implied,
                cycle: $cycles,
            );

            $this::assertSame($cycles, $opcodeProps->cycle);
        }
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'LDA',
            mode: Addressing::Immediate,
            cycle: 2,
        );

        $this::assertTrue(new ReflectionClass($opcodeProps)->isReadOnly());
    }

    #[Test]
    public function it_handles_lda_immediate(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'LDA',
            mode: Addressing::Immediate,
            cycle: 2,
        );

        $this::assertSame('LDA', $opcodeProps->baseName);
        $this::assertSame(Addressing::Immediate, $opcodeProps->mode);
        $this::assertSame(2, $opcodeProps->cycle);
    }

    #[Test]
    public function it_handles_sta_absolute(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'STA',
            mode: Addressing::Absolute,
            cycle: 4,
        );

        $this::assertSame('STA', $opcodeProps->baseName);
        $this::assertSame(Addressing::Absolute, $opcodeProps->mode);
        $this::assertSame(4, $opcodeProps->cycle);
    }

    #[Test]
    public function it_handles_jmp_indirect(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'JMP',
            mode: Addressing::IndirectAbsolute,
            cycle: 5,
        );

        $this::assertSame('JMP', $opcodeProps->baseName);
        $this::assertSame(Addressing::IndirectAbsolute, $opcodeProps->mode);
        $this::assertSame(5, $opcodeProps->cycle);
    }

    #[Test]
    public function it_handles_branch_instructions(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'BNE',
            mode: Addressing::Relative,
            cycle: 2,
        );

        $this::assertSame('BNE', $opcodeProps->baseName);
        $this::assertSame(Addressing::Relative, $opcodeProps->mode);
        $this::assertSame(2, $opcodeProps->cycle);
    }

    #[Test]
    public function it_handles_adc_indexed_indirect(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'ADC',
            mode: Addressing::PreIndexedIndirect,
            cycle: 6,
        );

        $this::assertSame('ADC', $opcodeProps->baseName);
        $this::assertSame(Addressing::PreIndexedIndirect, $opcodeProps->mode);
        $this::assertSame(6, $opcodeProps->cycle);
    }

    #[Test]
    public function it_handles_unofficial_opcodes(): void
    {
        $opcodeProps = new OpcodeProps(
            baseName: 'LAX',
            mode: Addressing::ZeroPage,
            cycle: 3,
        );

        $this::assertSame('LAX', $opcodeProps->baseName);
        $this::assertSame(Addressing::ZeroPage, $opcodeProps->mode);
        $this::assertSame(3, $opcodeProps->cycle);
    }
}
