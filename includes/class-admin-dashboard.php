<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Admin_Dashboard {
    
    public static function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'user_subscriptions';
        
        // Security check: Verify user capability
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'membership-system' ) );
        }

        // Handle Subscription Deletion
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_sub' && isset( $_GET['sub_id'] ) ) {
            check_admin_referer( 'delete_sub_' . $_GET['sub_id'] );
            $wpdb->delete( $table, [ 'id' => intval( $_GET['sub_id'] ) ] );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Subscription deleted successfully.', 'membership-system' ) . '</p></div>';
        }

        // Pagination setup
        $per_page = 20;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        // Fetch counts and subscriptions
        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
        $subscriptions = $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", 
            $per_page, 
            $offset 
        ) );
        
        $total_pages = ceil( $total_items / $per_page );
        
        ?>
        <div class="wrap ms-admin-page">
            <h1 class="wp-heading-inline"><?php _e( 'Membership Management', 'membership-system' ); ?></h1>
            <hr class="wp-header-end">

            <div class="ms-stats-row">
                <div class="ms-stat-card">
                    <span class="ms-stat-label">Total Subscriptions</span>
                    <span class="ms-stat-value"><?php echo $total_items; ?></span>
                </div>
                <div class="ms-stat-card">
                    <?php 
                    $active_count = $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'active'" );
                    ?>
                    <span class="ms-stat-label">Active Users</span>
                    <span class="ms-stat-value active"><?php echo $active_count; ?></span>
                </div>
            </div>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav top">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php printf( _n( '%s item', '%s items', $total_items, 'membership-system' ), number_format_i18n( $total_items ) ); ?></span>
                        <span class="pagination-links">
                            <?php if ( $current_page > 1 ) : ?>
                                <a class="prev-page button" href="<?php echo add_query_arg( 'paged', $current_page - 1 ); ?>">&lsaquo; <?php _e( 'Prev', 'membership-system' ); ?></a>
                            <?php endif; ?>
                            <span class="paging-input">
                                <span class="current-page"><?php echo $current_page; ?></span> <?php _e( 'of', 'membership-system' ); ?> <span class="total-pages"><?php echo $total_pages; ?></span>
                            </span>
                            <?php if ( $current_page < $total_pages ) : ?>
                                <a class="next-page button" href="<?php echo add_query_arg( 'paged', $current_page + 1 ); ?>"><?php _e( 'Next', 'membership-system' ); ?> &rsaquo;</a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped ms-subs-table">
                <thead>
                    <tr>
                        <th class="manage-column"><?php _e( 'User', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Package', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Limit', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Today Downloads', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Total Downloads', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Start Date', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Expiry Date', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Status', 'membership-system' ); ?></th>
                        <th class="manage-column"><?php _e( 'Actions', 'membership-system' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $subscriptions ) ) : ?>
                        <tr><td colspan="7"><?php _e( 'No subscriptions found.', 'membership-system' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $subscriptions as $sub ) : 
                            $user = get_userdata( $sub->user_id );
                            $package = wc_get_product( $sub->package_id );
                            $user_display = $user ? esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')' : 'Deleted User';
                            $package_display = $package ? esc_html($package->get_name()) : 'Unknown Package';
                            $limit_display = ($sub->limit_type === 'unlimited') ? 'Unlimited' : esc_html($sub->daily_limit);
                            $today_dl = ( $sub->last_reset_date === current_time( 'Y-m-d' ) ) ? $sub->daily_download_count : 0;
                            $status_class = 'status-' . esc_attr($sub->status);
                            
                            $delete_url = wp_nonce_url( admin_url( 'admin.php?page=membership-settings&action=delete_sub&sub_id=' . $sub->id ), 'delete_sub_' . $sub->id );
                            ?>
                            <tr>
                                <td><strong><?php echo $user_display; ?></strong></td>
                                <td><?php echo $package_display; ?></td>
                                <td><?php echo $limit_display; ?></td>
                                <td><span class="dl-count"><?php echo esc_html($today_dl); ?></span></td>
                                <td><span class="dl-count"><?php echo esc_html(isset($sub->total_download_count) ? $sub->total_download_count : 0); ?></span></td>
                                <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $sub->start_date ) ); ?></td>
                                <td><?php echo $sub->end_date ? date_i18n( get_option( 'date_format' ), strtotime( $sub->end_date ) ) : 'Lifetime'; ?></td>
                                <td><span class="ms-status-pill <?php echo $status_class; ?>"><?php echo ucfirst($sub->status); ?></span></td>
                                <td>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="button action-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this subscription?', 'membership-system' ); ?>');"><?php _e( 'Delete', 'membership-system' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="pagination-links">
                            <?php if ( $current_page > 1 ) : ?>
                                <a class="prev-page button" href="<?php echo add_query_arg( 'paged', $current_page - 1 ); ?>">&lsaquo; <?php _e( 'Prev', 'membership-system' ); ?></a>
                            <?php endif; ?>
                            <span class="paging-input">
                                <span class="current-page"><?php echo $current_page; ?></span> <?php _e( 'of', 'membership-system' ); ?> <span class="total-pages"><?php echo $total_pages; ?></span>
                            </span>
                            <?php if ( $current_page < $total_pages ) : ?>
                                <a class="next-page button" href="<?php echo add_query_arg( 'paged', $current_page + 1 ); ?>"><?php _e( 'Next', 'membership-system' ); ?> &rsaquo;</a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .ms-admin-page { margin-top: 20px; }
            .ms-stats-row { display: flex; gap: 20px; margin: 20px 0; }
            .ms-stat-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4; flex: 1; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .ms-stat-label { display: block; color: #646970; font-size: 14px; margin-bottom: 5px; }
            .ms-stat-value { font-size: 28px; font-weight: bold; color: #23282d; }
            .ms-stat-value.active { color: #2ecc71; }
            
            .ms-subs-table th { font-weight: bold; }
            .ms-status-pill { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; color: #fff; }
            .status-active { background: #2ecc71; }
            .status-upgraded { background: #3498db; }
            .status-pending { background: #f39c12; }
            .status-expired { background: #e74c3c; }
            .status-cancelled { background: #95a5a6; }
            
            .dl-count { background: #f1f1f1; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
        </style>
        <?php
    }
}
