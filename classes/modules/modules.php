<?php

if (!defined('ABSPATH')) die('Access denied.');

return [
    \WP_PluginSafetyValidator\Modules\Admin::class,
    \WP_PluginSafetyValidator\Modules\PluginScannerHandler::class,
    \WP_PluginSafetyValidator\Modules\WpVulnerabilityDataFeed::class,
];