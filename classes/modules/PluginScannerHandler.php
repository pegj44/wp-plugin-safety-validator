<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * The class that handles plugin scanning
 */
class PluginScannerHandler
{
    private $args;

    /**
     * Store the classes that perform actual plugin scanning
     *
     * @var string[]
     */
    private $plugin_scanner_classes = [
//        'WP_PluginSafetyValidator\Modules\WordFenceVulnerabilityDataFeed',
        'WP_PluginSafetyValidator\Modules\WpVulnerabilityDataFeed'
    ];

    private $results = [];

    public function __construct($args = [])
    {
        $this->args = $args;
    }

    private function invoke_plugin_scanner_classes(): void
    {
        foreach ($this->plugin_scanner_classes as $class) {
            if (isset($this->args['plugin_data']) && isset($this->args['save_data'])) {
                $scanner = new $class();
                $this->results[] = $scanner->scan_plugin($this->args['plugin_data'], $this->args['save_data']);
            }
        }
    }

    public function get_recorded_results()
    {
        $saved_records = [];

        foreach ($this->plugin_scanner_classes as $class) {
            $instance = $class::instance();

            if (property_exists($instance, 'option_record_key') &&
                property_exists($instance, 'scanner_name')) {
                $option_key = $instance->option_record_key;
                $saved_records[$instance->scanner_name] = get_option($option_key, []);
            }
        }

        return $saved_records;
    }

    public function get_results(): array
    {
        $this->invoke_plugin_scanner_classes();

        return array_filter($this->results);
    }
} // end Class