<?php

namespace WP_PluginSafetyValidator\traits;

if (!defined('ABSPATH')) die('Access denied.');

trait AjaxTrait
{
    protected $ajaxPrefixes = [
        'handle_ajax_',
        'handle_ajax_nopriv_',
        'handle_ajax_bothpriv_'
    ];

    public $nonce = [];

    public function initiate_ajax_actions(): void
    {
        add_action('plugins_loaded', [$this, 'bootstrap_ajax_actions']);
    }

    public function bootstrap_ajax_actions(): void
    {
        $ajaxActions = $this->get_ajax_actions();

        if (!empty($ajaxActions)) {

            foreach ($ajaxActions as $ajaxAction) {
                $actionName = str_replace(['handle_ajax_', 'bothpriv_', 'nopriv_'], '', $ajaxAction);

                if (str_contains($ajaxAction, 'handle_ajax_nopriv_')) {
                    add_action( 'wp_ajax_nopriv_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .$actionName, [$this, $ajaxAction] );
                }
                if(str_contains($ajaxAction, 'handle_ajax_bothpriv_')) {
                    add_action('wp_ajax_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .$actionName, [$this, $ajaxAction]);
                    add_action('wp_ajax_nopriv_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .$actionName, [$this, $ajaxAction]);
                }
                if(!str_contains($ajaxAction, 'handle_ajax_bothpriv_') && !str_contains($ajaxAction, 'handle_ajax_nopriv_')){
                    add_action('wp_ajax_'. WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .$actionName, [$this, $ajaxAction]);
                }

                $this->nonce[$actionName] = $this->create_nonce($actionName);
            }

//                add_action('wp_head', [$this, 'add_nonce_to_header']);
        }
    }

    public function add_nonce_to_header()
    {
        json_encode([
            WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_nonce' => $this->nonce,
        ]);
    }

    private function get_ajax_actions()
    {
        $reflection = new \ReflectionClass($this);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        return array_map(
            fn($method) => $method->getName(),
            array_filter($methods, fn($method) => preg_match('/^(' . implode('|', array_map('preg_quote', $this->ajaxPrefixes)) . ')/', $method->getName()))
        );
    }

    public function set_action($actionName): string
    {
        return WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $actionName;
    }

    public function check_ajax_referer($nonceName)
    {
        return check_ajax_referer( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $nonceName, 'nonce' );
    }

    public function create_nonce($nonceName): string
    {
        return wp_create_nonce( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $nonceName );
    }
}