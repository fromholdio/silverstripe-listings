<?php

namespace Fromholdio\Listings\Forms;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class ListedPageGridFieldItemRequest extends VersionedGridFieldItemRequest
{
    private static $allowed_actions = [
        'ItemEditForm'
    ];

    public function ItemEditForm()
    {
        $record = $this->getRecord();
        $fields = $this->component->getFields();

        $doPlaceCMSFieldsUnderRootTabSet = empty($fields) && !empty($record)
            && $record->hasMethod('doPlaceCMSFieldsUnderListedPagesAdminRootTabSet')
            && $record->doPlaceCMSFieldsUnderListedPagesAdminRootTabSet();

        if ($doPlaceCMSFieldsUnderRootTabSet)
        {
            $singularName = empty($record) ? 'Page' : $record->i18n_singular_name();
            $cmsTabSet = TabSet::create('PageTabSet', $singularName);

            $fields = FieldList::create(
                TabSet::create('Root',
                    $cmsTabSet = TabSet::create('CMSFieldsTabSet', $singularName),
                    $settingsTabSet = TabSet::create(
                        'SettingsTabSet', _t(self::class . '.SETTINGSTABSET', 'Settings')
                    )
                )
            );

            $cmsFields = $this->record->getCMSFields();
            $rootTabSet = $cmsFields->fieldByName('Root');
            foreach ($rootTabSet->Tabs() as $tab) {
                $cmsTabSet->push($tab);
            }

            if (
                !empty($record)
                && $record->hasMethod('getSettingsFields')
                && $record->hasMethod('doAddSettingsFieldsAsListedPagesAdminTab')
                && $record->doAddSettingsFieldsAsListedPagesAdminTab()
            ) {
                $settingsFields = $record->getSettingsFields();
                $settingsRootTabSet = $settingsFields->fieldByName('Root');
                foreach ($settingsRootTabSet->Tabs() as $tab) {
                    $settingsTabSet->push($tab);
                }
            }
            else {
                $fields->removeByName('SettingsTabSet');
            }

            $this->component->setFields($fields);
        }

        if (!empty($record) && empty($record->getField('ParentID'))) {
            if (!empty($this->getDefaultParentID())) {
                $record->setField('ParentID', $this->getDefaultParentID());
            }
        }

        return parent::ItemEditForm();
    }

    public function pushCurrent()
    {
        $this->getController->pushCurrent();
    }

    public function getDefaultParentID()
    {
        if (method_exists($this->component, 'getDefaultParentID')) {
            $defaultParentID = $this->component->getDefaultParentID();
            if ($defaultParentID !== null) {
                return $defaultParentID;
            }
        }

        $defaultRoot = $this->getRecord()->getDefaultListingsRoot();
        if (!$defaultRoot || !$defaultRoot->exists()) {
            return 0;
        }
        return (int) $defaultRoot->ID;
    }
}
