<?php

declare(strict_types=1);

namespace Tests\Support;

readonly class TestRomResult
{
    /**
     * Records a test-ROM outcome with diagnostic text and elapsed emulated time.
     */
    public function __construct(
        /** Protocol outcome, including non-passing unsupported and incomplete runs. */
        public TestRomStatus $status,
        /** Result byte reported by the ROM, when valid. */
        public ?int $code,
        /** Diagnostic text or the reason execution could not complete. */
        public string $output,
        /** CPU clocks consumed before the outcome. */
        public int $cpuCycles,
    ) {}
}
