<?php

namespace WP_PluginSafetyValidator\traits;

if (!defined('ABSPATH')) die('Access denied.');

/**
 * Trait for adding, managing, and executing custom WP-Cron schedules in a reusable way.
 *
 * Returns an array of schedule configs.
 * Each config: [
 *    'interval_slug' => (string),
 *    'interval_args' => (array),
 *    'callback'      => (callable|string),
 * ]
 */
trait SchedulerTrait
{
    /**
     * Registers cron interval filter, scheduling, and event hooks.
     * Call this once in the class to enable all custom schedules.
     *
     * @return void
     */
    public function register_schedulers(): void
    {
        add_filter('cron_schedules', [$this, 'add_custom_intervals']);
        add_action('wp', [$this, 'schedule_events']);

        $this->load_custom_schedules();
    }

    /**
     * Registers each schedule's callback as a WordPress action.
     *
     * @return void
     */
    public function load_custom_schedules(): void
    {
        foreach ($this->safe_get_schedules() as $schedule) {
            $hook = $this->get_hook_name($schedule['callback']);
            if (
                isset($hook, $schedule['callback'])
            ) {
                add_action(
                    $hook,
                    is_string($schedule['callback']) ? [$this, $schedule['callback']] : $schedule['callback']
                );
            }
        }
    }

    /**
     * Adds all custom intervals to WordPress's cron schedule list.
     * Called automatically when WP builds its cron schedules.
     *
     * @param array $schedules Existing WP cron schedules.
     * @return array Modified schedules array with custom intervals.
     */
    public function add_custom_intervals(array $schedules) : array
    {
        foreach ($this->safe_get_schedules() as $schedule) {
            if (!empty($schedule['interval_args']) && isset($schedule['interval_slug'])) {
                $schedules[$schedule['interval_slug']] = $schedule['interval_args'];
            }
        }
        return $schedules;
    }

    /**
     * Schedules each custom cron event if it is not already scheduled.
     * Called on the 'wp' action hook.
     *
     * @return void
     */
    public function schedule_events(): void
    {
        foreach ($this->safe_get_schedules() as $schedule) {
            $hook = $this->get_hook_name($schedule['callback']);
            if (isset($hook, $schedule['interval_slug'])) {
                if (!wp_next_scheduled($hook)) {
                    wp_schedule_event(time(), $schedule['interval_slug'], $hook);
                }
            }
        }
    }

    private function get_hook_name($hook): string
    {
        return WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_'. $hook;
    }

    /**
     * Helper function to safely retrieve the schedules array.
     * Logs an error if 'get_schedules' is missing or returns invalid data.
     *
     * @return array Schedules config or empty array on error.
     */
    private function safe_get_schedules(): array
    {
        if (method_exists($this, 'get_schedules')) {
            $schedules = $this->get_schedules();
            if (is_array($schedules)) {
                return $schedules;
            } else {
                error_log('WP_Multi_Scheduler: get_schedules() did not return an array.');
            }
        } else {
            error_log('WP_Multi_Scheduler: get_schedules() method not found.');
        }
        return [];
    }
}
