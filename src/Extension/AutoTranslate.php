<?php

namespace S2Hub\AutoTranslate\Extension;

use Exception;
use JsonException;
use S2Hub\CmsPopup\Forms\CmsModalBatchAction;
use S2Hub\AutoTranslate\Handler\AITranslateBatchHandler;
use S2Hub\AutoTranslate\Helper\FluentHelper;
use S2Hub\AutoTranslate\Translator\AITranslationStatus;
use S2Hub\AutoTranslate\Translator\Translatable;
use S2Hub\AutoTranslate\Translator\TranslatableFactory;
use RuntimeException;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\Service\CopyToLocaleService;
use TractorCow\Fluent\State\FluentState;

class AutoTranslate extends Extension
{
    /**
     * @config
     */
    private static $db = [
        'IsAutoTranslated' => 'Boolean',
        'LastTranslation' => 'Datetime',
    ];

    /**
     * @config
     */
    private static $field_include = [
        'IsAutoTranslated',
        //LastTranslation timestamp is used in default locale to mark a change; in other locales to remember the time of translation
        'LastTranslation',
    ];

    /** @internal not a config property */
    private static ?Translatable $translator = null;

    public function canTranslate(): bool
    {
        return $this->hasDefaultLocale() && $this->getOwner()->canEdit();
    }


    public function onBeforeWrite()
    {
        if ($this->getOwner()->Locale && $this->hasDefaultLocale() && $this->getOwner()->isChanged()) {
            $this->getOwner()->LastTranslation = DBDatetime::now()->getValue();
        }
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName('LastTranslation');
        if ($this->hasDefaultLocale()) {
            $fields->removeByName('IsAutoTranslated');
        }

        if (!$this->hasDefaultLocale()) {
            $isAutoTranslated = $fields->dataFieldByName('IsAutoTranslated');
            if (!$isAutoTranslated) {
                $isAutoTranslated = CheckboxField::create('IsAutoTranslated');
            }

            if ($this->getOwner()->LastTranslation) {
                $lastTranslation = DBDateTime::create()->setValue($this->getOwner()->LastTranslation);
                $isAutoTranslated->setTitle($this->getOwner()->fieldLabel('IsAutoTranslated') . '; Last Translation: ' . $lastTranslation->Nice());
                $fields->insertAfter('Title', $isAutoTranslated);
            }
        }
    }

    public function updateCMSActions(FieldList $actions)
    {
        if (!$this->getOwner()->canTranslate()) {
            return;
        }

        $buttonTitle = _t(
            self::class . '.TRANSLATE_MODAL_TITLE',
            'Auto Translate'
        );

        $owner = $this->getOwner();
        $translateAction = CmsModalBatchAction::forHandler(
            AITranslateBatchHandler::class,
            ['ClassName' => $owner->ClassName, 'ID' => $owner->ID],
            $buttonTitle
        )
            ->setModalTitle($buttonTitle . ': ' . $owner->getTitle())
            ->setButtonIcon('font-icon-translatable')
            ->setSubmitLabel(_t(self::class . '.START_TRANSLATION', 'Start Translation'))
            ->setBaseQueue([
                [
                    'id' => $owner->ID,
                    'title' => $owner->getTitle(),
                    'className' => $owner->ClassName,
                ],
            ]);

        $actions->push($translateAction);
    }

