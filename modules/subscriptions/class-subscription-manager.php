<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Subscription_Manager {
    public function __construct() {
        // 1. Create subscription record as 'pending' when order is placed
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'process_new_order_subscription' ], 10, 3 );
        
        // 2. Sync status when order status changes (Admin actions or Gateways)
        add_action( 'woocommerce_order_status_changed', [ $this, 'sync_subscription_status_on_change' ], 10, 4 );
    }

    public function process_new_order_subscription( $order_id, $posted_data, $order ) {
        $user_id = $order->get_user_id();
        if ( ! $user_id ) return;

        $package_created = false;
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $package_created && $product && ( get_post_meta( $product->get_id(), 'is_package', true ) || $product->get_type() === 'package' ) ) {
                $this->create_user_subscription( $user_id, $product->get_id(), $order_id );
                $package_created = true; // Only process one package per order
            }
        }
    }

    public function sync_subscription_status_on_change( $order_id, $old_status, $new_status, $order ) {
        $subscription = Membership_Subscription_Repository::get_subscription_by_order( $order_id );
        if ( ! $subscription ) return;

        $new_internal_status = 'pending';
        $update_data = [];
        
        if ( in_array( $new_status, [ 'completed', 'processing' ] ) ) {
            $new_internal_status = 'active';
            
            // If it was cancelled/refunded and now restored, recalculate dates
            if ( in_array( $old_status, [ 'cancelled', 'refunded', 'failed' ] ) ) {
                $duration = get_post_meta( $subscription->package_id, 'package_duration', true );
                $start_date = current_time( 'mysql' );
                $end_date = $this->calculate_end_date( $start_date, $duration );
                
                $update_data['start_date'] = $start_date;
                $update_data['end_date'] = $end_date;
            }
        } elseif ( in_array( $new_status, [ 'cancelled', 'refunded', 'failed' ] ) ) {
            $new_internal_status = 'cancelled';
        }

        $update_data['status'] = $new_internal_status;
        Membership_Subscription_Repository::update_subscription( $subscription->id, $update_data );
    }

    private function create_user_subscription( $user_id, $package_id, $order_id = null ) {
        // Deactivate previous active subscription if exists
        $existing = Membership_Subscription_Repository::get_active_subscription( $user_id );
        if ( $existing ) {
            Membership_Subscription_Repository::update_subscription( $existing->id, [ 'status' => 'upgraded' ] );
        }

        // Fetch package settings
        $duration = get_post_meta( $package_id, 'package_duration', true );
        $limit_type = get_post_meta( $package_id, 'package_limit_type', true );
        $daily_limit = get_post_meta( $package_id, 'daily_limit_value', true );
        $categories = get_post_meta( $package_id, 'package_categories', true );

        $start_date = current_time( 'mysql' );
        $end_date = $this->calculate_end_date( $start_date, $duration );

        $data = [
            'user_id'              => $user_id,
            'package_id'           => $package_id,
            'order_id'             => $order_id,
            'start_date'           => $start_date,
            'end_date'             => $end_date,
            'status'               => 'pending', // Starts as pending
            'limit_type'           => $limit_type,
            'daily_limit'          => $daily_limit,
            'daily_download_count' => 0,
            'last_reset_date'      => current_time( 'Y-m-d' ),
            'allowed_categories'   => maybe_serialize( $categories ),
        ];

        Membership_Subscription_Repository::create_subscription( $data );
    }

    private function calculate_end_date( $start_date, $duration ) {
        if ( $duration === 'daily' ) return date('Y-m-d H:i:s', strtotime($start_date . ' + 1 days'));
        if ( $duration === 'monthly' ) return date('Y-m-d H:i:s', strtotime($start_date . ' + 1 months'));
        if ( $duration === '3months' ) return date('Y-m-d H:i:s', strtotime($start_date . ' + 3 months'));
        if ( $duration === '6months' ) return date('Y-m-d H:i:s', strtotime($start_date . ' + 6 months'));
        if ( $duration === 'yearly' ) return date('Y-m-d H:i:s', strtotime($start_date . ' + 1 years'));
        return null; // Lifetime
    }

    public static function is_expired( $subscription ) {
        if ( ! $subscription->end_date ) {
            return false; // Lifetime
        }
        $current_time = current_time( 'mysql' );
        return ( $current_time > $subscription->end_date );
    }
}
