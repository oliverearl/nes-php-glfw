<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

class SyntheticNromRom
{
    /**
     * Writes a deterministic NROM ROM to a temporary file.
     *
     * @throws RuntimeException
     */
    public static function writeToTemporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nes-benchmark-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary ROM path.');
        }

        if (file_put_contents($path, self::contents()) === false) {
            throw new RuntimeException("Unable to write temporary ROM: {$path}.");
        }

        return $path;
    }

    /**
     * Creates deterministic iNES ROM contents for benchmark tests.
     */
    public static function contents(): string
    {
        $header = self::bytes([
            0x4E, 0x45, 0x53, 0x1A,
            0x01,
            0x01,
            0x00,
            0x00,
            0x00, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00,
        ]);

        $program = array_fill(0, 0x4000, 0xEA);
        $program[0x0000] = 0x78;
        $program[0x0001] = 0xD8;
        $program[0x0002] = 0xA2;
        $program[0x0003] = 0xFF;
        $program[0x0004] = 0x9A;
        $program[0x0005] = 0xEA;
        $program[0x0006] = 0x4C;
        $program[0x0007] = 0x05;
        $program[0x0008] = 0x80;

        $program[0x3FFA] = 0x00;
        $program[0x3FFB] = 0x80;
        $program[0x3FFC] = 0x00;
        $program[0x3FFD] = 0x80;
        $program[0x3FFE] = 0x00;
        $program[0x3FFF] = 0x80;

        $character = array_fill(0, 0x2000, 0x00);

        return $header . self::bytes($program) . self::bytes($character);
    }

    /**
     * Converts byte values to a binary string.
     *
     * @param list<int> $bytes
     */
    private static function bytes(array $bytes): string
    {
        $contents = '';

        foreach ($bytes as $byte) {
            $contents .= chr($byte);
        }

        return $contents;
    }
}