    /**
     * @throws RuntimeException
     * @throws JsonException
     */
    public function autoTranslate(
        bool $doPublish = false,
        bool $forceTranslation = false,
        array $limit_locales = [],
        bool $includeOwned = false
    ): AITranslationStatus {
        $this->checkIfAutoTranslateFieldsAreTranslatable();
        $status = AITranslationStatus::create($this->getOwner());

        /** @var DataObject $owner */
        $owner = $this->getOwner();
        if (!$this->hasDefaultLocale()) {
            return $status->setStatus(AITranslationStatus::STATUS_ERROR)->setMessage(AITranslationStatus::ERRORMSG_NOTDEFAULTLOCALE);
        }

        $data = $this->getTranslatableFields();

        $translator = self::getTranslator();

        $locales = Locale::get()->exclude(['Locale' => Locale::getDefault()->Locale]);
        if ($limit_locales !== []) {
            $locales = $locales->filter(['Locale' => $limit_locales]);
        }

        foreach ($locales as $locale) {
            $status = FluentState::singleton()
                ->withState(function (FluentState $state) use (
                    $locale,
                    $translator,
                    $status,
                    $data,
                    $doPublish,
                    $forceTranslation
                ) {
                    $state->setLocale($locale->Locale);
                    return Versioned::withVersionedMode(function () use (
                        $locale,
                        $translator,
                        $status,
                        $data,
                        $doPublish,
                        $forceTranslation
                    ) {
                        Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
                        return $this->performTranslation(
                            $translator,
                            $status,
                            $locale,
                            $data,
                            $doPublish,
                            $forceTranslation
                        );
                    });
                });
        }

        if ($includeOwned) {
            $this->translateOwnedObjects($status, $doPublish, $forceTranslation, $limit_locales);
        }

        $status->aggregateStatus();
        return $status;
    }

    /**
     * Iterate all transitively owned DataObjects (e.g. ElementalArea → BaseElements,
     * Links, media records) and run autoTranslate() on each that has the extension.
     * Per-locale statuses from each owned object are merged into the parent $status.
     */
    private function translateOwnedObjects(
        AITranslationStatus $status,
        bool $doPublish,
        bool $forceTranslation,
        array $limit_locales
    ): void {
        $ownedObjects = $this->getOwner()->findRelatedObjects('owns', true);
        foreach ($ownedObjects as $ownedObject) {
            if (!$ownedObject->hasExtension(AutoTranslate::class)) {
                continue;
            }
            if (!$ownedObject->hasMethod('autoTranslate')) {
                continue;
            }

            $ownedStatus = $ownedObject->autoTranslate($doPublish, $forceTranslation, $limit_locales, false);
            $status->mergeOwnedStatus($ownedStatus);
        }
    }


    /**
     * get all fields that are translatable
     * @return array
     */
    public function getTranslatableFields(): array
    {
        $fields = FluentHelper::getLocalisedDataFromDataObject($this->getOwner(), $this->getOwner()->Locale);
        if (array_key_exists('ID', $fields)) {
            unset($fields['ID']);
        }

        if (array_key_exists('LastTranslation', $fields)) {
            unset($fields['LastTranslation']);
        }

        unset($fields['IsAutoTranslated']);
        return $fields;
    }

    public function hasDefaultLocale(): bool
    {
        return $this->getOwner()->Locale === Locale::getDefault()->Locale;
    }

    /**
     * Check if the required fields are configured as translated fields
     * @return void
     * @throws RuntimeException
     */
    public function checkIfAutoTranslateFieldsAreTranslatable()
    {
        if (!$this->getOwner()->hasExtension(FluentExtension::class)) {
            throw new RuntimeException($this->getOwner()->ClassName . ' does not have FluentExtension');
        }

        foreach (['IsAutoTranslated', 'LastTranslation'] as $field) {
            $isLocalised = false;
            foreach ($this->getOwner()->getLocalisedTables() as $localisedTable) {
                if (in_array($field, $localisedTable)) {
                    $isLocalised = true;
                }
            }

            if (!$isLocalised) {
                throw new RuntimeException($this->getOwner()->ClassName . ' does not have ' . $field . ' as translatable field');
            }
        }
    }

    /**
     * When this module is added to an existing project, the LastTranslation field is not set for existing objects.
     * If not set, the object will be translated all the time, as LastEdited is not localised.
     *
     * @return DataObject
     */
    public function fixLastTranslationForDefaultLocale(): DataObject
    {
        $owner = $this->getOwner();
        if ($this->hasDefaultLocale() && $owner->LastTranslation === null) {
            $owner->LastTranslation = $owner->LastEdited;
            $owner->write();
            if ($owner->hasExtension(Versioned::class) && $owner->isPublished()) {
                /** @var Versioned|DataObject $owner */
                $owner->publishSingle();
            }
        }

        return $owner;
    }

