<?php

declare(strict_types=1);

namespace App\Input;

interface Controller
{
    /**
     * Reads the next serial controller bit.
     */
    public function read(): bool;

    /**
     * Writes to the controller strobe register.
     */
    public function write(int $data): void;
}
