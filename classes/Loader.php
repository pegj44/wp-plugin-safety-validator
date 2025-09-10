<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class handles the loading of all the modules and plugin extensions
 */
class Loader
{
    protected $classDir;
    protected $classBaseDir;
    protected $namespace;
    protected $instances = [];

    public function __construct($classDir)
    {
        $this->classBaseDir = basename($classDir);
        $this->classDir = WP_PLUGIN_SAFETY_VALIDATOR_DIR . '/classes/'. $classDir .'/'. $classDir .'.php';

        // Store in a filter hook to allow other plugins or themes to register their classes or exclude classes
        add_filter(WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_register_'. $this->classBaseDir, [$this, 'register_classes'], 10);
    }

    public function register_classes( array $classesArray ): array
    {
        $classes = require_once $this->classDir;

        foreach ($classes as $className) {
            if (class_exists($className) && method_exists($className, 'instance')) {
                $classesArray[$className] = $className;
            }
        }

        return $classesArray;
    }

    public function load_classes(): void
    {
        $invokeClasses = apply_filters( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_register_'. $this->classBaseDir, [] );

        foreach ($invokeClasses as $class => $class_instance) {
            $this->instances[$class] = $class_instance::instance();
        }
    }

    public function get_class_instance($class)
    {
        return $this->instances[$class] ?? null;
    }

    public function get_all_class_instances()
    {
        return $this->instances;
    }
}
