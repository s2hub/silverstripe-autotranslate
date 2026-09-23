<?php

namespace S2Hub\AutoTranslate\Tests\Stub;

use S2Hub\AutoTranslate\Extension\AutoTranslate;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

class LocalisedVersionedDataObject extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static $table_name = 'AutoTranslateTest_Versioned';

    /**
     * @config
     */
    private static $db = [
        'Title' => 'Varchar',
    ];

    /**
     * @config
     */
    private static $extensions = [
        Versioned::class,
        FluentVersionedExtension::class,
        AutoTranslate::class,
    ];

    /**
     * @config
     */
    private static $translate = [
        'Title',
        'IsAutoTranslated',
        'LastTranslation',
    ];
}
