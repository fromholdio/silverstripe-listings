<?php

namespace Fromholdio\Listings\Forms;

use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class ListedPageGridFieldItemRequest extends VersionedGridFieldItemRequest
{
    private static $allowed_actions = [
        'ItemEditForm'
    ];

    public function ItemEditForm()
    {
        if (!empty($this->getRecord()) && !$this->getRecord()->ParentID) {
            if ($this->getDefaultParentID() !== null) {
                $this->getRecord()->ParentID = $this->getDefaultParentID();
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
