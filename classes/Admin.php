<?php

namespace WP_PluginSafetyValidator;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class contains all the admin-facing functions
 */
class Admin
{
    protected static $_instance;

    public static function instance(): Admin
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