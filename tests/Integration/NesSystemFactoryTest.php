<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Cartridge\Loader;
use App\Cartridge\Cartridge;
use App\Cartridge\UnsupportedCartridgeException;
use App\Cpu\Cpu;
use App\Emulation\NesSystem;
use App\Emulation\NesSystemFactory;
use App\Graphics\Ppu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\SyntheticNromRom;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NesSystemFactory::class)]
#[CoversClass(NesSystem::class)]
final class NesSystemFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_a_reset_headless_system_from_a_cartridge(): void
    {
        $romPath = SyntheticNromRom::writeToTemporaryFile();

        try {
            $cartridge = new Loader($romPath, debugEnabled: false)->load();
            $system = new NesSystemFactory()->create($cartridge);

            $this::assertInstanceOf(Cpu::class, $system->cpu);
            $this::assertInstanceOf(Ppu::class, $system->ppu);
            $this::assertSame(0x8000, $system->cpu->snapshot()->pc);
            $this::assertSame(2, $system->cpu->run());
            $this::assertSame(0x8001, $system->cpu->snapshot()->pc);
        } finally {
            unlink($romPath);
        }
    }

    /**
     * Rejects cartridge configurations that cannot be represented by the NROM wiring.
     */
    #[Test]
    #[DataProvider('unsupportedCartridges')]
    public function it_rejects_unsupported_cartridges(Cartridge $cartridge): void
    {
        $this->expectException(UnsupportedCartridgeException::class);
        new NesSystemFactory()->create($cartridge);
    }

    /**
     * Supplies invalid direct construction paths independent of file-header validation.
     *
     * @return iterable<string, array{Cartridge}>
     */
    public static function unsupportedCartridges(): iterable
    {
        yield 'mapper' => [new Cartridge(true, array_fill(0, 0x4000, 0), [], mapper: 1)];
        yield 'program size' => [new Cartridge(true, [0], [])];
        yield 'character banks' => [new Cartridge(true, array_fill(0, 0x4000, 0), array_fill(0, 0x4000, 0))];
        yield 'unsupported RAM size' => [new Cartridge(true, array_fill(0, 0x4000, 0), [], programRamSize: 0x4000)];
        yield 'trainer without RAM' => [new Cartridge(true, array_fill(0, 0x4000, 0), [], trainer: array_fill(0, 512, 0))];
        yield 'short trainer' => [new Cartridge(true, array_fill(0, 0x4000, 0), [], programRamSize: 0x2000, trainer: [0])];
        yield 'oversized trainer' => [new Cartridge(true, array_fill(0, 0x4000, 0), [], programRamSize: 0x2000, trainer: array_fill(0, 513, 0))];
    }
}
