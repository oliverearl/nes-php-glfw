<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Cartridge\Cartridge;
use App\Emulation\NesSystemFactory;
use PHPUnit\Framework\Attributes\Test;

final class CartridgeRamTest extends IntegrationTestCase
{
    /**
     * Checks that the emulator maps cartridge ram separately from internal ram and rom.
     */
    #[Test]
    public function it_maps_cartridge_ram_separately_from_internal_ram_and_rom(): void
    {
        $cartridge = new Cartridge(true, array_fill(0, 0x4000, 0xEA), [], programRamSize: 0x2000);
        $system = new NesSystemFactory()->create($cartridge);

        $system->cpuBus->writeByCpu(0x6000, 0xDE);
        $system->cpuBus->writeByCpu(0x7FFF, 0x61);
        $system->cpuBus->writeByCpu(0x8000, 0x00);

        $this::assertSame(0xDE, $system->cpuBus->readByCpu(0x6000));
        $this::assertSame(0x61, $system->cpuBus->readByCpu(0x7FFF));
        $this::assertSame(0, $system->ram->read(0));
        $this::assertSame(0xEA, $system->cpuBus->readByCpu(0x8000));
    }

    /**
     * Checks that the emulator keeps absent cartridge ram unmapped.
     */
    #[Test]
    public function it_keeps_absent_cartridge_ram_unmapped(): void
    {
        $system = new NesSystemFactory()->create(new Cartridge(true, array_fill(0, 0x4000, 0xEA), []));
        $system->cpuBus->writeByCpu(0x6000, 0xDE);

        $this::assertSame(0, $system->cpuBus->readByCpu(0x6000));
    }

    /**
     * Checks that the emulator places a trainer at 7000 without overwriting the program.
     */
    #[Test]
    public function it_places_a_trainer_at_7000_without_overwriting_the_program(): void
    {
        $cartridge = new Cartridge(
            true,
            array_fill(0, 0x4000, 0xEA),
            [],
            programRamSize: 0x2000,
            trainer: array_fill(0, 512, 0xAB),
        );
        $system = new NesSystemFactory()->create($cartridge);

        $this::assertSame(0, $system->cpuBus->readByCpu(0x6FFF));
        $this::assertSame(0xAB, $system->cpuBus->readByCpu(0x7000));
        $this::assertSame(0xAB, $system->cpuBus->readByCpu(0x71FF));
        $this::assertSame(0, $system->cpuBus->readByCpu(0x7200));
        $this::assertSame(0xEA, $system->cpuBus->readByCpu(0x8000));
    }
}
