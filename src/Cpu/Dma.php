<?php

declare(strict_types=1);

namespace App\Cpu;

use App\Bus\Ram;
use App\Graphics\Ppu;

class Dma
{
    /**
     * The address in RAM from which data will be read for the DMA transfer.
     */
    private int $address = 0x0000;

    /**
     * Indicates whether a DMA transfer is currently being processed.
     */
    private bool $isProcessing = false;

    /**
     * Creates a new DMA controller instance.
     */
    public function __construct(private readonly Ram $ram, private readonly Ppu $ppu) {}

    /**
     * Checks if a DMA transfer is currently in progress.
     */
    public function isDmaProcessing(): bool
    {
        return $this->isProcessing;
    }

    /**
     * Executes the DMA transfer if one has been initiated.
     * Transfers 256 bytes from RAM to the PPU's sprite memory.
     */
    public function runDma(): void
    {
        if (! $this->isDmaProcessing()) {
            return;
        }

        for ($i = 0; $i < 0x100; $i++) {
            $this->ppu->transferSprite($i, $this->ram->read($this->address + $i));
        }

        $this->isProcessing = false;
    }

    /**
     * Initiates a DMA transfer by setting the starting address based on the provided data.
     */
    public function write(int $data): void
    {
        $this->address = $data << 8;
        $this->isProcessing = true;
    }
}
