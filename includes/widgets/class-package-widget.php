<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class Membership_Package_Widget extends Widget_Base {

    public function get_name() {
        return 'membership_packages';
    }

    public function get_title() {
        return __( 'Membership Packages', 'membership-system' );
    }

    public function get_icon() {
        return 'eicon-price-table';
    }

    public function get_categories() {
        return [ 'membership-system' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Content', 'membership-system' ),
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __( 'Columns', 'membership-system' ),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'membership-system' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_color',
            [
                'label' => __( 'Primary Color', 'membership-system' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#3498db',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Query Package Products
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'package',
                ],
            ],
        ];

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            echo '<p>No membership packages found.</p>';
            return;
        }

        $user_id = get_current_user_id();
        $current_sub = is_user_logged_in() ? ms_get_cached_subscription( $user_id ) : null;
        $active_package_id = $current_sub ? $current_sub->package_id : 0;

        $grid_style = "display: grid; grid-template-columns: repeat({$settings['columns']}, 1fr); gap: 20px; padding: 20px;";
        ?>
        <div class="membership-packages-grid" style="<?php echo $grid_style; ?>">
            <?php
            while ( $query->have_posts() ) : $query->the_post();
                global $product;
                $product_id = get_the_ID();
                $product = wc_get_product( $product_id );
                
                $is_current = ( (int)$active_package_id === (int)$product_id );
                $card_class = $is_current ? 'ms-package-card is-active-plan' : 'ms-package-card';
                
                // Button Text Logic based on subscription status
                if ( $is_current ) {
                    $btn_text = __( 'Your Plan', 'membership-system' );
                } elseif ( $active_package_id > 0 ) {
                    $btn_text = __( 'Upgrade', 'membership-system' );
                } else {
                    $btn_text = __( 'Get Started', 'membership-system' );
                }

                $price = $product->get_price_html();
                $duration = get_post_meta( $product_id, 'package_duration', true );
                $limit = get_post_meta( $product_id, 'daily_limit_value', true );
                $limit_type = get_post_meta( $product_id, 'package_limit_type', true );
                
                $limit_text = ($limit_type === 'unlimited' || empty($limit)) ? 'Unlimited Downloads' : $limit . ' Downloads/Day';
                $duration_text = ucfirst($duration);
                ?>
                <div class="<?php echo $card_class; ?>">
                    <?php if ( $is_current ) : ?>
                        <div class="ms-plan-badge"><?php _e( 'Your Plan', 'membership-system' ); ?></div>
                    <?php endif; ?>
                    <div class="ms-package-header">
                        <h3><?php the_title(); ?></h3>
                        <div class="ms-package-price"><?php echo $price; ?></div>
                        <span class="ms-package-duration"><?php echo $duration_text; ?></span>
                    </div>
                    <div class="ms-package-features">
                        <ul>
                            <li><i class="eicon-check-circle"></i> <?php echo $limit_text; ?></li>
                            <?php
                            $features_text = $product->get_short_description() ?: $product->get_description();
                            if ( ! empty( $features_text ) ) {
                                $features = explode( "\n", str_replace( "\r", "", strip_tags( $features_text ) ) );
                                foreach ( $features as $feature ) {
                                    $feature = trim( $feature );
                                    if ( ! empty( $feature ) ) {
                                        echo '<li><i class="eicon-check-circle"></i> ' . esc_html( $feature ) . '</li>';
                                    }
                                }
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="ms-package-footer">
                        <?php 
                        $checkout_url = add_query_arg( 'add-to-cart', $product_id, wc_get_checkout_url() );
                        ?>
                        <a href="<?php echo $is_current ? '#' : esc_url( $checkout_url ); ?>" class="ms-buy-btn <?php echo $is_current ? 'ms-btn-disabled' : ''; ?>">
                            <?php echo $btn_text; ?>
                        </a>
                    </div>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <style>
            .ms-package-card {
                background: #fff;
                border-radius: 15px;
                padding: 40px 30px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0,0,0,0.05);
                transition: all 0.3s ease;
                border: 1px solid #eee;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                position: relative;
                overflow: hidden;
            }
            .is-active-plan {
                border: 2px solid <?php echo $settings['primary_color']; ?>;
                transform: scale(1.03);
                z-index: 1;
            }
            .ms-plan-badge {
                position: absolute;
                top: 20px;
                right: -35px;
                background: <?php echo $settings['primary_color']; ?>;
                color: #fff;
                padding: 5px 40px;
                transform: rotate(45deg);
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
            }
            .ms-package-card:hover:not(.is-active-plan) {
                transform: translateY(-10px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border-color: <?php echo $settings['primary_color']; ?>;
            }
            .ms-package-header h3 {
                margin: 0 0 15px;
                font-size: 24px;
                color: #333;
            }
            .ms-package-price {
                font-size: 32px;
                font-weight: bold;
                color: <?php echo $settings['primary_color']; ?>;
                line-height: 1;
                margin-bottom: 5px;
            }
            .ms-package-duration {
                font-size: 14px;
                color: #888;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .ms-package-features {
                margin: 30px 0;
                text-align: center;
            }
            .ms-package-features ul {
                list-style: none;
                padding: 0;
                margin: 0;
                display: inline-block;
                text-align: left;
            }
            .ms-package-features li {
                margin-bottom: 12px;
                color: #666;
                display: flex;
                align-items: flex-start;
                justify-content: flex-start;
                gap: 10px;
                font-size: 15px;
            }
            .ms-package-features i {
                color: <?php echo $settings['primary_color']; ?>;
                flex-shrink: 0;
                margin-top: 3px;
            }
            .ms-buy-btn {
                display: inline-block;
                padding: 12px 30px;
                background-color: <?php echo $settings['primary_color']; ?>;
                color: #fff !important;
                border-radius: 30px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s;
                width: 100%;
            }
            .ms-btn-disabled {
                background-color: #ecf0f1;
                color: #95a5a6 !important;
                cursor: default;
                pointer-events: none;
            }
            .ms-buy-btn:hover:not(.ms-btn-disabled) {
                opacity: 0.9;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            @media (max-width: 768px) {
                .membership-packages-grid {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
        <?php
    }
}
