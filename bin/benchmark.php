<?php

declare(strict_types=1);

use App\Benchmark\BenchmarkFormatter;
use App\Benchmark\BenchmarkOptions;
use App\Benchmark\BenchmarkRunner;

require __DIR__ . '/../vendor/autoload.php';

$formatter = new BenchmarkFormatter();

try {
    $options = BenchmarkOptions::fromArgv(array_values($_SERVER['argv'] ?? []));

    if ($options->help) {
        echo $formatter->usage();
        exit(0);
    }

    $result = (new BenchmarkRunner())->run($options);

    echo $options->json
        ? $formatter->json($result)
        : $formatter->human($result);

    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Benchmark failed: ' . $throwable->getMessage() . PHP_EOL . PHP_EOL);
    fwrite(STDERR, $formatter->usage());
    exit(1);
}
