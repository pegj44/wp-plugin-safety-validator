<?php

namespace Pegj\Support\Templates;

if (defined('PEGJ_TEMPLATE_DIR')) {
    return;
}

define( 'PEGJ_TEMPLATE_DIR', __DIR__ );

/**
 * This class handles the plugin template rendering and autoloading the styles and scripts.
 */
class Template
{
    protected static $_instance;

    public static function instance(): Template
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Initiate the register styles and scripts.
     *
     * @return void
     */
    public static function initiate_register_styles_and_scripts(): void
    {
        self::register_styles_and_scripts('js', 'admin'); // register admin scripts
        self::register_styles_and_scripts('css', 'admin'); // register admin styles
        self::register_styles_and_scripts('js'); // register frontend scripts
        self::register_styles_and_scripts('css'); // register frontend styles
    }

    /**
     * Enqueue (and optionally register) a script with dependency/footer control,
     * limited to specific admin pages if desired.
     *
     * @param string $handle                    Script handle.
     * @param string[] $deps                    Dependencies to add/merge.
     * @param array $args                       Localized arguments to add/merge.
     * @param bool|null $in_footer              true=footer, false=header, null=no change.
     * @param string $src                       If not registered, register with this src.
     * @param bool|string|null $ver             Version (false to omit).
     * @param string|string[] $only_on          Admin page(s) to load on (e.g. 'plugins.php'). Empty = all.
     * @param string|null $current_page         Pass $hook_suffix from admin_enqueue_scripts for accuracy.
     * @return bool                             True if enqueued, false otherwise.
     */
    public static function enqueue_script(string $handle, array $deps = [], array $args = [], bool $in_footer = null, string $src = '', bool|string $ver = null, array|string $only_on = [], string $current_page = null ): bool
    {
        if ( ! $handle ) {
            return false;
        }

        $handle = pegj_get_plugin_domain() .'-'. $handle;

        // example: Admin-page filter.
        $only_on = array_filter( array_unique( (array) $only_on ) );
        if ( !empty( $only_on ) ) {
            $matches = false;

            // Prefer explicit $hook_suffix from admin_enqueue_scripts.
            if ( $current_page ) {
                $matches = in_array( $current_page, $only_on, true );
            }

            // Fallback to get_current_screen() if needed.
            if ( ! $matches && function_exists( 'get_current_screen' ) ) {
                $screen = get_current_screen();
                if ( $screen ) {
                    $candidates = [$screen->id, $screen->base ];
                    foreach ( $candidates as $cand ) {
                        if ( in_array( $cand, $only_on, true ) ) {
                            $matches = true;
                            break;
                        }
                    }
                }
            }

            if ( ! $matches ) {
                return false;
            }
        }

        $deps = array_values( array_filter( array_unique( $deps ) ) );

        // Register if missing (when $src provided).
        if ( ! wp_script_is( $handle, 'registered' ) ) {
            if ( empty( $src ) ) {
                return false;
            }
            wp_register_script( $handle, $src, $deps, $ver, (bool) $in_footer );
        } else {
            // Merge deps / placement / version on existing registration.
            $wp_scripts = wp_scripts();
            if ( isset( $wp_scripts->registered[ $handle ] ) ) {
                if ( ! empty( $deps ) ) {
                    $current = (array) $wp_scripts->registered[ $handle ]->deps;
                    $wp_scripts->registered[ $handle ]->deps = array_values( array_unique( array_merge( $current, $deps ) ) );
                }
                if ( null !== $in_footer ) {
                    $wp_scripts->registered[ $handle ]->extra['group'] = $in_footer ? 1 : 0; // 0=header,1=footer
                }
                if ( null !== $ver ) {
                    $wp_scripts->registered[ $handle ]->ver = $ver;
                }
            }
        }

        wp_enqueue_script( $handle );

        if (!empty($args)) {
            wp_localize_script($handle, str_replace('-', '_', $handle), $args);
        }

        return true;
    }

