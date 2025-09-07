<?php

namespace WP_PluginSafetyValidator\Support\Processes;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * Drop-in background REST executor for WordPress.
 * Lets you declare a REST route with 'background_process' => true.
 * The real callback is queued (Action Scheduler if present, else WP-Cron) and the REST response returns 202 + job_id immediately.
 */
class JobManager
{
    protected $ns;
    protected $group;
    protected $status_ttl;

    public function __construct( $namespace_prefix = 'bg', $group = 'bg-group', $status_ttl = 3600 )
    {
        $this->ns         = sanitize_key( $namespace_prefix );
        $this->group      = sanitize_key( $group );
        $this->status_ttl = (int) $status_ttl;

        // Ensure AS is available if bundled (optional).
        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            $path = __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        // Worker that executes deferred REST callbacks.
        add_action( $this->hook_for( 'rest_exec' ), array( $this, 'handle_rest_exec' ), 10, 2 );
    }

    /**
     * Register a REST route. If 'background_process' => true, the 'callback' runs in background.
     * $args: same as register_rest_route, plus:
     *   - background_process (bool) default false
     *   - task_name (string) optional identifier for the queue
     */
    public function register_rest_route_bg( $namespace, $route, $args )
    {
        if ( empty( $args['callback'] ) || ! isset( $args['methods'] ) ) {
            return;
        }

        $background = ! empty( $args['background_process'] );
        $original   = $args['callback'];

        if ( $background ) {
            // Validate we can serialize/resolve the callback later.
            if ( ! $this->is_serializable_callback( $original ) ) {
                // Hard fail early to avoid silent drops.
                $args['callback'] = array( $this, 'rest_bad_callback_response' );
            } else {
                // Wrap: enqueue job and return 202 with job_id.
                $args['callback'] = array( $this, 'rest_enqueue_wrapper' );
                // Stash original for wrapper via arg.
                $args['args']['__bg_cb']      = array(
                    'type'   => is_array( $original ) ? 'array' : 'string',
                    'value'  => is_array( $original ) ? array_values( $original ) : (string) $original,
                    'route'  => (string) $route,
                    'ns'     => (string) $namespace,
                    'task'   => ! empty( $args['task_name'] ) ? sanitize_key( $args['task_name'] ) : $this->ns . '_rest_' . md5( $namespace . '|' . $route ),
                );
                $args['args']['__bg_enabled'] = true;
            }
        }

        // Remove helper-only keys to avoid REST validation notices.
        unset( $args['background_process'], $args['task_name'] );

        register_rest_route( $namespace, $route, $args );
    }

    /**
     * Wrapper: queues the original REST callback for background execution.
     * Returns 202 + job_id immediately.
     */
    public function rest_enqueue_wrapper( \WP_REST_Request $request )
    {
        $meta = $request->get_attributes();
        if ( empty( $meta['args']['__bg_enabled'] ) || empty( $meta['args']['__bg_cb'] ) ) {
            return new \WP_Error( 'bg_invalid', 'Background wrapper misconfigured.', array( 'status' => 500 ) );
        }
        $cb = $meta['args']['__bg_cb'];

        $job_id = wp_generate_uuid4();

        // Persist initial status
        $this->save_status( $job_id, array(
            'status'  => 'queued',
            'message' => 'Background job queued.',
            'started' => current_time( 'mysql' ),
            'updated' => current_time( 'mysql' ),
            'result'  => null,
            'task'    => $cb['task'],
            'route'   => $cb['ns'] . '/' . $cb['route'],
        ) );

        // Build a minimal, serializable request snapshot.
        $payload = array(
            'cb'           => $cb,
            'method'       => $request->get_method(),
            'route'        => $cb['route'],
            'namespace'    => $cb['ns'],
            'headers'      => $request->get_headers(),
            'query_params' => $request->get_query_params(),
            'body_params'  => $request->get_body_params(),
            'json_params'  => $request->get_json_params(),
            'url_params'   => $request->get_url_params(),
            'body_raw'     => $request->get_body(), // for completeness
            'context'      => array(
                'user' => get_current_user_id(),
            ),
        );

        // Queue now (AS async if available; else WP-Cron).
        $hook = $this->hook_for( 'rest_exec' );
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( $hook, array( $job_id, $payload ), $this->group );
        } else {
            wp_schedule_single_event( time() + 1, $hook, array( $job_id, $payload ) );
        }

