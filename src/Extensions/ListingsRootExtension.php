<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\GridFieldExtraData\GridFieldConfig_ExtraData;
use Fromholdio\Helpers\ORM\ListHelper;
use Fromholdio\Listings\ListedPages;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\HeaderField;
use Page;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\VersionedGridFieldState\VersionedGridFieldState;

class ListingsRootExtension extends ListingsIndexExtension
{
    const ADMIN_MODE_CMS_TREE = 'cmstree';
    const ADMIN_MODE_GRIDFIELD = 'gridfield';
    const ADMIN_MODE_ADMIN = 'admin';

    /*
     * Flag to set whether this Listed Pages Root can have only children
     * that are in its $listed_pages_classes array.
     *
     * If true, the standard $allowed_children config will be overriden to
     * return the value of $listed_pages_classes.
     *
     * If false, the standard $allowed_child config will check that all classes
     * in $listed_pages_classes are included (or added if missing and a white-list
     * of classes was otherwise supplied).
     *
     * @var bool True to force allowedChildren() to return $listed_pages_classes
     */
    private static $allowed_children_listed_pages_only = true;

    /**
     * Set the sort field we should use for powering the GridFieldOrderableRows component.
     * Applicable only if $administration_mode is set to 'gridfield'.
     *
     * You can supply a value FALSE to disable the component.
     *
     * Else you must supply the field name of a valid database field from the common
     * Listed Page class of this root.
     *
     * @var string|false
     */
    private static $listed_pages_orderable_sort = 'Sort';

    /**
     * You must identify how you wish to manage the Listed Pages under this
     * Listed Pages Root. Valid values are:
     *
     * 'cmstree'
     *  - display as regular child pages in the CMS Tree
     *  - add via regular Add Page form
     *
     * 'gridfield'
     *  - display in gridfield in the CMS fields of this Listed Pages Root
     *  - add via GridField (GridFieldAddNewMultiClass added by default
     *    if more than 1 Listed Page class is supplied
     *  - if $listed_pages_orderable_sort is supplied, GridFieldOrderableRows
     *    component will be added by default (disable this via FALSE value).
     *  TODO:
     *  - you need to configure Listed Page class if you want to hide it
     *    from AddPages form, CMS Tree, SiteTree::ClassName cms field, etc.
     *
     * 'admin'
     *  - Same as 'gridfield' behaviour, except that the Listed Pages are managed
     *    via a ModelAdmin
     *
     * @var string
     */
    private static $administration_mode = self::ADMIN_MODE_CMS_TREE;

    private static $listed_pages_tab_name = null;


    /**
     * Listed pages
     * ----------------------------------------------------
     */

    /**
     * @return DataList&ListHelper
     */
    public function getListedPages(): DataList
    {
        $class = $this->getOwner()->getListedPagesCommonClass();
        $localPages = $this->getOwner()->getLocalListedPages();
        $extraPages = $this->getOwner()->getExtraListedPages();
        $pages = $class::get()->filter([
            'ID' => [
                ...$localPages->filterableColumn('ID'),
                ...$extraPages->filterableColumn('ID')
            ]
        ]);
        $this->getOwner()->invokeWithExtensions('updateListedPages', $pages);
        return $pages;
    }

    /**
     * @return DataList&ListHelper
     */
    public function getLocalListedPages(): DataList
    {
        $pages = ListedPages::get(
            $this->getOwner()->getListedPagesClasses(),
            $this->getOwner()->getListedPagesParentIDs()
        );
        $this->getOwner()->invokeWithExtensions('updateLocalListedPages', $pages);
        return $pages;
    }

    public function getListedPagesParentIDs(): array
    {
        return [$this->getOwner()->ID];
    }

    /**
     * @return DataList&ListHelper
     */
    public function getExtraListedPages(): DataList
    {
        $pages = ListedPages::get(
            $this->getOwner()->getListedPagesClasses()
        );
        /** @var DataList&ListHelper $pages */
        $pages = $pages->exclude('ParentID', $this->getOwner()->getField('ID'));
        if ($pages->count() > 0)
        {
            $ids = $this->getOwner()->hasMethod('provideExtraListedPageIDs')
                ? $this->getOwner()->provideExtraListedPageIDs()
                : [];
            $pages = empty($ids)
                ? $pages->empty()
                : $pages->filter('ID', $ids);
        }
        $this->getOwner()->invokeWithExtensions('updateExtraListedPages', $pages);
        return $pages;
    }


