<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Subscription_Repository {
    
    /**
     * Fetch active subscription for user
     */
    public static function get_active_subscription( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $user_id
        );
        
        return $wpdb->get_row( $sql );
    }

    /**
     * Fetch all subscriptions for user (History)
     */
    public static function get_user_subscriptions( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC",
            $user_id
        );
        
        return $wpdb->get_results( $sql );
    }

    /**
     * Fetch subscription by order ID
     */
    public static function get_subscription_by_order( $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d LIMIT 1", $order_id );
        return $wpdb->get_row( $sql );
    }

    /**
     * Create a new subscription record
     */
    public static function create_subscription( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }

    /**
     * Update an existing subscription
     */
    public static function update_subscription( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        $wpdb->update( $table, $data, [ 'id' => $id ] );
    }

    /**
     * Fetch user download history
     */
    public static function get_user_downloads( $user_id, $limit = 20 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_downloads';
        $posts = $wpdb->prefix . 'posts';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT d.*, p.post_title as product_name 
             FROM {$table} d
             LEFT JOIN {$posts} p ON d.product_id = p.ID 
             WHERE d.user_id = %d 
             ORDER BY d.created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ) );
    }
}
