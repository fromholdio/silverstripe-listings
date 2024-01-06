<?php

namespace Fromholdio\Listings\Forms;

use Fromholdio\GridFieldExtraData\GridFieldExtraDataDetailForm;
use SilverStripe\Forms\GridField\GridField_URLHandler;

class ListedPageGridFieldDetailForm extends GridFieldExtraDataDetailForm implements GridField_URLHandler
{
    protected $defaultParentID;

    public function setDefaultParentID($id)
    {
        $this->defaultParentID = (int) $id;
        return $this;
    }

    public function getDefaultParentID()
    {
        if (is_int($this->defaultParentID) && $this->defaultParentID >= 0) {
            return $this->defaultParentID;
        }
        return null;
    }
}
