<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListedPages;
use SilverStripe\CMS\Model\SiteTreeExtension;

class ListingsSiteTreeExtension extends SiteTreeExtension
{
    protected $listedPagesCommonClass;
    protected $listedPagesCommonSingularName;
    protected $listedPagesCommonPluralName;

    /**
     * You must supply an array of at least one SiteTree class extended
     * by ListedPageExtension, identifying which Listed Pages are to be
     * managed by and listed under this Listed Pages Root.
     *
     * @var array
     */
    private static $listed_pages_classes = [];

    private static $listed_pages_index_only = false;

    public function getListedPages()
    {
        $pages = ListedPages::get(
            $this->owner->getListedPagesClasses(),
            $this->owner->getListedPagesParentIDs()
        );

        if ($this->owner->hasMethod('updateListedPages')) {
            $pages = $this->owner->updateListedPages($pages);
        }

        return $pages;
    }

    /**
     * Get list of SiteTree classes configured as Listed Pages for this Listed Pages Root.
     *
     * @return array Array of classes.
     */
    public function getListedPagesClasses()
    {
        $classes = $this->owner->config()->get('listed_pages_classes');

        if ($this->owner->hasMethod('updateListedPagesClasses')) {
            $classes = $this->owner->updateListedPagesClasses($classes);
        }
        return $classes;
    }

    public function getListedPagesParentIDs()
    {
        $indexOnly = (bool) $this->owner->config()->get('listed_pages_index_only');
        $parentIDs = ($indexOnly) ? [0] : null;

        if ($this->owner->hasMethod('updateListedPagesParentIDs')) {
            $parentIDs = $this->owner->updateListedPagesParentIDs($parentIDs);
        }
        return $parentIDs;
    }

    public function getListedPagesCommonClass()
    {
        if ($this->owner->listedPagesCommonClass) {
            return $this->owner->listedPagesCommonClass;
        }

        $class = ListedPages::get_common_class($this->getListedPagesClasses());
        $this->owner->listedPagesCommonClass = $class;

        return $class;
    }

    public function getListedPagesCommonSingularName()
    {
        $class = $this->getOwner()->getListedPagesCommonClass();
        return $class::singleton()->i18n_singular_name();
    }

    public function getListedPagesCommonPluralName()
    {
        $class = $this->getOwner()->getListedPagesCommonClass();
        return $class::singleton()->i18n_plural_name();
    }
}
