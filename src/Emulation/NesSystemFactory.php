<?php

declare(strict_types=1);

namespace App\Emulation;

use App\Bus\CpuBus;
use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Bus\Rom;
use App\Cartridge\Cartridge;
use App\Cpu\Cpu;
use App\Cpu\Dma;
use App\Cpu\Interrupts;
use App\Graphics\Ppu;
use App\Input\Controller;
use App\Input\NullController;

class NesSystemFactory
{
    /**
     * Default CPU RAM size in bytes.
     */
    private const int CPU_RAM_SIZE = 0x0800;

    /**
     * Default PPU character RAM size in bytes.
     */
    private const int CHARACTER_RAM_SIZE = 0x4000;

    /**
     * Creates a complete NES system from a cartridge.
     */
    public function create(Cartridge $cartridge, ?Controller $controller = null): NesSystem
    {
        $ram = new Ram(self::CPU_RAM_SIZE);
        $programRom = new Rom($cartridge->programRom);
        $interrupts = new Interrupts();
        $characterRam = new Ram(self::CHARACTER_RAM_SIZE);

        for ($i = 0, $iMax = $cartridge->getCharacterRomSize(); $i < $iMax; $i++) {
            $characterRam->write($i, $cartridge->characterRom[$i]);
        }

        $ppuBus = new PpuBus($characterRam);
        $ppu = new Ppu($ppuBus, $interrupts, $cartridge->isHorizontalMirror);
        $controller ??= new NullController();
        $dma = new Dma($ram, $ppu);
        $cpuBus = new CpuBus($ram, $programRom, $ppu, $controller, $dma);
        $cpu = new Cpu($cpuBus, $interrupts);

        $cpu->reset();

        return new NesSystem($cpu, $cpuBus, $ram, $programRom, $ppu, $interrupts, $dma, $controller);
    }
}
