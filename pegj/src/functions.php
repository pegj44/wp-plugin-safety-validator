<?php

if (!function_exists('pegj_get_plugin_slug')) {
    function pegj_get_plugin_slug()
    {
        $path = pegj_get_plugin_dir_path();

        return basename($path);
    }
}

if (!function_exists('pegj_get_plugin_domain')) {
    function pegj_get_plugin_domain()
    {
        return str_replace('-', '_', sanitize_title_with_dashes(pegj_get_plugin_slug()));
    }
}

if (!function_exists('pegj_get_plugin_dir_path')) {
    function pegj_get_plugin_dir_path()
    {
        $root = dirname(__DIR__, 2);

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('pegj_get_plugin_dir_url')) {
    function pegj_get_plugin_dir_url()
    {
        $rel = str_replace(WP_PLUGIN_URL, '', pegj_get_plugin_slug());

        return plugins_url($rel);
    }
}