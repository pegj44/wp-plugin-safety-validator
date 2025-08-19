<?php

namespace WP_PluginSafetyValidator\traits;

if (!defined('ABSPATH')) die('Access denied.');

trait ScanPluginTrait
{
    public function save_scan_record($plugin_slug, $scan_record): void
    {
        if (empty($scan_record)) {
            return;
        }

        $records = get_option( $this->option_record_key, [] );

        $records[$plugin_slug] = $scan_record;

        update_option( $this->option_record_key, $records );
    }
}