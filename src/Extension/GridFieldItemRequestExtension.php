<?php

namespace S2Hub\AutoTranslate\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;

/**
 * Bridges DataObject::updateCMSActions() into GridFieldDetailForm_ItemRequest
 * so the AutoTranslate action also shows up when a record is edited through
 * a ModelAdmin / GridField detail form (not just through LeftAndMain).
 */
class GridFieldItemRequestExtension extends Extension
{
    public function updateItemEditForm(Form $form): void
    {
        $record = $this->getOwner()->getRecord();
        if (!$record || !$record->hasExtension(AutoTranslate::class)) {
            return;
        }

        $actions = $form->Actions();

        $namesBefore = [];
        foreach ($actions as $field) {
            $namesBefore[$field->getName()] = true;
        }

        $record->extend('updateCMSActions', $actions);

        $newFields = [];
        foreach ($actions as $field) {
            if (!isset($namesBefore[$field->getName()])) {
                $newFields[] = $field;
            }
        }

        // Position newly-added actions so they render between MajorActions
        // (Save/Publish) and Fluent's FluentMenu/RightGroup, rather than at
        // the far right after the pagination controls.
        $anchor = $actions->fieldByName('FluentMenu')
            ? 'FluentMenu'
            : ($actions->fieldByName('RightGroup') ? 'RightGroup' : null);

        foreach ($newFields as $field) {
            if ($anchor === null) {
                continue;
            }
            $actions->remove($field);
            $actions->insertBefore($anchor, $field);
        }
    }
}
