<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_System_Hooks {
    public function register_admin_menu() {
        // Main Memberships Menu
        add_menu_page(
            __( 'Membership System', 'membership-system' ),
            __( 'Memberships', 'membership-system' ),
            'manage_woocommerce',
            'membership-settings',
            [ 'Membership_Admin_Dashboard', 'render_page' ],
            'dashicons-groups',
            56
        );

        // Submenu under Products for Packages
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Packages', 'membership-system' ),
            __( 'Packages', 'membership-system' ),
            'manage_woocommerce',
            'edit.php?post_type=product&product_type=package'
        );

        // Add New Package Submenu
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Add New Package', 'membership-system' ),
            __( 'Add New Package', 'membership-system' ),
            'manage_woocommerce',
            'post-new.php?post_type=product&ms_type=package'
        );
    }

    /* WooCommerce My Account Hooks */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        
        // Add My Subscriptions and Downloads to My Account
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_membership_tabs' ] );
        add_action( 'init', [ $this, 'add_membership_endpoints' ] );
        add_action( 'woocommerce_account_membership-access_endpoint', [ $this, 'membership_content' ] );
        add_action( 'woocommerce_account_membership-downloads_endpoint', [ $this, 'membership_downloads_content' ] );
        add_action( 'woocommerce_account_membership-followed-products_endpoint', [ $this, 'followed_products_content' ] );

        // Override WooCommerce Dashboard Template
        add_filter( 'wc_get_template', [ $this, 'override_wc_dashboard_template' ], 10, 5 );

        // Exclude Packages from main product list
        add_action( 'pre_get_posts', [ $this, 'exclude_packages_from_main_list' ] );

        // Fix status counts (All, Published, Drafts)
        add_filter( 'views_edit-product', [ $this, 'fix_product_status_counts' ] );

        // Rename Labels (Products -> Packages)
        add_filter( 'post_type_labels_product', [ $this, 'rename_product_labels_for_packages' ] );
    }

    /**
     * Override WooCommerce My Account Dashboard template
     */
    public function override_wc_dashboard_template( $located, $template_name, $args, $template_path, $default_path ) {
        if ( 'myaccount/dashboard.php' === $template_name ) {
            $plugin_template = MEMBERSHIP_SYSTEM_DIR . 'templates/my-account-dashboard.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $located;
    }

    /**
     * Rename "Products" to "Packages" only when viewing the packages list.
     */
    public function rename_product_labels_for_packages( $labels ) {
        if ( ! is_admin() || ! isset( $_GET['product_type'] ) || $_GET['product_type'] !== 'package' ) {
            return $labels;
        }

        $labels->name = __( 'Packages', 'membership-system' );
        $labels->singular_name = __( 'Package', 'membership-system' );
        $labels->add_new = __( 'Add New Package', 'membership-system' );
        $labels->add_new_item = __( 'Add New Package', 'membership-system' );
        $labels->edit_item = __( 'Edit Package', 'membership-system' );
        $labels->new_item = __( 'New Package', 'membership-system' );
        $labels->view_item = __( 'View Package', 'membership-system' );
        $labels->search_items = __( 'Search Packages', 'membership-system' );
        $labels->not_found = __( 'No packages found', 'membership-system' );
        $labels->all_items = __( 'All Packages', 'membership-system' );
        
        return $labels;
    }

    /**
     * Fix the Top Status Links (All, Published, Drafts) to show correct counts 
     * depending on whether we are in "Packages" or "General Products" view.
     */
    public function fix_product_status_counts( $views ) {
        global $wpdb;

        $is_package_view = ( isset( $_GET['product_type'] ) && $_GET['product_type'] === 'package' );
        $table_posts = $wpdb->prefix . 'posts';
        $table_term_rel = $wpdb->prefix . 'term_relationships';
        $table_term_taxi = $wpdb->prefix . 'term_taxonomy';
        $table_terms = $wpdb->prefix . 'terms';

        // Prepare query to get counts filtered by product_type taxonomy
        // We get the term_id for 'package' slug
        $package_term_id = $wpdb->get_var( "SELECT t.term_id FROM {$table_terms} t JOIN {$table_term_taxi} tt ON t.term_id = tt.term_id WHERE t.slug = 'package' AND tt.taxonomy = 'product_type'" );

        if ( ! $package_term_id ) return $views;

        $statuses = [ 'all', 'publish', 'draft' ];
        $counts = [];

        foreach ( $statuses as $status ) {
            $status_filter = ( $status === 'all' ) ? "AND post_status != 'trash'" : "AND post_status = '$status'";
            
            $query = "SELECT COUNT(DISTINCT p.ID) 
                      FROM {$table_posts} p 
                      LEFT JOIN {$table_term_rel} tr ON p.ID = tr.object_id 
                      WHERE p.post_type = 'product' $status_filter ";

            if ( $is_package_view ) {
                // Only count packages
                $query .= " AND tr.term_taxonomy_id = (SELECT term_taxonomy_id FROM {$table_term_taxi} WHERE term_id = $package_term_id)";
            } else {
                // Count products excluding packages
                $query .= " AND p.ID NOT IN (SELECT object_id FROM {$table_term_rel} WHERE term_taxonomy_id = (SELECT term_taxonomy_id FROM {$table_term_taxi} WHERE term_id = $package_term_id))";
            }

            $counts[$status] = $wpdb->get_var( $query );
        }

        // Update the views HTML
        if ( isset( $views['all'] ) ) {
            $views['all'] = preg_replace( '/\(.*?\)/', '(' . number_format_i18n( $counts['all'] ) . ')', $views['all'] );
        }
        if ( isset( $views['publish'] ) ) {
            $views['publish'] = preg_replace( '/\(.*?\)/', '(' . number_format_i18n( $counts['publish'] ) . ')', $views['publish'] );
        }
        if ( isset( $views['draft'] ) ) {
            $views['draft'] = preg_replace( '/\(.*?\)/', '(' . number_format_i18n( $counts['draft'] ) . ')', $views['draft'] );
        }

        return $views;
    }

    /**
     * Filter the main product list in admin to exclude membership packages.
     * This keeps the main products list clean and focused only on actual products.
     */
    public function exclude_packages_from_main_list( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        global $pagenow, $post_type;

        // Only target the products list page
        if ( $pagenow !== 'edit.php' || $post_type !== 'product' ) {
            return;
        }

        // If explicitly viewing packages or another type, don't interfere
        if ( isset( $_GET['product_type'] ) ) {
            return;
        }

        // Apply exclusion for 'package' type
        $tax_query = $query->get( 'tax_query' ) ?: [];
        $tax_query[] = [
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => [ 'package' ],
            'operator' => 'NOT IN',
        ];
        
        $query->set( 'tax_query', $tax_query );
    }

    public function add_membership_tabs( $items ) {
        // Insert after 'Orders' or just at the end? 
        // We will add them together
        $items['membership-access'] = __( 'My Subscriptions', 'membership-system' );
        $items['membership-downloads'] = __( 'Recent Downloads', 'membership-system' );
        $items['membership-followed-products'] = __( 'Followed Products', 'membership-system' );
        return $items;
    }

    public function add_membership_endpoints() {
        add_rewrite_endpoint( 'membership-access', EP_PAGES );
        add_rewrite_endpoint( 'membership-downloads', EP_PAGES );
        add_rewrite_endpoint( 'membership-followed-products', EP_PAGES );
    }

    public function membership_content() {
        $user_id = get_current_user_id();
        $subscriptions = Membership_Subscription_Repository::get_user_subscriptions( $user_id );

        if ( empty( $subscriptions ) ) {
            echo '<p>' . __( 'You have no membership history.', 'membership-system' ) . '</p>';
            return;
        }

        // Find active one for the top card
        $active_sub = null;
        foreach ( $subscriptions as $sub ) {
            if ( $sub->status === 'active' ) {
                $active_sub = $sub;
                break;
            }
        }

        if ( $active_sub ) {
            $package = wc_get_product( $active_sub->package_id );
            $package_name = $package ? $package->get_name() : 'Unknown Package';
            $remaining = ( $active_sub->limit_type === 'unlimited' ) ? 'Unlimited' : ( $active_sub->daily_limit - $active_sub->daily_download_count );
            ?>
            <div class="ms-dashboard active-membership-section">
                <h3><?php _e( 'Active Membership Plan', 'membership-system' ); ?></h3>
                <div class="ms-card-status">
                    <div class="ms-info">
                        <strong>Package:</strong> <span><?php echo $package_name; ?></span>
                    </div>
                    <div class="ms-info">
                        <strong>Status:</strong> <span class="status-active-text"><?php echo ucfirst($active_sub->status); ?></span>
                    </div>
                    <div class="ms-info">
                        <strong>Downloads Remaining Today:</strong> <span class="ms-highlight"><?php echo $remaining; ?></span>
                    </div>
                    <div class="ms-info">
                        <strong>Expiry Date:</strong> <span><?php echo $active_sub->end_date ? date_i18n( get_option( 'date_format' ), strtotime( $active_sub->end_date ) ) : 'Lifetime'; ?></span>
                    </div>
                </div>
            </div>
            <br>
            <?php
        }

        // History Table
        ?>
        <div class="ms-history-section">
            <h3 style="margin-bottom: 15px;"><?php _e( 'Subscription History', 'membership-system' ); ?></h3>
            <table class="shop_table shop_table_responsive ms-history-table">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Start Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $subscriptions as $sub ) : 
                        $pkg = wc_get_product( $sub->package_id );
                        $pkg_name = $pkg ? $pkg->get_name() : 'Unknown Package';
                        ?>
                        <tr>
                            <td data-title="Package"><strong><?php echo $pkg_name; ?></strong></td>
                            <td data-title="Start Date"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $sub->start_date ) ); ?></td>
                            <td data-title="Expiry Date"><?php echo $sub->end_date ? date_i18n( get_option( 'date_format' ), strtotime( $sub->end_date ) ) : 'Lifetime'; ?></td>
                            <td data-title="Status"><span class="ms-status-badge status-<?php echo $sub->status; ?>"><?php echo ucfirst($sub->status); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style>
            .ms-dashboard { background: #f9f9f9; padding: 25px; border-radius: 15px; border: 1px solid #eee; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
            .ms-card-status { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
            .ms-info { padding: 15px; background: #fff; border-radius: 8px; border-left: 5px solid #3498db; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
            .ms-highlight { color: #e74c3c; font-weight: bold; }
            .status-active-text { color: #2ecc71; font-weight: bold; }
            
            .ms-history-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border: 1px solid #eee; border-radius: 10px; overflow: hidden; }
            .ms-history-table th { background: #f8f9fa; padding: 15px; text-align: left; }
            .ms-history-table td { padding: 15px; border-top: 1px solid #eee; }
            
            .ms-status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
            .ms-status-badge.status-active { background: #e8f8f0; color: #2ecc71; }
            .ms-status-badge.status-upgraded { background: #e8f4fd; color: #3498db; }
            .ms-status-badge.status-expired { background: #fdf2f2; color: #e74c3c; }
            
            @media (max-width: 768px) { .ms-card-status { grid-template-columns: 1fr; } }
        </style>
        <?php
    }

    public function membership_downloads_content() {
        $user_id = get_current_user_id();
        $downloads = Membership_Subscription_Repository::get_user_downloads( $user_id );
        ?>
        <div class="ms-downloads-section">
            <h2 style="margin-bottom: 20px;"><?php _e( 'My Download History', 'membership-system' ); ?></h2>
            <?php if ( empty( $downloads ) ) : ?>
                <p><?php _e( 'You have not downloaded any items yet.', 'membership-system' ); ?></p>
            <?php else : ?>
                <table class="shop_table shop_table_responsive ms-history-table">
                    <thead>
                        <tr>
                            <th><?php _e( 'Product Name', 'membership-system' ); ?></th>
                            <th><?php _e( 'Date', 'membership-system' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $downloads as $dl ) : ?>
                            <tr>
                                <td data-title="Product"><strong><?php echo esc_html( $dl->product_name ?: '#' . $dl->product_id ); ?></strong></td>
                                <td data-title="Date"><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dl->created_at ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .ms-history-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border: 1px solid #eee; border-radius: 10px; overflow: hidden; }
            .ms-history-table th { background: #f8f9fa; padding: 15px; text-align: left; }
            .ms-history-table td { padding: 15px; border-top: 1px solid #eee; }
        </style>
        <?php
    }

    public function followed_products_content() {
        $user_id = get_current_user_id();
        $followed_products = get_user_meta( $user_id, 'ms_followed_products', true );
        
        ?>
        <div class="ms-followed-products-section">
            <h2 style="margin-bottom: 20px;"><?php _e( 'Followed Products', 'membership-system' ); ?></h2>
            <?php if ( empty( $followed_products ) || ! is_array( $followed_products ) ) : ?>
                <p><?php _e( 'You are not following any products yet.', 'membership-system' ); ?></p>
            <?php else : ?>
                <table class="shop_table shop_table_responsive ms-history-table">
                    <thead>
                        <tr>
                            <th><?php _e( 'Product Name', 'membership-system' ); ?></th>
                            <th><?php _e( 'Followed Version', 'membership-system' ); ?></th>
                            <th><?php _e( 'Current Version', 'membership-system' ); ?></th>
                            <th><?php _e( 'Action', 'membership-system' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $followed_products as $product_id ) : 
                            $product = wc_get_product( $product_id );
                            if ( ! $product ) continue;
                            
                            $current_version = get_post_meta( $product_id, 'ms_product_version', true );
                            if ( ! $current_version ) $current_version = 'N/A';

                            $followed_version = get_user_meta( $user_id, 'ms_followed_version_' . $product_id, true );
                            if ( ! $followed_version ) $followed_version = 'N/A';
                            
                            $update_available = ( $current_version !== 'N/A' && $followed_version !== 'N/A' && $current_version !== $followed_version );
                            ?>
                            <tr>
                                <td data-title="Product"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><strong><?php echo esc_html( $product->get_name() ); ?></strong></a></td>
                                <td data-title="Followed Version"><span style="color:#7f8c8d;"><?php echo esc_html( $followed_version ); ?></span></td>
                                <td data-title="Current Version">
                                    <span class="<?php echo $update_available ? 'ms-highlight-new' : ''; ?>" style="<?php echo $update_available ? 'font-weight:bold; color:#2ecc71;' : ''; ?>">
                                        <?php echo esc_html( $current_version ); ?>
                                    </span>
                                    <?php if ( $update_available ) : ?>
                                        <span style="background:#e74c3c;color:#fff;font-size:10px;padding:3px 8px;border-radius:12px;display:inline-block;margin-left:8px;vertical-align:middle;">New Update!</span>
                                    <?php endif; ?>
                                </td>
                                <td data-title="Action"><a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="button"><?php _e( 'View Product', 'membership-system' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <style>
            .ms-followed-products-section .ms-history-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; border: 1px solid #eee; border-radius: 10px; overflow: hidden; }
            .ms-followed-products-section .ms-history-table th { background: #f8f9fa; padding: 15px; text-align: left; }
            .ms-followed-products-section .ms-history-table td { padding: 15px; border-top: 1px solid #eee; }
            .ms-followed-products-section .ms-highlight { font-weight: bold; color: #e74c3c; }
        </style>
        <?php
    }
}
