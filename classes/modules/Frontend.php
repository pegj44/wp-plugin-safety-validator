<?php

namespace WP_PluginSafetyValidator\Modules;

use WP_PluginSafetyValidator\Helpers\Template;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class contains all the frontend-facing functions
 */
class Frontend
{
    protected static $_instance;

    public static function instance(): Frontend
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __construct()
    {

    }
}