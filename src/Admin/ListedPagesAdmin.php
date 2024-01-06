<?php

namespace Fromholdio\Listings\Admin;

use Fromholdio\CommonAncestor\CommonAncestor;
use Fromholdio\Listings\Extensions\ListingsRootExtension;
use Fromholdio\Listings\Extensions\ListedPageExtension;
use Fromholdio\Listings\Forms\ListedPageGridFieldAddNewMultiClassHandler;
use Fromholdio\Listings\Forms\ListedPageGridFieldItemRequest;
use Fromholdio\Listings\ListedPages;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Listings\Forms\GridFieldConfig_ListedPages;
use SilverStripe\Listings\Forms\GridFieldConfig_ListedPagesAdmin;
use SilverStripe\View\Requirements;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

abstract class ListedPagesAdmin extends ModelAdmin
{
    public $showImportForm = false;
    public $showSearchForm = false;

    private static $url_handlers = [
        '$ModelClass/$Action' => 'handleAction'
    ];

    public function init()
    {
        parent::init();
        Requirements::javascript('silverstripe/cms: client/dist/js/bundle.js');
        Requirements::css('silverstripe/cms: client/dist/styles/bundle.css');
    }

    public function getManagedModels()
    {
        $models = static::config()->get('managed_models');
        if (is_string($models)) {
            $models = array($models);
        }
        if (!count($models)) {
            user_error(
                'ModelAdmin::getManagedModels():
				You need to specify at least one DataObject subclass in private static $managed_models.
				Make sure that this property is defined, and that its visibility is set to "private"',
                E_USER_ERROR
            );
        }

        // Normalize models to have their model class in array key
        foreach ($models as $k => $v) {
            if (is_numeric($k)) {

                if ($this->isIndexModel($v)) {
                    throw new \UnexpectedValueException(
                        'When defining an "index" $managed_model in ListedPagesAdmin '
                        . 'you must declare it as an assoc array with "title" option.'
                    );
                }

                $models[$v] = array('title' => singleton($v)->i18n_plural_name());
                unset($models[$k]);
            }
        }

        foreach ($models as $k => $v) {
            if ($this->isIndexModel($k)) {
                $index = explode(':', $k);
                if (isset($index[1])) {
                    $listedPageClass = $index[1];
                    ListedPages::validate_class($listedPageClass);
                    $models[$k]['indexClass'] = $listedPageClass;
                }
            }
        }

        return $models;
    }

    protected function isIndexModel($class = null)
    {
        if (is_null($class)) {
            $class = $this->modelClass;
        }
        return (substr($class, 0, 5) === 'index');

//        if (substr($class, 0, 5) === 'index') {
//            return true;
//        }
//        $models = $this->getManagedModels();
//        $options = $models[$class];
//        if (isset($options['isIndex'])) {
//            return $options['isIndex'];
//        }
//        return false;
    }

    public function getEditForm($id = null, $fields = null)
    {
        if ($this->isIndexModel()) {
            return $this->getEditFormForIndexListedPages(
                $this->modelClass,
                $id,
                $fields
            );
        }

        $model = singleton($this->modelClass);

        if ($model->hasExtension(ListingsRootExtension::class)) {

            if ($model->getAdministrationMode() !== ListingsRootExtension::ADMIN_MODE_ADMIN) {
                throw new \UnexpectedValueException(
                    'You have setup a ListedPagesAdmin with a ListedPagesRoot that is not set to "admin" mode.'
                    . ' Supplied ListedPagesRoot class is ' . $this->modelClass
                );
            }

            return $this->getEditFormForListedPagesRoot(
                $this->modelClass,
                $id,
                $fields
            );
        }
        if (is_a($model, SiteTree::class)) {
            return $this->getEditFormForSiteTrees($id, $fields);
        }

        return parent::getEditForm($id, $fields);
    }

    public function getList()
    {
        $model = singleton($this->modelClass);

//        if ($model->hasExtension(ListingsRootExtension::class)) {
//            return $this->getListForListingsRootPage();
//        }
        if (is_a($model, SiteTree::class)) {
            return $this->getListForSiteTrees();
        }

        return parent::getList();
    }

