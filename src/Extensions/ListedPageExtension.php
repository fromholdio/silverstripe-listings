<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Helpers\ORM\ListHelper;
use Fromholdio\Listings\ListedPages;
use Fromholdio\Listings\ListingsIndexes;
use Fromholdio\Listings\ListingsRoots;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use Page;
use SilverStripe\ORM\DataList;

class ListedPageExtension extends SiteTreeExtension
{
    private static $is_extra_roots_enabled = true;
    private static $is_insitu_enabled = true;
    private static $is_insitu_toggle_enabled = false;

    private static $db = [
        'IsInSituAllowed' => 'Boolean',
        'Token' => 'Varchar(10)'
    ];


    /**
     * Title
     * ----------------------------------------------------
     */

    public function getDefaultTitle(): string
    {
        $title = 'New ' . $this->getOwner()->i18n_singular_name();
        $this->getOwner()->invokeWithExtensions('updateDefaultTitle', $title);
        return $title;
    }

    public function getContextualTitle(string $delimiter = ' / '): string
    {
        $title = $this->getOwner()->getTitle();
        $root = $this->getOwner()->getListingsRoot();
        if (!is_null($root)) {
            $title = $root->getContextualTitle($delimiter) . $delimiter . $title;
        }
        return $title;
    }

    public function doPopulateTitle(bool $doOverride = false): void
    {
        if ($doOverride || empty($this->getOwner()->getField('Title'))) {
            $this->getOwner()->setField('Title', $this->getOwner()->getDefaultTitle());
        }
    }


    /**
     * Contextual/Sibling ListedPages
     * ----------------------------------------------------
     */

    public function getRootListedPages(): DataList
    {
        /** @var ListingsRootExtension $root */
        $root = $this->getOwner()->getListingsRoot();
        if (is_null($root)) {
            return ListedPages::get(null, 0);
        }
        return $root->getListedPages();
    }

    public function getNextListedPage(): ?Page
    {
        $pageIDs = $this->getOwner()->getRootListedPages()->getIDList();
        $next = false;
        foreach ($pageIDs as $pageID) {
            if ($next) {
                /** @var Page $page */
                $page = Page::get()->byID($pageID);
                return $page;
            }
            if ((int) $pageID === (int) $this->getOwner()->ID) {
                $next = true;
            }
        }
        return null;
    }

    public function getPrevListedPage(): ?Page
    {
        $pageIDs = array_reverse($this->getOwner()->getRootListedPages()->getIDList());
        $next = false;
        foreach ($pageIDs as $pageID) {
            if ($next) {
                /** @var Page $page */
                $page = Page::get()->byID($pageID);
                return $page;
            }
            if ((int) $pageID === (int) $this->getOwner()->ID) {
                $next = true;
            }
        }
        return null;
    }


    /**
     * Primary parent ListingsRoot
     * ----------------------------------------------------
     */

    /**
     * @return Page&ListingsRootExtension|null
     */
    public function getListingsRoot(): ?Page
    {
        $rootID = $this->getOwner()->getField('ParentID');
        if (empty($rootID)) return null;
        /** @var ?Page $root */
        $root = Page::get()->find('ID', $rootID);
        return $root;
    }

    /**
     * @return Page&ListingsRootExtension|null
     */
    public function getDefaultListingsRoot(): ?Page
    {
        $defaultRoot = null;
        $roots = $this->getOwner()->getAvailableListingsRoots();
        if ($roots->count() > 0) {
            $defaultRoot = $roots->first();
        }
        $this->getOwner()->invokeWithExtensions('updateDefaultListingsRoots', $defaultRoot);
        return $defaultRoot;
    }

    /**
     * @return DataList&ListHelper
     */
    public function getAvailableListingsRoots(): DataList
    {
        $classes = $this->getOwner()->getAvailableListingsRootsClasses();
        $roots = empty($classes)
            ? ListingsRoots::get()->filter('ID', '-1')
            : ListingsRoots::get($classes);
        return $roots;
    }

    public function getAvailableListingsRootsClasses(): array
    {
        return ListingsRoots::get_classes_for_page(get_class($this->getOwner()));
    }

