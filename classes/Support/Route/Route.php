<?php

namespace Pegj\Support\Route;

if (defined('PEGJ_ROUTE_DIR')) {
    return;
}

define( 'PEGJ_ROUTE_DIR', __DIR__ );

class Route
{
    private static $_instance = null;
    private static $requestRoutes = [];
    private static $prefix = '';
    private static $grouped = false;
    private static $permissions = [];
    private static $namespace = 'WP_PluginSafetyValidator\\';

    public static $version = '1';

    public static function registerRoutes()
    {
        foreach (self::$requestRoutes as $route) {

            $callback = explode('@', $route['callback']);

            if (2 === count($callback)) {

                $routeArgs = [
                    'methods'  => $route['method'],
                    'callback' => [$route['namespace'] . $callback[0], $callback[1]],
                    'args'     => $route['args']
                ];

                if (!empty($route['permissions'])) {
                    $routeArgs['permissions'] = $route['permissions'];
                    $routeArgs['permission_callback'] = [$route['namespace'] . 'RolesController', 'hasPermission'];
                } else {
                    $routeArgs['permission_callback'] = '__return_true';
                }

                register_rest_route($route['prefix'], $route['segment'], $routeArgs);
            }
        }
    }

    public static function formatSegment(string $segment): array
    {
        $segment = str_replace('{id}', '(?P<id>\d+)', $segment);
        $segment = trim($segment, '/') .'/';
        $segmentArr = explode('/', $segment);

        preg_match('/v[0-9.]+/i', $segmentArr[0], $version);

        if (self::$version) {
            $version = 'v'. self::$version;
        } else {
            if(!empty($version)) {
                $version = $version[0];
                unset($segmentArr[0]);
            } else {
                $version = 'v1';
            }
        }

        $prefix  = (self::$prefix)? trim(self::$prefix, '/') : '';
        $segment = implode('/', $segmentArr);
        $segment = ($segment !== '/')? $prefix .'/'. trim($segment, '/') : $prefix;

        return [
            'prefix'  => WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'/'. $version,
            'segment' => $segment
        ];
    }

    public static function post(string $segment, string $callback, array $args = []): ?Route
    {
        if (self::$_instance === null) {
            self::$_instance = new self;
        }

        $segment = self::formatSegment($segment);

        self::$requestRoutes[] = [
            'method'    => 'POST',
            'segment'   => $segment['segment'],
            'prefix'	=> $segment['prefix'],
            'args'      => $args,
            'namespace' => self::$namespace,
            'callback'  => $callback,
            'permissions' => self::$permissions
        ];

        return self::$_instance;
    }

    public static function get(string $segment, string $callback, array $args = []): ?Route
    {
        if (self::$_instance === null) {
            self::$_instance = new self;
        }

        $segment = self::formatSegment($segment);

        self::$requestRoutes[] = [
            'method'    => 'GET',
            'segment'   => $segment['segment'],
            'prefix'	=> $segment['prefix'],
            'args'      => $args,
            'namespace' => self::$namespace,
            'callback'  => $callback,
            'permissions' => self::$permissions
        ];

        return self::$_instance;
    }

    public static function group(array $args, callable $function): void
    {
        self::$grouped = true;
        self::$version = (isset($args['version']))? trim($args['version'], '/') : '';
        self::$prefix = (isset($args['prefix']))? trim($args['prefix'], '/') : '';
        self::$namespace = (isset($args['namespace']))? $args['namespace'] : self::$namespace;

        call_user_func($function);

        self::$grouped = false;
        self::$version = '';
        self::$prefix = '';
    }

    public static function groupPermissions(array $args, callable $function)
    {
        self::$permissions = $args;

        call_user_func($function);

        self::$permissions = '';
    }

    public static function getPermission( $permission )
    {
        if (strpos($permission, '@') !== false) {
            $callback = explode('@', $permission);
            return [self::$namespace . $callback[0], $callback[1]];
        }

        if (strpos($permission, '.') !== false) {
            $callback = explode('.', $permission);
            $function = $callback[0];

            return $function($callback[1]);
        }

        return false;
    }
}