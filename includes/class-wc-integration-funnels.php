<?php

namespace Svbk\WP\Plugins\WooCommerce\Funnels;

use WC_Integration;
use Svbk\WP\Helpers\Lists\Utils;
use Svbk\WP\Helpers;
use Wp_Query;


/**
 * WooCommerce Funnels Integration.
 *
 * @package  WC_Integration_Funnels
 * @category Integration
 * @author   Brando Meniconi
 */

if ( ! class_exists( __NAMESPACE__ . '\\WC_Integration_Funnels' ) ) :

	class WC_Integration_Funnels extends WC_Integration {

		public $api_key;

		public $logger;

		public $show_avatar = true;
		public $mycourses_menu_label;
		public $orders_menu_label;
		public $account_product_categories;
		public $dashboard_content_page;
		public $dashboard_intro_text;
		public $checkout_warranty_text;

		public $disable_woocommerce_styles;
		public $disable_sensei_styles;

		public $sensei;
		public $affiliate_wp;

		public $page_templates = array();

		public $debug = false;

		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			global $woocommerce;

			$this->id                 = 'silverback-funnels';
			$this->method_title       = __( 'Funnels', 'woocommerce-funnels' );
			$this->method_description = __( 'Changes the WooCommerce Purchase Flow to allow Funnels', 'woocommerce-funnels' );

			// Load the settings.
			$this->init_settings();

			// Define user set variables.
			$this->mycourses_menu_label       = $this->get_option( 'mycourses_menu_label' );
			$this->orders_menu_label          = $this->get_option( 'orders_menu_label' );
			$this->account_product_categories = $this->get_option( 'account_product_categories' );
			$this->dashboard_content_page     = $this->get_option( 'dashboard_content_page' );
			$this->dashboard_intro_text     = $this->get_option( 'dashboard_intro_text' );
			$this->disable_woocommerce_styles  = $this->get_option( 'disable_woocommerce_styles' );
			$this->checkout_warranty_text  = $this->get_option( 'checkout_warranty_text' );

			add_action( 'init', array( $this, 'init_form_fields' ), 30 );
			add_action( 'init', array( $this, 'init' ), 50 );

			$this->page_templates = array(
				'page-templates/private-area.php' => __( 'Private area', 'woocommerce-funnels' ),
			);

			// Actions.
			add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

		}

		public function init() {

			if ( function_exists( 'Sensei' ) ) {
				$this->sensei = new Sensei( $this );
			}

			if ( function_exists( 'affiliate_wp' ) ) {
				$this->affiliate_wp = new AffiliateWP( $this );
			}

			$this->woocommerce_hooks();

			$mb = new Helpers\Post\MetaBox(
				'funnels-restrict-to-product',
				array(
					'restrict_to_purchased_product_id' => array(
						'label' => __( 'Select the product that has to be purchased', 'woocommerce-funnels' ),
						'type' => 'number',
					),
					'not_purchased_redirect_page_id' => array(
						'label' => __( 'Select the page to where we redirect the user if the product has not been purchased', 'woocommerce-funnels' ),
						'type' => 'number',
					),
					'purchased_product_readmore_label' => array(
						'label' => __( 'Select the label for readmore button in account product category view', 'woocommerce-funnels' ),
						'type' => 'text',
					),
				),
				array(
					'post_type' => 'page',
					'title' => __( 'Product Restrict', 'woocommerce-funnels' ),
				)
			);

		}

		/**
		 * Initialize integration settings form fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'dashboard_content_page' => array(
					'title'       => __( 'Dashboard Content Page', 'woocommerce-funnels' ),
					'type'        => 'select',
					'description' => __( 'Select the page with the content to be shown in MyAccount Dashboard page', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => '',
					'options'     => wp_list_pluck( get_pages(), 'post_title', 'ID' ),
				),
				'dashboard_intro_text' => array(
					'title'       => __( 'Dashboard Intro Text', 'woocommerce-funnels' ),
					'type'        => 'textarea',
					'description' => __( 'Set the template for the dashboard intro text. Available printf placeholders are: orders_url, edit_address, edit_account, affiliate_url, mycourses_url', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => __( 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a> and <a href="%3$s">edit your password and account details</a>.', 'woocommerce-funnels' ),
				),				
				'orders_menu_label'          => array(
					'title'       => __( 'Orders Menu Label', 'woocommerce-funnels' ),
					'type'        => 'text',
					'description' => __( 'The label for orders in account menu', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => __( 'Orders / Invoices', 'woocommerce-funnels' ),
				),
				'account_product_categories' => array(
					'title'       => __( 'Account Product Categories', 'woocommerce-funnels' ),
					'type'        => 'multiselect',
					'description' => __( 'Select the product categories that you want to be shown in My Account Menu', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => '',
					'options'     => get_terms(
						'product_cat', array(
							'hide_empty' => false,
							'fields'     => 'id=>name',
						)
					),
				),
				'checkout_warranty_text' => array(
					'title'       => __( 'Checkout Warranty Text', 'woocommerce-funnels' ),
					'type'        => 'textarea',
					'description' => __( 'The content for the checkout warranty box', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => '',
				),
				'disable_woocommerce_styles' => array(
					'title'       => __( 'Disable WooCommerce Styles', 'woocommerce-funnels' ),
					'type'        => 'checkbox',
					'description' => __( 'Disable WooCommerce CSS styles', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => false,
				),
				'debug'                      => array(
					'title'       => __( 'Debug Log', 'woocommerce-funnels' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce-funnels' ),
					'default'     => 'no',
					'description' => __( 'Log events such as API requests', 'woocommerce-funnels' ),
				),
			);

		}

		/**
		 * Santize our settings
		 *
		 * @see process_admin_options()
		 */
		public function sanitize_settings( $settings ) {
			// We're just going to make the api key all upper case characters since that's how our imaginary API works.
			delete_transient( 'woocommerce_funnel_account_product_categories' );

			return $settings;
		}

		/**
		 * Display errors by overriding the display_errors() method
		 *
		 * @see display_errors()
		 */
		public function display_errors() {
			// loop through each error and display it.
			foreach ( $this->errors as $key => $value ) {
			?>
			<div class="error">
				<p><?php echo esc_html( sprintf( __( 'Looks like you made a mistake with the %S field. Make sure it isn&apos;t longer than 20 characters', 'woocommerce-integration-demo' ), $value ) ); ?></p>
			</div>
			<?php
			}
		}

		protected function woocommerce_account_product_categories() {

			$key    = 'woocommerce_funnel_account_product_categories';
			$result = wp_cache_get( $key );

			if ( false === $result ) {

				if ( empty( $this->account_product_categories ) ) {
					$result = array();
				} else {
					$terms = get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'include'    => $this->account_product_categories,
							'hide_empty' => 1,
						)
					);
	
					if ( ! is_wp_error( $terms ) ) {
						$result = $terms;
						wp_cache_set( $key, $result );
					}
				}
			}

			return $result;
		}


		public function woocommerce_hooks() {

			add_filter( 'woocommerce_account_menu_items', array( $this, 'woocommerce_menu_items' ), 80 );

			add_action( 'init', array( $this, 'woocommerce_endpoints' ), 99 );

			$account_product_categories = $this->woocommerce_account_product_categories();
			
			if( $account_product_categories ) {
				foreach ($account_product_categories  as $term ) {
					add_action(
						'woocommerce_account_' . $term->slug . '_endpoint', function ( $value ) use ( $term ) {
							$this->account_product_categories_page_content( $term );
						}
					);
				}
			}

			if ( $this->show_avatar ) {
				add_action( 'woocommerce_before_account_navigation', array( $this, 'woocommerce_myaccount_sidebar_profile_before' ) );
				add_action( 'woocommerce_after_account_navigation', array( $this, 'woocommerce_myaccount_sidebar_profile_after' ) );
			}

			foreach ( \WC()->query->get_query_vars() as $name => $endpoint ) {
				add_action( 'woocommerce_account_' . $name . '_endpoint', array( $this, 'woocommerce_content_wrapper_start' ), 9 );
				add_action( 'woocommerce_account_' . $name . '_endpoint', array( $this, 'woocommerce_content_wrapper_end' ), 11 );
			}

			add_filter( 'woocommerce_billing_fields', array( $this, 'woocommerce_billing_fields' ), 99 );

			add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'checkout_page_url' ), 15 );
			add_filter( 'woocommerce_get_cart_url', array( $this, 'checkout_page_url' ), 15 );

			add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'cart_button_text' ), 10, 2 );
			add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'cart_button_text' ), 100, 2 );    // 2.1 +

			add_filter( 'woocommerce_email_headers', array( $this, 'woocommerce_email_headers' ), 2, 3 );

			add_filter( 'woocommerce_get_endpoint_url', array( $this, 'woocommerce_endpoint_urls' ), 10, 4 );
			add_action( 'woocommerce_after_edit_account_form', array( $this, 'woocommerce_edit_account' ) );

			add_filter( 'woocommerce_add_to_cart_sold_individually_quantity', array( $this, 'woocommerce_sold_individually_quantity' ), 10, 5 );
			add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', array( $this, 'woocommerce_sold_individually_found_in_cart' ), 10, 5 );

			add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tabs' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_product_data_panels' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_product_data_save' ), 10, 2 );

			add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'order_received_url' ), 10, 2 );

			add_action( 'wp', array( $this, 'woocommerce_product_remove_default_contents' ) );
			add_action( 'template_redirect', array( $this, 'woocommerce_product_page_restrict' ) );

			add_filter( 'wc_get_template_part', array( $this, 'plugin_template_part' ), 10, 3 );
			add_filter( 'wc_get_template_part', array( $this, 'single_product_custom_content_template' ), 11, 4 );

			add_filter( 'wc_get_template', array( $this, 'plugin_template' ), 10, 4 );

			add_filter( 'theme_page_templates', array( $this, 'add_page_template' ), 10, 4 );
			add_filter( 'template_include', array( $this, 'include_page_template' ) );

			if ( $this->disable_woocommerce_styles ) {
				add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
			}

			add_filter( 'woocommerce_before_checkout_form', array( $this, 'checkout_safe_payments_banner' ) );
			add_filter( 'woocommerce_after_checkout_form', array( $this, 'checkout_warranty' ) );
			add_filter( 'woocommerce_checkout_order_review', array( $this, 'choose_payment_method' ), 15 );

			$this->disable_checkout_notifications();

			add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_link_close', 15 );
			add_action( 'woocommerce_after_shop_loop_item_title', 'the_excerpt', 8 );

			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close' );
			add_filter( 'woocommerce_my_account_my_address_description', array( $this, 'my_account_address_description' ) );
			add_action( 'woocommerce_save_account_details', array( $this, 'save_account_details_redirect' ) );

			add_shortcode( 'woocommerce_order_thankyou', array( $this, 'order_thankyou_shortcode' ) );
			add_action( 'woocommerce_before_main_content', array( $this, 'upsell_order_thankyou' ), 10 );

			add_filter( 'woocommerce_bacs_account_fields', array( $this, 'specify_bacs_reason' ), 10, 2 );
			
			add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
			
			add_action( 'add_chained_products_actions_filters', array( $this, 'chained_products_template_enable' ) );
			add_action( 'remove_chained_products_actions_filters', array( $this, 'chained_products_template_disable' ) );
			
			add_filter( 'woocommerce_account_dashboard_introtext', 'do_shortcode', 100 );
			add_filter( 'woocommerce_account_dashboard_introtext', 'wpautop', 110 );
		}

		public function chained_products_template_enable(){
			remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'chained_products_free_label') );
			add_filter( 'woocommerce_get_price_html_from_text', array( $this, 'chained_products_price_text') );
		}
		
		public function chained_products_template_disable(){
			add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 20 );
			remove_action( 'woocommerce_after_shop_loop_item', array( $this, 'chained_products_free_label') );
			remove_filter( 'woocommerce_get_price_html_from_text', array( $this, 'chained_products_price_text') );
		}		

		public function chained_products_free_label(){
			echo apply_filters('woocommerce_funnels_chained_free_label', '<div class="free-label">' . __('Free', 'woocommerce-funnels') . '</div>');
		}
		
		public function chained_products_price_text( $from ){
			return '<span class="from">' . _x( 'Value', 'original value', 'woocommerce-funnels' ) . ' </span>';
		}		

		public function woocommerce_product_remove_default_contents() {

			remove_filter( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
			remove_filter( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
			
			if ( is_product() && get_post_meta( get_the_ID(), '_funnels_disable_product_template', true ) ) {

				remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );

				remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
				remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
				
				//remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
				
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
				add_action( 'woocommerce_after_single_product_summary', 'woocommerce_product_description_tab', 10 );

				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

				add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
			} else {
				add_filter( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 25 );
			}

		}

		public function woocommerce_product_page_restrict() {

			global $post;

			if ( is_admin() || ! is_page() || current_user_can( 'edit_others_pages' ) || empty( $post ) ) {
				return;
			}

			$current_user = wp_get_current_user();

			$required_product = apply_filters(
				'woocommerce_funnels_page_restrict_purchased_product',
				get_post_meta( get_the_ID(), 'restrict_to_purchased_product_id', true ),
				$post,
				$current_user
			);

			if ( ! $required_product ||
				(
					is_user_logged_in() &&
					wc_customer_bought_product( $current_user->user_email, $current_user->ID, $required_product )
				)
			) {
				return;
			}

			$redirect_page = get_post_meta( get_the_ID(), 'not_purchased_redirect_page_id', true );

			if ( $redirect_page ) {
				wp_safe_redirect(
					apply_filters(
						'woocommerce_funnels_page_not_purchased_product_redirect',
						get_permalink( $redirect_page ),
						$redirect_page,
						$post
					)
				);
			} else {
				wp_die( __( 'You are not allowed to access this page, please purchase the linked product', 'woocommerce-funnels' ) );
			}
		}

		public function plugin_template_part( $template, $slug, $name ) {

			// Found template in theme? Skip plugin template.
			if ( $template && ( strpos( $template, WC()->template_path() ) === 0 ) ) {
				return $template;
			}

			if ( $name && file_exists( plugin_dir() . "templates/woocommerce/{$slug}-{$name}.php" ) ) {
				return plugin_dir() . "templates/woocommerce/{$slug}-{$name}.php";
			}

			if ( file_exists( plugin_dir() . "templates/woocommerce/{$slug}.php" ) ) {
				return plugin_dir() . "templates/woocommerce/{$slug}.php";
			}

			return $template;
		}

		public function plugin_template( $located, $template_name, $args = array(), $template_path = '' ) {

			if ( WC_TEMPLATE_DEBUG_MODE ) {
				return $located;
			}

			$override_located = wc_locate_template( $template_name, $template_path, plugin_dir() . 'templates/woocommerce/' );

			if ( $override_located && file_exists( $override_located ) ) {
				return $override_located;
			}

			return $located;
		}

		public function add_page_template( $post_templates, $theme, $post, $post_type ) {

			$post_templates = array_merge( $post_templates, $this->page_templates );

			return $post_templates;
		}

		public function include_page_template( $template ) {

			// Get global post
			global $post;

			// Return template if post is empty
			if ( ! $post ) {
				return $template;
			}

			$template_file = get_post_meta( $post->ID, '_wp_page_template', true );

			// Return default template if we don't have a custom one defined
			if ( ! $template_file || ! isset( $this->page_templates[ $template_file ] ) ) {
				return $template;
			}

			$template_path = plugin_dir() . '/templates/' . $template_file;

			// Just to be safe, we check if the file exist first
			if ( file_exists( $template_path ) ) {
				return $template_path;
			}

			// Return template
			return $template;
		}

		public function disable_checkout_notifications() {
			add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
			add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
		}

		public function woocommerce_sold_individually_quantity( $individual_quantity, $quantity, $product_id, $variation_id, $cart_item_data ) {

			// Generate a ID based on product ID, variation ID, variation data, and other cart item data.
			$cart_id = WC()->cart->generate_cart_id( $product_id, $variation_id, array(), $cart_item_data );

			// Find the cart item key in the existing cart.
			$cart_item_key = WC()->cart->find_product_in_cart( $cart_id );

			$in_cart_quantity = WC()->cart->get_cart_contents();

			if ( $cart_item_key && ( $in_cart_quantity[ $cart_item_key ]['quantity'] > 0 ) ) {
				return 0;
			}

			return $individual_quantity;
		}

		public function woocommerce_sold_individually_found_in_cart( $found_in_cart, $product_id, $variation_id, $cart_item_data, $cart_id ) {
			return false;
		}


		public function woocommerce_myaccount_sidebar_profile_before() {
		?>
			<button class="toggle-secondary"><span class="screen-reader-text"><?php esc_html_e( 'Toggle Account Menu', 'woocommerce-funnels' ); ?></span></button>
	
			<div id="secondary" class="my-account togglable">
	
			<?php
			if ( is_user_logged_in() ) :
				$member = wp_get_current_user();
			?>
				<div id="profile">
					<?php echo get_avatar( $member->ID, 'thumbnail' ); ?>
					<div class="user-name"><?php echo $member->user_firstname . '&nbsp;' . $member->user_lastname; ?></div>    
				<div class="user-email"><?php echo esc_html( $member->user_email ); ?></div>
			</div>
			<?php
			endif;
		}

		public function woocommerce_myaccount_sidebar_profile_after() {
		?>
		</div> <!-- #secondary.my-account -->
		<?php
		}


		public function woocommerce_menu_items( $items ) {

			$new_items = array();

			$account_product_categories = $this->woocommerce_account_product_categories();
			
			if( $account_product_categories ) {
				foreach ( $account_product_categories as $term ) {
					$new_items[ $term->slug ] = $term->name;
				}
			}

			$items = Utils::keyInsert( $items, $new_items, 'dashboard' );

			$items['orders'] = $this->orders_menu_label;

			unset( $items['edit-address'] );
			unset( $items['payment-methods'] );

			return $items;
		}

		public function woocommerce_endpoints() {
			$account_product_categories = $this->woocommerce_account_product_categories();
			
			if( $account_product_categories ) {
				foreach ( $account_product_categories as $term ) {
					add_rewrite_endpoint( $term->slug, EP_PAGES );
				}
			}

		}

		public function sensei_menu_items( $items ) {

			$new_items = array();

			$new_items['mycourses'] = __( 'My Courses', 'woocommerce-funnels' );

			if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
				$new_items['messages'] = __( 'Messages', 'woocommerce-funnels' );
			}

			$items = Utils::keyInsert( $items, $new_items, 'dashboard' );

			return $items;
		}

		/**
		 * Category Product page content in WC Account
		 */
		public function account_product_categories_page_content( $term ) {
		?>
		<div class="product-page-content">
			<header class="privatearea-header"> 
				<h2><?php echo esc_html( apply_filters( 'single_term_title', $term->name ) ); ?></h2>
				<?php
				$description = get_term_field( 'description', $term, 'product_cat' );
				if ( ! is_wp_error( $description ) && $description ) :
				?>
				<p class="subtitle"><?php echo $description; ?></p>
				<?php endif; ?>
			</header>
			<?php
			$current_user = wp_get_current_user();

			$args = array(
				'post_type'   => 'product',
				'product_cat' => $term->slug,
				'fields' => 'ids',
			);

			$product_ids = get_posts( $args );

			$bought_product_ids = array_filter(
				$product_ids, function ( $product_id ) use ( $current_user ) {
					return wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id );
				}
			);

			$args = array(
				'post_status' => 'publish',
				'post_type' => 'page',
				'ignore_sticky_posts' => true,
				'meta_query' => array(
					array(
						'key'     => 'restrict_to_purchased_product_id',
						'value'   => $bought_product_ids,
						'compare' => 'IN',
					),
				),
			);

			$pages_query = new WP_Query( $args );

			if ( ! empty( $bought_product_ids ) && $pages_query->have_posts() ) {
			?>
			<div class="section-content post-list product-page-list ">
			<?php
			while ( $pages_query->have_posts() ) :
				$pages_query->the_post();
					wc_get_template_part( 'myaccount/productpage/thumb', $term->slug );
				endwhile;
			?>
			</div>
			<?php
			} else {
				wc_no_products_found();
			}

			wp_reset_postdata();
			?>
		</div>
		<?php
		}


		/**
		 * Change the courses archive to WC endpoint
		 */
		public function sensei_post_types_archive_link( $link, $post_type ) {

			if ( 'sensei_message' === $post_type ) {
				return wc_get_endpoint_url( 'messages' );
			}

			return $link;
		}

		/**
		 * Change billing fields position, remove some
		 */
		public function woocommerce_billing_fields( $fields ) {

			$fields['billing_email']['priority'] = 25;
			$fields['billing_email']['class']    = array( 'form-row-wide' );

			unset( $fields['billing_address_2'] );
			unset( $fields['billing_phone'] );

			return $fields;
		}

		/**
		 * Print account page content wrapper start
		 */
		public function woocommerce_content_wrapper_start() {
		?>
		<div class="content-wrapper">
			<h2><?php echo esc_html( wc_page_endpoint_title( '' ) ); ?></h2>
		<?php
		}

		/**
		 * Print account page content wrapper end
		 */
		public function woocommerce_content_wrapper_end() {
		?>
		</div>
		<?php
		}

		/**
		 * Redirect cart to checkout
		 */
		public function checkout_page_url( $url ) {
			global $product;

			if ( $product && get_post_meta( $product->get_id(), '_funnels_disable_cart', true ) ) {
				return wc_get_checkout_url();
			}

			return $url;
		}

		/**
		 * Change purchase button text
		 */
		public function cart_button_text( $text, $product = null ) {

			$custom_text = $product ? get_post_meta( $product->get_id(), '_funnels_add_to_cart_text', true ) : null;

			if ( $custom_text ) {
				return $custom_text;
			}

			return $text;
		}

		/**
		 * Hack for Sendinblue, remove reply to.
		 */
		public function woocommerce_email_headers( $headers, $mail_id, $object ) {

			if ( 'new_order' === $mail_id ) {
				$headers = str_replace( 'Reply-to: ' . $object->get_billing_first_name() . ' ' . $object->get_billing_last_name() . ' <' . $object->get_billing_email() . ">\r\n", '', $headers );
			}

			return $headers;
		}

		public function woocommerce_edit_account() {

			if ( shortcode_exists( 'avatar_upload' ) ) {
				remove_action( 'wpua_before_avatar', 'wpua_do_before_avatar' );
				remove_action( 'wpua_after_avatar', 'wpua_do_after_avatar' );

			?>
			<section id="user-avatar">
				<h2><?php esc_html_e( 'Profile Image', 'woocommerce-funnels' ); ?></h2>
				<?php echo do_shortcode( '[avatar_upload]' ); ?>
			</section>
			<?php
				add_action( 'wpua_before_avatar', 'wpua_do_before_avatar' );
				add_action( 'wpua_after_avatar', 'wpua_do_after_avatar' );
			}
			?>

			<section id="edit-address">
				<h2><?php esc_html_e( 'Edit Adresses', 'woocommerce-funnels' ); ?></h2>
				<?php woocommerce_account_edit_address( '' ); ?>    
			</section>

			<?php
			$support_payment_methods = false;
			foreach ( WC()->payment_gateways->get_available_payment_gateways() as $gateway ) {
				if ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
					$support_payment_methods = true;
					break;
				}
			}

			if ( $support_payment_methods ) {
			?>
			<section id="payment-methods">
				<h2><?php esc_html_e( 'Payment Methods', 'woocommerce-funnels' ); ?></h2>
				<?php woocommerce_account_payment_methods(); ?>
			</section>        
			<?php } ?>
		<?php
		}

		public function woocommerce_endpoint_urls( $url, $endpoint, $value, $permalink ) {

			if ( ( 'edit-address' === $endpoint ) && ( '' === $value ) ) {
				$url = wc_get_endpoint_url( 'edit-account', $value, $permalink );
			}

			if ( ( 'payment-methods' === $endpoint ) ) {
				$url = wc_get_endpoint_url( 'edit-account', $value, $permalink );
			}

			return $url;

		}

		public function woocommerce_product_data_tabs( $tabs ) {
			$tabs['funnel'] = array(
				'label'    => __( 'Funnel', 'woocommerce' ),
				'target'   => 'funnel_product_data',
				'class'    => array( 'hide_if_external' ),
				'priority' => 90,
			);

			return $tabs;
		}

		public function woocommerce_product_data_panels() {
			global $post;
		?>
		<div id="funnel_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
					woocommerce_wp_checkbox(
						array(
							'id'          => '_funnels_disable_product_template',
							'label'       => __( 'Disable Default Template', 'woocommerce-funnels' ),
							'description' => __( 'Disable WooCommerce product template, you must create the page content manually', 'woocommerce-funnels' ),
							'cbvalue'     => 1,
						)
					);
				?>
			</div>

			<div class="options_group">
				<?php
					woocommerce_wp_text_input(
						array(
							'id'          => '_funnels_add_to_cart_text',
							'label'       => __( 'Add to Cart Text', 'woocommerce-funnels' ),
							'description' => __( 'The text displayed in Add to cart button', 'woocommerce-funnels' ),
							'cbvalue'     => 1,
						)
					);
				?>
			</div>

			<div class="options_group">
				<?php
					woocommerce_wp_checkbox(
						array(
							'id'          => '_funnels_disable_cart',
							'label'       => __( 'Skip Cart', 'woocommerce-funnels' ),
							'description' => __( 'This will skip the cart and redirect the user directly to checkout page', 'woocommerce-funnels' ),
							'cbvalue'     => 1,
						)
					);
				?>
			</div>

			<div class="options_group">
				<p class="form-field">
					<label for="funnels_direct_upsell_product"><?php esc_html_e( 'Direct Upsell Product', 'woocommerce-funnels' ); ?></label>
					<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="funnels_direct_upsell_product" name="_funnels_direct_upsell_product" data-sortable="false" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'woocommerce' ); ?>" data-action="woocommerce_json_search_products" data-exclude="<?php echo intval( $post->ID ); ?>">
					<?php
					$upsell_product_ids = get_post_meta( $post->ID, '_funnels_direct_upsell_product', true );

					foreach ( $upsell_product_ids as $upsell_product_id ) {
						$upsell_product = wc_get_product( $upsell_product_id );
						if ( is_object( $upsell_product_ids ) ) {
							echo '<option value="' . esc_attr( $upsell_product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $upsell_product->get_formatted_name() ) . '</option>';
						}
					}
					?>

					</select> <?php echo wc_help_tip( __( 'This lets you choose which product will be redirected to after checkout', 'woocommerce-funnels' ) ); // WPCS: XSS ok. ?>
				</p>
			</div>

			<div class="options_group">
				<?php
				$args = array(
					'show_option_none' => __( '-- Default order summary --', 'woocommerce-funnels' ),
					'name'             => '_funnels_thankyou_page',
					'id'               => 'funnels_thankyou_page',
					'selected'         => get_post_meta( $post->ID, '_funnels_thankyou_page', true ),
					'class'            => 'select short',
				);
				?>
				<p class="form-field dimensions_field">
					<label for="funnels_thankyou_page"><?php esc_html_e( 'Thank you page', 'woocommerce' ); ?></label>
					<?php wp_dropdown_pages( $args ); ?>
					<?php echo wc_help_tip( __( 'Select this to set the thankyou page after a successful checkout', 'woocommerce' ) ); ?>
				</p>		
			</div>
		</div>
		<?php

		}

		public function woocommerce_product_data_save( $product_id ) {

			$data = filter_input_array(
				INPUT_POST,
				array(
					'_funnels_add_to_cart_text'         => FILTER_SANITIZE_STRING,
					'_funnels_disable_product_template' => FILTER_VALIDATE_BOOLEAN,
					'_funnels_disable_cart'             => FILTER_VALIDATE_BOOLEAN,
					'_funnels_direct_upsell_product'    => array(
						'filter'  => FILTER_VALIDATE_INT,
						'flags'   => FILTER_REQUIRE_SCALAR,
						'options' => array( 'min_range' => 0 ),
					),
					'_funnels_thankyou_page'            => array(
						'filter'  => FILTER_VALIDATE_INT,
						'flags'   => FILTER_REQUIRE_SCALAR,
						'options' => array( 'min_range' => 0 ),
					),
				)
			);

			update_post_meta( $product_id, '_funnels_add_to_cart_text', wc_clean( $data['_funnels_add_to_cart_text'] ) ?: null );
			update_post_meta( $product_id, '_funnels_disable_product_template', $data['_funnels_disable_product_template'] );
			update_post_meta( $product_id, '_funnels_disable_cart', $data['_funnels_disable_cart'] );
			update_post_meta( $product_id, '_funnels_direct_upsell_product', $data['_funnels_direct_upsell_product'] );
			update_post_meta( $product_id, '_funnels_thankyou_page', $data['_funnels_thankyou_page'] );
		}

		public function checkout_safe_payments_banner() {
		?>
			<h2 id="checkout_heading" ><?php _e( 'Safe Payments', 'woocommerce-funnels' ); ?></h2>
		<?php
		}

		public function choose_payment_method() {
		?>
			<h3 id="checkout_payment_heading" ><?php _e( 'Choose payment method', 'woocommerce-funnels' ); ?></h3>
		<?php
		}

		public function checkout_warranty() {
			if ( $this->checkout_warranty_text ) :
			?>
			<div class="checkout-warranty"><?php echo $this->checkout_warranty_text; ?></div>
		<?php
			endif;
		}

		public function single_product_custom_content_template( $template, $slug, $name ) {
			global $product;

			if ( ( 'content' !== $slug ) || ( 'single-product' !== $name ) || ! $product ) {
				return $template;
			}

			$disable_wc_template = get_post_meta( $product->get_id(), '_funnels_disable_product_template', true );

			if ( $disable_wc_template ) {
				$template = $this->plugin_template_part( '', $slug, $name . '-custom' );
			}

			return $template;
		}

		public function my_account_address_description() {
			return __( 'The <b>billing address</b> is used to compose the invoices. The <b>shipping address</b> will be used to send you phisical products that you may purchase. You will be able to change both addresses durig the checkout process.', 'woocommerce-funnels' );
		}

		public function save_account_details_redirect() {
			wp_safe_redirect( wc_get_endpoint_url( 'edit-account' ) );
			exit;
		}

		public function get_order_thankyou_page( $order ) {

			$items = $order->get_items();

			foreach ( $items as $item ) {
				$thankyou_page_id = get_post_meta( $item->get_product_id(), '_funnels_thankyou_page', true );

				if ( $thankyou_page_id ) {
					return $thankyou_page_id;
				}
			}

			return false;
		}

		public function order_upsell_product( $order ) {

			$order_items = $order->get_items();

			foreach ( $order_items as $item ) {
				$upsell_product = get_post_meta( $item->get_product_id(), '_funnels_direct_upsell_product', true );

				if ( $upsell_product ) {
					return $upsell_product;
				}
			}

			return false;

		}

		public function order_received_url( $url, $order ) {

			if ( is_numeric( $order ) ) {
				$order = wc_get_order( $order );
			}

			if ( ! $order || $order->has_status( 'failed' ) ) {
				return $url;
			}

			$redirect_to = null;

			if ( $upsell_product = $this->order_upsell_product( $order ) ) {
				$redirect_to = get_permalink( $upsell_product );
			} elseif ( $thankyou_page_id = $this->get_order_thankyou_page( $order ) ) {
				$redirect_to = get_permalink( $thankyou_page_id );
			}

			if ( ! empty( $redirect_to ) ) {

				$redirect_to = add_query_arg(
					array(
						'order' => $order->get_id(),
						'key'   => $order->get_order_key(),
					),
					$redirect_to
				);

				if ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) || is_ssl() ) {
					$redirect_to = str_replace( 'http:', 'https:', $redirect_to );
				}

				$url = $redirect_to;

			}

			return $url;
		}

		public function upsell_order_thankyou() {
			echo $this->order_thankyou_shortcode();
		}

		public function order_thankyou_shortcode( $atts = array(), $content = '' ) {

			$order = false;

			$order_id  = apply_filters( 'woocommerce_thankyou_order_id', filter_input( INPUT_GET, 'order', FILTER_VALIDATE_INT ) );
			$order_key = apply_filters( 'woocommerce_thankyou_order_key', filter_input( INPUT_GET, 'key', FILTER_SANITIZE_ENCODED ) );

			if ( $order_id > 0 ) {
				$order = wc_get_order( $order_id );
				if ( ! $order || $order->get_order_key() !== $order_key ) {
					$order = false;
				}
			}

			if ( ! $order || $order->has_status( 'failed' ) ) {
				return '';
			}

			$payment_gateway = wc_get_payment_gateway_by_order( $order );

			if ( ! empty( $payment_gateway->instructions ) ) {
				$payment_gateway->instructions = str_replace( '{ORDER_ID}', $order_id, $payment_gateway->instructions );
			}

			$output = '<div id="order-thankyou">';

			$attr = shortcode_atts(
				array(
					'show_details' => 1,
					'details_button' => 1,
					'message' => __( 'Thank you for your purchase!', 'woocommerce-funnels' ),
				), $atts
			);

			$output .= '<h2>' . $attr['message'] . '</h2>';
			$output .= '<p class="shortcode-content">' . $content . '</p>';

			ob_start();
			// urroiudosi
			if ( has_action( 'woocommerce_thankyou_' . $order->get_payment_method() ) || has_action( 'woocommerce_thankyou' ) ) {
				echo '<div class="payment-instructions">';
				do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
				remove_all_actions( 'woocommerce_thankyou_' . $order->get_payment_method() );
				echo '</div>';
			}

			if ( $attr['show_details'] && $attr['details_button'] ) {
				$button_output = '<button class="button order-details-toggle" >' . __( 'Show details', 'woocommerce-funnels' ) . '</button>';
			} elseif ( $attr['details_button'] ) {
				$button_output = '<a class="button" target="_blank" href="' . esc_url( $order->get_checkout_order_received_url() ) . '">' . __( 'Show details', 'woocommerce-funnels' ) . '</a>';
			}

			if ( ! empty( $button_output ) ) {
				echo '<div class="order-more-info">' . sprintf( __( 'Find all the info %s', 'woocommerce-funnels' ), $button_output ) . '</div>';
			}

			if ( $attr['show_details'] ) {
				echo '<div id="order-details" style="display: none;">';
				wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
				echo '</div>';
			}

			$output .= ob_get_contents();
			ob_end_clean();

			$output .= '</div>';

			return $output;
		}

		public function specify_bacs_reason( $fields, $order_id ) {

			$fields['reason'] = array(
				'label' => __( 'Causal', 'woocommerce-funnels' ),
				'value' => __( 'Payment for order number: ', 'woocommerce-funnels' ) . $order_id,
			);

			return $fields;
		}

	}
endif;
