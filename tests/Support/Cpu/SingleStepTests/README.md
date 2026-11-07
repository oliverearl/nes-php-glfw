# Independent NES CPU instruction vectors

`vectors.json` contains the first four records of each selected opcode file from
the RP2A03 `nes6502/v1` dataset in [SingleStepTests/65x02](https://github.com/SingleStepTests/65x02).
The source revision, complete opcode list, record count, and local SHA-256 are in
`provenance.json`. The selected records are unmodified. The upstream MIT licence
and dataset guide are retained as `LICENSE` and `UPSTREAM_README.md`.

The fixed sample covers 231 implemented non-KIL opcodes. It includes every
documented instruction and the currently supported stable unofficial families;
it does not certify unsupported/unstable instructions or CPU jam behaviour.
Four upstream records per opcode are a fast regression sample, not exhaustive
state coverage. Focused boundary tests and exhaustive binary ADC/SBC complement
the sample. Expanding it must retain the pinned independent expectations.

Tests use flat 64 KiB memory, as required by the dataset. They compare final
registers, listed memory values, and the number of expected bus cycles. The
snapshot normalizes the synthetic B status bit to zero and the unused bit to one;
stacked status bytes are compared separately without that normalization.
Individual cycle ordering and every dummy access are not yet validated against
the vectors. The saved cycle records support that later validation without
changing the independent source data.
