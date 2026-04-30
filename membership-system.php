<?php
/**
 * Plugin Name: Membership & Download Access System
 * Description: Advanced membership system with daily download limits for WooCommerce.
 * Version: 1.0.0
 * Author: mosamir
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MEMBERSHIP_SYSTEM_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMBERSHIP_SYSTEM_URL', plugin_dir_url( __FILE__ ) );
define( 'MEMBERSHIP_SYSTEM_VERSION', '1.0.0' );

// Require Helper Functions
require_once MEMBERSHIP_SYSTEM_DIR . 'helpers/functions.php';

// Require Core Architectures
require_once MEMBERSHIP_SYSTEM_DIR . 'database/schema.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-core.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-hooks.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-api.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-product-type.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-elementor-integration.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-admin-dashboard.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'includes/class-product-updates.php';

// Require Modules
require_once MEMBERSHIP_SYSTEM_DIR . 'modules/subscriptions/class-subscription-repository.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'modules/subscriptions/class-subscription-manager.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'modules/access/class-access-control.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'modules/limits/class-limit-manager.php';
require_once MEMBERSHIP_SYSTEM_DIR . 'modules/cron/class-cron-manager.php';

// Register Activation Hook
register_activation_hook( __FILE__, [ 'Membership_System_Schema', 'install' ] );

// Initialize Core
function run_membership_system() {
    $plugin = new Membership_System_Core();
    $plugin->run();
}
run_membership_system();

// Automatic DB Update Check
function ms_update_db_check() {
    $current_version = '1.1';
    if ( get_option( 'membership_db_version' ) !== $current_version ) {
        Membership_System_Schema::install();
        update_option( 'membership_db_version', $current_version );
    }
}
add_action( 'plugins_loaded', 'ms_update_db_check' );