    /**
     * Admin mode
     * ----------------------------------------------------
     */

    /**
     * Validate and return administration mode, identifying how this
     * Listed Pages Root manages its Listed Pages
     *
     * @return string
     */
    public function getAdministrationMode(): string
    {
        $mode = $this->getOwner()->config()->get('administration_mode');
        $allowedModes = [
            self::ADMIN_MODE_CMS_TREE,
            self::ADMIN_MODE_GRIDFIELD,
            self::ADMIN_MODE_ADMIN
        ];
        $this->getOwner()->invokeWithExtensions('updateAdministrationMode', $mode);

        // If not valid mode, throw exception
        if (!in_array($mode, $allowedModes)) {
            throw new \UnexpectedValueException(
                'Invalid $administration_mode value supplied for class '
                . get_class($this->getOwner())
                . ' Supplied mode was "' . $mode . '"'
            );
        }
        return $mode;
    }


    /**
     * CMS Hierarchy
     * ----------------------------------------------------
     */

    /**
     * If only Listed Pages are allowed to be children of this Listed Pages Root,
     * update $allowed_children to reflect this.
     *
     * @param $allowedChildren array of the class names of classes that are allowed
     * to be children of this class.
     *
     * @return void;
     */
    public function updateAllowedChildren(array &$allowedChildren): void
    {
        $onlyListedPages = (bool) $this->getOwner()->config()->get('allowed_children_only_listed_pages');

        /**
         * If $onlyListedPages is true, or if $allowedChildren is 'none'
         * we want to override and ensure the Listed Pages classes of this root
         * can be children.
         */
        if ($onlyListedPages || $allowedChildren === 'none') {
            $allowedChildren = $this->getOwner()->getListedPagesClasses();
            return;
        }

        /**
         * If we are not forcing only this root's Listed Pages classes,
         * and no white-list of allowed_children has been set, simply
         * return without changing the original value.
         */
        if (empty($allowedChildren)) {
            return;
        }

        // Else merge the supplied $allowed_children array
        // with this root's Listed Pages classes
        // TODO: might need to flip these or end up with multiple for same class?
        $allowedChildren = array_merge(
            $allowedChildren,
            $this->getOwner()->getListedPagesClasses()
        );
    }

    public function getExcludedSiteTreeClassNames(): array
    {
        $classes = [];
        $mode = $this->getOwner()->getAdministrationMode();
        if ($mode === self::ADMIN_MODE_ADMIN || $mode === self::ADMIN_MODE_GRIDFIELD)
        {
            $listedPagesClasses = $this->getOwner()->getListedPagesClasses();
            foreach ($listedPagesClasses as $listedPagesClass)
            {
                $listedPagesSubclasses = ClassInfo::subclassesFor($listedPagesClass);
                foreach ($listedPagesSubclasses as $listedPagesSubclass) {
                    $classes[$listedPagesSubclass] = $listedPagesSubclass;
                }
            }
        }
        return $classes;
    }

    public function augmentAllChildrenIncludingDeleted(SS_List &$stageChildren): void
    {
        $curr = Controller::curr();
        $doFilter = $curr instanceof LeftAndMain
            && in_array($curr->getAction(), ["treeview", "listview", "getsubtree"]);
        if ($doFilter) {
            $stageChildren = $stageChildren->exclude('ClassName', $this->getExcludedSiteTreeClassNames());
        }
    }


    /**
     * CMS fields
     * ----------------------------------------------------
     */

    public function getListedPagesAddNewMultiClasses(): array
    {
        $multiClasses = [];
        $listedPageClasses = $this->getOwner()->getListedPagesClasses();
        if (count($listedPageClasses) > 1) {
            foreach ($listedPageClasses as $listedPageClass) {
                $multiClasses[] = $listedPageClass;
            }
        }
        $this->getOwner()->invokeWithExtensions(
            'updateListedPagesAddNewMultiClasses',
            $multiClasses
        );
        return $multiClasses;
    }

