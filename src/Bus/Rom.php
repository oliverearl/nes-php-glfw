<?php

declare(strict_types=1);

namespace App\Bus;

use RuntimeException;

readonly class Rom
{
    /**
     * Creates a new ROM instance.
     *
     * @param list<int> $data
     */
    public function __construct(public array $data) {}

    /**
     * Gets the size of the ROM in bytes.
     */
    public function size(): int
    {
        return count($this->data);
    }

    /**
     * Reads a byte from the ROM at the specified address.
     *
     * @throws \RuntimeException
     */
    public function read(int $addr): int
    {
        if (! isset($this->data[$addr])) {
            throw new RuntimeException(sprintf(
                'Invalid address on rom read. Address: 0x%s Rom: 0x0000 - 0x%s',
                dechex($addr),
                dechex($this->size()),
            ));
        }

        return $this->data[$addr];
    }
}
