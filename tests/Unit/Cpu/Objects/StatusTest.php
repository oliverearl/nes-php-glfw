<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Objects;

use App\Cpu\Objects\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Status::class)]
final class StatusTest extends TestCase
{
    #[Test]
    public function it_creates_status_with_all_flags(): void
    {
        $status = new Status(
            negative: true,
            overflow: true,
            reserved: true,
            breakMode: true,
            decimalMode: true,
            interrupt: true,
            zero: true,
            carry: true,
        );

        $this::assertTrue($status->negative);
        $this::assertTrue($status->overflow);
        $this::assertTrue($status->reserved);
        $this::assertTrue($status->breakMode);
        $this::assertTrue($status->decimalMode);
        $this::assertTrue($status->interrupt);
        $this::assertTrue($status->zero);
        $this::assertTrue($status->carry);
    }

    #[Test]
    public function it_creates_status_with_all_flags_false(): void
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

        $this::assertFalse($status->negative);
        $this::assertFalse($status->overflow);
        $this::assertFalse($status->reserved);
        $this::assertFalse($status->breakMode);
        $this::assertFalse($status->decimalMode);
        $this::assertFalse($status->interrupt);
        $this::assertFalse($status->zero);
        $this::assertFalse($status->carry);
    }

    #[Test]
    public function it_allows_modifying_negative_flag(): void
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

        $status->negative = true;
        $this::assertTrue($status->negative);

        $status->negative = false;
        $this::assertFalse($status->negative);
    }

    #[Test]
    public function it_allows_modifying_overflow_flag(): void
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

        $status->overflow = true;
        $this::assertTrue($status->overflow);

        $status->overflow = false;
        $this::assertFalse($status->overflow);
    }

    #[Test]
    public function it_allows_modifying_zero_flag(): void
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

        $status->zero = true;
        $this::assertTrue($status->zero);

        $status->zero = false;
        $this::assertFalse($status->zero);
    }

    #[Test]
    public function it_allows_modifying_carry_flag(): void
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

        $status->carry = true;
        $this::assertTrue($status->carry);

        $status->carry = false;
        $this::assertFalse($status->carry);
    }

    #[Test]
    public function it_allows_modifying_interrupt_flag(): void
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

        $status->interrupt = true;
        $this::assertTrue($status->interrupt);

        $status->interrupt = false;
        $this::assertFalse($status->interrupt);
    }

    #[Test]
    public function it_allows_modifying_decimal_mode_flag(): void
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

        $status->decimalMode = true;
        $this::assertTrue($status->decimalMode);

        $status->decimalMode = false;
        $this::assertFalse($status->decimalMode);
    }

    #[Test]
    public function it_allows_modifying_break_mode_flag(): void
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

        $status->breakMode = true;
        $this::assertTrue($status->breakMode);

        $status->breakMode = false;
        $this::assertFalse($status->breakMode);
    }

    #[Test]
    public function it_allows_modifying_reserved_flag(): void
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

        $status->reserved = true;
        $this::assertTrue($status->reserved);

        $status->reserved = false;
        $this::assertFalse($status->reserved);
    }

    #[Test]
    public function it_handles_mixed_flag_states(): void
    {
        $status = new Status(
            negative: true,
            overflow: false,
            reserved: true,
            breakMode: false,
            decimalMode: true,
            interrupt: false,
            zero: true,
            carry: false,
        );

        $this::assertTrue($status->negative);
        $this::assertFalse($status->overflow);
        $this::assertTrue($status->reserved);
        $this::assertFalse($status->breakMode);
        $this::assertTrue($status->decimalMode);
        $this::assertFalse($status->interrupt);
        $this::assertTrue($status->zero);
        $this::assertFalse($status->carry);
    }

    #[Test]
    public function it_allows_toggling_all_flags(): void
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

        // Toggle all to true.
        $status->negative = true;
        $status->overflow = true;
        $status->reserved = true;
        $status->breakMode = true;
        $status->decimalMode = true;
        $status->interrupt = true;
        $status->zero = true;
        $status->carry = true;

        $this::assertTrue($status->negative);
        $this::assertTrue($status->overflow);
        $this::assertTrue($status->reserved);
        $this::assertTrue($status->breakMode);
        $this::assertTrue($status->decimalMode);
        $this::assertTrue($status->interrupt);
        $this::assertTrue($status->zero);
        $this::assertTrue($status->carry);

        // Toggle all back to false.
        $status->negative = false;
        $status->overflow = false;
        $status->reserved = false;
        $status->breakMode = false;
        $status->decimalMode = false;
        $status->interrupt = false;
        $status->zero = false;
        $status->carry = false;

        $this::assertFalse($status->negative);
        $this::assertFalse($status->overflow);
        $this::assertFalse($status->reserved);
        $this::assertFalse($status->breakMode);
        $this::assertFalse($status->decimalMode);
        $this::assertFalse($status->interrupt);
        $this::assertFalse($status->zero);
        $this::assertFalse($status->carry);
    }
}
