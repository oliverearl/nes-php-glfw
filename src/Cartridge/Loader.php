<?php

declare(strict_types=1);

namespace App\Cartridge;

use RuntimeException;

readonly class Loader
{
    /**
     * NES file header size in bytes.
     */
    public const int NES_HEADER_SIZE = 0x0010;

    /**
     * Program ROM page size in bytes.
     */
    public const int PROGRAM_ROM_SIZE = 0x4000;

    /**
     * Character ROM page size in bytes.
     */
    public const int CHARACTER_ROM_SIZE = 0x2000;

    /**
     * Creates a cartridge loader for the specified NES ROM file.
     *
     * @throws RuntimeException
     */
    public function __construct(private string $filepath)
    {
        if (! file_exists($this->filepath) || ! is_file($this->filepath)) {
            throw new RuntimeException("File not found: {$this->filepath}");
        }

        if (! is_readable($this->filepath)) {
            throw new RuntimeException("File is not readable: {$this->filepath}");
        }
    }

    /**
     * Loads and parses the NES ROM file into a Cartridge object.
     *
     * @throws RuntimeException
     */
    public function load(): Cartridge
    {
        $contents = file_get_contents($this->filepath);

        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$this->filepath}");
        }

        if (! str_starts_with($contents, 'NES')) {
            throw new RuntimeException("Invalid NES file format: {$this->filepath}");
        }

        $nes = [];

        for ($i = 0, $iMax = strlen($contents); $i < $iMax; ++$i) {
            $nes[$i] = (ord($contents[$i]) & 0xFF);
        }

        $this->debugLog('ROM size: %d (0x%s)', count($nes), dechex(count($nes)));

        $programRomPages = $nes[4];
        $this->debugLog('Program ROM pages: %d', $programRomPages);

        $characterRomPages = $nes[5];
        $this->debugLog('Character ROM pages: %d', $characterRomPages);

        $isHorizontalMirror = !($nes[6] & 0x01);
        $mapper = ((($nes[6] & 0xF0) >> 4) | $nes[7] & 0xF0);
        $this->debugLog('Mapper: %d', $mapper);

        $characterRomStart = self::NES_HEADER_SIZE + $programRomPages * self::PROGRAM_ROM_SIZE;
        $this->debugLog('Character ROM start: 0x%s (%d)', dechex($characterRomStart), $characterRomStart);

        $characterRomEnd = $characterRomStart + $characterRomPages * self::CHARACTER_ROM_SIZE;
        $this->debugLog('Character ROM end: 0x%s (%d)', dechex($characterRomEnd), $characterRomEnd);

        $cartridge = new Cartridge(
            $isHorizontalMirror,
            array_slice($nes, self::NES_HEADER_SIZE, $characterRomStart - self::NES_HEADER_SIZE),
            array_slice($nes, $characterRomStart, $characterRomEnd - $characterRomStart),
        );

        $this->debugLog(
            'Program ROM: 0x0000 - 0x%s (%d bytes)',
            dechex($cartridge->getProgramRomSize() - 1),
            count($cartridge->programRom),
        );

        $this->debugLog(
            'Character ROM: 0x0000 - 0x%s (%d bytes)',
            dechex($cartridge->getCharacterRomSize() - 1),
            count($cartridge->characterRom),
        );

        return $cartridge;
    }

    /**
     * Outputs debug information as long as we're not running tests.
     */
    private function debugLog(string $format, mixed ...$args): void
    {
        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            return;
        }

        printf($format . PHP_EOL, ...$args);
    }
}
