<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListingsIndexes;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use Page;
use PageController;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\SS_List;

class ListingsIndexControllerExtension extends Extension
{
    const GETKEY_TOKEN = 'ind';

    private static $url_handlers = [
        'view/$ListingsToken!/$ListedPageURLSegment!' => 'view'
    ];

    private static $allowed_actions = [
        'view'
    ];

    public function index()
    {
        $request = $this->getOwner()->getRequest();
        $this->getOwner()->initListedPageFilters($request);
        $this->getOwner()->initListedPages($request);
        return [];
    }

    /**
     * TODO: handle insitu viewing
     * @return void
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function view()
    {
        if (!$this->getOwner()->isInSituAllowed()) {
            $this->getOwner()->httpError(404);
        }

        $request = $this->getOwner()->getRequest();
        if (is_null($request)) {
            $this->getOwner()->httpError(402);
        }

        $token = $request->param('ListingsToken');
        $urlSegment = $request->param('ListedPageURLSegment');
        if (empty($token) || empty($urlSegment)) {
            $this->getOwner()->httpError(404);
        }

        $page = $this->getOwner()->findInSituListedPage($token, $urlSegment);
        if (is_null($page)) {
            $this->getOwner()->httpError(404);
        }
        if (!$page->isInSituAllowed()) {
            $this->getOwner()->redirect($page->Link());
        }

        $this->getOwner()->setDynamicData('inSituListedPage', $page);

        /**
         * TODO: serve up a /view/ action that displays the InSituPage
         *
         * Set ParentID
         * Create Controller
         * Return controller response?
         *
         */
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
        $pages = $this->getOwner()->applyListedPageFilters($pages);
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
     * Filters
     * ----------------------------------------------------
     */

    public function initListedPageFilters(?HTTPRequest $request): void
    {
//        $filters = $this->getOwner()->getListingsIndex()->getListedPageFilters();
        $this->getOwner()->setDynamicData('listedPageFilters', []);
    }

    public function applyListedPageFilters(DataList $pages): SS_List
    {
        $this->getOwner()->invokeWithExtensions('onBeforeApplyListedPageFilters', $pages);
//        $filters = $this->getOwner()->getActiveListedPageFilters();
//        foreach ($filters as $key => $filter) {
            //$pages = $filter->doFilter($pages);
//        }
        $this->getOwner()->invokeWithExtensions('onAfterApplyListedPageFilters', $pages);
        return $pages;
    }

    public function getListedPageFilters(): array
    {
        return $this->getOwner()->getDynamicData('listedPageFilters');
    }

    public function getActiveListedPageFilters(): array
    {
        return $this->getOwner()->getListedPageFilters();
    }


    /**
     * Listings token
     * ----------------------------------------------------
     */

    public function isListingsTokensEnabled(): bool
    {
        return ListingsIndexes::is_tokens_enabled();
    }

    public function addListingsTokenToLink(string $link): string
    {
        $index = $this->getOwner()->getListingsIndex();
        $token = $this->getOwner()->isListingsTokensEnabled()
            ? $index->getListingsToken()
            : $index->getField('ID');
        if (empty($token)) {
            throw new \LogicException('ListingsIndex token is empty.');
        }
        return Controller::join_links(
            $link,
            '?' . self::GETKEY_TOKEN . '=' . $token
        );
    }


    /**
     * In-situ handling
     * ----------------------------------------------------
     */

    public function isInSituAllowed(): bool
    {
        return $this->getListingsIndex()->isInSituAllowed();
    }

    public function buildInSituLink(Page $page): string
    {
        /** @var Page&ListedPageExtension $page */
        $token = $this->getOwner()->isListingsTokensEnabled()
            ? $page->getListingsToken()
            : $page->getField('ID');
        if (empty($token)) {
            throw new \LogicException('ListedPage token is empty.');
        }
        return Controller::join_links(
            $this->getOwner()->Link('view'),
            $token,
            $page->getField('URLSegment')
        );
    }

    public function getInSituListedPage(): ?Page
    {
        return $this->getOwner()->getDynamicData('inSituListedPage');
    }

    /**
     * @param string $token
     * @param string $urlSegment
     * @return Page&ListedPageExtension|null
     */
    public function findInSituListedPage(string $token, string $urlSegment): ?Page
    {
        $pages = $this->getAllListedPages();
        /** @var ?Page&ListedPageExtension $page */
        $page = $this->getOwner()->isListingsTokensEnabled()
            ? $pages->find('Token', $token)
            : $pages->find('ID', $token);
        if (!is_null($page)) {
            if ($page->getField('URLSegment') !== $urlSegment) {
                $page = null;
            }
        }
        $this->getOwner()->invokeWithExtensions('updateFindInSituListedPage', $page, $token, $urlSegment);
        return $page;
    }


    /**
     * Amend ListedPage links when displayed during request of this controller
     * ----------------------------------------------------
     */

    public function amendListedPageLink(Page $page, ?string $link = null): ?string
    {
        if (!$page->hasExtension(ListedPageExtension::class)) {
            throw new \LogicException(
                'ListingsIndexControllerExtension::amendListedPageLink expects $page '
                . ' param to be a valid Page extended by ListedPageExtension.'
            );
        }

        /** @var Page&ListedPageExtension $page */
        $pageRoot = $page->getListingsRoot();
        $index = $this->getOwner()->getListingsIndex();
        $isInSitu = $page->isInSituAllowed() && $this->getOwner()->isInSituAllowed();
        $isPageRoot = !is_null($pageRoot)
            && $pageRoot->getField('ID') === $index->getField('ID');

        if (!$isPageRoot)
        {
            if ($isInSitu) {
                $link = $this->getOwner()->buildInSituLink($page);
            }
            elseif (!empty($link)) {
                $link = $this->getOwner()->addListingsTokenToLink($link);
            }
        }
        return $link;
    }

    // public function amendAttributeLink() {}



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
