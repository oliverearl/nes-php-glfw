<?php

declare(strict_types=1);

namespace App\Bus;

use App\Cpu\Dma;
use App\Graphics\Ppu;
use App\Input\Controller;

readonly class CpuBus
{
    /**
     * Creates a new CPU bus instance with all required components.
     */
    public function __construct(
        private Ram $ram,
        private Rom $programRom,
        private Ppu $ppu,
        private Controller $gamepad,
        private Dma $dma,
    ) {}

    /**
     * Reads a byte from the CPU address space.
     */
    public function readByCpu(int $address): int
    {
        if ($address < 0x0800) {
            return $this->ram->read($address);
        }

        if ($address < 0x2000) {
            return $this->ram->read($address % 0x0800);
        }

        if ($address < 0x4000) {
            return $this->ppu->read(($address - 0x2000) % 8);
        }

        if ($address === 0x4016) {
            return (int) $this->gamepad->read();
        }

        if ($address >= 0xC000) {
            if ($this->programRom->size() <= 0x4000) {
                return $this->programRom->read($address - 0xC000);
            }

            return $this->programRom->read($address - 0x8000);
        }

        if ($address >= 0x8000) {
            return $this->programRom->read($address - 0x8000);
        }

        return 0;
    }

    /**
     * Writes a byte to the CPU address space.
     */
    public function writeByCpu(int $address, int $data): void
    {
        if ($address < 0x0800) {
            $this->ram->write($address, $data);
        } elseif ($address < 0x2000) {
            $this->ram->write($address % 0x0800, $data);
        } elseif ($address < 0x4000) {
            $this->ppu->write(($address - 0x2000) % 8, $data);
        } elseif ($address < 0x4020) {
            if ($address === 0x4014) {
                $this->dma->write($data);
            } elseif ($address === 0x4016) {
                $this->gamepad->write($data);
            }
            // Writes to APU and other unmapped I/O registers are ignored.
        }

        // Ignore writes to other unmapped addresses.
    }
}
