<?php

declare(strict_types=1);

namespace App\Cpu;

use App\Bus\CpuBus;
use App\Cpu\Enums\Addressing;
use App\Cpu\Objects\OpcodeProps;
use App\Cpu\Objects\PayloadWithAdditionalCycle;
use App\Cpu\Objects\Registers;
use RuntimeException;

class Cpu
{
    /**
     * Opcodes cycle table.
     *
     * @var list<int>
     */
    private const array CYCLES = [
        7, 6, 2, 8, 3, 3, 5, 5, 3, 2, 2, 2, 4, 4, 6, 6,
        2, 5, 2, 8, 4, 4, 6, 6, 2, 4, 2, 7, 4, 4, 6, 7,
        6, 6, 2, 8, 3, 3, 5, 5, 4, 2, 2, 2, 4, 4, 6, 6,
        2, 5, 2, 8, 4, 4, 6, 6, 2, 4, 2, 7, 4, 4, 6, 7,
        6, 6, 2, 8, 3, 3, 5, 5, 3, 2, 2, 2, 3, 4, 6, 6,
        2, 5, 2, 8, 4, 4, 6, 6, 2, 4, 2, 7, 4, 4, 6, 7,
        6, 6, 2, 8, 3, 3, 5, 5, 4, 2, 2, 2, 5, 4, 6, 6,
        2, 5, 2, 8, 4, 4, 6, 6, 2, 4, 2, 7, 4, 4, 6, 7,
        2, 6, 2, 6, 3, 3, 3, 3, 2, 2, 2, 2, 4, 4, 4, 4,
        2, 6, 2, 6, 4, 4, 4, 4, 2, 4, 2, 5, 5, 4, 5, 5,
        2, 6, 2, 6, 3, 3, 3, 3, 2, 2, 2, 2, 4, 4, 4, 4,
        2, 5, 2, 5, 4, 4, 4, 4, 2, 4, 2, 4, 4, 4, 4, 4,
        2, 6, 2, 8, 3, 3, 5, 5, 2, 2, 2, 2, 4, 4, 6, 6,
        2, 5, 2, 8, 4, 4, 6, 6, 2, 4, 2, 7, 4, 4, 7, 7,
        2, 6, 3, 8, 3, 3, 5, 5, 2, 2, 2, 2, 4, 4, 6, 6,
        2, 5, 2, 8, 4, 4, 6, 6, 2, 4, 2, 7, 4, 4, 7, 7,
    ];

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

        $opcodes = $this->getOpcodes();

