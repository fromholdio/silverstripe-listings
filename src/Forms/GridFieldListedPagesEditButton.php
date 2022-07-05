<?php

namespace Fromholdio\Listings\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

/**
 * Swaps the GridField Link out for the SiteTree edit link using {@link SiteTree::CMSEditLink()}.
 *
 * Bypasses GridFieldDetailForm
 *
 * @author Michael Strong <mstrong@silverstripe.org>
 **/
class GridFieldListedPagesEditButton extends GridFieldEditButton
{

    public function getUrl($gridField, $record, $columnName, $addState = true)
    {
        $link = $record->CMSEditLink();
        if ($addState) {
            $link = $this->getStateManager()->addStateToURL($gridField, $link);
        }
        return $link;
    }
}
