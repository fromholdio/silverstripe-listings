<?php

namespace Fromholdio\Listings\Extensions;

use Fromholdio\Listings\ListingsIndexes;
use Page;
use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;

class ListedPageControllerExtension extends Extension
{
    public function onAfterInit(): void
    {
        $request = $this->getOwner()->getRequest();
        $this->getOwner()->initReferringListingsIndex($request);
    }

    public function initReferringListingsIndex(?HTTPRequest $request): void
    {
        $index = null;
        if (!is_null($request))
        {
            $key = ListingsIndexControllerExtension::GETKEY_TOKEN;
            $token = $request->getVar($key);
            if (!empty($token)) {
                $token = Convert::raw2sql($token);
                $index = $this->getOwner()->findReferringListingsIndex($token);
            }
        }
        $this->getOwner()->setDynamicData('referringListingsIndex', $index);
    }

    /**
     * @param string $token
     * @return Page&ListingsIndexExtension|null
     */
    public function findReferringListingsIndex(string $token): ?Page
    {
        $page = $this->getListedPage();
        $possibleIndexes = $page->getAllowedListingIndexes();
        $index = ListingsIndexes::is_tokens_enabled()
            ? $possibleIndexes->find('Token', $token)
            : $possibleIndexes->find('ID', $token);
        $this->getOwner()->invokeWithExtensions(
            'updateFindReferringListingsIndex', $index, $token
        );
        return $index;
    }

    /**
     * @return Page&ListingsIndexExtension|null
     */
    public function getReferringListingsIndex(): ?Page
    {
        /** @var Page&ListingsIndexExtension $index */
        $index = $this->getOwner()->getDynamicData('referringListingsIndex');
        return $index;
    }


    /**
     * @return Page&ListedPageExtension
     */
    public function getListedPage(): Page
    {
        /** @var Page&ListedPageExtension $page */
        $page = $this->getOwner()->data();
        if (!$page || !$page->hasExtension(ListedPageExtension::class)) {
            throw new \LogicException(
                'ListedPageControllerExtension requires data() to return '
                . 'a valid Page extended by ListedPageExtension.'
            );
        }
        return $page;
    }

    /**
     * @return PageController&ListedPageControllerExtension
     */
    public function getOwner(): PageController
    {
        /** @var PageController $owner */
        $owner = parent::getOwner();
        return $owner;
    }
}
