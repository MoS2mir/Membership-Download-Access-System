<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Membership_Request_Update_Widget extends Widget_Base {

    public function get_name() {
        return 'membership_request_update_button';
    }

    public function get_title() {
        return __( 'Request Update Button', 'membership-system' );
    }

    public function get_icon() {
        return 'eicon-envelope';
    }

    public function get_categories() {
        return [ 'membership-system' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'membership-system' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __( 'Button Text', 'membership-system' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Request for Update', 'membership-system' ),
                'placeholder' => __( 'Type your button text here', 'membership-system' ),
            ]
        );

        $this->add_responsive_control(
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
                    'justify' => [
                        'title' => __( 'Justified', 'membership-system' ),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => '',
                'selectors' => [
                    '{{WRAPPER}} .ms-request-update-widget-wrapper' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        global $product;

        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        if ( $product->get_type() === 'package' ) {
            return; // Don't show for packages
        }

        $settings = $this->get_settings_for_display();
        
        echo '<div class="ms-request-update-widget-wrapper">';
        
        if ( ! is_user_logged_in() ) {
            echo '<p><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="button">' . esc_html( $settings['button_text'] ) . '</a></p>';
        } else {
            echo '<button type="button" class="ms-request-update-button" data-product_id="' . esc_attr( $product->get_id() ) . '" data-product_name="' . esc_attr( $product->get_name() ) . '">' . esc_html( $settings['button_text'] ) . '</button>';
        }

        echo '</div>';
    }
}
