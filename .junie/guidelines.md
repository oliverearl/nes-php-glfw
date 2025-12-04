# Junie Development Guidelines for NES Emulator

This document provides guidelines for AI-assisted development of this NES (Nintendo Entertainment System) emulator written in PHP.

## Project Overview

This is a cycle-accurate NES emulator implementation in PHP 8.5+ using:
- **Graphics**: PHP-GLFW (OpenGL) via the VISU framework
- **Architecture**: 6502 CPU emulation, PPU (Picture Processing Unit), APU (Audio Processing Unit - planned)
- **Testing**: PHPUnit with comprehensive unit and integration tests
- **Quality**: PHPStan for static analysis, Laravel Pint for code formatting

## Code Style & Standards

### Documentation

**Properties and Methods:**
- All properties must have docblocks describing their purpose
- All methods must have docblocks describing what they do
- Only include `@param` or `@return` when generics need to be declared (e.g., `list<int>`, `array<int, string>`)
- Use `@throws`, `@see`, or `@inheritDoc` when appropriate
- Do NOT add class-level docblocks

**Example:**
```php
/**
 * The current PPU cycle within a scanline.
 */
private int $cycle = 0;

/**
 * Executes a single CPU instruction and returns the number of cycles consumed.
 *
 * @throws \RuntimeException
 */
public function run(): int
{
    // ...
}
```

### Comments

**General Rules:**
- Remove superfluous comments that merely restate the code
- All comments must end with a period (.)
- Multi-line comments use C-style `/* */` syntax
- Single-line comments use `//` syntax
- Keep comments that explain complex logic or non-obvious behavior

**Example:**
```php
/* Set up reset vector to point to 0x8000 (start of ROM).
 * Reset vector is at 0xFFFC-0xFFFD. */
$romData[0x7FFC] = 0x00;
$romData[0x7FFD] = 0x80;

// 6502 stack is from 0x0100-0x01FF, initialized to 0x01FD.
$this::assertSame(0x01FD, $registers->sp);
```

### Code Organization

**File Structure:**
- One class per file
- Namespace must match directory structure
- Use strict types: `declare(strict_types=1);`
- Order: properties first, constructor, public methods, protected methods, private methods

**Naming Conventions:**
- Classes: PascalCase
- Methods: camelCase
- Properties: camelCase
- Constants: SCREAMING_SNAKE_CASE
- Test methods: snake_case with `it_` prefix (e.g., `it_handles_8bit_values`)

## Architecture Patterns

### Emulator Components

**CPU (Central Processing Unit):**
- Located in `src/Cpu/`
- Implements 6502 instruction set (official and unofficial opcodes)
- Handles interrupts (NMI, IRQ)
- Cycle-accurate execution
- See `src/Cpu/Cpu.php` for reference

**PPU (Picture Processing Unit):**
- Located in `src/Graphics/`
- Handles rendering (background tiles, sprites)
- Manages VRAM, sprite RAM, palettes
- Runs at 3x CPU speed
- Generates VBlank interrupts
- See `src/Graphics/Ppu.php` for reference

**Memory Bus Architecture:**
- `CpuBus`: CPU address space ($0000-$FFFF)
- `PpuBus`: PPU address space ($0000-$3FFF)
- RAM mirroring implemented correctly
- Memory-mapped I/O for PPU, gamepad, DMA

**Key Classes:**
- `Cpu`: 6502 CPU emulation
- `Ppu`: Picture Processing Unit
- `Dma`: Direct Memory Access for sprite data
- `CpuBus`, `PpuBus`: Memory buses
- `Ram`, `Rom`: Memory components
- `Cartridge`, `Loader`: ROM file handling
- `Gamepad`: Input handling
- `Renderer`: Converts PPU output to framebuffer

### Value Objects

Use readonly classes for immutable data:
```php
readonly class Registers
{
    public function __construct(
        public int $a,
        public int $x,
        public int $y,
        public Status $p,
        public int $sp,
        public int $pc,
    ) {}
}
```

### Dependency Injection

Constructor injection is preferred:
```php
public function __construct(
    private readonly CpuBus $bus,
    private readonly Interrupts $interrupts,
) {}
```

## Testing Guidelines

### Unit Tests

**Structure:**
- Located in `tests/Unit/`
- Use `#[Test]` attribute instead of `test` prefix
- Use `#[CoversClass(ClassName::class)]` attribute
- Test method names: `it_does_something_specific()`

**Example:**
```php
#[CoversClass(Ram::class)]
final class RamTest extends TestCase
{
    #[Test]
    public function it_writes_and_reads_data(): void
    {
        $ram = new Ram(256);
        $ram->write(100, 0x42);
        
        $this::assertSame(0x42, $ram->read(100));
    }
}
```

**Helper Methods:**
- Add docblocks to helper methods
- setUp/tearDown methods use `@inheritDoc`
- Extract common setup to helper methods

### Integration Tests

**Structure:**
- Located in `tests/Integration/`
- Extend `IntegrationTestCase` for common utilities
- Test complete system interactions
- Use `requireTestRom()` for ROM file tests

