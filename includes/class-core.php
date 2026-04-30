<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Membership_System_Core {
    public function __construct() {
        // Initialization can go here
    }

    public function run() {
        // Init Managers
        $this->init_modules();
    }

    private function init_modules() {
        new Membership_System_Hooks();
        new Membership_System_API();
        new Membership_Product_Package();
        new Membership_Subscription_Manager();
        new Membership_Access_Control();
        new Membership_Limit_Manager();
        new Membership_Cron_Manager();
        new Membership_Elementor_Integration();
    }
}
