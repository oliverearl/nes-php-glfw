<?php

declare(strict_types=1);

namespace Tests\Support;

enum TestRomStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Timeout = 'timeout';
    case Unsupported = 'unsupported';
    case ResetRequired = 'reset-required';
}
