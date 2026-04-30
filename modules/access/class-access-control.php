<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Access_Control {
    public function __construct() {
        // Filter WooCommerce download button URL/visibility
        add_filter( 'woocommerce_is_purchasable', [ $this, 'hide_add_to_cart_if_subscribed' ], 10, 2 );
        add_filter( 'woocommerce_customer_get_downloadable_products', [ $this, 'grant_virtual_access' ] );
        add_filter( 'woocommerce_get_price_html', [ $this, 'hide_price_for_subscribers' ], 100, 2 );

        // Shortcode or filter for showing custom download button
        add_shortcode( 'ms_download_button', [ $this, 'render_download_button_shortcode' ] );

        // Replace add to cart button globally
        add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'replace_add_to_cart_button' ], 10, 2 );
        add_action( 'woocommerce_single_product_summary', [ $this, 'replace_single_add_to_cart' ], 30 );
        add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'replace_single_add_to_cart' ], 5 );
        add_action( 'woocommerce_product_meta_start', [ $this, 'replace_single_add_to_cart' ], 5 );

        // Global styles and scripts (avoid repetition)
        add_action( 'wp_head', [ $this, 'inject_global_styles' ] );
        add_action( 'wp_footer', [ $this, 'inject_global_scripts' ] );

        // Robust CSS-based removal for complex themes
        add_filter( 'body_class', [ $this, 'add_download_body_class' ] );

        // Free variations dynamic download button
        add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'inject_free_variation_download_button' ] );
        add_action( 'template_redirect', [ $this, 'handle_free_download_request' ] );
    }

    public function inject_global_styles() {
        ?>
        <style>
            .ms-download-btn-large { 
                display: block; 
                width: 100%; 
                max-width: 350px;
                text-align: center; 
                padding: 12px 20px !important; 
                background: #2ecc71 !important; 
                color: #fff !important; 
                font-size: 16px !important; 
                font-weight: bold !important; 
                border-radius: 6px !important;
                margin: 10px;
                box-shadow: 0 4px 12px rgba(46, 204, 113, 0.2);
                transition: all 0.2s ease;
                text-decoration: none !important;
            }
            .ms-download-btn-large:hover { 
                background: #27ae60 !important; 
                transform: translateY(-1px);
                box-shadow: 0 6px 15px rgba(46, 204, 113, 0.3);
            }
            .ms-loop-download-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 40px !important;
                height: 40px !important;
                border-radius: 50% !important;
                background: #2ecc71 !important;
                color: #fff !important;
                padding: 0 !important;
                margin: 5px 5px !important;
                box-shadow: 0 4px 10px rgba(46,204,113,0.2) !important;
                transition: all 0.2s ease !important;
            }
            .ms-loop-download-btn:hover {
                background: #27ae60 !important;
                color: #fff !important;
                transform: translateY(-2px);
            }
            .ms-no-files { color: #e74c3c; font-style: italic; margin: 10px 0; }
            /* Hide Price and Add to cart for subscribed users */
            body.ms-user-can-download .price, 
            body.ms-user-can-download .wd-single-price,
            body.ms-user-can-download .elementor-widget-wd_single_product_price,
            body.ms-user-can-download .elementor-element-396773a, /* License price heading */
            body.ms-user-can-download form.cart, 
            body.ms-user-can-download .single_variation_wrap,
            body.ms-user-can-download .quantity,
            body.ms-user-can-download #ppc-button-ppcp-gateway,
            body.ms-user-can-download .paypal-buttons { 
                display: none !important; 
            }
        </style>
        <?php
    }

    public function inject_global_scripts() {
        if ( ! function_exists('is_product') || ! is_product() ) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            if ($("body").hasClass("ms-user-can-download")) {
                var $cartContainer = $(".elementor-widget-wd_single_product_add_to_cart, .cart, .single_variation_wrap").first();
                
                // ONLY grab from main download area to be 100% safe
                var $downloadBtn = $(".ms-main-download-area .ms-download-container").first();

                if ($cartContainer.length && $downloadBtn.length) {
                    var $clonedBtn = $downloadBtn.clone();
                    $cartContainer.after($clonedBtn);
                    $clonedBtn.show();
                    // Hide original to avoid duplicates
                    $downloadBtn.hide();
                }
            }
        });
        </script>
        <?php
    }

    public function replace_add_to_cart_button( $html, $product ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            if ( ms_can_user_download( $user_id, $product->get_id() ) ) {
                return $this->render_download_button_shortcode(['id' => $product->get_id(), 'context' => 'loop']);
            }
        }
        return $html;
    }

    public function replace_single_add_to_cart() {
        global $product, $post;
        if ( ! is_user_logged_in() || ! $product ) return;
        
        // Robust check for single product context
        if ( ! is_singular('product') && ! is_product() ) return;

        // Safety check: Only show for the main product
        $current_id = (int) $product->get_id();
        $page_id = (int) ( $post ? $post->ID : get_the_ID() );
        
        if ( $current_id !== $page_id ) {
            // Check if it's a variation of the current page product
            if ( $product->get_parent_id() && (int) $product->get_parent_id() === $page_id ) {
                // Allow
            } else {
                return;
            }
        }

        static $rendered_ids = [];
        if ( in_array( $current_id, $rendered_ids ) ) return;

        if ( ms_can_user_download( get_current_user_id(), $current_id ) ) {
            $rendered_ids[] = $current_id;
            echo '<div class="ms-main-download-area" style="margin-bottom:20px; display:none;">' . $this->render_download_button_shortcode(['id' => $current_id]) . '</div>';
        }
    }

    public function add_download_body_class( $classes ) {
        if ( is_product() ) {
            $product = wc_get_product( get_the_ID() );
            if ( $product && is_user_logged_in() ) {
                if ( ms_can_user_download( get_current_user_id(), $product->get_id() ) ) {
                    $classes[] = 'ms-user-can-download';
                }
            }
        }
        return $classes;
    }

    public static function check_category_access( $product_id, $subscription ) {
        // Access control by category logic
        $allowed = maybe_unserialize( $subscription->allowed_categories );
        // If empty or explicitly 'all', allow everything
        if ( empty( $allowed ) || $allowed === 'all' || (is_array($allowed) && empty($allowed)) ) {
            return true;
        }

        $actual_product_id = $product_id;
        $product_obj = wc_get_product( $product_id );
        if ( $product_obj && $product_obj->get_parent_id() ) {
            $actual_product_id = $product_obj->get_parent_id();
        }

        $terms = wp_get_post_terms( $actual_product_id, 'product_cat', [ 'fields' => 'ids' ] );
        
        // Include ancestors to support recursive category access
        $all_related_terms = $terms;
        foreach ( $terms as $term_id ) {
            $ancestors = get_ancestors( $term_id, 'product_cat' );
            if ( ! empty( $ancestors ) ) {
                $all_related_terms = array_merge( $all_related_terms, $ancestors );
            }
        }
        $all_related_terms = array_unique( $all_related_terms );
        
        if ( is_array( $allowed ) ) {
            $intersect = array_intersect( $all_related_terms, $allowed );
            return ! empty( $intersect );
        }

        return true;
    }

    public function hide_add_to_cart_if_subscribed( $purchasable, $product ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();

            // Always allow purchasing packages (Upgrades)
            if ( get_post_meta( $product->get_id(), 'is_package', true ) || $product->get_type() === 'package' ) {
                return $purchasable;
            }

            if ( ms_can_user_download( $user_id, $product->get_id() ) ) {
                return false; // User can just download, no need to purchase again
            }
        }
        return $purchasable;
    }

    public function hide_price_for_subscribers( $price_html, $product ) {
        if ( is_admin() || ! is_user_logged_in() ) {
            return $price_html;
        }

        // Always show price for packages
        if ( get_post_meta( $product->get_id(), 'is_package', true ) || $product->get_type() === 'package' ) {
            return $price_html;
        }

        $user_id = get_current_user_id();
        if ( ms_can_user_download( $user_id, $product->get_id() ) ) {
            return ''; // Hide price completely
        }

        return $price_html;
    }

    public function grant_virtual_access( $downloads ) {
        // Logic to inject products into WooCommerce downloads page
        // Based on subscription
        return $downloads;
    }

    public function render_download_button_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id' => 0,
            'context' => 'single'
        ], $atts );

        $product_id = intval( $atts['id'] );
        
        if ( $product_id > 0 ) {
            $_product = wc_get_product( $product_id );
        } else {
            global $product;
            $_product = $product;
        }

        if ( ! $_product ) {
            return '';
        }

        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to download.</p>';
        }

        $user_id = get_current_user_id();
        
        if ( ms_can_user_download( $user_id, $_product->get_id() ) ) {
            // Retrieve secure woocommerce file url (Support Variations)
            $files = [];
            $subscription = ms_get_cached_subscription( $user_id );
            $pkg_duration = get_post_meta( $subscription->package_id, 'package_duration', true );

            if ( $_product->is_type( 'variable' ) ) {
                $children = $_product->get_children();
                
                // Define target Tag ID based on duration
                $target_tag_id = ( $pkg_duration === 'lifetime' ) ? 79 : 78;

                foreach ( $children as $child_id ) {
                    $variation = wc_get_product( $child_id );
                    if ( ! $variation ) continue;

                    // Check if variation belongs to the correct free-update term
                    $attr_value = $variation->get_attribute( 'pa_free-update' );
                    if ( ! $attr_value ) continue;

                    $term = get_term_by( 'name', $attr_value, 'pa_free-update' );
                    if ( ! $term || (int) $term->term_id !== (int) $target_tag_id ) {
                        continue;
                    }

                    $variation_files = $variation->get_downloads();
                    foreach ( $variation_files as $fid => $fval ) {
                        $files[$fid] = [
                            'name' => $fval['name'],
                            'id' => $child_id
                        ];
                    }
                }
            } else {
                $raw_files = $_product->get_downloads();
                foreach ( $raw_files as $fid => $fval ) {
                    $files[$fid] = [ 'name' => $fval['name'], 'id' => $_product->get_id() ];
                }
            }

            $html = '<div class="ms-download-container">';
            if ( empty($files) ) {
                $html .= '<p class="ms-no-files">' . __( 'No files attached to this product.', 'membership-system' ) . '</p>';
            } else {
                foreach ( $files as $download_id => $file_info ) {
                    $download_url = add_query_arg( [
                        'ms_download' => 1,
                        'product_id'  => $file_info['id'],
                        'file_id'     => $download_id
                    ], home_url( '/' ) );
                    
                    $download_url = esc_url_raw( wp_nonce_url( $download_url, 'ms_download_' . $file_info['id'], 'ms_nonce' ) );

                    if ( $atts['context'] === 'loop' ) {
                        $html .= '<a href="' . $download_url . '" class="button alt ms-loop-download-btn" title="' . esc_attr( $file_info['name'] ) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg></a>';
                    } else {
                        $btn_label = count($files) > 1 ? __( 'Download: ', 'membership-system' ) . $file_info['name'] : __( 'Download Now', 'membership-system' );
                        $html .= '<a href="' . $download_url . '" class="button alt ms-download-btn-large">' . $btn_label . '</a>';
                    }
                }
            }
            $html .= '</div>';
            return $html;
        }

        return '<p class="ms-upsell-msg">' . __( 'Join a membership to download this item.', 'membership-system' ) . '</p>';
    }

    public function inject_free_variation_download_button() {
        global $product;
        if ( ! $product || ! $product->is_type( 'variable' ) ) return;

        $free_downloads = [];
        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation ) continue;

            if ( $variation->get_price() == 0 ) {
                $files = $variation->get_downloads();
                if ( ! empty( $files ) ) {
                    $file_id = key( $files );
                    $download_url = add_query_arg( [
                        'ms_free_download' => 1,
                        'product_id'       => $variation_id,
                        'file_id'          => $file_id
                    ], home_url( '/' ) );
                    
                    $free_downloads[ $variation_id ] = esc_url_raw( $download_url );
                }
            }
        }

        if ( empty( $free_downloads ) ) return;

        ?>
        <div class="ms-free-download-wrapper" style="display:none; margin-bottom: 20px;">
            <a href="#" class="button alt ms-free-download-btn ms-download-btn-large"><?php _e( 'Download Now', 'membership-system' ); ?></a>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var freeDownloads = <?php echo wp_json_encode( $free_downloads ); ?>;
            
            $( '.variations_form' ).on( 'show_variation', function( event, variation ) {
                if ( variation.display_price === 0 || variation.display_price === '0' || variation.display_price === 0.00 ) {
                    $('.woocommerce-variation-add-to-cart').hide();
                    $('#ppc-button-ppcp-gateway, #buttons-container, .ppc-button-wrapper, .paypal-buttons').hide(); 
                    
                    if ( freeDownloads[ variation.variation_id ] ) {
                        $('.ms-free-download-btn').attr('href', freeDownloads[ variation.variation_id ]);
                        $('.ms-free-download-wrapper').fadeIn();
                    }
                } else {
                    $('.ms-free-download-wrapper').hide();
                    $('.woocommerce-variation-add-to-cart').fadeIn();
                    $('#ppc-button-ppcp-gateway, #buttons-container, .ppc-button-wrapper, .paypal-buttons').fadeIn(); 
                }
            });

            $( '.variations_form' ).on( 'hide_variation', function() {
                $('.ms-free-download-wrapper').hide();
            });
        });
        </script>
        <?php
    }

    public function handle_free_download_request() {
        if ( ! isset( $_GET['ms_free_download'] ) || ! isset( $_GET['product_id'] ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_die( __( 'Please log in to download.', 'membership-system' ) );
        }

        $product_id = intval( $_GET['product_id'] );
        $file_id = isset( $_GET['file_id'] ) ? sanitize_text_field( $_GET['file_id'] ) : '';

        // Removed Nonce check to support full page caching for guests

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_die( __( 'Product not found.', 'membership-system' ) );
        }

        // Verify product is free
        if ( $product->get_price() != 0 ) {
            wp_die( __( 'This product is not free.', 'membership-system' ) );
        }

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
            wp_die( __( 'File not found.', 'membership-system' ) );
        }
    }
}
