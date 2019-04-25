# silverstripe-listings

A SilverStripe module providing foundation for pages with listed records.

* All applied via extensions, so you can maintain your own Page class data structures
* Ability for listed pages (blog posts, for example) to be listed on site root and/or underneath Root pages (blog, for example)
* Index pages - akin to blog root page, but is more independent, it doesn't actually house listed pages/posts underneath it
* Root and Index pages managed within SiteTree
* ListedPages able to be managed in their Root page in the SiteTree, and/or in their own Admin, and can be hidden from SiteTree

This needs a whole heap more documentation, and even some example implementations. One thing at a time! But this is in use on several production sites, it's ready to roll.

Feel free to submit any questions as issues in the meantime.

## Requirements

* [silverstripe-framework](https://github.com/silverstripe/silverstripe-cms) ^4
* [fromholdio/silverstripe-commonancestor](https://github.com/fromholdio/silverstripe-commonancestor) ^1.0
* [symbiote/silverstripe-gridfieldextensions](https://github.com/symbiote/silverstripe-gridfieldextensions) ^3.0

## Installation

`composer require fromholdio/silverstripe-listings`

## Details & Usage

Install, and then apply the extensions to your page classes/data structures.

More thorough docs to come. In the meantime please submit questions as issues.

## To Do

* Documentation and usage examples