    public function doPopulateListingsRoot(bool $doOverride = false): void
    {
        $parentID = $this->getOwner()->getField('ParentID');
        if ($doOverride || empty($parentID)) {
            $root = $this->getDefaultListingsRoot();
            $parentID = is_null($root) ? 0 : $root->getField('ID');
            $this->getOwner()->setField('ParentID', $parentID);
        }
    }

    public function getAllowedListingIndexes(): DataList
    {
        $classes = ListingsIndexes::get_classes_for_page(get_class($this->getOwner()));
        return ListingsIndexes::get($classes);
    }


    /**
     * Allow display within ListingsIndexes other than direct parent root
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
     * Allow relations with extra Roots
     * ----------------------------------------------------
     */

    public function isExtraRootsEnabled(): bool
    {
        return (bool) $this->getOwner()->config()->get('is_extra_roots_enabled');
    }

    /**
     * @return DataList&ListHelper
     */
    public function getExtraListingsRoots(): DataList
    {
        $roots = $this->getOwner()->getAvailableExtraListingsRoots();
        if ($roots->count() > 0)
        {
            $ids = $this->getOwner()->hasMethod('provideExtraListingsRootIDs')
                ? $this->getOwner()->provideExtraListingsRootIDs()
                : [];
            $roots = empty($ids)
                ? $roots->empty()
                : $roots->filter('ID', $ids);
        }
        $this->getOwner()->invokeWithExtensions('updateExtraListingsRoots', $roots);
        return $roots;
    }

    /**
     * @return DataList&ListHelper
     */
    public function getAvailableExtraListingsRoots(): DataList
    {
        $roots = $this->getOwner()->getAvailableListingsRoots();
        $root = $this->getOwner()->getListingsRoot();
        if ($this->getOwner()->isExtraRootsEnabled()) {
            if (!is_null($root)) {
                $roots = $roots->exclude('ID', $root->getField('ID'));
            }
        }
        else {
            $roots = $roots->empty();
        }
        $this->getOwner()->invokeWithExtensions('updateAvailableExtraListingsRoots', $roots);
        return $roots;
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
     * Links
     * ----------------------------------------------------
     */

    public function updateLink(&$link, $action, $base)
    {
        /** @var \PageController&ListingsIndexControllerExtension $curr */
        $curr = Controller::curr();
        if ($curr && $curr->hasExtension(ListingsIndexControllerExtension::class)) {
            $link = $curr->amendListedPageLink($this->getOwner(), $link);
        }
    }


    /**
     * Template helpers
     * ----------------------------------------------------
     */

    public function getListing(array $data = [], ?string $variation = null)
    {
        $suffix = '_Listing';
        if (!empty($variation)) {
            $suffix .= '_' . $variation;
        }
        $templates = $this->getOwner()->getViewerTemplates($suffix);
        $this->getOwner()->invokeWithExtensions('updateListingTemplates', $templates);
        $string = $this->getOwner()
            ->customise($data)
            ->renderWith($templates);
        $this->getOwner()->invokeWithExtensions('updateListing', $string);
        return $string;
    }


    /**
     * Data processing and validation methods
     * ----------------------------------------------------
     */

    public function populateDefaults(): void
    {
        $this->getOwner()->doPopulateListingsRoot();
        $this->getOwner()->doPopulateTitle(true);
    }

    public function onBeforeWrite(): void
    {
        $this->getOwner()->doPopulateTitle();
    }


    /**
     * CMS Fields
     * ----------------------------------------------------
     */

    public function updateCMSFields(FieldList $fields): void
    {
        // Remove ParentID dropdown (if mode != cmstree)
        // and if listed_pages_root_switch_enabled
        // add dropdown with available roots.
        // TODO: actually just allow move between valid roots.
    }

    public function updateSettingsFields(FieldList $fields): void
    {
        $availableRoots = $this->getAvailableListingsRoots();
        if ($availableRoots->count() > 1) {
            $fields->removeByName('ParentTypeParentID');
        }
    }


    /**
     * @return Page&ListedPageExtension
     */
    public function getOwner(): Page
    {
        /** @var Page $owner */
        $owner = parent::getOwner();
        return $owner;
    }
}
