<?php

namespace Fromholdio\Listings\Forms;

use SilverStripe\Control\Controller;

class ListedPageGridFieldAddNewMultiClassHandler extends ListedPageGridFieldItemRequest
{
    public function Link($action = null)
    {
        if ($this->record->ID) {
            return parent::Link($action);
        } else {
            return Controller::join_links(
                $this->gridField->Link(),
                'add-multi-class',
                $this->sanitiseClassName(get_class($this->record))
            );
        }
    }

    /**
     * Sanitise a model class' name for inclusion in a link
     * @return string
     */
    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', $class);
    }
}
