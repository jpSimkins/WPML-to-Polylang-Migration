<?php

namespace WpmlToPolylangMigration;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

/**
 * Cron to process the actual migration since this will be very heavy and can timeout or be shutdown on ASGs.
 */
class Cron {

    const HOOK_NAME = 'wpml_to_polylang_migration';

    /**
     * Registers the cron hook.
     * MUST ALWAYS be registered for it to work.
     * @return void
     */
    public function __construct() {
        add_action(self::HOOK_NAME, __CLASS__ . '::process');
    }

    /**
     * Callback used to trigger the cron process.
     * @return void
     */
    public static function process() {
        (new Processor())->run();
    }

    /**
     * Schedules the cron single event.
     * @return void
     */
    public static function schedule() {
        if (false === \wp_next_scheduled(self::HOOK_NAME)) {
            \wp_schedule_single_event(time(), self::HOOK_NAME);
        }
    }

    /**
     * Clears to cron single event.
     * @return void
     */
    public static function clear() {
        \wp_clear_scheduled_hook(self::HOOK_NAME);
    }
}