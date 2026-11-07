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
     * CPU memory mapping used by a wired system, including RAM mirrors and cartridge space.
     */
    private ?Memory $bus = null;

    /**
     * Creates a new DMA controller instance.
     */
    public function __construct(private readonly Ram $ram, private readonly Ppu $ppu) {}

    /**
     * Connects CPU address decoding after the bus and DMA controller have been constructed.
     */
    public function connectBus(Memory $bus): void
    {
        $this->bus = $bus;
    }

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
            $data = $this->bus?->readByCpu($this->address + $i) ?? $this->ram->read($this->address + $i);
            $this->ppu->transferSprite($i, $data);
        }

        $this->isProcessing = false;
    }

    /**
     * Initiates a DMA transfer by setting the starting address based on the provided data.
     */
    public function write(int $data): void
    {
        $this->address = ($data & 0xFF) << 8;
        $this->isProcessing = true;
    }
}