    protected function getEditFormForIndexListedPages($id = null, $fields = null)
    {
        $models = $this->getManagedModels();
        $options = $models[$this->modelClass];

        if (isset($options['indexClass'])) {
            $class = $options['indexClass'];
            $list = $this->getListForIndexListedPages($class);
        }
        else {
            $class = null;
            $list = $this->getListForIndexListedPages(
                ListedPages::get_index_classes()
            );
        }

        $gridField = GridField::create(
            $this->sanitiseClassName($this->modelClass),
            false,
            $list,
            $gridConfig = GridFieldConfig_ListedPagesAdmin::create()
        );

        if ($class) {
            $gridField->setModelClass($class);
        }
        else {
            $gridField->setModelClass(
                CommonAncestor::get_closest(
                    ListedPages::get_index_classes()
                )
            );
        }

        // TODO FilterHeaders

        if (isset($options['indexSort'])) {
            $gridConfig->addComponent(new GridFieldOrderableRows($options['indexSort']));
        }

        if (!$class) {

            $addNewMultiClasses = ListedPages::get_index_classes();
            if ($addNewMultiClasses) {
                $gridConfig
                    ->removeComponentsByType(GridFieldAddNewButton::class)
                    ->addComponent($multiAdder = new GridFieldAddNewMultiClass());
                $multiAdder->setClasses($addNewMultiClasses);
                $multiAdder->setItemRequestClass(
                    ListedPageGridFieldAddNewMultiClassHandler::class
                );
            }
        }

        $detailForm = $gridConfig->getComponentByType(GridFieldDetailForm::class);
        if ($detailForm !== null) {
            $detailForm->setDefaultParentID(0);
            $detailForm->setItemRequestClass(
                ListedPageGridFieldItemRequest::class
            );
        }

        $form = Form::create(
            $this,
            'EditForm',
            FieldList::create($gridField),
            FieldList::create()
        )->setHTMLID('Form_EditForm');

        $this->extend('updateEditFormForIndexListedPages', $form);
        return $form;
    }

    protected function getEditFormForListedPagesRoot($rootClass, $id = null, $fields = null)
    {
        $roots = $rootClass::get();
        $rootsCount = $roots->count();

        if ($rootsCount === 0) {
            return null;
        }

        if ($rootsCount === 1) {
            $fields = $this->getEditFormFieldsForListedPagesRoot(
                $roots->first()
            );
        }
        else {
            $tabSet = TabSet::create('Root');
            foreach ($roots as $root) {
                $tab = Tab::create(
                    'Tab' . str_replace('-', '', $root->URLSegment),
                    $root->MenuTitle
                );
                $fields = $this->getEditFormFieldsForListedPagesRoot($root);
                foreach ($fields as $field) {
                    $tab->push($field);
                }
                $tabSet->push($tab);
            }
            $fields = FieldList::create($tabSet);
        }

        $form = Form::create(
            $this,
            'EditForm',
            $fields,
            FieldList::create()
        )->setHTMLID('Form_EditForm');

        return $form;
    }

    protected function getEditFormFieldsForListedPagesRoot($root)
    {
        $gridField = GridField::create(
            'GridField' . str_replace('-', '', $root->URLSegment),
            false,
            $this->getListForListingsRootPage($root),
            $gridConfig = GridFieldConfig_ListedPagesAdmin::create()
        );

        $gridField->setModelClass(
            $root->getListedPagesCommonClass()
        );

        $orderableSort = $root->getListedPagesOrderableSort();
        if (!empty($orderableSort)) {
            $gridConfig
                ->addComponent(new GridFieldOrderableRows($orderableSort));
        }

        // If add-new-multi classes are supplied, apply GFAddNewMultiClass.
        $addNewMultiClasses = $root->getListedPagesAddNewMultiClasses();
        if (!empty($addNewMultiClasses)) {
            $gridConfig
                ->removeComponentsByType(GridFieldAddNewButton::class)
                ->addComponent($multiAdder = new GridFieldAddNewMultiClass());
            $multiAdder->setClasses($addNewMultiClasses);
            $multiAdder->setItemRequestClass(
                ListedPageGridFieldAddNewMultiClassHandler::class
            );
        }

        $detailForm = $gridConfig->getComponentByType(GridFieldDetailForm::class);
        if ($detailForm !== null) {
            $detailForm->setDefaultParentID($root->ID);
            $detailForm->setItemRequestClass(
                ListedPageGridFieldItemRequest::class
            );
        }

        return FieldList::create($gridField);
    }

