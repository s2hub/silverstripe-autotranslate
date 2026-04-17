<?php

namespace S2Hub\AutoTranslate\Tests\Stub;

use S2Hub\AutoTranslate\Extension\AutoTranslate;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Extension\FluentExtension;

class LocalisedDataObject extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static $table_name = 'AutoTranslateTest_LocalisedDataObject';

    /**
     * @config
     */
    private static $db = [
        'Title' => 'Varchar',
        'Content' => 'HTMLText',
    ];

    /**
     * @config
     */
    private static $extensions = [
        FluentExtension::class,
        AutoTranslate::class
    ];

    /**
     * @config
     */
    private static $translate = [
        'Title',
        'Content',
        'IsAutoTranslated',
        'LastTranslation',
    ];

    private bool $canEdit = true;

    public function setCanEdit(bool $canEdit)
    {
        $this->canEdit = $canEdit;
    }

    public function canEdit($member = null)
    {
        return parent::canEdit() && $this->canEdit;
    }
}
