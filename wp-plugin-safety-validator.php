<?php

/**
 * Plugin Name: WP Plugin Safety Validator
 * Description: Validate plugins for version compatibility and security vulnerabilities.
 * Version: 1.0.0
 * License: GPL v2 or later
 * Text Domain: wp_plugin_safety_validator
 * Domain Path: /languages
 * Author: Paul Edmund Janubas
 * Author URI: https://www.linkedin.com/in/paul-edmund-janubas/
 * Requires at least: 5.6
 * Requires PHP: 8.0
 */

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

if ( ! defined( 'WP_PLUGIN_SAFETY_VALIDATOR_VERSION' ) ) {
    define( 'WP_PLUGIN_SAFETY_VALIDATOR_VERSION', '1.0.0' );
}
if ( ! defined( 'WP_PLUGIN_SAFETY_VALIDATOR_DIR' ) ) {
    define( 'WP_PLUGIN_SAFETY_VALIDATOR_DIR', __DIR__ );
}
if ( ! defined( 'WP_PLUGIN_SAFETY_VALIDATOR_URL' ) ) {
    define( 'WP_PLUGIN_SAFETY_VALIDATOR_URL', plugins_url( '', __FILE__ ) );
}
if ( ! defined( 'WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN' ) ) {
    define( 'WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN', 'wp_plugin_safety_validator' );
}

require_once WP_PLUGIN_SAFETY_VALIDATOR_DIR . '/vendor/autoload.php';

if (!class_exists('WP_PluginSafetyValidator')) :

    /**
     * Create a singleton instance of the WP_PluginSafetyValidator class
     *
     * @return WP_PluginSafetyValidator
     */
    function WP_PluginSafetyValidator(): WP_PluginSafetyValidator
    {
        return WP_PluginSafetyValidator::instance();
    }

    WP_PluginSafetyValidator();

endif;