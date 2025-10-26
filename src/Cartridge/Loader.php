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
     * Program ROM size in bytes.
     */
    public const int PROGRAM_ROM_SIZE = 0x4000;

    /**
     * Character ROM size in bytes.
     */
    public const int CHARACTER_ROM_SIZE = 0x2000;

    /**
     * Create a cartridge loader.
     *
     * @throws \RuntimeException
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
     * Load the cartridge from the NES file.
     *
     * @throws \RuntimeException
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

        printf('ROM size: %d (0x%s)%s', count($nes), dechex(count($nes)), PHP_EOL);

        $programRomPages = $nes[4];
        printf('Program ROM pages: %d%s', $programRomPages, PHP_EOL);

        $characterRomPages = $nes[5];
        printf('Character ROM pages: %d%s', $characterRomPages, PHP_EOL);

        $isHorizontalMirror = !($nes[6] & 0x01);
        $mapper = ((($nes[6] & 0xF0) >> 4) | $nes[7] & 0xF0);
        printf("Mapper: %d\n", $mapper);

        $characterRomStart = self::NES_HEADER_SIZE + $programRomPages * self::PROGRAM_ROM_SIZE;
        printf('Character ROM start: 0x%s (%d)%s', dechex($characterRomStart), $characterRomStart, PHP_EOL);

        $characterRomEnd = $characterRomStart + $characterRomPages * self::CHARACTER_ROM_SIZE;
        printf('Character ROM end: 0x%s (%d)%s', dechex($characterRomEnd), $characterRomEnd, PHP_EOL);

        $cartridge = new Cartridge(
            $isHorizontalMirror,
            array_slice($nes, self::NES_HEADER_SIZE, $characterRomStart - self::NES_HEADER_SIZE),
            array_slice($nes, $characterRomStart, $characterRomEnd - $characterRomStart),
        );

        printf(
            'Program ROM: 0x0000 - 0x%s (%d bytes)%s',
            dechex($cartridge->getProgramRomSize() - 1),
            count($cartridge->programRom),
            PHP_EOL,
        );

        printf(
            'Character ROM: 0x0000 - 0x%s (%d bytes)%s',
            dechex($cartridge->getCharacterRomSize() - 1),
            count($cartridge->characterRom),
            PHP_EOL,
        );

        return $cartridge;
    }
}
