# ROM fixtures

`HelloWorld.nes` is the committed homebrew fixture used by cartridge loading and system integration tests.
Its source, permission terms, and checksum are recorded in [third-party notices](../../THIRD_PARTY_NOTICES.md).
Most behavioral tests generate original temporary NROM programs with the helpers in `tests/Support/` instead.

Keep local test ROMs and game ROMs outside this directory, or under the ignored `var/storage/` tree.
Copying a ROM here does not add it to the test suite: add an explicit test and record its requirements and expected
result. `IntegrationTestCase::requireTestRom()` skips a test if its requested file is absent.

For compatible NROM tests using the blargg status protocol, `Tests\Support\TestRomRunner` requires the signature at
`$6001-$6003` and a running-to-completion transition at `$6000`. It bounds CPU cycles and diagnostic output, and
distinguishes passed, failed, timed out, reset required, and unsupported results. Unsupported mappers or hardware
prerequisites, and tests requiring cycle-ordered DMA, cannot be counted as successful validation. This helper is not a
command-line test discovery tool.

Before committing a third-party ROM, retain its source URL, exact revision, redistribution terms, build command,
checksum, and any transformations. Do not add commercial game ROMs.
