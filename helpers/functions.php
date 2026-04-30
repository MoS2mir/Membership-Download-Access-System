<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper Functions easily accessible across the plugin.
 */

if ( ! function_exists( 'ms_get_cached_subscription' ) ) {
    function ms_get_cached_subscription( $user_id ) {
        static $ms_cache = [];
        
        if ( isset( $ms_cache[ $user_id ] ) ) {
            return $ms_cache[ $user_id ];
        }

        $subscription = Membership_Subscription_Repository::get_active_subscription( $user_id );
        $ms_cache[ $user_id ] = $subscription;
        
        return $subscription;
    }
}

if ( ! function_exists( 'ms_can_user_download' ) ) {
    function ms_can_user_download( $user_id, $product_id ) {
        $subscription = ms_get_cached_subscription( $user_id );
        
        if ( ! $subscription || $subscription->status !== 'active' ) {
            return false;
        }
        
        if ( Membership_Subscription_Manager::is_expired( $subscription ) ) {
            return false;
        }
        
        if ( ! Membership_Access_Control::check_category_access( $product_id, $subscription ) ) {
            return false;
        }
        
        if ( ! Membership_Limit_Manager::check_daily_limit( $user_id, $subscription ) ) {
            return false;
        }
        
        return true;
    }
}
