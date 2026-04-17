<?php

namespace S2Hub\AutoTranslate\Tests;

use S2Hub\AutoTranslate\Extension\AutoTranslate;
use S2Hub\AutoTranslate\Tests\Stub\LocalisedDataObject;
use S2Hub\AutoTranslate\Tests\Translator\MockTranslator;
use S2Hub\AutoTranslate\Translator\AITranslationStatus;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AutoTranslateTest extends SapphireTest
{
    protected static $fixture_file = 'AutoTranslateTest.yml';

    protected static $extra_dataobjects = [
        LocalisedDataObject::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Locale::clearCached();
        AutoTranslate::setTranslator(new MockTranslator());

        // Write localised data for en_US (default locale)
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            DBDatetime::set_mock_now('2024-09-29 14:00:00');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $recordA->write();

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $recordB->write();

            DBDatetime::clear_mock_now();
        });

        // Write localised data for de_DE
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('de_DE');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $recordA->Title = 'Die Magie von Silverstripe';
            $recordA->Content = 'Besuchen Sie Hamburg für die beste Silverstripe-Community';
            $recordA->IsAutoTranslated = true;
            $recordA->LastTranslation = '2024-09-30 14:00:00';
            $recordA->write();

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $recordB->Title = 'Brüll, Drache, brüll!';
            $recordB->Content = 'Besuchen Sie uns auf der Stripecon in Ljubljana!';
            $recordB->IsAutoTranslated = false;
            $recordB->LastTranslation = '2024-09-28 00:00:00';
            $recordB->write();
        });
    }

    public function testCanTranslateIsTrueInDefaultLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertTrue((bool)$dataObject->getSourceLocale()->IsGlobalDefault, 'Locale should be default locale');
            $this->assertTrue($dataObject->canTranslate(), "We're only allowed to translate in default locale");
        });
    }

    public function testCanTranslateIsFalseInOtherLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('de_DE');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertFalse((bool)$dataObject->getSourceLocale()->IsGlobalDefault, 'Locale should not be default locale');
            $this->assertFalse($dataObject->canTranslate(), "We're not allowed to translate in other locales");
        });
    }

    public function testUnpriviledgedUserCannotTranslate()
    {

        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $dataObject->setCanEdit(false);
            $this->assertTrue((bool)$dataObject->getSourceLocale()->IsGlobalDefault, 'Locale should be default locale');
            $this->assertFalse($dataObject->canTranslate(), "We're only allowed to translate in default locale");
        });
    }

    public function testHasDefaultLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertTrue($dataObject->hasDefaultLocale(), 'Locale en_US should be default locale');
            $newState
                ->setLocale('de_DE');

            $dataObject = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertFalse($dataObject->hasDefaultLocale(), 'Locale de_DE not should be default locale');
        });
    }

    public function testAutoTranslateFailures()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('de_DE');

            $dataObjectDE = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            /** @var AITranslationStatus $status */
            $status = $dataObjectDE->autoTranslate();
            $this->assertEquals(AITranslationStatus::ERRORMSG_NOTDEFAULTLOCALE, $status->getMessage(), 'AutoTranslate should fail if not in default locale');

            $newState
                ->setLocale('en_US');

            $emptyDataObject = LocalisedDataObject::create(['Locale' => 'en_US']);
            $emptyDataObject->write();

            $status = $emptyDataObject->autoTranslate();
            // Empty objects are still processed: IsAutoTranslated gets set even without content fields
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertNotEmpty($localesStatus, 'AutoTranslate should still process locales for empty objects');
            foreach ($localesStatus as $localeStatus) {
                $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localeStatus, 'Empty objects should be marked as translated');
            }
        });
    }

    public function testAutoTranslateMessagesPerLocale()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $status = $recordA->autoTranslate();
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertCount(2, $localesStatus, 'AutoTranslate should return 2 locales status');
            $this->assertEquals(AITranslationStatus::STATUS_ALREADYTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return already translated for de_DE');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['es_ES'], 'AutoTranslate should return translated for es_ES');

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $status = $recordB->autoTranslate();
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertEquals(AITranslationStatus::STATUS_NOTAUTOTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return that de_DE is not auto translated');
        });
    }

    public function testAutoTranslateCanForceTranslation()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $status = $recordA->autoTranslate(false, true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertCount(2, $localesStatus, 'AutoTranslate should return 2 locales status');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return translated for de_DE');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['es_ES'], 'AutoTranslate should return translated for es_ES');

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $status = $recordB->autoTranslate(false, true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertEquals(AITranslationStatus::STATUS_NOTAUTOTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return that de_DE is not auto translated');
        });
    }

    public function testDoPublishIsIgnoredInUnversionedObjects()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $status = $recordA->autoTranslate(true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertCount(2, $localesStatus, 'AutoTranslate should return 2 locales status');
            $this->assertEquals(AITranslationStatus::STATUS_ALREADYTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return already translated for de_DE');
            $this->assertEquals(AITranslationStatus::STATUS_TRANSLATED, $localesStatus['es_ES'], 'AutoTranslate should return translated for es_ES');

            $recordB = $this->objFromFixture(LocalisedDataObject::class, 'record_b');
            $status = $recordB->autoTranslate(true);
            $localesStatus = $status->getLocalesTranslatedTo();
            $this->assertEquals(AITranslationStatus::STATUS_NOTAUTOTRANSLATED, $localesStatus['de_DE'], 'AutoTranslate should return that de_DE is not auto translated');
        });
    }

    public function testAutoTranslateCreatesAndSavesTranslation()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState
                ->setLocale('en_US');

            $recordA = $this->objFromFixture(LocalisedDataObject::class, 'record_a');
            $this->assertTrue($recordA->existsInLocale('de_DE'), 'Record should exist in de_DE before running autoTranslate');
            $this->assertFalse($recordA->existsInLocale('es_ES'), 'Record should not exist in es_ES before running autoTranslate');


            $status = $recordA->autoTranslate(false, true); //force translation for de_DE

            $this->assertTrue($recordA->existsInLocale('de_DE'), 'Record should exist in de_DE after running autoTranslate');
            $this->assertTrue($recordA->existsInLocale('es_ES'), 'Record should exist in es_ES after running autoTranslate');

            $newState->setLocale('de_DE');
            $recordA_DE = LocalisedDataObject::get()->byID($recordA->ID);
            $this->assertEquals('Magic of Silverstripe (translated to de_DE)', $recordA_DE->Title, 'Title should be translated to de_DE');
            $this->assertEquals('Vist Hamburg for the best Silverstripe community (translated to de_DE)', $recordA_DE->Content, 'Content should be translated to de_DE');

            $newState->setLocale('es_ES');
            $recordA_ES = LocalisedDataObject::get()->byID($recordA->ID);
            $this->assertEquals('Magic of Silverstripe (translated to es_ES)', $recordA_ES->Title, 'Title should be translated to es_ES');
            $this->assertEquals('Vist Hamburg for the best Silverstripe community (translated to es_ES)', $recordA_ES->Content, 'Content should be translated to es_ES');
        });
    }


    public function testDoPublishWorksOnVersionedObjects()
    {
        $this->markTestSkipped('to implement');
    }
}
