<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use App\Cartridge\Cartridge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cartridge::class)]
final class CartridgeTest extends TestCase
{
    #[Test]
    public function it_creates_cartridge_with_valid_data(): void
    {
        $programRom = array_fill(0, 0x4000, 0);
        $characterRom = array_fill(0, 0x2000, 0);

        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: $programRom,
            characterRom: $characterRom
        );

        $this::assertTrue($cartridge->isHorizontalMirror);
        $this::assertCount(0x4000, $cartridge->programRom);
        $this::assertCount(0x2000, $cartridge->characterRom);
    }

    #[Test]
    public function it_sets_horizontal_mirror_correctly(): void
    {
        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: [0x00],
            characterRom: [0x00]
        );

        $this::assertTrue($cartridge->isHorizontalMirror);
    }

    #[Test]
    public function it_sets_vertical_mirror_correctly(): void
    {
        $cartridge = new Cartridge(
            isHorizontalMirror: false,
            programRom: [0x00],
            characterRom: [0x00]
        );

        $this::assertFalse($cartridge->isHorizontalMirror);
    }

    #[Test]
    public function it_returns_program_rom_size(): void
    {
        $programRom = array_fill(0, 32768, 0);

        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: $programRom,
            characterRom: [0x00]
        );

        $this::assertSame(32768, $cartridge->getProgramRomSize());
    }

    #[Test]
    public function it_returns_character_rom_size(): void
    {
        $characterRom = array_fill(0, 8192, 0);

        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: [0x00],
            characterRom: $characterRom
        );

        $this::assertSame(8192, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_handles_empty_roms(): void
    {
        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: [],
            characterRom: []
        );

        $this::assertSame(0, $cartridge->getProgramRomSize());
        $this::assertSame(0, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_preserves_rom_data(): void
    {
        $programRom = [0x4C, 0x00, 0x80]; // JMP $8000
        $characterRom = [0xFF, 0xAA, 0x55];

        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: $programRom,
            characterRom: $characterRom
        );

        $this::assertSame([0x4C, 0x00, 0x80], $cartridge->programRom);
        $this::assertSame([0xFF, 0xAA, 0x55], $cartridge->characterRom);
    }

    #[Test]
    public function it_handles_typical_nes_cartridge_sizes(): void
    {
        // 16KB program ROM, 8KB character ROM (common NES cart)
        $cartridge = new Cartridge(
            isHorizontalMirror: true,
            programRom: array_fill(0, 16384, 0),
            characterRom: array_fill(0, 8192, 0)
        );

        $this::assertSame(16384, $cartridge->getProgramRomSize());
        $this::assertSame(8192, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_handles_large_cartridge(): void
    {
        // 32KB program ROM, 16KB character ROM
        $cartridge = new Cartridge(
            isHorizontalMirror: false,
            programRom: array_fill(0, 32768, 0),
            characterRom: array_fill(0, 16384, 0)
        );

        $this::assertSame(32768, $cartridge->getProgramRomSize());
        $this::assertSame(16384, $cartridge->getCharacterRomSize());
    }
}

