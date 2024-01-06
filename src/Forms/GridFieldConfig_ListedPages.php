<?php

namespace SilverStripe\Listings\Forms;

use Fromholdio\GridFieldExtraData\GridFieldConfig_ExtraData;
use Fromholdio\Listings\Forms\ListedPageGridFieldAddNewMultiClassHandler;
use Fromholdio\Listings\Forms\ListedPageGridFieldDetailForm;
use Fromholdio\Listings\Forms\ListedPageGridFieldItemRequest;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Versioned\GridFieldArchiveAction;
use SilverStripe\Versioned\VersionedGridFieldState\VersionedGridFieldState;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

class GridFieldConfig_ListedPages extends GridFieldConfig_ExtraData
{
    /**
     * @param int|null $itemsPerPage
     */
    public function __construct(
        ?string $orderableField = null,
        ?int $itemsPerPage = 20,
        bool $showPagination = true,
        bool $showAdd = true,
        ?array $extraData = null,
        bool $doWrite = false,
        ?bool $removeRelation = true
    ) {
        parent::__construct(
            $orderableField,
            $itemsPerPage,
            $showPagination,
            $showAdd,
            $extraData,
            $doWrite
        );

        $this->removeComponentsByType([
            GridFieldPrintButton::class,
            GridFieldExportButton::class,
            GridFieldDetailForm::class,
            GridFieldDeleteAction::class,
            GridFieldTitleHeader::class
        ]);

        $detailForm = new ListedPageGridFieldDetailForm(
            null,
            $showPagination,
            $showAdd,
            $extraData,
            $doWrite
        );
        $detailForm->setItemRequestClass(ListedPageGridFieldItemRequest::class);
        $this->addComponent($detailForm);
        $this->addComponent(new VersionedGridFieldState());
        $this->addComponent(new GridFieldArchiveAction());
        $this->addComponent(new GridField_ActionMenu());
//        $this->addComponent(GridFieldToolbarHeader::create());
        $this->addComponent($sort = GridFieldSortableHeader::create());
        $this->addComponent($filter = GridFieldFilterHeader::create());

        $sort->setThrowExceptionOnBadDataType(false);
        $filter->setThrowExceptionOnBadDataType(false);
    }

    public function addMultiAdder(array $classes): GridFieldConfig_ExtraData
    {
        $adder = new GridFieldAddNewMultiClass('buttons-before-left');
        $adder->setClasses($classes);
        $adder->setItemRequestClass(
            ListedPageGridFieldAddNewMultiClassHandler::class
        );
        $this->addComponent($adder);
        $this->removeComponentsByType(GridFieldAddNewButton::class);
        return $this;
    }
}
