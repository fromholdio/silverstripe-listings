<?php

namespace Fromholdio\Listings;

use Fromholdio\CommonAncestor\CommonAncestor;
use Fromholdio\Listings\Extensions\ListingsIndexExtension;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;

class ListingsIndexes implements Flushable
{
    use Injectable;
    use Extensible;
    use Configurable;

    /**
     * Use tokens rather than IDs for in-situ routing
     * and non-in-situ referring index linking.
     * --
     * Requires Token field value to be set on
     * ListingsIndexes and ListedPages.
     * @see Tokenator
     * @var bool
     */
    private static $is_tokens_enabled = true;

    private static $index_extension_class = ListingsIndexExtension::class;

    public static function is_tokens_enabled(): bool
    {
        return self::config()->get('is_tokens_enabled');
    }

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

    public static function get_classes_for_page(string $pageClass): array
    {
        ListedPages::validate_class($pageClass);
        $pageClassAncestors = ClassInfo::ancestry($pageClass);
        $allIndexClasses = static::get_classes(true);
        $indexClasses = [];
        foreach ($allIndexClasses as $indexClass)
        {
            /** @var ListingsIndexExtension&SiteTree $indexClass */
            $indexListedPageClasses = $indexClass::singleton()->getListedPagesClasses();
            foreach ($indexListedPageClasses as $indexListedPageClass)
            {
                if (in_array($indexListedPageClass, $pageClassAncestors))
                {
                    /** @var string $indexClass */
                    $indexClasses[$indexClass] = $indexClass;
                    break;
                }
            }
        }
        return $indexClasses;
    }

    public static function get(?array $classes = null, bool $includeSubclasses = true): DataList
    {
        if (empty($classes)) {
            $classes = static::get_classes($includeSubclasses);
        }
        $commonClass = static::get_common_class($classes);
        if ($includeSubclasses) {
            $classes = static::add_subclasses($classes);
        }
        $filter = ['ClassName:ExactMatch' => $classes];
        /** @var SiteTree $commonClass */
        /** @var DataList $indexes */
        $indexes = $commonClass::get()->filter($filter);
        return $indexes;
    }

    public static function get_common_class(array $classes): string
    {
        static::validate_classes($classes);

        // If only one class is configured, return it immediately.
        if (count($classes) === 1) {
            $classes = array_values($classes);
            return $classes[0];
        }

        // Else return closest common ancestor class name.
        return CommonAncestor::get_closest($classes);
    }

    public static function get_common_singular_name(array $classes): string
    {
        /** @var SiteTree&ListingsIndexExtension $class */
        $class = static::get_common_class($classes);
        return $class::singleton()->i18n_singular_name();
    }

    public static function get_common_plural_name(array $classes): string
    {
        /** @var SiteTree&ListingsIndexExtension $class */
        $class = static::get_common_class($classes);
        return $class::singleton()->i18n_plural_name();
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
        if (empty($classes)) {
            throw new \InvalidArgumentException(
                static::class . '::validate_classes() must be passed '
                . 'at least one page class in $classes array. Array was empty.'
            );
        }

        // To confirm we're working with a non-associative array of class names as expected.
        $classes = array_values($classes);

        $indexClass = static::config()->get('index_extension_class');

        // Ensure all supplied classes are valid
        foreach ($classes as $class) {

            $invalidMessage = 'Invalid class passed to ' . static::class . '::validate_classes(): ';

            // Check exists
            if (!ClassInfo::exists($class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' does not exist.'
                );
            }

            // Check is extended
            if (!$class::singleton()->hasExtension($indexClass)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' is not extended by ' . $indexClass
                );
            }

            // Check is a SiteTree or descendent
            if (!is_a($class, SiteTree::class, true)) {
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
     *
     * @see FlushMiddleware
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
            if ($class::has_extension(static::config()->get('index_extension_class'))) {
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
        $key = 'ListedIndexClasses-' . $suffix;
        if (!empty($classes)) {
            $key .= '-' . md5(implode('-', $classes));
        }
        return $key;
    }
}
