<?php

namespace Fromholdio\Listings\Forms;

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
    /**
     * @param  GridField $gridField
     * @param  DataObject $record
     * @param  string $columnName
     * @return string - the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        // No permission checks - handled through GridFieldDetailForm
        // which can make the form readonly if no edit permissions are available.

        $data = ArrayData::create([
            'Link' => $record->CMSEditLink(),
            'ExtraClass' => $this->getExtraClass(),
        ]);

        return $data->renderWith(GridFieldEditButton::class);
    }
}
