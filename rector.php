<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\ReplaceTestAnnotationWithPrefixedFunctionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/bin',
        __DIR__ . '/tools/benchmark-fixtures.php',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/var',
        __DIR__ . '/old',

        // Preserve public call signatures, including named arguments and static calls.
        RemoveUnusedConstructorParamRector::class,
        RemoveUnusedPublicMethodParameterRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,

        // Tests use #[Test] attributes and keep their it_* method names.
        ReplaceTestAnnotationWithPrefixedFunctionRector::class,
    ])
    // Load all PHP upgrade sets through the minimum version in composer.json (8.5).
    ->withPhpSets()
    ->withComposerBased(phpunit: true)
    ->withAttributesSets(phpunit: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        phpunitCodeQuality: true,
    )
    // Pint's PER preset owns formatting; avoid Rector's coding-style and naming sets.
    ->withImportNames(
        importDocBlockNames: false,
        removeUnusedImports: true,
    )
    ->withParallel()
    ->withCache(__DIR__ . '/var/cache/rector');
