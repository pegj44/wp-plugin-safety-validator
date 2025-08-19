<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * The class that handles plugin scanning
 */
class PluginScannerHandler
{
    private $plugin_data;
    private $save_data;

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

    public function __construct($plugin_data, $save_data)
    {
        $this->plugin_data = $plugin_data;
        $this->save_data = $save_data;
    }

    private function invoke_plugin_scanner_classes(): void
    {
        foreach ($this->plugin_scanner_classes as $class) {
            $scanner = new $class();
            $this->results[] = $scanner->scan_plugin($this->plugin_data, $this->save_data);
        }
    }

    public function get_results(): array
    {
        $this->invoke_plugin_scanner_classes();

        return array_filter($this->results);
    }
} // end Class