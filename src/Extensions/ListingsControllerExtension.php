<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListedPages;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;

class ListingsControllerExtension extends Extension
{
    protected $listedPages;

    /**
     * Only applicable if no associated Page extended with ListingsPageExtension.
     */
    private static $listed_pages_classes = [];
    private static $listed_pages_index_only = false;

    public function onBeforeInit()
    {
        $this->owner->getListedPages();
    }

    public function getListedPages()
    {
        if ($this->owner->listedPages) {
            return $this->owner->listedPages;
        }

        $listingsPage = $this->owner->getListingsPage();
        if ($listingsPage) {
            $pages = $listingsPage->getListedPages();
        }
        else {
            $pages = ListedPages::get(
                $this->owner->getListedPagesClasses(),
                $this->owner->getListedPagesParentID()
            );
        }

        if ($this->owner->hasMethod('updateListedPages')) {
            $pages = $this->owner->updateListedPages($pages);
        }

        $this->owner->setListedPages($pages);
        return $pages;
    }

    public function setListedPages($pages)
    {
        if (!is_a($pages, DataList::class) && !is_null($pages)) {
            throw new \InvalidArgumentException(
                'Invalid $pages value passed to '
                . get_class($this->owner) . '::setListedPages(). '
                . 'Supplied ' . gettype($pages) . ' but expected either DataList or NULL.'
            );
        }

        $this->owner->listedPages = $pages;
        return $this;
    }

    public function getListedPagesClasses()
    {
        $classes = $this->owner->config()->get('listed_pages_classes');

        if ($this->owner->hasMethod('updateListedPagesClasses')) {
            $classes = $this->owner->updateListedPagesClasses($classes);
        }
        return $classes;
    }

    public function getListedPagesParentID()
    {
        $indexOnly = (bool) $this->owner->config()->get('listed_pages_index_only');
        $parentID = ($indexOnly) ? 0 : null;

        if ($this->owner->hasMethod('updateListedPagesParentID')) {
            $parentID = $this->owner->updateListedPagesParentID($parentID);
        }
        return $parentID;
    }

    public function getListingsPage()
    {
        $listingsPage = null;

        if (is_a($this->owner, ContentController::class)) {
            $page = $this->owner->data();
            if ($page && $page->hasExtension(ListingsSiteTreeExtension::class)) {
                $listingsPage = $page;
            }
        }

        if ($this->owner->hasMethod('updateListingsPage')) {
            $listingsPage = $this->owner->updateListingsPage($listingsPage);
        }

        return $listingsPage;
    }
}
