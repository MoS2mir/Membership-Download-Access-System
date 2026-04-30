<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_Elementor_Integration {
    public function __construct() {
        // Register Widgets
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        // Register Category
        add_action( 'elementor/elements/categories_registered', [ $this, 'add_elementor_category' ] );
    }

    public function add_elementor_category( $elements_manager ) {
        $elements_manager->add_category(
            'membership-system',
            [
                'title' => __( 'Membership System', 'membership-system' ),
                'icon'  => 'fa fa-plug',
            ]
        );
    }

    public function register_widgets( $widgets_manager ) {
        require_once MEMBERSHIP_SYSTEM_DIR . 'includes/widgets/class-package-widget.php';
        require_once MEMBERSHIP_SYSTEM_DIR . 'includes/widgets/class-follow-button-widget.php';
        require_once MEMBERSHIP_SYSTEM_DIR . 'includes/widgets/class-request-update-widget.php';
        
        $widgets_manager->register( new Membership_Package_Widget() );
        $widgets_manager->register( new Membership_Follow_Button_Widget() );
        $widgets_manager->register( new Membership_Request_Update_Widget() );
    }
}
