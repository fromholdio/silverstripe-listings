<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListedPages;
use Page;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataList;

class ListingsIndexExtension extends Extension
{
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
