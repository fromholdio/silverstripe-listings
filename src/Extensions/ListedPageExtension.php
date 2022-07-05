<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListedPages;
use Fromholdio\Listings\ListingsRoots;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\View\SSViewer;

class ListedPageExtension extends SiteTreeExtension
{
    public static function add_to_class($class, $extensionClass, $args = null)
    {
        ListedPages::register_class($class);
    }

    public function updateCMSFields(FieldList $fields)
    {
        // Remove ParentID dropdown (if mode != cmstree)
        // and if listed_pages_root_switch_enabled
        // add dropdown with available roots.
        // TODO: actually just allow move between valid roots.
    }

    public function getListing($variation = null)
    {
        $suffix = '_Listing';
        if ($variation) {
            $suffix .= '_' . $variation;
        }
        $string = $this->getOwner()->renderWith(
            SSViewer::get_templates_by_class(
                ClassInfo::class_name($this->getOwner()),
                $suffix
            )
        );
        if ($this->getOwner()->hasMethod('updateListing')) {
            $string = $this->getOwner()->updateListing($string);
        }
        return $string;
    }

    public function getListingsRoot()
    {
        if ($this->getOwner()->ParentID === 0) {
            return null;
        }
        return $this->getOwner()->Parent();
    }

    public function getRootListedPages()
    {
        $root = $this->getOwner()->getListingsRoot();
        if ($root === null) {
            ListedPages::get(null, 0);
        }
        return $root->getListedPages();
    }

    public function getNextListedPage()
    {
        $pageIDs = $this->getOwner()->getRootListedPages()->getIDList();
        $next = false;
        foreach ($pageIDs as $pageID) {
            if ($next) {
                return SiteTree::get()->byID($pageID);
            }
            if ((int) $pageID === (int) $this->getOwner()->ID) {
                $next = true;
            }
        }
        return null;
    }

    public function getPrevListedPage()
    {
        $pageIDs = array_reverse($this->getOwner()->getRootListedPages()->getIDList());
        $next = false;
        foreach ($pageIDs as $pageID) {
            if ($next) {
                return SiteTree::get()->byID($pageID);
            }
            if ((int) $pageID === (int) $this->getOwner()->ID) {
                $next = true;
            }
        }
        return null;
    }

    public function getDefaultListingsRoot()
    {
        $defaultRoot = null;
        $roots = $this->getOwner()->getAvailableListingsRoots();
        if ($roots && $roots->count() > 0) {
            $defaultRoot = $roots->first();
        }
        if ($this->getOwner()->hasMethod('updateDefaultListingsRoot')) {
            $defaultRoot = $this->getOwner()->updateDefaultListingsRoot($defaultRoot);
        }
        return $defaultRoot;
    }

    public function getAvailableListingsRoots()
    {
        return ListingsRoots::get($this->getOwner()->getAvailableListingsRootsClasses());
    }

    public function getAvailableListingsRootsClasses()
    {
        return ListingsRoots::get_classes_for_page($this->getOwner());
    }
}
