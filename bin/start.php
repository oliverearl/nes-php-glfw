<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

use App\Emulator;
use VISU\Quickstart;
use VISU\Quickstart\QuickstartOptions;

$container = require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

new Quickstart(function (QuickstartOptions $options) use ($container): void {
    $options->appClass = Emulator::class;
    $options->container = $container;
    $options->windowTitle = $container->getParameter('project.name');
})->run();
