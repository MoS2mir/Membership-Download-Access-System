<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Cron_Manager {
    public function __construct() {
        add_action( 'ms_daily_cron_hook', [ $this, 'process_expired_subscriptions' ] );

        if ( ! wp_next_scheduled( 'ms_daily_cron_hook' ) ) {
            wp_schedule_event( time(), 'daily', 'ms_daily_cron_hook' );
        }
    }

    public function process_expired_subscriptions() {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        $current_time = current_time( 'mysql' );

        // Expire all subscriptions that passed the end_date and are currently active or pending
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'expired' WHERE end_date IS NOT NULL AND end_date <= %s AND status NOT IN ('expired', 'cancelled', 'upgraded')",
            $current_time
        ) );
    }
}
