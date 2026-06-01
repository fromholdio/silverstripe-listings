<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListingsIndexes;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use Page;
use PageController;
use SilverStripe\ORM\DataList;
use SilverStripe\Model\List\SS_List;

class ListingsIndexControllerExtension extends Extension
{
    public function index()
    {
        $request = $this->getOwner()->getRequest();
        $this->getOwner()->initListedPages($request);
        return [];
    }

    /**
     * Listed pages
     * ----------------------------------------------------
     */

    public function initListedPages(?HTTPRequest $request): void
    {
        $this->getOwner()->setDynamicData('listedPages', null);
    }

    public function getAllListedPages(): DataList
    {
        return $this->getOwner()->getListingsIndex()->getListedPages();
    }

    public function getListedPages(): SS_List
    {
        $pages = $this->getOwner()->getDynamicData('listedPages');
        if (!is_null($pages)) return $pages;

        $pages = $this->getOwner()->getAllListedPages();
        $this->getOwner()->invokeWithExtensions('updateListedPages', $pages);
        $this->getOwner()->setDynamicData('listedPages', $pages);
        return $pages;
    }

    /**
     * @return Page&ListingsIndexExtension
     */
    public function getListingsIndex(): Page
    {
        /** @var Page&ListingsIndexExtension $page */
        $page = $this->getOwner()->data();
        if (!$page || !$page->hasExtension(ListingsIndexExtension::class)) {
            throw new \LogicException(
                'ListingsIndexControllerExtension requires data() to return '
                . 'a valid Page extended by ListingsIndexExtension.'
            );
        }
        return $page;
    }


    /**
     * @return PageController&ListingsIndexControllerExtension
     */
    public function getOwner(): PageController
    {
        /** @var PageController $owner */
        $owner = parent::getOwner();
        return $owner;
    }
}
