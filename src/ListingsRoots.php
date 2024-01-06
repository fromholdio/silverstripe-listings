<?php

namespace Fromholdio\Listings;

use Fromholdio\Listings\Extensions\ListingsRootExtension;
use SilverStripe\Core\Flushable;

class ListingsRoots extends ListingsIndexes implements Flushable
{
    private static $index_extension_class = ListingsRootExtension::class;

    protected static function get_cache_key(string $suffix, ?array $classes = null): string
    {
        $key = 'ListedRootClasses-' . $suffix;
        if (!empty($classes)) {
            $key .= '-' . md5(implode('-', $classes));
        }
        return $key;
    }
}
