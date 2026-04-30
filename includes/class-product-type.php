<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



class Membership_Product_Package {
    public function __construct() {
        // Register Product Type
        add_filter( 'product_type_selector', [ $this, 'add_package_product_type' ] );
        add_action( 'init', [ $this, 'register_package_product_type' ] );

        // Link product type to class
        add_filter( 'woocommerce_product_class', [ $this, 'package_product_class' ], 10, 2 );
        
        // Show/Hide relevant tabs/fields
        add_action( 'admin_footer', [ $this, 'package_type_custom_js' ] );
        add_action( 'admin_footer', [ $this, 'auto_select_package_type_js' ] );
        
        // Add Custom Fields to Product Data Tab
        add_action( 'woocommerce_product_data_panels', [ $this, 'package_options_product_tab_content' ] );
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'package_options_product_tab' ] );
        
        // Save fields
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_package_options_fields' ] );

        // Force quantity 1 (Sold individually)
        add_filter( 'woocommerce_is_sold_individually', [ $this, 'package_sold_individually' ], 10, 2 );

        // Hide quantity column in cart/checkout
        add_action( 'wp_head', [ $this, 'hide_quantity_column_css' ] );

        // 1. Logic: Clear cart BEFORE adding a package (Single focus)
        add_action( 'woocommerce_add_to_cart', [ $this, 'clear_cart_on_package_add' ], 10, 6 );

        // 2. Logic: Force quantity to 1 during cart/checkout calculations
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'force_package_quantity_to_one' ], 10, 1 );

        // 3. UI: Change "Product" to "Package" in cart/checkout
        add_filter( 'gettext', [ $this, 'change_product_label_to_package' ], 20, 3 );

        // 4. Fix: Force purchasable for package type
        add_filter( 'woocommerce_is_purchasable', [ $this, 'package_is_purchasable' ], 10, 2 );
    }

    public function add_package_product_type( $types ) {
        $types['package'] = __( 'Membership Package', 'membership-system' );
        return $types;
    }

    public function register_package_product_type() {
        // Definition is moved outside of this class to avoid nesting errors
    }

    public function package_options_product_tab( $tabs ) {
        $tabs['membership_package'] = array(
            'label'    => __( 'Package Settings', 'membership-system' ),
            'target'   => 'membership_package_options',
            'class'    => array( 'show_if_package' ),
            'priority' => 21,
        );
        return $tabs;
    }

    public function package_options_product_tab_content() {
        ?>
        <div id="membership_package_options" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <?php
                // Security nonce
                wp_nonce_field( 'ms_save_package_meta', 'ms_package_meta_nonce' );

                // Is Package Hidden Field
                woocommerce_wp_hidden_input([
                    'id'    => 'is_package',
                    'value' => '1'
                ]);

                // Duration
                woocommerce_wp_select([
                    'id'      => 'package_duration',
                    'label'   => __( 'Duration', 'membership-system' ),
                    'options' => [
                        'daily'    => __( 'Daily', 'membership-system' ),
                        'monthly'  => __( 'Monthly', 'membership-system' ),
                        '3months'  => __( '3 Months', 'membership-system' ),
                        '6months'  => __( '6 Months', 'membership-system' ),
                        'yearly'   => __( 'Yearly', 'membership-system' ),
                        'lifetime' => __( 'Lifetime', 'membership-system' ),
                    ],
                ]);

                // Limit Type
                woocommerce_wp_select([
                    'id'      => 'package_limit_type',
                    'label'   => __( 'Limit Type', 'membership-system' ),
                    'options' => [
                        'limited'   => __( 'Limited Downloads', 'membership-system' ),
                        'unlimited' => __( 'Unlimited Downloads', 'membership-system' ),
                    ],
                ]);

                // Daily Limit Value
                woocommerce_wp_text_input([
                    'id'          => 'daily_limit_value',
                    'label'       => __( 'Daily Download Limit', 'membership-system' ),
                    'desc_tip'    => true,
                    'description' => __( 'Number of allowed downloads per day.', 'membership-system' ),
                    'type'        => 'number',
                    'custom_attributes' => [
                        'step' => '1',
                        'min'  => '0'
                    ],
                ]);

                // Categories with Hierarchy
                $categories = get_terms( 'product_cat', [ 'hide_empty' => false ] );
                $hierarchy = [];
                if ( ! is_wp_error( $categories ) ) {
                    foreach ( $categories as $cat ) {
                        $hierarchy[ $cat->parent ][] = $cat;
                    }
                }

                $selected_cats = get_post_meta( get_the_ID(), 'package_categories', true );
                if ( ! is_array( $selected_cats ) ) $selected_cats = [];

                echo '<p class="form-field">
                        <label>' . __( 'Allowed Categories', 'membership-system' ) . '</label>
                        <select id="package_categories" name="package_categories[]" multiple="multiple" style="width: 50%;" class="wc-enhanced-select">';
                
                function ms_render_hierarchical_options( $parent_id, $hierarchy, $level = 0, $selected_cats ) {
                    if ( ! isset( $hierarchy[ $parent_id ] ) ) return;
                    foreach ( $hierarchy[ $parent_id ] as $cat ) {
                        $indent = str_repeat( '&nbsp;&nbsp;', $level * 2 );
                        $prefix = $level > 0 ? '— ' : '';
                        
                        // Get all children for JS auto-select
                        $children = get_term_children( $cat->term_id, 'product_cat' );
                        $children_json = ! is_wp_error( $children ) ? wp_json_encode( $children ) : '[]';

                        echo '<option value="' . esc_attr( $cat->term_id ) . '" ' . 
                             ( in_array( $cat->term_id, $selected_cats ) ? 'selected="selected"' : '' ) . 
                             ' data-children=\'' . $children_json . '\'>' . 
                             $indent . $prefix . esc_html( $cat->name ) . 
                             '</option>';
                             
                        ms_render_hierarchical_options( $cat->term_id, $hierarchy, $level + 1, $selected_cats );
                    }
                }
                
                ms_render_hierarchical_options( 0, $hierarchy, 0, $selected_cats );
                
                echo '</select></p>';
                ?>
            </div>
        </div>
        <style>
            .show_if_package { display: none; }
            #membership_package_options .select2-container--default .select2-selection--multiple {
                max-height: 120px;
                overflow-y: auto !important;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>
        <?php
    }

    public function package_type_custom_js() {
        if ( 'product' != get_post_type() ) return;
        ?>
        <script type='text/javascript'>
            jQuery( function( $ ) {
                $( 'body' ).on( 'woocommerce-product-type-change', function( event, select_val ) {
                    if ( select_val == 'package' ) {
                        $( '.show_if_package' ).show();
                        $( '.show_if_simple' ).show(); // Keep price and other simple product fields
                        $( '.general_options' ).show();
                        $( '.general_tab' ).show();
                        $( '#general_product_data .pricing' ).show(); // Specifically ensure pricing is visible
                    } else {
                        $( '.show_if_package' ).hide();
                    }
                });
                $( 'select#product-type' ).trigger( 'change' );

                // Hierarchical Auto-select for Categories
                $( 'body' ).on( 'select2:select', '#package_categories', function( e ) {
                    var $el = $(this);
                    var selectedId = e.params.data.id;
                    var $option = $el.find('option[value="' + selectedId + '"]');
                    var children = $option.data('children');

                    if ( children && children.length > 0 ) {
                        var currentValues = $el.val() || [];
                        children.forEach(function(id) {
                            if ( currentValues.indexOf(id.toString()) === -1 ) {
                                currentValues.push(id.toString());
                            }
                        });
                        $el.val(currentValues).trigger('change');
                    }
                });

                $( 'body' ).on( 'select2:unselect', '#package_categories', function( e ) {
                    var $el = $(this);
                    var unselectedId = e.params.data.id;
                    var $option = $el.find('option[value="' + unselectedId + '"]');
                    var children = $option.data('children');

                    if ( children && children.length > 0 ) {
                        var currentValues = $el.val() || [];
                        children.forEach(function(id) {
                            var index = currentValues.indexOf(id.toString());
                            if ( index !== -1 ) {
                                currentValues.splice(index, 1);
                            }
                        });
                        $el.val(currentValues).trigger('change');
                    }
                });
            });
        </script>
        <?php
    }

    public function save_package_options_fields( $post_id ) {
        // Security check: Check nonce
        if ( ! isset( $_POST['ms_package_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ms_package_meta_nonce'], 'ms_save_package_meta' ) ) {
            return;
        }

        // Security check: Check user capabilities
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Only save if it's a package
        $product_type = empty( $_POST['product-type'] ) ? 'simple' : sanitize_title( stripslashes( $_POST['product-type'] ) );
        
        if ( $product_type !== 'package' ) {
            return;
        }

        update_post_meta( $post_id, 'is_package', '1' );
        
        if ( isset( $_POST['package_duration'] ) ) {
            update_post_meta( $post_id, 'package_duration', sanitize_text_field( $_POST['package_duration'] ) );
        }
        if ( isset( $_POST['package_limit_type'] ) ) {
            update_post_meta( $post_id, 'package_limit_type', sanitize_text_field( $_POST['package_limit_type'] ) );
        }
        if ( isset( $_POST['daily_limit_value'] ) ) {
            update_post_meta( $post_id, 'daily_limit_value', sanitize_text_field( $_POST['daily_limit_value'] ) );
        }
        if ( isset( $_POST['package_categories'] ) ) {
            update_post_meta( $post_id, 'package_categories', array_map( 'intval', $_POST['package_categories'] ) );
        } else {
            update_post_meta( $post_id, 'package_categories', [] );
        }
    }

    public function package_sold_individually( $return, $product ) {
        if ( $product->get_type() === 'package' ) {
            return true;
        }
        return $return;
    }

    public function hide_quantity_column_css() {
        if ( ( is_cart() || is_checkout() ) && ! is_admin() ) {
            $has_package = false;
            
            if ( WC()->cart ) {
                foreach ( WC()->cart->get_cart() as $cart_item ) {
                    if ( isset( $cart_item['data'] ) && $cart_item['data']->get_type() === 'package' ) {
                        $has_package = true;
                        break;
                    }
                }
            }
            if ( $has_package ) {
                echo '<style>
                    .product-quantity, 
                    .shop_table .product-quantity, 
                    table.cart th.product-quantity, 
                    table.cart td.product-quantity,
                    .woocommerce-checkout-review-order-table .product-quantity { 
                        display: none !important; 
                    }
                </style>';
            }
        }
    }

    /**
     * Auto-select "Membership Package" in the dropdown if we came from the "Add New Package" link.
     */
    public function auto_select_package_type_js() {
        global $pagenow, $post_type;
        if ( $pagenow === 'post-new.php' && $post_type === 'product' && isset( $_GET['ms_type'] ) && $_GET['ms_type'] === 'package' ) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Give WC a moment to initialize the product type selector
                    setTimeout(function() {
                        $('#product-type').val('package').change();
                        // Also ensure the "Package" checkbox/options are visible if needed
                        $('.is_package_field').show();
                    }, 100);
                });
            </script>
            <?php
        }
    }

    /**
     * Clear cart if a package is being added, ensuring only one product exists.
     */
    public function clear_cart_on_package_add( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        $product = wc_get_product( $product_id );
        if ( $product && $product->get_type() === 'package' ) {
            foreach ( WC()->cart->get_cart() as $key => $item ) {
                if ( $key !== $cart_item_key ) {
                    WC()->cart->remove_cart_item( $key );
                }
            }
        }
    }

    public function package_is_purchasable( $purchasable, $product ) {
        if ( $product->get_type() === 'package' ) {
            return ( $product->get_price() !== '' );
        }
        return $purchasable;
    }

    /**
     * Force quantity to 1 for all packages during checkout/cart calculations.
     */
    public function force_package_quantity_to_one( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['data'] ) && $cart_item['data']->get_type() === 'package' ) {
                if ( $cart_item['quantity'] > 1 ) {
                    $cart->set_quantity( $cart_item_key, 1 );
                }
            }
        }
    }

    /**
     * Rename "Product" header to "Package" for membership orders.
     */
    public function change_product_label_to_package( $translated_text, $text, $domain ) {
        if ( ! is_admin() && $domain === 'woocommerce' && $text === 'Product' ) {
            if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
                $has_package = false;
                if ( function_exists( 'WC' ) && WC()->cart ) {
                    foreach ( WC()->cart->get_cart() as $cart_item ) {
                        if ( isset( $cart_item['data'] ) && $cart_item['data']->get_type() === 'package' ) {
                            $has_package = true;
                            break;
                        }
                    }
                }

                if ( $has_package ) {
                    return __( 'Package', 'membership-system' );
                }
            }
        }
        return $translated_text;
    }

    public function package_product_class( $classname, $product_type ) {
        if ( $product_type === 'package' ) {
            return 'WC_Product_Package';
        }
        return $classname;
    }
}
/**
 * Define the product class outside of the main class to avoid nesting errors.
 * We use the 'init' hook to ensure WooCommerce core classes like WC_Product_Simple are already loaded.
 */
function ms_register_package_product_type_class() {
    if ( class_exists( 'WC_Product_Simple' ) && ! class_exists( 'WC_Product_Package' ) ) {
        class WC_Product_Package extends WC_Product_Simple {
            public function get_type() {
                return 'package';
            }
        }
    }
}
add_action( 'init', 'ms_register_package_product_type_class' );
