<?php

namespace Fromholdio\Listings;

use Fromholdio\Listings\Extensions\ListingsRootPageExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class ListingsRoots
{
    use Injectable;
    use Extensible;
    use Configurable;

    protected static $classes = [];

    public static function register_class($class)
    {
        self::validate_class($class);
        self::$classes[$class] = $class;
    }

    public static function get_classes($includeSubclasses = true)
    {
        $classes = self::$classes;
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
        $classes = array_combine($classes, $classes);

        foreach ($classes as $class) {
            $subclasses = ClassInfo::subclassesFor($class);
            foreach ($subclasses as $subclass) {
                $classes[$subclass] = $subclass;
            }
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
}
