<?php

declare(strict_types=1);

namespace App\Cpu;

use App\Bus\CpuBus;
use App\Cpu\Enums\Addressing;
use App\Cpu\Objects\PayloadWithAdditionalCycle;
use App\Cpu\Objects\Registers;

class Cpu
{
    private const int READ_BYTE = 1;
    private const int READ_WORD = 2;

    private Registers $registers;

    private bool $hasBranched = false;

    /** @var array<int, \App\Cpu\Objects\OpcodeProps> */
    private array $opcodeList = [];

    /**
     * Creates a new CPU instance.
     */
    public function __construct(private CpuBus $bus, private Interrupts $interrupts)
    {
        $this->registers = Registers::getDefault();

        $opcodes = [];

        // TODO: We gotta do this next!
        foreach ($opcodes as $key => $opcode) {
            $this->opcodeList[hexdec($key)] = $opcode;
        }
    }

    /**
     * Executes a single CPU cycle.
     */
    public function run(): int
    {
        if ($this->interrupts->isNmiAsserted()) {
            $this->processNmi();
        }

        if ($this->interrupts->isIrqAsserted()) {
            $this->processIrq();
        }

        $opcode = $this->fetch($this->registers->pc);
        $ocp = $this->opcodeList[$opcode];
        $data = $this->getPayloadWithAdditionalCycle($ocp->mode);
        $this->execInstruction($ocp->baseName, $data->payload, $ocp->mode);

        return $ocp->cycle + $data->additionalCycle + ($this->hasBranched ? 1 : 0);
    }

    /**
     * Resets the CPU to its initial state.
     */
    public function reset(): void
    {
        $this->registers = Registers::getDefault();
        $this->registers->pc = $this->read(0xFFFC, self::READ_WORD);
    }

    private function fetch(int $address, int $size = self::READ_BYTE): int
    {
        $this->registers->pc += ($size === self::READ_BYTE) ? 1 : 2;

        return $this->read($address, $size);
    }

    private function read(int $address, int $size = self::READ_BYTE): int
    {
        $address &= 0xFFFF;

        return $size === self::READ_WORD
            ? ($this->bus->readByCpu($address) | $this->bus->readByCpu($address + 1) << 8)
            : $this->bus->readByCpu($address);
    }

    private function write(int $address, int $data): void
    {
        $this->bus->writeByCpu($address, $data);
    }

    private function push(int $data): void
    {
        $this->write(0x100 | ($this->registers->sp & 0xFF), $data);
        $this->registers->sp--;
    }

    private function pop(): int
    {
        $this->registers->sp++;

        return $this->read(0x100 | ($this->registers->sp & 0xFF), self::READ_BYTE);
    }

    private function branch(int $address): void
    {
        $this->registers->pc = $address;
        $this->hasBranched = true;
    }

    private function pushStatus(): void
    {
        $status = (+$this->registers->p->negative) << 7 |
            (+$this->registers->p->overflow) << 6 |
            (+$this->registers->p->reserved) << 5 |
            (+$this->registers->p->breakMode) << 4 |
            (+$this->registers->p->decimalMode) << 3 |
            (+$this->registers->p->interrupt) << 2 |
            (+$this->registers->p->zero) << 1 |
            (+$this->registers->p->carry);

        $this->push($status);
    }

    private function popStatus(): void
    {
        $status = $this->pop();

        $this->registers->p->negative = (bool) ($status & 0x80);
        $this->registers->p->overflow = (bool) ($status & 0x40);
        $this->registers->p->reserved = (bool) ($status & 0x20);
        $this->registers->p->breakMode = (bool) ($status & 0x10);
        $this->registers->p->decimalMode = (bool) ($status & 0x08);
        $this->registers->p->interrupt = (bool) ($status & 0x04);
        $this->registers->p->zero = (bool) ($status & 0x02);
        $this->registers->p->carry = (bool) ($status & 0x01);
    }

    private function popPC(): void
    {
        $this->registers->pc = $this->pop();
        $this->registers->pc += ($this->pop() << 8);
    }

    private function processNmi(): void
    {
        $this->interrupts->deassertNmi();
        $this->registers->p->breakMode = false;
        $this->push(($this->registers->pc >> 8) & 0xFF);
        $this->push($this->registers->pc & 0xFF);
        $this->pushStatus();
        $this->registers->p->interrupt = true;
        $this->registers->pc = $this->read(0xFFFA, self::READ_WORD);
    }

