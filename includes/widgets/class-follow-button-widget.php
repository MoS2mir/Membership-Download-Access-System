<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Membership_Follow_Button_Widget extends Widget_Base {

    public function get_name() {
        return 'membership_follow_button';
    }

    public function get_title() {
        return __( 'Follow for Update', 'membership-system' );
    }

    public function get_icon() {
        return 'eicon-button';
    }

    public function get_categories() {
        return [ 'membership-system' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Content', 'membership-system' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_version',
            [
                'label' => __( 'Show Current Version', 'membership-system' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Show', 'membership-system' ),
                'label_off' => __( 'Hide', 'membership-system' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'membership-system' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'align',
            [
                'label' => __( 'Alignment', 'membership-system' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __( 'Left', 'membership-system' ),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __( 'Center', 'membership-system' ),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __( 'Right', 'membership-system' ),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .ms-follow-widget-wrapper' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        global $product;
        if ( ! $product ) {
            $product_id = get_the_ID();
            if ( $product_id && 'product' === get_post_type( $product_id ) ) {
                $product = wc_get_product( $product_id );
            }
        }

        // Don't show for packages or if product not found
        if ( ! $product || ! is_a( $product, 'WC_Product' ) || $product->get_type() === 'package' ) {
            // Note: If using elementor template preview, we might still want to show a dummy button
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="ms-follow-widget-wrapper">';
                echo '<button type="button" class="ms-follow-button">' . __( 'Follow for Update', 'membership-system' ) . '</button>';
                echo '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        
        echo '<div class="ms-follow-widget-wrapper">';

        $version = get_post_meta( $product->get_id(), 'ms_product_version', true );
        if ( $version && 'yes' === $settings['show_version'] ) {
            echo '<p class="ms-version-display"><strong>' . __( 'Current Version:', 'membership-system' ) . '</strong> ' . esc_html( $version ) . '</p>';
        }

        if ( ! is_user_logged_in() ) {
            echo '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="button">' . __( 'Follow for Update', 'membership-system' ) . '</a></p>';
            echo '</div>';
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
        
        echo '</div>';
    }
}
