<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/var',
        __DIR__ . '/old',
    ])
    ->withComposerBased()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withSets([
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        SetList::TYPE_DECLARATION,
    ])
    ->withRules([
        // PHP 8.3+
        AddOverrideAttributeToOverriddenMethodsRector::class,

        // PHP 8.1+
        ReadOnlyPropertyRector::class,
        NullToStrictStringFuncCallArgRector::class,
    ])
    ->withImportNames(
        importDocBlockNames: false,
        removeUnusedImports: true,
    )
    ->withParallel()
    ->withCache(__DIR__ . '/var/cache/rector');
