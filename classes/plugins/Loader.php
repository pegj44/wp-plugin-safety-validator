<?php

namespace WP_PluginSafetyValidator\Plugins;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * This class validates and invokes the custom plugin extension classes.
 */
class Loader
{
    /**
     * The singleton instance of the Loader class
     *
     * @var string
     */
    protected static $_instance;

    /**
     * Get the singleton instance of the Loader class
     *
     * @return Loader
     */
    public static function instance(): Loader
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