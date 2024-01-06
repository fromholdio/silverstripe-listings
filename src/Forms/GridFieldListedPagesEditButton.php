<?php

namespace Fromholdio\Listings\Forms;

use SilverStripe\Forms\GridField\GridFieldEditButton;

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
