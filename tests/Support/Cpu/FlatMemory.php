<?php

declare(strict_types=1);

namespace Tests\Support\Cpu;

use App\Cpu\Memory;
use OutOfBoundsException;

final class FlatMemory implements Memory
{
    /**
     * Explicitly initialized byte values in a flat 64 KiB address space.
     *
     * @var array<int, int>
     */
    private array $bytes = [];

    /**
     * Writes observed during instruction execution, including repeated writes.
     *
     * @var list<array{int, int}>
     */
    public array $writes = [];

    /**
     * Initializes sparse memory without recording setup as CPU writes.
     *
     * @param list<array{int, int}> $bytes
     */
    public function __construct(array $bytes = [])
    {
        foreach ($bytes as [$address, $value]) {
            $this->bytes[$address] = $value;
        }
    }

    /**
     * Reads a byte, rejecting addresses that escaped CPU wrapping.
     */
    public function readByCpu(int $address): int
    {
        if ($address < 0 || $address > 0xFFFF) {
            throw new OutOfBoundsException('CPU read address is outside 64 KiB.');
        }

        return $this->bytes[$address] ?? 0;
    }

    /**
     * Writes a byte, rejecting addresses and values that escaped CPU wrapping.
     */
    public function writeByCpu(int $address, int $data): void
    {
        if ($address < 0 || $address > 0xFFFF || $data < 0 || $data > 0xFF) {
            throw new OutOfBoundsException('CPU write is outside its address or byte range.');
        }

        $this->bytes[$address] = $data;
        $this->writes[] = [$address, $data];
    }
}
