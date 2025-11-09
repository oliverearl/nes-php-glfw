<?php

declare(strict_types=1);

namespace App\Cpu\Objects;

class Registers
{
    public function __construct(
        public int $a,
        public int $x,
        public int $y,
        public Status $p,
        public int $sp,
        public int $pc
    ) {}

    /**
     * Create an instance with default register values.
     */
    public static function getDefault(): self
    {
        return new self(
            a: 0x00,
            x: 0x00,
            y: 0x00,
            p: new Status(
                negative: false,
                overflow: false,
                reserved: true,
                breakMode: true,
                decimalMode: false,
                interrupt: true,
                zero: false,
                carry: false,
            ),
            sp: 0x01fd,
            pc: 0x0000,
        );
    }
}
