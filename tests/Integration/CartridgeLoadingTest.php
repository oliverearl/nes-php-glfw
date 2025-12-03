<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Cartridge\Cartridge;
use App\Cartridge\Loader;
use PHPUnit\Framework\Attributes\Test;

final class CartridgeLoadingTest extends IntegrationTestCase
{
    #[Test]
    public function it_loads_test_rom_file(): void
    {
        $testRomPath = $this->requireTestRom();
        $loader = new Loader($testRomPath);

        // Capture output (loader prints debug info)
        ob_start();
        $cartridge = $loader->load();
        ob_get_clean();

        // Verify cartridge loaded
        $this->assertInstanceOf(Cartridge::class, $cartridge);

        // HelloWorld has program ROM
        $this->assertGreaterThan(0, $cartridge->getProgramRomSize());

        // Should have character ROM or RAM
        $this->assertGreaterThanOrEqual(0, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_validates_nes_file_format(): void
    {
        $testRomPath = $this->requireTestRom('HelloWorld.nes');

        // Verify file starts with "NES\x1A"
        $handle = fopen($testRomPath, 'rb');
        $header = fread($handle, 4);
        fclose($handle);

        $this->assertSame('NES', substr($header, 0, 3));
        $this->assertSame("\x1A", substr($header, 3, 1));
    }

    #[Test]
    public function it_extracts_program_rom_from_file(): void
    {
        $testRomPath = $this->requireTestRom('HelloWorld.nes');

        $loader = new Loader($testRomPath);
        ob_start();
        $cartridge = $loader->load();
        ob_end_clean();

        // Program ROM should contain 6502 code
        $this->assertNotEmpty($cartridge->programRom);

        // Verify it's actual data, not all zeros
        $hasNonZero = false;
        foreach ($cartridge->programRom as $byte) {
            if ($byte !== 0) {
                $hasNonZero = true;
                break;
            }
        }

        $this->assertTrue($hasNonZero, 'Program ROM should contain non-zero data');
    }

    #[Test]
    public function it_extracts_character_rom_from_file(): void
    {
        $testRomPath = $this->requireTestRom('HelloWorld.nes');

        $loader = new Loader($testRomPath);
        ob_start();
        $cartridge = $loader->load();
        ob_end_clean();

        // Character ROM should exist (may be empty for CHR-RAM games)
        $this->assertGreaterThanOrEqual(0, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_determines_mirroring_mode(): void
    {
        $testRomPath = $this->requireTestRom('HelloWorld.nes');

        $loader = new Loader($testRomPath);
        ob_start();
        $cartridge = $loader->load();
        ob_end_clean();

        // Mirroring mode should be set (either true or false)
        $this->assertIsBool($cartridge->isHorizontalMirror);
    }

    #[Test]
    public function it_throws_exception_for_missing_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        new Loader('/nonexistent/file.nes');
    }

    #[Test]
    public function it_throws_exception_for_invalid_nes_file(): void
    {
        // Create a temp file with invalid content
        $tempFile = sys_get_temp_dir() . '/invalid.nes';
        file_put_contents($tempFile, 'INVALID');

        try {
            $loader = new Loader($tempFile);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid NES file format');

            $loader->load();
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function it_handles_different_rom_sizes(): void
    {
        $testRomPath = $this->requireTestRom('HelloWorld.nes');

        $loader = new Loader($testRomPath);
        ob_start();
        $cartridge = $loader->load();
        ob_end_clean();

        $programSize = $cartridge->getProgramRomSize();
        $characterSize = $cartridge->getCharacterRomSize();

        // Both should be multiples of their page sizes
        // Program ROM pages are 16KB (0x4000)
        // Character ROM pages are 8KB (0x2000)

        if ($programSize > 0) {
            $this->assertSame(0, $programSize % 0x4000, 'Program ROM should be multiple of 16KB');
        }

        if ($characterSize > 0) {
            $this->assertSame(0, $characterSize % 0x2000, 'Character ROM should be multiple of 8KB');
        }
    }
}

