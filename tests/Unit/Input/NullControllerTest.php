<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use App\Input\NullController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullController::class)]
final class NullControllerTest extends TestCase
{
    #[Test]
    public function it_always_reads_inactive_input(): void
    {
        $controller = new NullController();

        $this::assertFalse($controller->read());
    }

    #[Test]
    public function it_ignores_writes(): void
    {
        $controller = new NullController();

        $controller->write(1);
        $controller->write(0);

        $this::assertFalse($controller->read());
    }
}
