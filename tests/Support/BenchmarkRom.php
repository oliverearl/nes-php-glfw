<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;
use RuntimeException;

class BenchmarkRom
{
    /**
     * Workload exercising arithmetic, branches, stack operations, and indexed RAM.
     */
    public const string CPU = 'cpu';

    /**
     * Workload exercising backgrounds, sprites, scrolling, DMA, NMI, and input reads.
     */
    public const string GRAPHICS = 'graphics';

    /**
     * Generates original deterministic NROM content for a named benchmark workload.
     *
     * Both workloads run with no pressed buttons when attached to NullController.
     *
     * @throws InvalidArgumentException
     */
    public static function contents(string $workload): string
    {
        $instructions = match ($workload) {
            self::CPU => self::cpuProgram(),
            self::GRAPHICS => self::graphicsProgram(),
            default => throw new InvalidArgumentException("Unknown benchmark workload: {$workload}."),
        };
        $header = "NES\x1A\x01\x01" . str_repeat("\x00", 10);
        $character = array_fill(0, 0x2000, 0);

        if ($workload === self::GRAPHICS) {
            for ($tile = 0; $tile < 512; $tile++) {
                for ($row = 0; $row < 8; $row++) {
                    $character[$tile * 16 + $row] = (($tile + $row) & 1) === 0 ? 0xAA : 0x55;
                    $character[$tile * 16 + $row + 8] = ($tile & 2) === 0 ? 0x80 >> $row : 0xFF ^ (0x80 >> $row);
                }
            }
        }

        return $header . pack('C*', ...self::assemble($instructions)) . pack('C*', ...$character);
    }

    /**
     * Writes reproducible fixtures to an explicit destination directory.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException
     */
    public static function writeToDirectory(string $directory): array
    {
        if ($directory === '') {
            throw new RuntimeException('A fixture destination directory is required.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create fixture directory: {$directory}.");
        }

        $paths = [];

        foreach ([self::CPU, self::GRAPHICS] as $workload) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $workload . '.nes';

            if (file_put_contents($path, self::contents($workload)) === false) {
                throw new RuntimeException("Unable to write benchmark fixture: {$path}.");
            }

            $paths[$workload] = $path;
        }

        return $paths;
    }

    /**
     * Defines a varied CPU loop without enabling display rendering.
     *
     * @return list<int|string|array{int, string}>
     */
    private static function cpuProgram(): array
    {
        return [
            'reset',
            0x78, 0xD8, 0xA2, 0xFF, 0x9A,
            0xA9, 0x31, 0x85, 0x00, 0xA2, 0x00,
            'work',
            0x8A, 0x18, 0x65, 0x00, 0x49, 0x5A,
            0x48, 0x68, 0x9D, 0x00, 0x02,
            0xE6, 0x00, 0xE8, [0xD0, 'work'],
            0xA0, 0x40,
            'delay',
            0x88, [0xD0, 'delay'],
            [0x4C, 'work'],
            'nmi', 'irq', 0x40,
        ];
    }

