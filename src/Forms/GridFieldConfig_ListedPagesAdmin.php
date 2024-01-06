<?php

namespace SilverStripe\Listings\Forms;

use Fromholdio\Listings\Forms\ListedPageGridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Versioned\GridFieldArchiveAction;
use SilverStripe\Versioned\VersionedGridFieldState\VersionedGridFieldState;

class GridFieldConfig_ListedPagesAdmin extends GridFieldConfig
{
    /**
     * @param int|null $itemsPerPage
     */
    public function __construct($itemsPerPage = null)
    {
        parent::__construct();

        $this->removeComponentsByType(GridFieldExportButton::class);
        $this->removeComponentsByType(GridFieldPrintButton::class);
        $this->addComponent(new GridFieldButtonRow('before'));
        $this->addComponent(new GridFieldAddNewButton('buttons-before-left'));
        $this->addComponent(new GridFieldToolbarHeader());
        $this->addComponent(new GridFieldSortableHeader());
        $this->addComponent(new GridFieldFilterHeader());
        $this->addComponent(new GridFieldDataColumns());
        $this->addComponent(new VersionedGridFieldState());
        $this->addComponent(new GridFieldEditButton());
        $this->addComponent(new GridFieldArchiveAction());
        $this->addComponent(new ListedPageGridFieldDetailForm());
        $this->addComponent(new GridFieldPageCount('toolbar-header-right'));
        $this->addComponent($pagination = new GridFieldPaginator($itemsPerPage));
//        $this->addComponent(new GridFieldSiteTreeState());

        $pagination->setThrowExceptionOnBadDataType(true);
    }
}
