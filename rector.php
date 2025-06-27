<?php

declare(strict_types=1);

use Netwerkstatt\SilverstripeRector\Rector\DataObject\EnsureTableNameIsSetRector;
use Netwerkstatt\SilverstripeRector\Rector\Injector\UseCreateRector;
use Netwerkstatt\SilverstripeRector\Rector\Misc\AddConfigPropertiesRector;
use Netwerkstatt\SilverstripeRector\Set\SilverstripeLevelSetList;
use Netwerkstatt\SilverstripeRector\Set\SilverstripeSetList;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;


return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src/_config.php',
        __DIR__ . '/src',
        __DIR__ . '/tests/php'
    ])
    ->withPreparedSets(deadCode: true)
    ->withSets([
        //rector lists
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        //silverstripe rector
        SilverstripeSetList::CODE_STYLE,
        SilverstripeLevelSetList::UP_TO_SS_6_0
    ])
    ->withRules([
        EnsureTableNameIsSetRector::class,
    ])


    // any rules that are included in the selected sets you want to skip
    ->withSkip([
//        ClassPropertyAssignToConstructorPromotionRector::class,
//        ReturnNeverTypeRector::class
    ]);