    /**
     * Enqueue (and optionally register) a style with dependency/media control,
     * limited to specific admin pages if desired.
     *
     * @param string $handle            Style handle.
     * @param string[] $deps            Dependencies to add/merge.
     * @param string|null $media        Media attribute ('all','print',etc). Null=no change.
     * @param string $src               If not registered, register with this src.
     * @param bool|string|null $ver     Version (false to omit).
     * @param string|string[] $only_on  Admin page(s) to load on (e.g. 'plugins.php'). Empty = all.
     * @param string|null $current_page Pass $hook_suffix from admin_enqueue_scripts for accuracy.
     * @return bool                     True if enqueued, false otherwise.
     */
    public static function enqueue_style(string $handle, array $deps = [], string $media = null, string $src = '', bool|string $ver = null, array|string $only_on = [], string $current_page = null ): bool
    {
        if ( ! $handle ) {
            return false;
        }

        $handle = pegj_get_plugin_domain() .'-'. $handle;

        // Admin-page filter.
        $only_on = array_filter( array_unique( (array) $only_on ) );
        if ( is_admin() && ! empty( $only_on ) ) {
            $matches = false;

            if ( $current_page ) {
                $matches = in_array( $current_page, $only_on, true );
            }

            if ( ! $matches && function_exists( 'get_current_screen' ) ) {
                $screen = get_current_screen();
                if ( $screen ) {
                    $candidates = array( $screen->id, $screen->base );
                    foreach ( $candidates as $cand ) {
                        if ( in_array( $cand, $only_on, true ) ) {
                            $matches = true;
                            break;
                        }
                    }
                }
            }

            if ( ! $matches ) {
                return false;
            }
        }

        $deps = array_values( array_filter( array_unique( $deps ) ) );

        // Register if missing (when $src provided).
        if ( ! wp_style_is( $handle, 'registered' ) ) {
            if ( empty( $src ) ) {
                return false;
            }
            wp_register_style( $handle, $src, $deps, $ver, $media ? $media : 'all' );
        } else {
            // Merge deps / media / version on existing registration.
            $wp_styles = wp_styles();
            if ( isset( $wp_styles->registered[ $handle ] ) ) {
                if ( ! empty( $deps ) ) {
                    $current = (array) $wp_styles->registered[ $handle ]->deps;
                    $wp_styles->registered[ $handle ]->deps = array_values( array_unique( array_merge( $current, $deps ) ) );
                }
                if ( null !== $media ) {
                    $wp_styles->registered[ $handle ]->args = $media;
                }
                if ( null !== $ver ) {
                    $wp_styles->registered[ $handle ]->ver = $ver;
                }
            }
        }

        wp_enqueue_style( $handle );

        return true;
    }

    /**
     * Auto-register styles and scripts.
     *
     * @param string $type
     * @param string $context
     * @return void
     */
    public static function register_styles_and_scripts(string $type, string $context = 'frontend'): void
    {
        $file_dir = pegj_get_plugin_dir_path() . '/'. $context .'/assets/'. $type .'/';
        $file_url = pegj_get_plugin_dir_url() . '/'. $context .'/assets/'. $type .'/';

        if ( ! is_dir( $file_dir ) ) {
            return;
        }

        $register_type = $type === 'css' ? 'wp_register_style' : 'wp_register_script';

        foreach ( glob( $file_dir . '*.'. $type ) as $file_path ) { // loop through all files in the directory
            $handle = pegj_get_plugin_domain() . '-' . sanitize_title( basename( $file_path, '.'. $type ) );
            $version = filemtime( $file_path ); // cache-bust by last modified time

            $register_type(
                $handle,
                $file_url . basename( $file_path ),
                [],
                $version
            );
        }
    }

    /**
     * Filterable namespace used in themes for overrides, e.g. <theme>/<plugin domain>/admin/file.php
     *
     * @return string
     */
    public static function get_template_namespace(): string
    {
        $plugin_domain = pegj_get_plugin_domain();

        return apply_filters( $plugin_domain .'_template_namespace', $plugin_domain );
    }

    /**
     * Normalize a relative template name, strip dangerous parts.
     *
     * @param string $template
     * @return string
     */
    private static function normalize_template_name(string $template ): string
    {
        $template = trim( $template );
        $template = ltrim( $template, '/\\' );
        $template = str_replace( array( '..', "\0" ), '', $template );

        return preg_replace( '#\\+#', '/', $template );
    }