    private function performTranslation(
        Translatable $translator,
        AITranslationStatus $status,
        Locale $locale,
        array $data,
        bool $doPublish = false,
        bool $forceTranslation = false
    ): AITranslationStatus {
        $owner = $this->getOwner();
        $existsInLocale = $owner->existsInLocale($locale->Locale);

        try {
            //get translated dataobject
            /** @var DataObject $translatedObject */
            $translatedObject = $this->findOrCreateTranslatedObject($locale->Locale);
        } catch (Exception $e) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_ERROR . ': ' . $e->getMessage());
            return $status;
        }

        //if translated do is newer than original, do not translate. It is already translated
        if ($existsInLocale && $translatedObject->LastTranslation > $owner->LastTranslation && !$forceTranslation) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_ALREADYTRANSLATED);
            return $status;
        }

        //if translated do is not set to auto translate, do not translate as it was edited manually
        if ($existsInLocale && !$translatedObject->IsAutoTranslated) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_NOTAUTOTRANSLATED);
            return $status;
        }

        if ($data !== []) {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            $translatedDataOrig = $translator->translate(
                $json,
                Locale::getDefault()->Locale,
                $locale->Locale
            );
            $translatedData = json_decode($translatedDataOrig, true);

            if (!$translatedData) {
                $status->addLocale($locale->Locale, AITranslationStatus::STATUS_NOTHINGTOTRANSLATE);
                return $status;
            }

            if (!is_array($translatedData)) {
                $status->addLocale($locale->Locale, AITranslationStatus::STATUS_ERROR);
                $status->setSource($json);
                $status->setAiResponse($translatedDataOrig);
                $status->setData($translatedData);
                return $status;
            }

            $translatedObject->update($translatedData);
        }

        $translatedObject->IsAutoTranslated = true;
        $translatedObject->LastTranslation = DBDatetime::now()->getValue();

        try {
            $translatedObject->write(false, false, false, false, true);
        } catch (Exception $e) {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_ERROR . ': ' . $e->getMessage());
            return $status;
        }

        $isPublishableObject = $translatedObject->hasExtension(Versioned::class) && $owner->hasExtension(FluentVersionedExtension::class);
        $ownerIsPublished = $isPublishableObject && $owner->isPublishedInLocale($owner->Locale);

        if ($doPublish && $isPublishableObject && $ownerIsPublished) {
            /** @var Versioned|DataObject $translatedObject */
            $translatedObject->publishSingle();
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_PUBLISHED);
        } else {
            $status->addLocale($locale->Locale, AITranslationStatus::STATUS_TRANSLATED);
        }

        return $status;
    }

    public static function getTranslator(): Translatable
    {
        if (!self::$translator instanceof Translatable) {
            self::$translator = self::getDefaultTranslator();
        }

        return self::$translator;
    }

    public static function setTranslator(Translatable $translator): void
    {
        self::$translator = $translator;
    }

    /**
     * Fallback if no translator is set. Use ChatGPT for now
     *
     * @return Translatable
     * @throws RuntimeException
     */
    public static function getDefaultTranslator(): Translatable
    {
        return TranslatableFactory::getInstance();
    }

    /**
     * @throws RuntimeException
     */
    private function findOrCreateTranslatedObject(string $locale): DataObject
    {
        $owner = $this->getOwner();
        $obj = DataObject::get($owner->ClassName)->byID($owner->ID);

        if ($obj) {
            return $obj;
        }
        //no object, are we in live and stage exists?

        //get from stage...
        return Versioned::withVersionedMode(function () use ($owner, $locale) {
            Versioned::set_reading_mode('Stage.' . Versioned::DRAFT);
            $obj = DataObject::get($owner->ClassName)->byID($owner->ID);

            if ($obj) {
                return $obj;
            }
            //we need to translate the object...
            CopyToLocaleService::singleton()->copyToLocale($owner->ClassName, $owner->ID, $owner->Locale, $locale);

            $obj = DataObject::get($owner->ClassName)->byID($owner->ID);
            if (!$obj) {
                throw new RuntimeException('Unable to find ' . $owner->ClassName . ' #' . $owner->ID . ' in locale ' . $locale);
            }
            return $obj;
        });
    }
}
