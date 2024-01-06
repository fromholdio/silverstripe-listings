# silverstripe-listings

A SilverStripe module providing foundation for pages with listed records.

* All applied via extensions, so you can maintain your own Page class data structures
* Ability for listed pages (blog posts, for example) to be listed on site root and/or underneath Root pages (blog, for example)
* Index pages - akin to blog root page, but is more independent, it doesn't actually house listed pages/posts underneath it
* Root and Index pages managed within SiteTree
* ListedPages able to be managed in their Root page in the SiteTree, and/or in their own Admin, and can be hidden from SiteTree

This needs a whole heap more documentation, and even some example implementations. One thing at a time! But this is in use on several production sites, it's ready to roll.

Feel free to submit any questions as issues in the meantime.

## Requirements for 3.x (in-progress branch)

* [silverstripe-framework](https://github.com/silverstripe/silverstripe-cms) ^5.0
* [fromholdio/silverstripe-commonancestor](https://github.com/fromholdio/silverstripe-commonancestor) ^1.0
* [fromholdio/silverstripe-gridfield-extradata](https://github.com/fromholdio/silverstripe-gridfield-extradata) ^1.1.0
* [symbiote/silverstripe-gridfieldextensions](https://github.com/symbiote/silverstripe-gridfieldextensions) ^4.0

## Installation

`composer require fromholdio/silverstripe-listings`

## Details & Usage

Install, and then apply the extensions to your page classes/data structures.

More thorough docs to come. In the meantime please submit questions as issues.

## To Do

* Documentation and usage examples

## When using ListedPagesAdmin (ModelAdmin subclass) to manage pages

Add `doPlaceCMSFieldsUnderListedPagesAdminRootTabSet():bool` to your ListedPage class, and when displayed inside a ListedPagesAdmin the page fields' TabSets and Tabs will be displayed on the left side (like regularly viewed pages) rather than the top right.

Further add `doAddSettingsFieldsAsListedPagesAdminTab():bool` to ListedPage class, and the page's settings fields will be displayed per regularly viewed pages as a Settings tab on the top right. This may/may not work for your specific class, where the same field name exists in your pages' getCMSFields and getSettingsFields. You'll need to manage that.
