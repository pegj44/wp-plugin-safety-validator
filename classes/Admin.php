<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class contains all the admin-facing functions
 */
class Admin
{
    protected static $_instance;

    public static function instance(): Admin
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct()
    {
        add_action( 'plugins_loaded', [$this, 'render_plugins_list_html'] );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );
    }

    /**
     * Loads all the plugin custom HTML elements
     *
     * @return void
     */
    public function render_plugins_list_html(): void
    {
        add_filter( 'plugin_action_links', [$this, 'add_scan_plugin_button'], 9999, 4 );
    }

    public function enqueue_scripts(): void
    {
        Template::enqueue_script('scripts', ['jquery'], true);
        Template::enqueue_style('styles');
    }

    /**
     * Add the "Scan" button to all the plugins in the plugin list page
     *
     * @param $actions
     * @param $plugin_file
     * @param $plugin_data
     * @param $context
     * @return array
     */
    public function add_scan_plugin_button($actions, $plugin_file, $plugin_data, $context): array
    {
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action='. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_scan&target_plugin=' . urlencode( $plugin_file ) ),
            WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_scan_nonce'
        );

        $actions['scan'] = Template::load_admin_view('plugin-scan-button', [
            'url' => esc_url( $url )
        ]);

        return $actions;
    }
}