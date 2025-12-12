<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use App\Cartridge\Loader;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Loader::class)]
final class LoaderTest extends TestCase
{
    /**
     * Temporary path for test ROM files.
     */
    private string $testRomPath;

    /** @inheritDoc */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testRomPath = sys_get_temp_dir() . '/test_rom.nes';
    }

    /** @inheritDoc */
    #[Override]
    protected function tearDown(): void
    {
        if (file_exists($this->testRomPath)) {
            unlink($this->testRomPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_throws_exception_for_non_existent_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found:');

        new Loader('/non/existent/file.nes');
    }

    #[Test]
    public function it_throws_exception_for_invalid_nes_format(): void
    {
        // Create a file without NES header.
        file_put_contents($this->testRomPath, 'INVALID');

        $loader = new Loader($this->testRomPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid NES file format:');

        $loader->load();
    }

    #[Test]
    public function it_loads_valid_nes_rom(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        $this::assertTrue($cartridge->isHorizontalMirror);
        $this::assertSame(0x4000, $cartridge->getProgramRomSize());
        $this::assertSame(0x2000, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_parses_horizontal_mirror_flag(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        $this::assertTrue($cartridge->isHorizontalMirror);
    }

    #[Test]
    public function it_parses_vertical_mirror_flag(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
            isHorizontalMirror: false,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        $this::assertFalse($cartridge->isHorizontalMirror);
    }

    #[Test]
    public function it_loads_16kb_program_rom(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        $this::assertSame(16384, $cartridge->getProgramRomSize());
    }

    #[Test]
    public function it_loads_32kb_program_rom(): void
    {
        $this->createValidNesRom(
            programRomPages: 2,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        $this::assertSame(32768, $cartridge->getProgramRomSize());
    }

    #[Test]
    public function it_loads_8kb_character_rom(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        $this::assertSame(8192, $cartridge->getCharacterRomSize());
    }

    #[Test]
    public function it_loads_rom_with_actual_data(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        // Program ROM should start with reset vector pattern.
        $this::assertCount(0x4000, $cartridge->programRom);

        // Character ROM should have pattern data.
        $this::assertCount(0x2000, $cartridge->characterRom);
    }

    #[Test]
    public function it_validates_nes_header_signature(): void
    {
        // Create file with incorrect signature.
        $header = str_repeat("\x00", 16);
        file_put_contents($this->testRomPath, $header);

        $loader = new Loader($this->testRomPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid NES file format:');

        $loader->load();
    }

    #[Test]
    public function it_handles_mapper_0(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        /** @phpstan-ignore-next-line Assert no exception was thrown. */
        $this::assertNotNull($cartridge);
    }

    #[Test]
    public function it_handles_different_rom_sizes(): void
    {
        $testCases = [
            ['prg' => 1, 'chr' => 0],
            ['prg' => 1, 'chr' => 1],
            ['prg' => 2, 'chr' => 1],
            ['prg' => 2, 'chr' => 2],
        ];

        foreach ($testCases as $testCase) {
            $this->createValidNesRom(
                programRomPages: $testCase['prg'],
                characterRomPages: $testCase['chr'],
            );

            $loader = new Loader($this->testRomPath);
            $cartridge = $loader->load();

            $expectedPrgSize = $testCase['prg'] * 0x4000;
            $expectedChrSize = $testCase['chr'] * 0x2000;

            $this::assertSame($expectedPrgSize, $cartridge->getProgramRomSize());
            $this::assertSame($expectedChrSize, $cartridge->getCharacterRomSize());

            unlink($this->testRomPath);
        }
    }

    #[Test]
    public function it_preserves_rom_data_integrity(): void
    {
        $this->createValidNesRom(
            programRomPages: 1,
            characterRomPages: 1,
        );

        // Write specific pattern to program ROM.
        $content = file_get_contents($this->testRomPath);
        $content = substr($content, 0, 16) . str_repeat("\xAA", 0x4000) . str_repeat("\x55", 0x2000);
        file_put_contents($this->testRomPath, $content);

        $loader = new Loader($this->testRomPath);
        $cartridge = $loader->load();

        // Verify program ROM pattern.
        $this::assertSame(0xAA, $cartridge->programRom[0]);
        $this::assertSame(0xAA, $cartridge->programRom[0x3FFF]);

        // Verify character ROM pattern.
        $this::assertSame(0x55, $cartridge->characterRom[0]);
        $this::assertSame(0x55, $cartridge->characterRom[0x1FFF]);
    }

    /**
     * Creates a valid dummy NES ROM file for testing.
     */
    private function createValidNesRom(
        int $programRomPages,
        int $characterRomPages,
        bool $isHorizontalMirror = true,
        int $mapper = 0,
    ): void {
        // Create NES header.
        $header = [
            // "NES" followed by MS-DOS EOF
            0x4E, 0x45, 0x53, 0x1A,
            // Number of 16KB PRG-ROM pages
            $programRomPages,
            // Number of 8KB CHR-ROM pages
            $characterRomPages,
            // Flags 6
            $isHorizontalMirror ? 0x00 : 0x01,
            // Flags 7
            ($mapper << 4),
            // Flags 8-11 (unused)
            0x00, 0x00, 0x00, 0x00,
            // Padding
            0x00, 0x00, 0x00, 0x00,
        ];

        $headerData = '';

        foreach ($header as $byte) {
            $headerData .= chr($byte);
        }

        $programRom = str_repeat("\x00", $programRomPages * 0x4000);
        $characterRom = str_repeat("\x00", $characterRomPages * 0x2000);
        file_put_contents($this->testRomPath, $headerData . $programRom . $characterRom);
    }
}
