<?php

namespace SilverStripe\Listings\Forms;

use Fromholdio\Listings\Forms\GridFieldListedPagesAddNewButton;
use Fromholdio\Listings\Forms\GridFieldListedPagesEditButton;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Versioned\VersionedGridFieldState\VersionedGridFieldState;

class GridFieldConfig_ListedPages extends GridFieldConfig
{
    /**
     * @param int|null $itemsPerPage
     */
    public function __construct($itemsPerPage = null)
    {
        parent::__construct($itemsPerPage);

        $this->addComponent(new GridFieldButtonRow('before'));
        $this->addComponent(new GridFieldListedPagesAddNewButton('buttons-before-left'));
        $this->addComponent(new GridFieldToolbarHeader());
        $this->addComponent(new GridFieldSortableHeader());
        $this->addComponent(new GridFieldFilterHeader());
        $this->addComponent(new GridFieldDataColumns());
        $this->addComponent(new VersionedGridFieldState());
        $this->addComponent(new GridFieldListedPagesEditButton());
        $this->addComponent(new GridFieldPageCount('toolbar-header-right'));
        $this->addComponent($pagination = new GridFieldPaginator($itemsPerPage));
//        $this->addComponent(new GridFieldSiteTreeState());

        $pagination->setThrowExceptionOnBadDataType(true);
    }
}
