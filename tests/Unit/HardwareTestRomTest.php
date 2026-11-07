<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\HardwareTestRom;

#[CoversClass(HardwareTestRom::class)]
final class HardwareTestRomTest extends TestCase
{
    /**
     * Keeps the cartridge header, payload size, and vectors deterministic at the maximum program size.
     */
    #[Test]
    public function it_builds_a_complete_nrom_payload_without_overwriting_vectors(): void
    {
        $contents = HardwareTestRom::contents(array_fill(0, 0x3FFA, 0x42));

        $this::assertSame(16 + 0x4000, strlen($contents));
        $this::assertSame("NES\x1A\x01\x00", substr($contents, 0, 6));
        $this::assertSame(str_repeat("\x42", 0x3FFA), substr($contents, 16, 0x3FFA));
        $this::assertSame("\x00\x80\x00\x80\x00\x80", substr($contents, -6));
    }

    /**
     * Rejects ambiguous or out-of-range programs before generating a misleading cartridge.
     *
     * @param array<int, int> $program
     */
    #[Test]
    #[DataProvider('invalidPrograms')]
    public function it_rejects_invalid_programs(array $program): void
    {
        $this->expectException(InvalidArgumentException::class);
        HardwareTestRom::contents($program);
    }

    /**
     * Supplies vector overlaps, invalid byte values, and sparse indexing.
     *
     * @return iterable<string, array{array<int, int>}>
     */
    public static function invalidPrograms(): iterable
    {
        yield 'vector overlap' => [array_fill(0, 0x3FFB, 0)];
        yield 'negative byte' => [[-1]];
        yield 'ninth bit' => [[256]];
        yield 'sparse indexes' => [[1 => 0xEA]];
    }
}
