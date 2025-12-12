<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use App\Bus\CpuBus;
use App\Bus\Ram;
use App\Bus\Rom;
use App\Cpu\Dma;
use App\Graphics\Ppu;
use App\Input\Gamepad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuBus::class)]
final class CpuBusTest extends TestCase
{
    #[Test]
    public function it_reads_from_ram(): void
    {
        $ram = new Ram(0x800);
        $ram->write(0x0000, 0x42);
        $ram->write(0x0100, 0xAA);
        $ram->write(0x07FF, 0xFF);

        $bus = $this->createBus($ram);

        $this::assertSame(0x42, $bus->readByCpu(0x0000));
        $this::assertSame(0xAA, $bus->readByCpu(0x0100));
        $this::assertSame(0xFF, $bus->readByCpu(0x07FF));
    }

    #[Test]
    public function it_mirrors_ram_correctly(): void
    {
        $ram = new Ram(0x800);
        $ram->write(0x0042, 0x55);

        $bus = $this->createBus($ram);

        // RAM mirrors at 0x0800, 0x1000, 0x1800.
        $this::assertSame(0x55, $bus->readByCpu(0x0042));
        $this::assertSame(0x55, $bus->readByCpu(0x0842));
        $this::assertSame(0x55, $bus->readByCpu(0x1042));
        $this::assertSame(0x55, $bus->readByCpu(0x1842));
    }

    #[Test]
    public function it_writes_to_ram(): void
    {
        $ram = new Ram(0x800);
        $bus = $this->createBus($ram);

        $bus->writeByCpu(0x0200, 0x33);
        $this::assertSame(0x33, $ram->read(0x0200));
    }

    #[Test]
    public function it_writes_to_mirrored_ram(): void
    {
        $ram = new Ram(0x800);
        $bus = $this->createBus($ram);

        $bus->writeByCpu(0x0842, 0x77);
        // Should write to 0x0042 (mirrored).
        $this::assertSame(0x77, $ram->read(0x0042));
    }

    #[Test]
    public function it_reads_from_ppu_registers(): void
    {
        $ppu = $this->createMock(Ppu::class);
        $ppu->expects($this->once())
            ->method('read')
            ->with(0x02) // 0x2002 - 0x2000 = 0x02
            ->willReturn(0x80);

        $bus = $this->createBus(ppu: $ppu);

        $this::assertSame(0x80, $bus->readByCpu(0x2002));
    }

    #[Test]
    public function it_mirrors_ppu_registers(): void
    {
        $ppu = $this->createMock(Ppu::class);
        $ppu->expects($this->exactly(3))
            ->method('read')
            ->with(0x02)
            ->willReturn(0x90);

        $bus = $this->createBus(ppu: $ppu);

        // PPU registers mirror every 8 bytes.
        $this::assertSame(0x90, $bus->readByCpu(0x2002));
        $this::assertSame(0x90, $bus->readByCpu(0x200A));
        $this::assertSame(0x90, $bus->readByCpu(0x3FFA));
    }

    #[Test]
    public function it_writes_to_ppu_registers(): void
    {
        $ppu = $this->createMock(Ppu::class);
        $ppu->expects($this->once())
            ->method('write')
            ->with(0x00, 0x80);

        $bus = $this->createBus(ppu: $ppu);

        $bus->writeByCpu(0x2000, 0x80);
    }

    #[Test]
    public function it_reads_from_gamepad(): void
    {
        $gamepad = $this->createMock(Gamepad::class);
        $gamepad
            ->expects($this->once())
            ->method('read')
            ->willReturn(true);

        $bus = $this->createBus(gamepad: $gamepad);

        $this::assertSame(1, $bus->readByCpu(0x4016));
    }

    #[Test]
    public function it_writes_to_gamepad(): void
    {
        $gamepad = $this->createMock(Gamepad::class);
        $gamepad
            ->expects($this->once())
            ->method('write')
            ->with(0x01);

        $bus = $this->createBus(gamepad: $gamepad);

        $bus->writeByCpu(0x4016, 0x01);
    }

