<?php

namespace Fromholdio\Listings;

use Fromholdio\CommonAncestor\CommonAncestor;
use Fromholdio\Listings\Extensions\ListedPageExtension;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class ListedPages implements Flushable
{
    use Injectable;
    use Extensible;
    use Configurable;

    public static function get_classes(bool $includeSubclasses = true): array
    {
        $classes = [];
        $cache = static::get_cache();
        $cacheKey = static::get_cache_key('Classes');
        if (!$cache->has($cacheKey)) {
            static::build_cache();
        }
        if ($cache->has($cacheKey)) {
            $classes = $cache->get($cacheKey);
        }
        if ($includeSubclasses) {
            $classes = static::add_subclasses($classes);
        }
        return $classes;
    }

    public static function get_classes_dropdown_source(
        bool $includeSubclasses = true,
        bool $useSingular = false
    ): array
    {
        $classes = static::get_classes($includeSubclasses);
        foreach ($classes as $class) {
            if ($useSingular) {
                $title = $class::singleton()->i18n_singular_name();
            }
            else {
                $title = $class::singleton()->i18n_plural_name();
            }
            $classes[$class] = $title;
        }
        return $classes;
    }

    public static function get_index_classes(): array
    {
        $classes = static::get_classes();
        foreach ($classes as $class) {
            if (!$class::singleton()->config()->get('can_be_root')) {
                unset($classes[$class]);
            }
        }
        return $classes;
    }

    public static function get(
        ?array $classes = null,
        $parentIDs = null,
        $includeSubclasses = true
    ): DataList
    {
        if (empty($classes)) {
            $classes = static::get_classes($includeSubclasses);
        }
        elseif ($includeSubclasses) {
            $classes = static::add_subclasses($classes);
        }

        $commonClass = static::get_common_class($classes);

        $filter = ['ClassName:ExactMatch' => $classes];
        if (!empty($parentIDs)) {
            if (!is_array($parentIDs)) {
                $parentIDs = [$parentIDs];
            }
            $filter['ParentID'] = $parentIDs;
        }

        /** @var DataObject $commonClass */
        return $commonClass::get()->filter($filter);
    }

    public static function filter(DataList $pages, $filter): DataList
    {
        if ($pages->count() === 0) {
            return $pages;
        }

        $pageIDs = $pages->columnUnique('ID');
        $pageClass = $pages->dataClass();
        if (!empty($filter['ID']))
        {
            $filterIDs = $filter['ID'];
            if (!is_array($filterIDs)) {
                $filterIDs = [$filterIDs];
            }
            $ids = array_intersect($pageIDs, $filterIDs);
            $filter['ID'] = count($ids) > 0 ? $ids : ['-1'];
        }

        /** @var DataObject $pageClass */
        return $pageClass::get()->filter($filter);
    }

    public static function filter_by_ids(DataList $pages, array $ids): DataList
    {
        return static::filter($pages, ['ID' => $ids]);
    }

    public static function get_common_class(?array $classes = null): string
    {
        if (!empty($classes)) {
            static::validate_classes($classes);
        }
        else {
            $classes = static::get_classes(false);
        }

        // If only one class is configured, return it immediately.
        if (count($classes) === 1) {
            return reset($classes);
        }

        // Else return closest common ancestor class name.
        return CommonAncestor::get_closest($classes);
    }

    protected static function add_subclasses(array $classes): array
    {
        $cache = static::get_cache();
        $cacheKey = static::get_cache_key('IncludeSubclasses', $classes);
        if ($cache->has($cacheKey)) {
            $classes = $cache->get($cacheKey);
        }
        else {
            $classes = array_combine($classes, $classes);
            foreach ($classes as $class) {
                $subclasses = ClassInfo::subclassesFor($class);
                foreach ($subclasses as $subclass) {
                    $classes[$subclass] = $subclass;
                }
            }
            $cache->set($cacheKey, $classes);
        }
        return $classes;
    }

    public static function validate_class(string $class): bool
    {
        return static::validate_classes([$class]);
    }

    public static function validate_classes(array $classes): bool
    {
        if (!is_array($classes)) {
            throw new \InvalidArgumentException(
                'Classes must be passed as an array to ListedPages::validate_classes(). '
                . gettype($classes) . ' was supplied instead.'
            );
        }

        if (empty($classes)) {
            throw new \InvalidArgumentException(
                'ListedPages::validate_classes() must be passed '
                . 'at least one page class in $classes array. Array was empty.'
            );
        }

        // To confirm we're working with a non-associative array of class names as expected.
        $classes = array_values($classes);

        // Ensure all supplied classes are valid
        foreach ($classes as $class) {

            $invalidMessage = 'Invalid class passed to ListedPages::validate_classes(): ';

            // Check exists
            if (!ClassInfo::exists($class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' does not exist.'
                );
            }

            // Check is extended by ListedPageExtension
            if (!singleton($class)->has_extension(ListedPageExtension::class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' is not extended by ListedPageExtension.'
                );
            }

            // Check is a SiteTree or descendent
            if (!is_a(singleton($class), SiteTree::class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' is not a descendent of ' . SiteTree::class . '.'
                );
            }
        }

        $self = static::singleton();
        $self->invokeWithExtensions('validateClasses', $classes);
        return true;
    }


    /**
     * This function is triggered early in the request if the "flush" query
     * parameter has been set. Each class that implements Flushable implements
     * this function which looks after its own specific flushing functionality.
     */
    public static function flush(): void
    {
        static::get_cache()->clear();
        static::build_cache();
    }

    protected static function build_cache(): void
    {
        $pages = [];
        $classes = ClassInfo::subclassesFor(SiteTree::class);
        foreach ($classes as $class) {
            if ($class::has_extension(ListedPageExtension::class)) {
                static::validate_class($class);
                $pages[$class] = $class;
            }
        }
        $cache = static::get_cache();
        $cacheKey = static::get_cache_key('Classes');
        $cache->set($cacheKey, $pages);
        static::add_subclasses($pages);
    }

    protected static function get_cache(): CacheInterface
    {
        return Injector::inst()->get(CacheInterface::class . '.ListingsCache');
    }

    protected static function get_cache_key(string $suffix, ?array $classes = null): string
    {
        $key = 'ListedPageClasses-' . $suffix;
        if (!empty($classes)) {
            $key .= '-' . md5(implode('-', $classes));
        }
        return $key;
    }
}
