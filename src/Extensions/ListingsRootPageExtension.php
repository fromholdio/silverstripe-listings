<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListingsRoots;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Listings\Forms\GridFieldConfig_ListedPages;
use Symbiote\GridFieldExtensions\GridFieldConfigurablePaginator;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class ListingsRootPageExtension extends ListingsSiteTreeExtension
{
    const ADMIN_MODE_CMS_TREE = 'cmstree';
    const ADMIN_MODE_GRIDFIELD = 'gridfield';
    const ADMIN_MODE_ADMIN = 'admin';

    protected $excludedSiteTreeClassNames;

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
    private static $administration_mode = 'cmstree';

    public static function add_to_class($class, $extensionClass, $args = null)
    {
        ListingsRoots::register_class($class);
    }

    public function getListedPagesParentIDs()
    {
        return [$this->owner->ID];
    }

    public function updateCMSFields(FieldList $fields)
    {
        // If Administration Mode is 'gridfield', add the GridField
        if ($this->owner->getAdministrationMode() === self::ADMIN_MODE_GRIDFIELD) {

            // Refer to ListedPages using the plural name of the common ancestor class
            $pluralName = $this->owner->getListedPagesCommonPluralName();

            // If this page has not been saved yet, display notification rather than GridField
            if (!$this->owner->isInDB()) {
                $listedPagesField = HeaderField::create(
                    'ListedPagesAfterSave',
                    'You can begin adding ' . $pluralName
                    . ' after first saving this ' . $this->owner->i18n_singular_name(),
                    4
                );
            }

            // Else build the GridField
            else {
                $listedPagesField = GridField::create(
                    'ListedPages',
                    $pluralName,
                    $this->getListedPages(),
                    $listedPagesFieldConfig = GridFieldConfig_ListedPages::create()
                );

                // In the absence of a real has_many/many_many relation defined,
                // ensure GridField has a model class name to get summary_fields from.
                $listedPagesField->setModelClass(
                    $this->owner->getListedPagesCommonClass()
                );

                // If orderable sort field name is supplied, apply GridFieldOrderableRows.
                $orderableSort = $this->owner->getListedPagesOrderableSort();
                if ($orderableSort) {
                    $listedPagesFieldConfig
                        ->addComponent(new GridFieldOrderableRows($orderableSort));
                }

                $listedPagesFieldConfig
                    ->removeComponentsByType([
                        GridFieldPageCount::class,
                        GridFieldPaginator::class
                    ])
                    ->addComponent(new GridFieldConfigurablePaginator());
            }

            // Add field to new Tab, named using $pluralName with spaces removed
            $fields->addFieldToTab(
                $this->owner->getListedPagesTabName(),
                $listedPagesField
            );
        }
    }

    public function getListedPagesAddNewMultiClasses()
    {
        if (count($this->owner->getListedPagesClasses()) === 1) {
            return false;
        }

        $multiClasses = [];

        $listedPageClasses = $this->owner->getListedPagesClasses();
        foreach ($listedPageClasses as $listedPageClass) {
            $multiClasses[$listedPageClass] = true;
        }

        $multiClasses = array_keys($multiClasses);

        if ($this->owner->hasMethod('updateListedPagesAddNewMultiClasses')) {
            $multiClasses = $this->owner->updateListedPagesAddNewMultiClasses($multiClasses);
        }

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
    public function getListedPagesOrderableSort()
    {
        $allowedModes = [
            self::ADMIN_MODE_ADMIN,
            self::ADMIN_MODE_GRIDFIELD
        ];

        // Return FALSE value if $administration_mode is not gridfield
        if (!in_array($this->owner->getAdministrationMode(), $allowedModes)) {
            return false;
        }

        $sort = $this->owner->config()->get('listed_pages_orderable_sort');

        if ($sort === null || $sort === 'false') {
            $sort = false;
        }

        if (!is_string($sort) && $sort !== false) {
            throw new \UnexpectedValueException(
                'A $listed_pages_orderable_sort value has been supplied of an invalid type.'
                . ' Supplied type was ' . gettype($sort) . '.'
                . ' Should be either string or false'
            );
        }

        if ($sort) {

            // Validate that $sort is a database field on Listed Pages common class
            if (!singleton($this->owner->getListedPagesCommonClass())->hasDatabaseField($sort)) {
                throw new \UnexpectedValueException(
                    'An invalid $listed_pages_orderable_sort value has been supplied for class '
                    . get_class($this->owner)
                    . ' Supplied value was "' . $sort . '"'
                );
            }
        }

        if ($this->owner->hasMethod('updateListedPagesOrderableSort')) {
            $sort = $this->owner->updateListedPagesOrderableSort($sort);
        }

        return $sort;
    }

    public function getListedPagesTabName()
    {
        $name = $this->owner->config()->get('listed_pages_tab_name');
        if (!$name) {
            $pluralName = $this->owner->getListedPagesCommonPluralName();
            $name = 'Root.' . str_replace(' ', '', $pluralName);
        }

        if ($this->owner->hasMethod('updateListedPagesTabName')) {
            $name = $this->owner->updateListedPagesTabName($name);
        }
        return $name;
    }

    /**
     * Validate and return administration mode, identifying how this
     * Listed Pages Root manages its Listed Pages
     *
     * @return string
     */
    public function getAdministrationMode()
    {
        $mode = $this->owner->config()->get('administration_mode');

        $allowedModes = [
            self::ADMIN_MODE_CMS_TREE,
            self::ADMIN_MODE_GRIDFIELD,
            self::ADMIN_MODE_ADMIN
        ];

        // Override hook
        if ($this->owner->hasMethod('updateAdministrationMode')) {
            $mode = $this->owner->updateAdministrationMode($mode);
        }

        // If not valid mode, throw exception
        if (!in_array($mode, $allowedModes)) {
            throw new \UnexpectedValueException(
                'Invalid $administration_mode value supplied for class '
                . get_class($this->owner)
                . ' Supplied mode was "' . $mode . '"'
            );
        }

        return $mode;
    }

    /**
     * If only Listed Pages are allowed to be children of this Listed Pages Root,
     * update $allowed_children to reflect this.
     *
     * @param $allowedChildren array of the class names of classes that are allowed
     * to be children of this class.
     *
     * @return null;
     */
    public function updateAllowedChildren(&$allowedChildren)
    {
        $onlyListedPages = (bool) $this->owner->config()->get('allowed_children_only_listed_pages');

        /**
         * If $onlyListedPages is true, or if $allowedChildren is 'none'
         * we want to override and ensure the Listed Pages classes of this root
         * can be children.
         */
        if ($onlyListedPages || $allowedChildren === 'none') {
            $allowedChildren = $this->owner->getListedPagesClasses();
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
            $this->owner->getListedPagesClasses()
        );
    }

    public function getExcludedSiteTreeClassNames()
    {
        if (is_null($this->owner->excludedSiteTreeClassNames)) {
            $this->owner->excludedSiteTreeClassNames = [];
        }

        $classes = [];

        $mode = $this->owner->getAdministrationMode();
        if ($mode === self::ADMIN_MODE_ADMIN || $mode === self::ADMIN_MODE_GRIDFIELD) {
            $listedPagesClasses = $this->owner->getListedPagesClasses();
            foreach ($listedPagesClasses as $listedPagesClass) {
                $listedPagesSubclasses = ClassInfo::subclassesFor($listedPagesClass);
                foreach ($listedPagesSubclasses as $listedPagesSubclass) {
                    $classes[$listedPagesSubclass] = $listedPagesSubclass;
                }
            }
        }

        return $classes;
    }

    public function augmentAllChildrenIncludingDeleted(&$stageChildren, &$context)
    {
        if ($this->shouldFilter()) {
            $stageChildren = $stageChildren->exclude('ClassName', $this->getExcludedSiteTreeClassNames());
        }
    }

    protected function shouldFilter()
    {
        $controller = Controller::curr();
        return $controller instanceof LeftAndMain
            && in_array($controller->getAction(), ["treeview", "listview", "getsubtree"]);
    }
}
