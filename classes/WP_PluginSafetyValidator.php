<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * The main class of the plugin
 *
 * @package WP_PluginSafetyValidator
 * @since 1.0.0
 *
 * @todo
 * - Add PHP version compatibility check
 * - Add WordPress version compatibility check
 * - Add malicious code detection
 * - Add change log checker - Checks for function deprecation and other changes that may affect custom code extensions
 * - Add WordPress deprecated functions checker
 * - Add a Plugin abandon checker
 */
class WP_PluginSafetyValidator
{
    protected $modules;
    protected $plugins;

    protected static $_instance;

    /**
     * Get the singleton instance of the WP_PluginSafetyValidator class
     *
     * @return WP_PluginSafetyValidator
     */
    public static function instance(): WP_PluginSafetyValidator
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->register_scripts_and_styles();
        $this->load_modules();
        $this->load_plugins();
    }

    /**
     * Auto registers JS and CSS under directories /admin/assets/js|css and /frontend/assets/js|css
     *
     * @return void
     */
    public function register_scripts_and_styles(): void
    {
        if (class_exists('WP_PluginSafetyValidator\Support\Templates\Template')) {
            add_action( 'admin_enqueue_scripts', function() {
                \WP_PluginSafetyValidator\Support\Templates\Template::initiate_register_styles_and_scripts();
            });
        }
    }

    /**
     * Load the module classes.
     *
     * @return void
     */
    public function load_modules(): void
    {
        $this->modules = new Loader('modules');
        $this->modules->load_classes();
    }

    /**
     * Load the plugin extension classes.
     *
     * @return void
     */
    public function load_plugins(): void
    {
        $this->plugins = new Loader('plugins');
        $this->plugins->load_classes();
    }

    /**
     * Get the instance of a module
     *
     * @param $module_name
     * @return mixed
     */
    public function get_module_instance($module_name)
    {
        return $this->modules->get_class_instance($module_name);
    }

    /**
     * Get all instances of the module classes
     *
     * @return mixed
     */
    public function get_all_module_instances()
    {
        return $this->modules->get_all_class_instances();
    }

    /**
     * Get the instance of a plugin
     *
     * @param $plugin_name
     * @return mixed
     */
    public function get_plugin_instance($plugin_name)
    {
        return $this->plugins->get_class_instance($plugin_name);
    }

    /**
     * Get all the instances of the plugin classes
     *
     * @return mixed
     */
    public function get_all_plugin_instances()
    {
        return $this->plugins->get_all_class_instances();
    }
} // end class