    /**
     * Get the sort field we should use for powering the GridFieldOrderableRows component.
     * Applicable only if $administration_mode is set to 'gridfield'.
     *
     * You can supply a value FALSE to disable the component.
     *
     * Else you must supply the field name of a valid database field from the common
     * Listed Page class of this root.
     *
     * @return string|false Either the field name, or boolean FALSE value.
     */
    public function getListedPagesOrderableSort(): ?string
    {
        $sort = null;
        $allowedModes = [self::ADMIN_MODE_ADMIN, self::ADMIN_MODE_GRIDFIELD];
        $mode = $this->getOwner()->getAdministrationMode();
        if (in_array($mode, $allowedModes))
        {
            $sort = $this->getOwner()->config()->get('listed_pages_orderable_sort');
            if (empty($sort)) {
                $sort = null;
            }
            else {
                // Validate that $sort is a database field on Listed Pages common class
                $commonClass = $this->getOwner()->getListedPagesCommonClass();
                if (!$commonClass::singleton()->hasDatabaseField($sort)) {
                    throw new \UnexpectedValueException(
                        'An invalid $listed_pages_orderable_sort value has been supplied for class '
                        . get_class($this->getOwner())
                        . ' Supplied value was "' . $sort . '"'
                    );
                }
            }
        }
        $this->getOwner()->invokeWithExtensions('updateListedPagesOrderableSort', $sort);
        return $sort;
    }

    public function getListedPagesTabName(): ?string
    {
        $name = $this->getOwner()->config()->get('listed_pages_tab_name');
        if (empty($name)) {
            $name = null;
        }
        else {
            $pluralName = $this->getOwner()->getListedPagesCommonPluralName();
            $name = 'Root.' . str_replace(' ', '', $pluralName);
        }
        $this->getOwner()->invokeWithExtensions('updateListedPagesTabName', $name);
        return $name;
    }

    public function updateCMSFields(FieldList $fields): void
    {
        // If Administration Mode is 'gridfield', add the GridField
        if ($this->getOwner()->getAdministrationMode() === self::ADMIN_MODE_GRIDFIELD)
        {
            // Refer to ListedPages using the plural name of the common ancestor class
            $pluralName = $this->getOwner()->getListedPagesCommonPluralName();

            // If this page has not been saved yet, display notification rather than GridField
            if (!$this->getOwner()->isInDB()) {
                $listedPagesField = HeaderField::create(
                    'ListedPagesAfterSave',
                    'You can begin adding ' . $pluralName
                    . ' after first saving this ' . $this->getOwner()->i18n_singular_name(),
                    4
                );
            }

            // Else build the GridField
            else {
                $multiClasses = $this->getOwner()->getListedPagesAddNewMultiClasses();
                $sort = $this->getOwner()->getListedPagesOrderableSort();

                $pagesField = GridField::create(
                    'ListedPages',
                    $pluralName,
                    $this->getOwner()->getLocalListedPages(),
                    $pagesConfig = GridFieldConfig_ExtraData::create(
                        $sort, 20, false, false, ['ParentID' => $this->getOwner()->getField('ID')], true
                    )
                );

                $pagesConfig->addComponent(new VersionedGridFieldState());
                $pagesConfig->addComponent(GridFieldFilterHeader::create());

//                $listedPagesFieldConfig
//                    ->addComponent(new GridFieldListedPagesAddNewButton())
//                    ->addComponent(new GridFieldListedPagesEditButton())
//                    ->addComponent(new GridField_ActionMenu());

                // In the absence of a real has_many/many_many relation defined,
                // ensure GridField has a model class name to get summary_fields from.
                $pagesField->setModelClass(
                    $this->getOwner()->getListedPagesCommonClass()
                );

//                $detailForm = new ListedPageGridFieldDetailForm(
//                    'ListedPageDetail',
//                    false,
//                    false,
//                    ['ParentID' => $this->getOwner()->getField('ID')],
//                    true
//                );
//                $detailForm->setItemRequestClass(ListedPageGridFieldItemRequest::class);
//                $pagesConfig->removeComponentsByType(GridFieldDetailForm::class);
//                $pagesConfig->addComponent($detailForm);

                if (count($multiClasses) > 0) {
                    $pagesConfig->addMultiAdder($multiClasses);
                }
            }

            // Add field to new Tab, named using $pluralName with spaces removed
            $fields->addFieldToTab(
                $this->getOwner()->getListedPagesTabName(),
                $pagesField
            );
        }
    }


    /**
     * @return Page&ListingsRootExtension
     */
    public function getOwner(): Page
    {
        /** @var Page $owner */
        $owner = parent::getOwner();
        return $owner;
    }
}
