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
        $this->classDir = WP_PLUGIN_SAFETY_VALIDATOR_DIR . '/classes/'. $classDir .'/*.php';
        $this->namespace = 'WP_PluginSafetyValidator\\'. ucfirst($classDir);

        // Store in a filter hook to allow other plugins or themes to register their classes or exclude classes
        add_filter(WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_register_'. $this->classBaseDir, [$this, 'register_classes'], 10);
    }

    public function register_classes( array $classes ): array
    {
        foreach (glob($this->classDir) as $file) {
            $classFile = basename($file, '.php');
            $className = $this->namespace ? $this->namespace . '\\' . $classFile : $classFile;

            if (class_exists($className) &&
                !isset($this->instances[$classFile]) &&
                method_exists($className, 'instance')) {

                $classes[$classFile] = $className;
            }
        }

        return $classes;
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
