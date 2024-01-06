<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Helpers\ORM\ListHelper;
use Fromholdio\Listings\ListedPages;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataList;
use Page;

class ListingsIndexExtension extends SiteTreeExtension
{
    private static $is_insitu_enabled = true;
    private static $is_insitu_toggle_enabled = false;

    /**
     * You must supply an array of at least one SiteTree class extended
     * by ListedPageExtension, identifying which Listed Pages are to be
     * managed by and listed under this Listed Pages Root.
     *
     * @var array
     */
    private static $listed_pages_classes = [];

    /**
     * If true, this page only lists pages that are in the root of the site
     * @var bool
     */
    private static $listed_pages_index_only = false;

    private static $is_filter_toggles_enabled = true;
    private static $listed_page_filters = [];

    private static $db = [
        'IsInSituAllowed' => 'Boolean',
        'Token' => 'Varchar(10)',
        'EnabledListedPageFilterKeys' => 'Varchar'
    ];


    /**
     * Title
     * ----------------------------------------------------
     */

    public function getContextualTitle(string $delimiter = ' / '): string
    {
        $title = $this->getOwner()->getTitle();
        /** @var Page&ListingsIndexExtension $parent */
        $parent = $this->getOwner()->getComponent('Parent');
        if ($parent && $parent->exists() && $parent->hasExtension(static::class)) {
            $title = $parent->getContextualTitle() . $delimiter . $title;
        }
        return $title;
    }


    /**
     * Listed pages
     * ----------------------------------------------------
     */

    /**
     * @return DataList&ListHelper
     */
    public function getListedPages(): DataList
    {
        $pages = ListedPages::get(
            $this->getOwner()->getListedPagesClasses(),
            $this->getOwner()->getListedPagesParentIDs()
        );
        $this->getOwner()->invokeWithExtensions('updateListedPages', $pages);
        return $pages;
    }


    /**
     * Filters
     * ----------------------------------------------------
     */

    public function isListedPageFilterTogglesEnabled(): bool
    {
        return (bool) $this->getOwner()->get('is_filter_toggles_enabled');
    }

    public function getListedPageFilterTogglesField(): FormField
    {
        $filters = $this->getOwner()->getAvailableListedPageFilters();
        if (empty($filters)) {
            $field = HiddenField::create('EnabledListedPageFilterKeys', false, null);
        }
        else {
            $options = [];
            foreach ($filters as $key => $config) {
                $options[$key] = $config['Title'];
            }
            $field = CheckboxSetField::create(
                'EnabledListedPageFilterKeys',
                $this->getOwner()->fieldLabel('EnabledListedPageFilterKeys'),
                $options
            );
        }
        return $field;
    }

    public function getAvailableListedPageFilters(): array
    {
        $filters = $this->getOwner()->config()->get('listed_page_filters');
        return empty($filters) ? [] : $filters;
    }

    public function getListedPageFilters(): array
    {
        $filters = $this->getAvailableListedPageFilters();
        if ($this->isListedPageFilterTogglesEnabled())
        {
            $keys = $this->getOwner()->getField('EnabledListedPageFilterKeys');
            if (!empty($keys))
            {
                $keys = explode(',', $keys);
                foreach ($filters as $filterKey => $filterData)
                {
                    if (!in_array($filterKey, $keys, true)) {
                        unset($filters[$filterKey]);
                    }
                }
            }
        }
        return $filters;
    }


    /**
     * Display listed pages in-situ or in sitetree-position
     * ----------------------------------------------------
     */

    public function isInSituEnabled(): bool
    {
        return $this->getOwner()->config()->get('is_insitu_enabled');
    }

    public function isInSituToggleEnabled(): bool
    {
        return $this->getOwner()->isInSituEnabled()
            && $this->getOwner()->config()->get('is_insitu_toggle_enabled');
    }

    public function isInSituAllowed(): bool
    {
        return $this->getOwner()->isInSituEnabled()
            && (
                !$this->getOwner()->isInSituToggleEnabled()
                || $this->getOwner()->getField('IsInSituAllowed')
            );
    }


    /**
     * Listings token
     * ----------------------------------------------------
     */

    public function getListingsToken(): ?string
    {
        $token = $this->getOwner()->getField('Token');
        $this->getOwner()->invokeWithExtensions('updateListingsToken', $token);
        return $token;
    }


    /**
     * Listed page meta information
     * ----------------------------------------------------
     */

    /**
     * Get list of SiteTree classes configured as Listed Pages for this Listed Pages Root.
     *
     * @return array Array of classes.
     */
    public function getListedPagesClasses(): array
    {
        $classes = $this->getOwner()->config()->get('listed_pages_classes');
        if (empty($classes)) $classes = [];
        $this->getOwner()->invokeWithExtensions('updateListedPagesClasses', $classes);
        return $classes;
    }

    public function getListedPagesParentIDs(): array
    {
        $indexOnly = (bool) $this->getOwner()->config()->get('listed_pages_index_only');
        $parentIDs = ($indexOnly) ? [0] : [];
        $this->getOwner()->invokeWithExtensions('updateListedPagesParentIDs', $parentIDs);
        return $parentIDs;
    }

    public function getListedPagesCommonClass(): string
    {
        $class = $this->getOwner()->getDynamicData('listedPagesCommonClass') ?? null;
        if (!empty($class)) return $class;
        $class = ListedPages::get_common_class($this->getListedPagesClasses());
        $this->getOwner()->setDynamicData('listedPagesCommonClass', $class);
        return $class;
    }

    public function getListedPagesCommonSingularName(): string
    {
        $class = $this->getOwner()->getListedPagesCommonClass();
        return $class::singleton()->i18n_singular_name();
    }

    public function getListedPagesCommonPluralName(): string
    {
        $class = $this->getOwner()->getListedPagesCommonClass();
        return $class::singleton()->i18n_plural_name();
    }


    /**
     * @return Page&ListingsIndexExtension
     */
    public function getOwner(): Page
    {
        /** @var Page $owner */
        $owner = parent::getOwner();
        return $owner;
    }
}
