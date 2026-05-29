<?php

declare(strict_types=1);

namespace App\Input;

class NullController implements Controller
{
    /**
     * Reads an inactive controller state.
     */
    public function read(): bool
    {
        return false;
    }

    /**
     * Ignores controller strobe writes.
     */
    public function write(int $data): void
    {
        // No input state is latched for headless runs.
    }
}
