<?php

namespace WP_PluginSafetyValidator\Interfaces;

/**
 * Interface for plugin scanner classes
 */
interface PluginScannerInterface
{
    public function scan_plugin($plugin_data, $save_data = true);

    public function save_scan_record($plugin_slug, $scan_record): void;
}