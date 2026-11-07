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
     * Device-owned IRQ levels combined into the CPU interrupt request line.
     *
     * @var array<string, true>
     */
    private array $irqSources = [];

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
        return $this->irqSources !== [];
    }

    /**
     * Asserts one device's IRQ level without affecting other sources.
     */
    public function assertIrq(string $source = 'external'): void
    {
        $this->irqSources[$source] = true;
    }

    /**
     * Acknowledges one device's IRQ level without clearing other sources.
     */
    public function deassertIrq(string $source = 'external'): void
    {
        unset($this->irqSources[$source]);
    }
}
