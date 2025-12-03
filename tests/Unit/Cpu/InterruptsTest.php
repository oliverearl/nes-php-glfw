<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu;

use App\Cpu\Interrupts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interrupts::class)]
final class InterruptsTest extends TestCase
{
    #[Test]
    public function it_initializes_with_no_interrupts(): void
    {
        $interrupts = new Interrupts();

        $this::assertFalse($interrupts->isNmiAsserted());
        $this::assertFalse($interrupts->isIrqAsserted());
    }

    #[Test]
    public function it_asserts_nmi(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertNmi();

        $this::assertTrue($interrupts->isNmiAsserted());
        $this::assertFalse($interrupts->isIrqAsserted());
    }

    #[Test]
    public function it_deasserts_nmi(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertNmi();
        $this::assertTrue($interrupts->isNmiAsserted());

        $interrupts->deassertNmi();
        $this::assertFalse($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_asserts_irq(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertIrq();

        $this::assertTrue($interrupts->isIrqAsserted());
        $this::assertFalse($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_deasserts_irq(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertIrq();
        $this::assertTrue($interrupts->isIrqAsserted());

        $interrupts->deassertIrq();
        $this::assertFalse($interrupts->isIrqAsserted());
    }

    #[Test]
    public function it_handles_both_interrupts_simultaneously(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertNmi();
        $interrupts->assertIrq();

        $this::assertTrue($interrupts->isNmiAsserted());
        $this::assertTrue($interrupts->isIrqAsserted());
    }

    #[Test]
    public function it_deasserts_independently(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertNmi();
        $interrupts->assertIrq();

        $interrupts->deassertNmi();

        $this::assertFalse($interrupts->isNmiAsserted());
        $this::assertTrue($interrupts->isIrqAsserted());
    }

    #[Test]
    public function it_can_reassert_after_deasserting(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertNmi();
        $interrupts->deassertNmi();
        $interrupts->assertNmi();

        $this::assertTrue($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_handles_multiple_assertions(): void
    {
        $interrupts = new Interrupts();

        // Multiple assertions shouldn't change state
        $interrupts->assertNmi();
        $interrupts->assertNmi();
        $interrupts->assertNmi();

        $this::assertTrue($interrupts->isNmiAsserted());
    }

    #[Test]
    public function it_handles_multiple_deassertions(): void
    {
        $interrupts = new Interrupts();

        $interrupts->assertNmi();

        // Multiple deassertions shouldn't cause issues
        $interrupts->deassertNmi();
        $interrupts->deassertNmi();

        $this::assertFalse($interrupts->isNmiAsserted());
    }
}

