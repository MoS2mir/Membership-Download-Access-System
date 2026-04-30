<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Product_Updates {
    public function __construct() {
        // Add Version Field to Product Data
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_product_version_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_version_field' ] );
        
        // Add Follow Button to Single Product Page
        add_action( 'woocommerce_single_product_summary', [ $this, 'add_follow_button' ], 35 );
        
        // Handle Follow AJAX
        add_action( 'wp_ajax_ms_follow_product', [ $this, 'handle_follow_product' ] );
        add_action( 'wp_ajax_nopriv_ms_follow_product', [ $this, 'handle_follow_product_nopriv' ] );
        
        // Enqueue Script for Follow Button
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        // Request Update Feature
        add_action( 'wp_footer', [ $this, 'add_request_update_modal' ] );
        add_action( 'wp_ajax_ms_request_update', [ $this, 'handle_request_update' ] );
        
        // Track Version Changes for Email Notification
        add_action( 'updated_post_meta', [ $this, 'check_version_update' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'check_version_update' ], 10, 4 );

        // Shortcode to display product version
        add_shortcode( 'ms_product_version', [ $this, 'render_product_version_shortcode' ] );
    }

    public function add_product_version_field() {
        global $product_object;
        // Don't show for packages
        if ( ! $product_object || $product_object->get_type() === 'package' ) {
            return;
        }

        echo '<div class="options_group show_if_simple show_if_external show_if_variable">';
        
        woocommerce_wp_text_input( [
            'id'          => 'ms_product_version',
            'label'       => __( 'Product Version', 'membership-system' ),
            'placeholder' => 'e.g. 1.0.0',
            'desc_tip'    => true,
            'description' => __( 'Enter the current version of the product. This field is required.', 'membership-system' ),
            'custom_attributes' => [
                'required' => 'required'
            ]
        ] );
        
        echo '</div>';
    }

    public function save_product_version_field( $post_id ) {
        if ( isset( $_POST['ms_product_version'] ) ) {
            update_post_meta( $post_id, 'ms_product_version', sanitize_text_field( $_POST['ms_product_version'] ) );
        }
    }

    public function add_follow_button() {
        global $product;
        // Don't show for packages
        if ( ! $product || $product->get_type() === 'package' ) {
            return;
        }

        $version = get_post_meta( $product->get_id(), 'ms_product_version', true );
        if ( $version ) {
            echo '<p class="ms-version-display"><strong>' . __( 'Current Version:', 'membership-system' ) . '</strong> ' . esc_html( $version ) . '</p>';
        }

        if ( ! is_user_logged_in() ) {
            echo '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="button">' . __( 'Follow for Update', 'membership-system' ) . '</a></p>';
            return;
        }

        $user_id = get_current_user_id();
        $followed_products = get_user_meta( $user_id, 'ms_followed_products', true );
        if ( ! is_array( $followed_products ) ) {
            $followed_products = [];
        }

        $is_following = in_array( $product->get_id(), $followed_products );
        $button_text = $is_following ? __( 'Following for Updates', 'membership-system' ) : __( 'Follow for Update', 'membership-system' );
        $button_class = $is_following ? 'ms-follow-button following' : 'ms-follow-button';
        
        echo '<button type="button" class="' . esc_attr( $button_class ) . '" data-product_id="' . esc_attr( $product->get_id() ) . '">' . esc_html( $button_text ) . '</button>';
        echo '<div class="ms-follow-message" style="display:none; margin-top: 10px;"></div>';
    }

    public function enqueue_scripts() {
        // Use event delegation in case the button is loaded dynamically by Elementor
        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                $(document).on("click", ".ms-follow-button", function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var productId = button.data("product_id");
                    var msg = button.siblings(".ms-follow-message");
                    
                    button.prop("disabled", true);
                    
                    $.ajax({
                        url: "' . admin_url( 'admin-ajax.php' ) . '",
                        type: "POST",
                        data: {
                            action: "ms_follow_product",
                            product_id: productId,
                            nonce: "' . wp_create_nonce( 'ms-follow-nonce' ) . '"
                        },
                        success: function(response) {
                            button.prop("disabled", false);
                            if (response.success) {
                                if (response.data.is_following) {
                                    button.addClass("following").text("' . __( 'Following for Updates', 'membership-system' ) . '");
                                } else {
                                    button.removeClass("following").text("' . __( 'Follow for Update', 'membership-system' ) . '");
                                }
                            } else {
                                msg.html("<span style=\'color:red;\'>" + response.data + "</span>").show().delay(3000).fadeOut();
                            }
                        }
                    });
                });
            });
        ' );
        
        wp_register_style( 'ms-follow-style', false );
        wp_enqueue_style( 'ms-follow-style' );
        wp_add_inline_style( 'ms-follow-style', '
            .ms-follow-button { background-color: #f1f1f1; border: 1px solid #ccc; padding: 10px 15px; cursor: pointer; border-radius: 4px; display: inline-block; margin-bottom: 20px;}
            .ms-follow-button.following { background-color: #2ecc71; color: #fff; border-color: #27ae60; }
            .ms-version-display { font-size: 14px; margin-bottom: 10px; }
        ' );
    }

    public function handle_follow_product() {
        check_ajax_referer( 'ms-follow-nonce', 'nonce' );
        
        $user_id = get_current_user_id();
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        
        if ( ! $product_id || ! $user_id ) {
            wp_send_json_error( __( 'Invalid request.', 'membership-system' ) );
        }

        $followed_products = get_user_meta( $user_id, 'ms_followed_products', true );
        if ( ! is_array( $followed_products ) ) {
            $followed_products = [];
        }

        $is_following = false;
        $index = array_search( $product_id, $followed_products );
        
        if ( $index !== false ) {
            // Unfollow
            unset( $followed_products[$index] );
            $followed_products = array_values( $followed_products );
            delete_user_meta( $user_id, 'ms_followed_version_' . $product_id );
        } else {
            // Follow
            $followed_products[] = $product_id;
            $is_following = true;
            $current_version = get_post_meta( $product_id, 'ms_product_version', true );
            if ( ! $current_version ) {
                $current_version = 'N/A';
            }
            update_user_meta( $user_id, 'ms_followed_version_' . $product_id, $current_version );
        }

        update_user_meta( $user_id, 'ms_followed_products', $followed_products );

        wp_send_json_success( [ 'is_following' => $is_following ] );
    }

    public function handle_follow_product_nopriv() {
        wp_send_json_error( __( 'Please login to follow products.', 'membership-system' ) );
    }

    public function add_request_update_modal() {
        if ( ! is_user_logged_in() ) return;
        ?>
        <div id="ms-request-update-modal" class="ms-modal" style="display: none;">
            <div class="ms-modal-content">
                <span class="ms-modal-close">&times;</span>
                <h3><?php _e( 'Fast update', 'membership-system' ); ?></h3>
                
                <div class="ms-modal-notice">
                    <span class="dashicons dashicons-warning" style="font-size: 30px; width: 30px; height: 30px; color: #4caf50; display: inline-block;"></span>
                    <div>
                    <?php _e( 'Because you are a member, we prioritize updates as quickly as possible. Please note that we do not reply to update requests. Updates are usually completed within 6–48 hours. You can click “Follow Update” to receive an automatic email notification once the update is available. For more details about updates and our policy, please review our Terms and Conditions. When submitting a request, make sure to include the new version details.', 'membership-system' ); ?>
                    </div>
                </div>

                <form id="ms-request-update-form">
                    <input type="text" id="ms-request-product-name" readonly style="background:#f9f9f9;">
                    <input type="hidden" id="ms-request-product-id" name="product_id">
                    
                    <input type="email" id="ms-request-email" name="email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required placeholder="Email Address">
                    
                    <input type="text" id="ms-request-version" name="version" placeholder="Request Version eg: 5.23">
                    
                    <button type="submit" class="ms-submit-btn"><?php _e( 'SUBMIT', 'membership-system' ); ?></button>
                    <div id="ms-request-msg" style="display:none; margin-top:10px; font-weight:bold; text-align:center;"></div>
                </form>
            </div>
        </div>
        <style>
            .ms-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); font-family: sans-serif; }
            .ms-modal-content { background-color: #fff; margin: 10% auto; padding: 20px 30px 30px; border: 1px solid #888; width: 90%; max-width: 500px; position: relative; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
            .ms-modal-close { position: absolute; right: 15px; top: 10px; color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
            .ms-modal-close:hover { color: #000; text-decoration: none; }
            .ms-modal h3 { margin-top: 0; font-size: 22px; font-weight: normal; margin-bottom: 20px; color: #333; }
            .ms-modal-notice { background-color: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; font-size: 13px; line-height: 1.5; }
            .ms-modal input[type="text"], .ms-modal input[type="email"] { width: 100%; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
            .ms-submit-btn { width: 100%; background-color: #27672a; color: white; padding: 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; text-transform: uppercase; transition: background 0.3s; }
            .ms-submit-btn:hover { background-color: #1b5e20; }
        </style>
        <script>
            jQuery(document).ready(function($) {
                $(document).on('click', '.ms-request-update-button', function(e) {
                    e.preventDefault();
                    var productId = $(this).data('product_id');
                    var productName = $(this).data('product_name');
                    $('#ms-request-product-id').val(productId);
                    $('#ms-request-product-name').val(productName);
                    $('#ms-request-version').val('');
                    $('#ms-request-msg').hide();
                    $('#ms-request-update-modal').fadeIn('fast');
                });

                $('.ms-modal-close').on('click', function() {
                    $('#ms-request-update-modal').fadeOut('fast');
                });

                $(window).on('click', function(e) {
                    if ($(e.target).is('#ms-request-update-modal')) {
                        $('#ms-request-update-modal').fadeOut('fast');
                    }
                });

                $('#ms-request-update-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    var btn = form.find('.ms-submit-btn');
                    var msg = $('#ms-request-msg');
                    
                    btn.prop('disabled', true).text('<?php _e("Submitting...", "membership-system"); ?>');
                    
                    $.ajax({
                        url: '<?php echo admin_url( "admin-ajax.php" ); ?>',
                        type: 'POST',
                        data: form.serialize() + '&action=ms_request_update&nonce=<?php echo wp_create_nonce("ms-request-update-nonce"); ?>',
                        success: function(response) {
                            if (response.success) {
                                msg.css('color', 'green').html(response.data).fadeIn();
                                setTimeout(function(){
                                    $('#ms-request-update-modal').fadeOut('fast');
                                    btn.prop('disabled', false).text('<?php _e("SUBMIT", "membership-system"); ?>');
                                }, 2000);
                            } else {
                                msg.css('color', 'red').html(response.data).fadeIn();
                                btn.prop('disabled', false).text('<?php _e("SUBMIT", "membership-system"); ?>');
                            }
                        },
                        error: function() {
                            msg.css('color', 'red').html('An error occurred. Please try again.').fadeIn();
                            btn.prop('disabled', false).text('<?php _e("SUBMIT", "membership-system"); ?>');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function handle_request_update() {
        check_ajax_referer( 'ms-request-update-nonce', 'nonce' );
        
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
        
        if ( ! $product_id || ! is_email( $email ) ) {
            wp_send_json_error( __( 'Invalid request or email.', 'membership-system' ) );
        }
        
        $requests = get_post_meta( $product_id, '_ms_update_requests', true );
        if ( ! is_array( $requests ) ) {
            $requests = [];
        }
        
        // Add new request
        $requests[] = [
            'email'   => $email,
            'version' => $version,
            'date'    => time(),
            'user_id' => get_current_user_id()
        ];
        
        update_post_meta( $product_id, '_ms_update_requests', $requests );
        
        wp_send_json_success( __( 'Your request has been submitted successfully!', 'membership-system' ) );
    }

    public function check_version_update( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( $meta_key !== 'ms_product_version' ) {
            return;
        }
        
        $requests = get_post_meta( $post_id, '_ms_update_requests', true );
        if ( empty( $requests ) || ! is_array( $requests ) ) {
            return;
        }
        
        $new_version = $meta_value;
        $remaining_requests = [];
        
        $product = wc_get_product( $post_id );
        if ( ! $product ) return;
        $product_name = $product->get_name();
        
        foreach ( $requests as $request ) {
            $req_email = $request['email'];
            $req_ver = trim($request['version']);
            
            $should_notify = false;
            if ( empty( $req_ver ) ) {
                $should_notify = true;
            } elseif ( version_compare( $new_version, $req_ver, '>=' ) || strtolower($new_version) === strtolower($req_ver) ) {
                $should_notify = true;
            }
            
            if ( $should_notify ) {
                $subject = sprintf( __( 'Update Available: %s', 'membership-system' ), $product_name );
                $message = sprintf( __( 'Hello,%s%sThe product "%s" has just been updated to version %s.%s You can download it from our website.%s%sThank you!', 'membership-system' ), "\n\n", "\n", $product_name, $new_version, "\n", "\n\n", "\n" );
                
                wp_mail( $req_email, $subject, $message );
            } else {
                $remaining_requests[] = $request;
            }
        }
        
        update_post_meta( $post_id, '_ms_update_requests', $remaining_requests );
    }

    public function render_product_version_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id' => get_the_ID(),
        ], $atts, 'ms_product_version' );

        $product_id = intval( $atts['id'] );
        if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
            return '';
        }

        $version = get_post_meta( $product_id, 'ms_product_version', true );
        if ( ! $version ) {
            return '';
        }

        return '<p class="ms-version-display"><strong>' . __( 'Current Version:', 'membership-system' ) . '</strong> ' . esc_html( $version ) . '</p>';
    }
}
new Membership_Product_Updates();