    /**
     * Defines a visible scene updated once per NMI while the CPU keeps working.
     *
     * @return list<int|string|array{int, string}>
     */
    private static function graphicsProgram(): array
    {
        return [
            'reset',
            0x78, 0xD8, 0xA2, 0xFF, 0x9A,
            0xA9, 0x00, 0x8D, 0x00, 0x20, 0x8D, 0x01, 0x20, 0x85, 0x00, 0x85, 0x01,

            // Populate all 32 palette entries with valid, nonzero colors.
            0xA9, 0x3F, 0x8D, 0x06, 0x20, 0xA9, 0x00, 0x8D, 0x06, 0x20, 0xA2, 0x00,
            'palette',
            0x8A, 0x09, 0x01, 0x8D, 0x07, 0x20, 0xE8, 0xE0, 0x20, [0xD0, 'palette'],

            // Fill a nametable and its attributes with repeating patterned tiles.
            0xA9, 0x20, 0x8D, 0x06, 0x20, 0xA9, 0x00, 0x8D, 0x06, 0x20, 0xA0, 0x04, 0xA2, 0x00,
            'nametable',
            0x8A, 0x29, 0x03, 0x8D, 0x07, 0x20, 0xE8, [0xD0, 'nametable'], 0x88, [0xD0, 'nametable'],

            // Populate all 64 sprite records, including flip and priority attributes.
            0xA2, 0x00,
            'sprites',
            0x8A, 0x29, 0x7F, 0x18, 0x69, 0x10, 0x9D, 0x00, 0x02,
            0xA9, 0x02, 0x9D, 0x01, 0x02,
            0x8A, 0x49, 0xC3, 0x9D, 0x02, 0x02,
            0x8A, 0x9D, 0x03, 0x02,
            0xE8, 0xE8, 0xE8, 0xE8, [0xD0, 'sprites'],
            0xA9, 0x00, 0x8D, 0x03, 0x20, 0xA9, 0x02, 0x8D, 0x14, 0x40,
            0xA9, 0x80, 0x8D, 0x00, 0x20, 0xA9, 0x1E, 0x8D, 0x01, 0x20,
            'work',
            0xE6, 0x10, 0xA5, 0x10, 0x49, 0x5A, 0x85, 0x11, [0x4C, 'work'],

            // Update scroll and sprite position, transfer OAM, and read eight input bits.
            'nmi',
            0x48, 0x8A, 0x48, 0x98, 0x48,
            0xE6, 0x00, 0xA5, 0x00, 0x8D, 0x05, 0x20, 0x8D, 0x03, 0x02,
            0xA9, 0x00, 0x8D, 0x05, 0x20, 0x8D, 0x03, 0x20,
            0xA9, 0x02, 0x8D, 0x14, 0x40,
            0xA9, 0x01, 0x8D, 0x16, 0x40, 0xA9, 0x00, 0x8D, 0x16, 0x40, 0xA2, 0x08,
            'input',
            0xAD, 0x16, 0x40, 0x4A, 0x26, 0x01, 0xCA, [0xD0, 'input'],
            0x68, 0xA8, 0x68, 0xAA, 0x68, 0x40,
            'irq', 0x40,
        ];
    }

    /**
     * Resolves labels for the fixture's JMP/BNE instructions and interrupt vectors.
     *
     * @param list<int|string|array{int, string}> $instructions
     * @return list<int>
     */
    private static function assemble(array $instructions): array
    {
        $program = [];
        $labels = [];
        $patches = [];

        foreach ($instructions as $instruction) {
            if (is_string($instruction)) {
                $labels[$instruction] = count($program);
            } elseif (is_array($instruction)) {
                [$opcode, $label] = $instruction;
                $program[] = $opcode;
                $patches[] = [count($program), $opcode, $label];
                $program[] = 0;

                if ($opcode === 0x4C) {
                    $program[] = 0;
                }
            } else {
                $program[] = $instruction;
            }
        }

        foreach ($patches as [$offset, $opcode, $label]) {
            if ($opcode === 0x4C) {
                $address = 0x8000 + $labels[$label];
                $program[$offset] = $address & 0xFF;
                $program[$offset + 1] = $address >> 8;
            } else {
                $distance = $labels[$label] - $offset - 1;

                if ($distance < -128 || $distance > 127) {
                    throw new RuntimeException("Benchmark branch exceeds its byte range: {$label}.");
                }

                $program[$offset] = $distance & 0xFF;
            }
        }

        $program = array_pad($program, 0x4000, 0xEA);

        foreach (['nmi', 'reset', 'irq'] as $index => $label) {
            $address = 0x8000 + $labels[$label];
            $program[0x3FFA + $index * 2] = $address & 0xFF;
            $program[0x3FFB + $index * 2] = $address >> 8;
        }

        return $program;
    }
}
