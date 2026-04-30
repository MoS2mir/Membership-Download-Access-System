<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;

// Get Membership Info
$subscriptions = class_exists('Membership_Subscription_Repository') ? Membership_Subscription_Repository::get_user_subscriptions( $user_id ) : [];
$active_sub    = null;

if ( ! empty( $subscriptions ) ) {
    foreach ( $subscriptions as $sub ) {
        if ( $sub->status === 'active' ) {
            $active_sub = $sub;
            break;
        }
    }
}

// Stats
$downloads_count = class_exists('Membership_Subscription_Repository') ? count(Membership_Subscription_Repository::get_user_downloads( $user_id )) : 0;
$followed_array  = get_user_meta( $user_id, 'ms_followed_products', true );
$followed_count  = ( is_array( $followed_array ) ) ? count( $followed_array ) : 0;

$pack_name = __( 'Free / None', 'membership-system' );
$remaining = 0;
if ( $active_sub ) {
    $package   = wc_get_product( $active_sub->package_id );
    if($package) $pack_name = $package->get_name();
    $remaining = ( $active_sub->limit_type === 'unlimited' ) ? 'Unlimited' : ( $active_sub->daily_limit - $active_sub->daily_download_count );
}

?>

<div class="custom-ms-dashboard-wrapper">
    <div class="woocommerce-notices-wrapper">
        <?php wc_print_notices(); ?>
    </div>

    <!-- Welcome Header Section -->
    <div class="ms-welcome-banner">
        <div class="ms-user-avatar">
            <?php echo get_avatar($current_user->user_email, 80); ?>
        </div>
        <div class="ms-user-greeting">
            <h2><?php printf( esc_html__( 'Hello, %s!', 'membership-system' ), '<strong>' . esc_html( $current_user->display_name ) . '</strong>' ); ?></h2>
            <p><?php
                printf(
                    __( 'Not %1$s? <a href="%2$s">Log out</a>', 'membership-system' ),
                    esc_html( $current_user->display_name ),
                    esc_url( wc_logout_url() )
                );
            ?></p>
            <div class="alg-wc-ev-verification-info" style="margin-top:10px;">
                <span class="alg-wc-ev-custom-msg" style="display:inline-block;background:#e8f8f0;color:#2ecc71;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:bold;">
                    <i class="dashicons dashicons-yes-alt"></i> Verified Account
                </span>
            </div>
        </div>
    </div>

    <!-- Plugin Features Counters / Stats -->
    <div class="ms-dashboard-stats-grid">
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <div class="ms-stat-details">
                <h4><?php echo esc_html( $pack_name ); ?></h4>
                <span><?php _e( 'Active Plan', 'membership-system' ); ?></span>
            </div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </div>
            <div class="ms-stat-details">
                <h4><?php echo esc_html( $remaining ); ?></h4>
                <span><?php _e( 'Downloads Left Today', 'membership-system' ); ?></span>
            </div>
        </div>

        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.518 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.973 2.888a1 1 0 00-.364 1.118l1.518 4.674c.3.921-.755 1.688-1.539 1.118l-3.973-2.888a1 1 0 00-1.176 0l-3.973 2.888c-.784.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.364-1.118L2.05 10.1c-.783-.57-.38-1.81.588-1.81h4.915a1 1 0 00.951-.69l1.518-4.674z"/></svg>
            </div>
            <div class="ms-stat-details">
                <h4><?php echo esc_html( $followed_count ); ?></h4>
                <span><?php _e( 'Followed Products', 'membership-system' ); ?></span>
            </div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f39c12;">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="ms-stat-details">
                <h4 style="font-size: 16px;"><?php esc_html_e( 'Lifetime License', 'membership-system' ); ?></h4>
                <span><?php esc_html_e( 'Available', 'membership-system' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Grid (WoodMart Style + Woo) -->
    <h3 class="ms-section-title"><?php _e( 'Account Quick Links', 'membership-system' ); ?></h3>
    <ul class="wd-my-account-links custom-grid-nav wd-nav-my-acc wd-nav wd-icon-top wd-grid-g">
        <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
            <?php if ( $endpoint === 'dashboard' ) continue; ?>
            <li class="wd-my-acc-<?php echo esc_attr( $endpoint ); ?>">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" class="ms-dashboard-link-card">
                    <span class="wd-nav-icon ms-nav-icon-wrapper">
                        <!-- Example mappings based on Woodmart class or custom icon logic -->
                        <?php if ( $endpoint === 'orders' ) : ?> <i class="dashicons dashicons-cart"></i>
                        <?php elseif ( $endpoint === 'downloads' ) : ?> <i class="dashicons dashicons-download"></i>
                        <?php elseif ( $endpoint === 'edit-address' ) : ?> <i class="dashicons dashicons-location"></i>
                        <?php elseif ( $endpoint === 'payment-methods' ) : ?> <i class="dashicons dashicons-money-alt"></i>
                        <?php elseif ( $endpoint === 'edit-account' ) : ?> <i class="dashicons dashicons-admin-users"></i>
                        <?php elseif ( $endpoint === 'customer-logout' ) : ?> <i class="dashicons dashicons-arrow-right-alt2"></i>
                        <?php elseif ( $endpoint === 'membership-access' ) : ?> <i class="dashicons dashicons-tickets-alt"></i>
                        <?php elseif ( $endpoint === 'membership-downloads' ) : ?> <i class="dashicons dashicons-cloud"></i>
                        <?php elseif ( $endpoint === 'membership-followed-products' ) : ?> <i class="dashicons dashicons-star-half"></i>
                        <?php else : ?> <i class="dashicons dashicons-admin-generic"></i>
                        <?php endif; ?>
                    </span>
                    <span class="nav-link-text">
                        <?php echo esc_html( $label ); ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Complete Custom Support Block -->
    <div class="ms-support-block">
        <div class="ms-support-header">
            <h3 style="color:#fff;">Customer Support</h3>
            <p>We're here to help - choose the most suitable option below</p>
        </div>
        <div class="ms-support-body">
            <div class="ms-support-left">
                <h4 class="ms-support-subtitle">CONTACT INFORMATION</h4>
                <div class="ms-support-item">
                    <div class="ms-support-icon" style="color: #636e72;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="ms-support-info">
                        <h5>Email Support</h5>
                        <strong>support@spodly.com</strong>
                    </div>
                </div>
                <div class="ms-support-item">
                    <div class="ms-support-icon" style="color: #25d366;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.449A11.96 11.96 0 0 0 12 0c-6.627 0-12 5.373-12 12 0 2.124.553 4.152 1.6 5.96L0 24l6.196-1.624a11.916 11.916 0 0 0 5.804 1.503h.005c6.623 0 12-5.373 12-12a11.972 11.972 0 0 0-3.485-8.43zm-8.52 18.529h-.004a9.92 9.92 0 0 1-5.06-1.378l-.363-.215-3.766.987.999-3.67-.236-.376a9.923 9.923 0 0 1-1.52-5.334c0-5.485 4.465-9.95 9.95-9.95 2.658 0 5.16 1.036 7.039 2.917 1.88 1.88 2.916 4.382 2.916 7.042.001 5.484-4.464 9.947-9.947 9.947h-.004zm5.457-7.447c-.299-.15-1.77-.874-2.046-.974-.275-.1-.476-.15-.675.15-.2.3-.775.975-.951 1.175-.175.2-.35.225-.65.075-.299-.15-1.263-.466-2.404-1.485-.888-.795-1.488-1.775-1.663-2.075-.175-.3-.018-.462.132-.612.135-.135.299-.35.45-.525.15-.175.2-.299.299-.5s.048-.375-.025-.525c-.075-.15-.675-1.625-.925-2.225-.243-.585-.49-.505-.675-.515-.175-.008-.376-.008-.576-.008s-.525.075-.8.375c-.275.3-1.05 1.025-1.05 2.5s1.074 2.898 1.224 3.098c.15.2 2.112 3.224 5.114 4.517.714.308 1.272.492 1.706.63.716.227 1.368.195 1.883.118.575-.086 1.77-.723 2.02-1.423.25-.7.25-1.3.175-1.423-.075-.123-.275-.198-.575-.348z"/></svg>
                    </div>
                    <div class="ms-support-info">
                        <h5>WhatsApp</h5>
                        <strong>WA: +90 536 650 5151</strong>
                    </div>
                </div>
                <div class="ms-support-item">
                    <div class="ms-support-icon" style="color: #636e72;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="ms-support-info">
                        <h5>Address </h5>
                        <strong>12EC1V 2NX, London, UK</strong>
                    </div>
                </div>   
            </div>
            
            <div class="ms-support-right">
                <h4 class="ms-support-subtitle">CONTACT OPTIONS</h4>
                
                <a href="https://wa.me/905366505151" class="ms-support-btn btn-whatsapp" target="_blank">
                    <div class="btn-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.449A11.96 11.96 0 0 0 12 0c-6.627 0-12 5.373-12 12 0 2.124.553 4.152 1.6 5.96L0 24l6.196-1.624a11.916 11.916 0 0 0 5.804 1.503h.005c6.623 0 12-5.373 12-12a11.972 11.972 0 0 0-3.485-8.43zm-8.52 18.529h-.004a9.92 9.92 0 0 1-5.06-1.378l-.363-.215-3.766.987.999-3.67-.236-.376a9.923 9.923 0 0 1-1.52-5.334c0-5.485 4.465-9.95 9.95-9.95 2.658 0 5.16 1.036 7.039 2.917 1.88 1.88 2.916 4.382 2.916 7.042.001 5.484-4.464 9.947-9.947 9.947h-.004zm5.457-7.447c-.299-.15-1.77-.874-2.046-.974-.275-.1-.476-.15-.675.15-.2.3-.775.975-.951 1.175-.175.2-.35.225-.65.075-.299-.15-1.263-.466-2.404-1.485-.888-.795-1.488-1.775-1.663-2.075-.175-.3-.018-.462.132-.612.135-.135.299-.35.45-.525.15-.175.2-.299.299-.5s.048-.375-.025-.525c-.075-.15-.675-1.625-.925-2.225-.243-.585-.49-.505-.675-.515-.175-.008-.376-.008-.576-.008s-.525.075-.8.375c-.275.3-1.05 1.025-1.05 2.5s1.074 2.898 1.224 3.098c.15.2 2.112 3.224 5.114 4.517.714.308 1.272.492 1.706.63.716.227 1.368.195 1.883.118.575-.086 1.77-.723 2.02-1.423.25-.7.25-1.3.175-1.423-.075-.123-.275-.198-.575-.348z"/></svg>
                    </div>
                    <div class="btn-text">
                        <strong>Chat on WhatsApp</strong>
                        <span>Quick and convenient communication</span>
                    </div>
                    <svg class="btn-arrow" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                
                <a href="mailto:support@spodly.com" class="ms-support-btn btn-livechat">
                    <div class="btn-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="btn-text">
                        <strong>Live Chat</strong>
                        <span>Real-time assistance during hours</span>
                    </div>
                    <svg class="btn-arrow" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                
                <a href="#" class="ms-support-btn btn-ticket">
                    <div class="btn-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <div class="btn-text">
                        <strong>Submit a Support Ticket</strong>
                        <span>Recommended for all issues</span>
                    </div>
                    <svg class="btn-arrow" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>
        <div class="ms-support-footer">
            If you do not receive a response on call, please do not be concerned - you may reach us via WhatsApp, Live Chat, or by submitting a support ticket. Kindly leave a detailed message and our team will follow up as soon as possible.
        </div>
    </div>
</div>

<style>
/* Custom Dashboard Styling */
.custom-ms-dashboard-wrapper {
    font-family: 'Inter', Roboto, sans-serif;
    color: #333;
}

.ms-welcome-banner {
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #eef1f6 100%);
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}

.ms-user-avatar img {
    border-radius: 50%;
    margin-right: 25px;
    border: 4px solid #fff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.ms-user-greeting h2 {
    margin: 0 0 5px;
    font-size: 26px;
    font-weight: 700;
    color: #2c3e50;
}

.ms-user-greeting p {
    margin: 0;
    color: #7f8c8d;
    font-size: 14px;
}

.ms-dashboard-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 40px;
}

