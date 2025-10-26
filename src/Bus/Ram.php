<?php

declare(strict_types=1);

namespace App\Bus;

class Ram
{
    /**
     * The underlying RAM storage.
     *
     * @var list<int>
     */
    private array $ram;

    /**
     * Creates a new Ram instance.
     */
    public function __construct(int $size = 2048)
    {
        $this->ram = array_fill(0, $size, 0);
    }

    /**
     * Clears the RAM by setting all bytes to zero.
     */
    public function reset(): void
    {
        $this->ram = array_fill(0, count($this->ram), 0);
    }

    /**
     * Reads a byte from the specified address.
     */
    public function read(int $addr): int
    {
        return $this->ram[$addr];
    }

    /**
     * Writes a byte to the specified address.
     */
    public function write(int $addr, int $val): void
    {
        $this->ram[$addr] = $val;
    }
}
