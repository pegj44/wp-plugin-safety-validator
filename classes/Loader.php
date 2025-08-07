<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

class Loader
{
    protected $module_dir;
    protected $namespace;
    protected $instances = [];

    public function __construct($module_dir, $namespace)
    {
        $this->module_dir = $module_dir;
        $this->namespace = $namespace;
    }

    public function load_classes(): void
    {
        foreach (glob($this->module_dir) as $file) {
            $classFile = basename($file, '.php');
            $className = $this->namespace ? $this->namespace . '\\' . $classFile : $classFile;

            if (class_exists($className) &&
                !isset($this->instances[$classFile]) &&
                method_exists($className, 'instance')) {

                $this->instances[$classFile] = $className::instance();
            }
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
