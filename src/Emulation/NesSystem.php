<?php

declare(strict_types=1);

namespace App\Emulation;

use App\Bus\CpuBus;
use App\Bus\Ram;
use App\Bus\Rom;
use App\Cpu\Cpu;
use App\Cpu\Dma;
use App\Cpu\Interrupts;
use App\Graphics\Ppu;
use App\Input\Controller;

class NesSystem
{
    /**
     * CPU used by the system.
     */
    public readonly Cpu $cpu;

    /**
     * CPU bus used by the system.
     */
    public readonly CpuBus $cpuBus;

    /**
     * Internal CPU RAM.
     */
    public readonly Ram $ram;

    /**
     * Program ROM mapped into CPU space.
     */
    public readonly Rom $programRom;

    /**
     * PPU used by the system.
     */
    public readonly Ppu $ppu;

    /**
     * Interrupt lines shared by CPU and PPU.
     */
    public readonly Interrupts $interrupts;

    /**
     * DMA controller used for OAM transfers.
     */
    public readonly Dma $dma;

    /**
     * Controller connected to the first controller port.
     */
    public readonly Controller $controller;

    /**
     * Creates a fully wired NES system.
     */
    public function __construct(
        Cpu $cpu,
        CpuBus $cpuBus,
        Ram $ram,
        Rom $programRom,
        Ppu $ppu,
        Interrupts $interrupts,
        Dma $dma,
        Controller $controller,
    ) {
        $this->cpu = $cpu;
        $this->cpuBus = $cpuBus;
        $this->ram = $ram;
        $this->programRom = $programRom;
        $this->ppu = $ppu;
        $this->interrupts = $interrupts;
        $this->dma = $dma;
        $this->controller = $controller;
    }
}
