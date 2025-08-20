<?php

namespace WP_PluginSafetyValidator\traits;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * Trait for adding, managing, and executing custom WP-Ajax in a reusable way.
 */
trait AjaxTrait
{
    protected $ajaxPrefixes = [
        'handle_ajax_',         // only private
        'handle_ajax_nopriv_',  // only public
        'handle_ajax_bothpriv_' // both public and private
    ];

    public $nonce = [];

    /**
     * Bootstrap the admin ajax actions
     *
     * @return void
     */
    public function bootstrap_admin_ajax_actions(): void
    {
        add_action('plugins_loaded', [$this, 'initiate_admin_ajax_actions']);
    }

    /**
     * Bootstrap the frontend ajax actions
     *
     * @return void
     */
    public function bootstrap_frontend_ajax_actions(): void
    {
        add_action( 'plugins_loaded', [$this, 'initiate_frontend_ajax_actions'] );
    }

    /**
     * Initiate the admin ajax actions
     *
     * @return void
     */
    public function initiate_admin_ajax_actions(): void
    {
        $this->add_ajax_actions('admin');
    }

    /**
     * Initiate the frontend ajax actions
     *
     * @return void
     */
    public function initiate_frontend_ajax_actions(): void
    {
        $this->add_ajax_actions('frontend');
    }

    /**
     * Add the ajax actions with or without private access based on the function prefix
     * 'bothpriv'_ - both public and private
     * 'nopriv_' - only public
     * 'handle_ajax_' - only private
     *
     * @param $context
     * @return void
     */
    private function add_ajax_actions($context): void
    {
        $ajaxActions = $this->get_ajax_actions();

        if (!empty($ajaxActions)) {

            foreach ($ajaxActions as $ajaxAction) {
                $actionName = str_replace(['handle_ajax_', 'bothpriv_', 'nopriv_'], '', $ajaxAction);

                if ($context === 'admin') { // admin related ajax always needs to be private
                    add_action('wp_ajax_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $actionName, [$this, $ajaxAction]);
                } else {
                    if (str_contains($ajaxAction, 'handle_ajax_nopriv_')) {
                        add_action( 'wp_ajax_nopriv_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $actionName, [$this, $ajaxAction] );
                    }
                    if(str_contains($ajaxAction, 'handle_ajax_bothpriv_')) {
                        add_action('wp_ajax_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $actionName, [$this, $ajaxAction]);
                        add_action('wp_ajax_nopriv_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $actionName, [$this, $ajaxAction]);
                    }
                    if(!str_contains($ajaxAction, 'handle_ajax_bothpriv_') && !str_contains($ajaxAction, 'handle_ajax_nopriv_')){
                        add_action('wp_ajax_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $actionName, [$this, $ajaxAction]);
                    }
                }

                // create nonce for each ajax action
                $this->nonce[$actionName] = $this->create_nonce($actionName);
            }

            if ($context === 'admin') {
                add_action('admin_head', [$this, 'add_nonce_to_header']);
            }

            if ($context === 'frontend') {
                add_action('wp_head', [$this, 'add_nonce_to_header']);
            }
        }
    }

    /**
     * Automatically add nonce to the header for ajax requests
     * Nonce keys are created based on the function name prefixed with the constant MY_PLUGIN_DOMAIN
     * JavaScript ajax data:
     * nonce: <name of action without the prefix> - E.g. 'print_hello_world'
     * action: <name of action with the prefix> - E.g. 'my_plugin_domain_print_hello_world'
     *
     * @return void
     */
    public function add_nonce_to_header(): void
    {
        echo '<script type="text/javascript">';
            echo 'const '. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_ajax = '. wp_json_encode([
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => $this->nonce,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo '</script>';
    }

    /**
     * Get all ajax actions from the class
     *
     * @return array|string[]
     */
    private function get_ajax_actions()
    {
        $reflection = new \ReflectionClass($this);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        return array_map(
            fn($method) => $method->getName(),
            array_filter($methods, fn($method) => preg_match('/^(' . implode('|', array_map('preg_quote', $this->ajaxPrefixes)) . ')/', $method->getName()))
        );
    }

    /**
     * Check if the ajax request is valid
     *
     * @param $nonceName
     * @return false|int|mixed|null
     */
    public function check_ajax_referer($nonceName)
    {
        return check_ajax_referer( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $nonceName, 'nonce' );
    }

    /**
     * Create the nonce
     *
     * @param $nonceName
     * @return string
     */
    public function create_nonce($nonceName): string
    {
        return wp_create_nonce( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $nonceName );
    }
}