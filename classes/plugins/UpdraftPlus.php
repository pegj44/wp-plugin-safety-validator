<?php

namespace WP_PluginSafetyValidator\Plugins;

if (!defined('ABSPATH')) die('Access denied.');

class UpdraftPlus
{
    public $plugin_slug = 'updraftplus';

    public function __construct()
    {
        echo 'updraftplus';
        die();
    }
}