**Example:**
```php
final class SystemIntegrationTest extends IntegrationTestCase
{
    #[Test]
    public function it_completes_full_frame_rendering_cycle(): void
    {
        [$cpu, , , , $ppu] = $this->createTestSystem();
        $renderer = new Renderer();
        
        $cpu->reset();
        
        $renderingData = false;
        $iterations = 0;
        
        while ($renderingData === false && $iterations < 50000) {
            $cpuCycles = $cpu->run();
            $renderingData = $ppu->run($cpuCycles * 3);
            $iterations++;
        }
        
        $this::assertNotFalse($renderingData);
        $frameBuffer = $renderer->render($renderingData);
        $this::assertCount(256 * 256 * 4, $frameBuffer);
    }
}
```

### Test Coverage

**Requirements:**
- All public methods must be tested
- All static methods must be tested
- Edge cases: boundary values, zero values, maximum values
- Error conditions must be tested with appropriate `expectException()`

**Current Coverage:**
- 234 tests with 266,452+ assertions
- Unit tests for all components
- Integration tests for system interactions
- ROM loading and parsing tests

## NES-Specific Knowledge

### Memory Map

**CPU Address Space ($0000-$FFFF):**
- $0000-$07FF: 2KB internal RAM
- $0800-$1FFF: Mirrors of RAM
- $2000-$2007: PPU registers (mirrored through $3FFF)
- $4000-$4017: APU and I/O registers
- $4020-$FFFF: Cartridge space (PRG-ROM, mapper registers)

**PPU Address Space ($0000-$3FFF):**
- $0000-$0FFF: Pattern table 0
- $1000-$1FFF: Pattern table 1
- $2000-$23FF: Nametable 0
- $2400-$27FF: Nametable 1
- $2800-$2BFF: Nametable 2
- $2C00-$2FFF: Nametable 3
- $3000-$3EFF: Mirrors of nametables
- $3F00-$3F1F: Palette RAM
- $3F20-$3FFF: Mirrors of palette

### Timing

**Critical Timings:**
- CPU: ~1.79 MHz (NTSC)
- PPU: 3x CPU speed
- Frame: 262 scanlines
- Scanline: 341 PPU cycles
- DMA: 514 CPU cycles (256 bytes + overhead)

### Data Sizes

**Common Values:**
- Byte: 8-bit (0x00-0xFF)
- Word: 16-bit (0x0000-0xFFFF)
- Sprite: 8x8 pixels
- Tile: 8x8 pixels
- Nametable: 32x30 tiles
- Screen: 256x240 pixels (256x224 visible)

## Common Tasks

### Adding a New Feature

1. **Write tests first** (TDD approach)
2. **Implement the feature** following code style
3. **Add proper docblocks** to all new methods/properties
4. **Run tests**: `vendor/bin/phpunit`
5. **Run static analysis**: `vendor/bin/phpstan analyze`
6. **Format code**: `vendor/bin/pint`

### Debugging CPU Issues

1. Check cycle counts against CYCLES table
2. Verify flag updates (N, Z, C, V)
3. Test with nestest.nes ROM if available
4. Use integration tests to verify instruction behavior

### Debugging PPU Issues

1. Verify timing (PPU runs at 3x CPU speed)
2. Check VRAM address calculations
3. Verify mirroring (horizontal vs. vertical)
4. Test palette writes and reads
5. Check sprite priority and flipping

### Adding New Tests

**Unit Test:**
```php
#[Test]
public function it_handles_specific_case(): void
{
    $component = new Component();
    
    $result = $component->doSomething();
    
    $this::assertSame($expected, $result);
}
```

**Integration Test:**
```php
#[Test]
public function it_integrates_components(): void
{
    [$cpu, $bus, $ram] = $this->createTestSystem();
    
    // Setup
    $ram->write(0x0200, 0x42);
    
    // Execute
    $cpu->reset();
    $cycles = $cpu->run();
    
    // Assert
    $this::assertGreaterThan(0, $cycles);
}
```

## Quality Checklist

Before submitting changes, ensure:

- [ ] All tests pass (`vendor/bin/phpunit`)
- [ ] PHPStan passes (`vendor/bin/phpstan analyze`)
- [ ] Code is formatted (`vendor/bin/pint`)
- [ ] All new methods have docblocks
- [ ] All new properties have docblocks
- [ ] Complex logic has explanatory comments
- [ ] No superfluous comments remain
- [ ] Test coverage for new functionality
- [ ] Integration tests for new features

## Helpful Commands

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Unit/Cpu/CpuTest.php

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage

# Run static analysis
vendor/bin/phpstan analyze

# Format code
vendor/bin/pint

# Run emulator with ROM
php bin/start.php path/to/rom.nes
```

## Additional Resources

- NES Dev Wiki: https://wiki.nesdev.com/
- 6502 Reference: http://www.6502.org/
- PPU Documentation: https://wiki.nesdev.com/w/index.php/PPU
- Test ROMs: https://github.com/christopherpow/nes-test-roms

## Notes

- This emulator prioritizes accuracy over performance
- Cycle counting is critical for timing-sensitive games
- Unofficial opcodes are supported for compatibility
- The project uses PHP-GLFW for graphics via VISU framework
- DMA transfers take 514 cycles (256 bytes + overhead)
- PPU rendering generates data every 262 scanlines (one frame)

---

*Last updated: December 4, 2025*

