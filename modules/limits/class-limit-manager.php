<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Limit_Manager {
    public function __construct() {
        // Hook for handling custom download endpoint
        add_action( 'template_redirect', [ $this, 'handle_download_request' ] );
        add_action( 'wp_footer', [ $this, 'render_limit_popup' ] );
    }

    public static function check_daily_limit( $user_id, $subscription ) {
        // Live Expiration Check
        if ( Membership_Subscription_Manager::is_expired( $subscription ) ) {
            return false;
        }

        // If limit type is unlimited OR daily_limit is not set/zero, allow everything
        if ( $subscription->limit_type === 'unlimited' || empty($subscription->daily_limit) || $subscription->daily_limit <= 0 ) {
            return true;
        }

        // Lazy reset
        $current_date = current_time( 'Y-m-d' );
        if ( $subscription->last_reset_date !== $current_date ) {
            self::reset_daily_limit( $subscription->id, $current_date );
            $subscription->daily_download_count = 0;
            $subscription->last_reset_date = $current_date;
        }

        if ( $subscription->daily_download_count >= $subscription->daily_limit ) {
            return false;
        }

        return true;
    }

    public static function increment_download_count( $user_id, $product_id, $subscription_id ) {
        global $wpdb;
        $table_sub = $wpdb->prefix . 'user_subscriptions';
        
        // Update subscription counter
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_sub} SET daily_download_count = daily_download_count + 1, total_download_count = total_download_count + 1 WHERE id = %d",
            $subscription_id
        ) );

        // Log the download
        $table_dl = $wpdb->prefix . 'user_downloads';
        $wpdb->insert( $table_dl, [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'created_at' => current_time( 'mysql' )
        ] );
    }

    public static function reset_daily_limit( $subscription_id, $date ) {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        $wpdb->update( $table, [
            'daily_download_count' => 0,
            'last_reset_date' => $date
        ], [ 'id' => $subscription_id ] );
    }

    public function handle_download_request() {
        if ( ! isset( $_GET['ms_download'] ) || ! isset( $_GET['product_id'] ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_die( 'Please log in.' );
        }

        $user_id = get_current_user_id();
        $product_id = intval( $_GET['product_id'] );
        $file_id = isset( $_GET['file_id'] ) ? sanitize_text_field( $_GET['file_id'] ) : '';

        // Security check: Nonce verification
        if ( ! isset( $_GET['ms_nonce'] ) || ! wp_verify_nonce( $_GET['ms_nonce'], 'ms_download_' . $product_id ) ) {
            wp_die( 'Security check failed. Please refresh the page and try again.' );
        }

        $subscription = ms_get_cached_subscription( $user_id );

            if ( ms_can_user_download( $user_id, $product_id ) ) {
                // Transient Lock for Race Conditions
                $lock_key = 'ms_download_lock_' . $user_id;
                if ( get_transient( $lock_key ) ) {
                    $redirect_url = get_permalink( $product_id );
                    $redirect_url = $redirect_url ? add_query_arg( 'ms_error', 'limit_reached', $redirect_url ) : home_url( '/' );
                    wp_redirect( $redirect_url );
                    exit;
                }
                set_transient( $lock_key, true, 3 ); // 3 seconds lock

                self::increment_download_count( $user_id, $product_id, $subscription->id );

                $product = wc_get_product( $product_id );
                $files = $product->get_downloads();
                
                if ( isset( $files[ $file_id ] ) ) {
                    $download_url = $files[ $file_id ]['file'];
                    $file_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $download_url );
                    if ( file_exists( $file_path ) ) {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($file_path));
                        readfile($file_path);
                        exit;
                    } else {
                        wp_redirect( $download_url );
                        exit;
                    }
                } else {
                    wp_die( 'File not found.' );
                }
            } else {
                $redirect_url = get_permalink( $product_id );
                if ( ! $redirect_url ) {
                    $redirect_url = home_url( '/' );
                }
                $redirect_url = add_query_arg( 'ms_error', 'limit_reached', $redirect_url );
                wp_redirect( $redirect_url );
                exit;
            }
    }

    public function render_limit_popup() {
        if ( isset( $_GET['ms_error'] ) && $_GET['ms_error'] === 'limit_reached' ) {
            ?>
            <div id="ms-limit-popup" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(5px);">
                <div style="background:#fff;padding:40px;border-radius:15px;text-align:center;max-width:400px;box-shadow:0 15px 35px rgba(0,0,0,0.2);animation:msPopupFadeIn 0.3s ease-out;">
                    <div style="color:#e74c3c;font-size:50px;margin-bottom:20px;">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </div>
                    <h3 style="margin:0 0 10px 0;color:#2c3e50;font-size:24px;font-family:'Inter', sans-serif;font-weight:700;">Daily Limit Reached</h3>
                    <p style="color:#7f8c8d;font-size:16px;line-height:1.6;margin-bottom:30px;font-family:'Inter', sans-serif;">
                        You have reached your daily download limit. You can continue downloading tomorrow.
                    </p>
                    <button onclick="document.getElementById('ms-limit-popup').style.display='none'; if(window.history.replaceState){ var url=window.location.protocol+'//'+window.location.host+window.location.pathname; window.history.replaceState({path:url},'',url); }" style="background:#e74c3c;color:#fff;border:none;padding:12px 30px;font-size:16px;border-radius:8px;cursor:pointer;font-weight:bold;transition:all 0.2s;box-shadow:0 4px 15px rgba(231,76,60,0.3);">Understood</button>
                </div>
            </div>
            <style>
                @keyframes msPopupFadeIn { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
                #ms-limit-popup button:hover { background: #c0392b; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(231,76,60,0.4); }
            </style>
            <?php
        }
    }
}
