<?php

namespace Fromholdio\Listings;

use Fromholdio\CommonAncestor\CommonAncestor;
use Fromholdio\Listings\Extensions\ListingsRootPageExtension;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

class ListingsRoots implements Flushable
{
    use Injectable;
    use Extensible;
    use Configurable;

    public static function get_classes($includeSubclasses = true)
    {
        $classes = [];
        $cache = self::get_cache();
        
        // retrieve classes from cache
        if ($cache->has(self::get_cache_key('Classes'))) {
            $classes = $cache->get(self::get_cache_key('Classes'));
        }
        
        if ($includeSubclasses) {
            $classes = self::add_subclasses($classes);
        }
        
        return $classes;
    }

    public static function get_classes_for_page($pageClass)
    {
        if (is_a($pageClass, SiteTree::class)) {
            $pageClass = get_class($pageClass);
        }

        ListedPages::validate_class($pageClass);

        $pageClassAncestors = ClassInfo::ancestry($pageClass);
        $allRootClasses = self::get_classes(true);
        $rootClasses = [];

        foreach ($allRootClasses as $rootClass) {
            $rootlistedPagesClasses = $rootClass::singleton()->getListedPagesClasses();
            foreach ($rootlistedPagesClasses as $rootlistedPagesClass) {

                if (in_array($rootlistedPagesClass, $pageClassAncestors)) {
                    $rootClasses[$rootClass] = $rootClass;
                    break;
                }
            }
        }

        return $rootClasses;
    }

    public static function get(array $classes = null, $includeSubclasses = true)
    {
        if (is_null($classes) || empty($classes)) {
            $classes = self::get_classes($includeSubclasses);
        }
        else {
            if (count($classes) === 1) {
                $commonClass = reset($classes);
            }
            else {
                $commonClass = self::get_common_class($classes);
            }

            if ($includeSubclasses) {
                $classes = self::add_subclasses($classes);
            }
        }

        $filter = ['ClassName:ExactMatch' => $classes];

        return $commonClass::get()->filter($filter);
    }



    public static function get_common_class($classes)
    {
        self::validate_classes($classes);

        // If only one class is configured, return it immediately.
        if (count($classes) === 1) {
            return $classes[0];
        }

        // Else return closest common ancestor class name.
        return CommonAncestor::get_closest($classes);
    }

    public static function get_common_singular_name($classes)
    {
        if (is_string($classes) && self::validate_class($classes)) {
            $class = $classes;
        }
        else {
            $class = self::get_common_class($classes);
        }
        return $class::singleton()->i18n_singular_name();
    }

    public static function get_common_plural_name($classes)
    {
        if (is_string($classes) && self::validate_class($classes)) {
            $class = $classes;
        }
        else {
            $class = self::get_common_class($classes);
        }
        return $class::singleton()->i18n_plural_name();
    }

    protected static function add_subclasses($classes)
    {
        $cache = self::get_cache();
        
        // retrieve classes from cache
        $cacheKey = self::get_cache_key('IncludeSubclasses', $classes);
        if ($cache->has($cacheKey)) {
            
            $classes = $cache->get($cacheKey);
            
        } else {
            
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

    public static function validate_class($class)
    {
        return self::validate_classes([$class]);
    }

    public static function validate_classes($classes)
    {
        if (!is_array($classes)) {
            throw new \InvalidArgumentException(
                'Classes must be passed as an array to ListingsRoots::validate_classes(). '
                . gettype($classes) . ' was supplied instead.'
            );
        }

        if (empty($classes)) {
            throw new \InvalidArgumentException(
                'ListingsRoots::validate_classes() must be passed '
                . 'at least one page class in $classes array. Array was empty.'
            );
        }

        // To confirm we're working with a non-associative array of class names as expected.
        $classes = array_values($classes);

        // Ensure all supplied classes are valid
        foreach ($classes as $class) {

            $invalidMessage = 'Invalid class passed to ListingsRoots::validate_classes(): ';

            // Check exists
            if (!ClassInfo::exists($class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' does not exist.'
                );
            }

            // Check is extended by ListedPageExtension
            if (!singleton($class)->has_extension(ListingsRootPageExtension::class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' is not extended by ListingsRootPageExtension.'
                );
            }

            // Check is a SiteTree or descendent
            if (!is_a(singleton($class), SiteTree::class)) {
                throw new \UnexpectedValueException(
                    $invalidMessage . ' ' . $class . ' is not a descendent of ' . SiteTree::class . '.'
                );
            }
        }

        $self = self::singleton();
        if ($self->hasMethod('validateClasses')) {
            $self->validateClasses($classes);
        }

        return true;
    }

    /**
     * This function is triggered early in the request if the "flush" query
     * parameter has been set. Each class that implements Flushable implements
     * this function which looks after it's own specific flushing functionality.
     *
     * @see FlushMiddleware
     */
    public static function flush()
    {
        self::get_cache()->clear();
        
        // build pages cache
        $pages = [];
        $classes = ClassInfo::subclassesFor(SiteTree::class);
        foreach ($classes as $class) {
            if ($class::has_extension(ListingsRootPageExtension::class)) {
                self::validate_class($class);
                $pages[$class] = $class;
            }
        }
        $cache = self::get_cache();
        $cacheKey = self::get_cache_key('Classes');
        $cache->set($cacheKey, $pages);
        self::add_subclasses($pages);
    }
    
    private static function get_cache() {
        return Injector::inst()->get(CacheInterface::class . '.ListingsCache');
    }
    
    private static function get_cache_key($suffix, $classes=null) {
        return 'ListingsRootClasses-'.$suffix.($classes ? md5(implode('-', $classes)) : '');
    }
}
