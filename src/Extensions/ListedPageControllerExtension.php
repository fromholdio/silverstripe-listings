<?php

namespace Fromholdio\Listings\Extensions;

use Page;
use PageController;
use SilverStripe\Core\Extension;

class ListedPageControllerExtension extends Extension
{
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