    #[Test]
    public function it_writes_to_dma_register(): void
    {
        $dma = $this->createMock(Dma::class);
        $dma->expects($this->once())
            ->method('write')
            ->with(0x02);

        $bus = $this->createBus(dma: $dma);

        $bus->writeByCpu(0x4014, 0x02);
    }

    #[Test]
    public function it_reads_from_program_rom_lower_bank(): void
    {
        $programRom = new Rom([
            0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07,
        ]);

        $bus = $this->createBus(programRom: $programRom);

        $this::assertSame(0x00, $bus->readByCpu(0x8000));
        $this::assertSame(0x04, $bus->readByCpu(0x8004));
    }

    #[Test]
    public function it_reads_from_program_rom_upper_bank(): void
    {
        $data = array_fill(0, 0x8000, 0xAA);
        $data[0x4000] = 0x55;
        $programRom = new Rom($data);

        $bus = $this->createBus(programRom: $programRom);

        $this::assertSame(0x55, $bus->readByCpu(0xC000));
    }

    #[Test]
    public function it_handles_16kb_rom_mirroring(): void
    {
        // For ROMs <= 16KB, upper bank mirrors lower bank
        $data = array_fill(0, 0x4000, 0xBB);
        $data[0x0100] = 0xCC;
        $programRom = new Rom($data);

        $bus = $this->createBus(programRom: $programRom);

        // Reading from 0xC000 should mirror 0x8000
        $this::assertSame(0xCC, $bus->readByCpu(0x8100));
        $this::assertSame(0xCC, $bus->readByCpu(0xC100));
    }

    #[Test]
    public function it_returns_zero_for_unmapped_reads(): void
    {
        $bus = $this->createBus();

        // APU and other unmapped areas return 0.
        $this::assertSame(0, $bus->readByCpu(0x4000));
        $this::assertSame(0, $bus->readByCpu(0x4015));
        $this::assertSame(0, $bus->readByCpu(0x6000));
    }

    #[Test]
    public function it_ignores_writes_to_unmapped_areas(): void
    {
        $bus = $this->createBus();

        // These should not throw exceptions.
        $bus->writeByCpu(0x4001, 0xFF);
        $bus->writeByCpu(0x6000, 0xAA);
        $bus->writeByCpu(0x8000, 0x55);

        /** @phpstan-ignore-next-line Assert no exception was thrown. */
        $this::assertTrue(true);
    }

    #[Test]
    public function it_handles_complete_memory_map(): void
    {
        $ram = new Ram(0x800);
        $ram->write(0x0000, 0x01);

        $programRom = new Rom([0x02]);

        $ppu = $this->createMock(Ppu::class);
        $ppu->method('read')->willReturn(0x03);

        $gamepad = $this->createMock(Gamepad::class);
        $gamepad->method('read')->willReturn(false);

        $bus = $this->createBus($ram, $programRom, $ppu, $gamepad);

        $this::assertSame(0x01, $bus->readByCpu(0x0000)); // RAM
        $this::assertSame(0x03, $bus->readByCpu(0x2000)); // PPU
        $this::assertSame(0x02, $bus->readByCpu(0x8000)); // ROM
    }

    /**
     * Creates a CpuBus instance with optional dependencies for testing.
     *
     * @noinspection ProperNullCoalescingOperatorUsageInspection
     */
    private function createBus(
        ?Ram $ram = null,
        ?Rom $programRom = null,
        ?Ppu $ppu = null,
        ?Gamepad $gamepad = null,
        ?Dma $dma = null,
    ): CpuBus {
        return new CpuBus(
            $ram ?? new Ram(0x800),
            $programRom ?? new Rom([]),
            $ppu ?? $this->createMock(Ppu::class),
            $gamepad ?? $this->createMock(Gamepad::class),
            $dma ?? $this->createMock(Dma::class),
        );
    }
}
