<?php

declare(strict_types=1);

namespace App\Cpu\Enums;

enum Addressing
{
    case Immediate;
    case ZeroPage;
    case Relative;
    case Implied;
    case Absolute;
    case Accumulator;
    case ZeroPageX;
    case ZeroPageY;
    case AbsoluteX;
    case AbsoluteY;
    case PreIndexedIndirect;
    case PostIndexedIndirect;
    case IndirectAbsolute;
}
