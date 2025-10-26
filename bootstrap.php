<?php

declare(strict_types=1);

date_default_timezone_set('Europe/London');

if (! defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

require __DIR__ . DS . 'vendor' . DS . 'autoload.php';

const VISU_PATH_ROOT = __DIR__;
const VISU_PATH_CACHE = VISU_PATH_ROOT . DS . 'var' . DS . 'cache';
const VISU_PATH_STORE = VISU_PATH_ROOT . DS . 'var' . DS . 'storage';
const VISU_PATH_RESOURCES = VISU_PATH_ROOT . DS . 'resources';
const VISU_PATH_APPCONFIG = VISU_PATH_ROOT . DS . 'app';

return require __DIR__ . DS . 'vendor' . DS . 'phpgl' . DS . 'visu' . DS . 'bootstrap.php';