        foreach ($opcodes as $key => $opcode) {
            $this->opcodeList[hexdec($key)] = $opcode;
        }
    }

    /**
     * Executes a single CPU cycle.
     *
     * @throws \RuntimeException
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

        return $this->read(0x100 | ($this->registers->sp & 0xFF));
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

        throw new RuntimeException("Unsupported addressing mode: {$mode->name}");
    }

    private function execInstruction(string $name, int $payload, Addressing $mode): void
    {
        $this->hasBranched = false;

        switch ($$name) {
            case 'LDA':
                $this->registers->a = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !$this->registers->a;
                break;
            case 'LDX':
                $this->registers->x = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $this->registers->p->negative = (bool) ($this->registers->x & 0x80);
                $this->registers->p->zero = !$this->registers->x;
                break;
            case 'LDY':
                $this->registers->y = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $this->registers->p->negative = (bool) ($this->registers->y & 0x80);
                $this->registers->p->zero = !$this->registers->y;
                break;
            case 'STA':
                $this->write($payload, $this->registers->a);
                break;
            case 'STX':
                $this->write($payload, $this->registers->x);
                break;
            case 'STY':
                $this->write($payload, $this->registers->y);
                break;
            case 'TAX':
                $this->registers->x = $this->registers->a;
                $this->registers->p->negative = (bool) ($this->registers->x & 0x80);
                $this->registers->p->zero = !$this->registers->x;
                break;
            case 'TAY':
                $this->registers->y = $this->registers->a;
                $this->registers->p->negative = (bool) ($this->registers->y & 0x80);
                $this->registers->p->zero = !$this->registers->y;
                break;
            case 'TSX':
                $this->registers->x = $this->registers->sp & 0xFF;
                $this->registers->p->negative = (bool) ($this->registers->x & 0x80);
                $this->registers->p->zero = !$this->registers->x;
                break;
            case 'TXA':
                $this->registers->a = $this->registers->x;
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !$this->registers->a;
                break;
            case 'TXS':
                $this->registers->sp = $this->registers->x + 0x0100;
                break;
            case 'TYA':
                $this->registers->a = $this->registers->y;
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !$this->registers->a;
                break;
            case 'ADC':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $operated = $data + $this->registers->a + $this->registers->p->carry;
                $overflow = (!((($this->registers->a ^ $data) & 0x80) !== 0) &&
                    ((($this->registers->a ^ $operated) & 0x80)) !== 0);
                $this->registers->p->overflow = $overflow;
                $this->registers->p->carry = $operated > 0xFF;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !($operated & 0xFF);
                $this->registers->a = $operated & 0xFF;
                break;
            case 'AND':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $operated = $data & $this->registers->a;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !$operated;
                $this->registers->a = $operated & 0xFF;
                break;
            case 'ASL':
                if ($mode === Addressing::Accumulator) {
                    $acc = $this->registers->a;
                    $this->registers->p->carry = (bool) ($acc & 0x80);
                    $this->registers->a = ($acc << 1) & 0xFF;
                    $this->registers->p->zero = !$this->registers->a;
                    $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                } else {
                    $data = $this->read($payload);
                    $this->registers->p->carry = (bool) ($data & 0x80);
                    $shifted = ($data << 1) & 0xFF;
                    $this->write($payload, $shifted);
                    $this->registers->p->zero = !$shifted;
                    $this->registers->p->negative = (bool) ($shifted & 0x80);
                }
                break;
            case 'BIT':
                $data = $this->read($payload);
                $this->registers->p->negative = (bool) ($data & 0x80);
                $this->registers->p->overflow = (bool) ($data & 0x40);
                $this->registers->p->zero = !($this->registers->a & $data);
                break;
            case 'CMP':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $compared = $this->registers->a - $data;
                $this->registers->p->carry = $compared >= 0;
                $this->registers->p->negative = (bool) ($compared & 0x80);
                $this->registers->p->zero = !($compared & 0xff);
                break;
            case 'CPX':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $compared = $this->registers->x - $data;
                $this->registers->p->carry = $compared >= 0;
                $this->registers->p->negative = (bool) ($compared & 0x80);
                $this->registers->p->zero = !($compared & 0xff);
                break;
            case 'CPY':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $compared = $this->registers->y - $data;
                $this->registers->p->carry = $compared >= 0;
                $this->registers->p->negative = (bool) ($compared & 0x80);
                $this->registers->p->zero = !($compared & 0xff);
                break;
            case 'DEC':
                $data = ($this->read($payload) - 1) & 0xFF;
                $this->registers->p->negative = (bool) ($data & 0x80);
                $this->registers->p->zero = !$data;
                $this->write($payload, $data);
                break;
            case 'DEX':
                $this->registers->x = ($this->registers->x - 1) & 0xFF;
                $this->registers->p->negative = (bool) ($this->registers->x & 0x80);
                $this->registers->p->zero = !$this->registers->x;
                break;
            case 'DEY':
                $this->registers->y = ($this->registers->y - 1) & 0xFF;
                $this->registers->p->negative = (bool) ($this->registers->y & 0x80);
                $this->registers->p->zero = !$this->registers->y;
                break;
            case 'EOR':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $operated = $data ^ $this->registers->a;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !$operated;
                $this->registers->a = $operated & 0xFF;
                break;
            case 'INC':
                $data = ($this->read($payload) + 1) & 0xFF;
                $this->registers->p->negative = (bool) ($data & 0x80);
                $this->registers->p->zero = !$data;
                $this->write($payload, $data);
                break;
            case 'INX':
                $this->registers->x = ($this->registers->x + 1) & 0xFF;
                $this->registers->p->negative = (bool) ($this->registers->x & 0x80);
                $this->registers->p->zero = !$this->registers->x;
                break;
            case 'INY':
                $this->registers->y = ($this->registers->y + 1) & 0xFF;
                $this->registers->p->negative = (bool) ($this->registers->y & 0x80);
                $this->registers->p->zero = !$this->registers->y;
                break;
            case 'LSR':
                if ($mode === Addressing::Accumulator) {
                    $acc = $this->registers->a & 0xFF;
                    $this->registers->p->carry = (bool) ($acc & 0x01);
                    $this->registers->a = $acc >> 1;
                    $this->registers->p->zero = !$this->registers->a;
                } else {
                    $data = $this->read($payload);
                    $this->registers->p->carry = (bool) ($data & 0x01);
                    $this->registers->p->zero = !($data >> 1);
                    $this->write($payload, $data >> 1);
                }
                $this->registers->p->negative = false;
                break;
            case 'ORA':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $operated = $data | $this->registers->a;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !$operated;
                $this->registers->a = $operated & 0xFF;
                break;
            case 'ROL':
                if ($mode === Addressing::Accumulator) {
                    $acc = $this->registers->a;
                    $this->registers->a = ($acc << 1) & 0xFF | ($this->registers->p->carry ? 0x01 : 0x00);
                    $this->registers->p->carry = (bool) ($acc & 0x80);
                    $this->registers->p->zero = !$this->registers->a;
                    $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                } else {
                    $data = $this->read($payload);
                    $writeData = ($data << 1 | ($this->registers->p->carry ? 0x01 : 0x00)) & 0xFF;
                    $this->write($payload, $writeData);
                    $this->registers->p->carry = (bool) ($data & 0x80);
                    $this->registers->p->zero = !$writeData;
                    $this->registers->p->negative = (bool) ($writeData & 0x80);
                }
                break;
            case 'ROR':
                if ($mode === Addressing::Accumulator) {
                    $acc = $this->registers->a;
                    $this->registers->a = $acc >> 1 | ($this->registers->p->carry ? 0x80 : 0x00);
                    $this->registers->p->carry = (bool) ($acc & 0x01);
                    $this->registers->p->zero = !$this->registers->a;
                    $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                } else {
                    $data = $this->read($payload);
                    $writeData = $data >> 1 | ($this->registers->p->carry ? 0x80 : 0x00);
                    $this->write($payload, $writeData);
                    $this->registers->p->carry = (bool) ($data & 0x01);
                    $this->registers->p->zero = !$writeData;
                    $this->registers->p->negative = (bool) ($writeData & 0x80);
                }
                break;
            case 'SBC':
                $data = ($mode === Addressing::Immediate) ? $payload : $this->read($payload);
                $operated = $this->registers->a - $data - ($this->registers->p->carry ? 0 : 1);
                $overflow = ((($this->registers->a ^ $operated) & 0x80) !== 0 &&
                    (($this->registers->a ^ $data) & 0x80) !== 0);
                $this->registers->p->overflow = $overflow;
                $this->registers->p->carry = $operated >= 0;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !($operated & 0xFF);
                $this->registers->a = $operated & 0xFF;
                break;
            case 'PHA':
                $this->push($this->registers->a);
                break;
            case 'PHP':
                $this->registers->p->breakMode = true;
                $this->pushStatus();
                break;
            case 'PLA':
                $this->registers->a = $this->pop();
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !$this->registers->a;
                break;
            case 'PLP':
                $this->popStatus();
                $this->registers->p->reserved = true;
                break;
            case 'JMP':
                $this->registers->pc = $payload;
                break;
            case 'JSR':
                $pc = $this->registers->pc - 1;
                $this->push(($pc >> 8) & 0xFF);
                $this->push($pc & 0xFF);
                $this->registers->pc = $payload;
                break;
            case 'RTS':
                $this->popPC();
                $this->registers->pc++;
                break;
            case 'RTI':
                $this->popStatus();
                $this->popPC();
                $this->registers->p->reserved = true;
                break;
            case 'BCC':
                if (!$this->registers->p->carry) {
                    $this->branch($payload);
                }
                break;
            case 'BCS':
                if ($this->registers->p->carry) {
                    $this->branch($payload);
                }
                break;
            case 'BEQ':
                if ($this->registers->p->zero) {
                    $this->branch($payload);
                }
                break;
            case 'BMI':
                if ($this->registers->p->negative) {
                    $this->branch($payload);
                }
                break;
            case 'BNE':
                if (!$this->registers->p->zero) {
                    $this->branch($payload);
                }
                break;
            case 'BPL':
                if (!$this->registers->p->negative) {
                    $this->branch($payload);
                }
                break;
            case 'BVS':
                if ($this->registers->p->overflow) {
                    $this->branch($payload);
                }
                break;
            case 'BVC':
                if (!$this->registers->p->overflow) {
                    $this->branch($payload);
                }
                break;
            case 'CLD':
                $this->registers->p->decimalMode = false;
                break;
            case 'CLC':
                $this->registers->p->carry = false;
                break;
            case 'CLI':
                $this->registers->p->interrupt = false;
                break;
            case 'CLV':
                $this->registers->p->overflow = false;
                break;
            case 'SEC':
                $this->registers->p->carry = true;
                break;
            case 'SEI':
                $this->registers->p->interrupt = true;
                break;
            case 'SED':
                $this->registers->p->decimalMode = true;
                break;
            case 'BRK':
                $interrupt = $this->registers->p->interrupt;
                $this->registers->pc++;
                $this->push(($this->registers->pc >> 8) & 0xFF);
                $this->push($this->registers->pc & 0xFF);
                $this->registers->p->breakMode = true;
                $this->pushStatus();
                $this->registers->p->interrupt = true;
                // Ignore interrupt when already set.
                if (!$interrupt) {
                    $this->registers->pc = $this->read(0xFFFE, self::READ_WORD);
                }
                $this->registers->pc--;
                break;
            case 'NOP':
                break;
            // Unofficial Opecode
            case 'NOPD':
                $this->registers->pc++;
                break;
            case 'NOPI':
                $this->registers->pc += 2;
                break;
            case 'LAX':
                $this->registers->a = $this->registers->x = $this->read($payload);
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !$this->registers->a;
                break;
            case 'SAX':
                $operated = $this->registers->a & $this->registers->x;
                $this->write($payload, $operated);
                break;
            case 'DCP':
                $operated = ($this->read($payload) - 1) & 0xFF;
                $this->registers->p->negative = (bool) ((($this->registers->a - $operated) & 0x1FF) & 0x80);
                $this->registers->p->zero = !(($this->registers->a - $operated) & 0x1FF);
                $this->write($payload, $operated);
                break;
            case 'ISB':
                $data = ($this->read($payload) + 1) & 0xFF;
                $operated = (~$data & 0xFF) + $this->registers->a + $this->registers->p->carry;
                $overflow = (!((($this->registers->a ^ $data) & 0x80) !== 0) &&
                    ((($this->registers->a ^ $operated) & 0x80)) !== 0);
                $this->registers->p->overflow = $overflow;
                $this->registers->p->carry = $operated > 0xFF;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !($operated & 0xFF);
                $this->registers->a = $operated & 0xFF;
                $this->write($payload, $data);
                break;
            case 'SLO':
                $data = $this->read($payload);
                $this->registers->p->carry = (bool) ($data & 0x80);
                $data = ($data << 1) & 0xFF;
                $this->registers->a |= $data;
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !($this->registers->a & 0xFF);
                $this->write($payload, $data);
                break;
            case 'RLA':
                $data = ($this->read($payload) << 1) + $this->registers->p->carry;
                $this->registers->p->carry = (bool) ($data & 0x100);
                $this->registers->a = ($data & $this->registers->a) & 0xFF;
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !($this->registers->a & 0xFF);
                $this->write($payload, $data);
                break;
            case 'SRE':
                $data = $this->read($payload);
                $this->registers->p->carry = (bool) ($data & 0x01);
                $data >>= 1;
                $this->registers->a ^= $data;
                $this->registers->p->negative = (bool) ($this->registers->a & 0x80);
                $this->registers->p->zero = !($this->registers->a & 0xFF);
                $this->write($payload, $data);
                break;
            case 'RRA':
                $data = $this->read($payload);
                $carry = (bool) ($data & 0x01);
                $data = ($data >> 1) | ($this->registers->p->carry ? 0x80 : 0x00);
                $operated = $data + $this->registers->a + $carry;
                $overflow = (!((($this->registers->a ^ $data) & 0x80) !== 0) &&
                    ((($this->registers->a ^ $operated) & 0x80)) !== 0);
                $this->registers->p->overflow = $overflow;
                $this->registers->p->negative = (bool) ($operated & 0x80);
                $this->registers->p->zero = !($operated & 0xFF);
                $this->registers->a = $operated & 0xFF;
                $this->registers->p->carry = $operated > 0xFF;
                $this->write($payload, $data);
                break;
            default:
                throw new RuntimeException("Unknown opcode {$name} detected.");
        }
    }

    private function getOpcodes(): array
    {
        return [
            'A9' => new OpcodeProps('LDA', Addressing::Immediate, self::CYCLES[0xA9]),
            'A5' => new OpcodeProps('LDA', Addressing::ZeroPage, self::CYCLES[0xA5]),
            'AD' => new OpcodeProps('LDA', Addressing::Absolute, self::CYCLES[0xAD]),
            'B5' => new OpcodeProps('LDA', Addressing::ZeroPageX, self::CYCLES[0xB5]),
            'BD' => new OpcodeProps('LDA', Addressing::AbsoluteX, self::CYCLES[0xBD]),
            'B9' => new OpcodeProps('LDA', Addressing::AbsoluteY, self::CYCLES[0xB9]),
            'A1' => new OpcodeProps('LDA', Addressing::PreIndexedIndirect, self::CYCLES[0xA1]),
            'B1' => new OpcodeProps('LDA', Addressing::PostIndexedIndirect, self::CYCLES[0xB1]),
            'A2' => new OpcodeProps('LDX', Addressing::Immediate, self::CYCLES[0xA2]),
            'A6' => new OpcodeProps('LDX', Addressing::ZeroPage, self::CYCLES[0xA6]),
            'AE' => new OpcodeProps('LDX', Addressing::Absolute, self::CYCLES[0xAE]),
            'B6' => new OpcodeProps('LDX', Addressing::ZeroPageY, self::CYCLES[0xB6]),
            'BE' => new OpcodeProps('LDX', Addressing::AbsoluteY, self::CYCLES[0xBE]),
            'A0' => new OpcodeProps('LDY', Addressing::Immediate, self::CYCLES[0xA0]),
            'A4' => new OpcodeProps('LDY', Addressing::ZeroPage, self::CYCLES[0xA4]),
            'AC' => new OpcodeProps('LDY', Addressing::Absolute, self::CYCLES[0xAC]),
            'B4' => new OpcodeProps('LDY', Addressing::ZeroPageX, self::CYCLES[0xB4]),
            'BC' => new OpcodeProps('LDY', Addressing::AbsoluteX, self::CYCLES[0xBC]),
            '85' => new OpcodeProps('STA', Addressing::ZeroPage, self::CYCLES[0x85]),
            '8D' => new OpcodeProps('STA', Addressing::Absolute, self::CYCLES[0x8D]),
            '95' => new OpcodeProps('STA', Addressing::ZeroPageX, self::CYCLES[0x95]),
            '9D' => new OpcodeProps('STA', Addressing::AbsoluteX, self::CYCLES[0x9D]),
            '99' => new OpcodeProps('STA', Addressing::AbsoluteY, self::CYCLES[0x99]),
            '81' => new OpcodeProps('STA', Addressing::PreIndexedIndirect, self::CYCLES[0x81]),
            '91' => new OpcodeProps('STA', Addressing::PostIndexedIndirect, self::CYCLES[0x91]),
            '86' => new OpcodeProps('STX', Addressing::ZeroPage, self::CYCLES[0x86]),
            '8E' => new OpcodeProps('STX', Addressing::Absolute, self::CYCLES[0x8E]),
            '96' => new OpcodeProps('STX', Addressing::ZeroPageY, self::CYCLES[0x96]),
            '84' => new OpcodeProps('STY', Addressing::ZeroPage, self::CYCLES[0x84]),
            '8C' => new OpcodeProps('STY', Addressing::Absolute, self::CYCLES[0x8C]),
            '94' => new OpcodeProps('STY', Addressing::ZeroPageX, self::CYCLES[0x94]),
            '8A' => new OpcodeProps('TXA', Addressing::Implied, self::CYCLES[0x8A]),
            '98' => new OpcodeProps('TYA', Addressing::Implied, self::CYCLES[0x98]),
            '9A' => new OpcodeProps('TXS', Addressing::Implied, self::CYCLES[0x9A]),
            'A8' => new OpcodeProps('TAY', Addressing::Implied, self::CYCLES[0xA8]),
            'AA' => new OpcodeProps('TAX', Addressing::Implied, self::CYCLES[0xAA]),
            'BA' => new OpcodeProps('TSX', Addressing::Implied, self::CYCLES[0xBA]),
            '8' => new OpcodeProps('PHP', Addressing::Implied, self::CYCLES[0x08]),
            '28' => new OpcodeProps('PLP', Addressing::Implied, self::CYCLES[0x28]),
            '48' => new OpcodeProps('PHA', Addressing::Implied, self::CYCLES[0x48]),
            '68' => new OpcodeProps('PLA', Addressing::Implied, self::CYCLES[0x68]),
            '69' => new OpcodeProps('ADC', Addressing::Immediate, self::CYCLES[0x69]),
            '65' => new OpcodeProps('ADC', Addressing::ZeroPage, self::CYCLES[0x65]),
            '6D' => new OpcodeProps('ADC', Addressing::Absolute, self::CYCLES[0x6D]),
            '75' => new OpcodeProps('ADC', Addressing::ZeroPageX, self::CYCLES[0x75]),
            '7D' => new OpcodeProps('ADC', Addressing::AbsoluteX, self::CYCLES[0x7D]),
            '79' => new OpcodeProps('ADC', Addressing::AbsoluteY, self::CYCLES[0x79]),
            '61' => new OpcodeProps('ADC', Addressing::PreIndexedIndirect, self::CYCLES[0x61]),
            '71' => new OpcodeProps('ADC', Addressing::PostIndexedIndirect, self::CYCLES[0x71]),
            'E9' => new OpcodeProps('SBC', Addressing::Immediate, self::CYCLES[0xE9]),
            'E5' => new OpcodeProps('SBC', Addressing::ZeroPage, self::CYCLES[0xE5]),
            'ED' => new OpcodeProps('SBC', Addressing::Absolute, self::CYCLES[0xED]),
            'F5' => new OpcodeProps('SBC', Addressing::ZeroPageX, self::CYCLES[0xF5]),
            'FD' => new OpcodeProps('SBC', Addressing::AbsoluteX, self::CYCLES[0xFD]),
            'F9' => new OpcodeProps('SBC', Addressing::AbsoluteY, self::CYCLES[0xF9]),
            'E1' => new OpcodeProps('SBC', Addressing::PreIndexedIndirect, self::CYCLES[0xE1]),
            'F1' => new OpcodeProps('SBC', Addressing::PostIndexedIndirect, self::CYCLES[0xF1]),
            'E0' => new OpcodeProps('CPX', Addressing::Immediate, self::CYCLES[0xE0]),
            'E4' => new OpcodeProps('CPX', Addressing::ZeroPage, self::CYCLES[0xE4]),
            'EC' => new OpcodeProps('CPX', Addressing::Absolute, self::CYCLES[0xEC]),
            'C0' => new OpcodeProps('CPY', Addressing::Immediate, self::CYCLES[0xC0]),
            'C4' => new OpcodeProps('CPY', Addressing::ZeroPage, self::CYCLES[0xC4]),
            'CC' => new OpcodeProps('CPY', Addressing::Absolute, self::CYCLES[0xCC]),
            'C9' => new OpcodeProps('CMP', Addressing::Immediate, self::CYCLES[0xC9]),
            'C5' => new OpcodeProps('CMP', Addressing::ZeroPage, self::CYCLES[0xC5]),
            'CD' => new OpcodeProps('CMP', Addressing::Absolute, self::CYCLES[0xCD]),
            'D5' => new OpcodeProps('CMP', Addressing::ZeroPageX, self::CYCLES[0xD5]),
            'DD' => new OpcodeProps('CMP', Addressing::AbsoluteX, self::CYCLES[0xDD]),
            'D9' => new OpcodeProps('CMP', Addressing::AbsoluteY, self::CYCLES[0xD9]),
            'C1' => new OpcodeProps('CMP', Addressing::PreIndexedIndirect, self::CYCLES[0xC1]),
            'D1' => new OpcodeProps('CMP', Addressing::PostIndexedIndirect, self::CYCLES[0xD1]),
            '29' => new OpcodeProps('AND', Addressing::Immediate, self::CYCLES[0x29]),
            '25' => new OpcodeProps('AND', Addressing::ZeroPage, self::CYCLES[0x25]),
            '2D' => new OpcodeProps('AND', Addressing::Absolute, self::CYCLES[0x2D]),
            '35' => new OpcodeProps('AND', Addressing::ZeroPageX, self::CYCLES[0x35]),
            '3D' => new OpcodeProps('AND', Addressing::AbsoluteX, self::CYCLES[0x3D]),
            '39' => new OpcodeProps('AND', Addressing::AbsoluteY, self::CYCLES[0x39]),
            '21' => new OpcodeProps('AND', Addressing::PreIndexedIndirect, self::CYCLES[0x21]),
            '31' => new OpcodeProps('AND', Addressing::PostIndexedIndirect, self::CYCLES[0x31]),
            '49' => new OpcodeProps('EOR', Addressing::Immediate, self::CYCLES[0x49]),
            '45' => new OpcodeProps('EOR', Addressing::ZeroPage, self::CYCLES[0x45]),
            '4D' => new OpcodeProps('EOR', Addressing::Absolute, self::CYCLES[0x4D]),
            '55' => new OpcodeProps('EOR', Addressing::ZeroPageX, self::CYCLES[0x55]),
            '5D' => new OpcodeProps('EOR', Addressing::AbsoluteX, self::CYCLES[0x5D]),
            '59' => new OpcodeProps('EOR', Addressing::AbsoluteY, self::CYCLES[0x59]),
            '41' => new OpcodeProps('EOR', Addressing::PreIndexedIndirect, self::CYCLES[0x41]),
            '51' => new OpcodeProps('EOR', Addressing::PostIndexedIndirect, self::CYCLES[0x51]),
            '9' => new OpcodeProps('ORA', Addressing::Immediate, self::CYCLES[0x09]),
            '5' => new OpcodeProps('ORA', Addressing::ZeroPage, self::CYCLES[0x05]),
            'D' => new OpcodeProps('ORA', Addressing::Absolute, self::CYCLES[0x0D]),
            '15' => new OpcodeProps('ORA', Addressing::ZeroPageX, self::CYCLES[0x15]),
            '1D' => new OpcodeProps('ORA', Addressing::AbsoluteX, self::CYCLES[0x1D]),
            '19' => new OpcodeProps('ORA', Addressing::AbsoluteY, self::CYCLES[0x19]),
            '1' => new OpcodeProps('ORA', Addressing::PreIndexedIndirect, self::CYCLES[0x01]),
            '11' => new OpcodeProps('ORA', Addressing::PostIndexedIndirect, self::CYCLES[0x11]),
            '24' => new OpcodeProps('BIT', Addressing::ZeroPage, self::CYCLES[0x24]),
            '2C' => new OpcodeProps('BIT', Addressing::Absolute, self::CYCLES[0x2C]),
            'A' => new OpcodeProps('ASL', Addressing::Accumulator, self::CYCLES[0x0A]),
            '6' => new OpcodeProps('ASL', Addressing::ZeroPage, self::CYCLES[0x06]),
            'E' => new OpcodeProps('ASL', Addressing::Absolute, self::CYCLES[0x0E]),
            '16' => new OpcodeProps('ASL', Addressing::ZeroPageX, self::CYCLES[0x16]),
            '1E' => new OpcodeProps('ASL', Addressing::AbsoluteX, self::CYCLES[0x1E]),
            '4A' => new OpcodeProps('LSR', Addressing::Accumulator, self::CYCLES[0x4A]),
            '46' => new OpcodeProps('LSR', Addressing::ZeroPage, self::CYCLES[0x46]),
            '4E' => new OpcodeProps('LSR', Addressing::Absolute, self::CYCLES[0x4E]),
            '56' => new OpcodeProps('LSR', Addressing::ZeroPageX, self::CYCLES[0x56]),
            '5E' => new OpcodeProps('LSR', Addressing::AbsoluteX, self::CYCLES[0x5E]),
            '2A' => new OpcodeProps('ROL', Addressing::Accumulator, self::CYCLES[0x2A]),
            '26' => new OpcodeProps('ROL', Addressing::ZeroPage, self::CYCLES[0x26]),
            '2E' => new OpcodeProps('ROL', Addressing::Absolute, self::CYCLES[0x2E]),
            '36' => new OpcodeProps('ROL', Addressing::ZeroPageX, self::CYCLES[0x36]),
            '3E' => new OpcodeProps('ROL', Addressing::AbsoluteX, self::CYCLES[0x3E]),
            '6A' => new OpcodeProps('ROR', Addressing::Accumulator, self::CYCLES[0x6A]),
            '66' => new OpcodeProps('ROR', Addressing::ZeroPage, self::CYCLES[0x66]),
            '6E' => new OpcodeProps('ROR', Addressing::Absolute, self::CYCLES[0x6E]),
            '76' => new OpcodeProps('ROR', Addressing::ZeroPageX, self::CYCLES[0x76]),
            '7E' => new OpcodeProps('ROR', Addressing::AbsoluteX, self::CYCLES[0x7E]),
            'E8' => new OpcodeProps('INX', Addressing::Implied, self::CYCLES[0xE8]),
            'C8' => new OpcodeProps('INY', Addressing::Implied, self::CYCLES[0xC8]),
            'E6' => new OpcodeProps('INC', Addressing::ZeroPage, self::CYCLES[0xE6]),
            'EE' => new OpcodeProps('INC', Addressing::Absolute, self::CYCLES[0xEE]),
            'F6' => new OpcodeProps('INC', Addressing::ZeroPageX, self::CYCLES[0xF6]),
            'FE' => new OpcodeProps('INC', Addressing::AbsoluteX, self::CYCLES[0xFE]),
            'CA' => new OpcodeProps('DEX', Addressing::Implied, self::CYCLES[0xCA]),
            '88' => new OpcodeProps('DEY', Addressing::Implied, self::CYCLES[0x88]),
            'C6' => new OpcodeProps('DEC', Addressing::ZeroPage, self::CYCLES[0xC6]),
            'CE' => new OpcodeProps('DEC', Addressing::Absolute, self::CYCLES[0xCE]),
            'D6' => new OpcodeProps('DEC', Addressing::ZeroPageX, self::CYCLES[0xD6]),
            'DE' => new OpcodeProps('DEC', Addressing::AbsoluteX, self::CYCLES[0xDE]),
            '18' => new OpcodeProps('CLC', Addressing::Implied, self::CYCLES[0x18]),
            '58' => new OpcodeProps('CLI', Addressing::Implied, self::CYCLES[0x58]),
            'B8' => new OpcodeProps('CLV', Addressing::Implied, self::CYCLES[0xB8]),
            '38' => new OpcodeProps('SEC', Addressing::Implied, self::CYCLES[0x38]),
            '78' => new OpcodeProps('SEI', Addressing::Implied, self::CYCLES[0x78]),
            'EA' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0xEA]),
            '0' => new OpcodeProps('BRK', Addressing::Implied, self::CYCLES[0x00]),
            '20' => new OpcodeProps('JSR', Addressing::Absolute, self::CYCLES[0x20]),
            '4C' => new OpcodeProps('JMP', Addressing::Absolute, self::CYCLES[0x4C]),
            '6C' => new OpcodeProps('JMP', Addressing::IndirectAbsolute, self::CYCLES[0x6C]),
            '40' => new OpcodeProps('RTI', Addressing::Implied, self::CYCLES[0x40]),
            '60' => new OpcodeProps('RTS', Addressing::Implied, self::CYCLES[0x60]),
            '10' => new OpcodeProps('BPL', Addressing::Relative, self::CYCLES[0x10]),
            '30' => new OpcodeProps('BMI', Addressing::Relative, self::CYCLES[0x30]),
            '50' => new OpcodeProps('BVC', Addressing::Relative, self::CYCLES[0x50]),
            '70' => new OpcodeProps('BVS', Addressing::Relative, self::CYCLES[0x70]),
            '90' => new OpcodeProps('BCC', Addressing::Relative, self::CYCLES[0x90]),
            'B0' => new OpcodeProps('BCS', Addressing::Relative, self::CYCLES[0xB0]),
            'D0' => new OpcodeProps('BNE', Addressing::Relative, self::CYCLES[0xD0]),
            'F0' => new OpcodeProps('BEQ', Addressing::Relative, self::CYCLES[0xF0]),
            'F8' => new OpcodeProps('SED', Addressing::Implied, self::CYCLES[0xF8]),
            'D8' => new OpcodeProps('CLD', Addressing::Implied, self::CYCLES[0xD8]),
            // unofficial opecode
            // Also see https://wiki.nesdev.com/w/index.php/CPU_unofficial_opcodes
            '1A' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x1A]),
            '3A' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x3A]),
            '5A' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x5A]),
            '7A' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x7A]),
            'DA' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0xDA]),
            'FA' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0xFA]),

            '02' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x02]),
            '12' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x12]),
            '22' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x22]),
            '32' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x32]),
            '42' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x42]),
            '52' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x52]),
            '62' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x62]),
            '72' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x72]),
            '92' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0x92]),
            'B2' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0xB2]),
            'D2' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0xD2]),
            'F2' => new OpcodeProps('NOP', Addressing::Implied, self::CYCLES[0xF2]),

            '80' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x80]),
            '82' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x82]),
            '89' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x89]),
            'C2' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0xC2]),
            'E2' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0xE2]),
            '04' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x04]),
            '44' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x44]),
            '64' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x64]),
            '14' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x14]),
            '34' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x34]),
            '54' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x54]),
            '74' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0x74]),
            'D4' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0xD4]),
            'F4' => new OpcodeProps('NOPD', Addressing::Implied, self::CYCLES[0xF4]),

            '0C' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0x0C]),
            '1C' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0x1C]),
            '3C' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0x3C]),
            '5C' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0x5C]),
            '7C' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0x7C]),
            'DC' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0xDC]),
            'FC' => new OpcodeProps('NOPI', Addressing::Implied, self::CYCLES[0xFC]),
            // LAX
            'A7' => new OpcodeProps('LAX', Addressing::ZeroPage, self::CYCLES[0xA7]),
            'B7' => new OpcodeProps('LAX', Addressing::ZeroPageY, self::CYCLES[0xB7]),
            'AF' => new OpcodeProps('LAX', Addressing::Absolute, self::CYCLES[0xAF]),
            'BF' => new OpcodeProps('LAX', Addressing::AbsoluteY, self::CYCLES[0xBF]),
            'A3' => new OpcodeProps('LAX', Addressing::PreIndexedIndirect, self::CYCLES[0xA3]),
            'B3' => new OpcodeProps('LAX', Addressing::PostIndexedIndirect, self::CYCLES[0xB3]),
            // SAX
            '87' => new OpcodeProps('SAX', Addressing::ZeroPage, self::CYCLES[0x87]),
            '97' => new OpcodeProps('SAX', Addressing::ZeroPageY, self::CYCLES[0x97]),
            '8F' => new OpcodeProps('SAX', Addressing::Absolute, self::CYCLES[0x8F]),
            '83' => new OpcodeProps('SAX', Addressing::PreIndexedIndirect, self::CYCLES[0x83]),
            // SBC
            'EB' => new OpcodeProps('SBC', Addressing::Immediate, self::CYCLES[0xEB]),
            // DCP
            'C7' => new OpcodeProps('DCP', Addressing::ZeroPage, self::CYCLES[0xC7]),
            'D7' => new OpcodeProps('DCP', Addressing::ZeroPageX, self::CYCLES[0xD7]),
            'CF' => new OpcodeProps('DCP', Addressing::Absolute, self::CYCLES[0xCF]),
            'DF' => new OpcodeProps('DCP', Addressing::AbsoluteX, self::CYCLES[0xDF]),
            'DB' => new OpcodeProps('DCP', Addressing::AbsoluteY, self::CYCLES[0xD8]),
            'C3' => new OpcodeProps('DCP', Addressing::PreIndexedIndirect, self::CYCLES[0xC3]),
            'D3' => new OpcodeProps('DCP', Addressing::PostIndexedIndirect, self::CYCLES[0xD3]),
            // ISB
            'E7' => new OpcodeProps('ISB', Addressing::ZeroPage, self::CYCLES[0xE7]),
            'F7' => new OpcodeProps('ISB', Addressing::ZeroPageX, self::CYCLES[0xF7]),
            'EF' => new OpcodeProps('ISB', Addressing::Absolute, self::CYCLES[0xEF]),
            'FF' => new OpcodeProps('ISB', Addressing::AbsoluteX, self::CYCLES[0xFF]),
            'FB' => new OpcodeProps('ISB', Addressing::AbsoluteY, self::CYCLES[0xF8]),
            'E3' => new OpcodeProps('ISB', Addressing::PreIndexedIndirect, self::CYCLES[0xE3]),
            'F3' => new OpcodeProps('ISB', Addressing::PostIndexedIndirect, self::CYCLES[0xF3]),
            // SLO
            '07' => new OpcodeProps('SLO', Addressing::ZeroPage, self::CYCLES[0x07]),
            '17' => new OpcodeProps('SLO', Addressing::ZeroPageX, self::CYCLES[0x17]),
            '0F' => new OpcodeProps('SLO', Addressing::Absolute, self::CYCLES[0x0F]),
            '1F' => new OpcodeProps('SLO', Addressing::AbsoluteX, self::CYCLES[0x1F]),
            '1B' => new OpcodeProps('SLO', Addressing::AbsoluteY, self::CYCLES[0x1B]),
            '03' => new OpcodeProps('SLO', Addressing::PreIndexedIndirect, self::CYCLES[0x03]),
            '13' => new OpcodeProps('SLO', Addressing::PostIndexedIndirect, self::CYCLES[0x13]),
            // RLA
            '27' => new OpcodeProps('RLA', Addressing::ZeroPage, self::CYCLES[0x27]),
            '37' => new OpcodeProps('RLA', Addressing::ZeroPageX, self::CYCLES[0x37]),
            '2F' => new OpcodeProps('RLA', Addressing::Absolute, self::CYCLES[0x2F]),
            '3F' => new OpcodeProps('RLA', Addressing::AbsoluteX, self::CYCLES[0x3F]),
            '3B' => new OpcodeProps('RLA', Addressing::AbsoluteY, self::CYCLES[0x3B]),
            '23' => new OpcodeProps('RLA', Addressing::PreIndexedIndirect, self::CYCLES[0x23]),
            '33' => new OpcodeProps('RLA', Addressing::PostIndexedIndirect, self::CYCLES[0x33]),
            // SRE
            '47' => new OpcodeProps('SRE', Addressing::ZeroPage, self::CYCLES[0x47]),
            '57' => new OpcodeProps('SRE', Addressing::ZeroPageX, self::CYCLES[0x57]),
            '4F' => new OpcodeProps('SRE', Addressing::Absolute, self::CYCLES[0x4F]),
            '5F' => new OpcodeProps('SRE', Addressing::AbsoluteX, self::CYCLES[0x5F]),
            '5B' => new OpcodeProps('SRE', Addressing::AbsoluteY, self::CYCLES[0x5B]),
            '43' => new OpcodeProps('SRE', Addressing::PreIndexedIndirect, self::CYCLES[0x43]),
            '53' => new OpcodeProps('SRE', Addressing::PostIndexedIndirect, self::CYCLES[0x53]),
            // RRA
            '67' => new OpcodeProps('RRA', Addressing::ZeroPage, self::CYCLES[0x67]),
            '77' => new OpcodeProps('RRA', Addressing::ZeroPageX, self::CYCLES[0x77]),
            '6F' => new OpcodeProps('RRA', Addressing::Absolute, self::CYCLES[0x6F]),
            '7F' => new OpcodeProps('RRA', Addressing::AbsoluteX, self::CYCLES[0x7F]),
            '7B' => new OpcodeProps('RRA', Addressing::AbsoluteY, self::CYCLES[0x7B]),
            '63' => new OpcodeProps('RRA', Addressing::PreIndexedIndirect, self::CYCLES[0x63]),
            '73' => new OpcodeProps('RRA', Addressing::PostIndexedIndirect, self::CYCLES[0x73]),
        ];
    }
}
