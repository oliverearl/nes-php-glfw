<?php

declare(strict_types=1);

namespace Tests\Unit\Benchmark;

use App\Cartridge\Loader;
use App\Emulation\NesSystem;
use App\Emulation\NesSystemFactory;
use App\Graphics\Objects\RenderingData;
use App\Graphics\Renderer;
use App\Input\Controller;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\BenchmarkRom;

#[CoversClass(BenchmarkRom::class)]
final class BenchmarkRomTest extends TestCase
{
    /**
     * Verifies that generated workloads remain reproducible and loadable as NROM.
     */
    #[Test]
    public function it_generates_deterministic_nrom_workloads(): void
    {
        foreach ([BenchmarkRom::CPU, BenchmarkRom::GRAPHICS] as $workload) {
            $contents = BenchmarkRom::contents($workload);

            $this::assertSame($contents, BenchmarkRom::contents($workload));
            $this::assertSame(16 + 0x4000 + 0x2000, strlen($contents));
            $this::assertSame("NES\x1A\x01\x01", substr($contents, 0, 6));
        }

        $this::assertNotSame(BenchmarkRom::contents(BenchmarkRom::CPU), BenchmarkRom::contents(BenchmarkRom::GRAPHICS));
    }

    /**
     * Verifies that unknown workload names cannot silently select a fixture.
     */
    #[Test]
    public function it_rejects_unknown_workloads(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BenchmarkRom::contents('missing');
    }

    /**
     * Requires an explicit destination for generated binary fixtures.
     */
    #[Test]
    public function it_rejects_an_empty_destination_directory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A fixture destination directory is required.');

        BenchmarkRom::writeToDirectory('');
    }

    /**
     * Exercises the generated machine code, including its video and input paths.
     */
    #[Test]
    public function it_executes_graphics_dma_scroll_and_controller_work(): void
    {
        $directory = sys_get_temp_dir() . '/nes-workloads-' . bin2hex(random_bytes(8));
        $paths = BenchmarkRom::writeToDirectory($directory);

        try {
            $controller = $this->createMock(Controller::class);
            $controller->expects($this->atLeastOnce())->method('read')->willReturn(false);
            $controller->expects($this->atLeastOnce())->method('write');
            $system = new NesSystemFactory()->create(new Loader($paths[BenchmarkRom::GRAPHICS], false)->load(), $controller);
            $renderer = new Renderer();
            $frames = [];
            $dmaTransfers = 0;

            for ($frame = 0; $frame < 4; $frame++) {
                [$data, $transfers] = $this->advanceFrame($system);
                $dmaTransfers += $transfers;
                $frames[] = $renderer->render($data);
            }

            $this::assertGreaterThanOrEqual(4, $dmaTransfers);
            $this::assertGreaterThanOrEqual(3, $system->ram->read(0));
            $this::assertNotEmpty($data->background);
            $this::assertCount(64, $data->sprites ?? []);
            $this::assertNotSame($frames[2], $frames[3]);
            $this::assertSame(0, $system->ram->read(1));

            $cpuSystem = new NesSystemFactory()->create(new Loader($paths[BenchmarkRom::CPU], false)->load());
            [$cpuData, $cpuDmaTransfers] = $this->advanceFrame($cpuSystem);

            $this::assertSame(0, $cpuDmaTransfers);
            $this::assertNull($cpuData->background);
            $this::assertNull($cpuData->sprites);
            $this::assertNotSame(array_fill(0, 256, 0), array_slice($cpuSystem->ram->getRam(), 0x0200, 256));
        } finally {
            foreach ($paths as $path) {
                unlink($path);
            }

            rmdir($directory);
        }
    }

    /**
     * Advances the actual emulator while counting observed DMA transactions.
     *
     * @return array{RenderingData, int}
     */
    private function advanceFrame(NesSystem $system): array
    {
        $transfers = 0;

        for ($iteration = 0; $iteration < 30000; $iteration++) {
            $cycles = 0;

            if ($system->dma->isDmaProcessing()) {
                $system->dma->runDma();
                $cycles = 514;
                $transfers++;
            }

            $cycles += $system->cpu->run();
            $data = $system->ppu->run($cycles * 3);

            if ($data !== false) {
                return [$data, $transfers];
            }
        }

        throw new RuntimeException('Benchmark workload did not produce a frame.');
    }
}
