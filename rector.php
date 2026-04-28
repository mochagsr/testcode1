<?php

use Rector\Config\RectorConfig;
use SanderMuller\FluentValidationRector\Set\FluentValidationSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
    ])
    ->withSets([
        FluentValidationSetList::ALL,
    ]);