.ms-stat-card {
    background: #fff;
    padding: 15px 15px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.02);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.ms-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.ms-stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

.ms-stat-icon i {
    font-size: 22px;
}

.ms-stat-details h4 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    word-break: break-word;
}

.ms-stat-details span {
    font-size: 11px;
    color: #95a5a6;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.ms-section-title {
    font-size: 22px;
    margin-bottom: 25px;
    font-weight: 600;
    color: #2c3e50;
}

/* Enhancing Woodmart style grid */
ul.custom-grid-nav {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)) !important;
    gap: 15px !important;
    padding: 0 !important;
    list-style: none !important;
}

ul.custom-grid-nav li {
    margin: 0 !important;
    width: 100% !important;
}

.ms-dashboard-link-card {
    display: flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #fff !important;
    padding: 30px 15px !important;
    border-radius: 16px !important;
    border: 1px solid #f0f2f5 !important;
    transition: all 0.3s ease !important;
    text-align: center;
    height: 100%;
}

.ms-dashboard-link-card:hover {
    background: #3498db !important;
    color: #fff !important;
    border-color: #3498db !important;
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(52, 152, 219, 0.2);
}

.ms-dashboard-link-card:hover * {
    color: #fff !important;
}

.ms-nav-icon-wrapper {
    margin-bottom: 12px;
    font-size: 28px;
    color: #7f8c8d;
    transition: all 0.3s;
}

