<?php

use Kahlan\Filter\Filters;
use Kahlan\Reporter\Coverage;
use Kahlan\Reporter\Coverage\Driver\Phpdbg;
use Kahlan\Reporter\Coverage\Driver\Xdebug;
use Kahlan\Reporter\Coverage\Exporter\Clover;

$commandLine = $this->commandLine();
$commandLine->option('ff', 'default', 1);
$commandLine->option('coverage', 'default', 3);
$commandLine->option('coverage-clover', 'default', 'clover.xml');

Filters::apply($this, 'reporting', function ($next) {

    // Get the reporter called `'coverage'` from the list of reporters
    $reporter = $this->reporters()->get('coverage');

    // Abort if no coverage is available.
    if (! $reporter || ! $this->commandLine()->exists('coverage-clover')) {
        return $next();
    }

    // Use the `Coveralls` class to write the JSON coverage into a file
    Clover::write([
        'collector' => $reporter,
        'file' => $this->commandLine()->get('coverage-clover'),
    ]);

    return $next();
});

Filters::apply($this, 'coverage', function ($next) {
    if (! extension_loaded('xdebug') && PHP_SAPI !== 'phpdbg') {
        return;
    }
    $reporters = $this->reporters();
    $coverage = new Coverage([
        'verbosity' => $this->commandLine()->get('coverage'),
        'driver' => PHP_SAPI !== 'phpdbg' ? new Xdebug : new Phpdbg,
        'path' => $this->commandLine()->get('src'),
        'colors' => ! $this->commandLine()->get('no-colors'),
    ]);
    $reporters->add('coverage', $coverage);
});

require_once realpath(rtrim(getcwd(), '\\/ ')).DIRECTORY_SEPARATOR.'spec'.DIRECTORY_SEPARATOR.'bootstrap.php';
