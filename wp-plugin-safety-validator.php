<?php

/**
 * Plugin Name: WP Plugin Safety Validator
 * Description: Validate plugins for version compatibility and security vulnerabilities.
 * Version: 1.0.0
 * License: GPL v2 or later
 * Plugin URI:
 * Text Domain: wp_plugin_safety_validator
 * Domain: wp_plugin_safety_validator
 * Domain Path: /languages
 * Author: Paul Edmund Janubas
 * Author URI: https://www.linkedin.com/in/paul-edmund-janubas/
 * Requires at least: 4.6
 * Requires PHP: 7.4
 */

namespace WP_PluginSafetyValidator;

use WP_PluginSafetyValidator\Plugins\Loader;

if (!defined('ABSPATH')) die('Access denied.');

define('WP_PLUGIN_SAFETY_VALIDATOR_DIR', dirname(__FILE__));
define('WP_PLUGIN_SAFETY_VALIDATOR_URL', plugins_url('', __FILE__));

if (!class_exists('WP_PluginSafetyValidator')) :

/**
 * Autoload classes
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'WP_PluginSafetyValidator\\';
    $base_dir = WP_PLUGIN_SAFETY_VALIDATOR_DIR . '/classes/';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

/**
 * The main class of the plugin
 *
 * @package WP_PluginSafetyValidator
 * @since 1.0.0
 *
 */
class WP_PluginSafetyValidator
{
    const VERSION = '1.0.0';
    const PHP_VERSION_REQUIRED = '7.4'; // The minimum PHP version required
    const WP_VERSION_REQUIRED = '5.6'; // The minimum WordPress version required

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
        $this->load_admin_instance();
        $this->load_frontend_instance();

        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init(): void
    {
        $this->load_plugin_loader_instance();
    }

    /**
     * Load the plugin loader instance
     *
     * @return void
     */
    private function load_plugin_loader_instance(): void
    {
        Loader::instance();
    }

    /**
     * Load the admin instance
     *
     * @return void
     */
    private function load_admin_instance(): void
    {
        $this->get_admin_instance();
    }

    /**
     * Get the admin instance
     *
     * @return Admin
     */
    public function get_admin_instance(): Admin
    {
        return Admin::instance();
    }

    /**
     * Load the frontend instance
     *
     * @return void
     */
    private function load_frontend_instance(): void
    {
        $this->get_frontend_instance();
    }

    /**
     * Get the frontend instance
     *
     * @return Frontend
     */
    public function get_frontend_instance(): Frontend
    {
        return Frontend::instance();
    }
} // end class

endif;

/**
 * Create a singleton instance of the WP_PluginSafetyValidator class
 *
 * @return WP_PluginSafetyValidator
 */
function WP_PluginSafetyValidator(): WP_PluginSafetyValidator
{
    return WP_PluginSafetyValidator::instance();
}

/**
 * Store the singleton instance of the WP_PluginSafetyValidator class in the global scope
 */
$_GLOBALS['wp_plugin_safety_validator'] = WP_PluginSafetyValidator();