/* Support Block CSS */
.ms-support-block {
    margin-top: 50px;
    background: #fff;
    border: 1px solid #e1e8ed;
    border-radius: 12px;
    overflow: hidden;
}

.ms-support-header {
    background: #198754;
    color: #fff;
    padding: 20px 25px;
}
.ms-support-header h3 {
    margin: 0 0 5px;
    font-size: 18px;
    font-weight: 700;
}
.ms-support-header p {
    margin: 0;
    font-size: 13px;
    opacity: 0.9;
}

.ms-support-body {
    display: flex;
}

.ms-support-left, .ms-support-right {
    flex: 1;
    padding: 30px;
}

.ms-support-left {
    border-right: 1px solid #e1e8ed;
}

.ms-support-subtitle {
    color: #95a5a6;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 0 0 20px;
    font-weight: 600;
}

.ms-support-item {
    display: flex;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid #f0f2f5;
}

.ms-support-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.ms-support-icon {
    font-size: 24px;
    margin-right: 15px;
    width: 40px;
    text-align: center;
}

.ms-support-info h5 {
    margin: 0 0 5px;
    color: #2c3e50;
    font-size: 14px;
    font-weight: 700;
}

.ms-support-info strong {
    color: #198754; 
    font-size: 16px;
    font-weight: 700;
}