        // Respond with 202 Accepted and the job id.
        return new \WP_REST_Response( array( 'job_id' => $job_id ), 202 );
    }

    /**
     * Worker: reconstructs a WP_REST_Request and invokes the original callback.
     * Stores the result (non-sensitive, JSON-serializable best-effort).
     */
    public function handle_rest_exec( $job_id, $payload )
    {
        $key = $this->status_key( $job_id );
        $this->update_status( $job_id, array( 'status' => 'running', 'message' => 'Processing…' ) );

        try {
            // Restore user (best-effort).
            if ( ! empty( $payload['context']['user'] ) ) {
                wp_set_current_user( (int) $payload['context']['user'] );
            }

            // Rebuild request.
            $req = new \WP_REST_Request( $payload['method'], '/' . ltrim( $payload['namespace'] . '/' . ltrim( $payload['route'], '/' ), '/' ) );
            if ( ! empty( $payload['headers'] ) && is_array( $payload['headers'] ) ) {
                $req->set_headers( $payload['headers'] );
            }
            if ( is_array( $payload['url_params'] ) ) {
                $req->set_url_params( $payload['url_params'] );
            }
            if ( is_array( $payload['query_params'] ) ) {
                $req->set_query_params( $payload['query_params'] );
            }
            if ( is_array( $payload['body_params'] ) ) {
                $req->set_body_params( $payload['body_params'] );
            }
            if ( is_array( $payload['json_params'] ) ) {
                $req->set_json_params( $payload['json_params'] );
            }
            if ( is_string( $payload['body_raw'] ) ) {
                $req->set_body( $payload['body_raw'] );
            }

            // Resolve original callback.
            $callable = ( $payload['cb']['type'] === 'array' ) ? $payload['cb']['value'] : $payload['cb']['value'];
            if ( ! is_callable( $callable ) ) {
                throw new \RuntimeException( 'Original callback is not callable at runtime.' );
            }

            // Execute.
            $result = call_user_func( $callable, $req );

            // Normalize result for storage.
            $stored = $this->normalize_result_for_storage( $result );

            $this->update_status( $job_id, array(
                'status'  => 'completed',
                'message' => 'Completed.',
                'result'  => $stored,
            ) );

        } catch ( \Throwable $e ) {
            $this->update_status( $job_id, array(
                'status'  => 'failed',
                'message' => 'Error: ' . $e->getMessage(),
            ) );
        }
    }

    /** ----- Status API ----- */
    public function get_status( $job_id )
    {
        $data = get_option( $this->status_key( $job_id ) );
        return is_array( $data ) ? $data : null;
    }

    public function delete_status( $job_id )
    {
        delete_option( $this->status_key( $job_id ) );
    }

    /** ----- Internals ----- */
    protected function hook_for( $task )
    {
        return "{$this->ns}_run_" . sanitize_key( $task );
    }

    protected function status_key( $job_id )
    {
        $sanitized = preg_replace( '/[^a-z0-9\-]/i', '', (string) $job_id );
        return $this->ns . '_status_' . $sanitized;
    }

    protected function save_status( $job_id, array $data )
    {
        $data['_ttl'] = time() + $this->status_ttl;
        add_option( $this->status_key( $job_id ), $data, '', false );
    }

    protected function update_status( $job_id, array $data ) {
        $key     = $this->status_key( $job_id );
        $current = get_option( $key );
        if ( ! is_array( $current ) ) {
            $current = array( 'started' => current_time( 'mysql' ) );
        }
        $merged = array_merge( $current, $data, array( 'updated' => current_time( 'mysql' ), '_ttl' => time() + $this->status_ttl ) );
        update_option( $key, $merged, false );
    }
    protected function is_serializable_callback( $cb ) {
        if ( is_string( $cb ) ) {
            return true;
        }
        if ( is_array( $cb ) && count( $cb ) === 2 && is_string( $cb[0] ) && is_string( $cb[1] ) ) {
            return true; // ['ClassName','method'] static-style
        }
        return false; // Closures/objects not supported for background execution.
    }
    protected function normalize_result_for_storage( $result ) {
        if ( $result instanceof \WP_REST_Response ) {
            return array(
                'data'   => $result->get_data(),
                'status' => $result->get_status(),
                'headers'=> $result->get_headers(),
            );
        }
        if ( is_wp_error( $result ) ) {
            return array(
                'error'  => $result->get_error_message(),
                'codes'  => $result->get_error_codes(),
                'data'   => $result->get_error_data(),
            );
        }
        if ( is_scalar( $result ) || is_array( $result ) ) {
            return $result;
        }
        return array( 'info' => 'Non-serializable result type; not stored.' );
    }

    /** Error response for unsupported callbacks (e.g., closures). */
    public function rest_bad_callback_response( \WP_REST_Request $request ) {
        return new \WP_Error( 'bg_callback_unsupported', 'Background route requires a string or [Class,method] callback (no closures).', array( 'status' => 500 ) );
    }
}

