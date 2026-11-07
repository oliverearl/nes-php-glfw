<?php

declare(strict_types=1);

namespace App\Emulation;

use App\Bus\CpuBus;
use App\Bus\PpuBus;
use App\Bus\Ram;
use App\Bus\Rom;
use App\Cartridge\Cartridge;
use App\Cartridge\UnsupportedCartridgeException;
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
     *
     * @throws UnsupportedCartridgeException
     */
    public function create(Cartridge $cartridge, ?Controller $controller = null): NesSystem
    {
        if ($cartridge->mapper !== 0
            || ! in_array($cartridge->getProgramRomSize(), [0x4000, 0x8000], strict: true)
            || ! in_array($cartridge->getCharacterRomSize(), [0, 0x2000], strict: true)
            || ! in_array($cartridge->programRamSize, [0, 0x2000], strict: true)
            || ! in_array(count($cartridge->trainer), [0, 512], strict: true)
            || ($cartridge->trainer !== [] && $cartridge->programRamSize === 0)
        ) {
            throw new UnsupportedCartridgeException('Unsupported NROM cartridge configuration.');
        }

        $ram = new Ram(self::CPU_RAM_SIZE);
        $programRom = new Rom($cartridge->programRom);
        $interrupts = new Interrupts();
        $characterRam = new Ram(self::CHARACTER_RAM_SIZE);

        for ($i = 0, $iMax = $cartridge->getCharacterRomSize(); $i < $iMax; $i++) {
            $characterRam->write($i, $cartridge->characterRom[$i]);
        }

        $ppuBus = new PpuBus($characterRam, readOnly: $cartridge->getCharacterRomSize() > 0);
        $ppu = new Ppu($ppuBus, $interrupts, $cartridge->isHorizontalMirror);
        $controller ??= new NullController();
        $dma = new Dma($ram, $ppu);
        $programRam = $cartridge->programRamSize > 0 ? new Ram($cartridge->programRamSize) : null;
        $cpuBus = new CpuBus($ram, $programRom, $ppu, $controller, $dma, $programRam);

        foreach ($cartridge->trainer as $offset => $byte) {
            $cpuBus->writeByCpu(0x7000 + $offset, $byte);
        }

        $cpu = new Cpu($cpuBus, $interrupts);

        $cpu->reset();

        return new NesSystem($cpu, $cpuBus, $ram, $programRom, $ppu, $interrupts, $dma, $controller);
    }
}