    protected function getEditFormForSiteTrees($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if (singleton($this->modelClass)->hasExtension(ListedPageExtension::class))
        {
            /** @var GridField $gridField */
            $gridField = $form->Fields()->fieldByName(
                $this->sanitiseClassName($this->modelClass)
            );

            $multiClasses = $this->getManagedAddNewMultiClasses($this->modelClass);
            $showAdd = empty($multiClasses);

            $gridConfig = GridFieldConfig_ListedPages::create(
                null, 20, true, $showAdd, null, true
            );
            if (!empty($multiClasses)) {
                $gridConfig->addMultiAdder($multiClasses);
            }

            $gridField->setConfig($gridConfig);
        }

        $this->extend('updateEditFormForSiteTrees', $form);
        return $form;
    }

    protected function getListForIndexListedPages($classes)
    {
        $filter = ['ParentID' => 0];

        if (is_string($classes)) {
            $class = $classes;
        }
        else {
            $class = CommonAncestor::get_closest($classes);
            $filter['ClassName:ExactMatch'] = array_values($classes);
        }

        /** @var \Page $class */
        $list = $class::get()->filter($filter);
        $this->extend('updateListForIndexListedPages', $list);
        return $list;
    }

    protected function getListForListingsRootPage($root)
    {
        $list = $root->getListedPages();
        $this->extend('updateListForListingsRootPage', $list);
        return $list;
    }

    protected function getListForSiteTrees()
    {
        $list = parent::getList();
        $multiClasses = $this->getManagedAddNewMultiClasses($this->modelClass);
        if (!empty($multiClasses))
        {
            $classesFilter = [];
            foreach ($multiClasses as $key => $value)
            {
                $class = is_int($key) ? $value : $key;
                $subclasses = array_values(ClassInfo::subclassesFor($class));
                $classesFilter = [...$classesFilter, ...$subclasses];
            }
            $list = $list->filter('ClassName', $classesFilter);
        }
        $sort = $this->getManagedModelSort($this->modelClass);
        if (!is_null($sort)) {
            $list = $list->sort($sort);
        }
        $this->extend('updateListForSiteTrees', $list);
        return $list;
    }


    protected function getManagedAddNewMultiClasses(string $modelClass): ?array
    {
        $multiClasses = static::config()->get('managed_add_new_multi');
        if (empty($multiClasses)) return null;
        $modelMultiClasses = $multiClasses[$modelClass] ?? null;
        if (empty($modelMultiClasses)) return null;
        return $modelMultiClasses;
    }

    protected function getManagedModelSort(string $modelClass): ?string
    {
        $sorts = static::config()->get('managed_model_sort');
        if (empty($sorts)) return null;
        $modelSort = $sorts[$modelClass] ?? null;
        if (empty($modelSort)) return null;
        return $modelSort;
    }


    /**
     * Return null for export/import/search -related functions
     * if the managed $model is extended by ListingsRootExtension
     */

    public function getExportFields()
    {
        if ($this->isIndexModel()) {
            return null;
        }

        $model = singleton($this->modelClass);
        if ($model->hasExtension(ListingsRootExtension::class)) {
            return null;
        }
        return parent::getExportFields();
    }

    public function ImportForm()
    {
        if ($this->isIndexModel()) {
            return null;
        }

        $model = singleton($this->modelClass);
        if ($model->hasExtension(ListingsRootExtension::class)) {
            return null;
        }
        return parent::ImportForm();
    }

    public function SearchForm()
    {
        if ($this->isIndexModel()) {
            return null;
        }

        $model = singleton($this->modelClass);
        if ($model->hasExtension(ListingsRootExtension::class)) {
            return null;
        }
        return parent::SearchForm();
    }

    public function SearchSummary()
    {
        if ($this->isIndexModel()) {
            return null;
        }

        $model = singleton($this->modelClass);
        if ($model->hasExtension(ListingsRootExtension::class)) {
            return null;
        }
        return parent::SearchSummary();
    }

    public function getSearchContext()
    {
        if ($this->isIndexModel()) {
            return null;
        }

        $model = singleton($this->modelClass);
        if ($model->hasExtension(ListingsRootExtension::class)) {
            return null;
        }
        return parent::getSearchContext();
    }
}
