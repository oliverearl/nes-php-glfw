<?php

declare(strict_types=1);

use Tests\Support\BenchmarkRom;

require __DIR__ . '/../vendor/autoload.php';

try {
    $directory = $_SERVER['argv'][1] ?? '';

    if ($directory === '') {
        throw new InvalidArgumentException('Usage: php tools/benchmark-fixtures.php <destination-directory>');
    }

    foreach (BenchmarkRom::writeToDirectory($directory) as $workload => $path) {
        printf("%s: %s (SHA-256 %s)\n", $workload, $path, hash_file('sha256', $path));
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}
