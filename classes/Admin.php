<?php

namespace WP_PluginSafetyValidator;

use WP_PluginSafetyValidator\Helpers\VersionChecker;
use WP_PluginSafetyValidator\Modules\WordFenceVulnerabilityDataFeed;
use WP_PluginSafetyValidator\Modules\WpVulnerabilityDataFeed;
use WP_PluginSafetyValidator\traits\AjaxTrait;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class contains all the admin-facing functions
 */
class Admin
{
    use AjaxTrait;

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
        $this->register_ajax_actions($this);

//        delete_option(WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_wf_scan_record');
//        delete_option(WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_wpv_scan_record');

        add_action( 'after_plugin_row', [$this, 'render_notice_row'], 10, 3 );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );
        add_filter( 'plugin_action_links', [$this, 'add_scan_plugin_button'], 9999, 4 );
    }

    public function enqueue_scripts(): void
    {
        Template::enqueue_script('scripts', ['jquery'], [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'scan_action' => $this->set_action('scan_plugin'),
            'scan_nonce' => $this->create_nonce('scan_nonce')
        ], true);

        Template::enqueue_style('styles');
    }

    public function handle_ajax_scan_plugin()
    {
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
                'version' => sanitize_text_field($_POST['version']),
            ];

            $WF_data_feed = WordFenceVulnerabilityDataFeed::instance();
            $wf_response = $WF_data_feed->scan_plugin($plugin_data, true);

            $Wp_vuln_data_feed = WpVulnerabilityDataFeed::instance();
            $wp_vuln_response = $Wp_vuln_data_feed->scan_plugin($plugin_data, true);

            $results = array_filter([$wp_vuln_response, $wf_response]);

            wp_send_json_success([
                'results' => $results
            ]);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            wp_send_json_error([
                'message' => __('Something went wrong, please try again.', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN)
            ]);
        }
    }

    public function render_notice_row( $plugin_file, $plugin_data, $status )
    {
        $found_vulnerabilities = $this->get_vulnerabilities($plugin_data);

        if ( empty($found_vulnerabilities)) {
            return;
        }

        $classes = 'plugin-update-tr custom-plugin-row-notice';
        if ( 'active' === $status || is_plugin_active( $plugin_file ) ) {
            $classes .= ' active';
        }

        $message = 'IMPORTANT! - This plugin has been identified as vulnerable to a known security issue. Please update as soon as possible.';

        Template::load_admin_view('plugin-issue-notification', [
            'classes' => $classes,
            'plugin_file' => $plugin_file,
            'message' => $message,
            'vulnerabilities' => $found_vulnerabilities
        ], true);
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

        $actions['scan'] = Template::load_admin_view('plugin-scan-button', [
            'slug' => $slug,
            'version' => $plugin_data['Version'],
            'plugin_file' => $plugin_file
        ]);

        return $actions;
    }

    private function get_vulnerabilities($plugin_data): array
    {
        global $wp_plugin_vulnerabilities;

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