    private function processIrq(): void
    {
        if ($this->registers->p->interrupt) {
            return;
        }

        $this->interrupts->deassertIrq();
        $this->registers->p->breakMode = false;
        $this->push(($this->registers->pc >> 8) & 0xFF);
        $this->push($this->registers->pc & 0xFF);
        $this->pushStatus();
        $this->registers->p->interrupt = true;
        $this->registers->pc = $this->read(0xFFFE, self::READ_WORD);
    }

    /**
     * Fetches the payload and calculates any additional cycles based on the addressing mode.
     */
    private function getPayloadWithAdditionalCycle(Addressing $mode): PayloadWithAdditionalCycle
    {
        switch ($mode) {
            case Addressing::Accumulator:
            case Addressing::Implied:
                return new PayloadWithAdditionalCycle(0x00, 0);
            case Addressing::Immediate:
            case Addressing::ZeroPage:
                return new PayloadWithAdditionalCycle($this->fetch($this->registers->pc), 0);
            case Addressing::Relative:
                $baseAddress = $this->fetch($this->registers->pc);
                $address = $baseAddress < 0x80
                    ? $baseAddress + $this->registers->pc
                    : $baseAddress + $this->registers->pc - 256;

                return new PayloadWithAdditionalCycle(
                    payload: $address,
                    additionalCycle: ($address & 0xFF00) !== ($this->registers->pc & 0xFF00) ? 1 : 0,
                );
            case Addressing::ZeroPageX:
                return new PayloadWithAdditionalCycle(
                    payload: ($this->fetch($this->registers->pc) + $this->registers->x) & 0xFF,
                    additionalCycle: 0,
                );
            case Addressing::ZeroPageY:
                return new PayloadWithAdditionalCycle(
                    payload: $this->fetch($this->registers->pc) + $this->registers->x & 0xFF,
                    additionalCycle: 0,
                );
            case Addressing::Absolute:
                return new PayloadWithAdditionalCycle(
                    payload: $this->fetch($this->registers->pc, self::READ_WORD),
                    additionalCycle: 0,
                );
            case Addressing::AbsoluteX:
                $address = $this->fetch($this->registers->pc, self::READ_WORD);

                return new PayloadWithAdditionalCycle(
                    payload: ($address + $this->registers->x) & 0xFFFF,
                    additionalCycle: ($address & 0xFF00) !== (($address + $this->registers->x) & 0xFF00) ? 1 : 0,
                );
            case Addressing::AbsoluteY:
                $address = $this->fetch($this->registers->pc, self::READ_WORD);

                return new PayloadWithAdditionalCycle(
                    payload: ($address + $this->registers->y) & 0xFFFF,
                    additionalCycle: ($address & 0xFF00) !== (($address + $this->registers->y) & 0xFF00) ? 1 : 0,
                );
            case Addressing::PreIndexedIndirect:
                $baseAddress = ($this->fetch($this->registers->pc) + $this->registers->x) & 0xFF;
                $address = $this->read($baseAddress) + ($this->read(($baseAddress + 1) & 0xFF) << 8);

                return new PayloadWithAdditionalCycle(
                    payload: $address & 0xFFFF,
                    additionalCycle: ($address & 0xFF00) !== ($baseAddress & 0xFF00) ? 1 : 0,
                );
            case Addressing::PostIndexedIndirect:
                $payload = $this->fetch($this->registers->pc);
                $baseAddress = $this->read($payload) + ($this->read(($payload + 1) & 0xFF) << 8);
                $address = $baseAddress + $this->registers->y;

                return new PayloadWithAdditionalCycle(
                    payload: $address & 0xFFFF,
                    additionalCycle: ($address & 0xFF00) !== ($baseAddress & 0xFF00) ? 1 : 0,
                );
            case Addressing::IndirectAbsolute:
                $payload = $this->fetch($this->registers->pc, self::READ_WORD);
                $address = $this->read($payload) +
                    ($this->read(($payload & 0xFF00) | ((($payload & 0xFF) + 1) & 0xFF)) << 8);

                return new PayloadWithAdditionalCycle($address & 0xFFFF, 0);
        }
    }
}
