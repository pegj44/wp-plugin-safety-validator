<?php

namespace Pegj\Support\Processes;

if (defined('PEGJ_BACKGROUND_PROCESS_DIR')) {
    return;
}

define( 'PEGJ_BACKGROUND_PROCESS_DIR', __DIR__ );

/**
 * Generic background job bus using Action Scheduler.
 * - Register handlers by string type.
 * - Enqueue jobs with type + payload.
 * - Time-boxed runs, locking, retries with backoff.
 */
class BackgroundProcess
{
    protected string $hook;         // AS hook name, e.g. '<plugin_slug>/queue/run'
    protected string $table;        // wp_{prefix}_<plugin_slug>_jobs
    protected int    $batch_size    = 25;
    protected int    $run_timeout_s = 45;
    protected string $lock_key;
    protected int    $lock_ttl_s    = 60;

    protected int    $max_attempts  = 5;
    protected bool   $retry_failures = true;
    protected array  $backoff_s     = [60, 300, 900, 3600, 21600];

    private string $plugin_slug;

    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(string $hook)
    {
        $this->hook = $hook;
        $this->table = self::get_table_name();
        $this->lock_key = 'bg_lock_' . md5($hook . '|' . $this->table);
        $this->plugin_slug = self::get_plugin_slug();

        add_action($this->hook, [$this, 'run']);
    }

    /**
     * Get the table name.
     *
     * @return string
     */
    private static function get_table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::get_plugin_slug() . '_jobs';
    }

    /**
     * Get the plugin slug.
     *
     * @return string
     */
    private static function get_plugin_slug(): string
    {
        $plugin_slug = strtok(ltrim(dirname( plugin_basename( __FILE__ ) ), "/"), "/");

        return str_replace('-', '_', sanitize_title_with_dashes($plugin_slug));
    }

    /** Register a handler callable for a given $type. */
    public function register(string $type, callable $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    /**
     * Enqueue a job for later processing.
     * @param string $type   Handler key you registered via register()
     * @param array  $payload Arbitrary data (JSON-serializable)
     * @param int|null $delay_seconds Optional delay
     */
    public function enqueue(string $type, array $payload, ?int $delay_seconds = null): void
    {
        global $wpdb;

        $available_at = gmdate('Y-m-d H:i:s', time() + max(0, (int) $delay_seconds));

        $wpdb->insert($this->table, [
            'type'        => $type,
            'status'      => 'pending',
            'payload'     => wp_json_encode($payload),
            'attempts'    => 0,
            'available_at'=> $available_at,
            'created_at'  => gmdate('Y-m-d H:i:s'),
            'updated_at'  => gmdate('Y-m-d H:i:s'),
        ], ['%s','%s','%s','%d','%s','%s','%s']);

        if (function_exists('\as_enqueue_async_action')) {
            \as_enqueue_async_action($this->hook, [], $this->plugin_slug);
        } else {
            if (!wp_next_scheduled($this->hook)) {
                wp_schedule_single_event(time() + 60, $this->hook);
            }
        }
    }

    /** Action Scheduler callback */
    public function run(): void
    {
        if (!$this->acquire_lock()) return;
        $started = time();

        try {
            do {
                $jobs = $this->claim_batch($this->batch_size);

                if (empty($jobs)) break;

                foreach ($jobs as $job) {
                    $ok = false;

                    try {
                        $payload = json_decode($job->payload, true) ?: [];
                        $handler = $this->handlers[$job->type] ?? null;

                        if (is_callable($handler)) {
                            // Your callable signature: fn(array $payload, object $job, QueueSupport $queue): bool
                            $ok = (bool) call_user_func($handler, $payload, $job, $this);
                        } else {
                            // Unknown type → fail (will retry then fail permanently)
                            $ok = false;
                            $this->record_error((int)$job->id, "No handler for type '{$job->type}'");
                        }
                    } catch (\Throwable $e) {
                        $ok = false;
                        $this->record_error((int)$job->id, $e->getMessage());
                    }

                    $this->finalize($job, $ok);

                    if ((time() - $started) >= $this->run_timeout_s) {
                        break 2;
                    }
                }
            } while (!empty($jobs) && (time() - $started) < $this->run_timeout_s);
        } finally {
            $this->release_lock();
        }

        if ($this->has_pending()) {
            as_enqueue_async_action($this->hook, [], $this->plugin_slug);
        }
    }

    /* ------------------------------ Internals ------------------------------ */

    protected function claim_batch(int $limit): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$this->table}
                 WHERE status='pending' AND available_at <= UTC_TIMESTAMP()
                 ORDER BY id ASC
                 LIMIT %d", $limit
            )
        );
        if (empty($rows)) return [];

        $ids = implode(',', array_map('intval', wp_list_pluck($rows, 'id')));
        $wpdb->query("UPDATE {$this->table}
                      SET status='processing', updated_at=UTC_TIMESTAMP()
                      WHERE id IN ($ids) AND status='pending'");

        return $wpdb->get_results("SELECT * FROM {$this->table} WHERE id IN ($ids) AND status='processing'");
    }

    protected function finalize(object $job, bool $ok): void
    {
        global $wpdb;

        if ($ok) {
            $wpdb->update($this->table, [
                'status'     => 'done',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => (int)$job->id], ['%s','%s'], ['%d']);
            return;
        }

        $attempts = (int)$job->attempts + 1;

        if ($this->retry_failures && $attempts < $this->max_attempts) {
            $delay = $this->backoff_seconds($attempts);
            $wpdb->update($this->table, [
                'status'       => 'pending',
                'attempts'     => $attempts,
                'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                'updated_at'   => gmdate('Y-m-d H:i:s'),
            ], ['id' => (int)$job->id], ['%s','%d','%s','%s'], ['%d']);
        } else {
            $wpdb->update($this->table, [
                'status'     => 'failed',
                'attempts'   => $attempts,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => (int)$job->id], ['%s','%d','%s'], ['%d']);
        }
    }

    protected function backoff_seconds(int $attempt): int
    {
        $idx = max(0, min($attempt - 1, count($this->backoff_s) - 1));
        return (int)$this->backoff_s[$idx];
    }

    protected function record_error(int $job_id, string $message): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table,
            ['last_error' => mb_substr($message, 0, 10000), 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['id' => $job_id],
            ['%s','%s'],
            ['%d']
        );
    }

    protected function has_pending(): bool
    {
        global $wpdb;
        return (bool)$wpdb->get_var("SELECT 1 FROM {$this->table}
            WHERE status='pending' AND available_at <= UTC_TIMESTAMP() LIMIT 1");
    }

    protected function acquire_lock(): bool
    {
        if (get_site_transient($this->lock_key)) return false;
        set_site_transient($this->lock_key, 1, $this->lock_ttl_s);
        return true;
    }

    protected function release_lock(): void
    {
        delete_site_transient($this->lock_key);
    }

    /** Create table once on activation */
    public static function create_table(): void
    {
        global $wpdb;

        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payload LONGTEXT NULL,
            attempts INT NOT NULL DEFAULT 0,
            last_error LONGTEXT NULL,
            available_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status),
            KEY available_at (available_at),
            KEY status_available (status, available_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}