    /**
     * Locate a template with override support (child → parent → plugin).
     *
     * @param string $template      Without .php (e.g., 'metabox' or 'partials/item').
     * @param string $context       'admin' or 'frontend'.
     * @return string               Absolute path or empty string.
     */
    private static function locate_template(string $template, string $context ): string
    {
        $template = self::normalize_template_name( $template );
        $filename      = $template . '.php';
        $ns            = self::get_template_namespace();
        $plugin_domain = pegj_get_plugin_domain();

        $context = (!empty($context))? DIRECTORY_SEPARATOR. $context .DIRECTORY_SEPARATOR : '';

        $theme_template_file_candidates = [
            trailingslashit( get_stylesheet_directory() ) . $ns . $context . $filename,
            trailingslashit( get_template_directory() )   . $ns . $context . $filename,
        ];

        /**
         * Allow other code to adjust theme candidate paths.
         *
         * @param array  $theme_template_file_candidates
         * @param string $template
         * @param string $context
         */
        $theme_template_file_candidates = apply_filters( $plugin_domain .'_theme_templates', $theme_template_file_candidates, $template, $context );

        foreach ( $theme_template_file_candidates as $path ) {
            if ( $path && is_readable( $path ) ) {
                /** @param string $path */
                return apply_filters( $plugin_domain .'_located_template', $path, $template, $context );
            }
        }

        // Plugin fallbacks: /admin/templates or /frontend/templates.
        $plugin_template_file_candidates = [
            pegj_get_plugin_dir_path() . "$context/templates/$filename",
        ];

        /**
         * Allow other code to add/modify plugin candidates.
         *
         * @param array  $plugin_template_file_candidates
         * @param string $template
         * @param string $context
         */
        $plugin_template_file_candidates = apply_filters( $plugin_domain .'_plugin_templates', $plugin_template_file_candidates, $template, $context );

        foreach ( $plugin_template_file_candidates as $path ) {
            if ( $path && is_readable( $path ) ) {
                return apply_filters( $plugin_domain .'_located_template', $path, $template, $context );
            }
        }

        return '';
    }

    /**
     * Render utility used by admin/frontend loaders.
     *
     * @param string $template Relative name without .php.
     * @param array $args          Variables exposed to the template.
     * @param bool $echo          Echo or return.
     * @param string $context       'admin' or 'frontend'.
     * @return string               Rendered HTML.
     */
    private static function render_template(string $template, array $args = [], bool $echo = true, string $context = '' ): string
    {
        $path = self::locate_template( $template, $context );
        $plugin_domain = pegj_get_plugin_domain();

        /**
         * Filter before including the template.
         *
         * @param string $path
         * @param string $template
         * @param array  $args
         * @param string $context
         */
        $path = apply_filters( $plugin_domain .'_before_render_template', $path, $template, $args, $context );

        if ( '' === $path ) {
            return '';
        }

        if ( is_array( $args ) ) {
            // Makes $args['key'] available as $key in the template.
            extract( $args, EXTR_SKIP );
        }

        ob_start();
        include $path;
        $html = ob_get_clean();

        /**
         * Filter the rendered HTML.
         *
         * @param string $html
         * @param string $template
         * @param array  $args
         * @param string $context
         */
        $html = apply_filters( $plugin_domain .'_rendered_template_html', $html, $template, $args, $context );

        if ( $echo ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return $html;
    }

    /**
     * Load a template from with a custom file path.
     *
     * @param string $template
     * @param array $args
     * @param bool $echo
     * @return string
     */
    public static function load_view( string $template, array $args = [], bool $echo = false ): string
    {
        return self::render_template( $template, $args, $echo );
    }

    /**
     * Load an admin template from /admin/templates with override support.
     *
     * @param string $template
     * @param array $args
     * @param bool $echo
     * @return string
     */
    public static function load_admin_template(string $template, array $args = [], bool $echo = false ): string
    {
        return self::render_template( $template, $args, $echo, 'admin' );
    }

    /**
     * Load a frontend template from /frontend/templates with override support.
     *
     * @param string $template
     * @param array $args
     * @param bool $echo
     * @return string
     */
    public static function load_frontend_template(string $template, array $args = [], bool $echo = false ): string
    {
        return self::render_template( $template, $args, $echo, 'frontend' );
    }
}