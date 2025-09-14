<?php

namespace WP_PluginSafetyValidator\Modules;

use WP_PluginSafetyValidator\Helpers\VersionChecker;
use Pegj\Support\Templates\Template;
use Pegj\Support\Traits\AjaxTrait;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class contains all the admin-facing functions
 */
class Admin
{
//    use AjaxTrait;

    protected static $_instance;

    /**
     * Get the singleton instance of the Admin class
     *
     * @return Admin
     */
    public static function instance(): Admin
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct()
    {
//        $this->bootstrap_admin_ajax_actions();

//        delete_option(WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_wpv_scan_record');

        add_action( 'after_plugin_row', [$this, 'render_notice_row'], 10, 3 );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );
        add_filter( 'plugin_action_links', [$this, 'add_scan_plugin_button'], 9999, 4 );
    }

    /**
     * Enqueue the admin scripts and styles
     *
     * @return void
     */
    public function enqueue_scripts(): void
    {
        Template::enqueue_script('scripts', ['jquery'], [], true);
        Template::enqueue_style('styles');
    }

    /**
     * Handle the AJAX request to scan a plugin
     *
     * @return void
     */
    public function handle_ajax_scan_plugin(): void
    {
        $this->check_ajax_referer('scan_plugin');

        if (!isset($_POST['plugin_file'])) {
            wp_send_json_error([
                'error' => __('No plugin file was provided.', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN)
            ]);
            return;
        }

        try {
            $plugin_data = [
                'plugin_file' => preg_replace('#(\.\.|[\\\\]+|//+|\x00)#','', $_POST['plugin_file']),
                'slug' => sanitize_text_field($_POST['slug']),
                'Version' => sanitize_text_field($_POST['version']),
            ];

            $scanner = new PluginScannerHandler([
                'plugin_data' => $plugin_data,
                'save_data' => true,
            ]);
            $results = $scanner->get_results();

            wp_send_json_success([
                'results' => $results,
                'html' => $this->get_plugin_vulnerability_report_html($plugin_data['plugin_file'], $plugin_data)
            ]);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            wp_send_json_error([
                'message' => __('Something went wrong, please try again.', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN)
            ]);
        }
    }

    /**
     * Render the vulnerability notification row
     *
     * @param $plugin_file
     * @param $plugin_data
     * @param $status
     * @return void
     */
    public function render_notice_row( $plugin_file, $plugin_data, $status ): void
    {
        echo $this->get_plugin_vulnerability_report_html($plugin_file, $plugin_data);
    }

    public function get_plugin_vulnerability_report_html($plugin_file, $plugin_data, $data = []): string
    {
        $found_vulnerabilities = $this->get_vulnerabilities($plugin_data, $data);

        if ( empty($found_vulnerabilities)) {
            return '';
        }

        $classes = 'plugin-update-tr '. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'-plugin-notice';
        if ( is_plugin_active( $plugin_file ) ) {
            $classes .= ' active';
        }

        return Template::load_admin_template('plugin-issue-notification', [
            'classes' => $classes,
            'plugin_file' => $plugin_file,
            'message' => __('IMPORTANT! - This plugin has been identified as vulnerable to a known security issue. Please update as soon as possible.', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN),
            'vulnerabilities' => $found_vulnerabilities
        ]);
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
        $slug = (isset($plugin_data['slug'])? $plugin_data['slug'] : '');

        if ($this->get_vulnerabilities($plugin_data)) {
            return $actions;
        }

        $actions['scan'] = Template::load_admin_template('plugin-scan-button', [
            'slug' => $slug,
            'version' => $plugin_data['Version'],
            'plugin_file' => $plugin_file
        ]);

        return $actions;
    }

    private function get_vulnerabilities($plugin_data, $data = []): array
    {
        global $wp_plugin_vulnerabilities;

        if ( !empty($data) ) {
            $wp_plugin_vulnerabilities = $data;
        }

        if (empty($wp_plugin_vulnerabilities)) {
            $scannerHandler = new PluginScannerHandler();
            $wp_plugin_vulnerabilities = $scannerHandler->get_recorded_results();
        }

        $slug = (isset($plugin_data['slug'])? $plugin_data['slug'] : '');
        $found_vulnerabilities = [];

        foreach ($wp_plugin_vulnerabilities as $vulnerabilities) {
            if (isset($vulnerabilities[$slug])) {
                foreach ($vulnerabilities[$slug] as $vulnerability) {
                    if (VersionChecker::isVersionVulnerable($plugin_data['Version'], $vulnerability)) {
                        $found_vulnerabilities[] = $vulnerability;
                    }
                }
            }
        }

        return $found_vulnerabilities;
    }
}