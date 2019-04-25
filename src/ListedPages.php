<?php

namespace Fromholdio\Listings;

use Fromholdio\CommonAncestor\CommonAncestor;
use Fromholdio\Listings\Extensions\ListedPageExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;

class ListedPages
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

    public static function get_classes_dropdown_source($includeSubclasses = true, $useSingular = false)
    {
        $classes = self::get_classes($includeSubclasses);
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

    public static function get_index_classes()
    {
        $classes = self::$classes;
        foreach ($classes as $class) {
            if (!$class::singleton()->config()->get('can_be_root')) {
                unset($classes[$class]);
            }
        }
        return $classes;
    }

    public static function get(array $classes = null, $parentIDs = null, $includeSubclasses = true)
    {
        if (is_null($classes) || empty($classes)) {
            $classes = self::get_classes($includeSubclasses);
        }
        else if ($includeSubclasses) {
            $classes = self::add_subclasses($classes);
        }

        $commonClass = self::get_common_class($classes);

        $filter = ['ClassName:ExactMatch' => $classes];
        if (!is_null($parentIDs) && !empty($parentIDs)) {
            if (!is_array($parentIDs)) {
                $parentIDs = [$parentIDs];
            }
            $filter['ParentID'] = $parentIDs;
        }

        return $commonClass::get()->filter($filter);
    }

    public static function filter(DataList $pages, $filter)
    {
        if ($pages->count() === 0) {
            return $pages;
        }

        $pageIDs = $pages->columnUnique('ID');
        $pageClass = $pages->dataClass();

        if (isset($filter['ID'])) {
            $filterIDs = $filter['ID'];
            if (!is_array($filterIDs)) {
                $filterIDs = [$filterIDs];
            }
            $ids = array_intersect($pageIDs, $filterIDs);

            if (count($ids) === 0) {
                $ids = [-1];
            }

            $filter['ID'] = $ids;
        }

        return $pageClass::get()->filter($filter);
    }

    public static function filter_by_ids($pages, $ids)
    {
        self::filter($pages, ['ID' => $ids]);
    }

    public static function get_common_class($classes = null)
    {
        if (!is_null($classes)) {
            self::validate_classes($classes);
        }
        else {
            $classes = self::get_classes(false);
        }

        // If only one class is configured, return it immediately.
        if (count($classes) === 1) {
            return reset($classes);
        }

        // Else return closest common ancestor class name.
        return CommonAncestor::get_closest($classes);
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

        $self = self::singleton();
        if ($self->hasMethod('validateClasses')) {
            $self->validateClasses($classes);
        }

        return true;
    }
}
