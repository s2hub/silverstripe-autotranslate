<?php

namespace S2Hub\AutoTranslate\Extension;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;

/**
 * Extension to add Save and Delete buttons to e.g. SiteConfig, if AutoTranslate extension is applied.
 * See https://github.com/s2hub/silverstripe-autotranslate/issues/6
 */
class SingleRecordAllCMSActions extends Extension
{
    public function getAllCMSActions()
    {
        $actions = FieldList::create();
        if ($this->getOwner()->hasMethod('canEdit') && $this->getOwner()->canEdit()) {
            $actions->push(
                FormAction::create('save', _t(LeftAndMain::class . '.SAVE', 'Save'))
                    ->addExtraClass('btn btn-primary')
                    ->setIcon('add-circle')
            );
        }
        if ($this->getOwner()->hasMethod('canDelete') && $this->getOwner()->canDelete()) {
            $actions->push(
                FormAction::create('delete', _t(LeftAndMain::class . '.DELETE', 'Delete'))
                    ->addExtraClass('btn btn-secondary')
            );
        }
        $this->getOwner()->extend('updateCMSActions', $actions);
        return $actions;
    }
}
