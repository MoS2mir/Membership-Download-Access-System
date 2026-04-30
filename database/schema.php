<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_System_Schema {
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Table 1: Subscriptions
        $table_subscriptions = $wpdb->prefix . 'user_subscriptions';
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            package_id BIGINT(20) NOT NULL,
            order_id BIGINT(20) NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NULL,
            status VARCHAR(20) NOT NULL,
            limit_type VARCHAR(20) NOT NULL,
            daily_limit INT(11) NULL,
            daily_download_count INT(11) DEFAULT 0,
            total_download_count INT(11) DEFAULT 0,
            last_reset_date DATE NULL,
            allowed_categories TEXT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_status_end (user_id, status, end_date)
        ) $charset_collate;";
        dbDelta( $sql_subscriptions );

        // Table 2: Downloads Log
        $table_downloads = $wpdb->prefix . 'user_downloads';
        $sql_downloads = "CREATE TABLE $table_downloads (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            product_id BIGINT(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_created (user_id, created_at)
        ) $charset_collate;";
        dbDelta( $sql_downloads );
    }
}
