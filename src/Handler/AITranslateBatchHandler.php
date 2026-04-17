<?php

namespace S2Hub\AutoTranslate\Handler;

use S2Hub\CmsPopup\Handler\CmsPopupBatchHandler;
use S2Hub\AutoTranslate\Extension\AutoTranslate;
use S2Hub\AutoTranslate\Translator\AITranslationStatus;
use Psr\Log\LoggerInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class AITranslateBatchHandler extends CmsPopupBatchHandler
{
    public function getBatchFormFields(): FieldList
    {
        $locales = Locale::get()->exclude(['IsGlobalDefault' => 1]);
        $localeMap = [];
        foreach ($locales as $locale) {
            $localeMap[$locale->Locale] = $locale->Title . ' (' . $locale->Locale . ')';
        }

        return FieldList::create([
            CheckboxSetField::create('locales', _t(AutoTranslate::class . '.TARGET_LANGUAGES', 'Target Languages'), $localeMap)
                ->setValue(array_keys($localeMap))
                ->addExtraClass('list-unstyled'),
            CheckboxField::create('doPublish', _t(AutoTranslate::class . '.PUBLISH_AFTER', 'Publish after translation'))
                ->setValue(true),
            CheckboxField::create('onlyNew', _t(AutoTranslate::class . '.ONLY_NEW', 'Only translate new content'))
                ->setValue(true),
            CheckboxField::create('recursive', _t(AutoTranslate::class . '.RECURSIVE', 'Also translate child pages')),
        ]);
    }

    public function getQueueItems(HTTPRequest $request): array
    {
        $className = $request->getVar('ClassName');
        $id = (int) $request->getVar('ID');

        if (!$className || !$id) {
            return [];
        }

        $object = DataObject::get($className)->byID($id);
        if (!$object || !$object->canEdit()) {
            return [];
        }

        $children = [];
        if ($object instanceof SiteTree) {
            $this->collectChildren($object, $children, 0);
        }

        return $children;
    }

    public function processItem(HTTPRequest $request): HTTPResponse
    {
        $body = json_decode($request->getBody(), true);
        if (!$body) {
            return $this->jsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $className = $body['className'] ?? null;
        $id = (int) ($body['id'] ?? 0);
        $locales = $body['locales'] ?? [];
        $doPublish = (bool) ($body['doPublish'] ?? false);
        $forceTranslation = !(bool) ($body['onlyNew'] ?? true);

        if (!$className || !$id || $locales === []) {
            return $this->jsonResponse(['error' => 'className, id and locales required'], 400);
        }

        $object = FluentState::singleton()
            ->withState(function (FluentState $state) use ($className, $id) {
                $state->setLocale(Locale::getDefault()->Locale);
                return $className::get()->byID($id);
            });

        if (!$object) {
            return $this->jsonResponse(['error' => 'Object not found'], 404);
        }

        if (!$object->hasExtension(AutoTranslate::class)) {
            return $this->jsonResponse(['error' => 'Object does not have AutoTranslate extension'], 400);
        }

        if (!$object->canTranslate()) {
            return $this->jsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            /** @var AITranslationStatus $status */
            $status = $object->autoTranslate($doPublish, $forceTranslation, $locales, true);
        } catch (\Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                'AITranslateBatchHandler::processItem - ' . $e->getMessage(),
                ['exception' => $e]
            );
            return $this->jsonResponse([
                'id' => $object->ID,
                'title' => $object->getTitle(),
                'className' => $object->ClassName,
                'status' => 'Error',
                'message' => $e->getMessage(),
                'details' => [],
            ], 500);
        }

        $details = [];
        foreach ($status->getLocalesTranslatedTo() as $locale => $localeStatus) {
            $details[] = [
                'label' => $locale,
                'status' => $localeStatus,
                'level' => AITranslationStatus::getLogLevel($localeStatus),
            ];
        }

        return $this->jsonResponse([
            'id' => $object->ID,
            'title' => $object->getTitle(),
            'className' => $object->ClassName,
            'status' => $status->getStatus(),
            'message' => $status->getMessage(),
            'details' => $details,
        ]);
    }

    private function collectChildren(SiteTree $parent, array &$result, int $depth): void
    {
        $children = $parent->Children();
        foreach ($children as $child) {
            $hasAutoTranslate = $child->hasExtension(AutoTranslate::class);
            $result[] = [
                'id' => $child->ID,
                'title' => $child->getTitle(),
                'className' => $child->ClassName,
                'depth' => $depth,
                'enabled' => $hasAutoTranslate,
                'disabledReason' => $hasAutoTranslate ? null : 'no AutoTranslate',
            ];
            if ($child->Children()->count() > 0) {
                $this->collectChildren($child, $result, $depth + 1);
            }
        }
    }

    private function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        return HTTPResponse::create()
            ->setStatusCode($status)
            ->addHeader('Content-Type', 'application/json')
            ->setBody((string) json_encode($data));
    }
}