/** ===================== USAGE ===================== */

/**
 * 1) Bootstrap the manager once (e.g., in your plugin main file).
 */
function myplugin_boot_bg_manager() {
    $GLOBALS['myplugin_bg'] = new JobManager( 'myplugin', 'myplugin-group', 3600 );
}
add_action( 'plugins_loaded', 'myplugin_boot_bg_manager', 5 );

/**
 * 2) Register a REST route whose callback will run in the background by setting 'background_process' => true.
 *    Example shows your exact signature, but NOTE: this makes the heavy work async; the client gets { job_id } with 202.
 */
function myplugin_register_status_route() {
    $bg = isset( $GLOBALS['myplugin_bg'] ) ? $GLOBALS['myplugin_bg'] : null;
    if ( ! $bg ) { return; }

    $bg->register_rest_route_bg(
        'myplugin/v1',
        '/job-status/(?P<job_id>[a-z0-9\-]+)',
        array(
            'methods'              => 'GET',
            'callback'             => 'myplugin_rest_job_status',   // Your real heavy callback (must be string or ['Class','method'])
            'permission_callback'  => 'myplugin_can_view_status',
            'background_process'   => true,                         // <— This is the flag you wanted
            'task_name'            => 'job_status',                  // optional, for queue grouping/visibility
            'args'                 => array(
                'job_id' => array(
                    'required' => true,
                    'validate_callback' => 'myplugin_validate_job_id',
                ),
            ),
        )
    );
}
//add_action( 'rest_api_init', 'myplugin_register_status_route' );

/**
 * 3) Your original callback (will now be executed in background by the manager).
 *    IMPORTANT: It must be a named function or ['Class','method'] (no closures).
 */
function myplugin_rest_job_status( \WP_REST_Request $request ) {
    $job_id = sanitize_text_field( $request['job_id'] );

    // Simulate heavy work (replace with real logic).
    // Example: compute/report something based on $job_id, or fetch+aggregate.
    $data = array(
        'job_id'  => $job_id,
        'ts'      => current_time( 'mysql' ),
        'message' => 'Heavy status calculation done in background.',
    );

    return new \WP_REST_Response( $data, 200 );
}

/** Permissions + validators (standard). */
function myplugin_can_view_status( \WP_REST_Request $request ) {
    return current_user_can( 'edit_posts' ) && wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
}
function myplugin_validate_job_id( $value, $request, $key ) {
    return is_string( $value ) && (bool) preg_match( '/^[a-f0-9\-]{10,}$/i', $value );
}
