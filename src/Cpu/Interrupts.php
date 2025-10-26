<?php

declare(strict_types=1);

namespace App\Cpu;

class Interrupts
{
    /**
     * The NMI (Non-Maskable Interrupt) flag.
     */
    private bool $nmi = false;

    /**
     * The IRQ (Interrupt Request) flag.
     */
    private bool $irq = false;

    /**
     * Checks if the NMI is currently asserted.
     */
    public function isNmiAsserted(): bool
    {
        return $this->nmi;
    }

    /**
     * Asserts the NMI interrupt.
     */
    public function assertNmi(): void
    {
        $this->nmi = true;
    }

    /**
     * Deasserts the NMI interrupt.
     */
    public function deassertNmi(): void
    {
        $this->nmi = false;
    }

    /**
     * Checks if the IRQ is currently asserted.
     */
    public function isIrqAsserted(): bool
    {
        return $this->irq;
    }

    /**
     * Asserts the IRQ interrupt.
     */
    public function assertIrq(): void
    {
        $this->irq = true;
    }

    /**
     * Deasserts the IRQ interrupt.
     */
    public function deassertIrq(): void
    {
        $this->irq = false;
    }
}
