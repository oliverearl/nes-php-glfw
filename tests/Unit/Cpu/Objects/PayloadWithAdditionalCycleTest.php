<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Objects;

use App\Cpu\Objects\PayloadWithAdditionalCycle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(PayloadWithAdditionalCycle::class)]
final class PayloadWithAdditionalCycleTest extends TestCase
{
    #[Test]
    public function it_creates_with_payload_and_no_additional_cycle(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x1234,
            additionalCycle: 0,
        );

        $this::assertSame(0x1234, $payload->payload);
        $this::assertSame(0, $payload->additionalCycle);
    }

    #[Test]
    public function it_creates_with_payload_and_additional_cycle(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0xABCD,
            additionalCycle: 1,
        );

        $this::assertSame(0xABCD, $payload->payload);
        $this::assertSame(1, $payload->additionalCycle);
    }

    #[Test]
    public function it_handles_zero_payload(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x0000,
            additionalCycle: 0,
        );

        $this::assertSame(0x0000, $payload->payload);
        $this::assertSame(0, $payload->additionalCycle);
    }

    #[Test]
    public function it_handles_maximum_16bit_payload(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0xFFFF,
            additionalCycle: 1,
        );

        $this::assertSame(0xFFFF, $payload->payload);
        $this::assertSame(1, $payload->additionalCycle);
    }

    #[Test]
    public function it_handles_8bit_payload(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0xFF,
            additionalCycle: 0,
        );

        $this::assertSame(0xFF, $payload->payload);
        $this::assertSame(0, $payload->additionalCycle);
    }

    #[Test]
    public function it_handles_page_boundary_crossing_cycle(): void
    {
        // Page boundary crossing adds 1 cycle.
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x10FF,
            additionalCycle: 1,
        );

        $this::assertSame(0x10FF, $payload->payload);
        $this::assertSame(1, $payload->additionalCycle);
    }

    #[Test]
    public function it_handles_no_page_boundary_crossing(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x1050,
            additionalCycle: 0,
        );

        $this::assertSame(0x1050, $payload->payload);
        $this::assertSame(0, $payload->additionalCycle);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x1234,
            additionalCycle: 1,
        );

        $this::assertTrue(new ReflectionClass($payload)->isReadOnly());
    }

    #[Test]
    public function it_handles_various_payloads(): void
    {
        $testCases = [
            ['payload' => 0x0000, 'cycle' => 0],
            ['payload' => 0x00FF, 'cycle' => 0],
            ['payload' => 0x0100, 'cycle' => 1],
            ['payload' => 0x1234, 'cycle' => 0],
            ['payload' => 0x8000, 'cycle' => 1],
            ['payload' => 0xFFFF, 'cycle' => 1],
        ];

        foreach ($testCases as $testCase) {
            $payload = new PayloadWithAdditionalCycle(
                payload: $testCase['payload'],
                additionalCycle: $testCase['cycle'],
            );

            $this::assertSame($testCase['payload'], $payload->payload);
            $this::assertSame($testCase['cycle'], $payload->additionalCycle);
        }
    }

    #[Test]
    public function it_stores_immediate_addressing_payload(): void
    {
        // Immediate addressing mode - payload is the value itself.
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x42,
            additionalCycle: 0,
        );

        $this::assertSame(0x42, $payload->payload);
        $this::assertSame(0, $payload->additionalCycle);
    }

    #[Test]
    public function it_stores_absolute_addressing_payload(): void
    {
        // Absolute addressing mode - payload is a 16-bit address.
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x8000,
            additionalCycle: 0,
        );

        $this::assertSame(0x8000, $payload->payload);
        $this::assertSame(0, $payload->additionalCycle);
    }

    #[Test]
    public function it_stores_indexed_addressing_payload_with_crossing(): void
    {
        // Indexed addressing that crosses page boundary.
        $payload = new PayloadWithAdditionalCycle(
            payload: 0x20FF,
            additionalCycle: 1,
        );

        $this::assertSame(0x20FF, $payload->payload);
        $this::assertSame(1, $payload->additionalCycle);
    }
}
