<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use InvalidArgumentException;

class HardwareTestRom
{
    /**
     * Builds an original NROM test program with deterministic reset and interrupt vectors.
     *
     * @param array<int, int> $program
     */
    public static function contents(array $program): string
    {
        if (! array_is_list($program) || count($program) > 0x3FFA
            || array_any($program, static fn(int $byte): bool => $byte < 0 || $byte > 0xFF)) {
            throw new InvalidArgumentException('The test program must be a byte list ending before the interrupt vectors.');
        }

        $rom = array_fill(0, 0x4000, 0xEA);

        foreach ($program as $offset => $byte) {
            $rom[$offset] = $byte;
        }

        foreach ([0x3FFA, 0x3FFC, 0x3FFE] as $offset) {
            $rom[$offset] = 0x00;
            $rom[$offset + 1] = 0x80;
        }

        return "NES\x1A\x01\x00" . str_repeat("\x00", 10) . implode('', array_map(chr(...), $rom));
    }

    /**
     * Writes an original test cartridge to a uniquely named temporary file.
     *
     * @param list<int> $program
     */
    public static function write(array $program): string
    {
        $contents = self::contents($program);
        $path = tempnam(sys_get_temp_dir(), 'nes-hardware-');

        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write hardware test ROM.');
        }

        return $path;
    }
}
