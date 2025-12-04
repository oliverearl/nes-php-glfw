<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Objects;

use App\Cpu\Objects\Registers;
use App\Cpu\Objects\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Registers::class)]
final class RegistersTest extends TestCase
{
    #[Test]
    public function it_creates_registers_with_values(): void
    {
        $status = new Status(
            negative: false,
            overflow: false,
            reserved: true,
            breakMode: false,
            decimalMode: false,
            interrupt: false,
            zero: false,
            carry: false,
        );

        $registers = new Registers(
            a: 0x42,
            x: 0x10,
            y: 0x20,
            p: $status,
            sp: 0x01FD,
            pc: 0x8000,
        );

        $this::assertSame(0x42, $registers->a);
        $this::assertSame(0x10, $registers->x);
        $this::assertSame(0x20, $registers->y);
        $this::assertSame($status, $registers->p);
        $this::assertSame(0x01FD, $registers->sp);
        $this::assertSame(0x8000, $registers->pc);
    }

    #[Test]
    public function it_creates_default_registers(): void
    {
        $registers = Registers::getDefault();

        $this::assertSame(0x00, $registers->a);
        $this::assertSame(0x00, $registers->x);
        $this::assertSame(0x00, $registers->y);
        $this::assertSame(0x01FD, $registers->sp);
        $this::assertSame(0x0000, $registers->pc);

        $this::assertFalse($registers->p->negative);
        $this::assertFalse($registers->p->overflow);
        $this::assertTrue($registers->p->reserved);
        $this::assertTrue($registers->p->breakMode);
        $this::assertFalse($registers->p->decimalMode);
        $this::assertTrue($registers->p->interrupt);
        $this::assertFalse($registers->p->zero);
        $this::assertFalse($registers->p->carry);
    }

    #[Test]
    public function it_allows_modifying_register_values(): void
    {
        $registers = Registers::getDefault();

        $registers->a = 0xFF;
        $registers->x = 0xAA;
        $registers->y = 0x55;
        $registers->sp = 0x0100;
        $registers->pc = 0xFFFC;

        $this::assertSame(0xFF, $registers->a);
        $this::assertSame(0xAA, $registers->x);
        $this::assertSame(0x55, $registers->y);
        $this::assertSame(0x0100, $registers->sp);
        $this::assertSame(0xFFFC, $registers->pc);
    }

    #[Test]
    public function it_allows_modifying_status_flags(): void
    {
        $registers = Registers::getDefault();

        $registers->p->negative = true;
        $registers->p->zero = true;
        $registers->p->carry = true;

        $this::assertTrue($registers->p->negative);
        $this::assertTrue($registers->p->zero);
        $this::assertTrue($registers->p->carry);
    }

    #[Test]
    public function it_handles_8bit_register_values(): void
    {
        $status = new Status(
            negative: false,
            overflow: false,
            reserved: false,
            breakMode: false,
            decimalMode: false,
            interrupt: false,
            zero: false,
            carry: false,
        );

        $registers = new Registers(
            a: 0xFF,
            x: 0xFF,
            y: 0xFF,
            p: $status,
            sp: 0xFFFF,
            pc: 0xFFFF,
        );

        $this::assertSame(0xFF, $registers->a);
        $this::assertSame(0xFF, $registers->x);
        $this::assertSame(0xFF, $registers->y);
        $this::assertSame(0xFFFF, $registers->sp);
        $this::assertSame(0xFFFF, $registers->pc);
    }

    #[Test]
    public function it_handles_zero_values(): void
    {
        $status = new Status(
            negative: false,
            overflow: false,
            reserved: false,
            breakMode: false,
            decimalMode: false,
            interrupt: false,
            zero: false,
            carry: false,
        );

        $registers = new Registers(
            a: 0x00,
            x: 0x00,
            y: 0x00,
            p: $status,
            sp: 0x0000,
            pc: 0x0000,
        );

        $this::assertSame(0x00, $registers->a);
        $this::assertSame(0x00, $registers->x);
        $this::assertSame(0x00, $registers->y);
        $this::assertSame(0x0000, $registers->sp);
        $this::assertSame(0x0000, $registers->pc);
    }

    #[Test]
    public function default_registers_have_correct_stack_pointer(): void
    {
        $registers = Registers::getDefault();

        // 6502 stack is from 0x0100-0x01FF, initialised to 0x01FD.
        $this::assertSame(0x01FD, $registers->sp);
    }

    #[Test]
    public function default_registers_have_interrupt_flag_set(): void
    {
        $registers = Registers::getDefault();

        // Interrupt flag should be set on power-up.
        $this::assertTrue($registers->p->interrupt);
    }

    #[Test]
    public function default_registers_have_reserved_flag_set(): void
    {
        $registers = Registers::getDefault();

        // Reserved flag is always set.
        $this::assertTrue($registers->p->reserved);
    }

    #[Test]
    public function default_registers_have_break_mode_set(): void
    {
        $registers = Registers::getDefault();

        // Break mode flag should be set on power-up.
        $this::assertTrue($registers->p->breakMode);
    }
}
