<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Cartridge\Loader;
use App\Cpu\Cpu;
use App\Emulation\NesSystem;
use App\Emulation\NesSystemFactory;
use App\Graphics\Ppu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\SyntheticNromRom;

#[CoversClass(NesSystemFactory::class)]
#[CoversClass(NesSystem::class)]
final class NesSystemFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_reset_headless_system_from_a_cartridge(): void
    {
        $romPath = SyntheticNromRom::writeToTemporaryFile();

        try {
            $cartridge = (new Loader($romPath, debugEnabled: false))->load();
            $system = (new NesSystemFactory())->create($cartridge);

            $this::assertInstanceOf(Cpu::class, $system->cpu);
            $this::assertInstanceOf(Ppu::class, $system->ppu);
            $this::assertGreaterThan(0, $system->cpu->run());
        } finally {
            unlink($romPath);
        }
    }
}