.ms-support-btn {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 10px;
    text-decoration: none;
    margin-bottom: 15px;
    transition: all 0.3s;
}

.btn-whatsapp { border: 1px solid #b7e4c7; background: #f0fdf4; }
.btn-livechat { border: 1px solid #cce5ff; background: #f1f8ff; }
.btn-ticket { border: 1px solid #e1e8ed; background: #f8f9fa; }

.ms-support-btn:last-child { margin-bottom: 0; }
.ms-support-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

.btn-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-right: 15px;
    color: #fff;
    font-size: 20px;
}

.btn-whatsapp .btn-icon { background: #25d366; }
.btn-livechat .btn-icon { background: #0066cc; }
.btn-ticket .btn-icon { background: #212529; }

.btn-text {
    flex: 1;
}

.btn-text strong { display: block; color: #2c3e50; font-size: 14px; margin-bottom: 3px; font-weight: 700; }
.btn-text span { display: block; color: #7f8c8d; font-size: 11px; line-height: 1.3;}

.btn-arrow { margin-left: auto; color: #95a5a6; }

.ms-support-footer {
    background: #f8f9fa;
    border-top: 1px solid #e1e8ed;
    padding: 20px 30px;
    font-size: 12px;
    color: #6c757d;
    line-height: 1.6;
}

@media(max-width: 1024px) {
    .ms-dashboard-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 768px) {
    .ms-welcome-banner {
        flex-direction: column;
        text-align: center;
    }
    .ms-user-avatar img {
        margin: 0 0 15px 0;
    }
    .ms-dashboard-stats-grid {
        grid-template-columns: 1fr;
    }
    .ms-support-body { 
        flex-direction: column; 
    }
    .ms-support-left { 
        border-right: none; 
        border-bottom: 1px solid #e1e8ed; 
    }
}

/* Make WoodMart sidebar sticky on desktop */
@media(min-width: 1025px) {
    .wd-my-account-sidebar {
        position: sticky !important;
        top: 120px !important; /* Offset for site header */
        align-self: flex-start !important;
        z-index: 10;
        transition: top 0.3s ease;
    }
}

/* Sidebar navigation two columns on mobile */
@media(max-width: 768px) {
    .woocommerce-MyAccount-navigation ul {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
        padding: 0 !important;
    }
    .woocommerce-MyAccount-navigation ul li {
        width: 100% !important;
        margin: 0 !important;
    }
    .woocommerce-MyAccount-navigation ul li a {
        display: flex !important;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 12px 10px !important;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 13px;
        border: 1px solid #e1e8ed;
        height: 100%;
        line-height: 1.2;
    }
    .woocommerce-MyAccount-navigation ul li.is-active a {
        background: #3498db !important;
        color: #fff !important;
        border-color: #3498db !important;
    }
}
</style>
