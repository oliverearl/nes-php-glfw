# Changelog

## 1.1.0 — Unreleased

### Added

- Headless frame benchmarks with detailed or throughput timing, optional software rendering, JSON output, and checksums.
- Deterministic benchmark ROM generators, repeated Python captures and comparisons, and desktop profiling diagnostics.
- Independent CPU instruction vectors, focused instruction and interrupt regressions, and guarded execution of compatible
  test ROMs with explicit timeout, reset-required, and unsupported outcomes.
- A shared NES system factory, CPU state snapshots, and controller state injection for headless callers.
- Rector configuration and quality commands, Python tool CI, locked dependency audits, release-tag checks, and a
  maintainer release checklist.

### Fixed

- CPU instruction cycle totals, page-crossing behavior, arithmetic and status handling, stack operations, and interrupts.
- NROM header and payload validation, cartridge memory mapping, trainer loading, CHR RAM, and PRG RAM access.
- OAM DMA source reads through the CPU bus, including mapped memory and destination wrapping.
- PPU register access, palette handling, and preservation of elapsed clocks across scanlines and frame boundaries.
- Framebuffer backdrop color and sprite transparency, background priority, and OAM priority.
- Controller strobe and serial reporting independently of host keyboard polling.
- Repeated ROM loading retains the keyboard context and clears controller state instead of registering the same
  context twice and raising an input-mapping exception.
- Composer scripts now use Composer's PHP interpreter. Removed a stale project-creation hook referencing the deleted
  `bin/install.php` and requiring world-writable directories.

### Documentation and maintenance

- Updated setup, controls, compatibility limits, architecture, testing, and benchmarking documentation.
- Synchronized agent guidance across Codex, Cursor, Claude, Copilot, and Junie.
- Updated the copyright year range and documented third-party fixture attribution and permission terms.
- Updated CI actions, constrained workflow permissions and runtimes, and pinned native builds to Ubuntu 24.04.

### Compatibility notes

- PHP 8.5+ and PHP-GLFW remain required, including for headless runs. Install the committed Composer lock file.
- Cartridge support is limited to supported legacy iNES NROM layouts. Unsupported mappers and formats are rejected.
- Audio/APU emulation, persistent battery saves, and PAL timing are not implemented. PPU and DMA timing still use
  approximations; this release does not establish complete hardware or game-library compatibility.

## 1.0.0

Initial tagged release. See the [release history](https://github.com/oliverearl/nes-php-glfw/releases/tag/1.0.0).
