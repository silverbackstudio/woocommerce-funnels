<?php

namespace Svbk\WP\Plugins\WooCommerce\Funnels;

use WC_Integration;
use Svbk\WP\Helpers\Lists\Utils;
use Wp_Query;


/**
 * Funnels Integration.
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
		public $affiliate_menu_label;
		public $orders_menu_label;
		public $account_product_categories;

		public $sensei       = true;
		public $affiliate_wp = true;

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
			$this->affiliate_menu_label       = $this->get_option( 'affiliate_menu_label' );
			$this->orders_menu_label          = $this->get_option( 'orders_menu_label' );
			$this->account_product_categories = $this->get_option( 'account_product_categories' );

			add_action( 'init', array( $this, 'init_form_fields' ), 30 );
			add_action( 'init', array( $this, 'init' ), 50 );

			// Actions.
			add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

		}

		public function init() {

			if ( function_exists( 'Sensei' ) ) {
				$this->sensei = true;
			}

			if ( function_exists( 'affiliate_wp' ) ) {
				$this->affiliate_wp = true;
			}

			$this->woocommerce_hooks();

			if ( $this->sensei ) {
				$this->sensei_hooks();
			}

		}

		/**
		 * Initialize integration settings form fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'mycourses_menu_label'       => array(
					'title'       => __( 'Courses Menu Label', 'woocommerce-funnels' ),
					'type'        => 'text',
					'description' => __( 'The label for Courses in account menu', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => __( 'My Courses', 'woocommerce-funnels' ),
				),
				'affiliate_menu_label'       => array(
					'title'       => __( 'Affiliate Menu Label', 'woocommerce-funnels' ),
					'type'        => 'text',
					'description' => __( 'The label for Courses in account menu', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => __( 'Affiliate', 'woocommerce-funnels' ),
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
				}

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

			return $result;
		}


		public function woocommerce_hooks() {

			add_filter( 'woocommerce_account_menu_items', array( $this, 'woocommerce_menu_items' ), 80 );

			add_action( 'init', array( $this, 'woocommerce_endpoints' ), 99 );
			add_action( 'woocommerce_account_affiliate_endpoint', array( $this, 'affiliate_page_content' ) );

			foreach ( $this->woocommerce_account_product_categories() as $term ) {
				add_action(
					'woocommerce_account_' . $term->slug . '_endpoint', function ( $value ) use ( $term ) {
						$this->account_product_categories_page_content( $term );
					}
				);
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

			add_action( 'woocommerce_before_template_part', array( $this, 'woocommerce_before_template_parts' ), 10, 4 );

			add_action( 'template_redirect', array( $this, 'wooocommerce_product_upsell_redirect' ) );

			add_action( 'wp', array( $this, 'woocommerce_product_remove_default_contents' ) );

			add_filter( 'affwp_affiliate_area_page_url', array( $this, 'affiliate_area_page_url' ), 10, 3 );

			$this->disable_checkout_notifications();

		}

		public function woocommerce_product_remove_default_contents() {

			if ( is_product() && get_post_meta( get_the_ID(), '_funnels_disable_product_template', true ) ) {

				remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0 );

				remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
				remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
				remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
				add_action( 'woocommerce_after_single_product_summary', 'woocommerce_product_description_tab', 10 );

				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
				remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

				add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
			}

		}

		public function disable_checkout_notifications() {
			add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
			add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );
		}

		public function woocommerce_sold_individually_quantity( $individual_quantity, $quantity, $product_id, $variation_id, $cart_item_data ) {

			// Generate a ID based on product ID, variation ID, variation data, and other cart item data.
			$cart_id = $this->generate_cart_id( $product_id, $variation_id, array(), $cart_item_data );

			// Find the cart item key in the existing cart.
			$cart_item_key = $this->find_product_in_cart( $cart_id );

			$in_cart_quantity = WC()->cart->get_cart_contents();

			if ( $cart_item_key && ( $in_cart_quantity[ $cart_item_key ]['quantity'] > 0 ) ) {
				return 0;
			}

			return $individual_quantity;
		}

		public function woocommerce_sold_individually_found_in_cart( $found_in_cart, $product_id, $variation_id, $cart_item_data, $cart_id ) {
			return false;
		}

		public function sensei_hooks() {

			add_action( 'woocommerce_account_dashboard', array( $this, 'myaccount_all_courses_page_content' ), 9 );

			// Hack to edit the shortcode tabs url.
			add_action( 'sensei_loop_course_before', array( $this, 'sensei_replace_mycourses_url_activate' ), 9 );

			add_action( 'init', array( $this, 'woocommerce_endpoints' ), 99 );

			add_filter( 'woocommerce_account_menu_items', array( $this, 'sensei_menu_items' ), 90 );

			if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
				add_action( 'woocommerce_account_messages_endpoint', array( $this, 'sensei_messages_page_content' ) );
			}

			add_filter( 'post_type_archive_link', array( $this, 'sensei_post_types_archive_link' ), 10, 2 );

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

			foreach ( $this->woocommerce_account_product_categories() as $term ) {
				$new_items[ $term->slug ] = $term->name;
			}

			if ( $this->affiliate_wp ) {
				$new_items['affiliate'] = __( 'Affiliate', 'woocommerce-funnels' );
			}

			$items = Utils::keyInsert( $items, $new_items, 'dashboard' );

			$items['orders'] = $this->orders_menu_label;

			unset( $items['edit-address'] );

			return $items;
		}

		public function woocommerce_endpoints() {

			add_rewrite_endpoint( 'affiliate', EP_PAGES );

			foreach ( $this->woocommerce_account_product_categories() as $term ) {
				add_rewrite_endpoint( $term->slug, EP_PAGES );
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


		public function sensei_endpoints() {

			add_rewrite_endpoint( 'mycourses', EP_PAGES );

			if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
				add_rewrite_endpoint( 'messages', EP_PAGES );
			}

		}

		public function sensei_replace_mycourses_url_activate( $url ) {
			add_filter( 'page_link', 'giordanoguerrieri_replace_sensei_mycourses_url' );
		}

		public function sensei_replace_mycourses_url_apply( $url ) {
			remove_filter( 'page_link', 'giordanoguerrieri_replace_sensei_mycourses_url' );
			return wc_get_endpoint_url( 'mycourses' );
		}

		/**
		 * Messages page in WC Account content
		 */
		public function sensei_messages_page_content() {
		?>
		<div class="content-wrapper">
			<h2><?php esc_html_e( 'Messages', 'woocommerce-funnels' ); ?></h2>
			<?php echo do_shortcode( '[sensei_user_messages]' ); ?>
		</div>
		<?php
		}

		/**
		 * Courses page in WC Account content
		 */
		public function myaccount_all_courses_page_content() {
		?>
		<section id="dashboard-courses">
			<h2><?php esc_html_e( 'Courses', 'woocommerce-funnels' ); ?></h2>
			<?php echo do_shortcode( '[sensei_courses]' ); ?>
		</section>    
		<?php
		}

		/**
		 * Category Product page content in WC Account
		 */
		public function account_product_categories_page_content( $term ) {
		?>
		<div class="content-wrapper">
			<h2><?php echo esc_html( apply_filters( 'single_term_title', $term->name ) ); ?></h2>

			<?php
			$current_user = wp_get_current_user();
			$args         = array(
				'post_type'   => 'product',
				'product_cat' => $term->slug,
			);
			$loop         = new \WP_Query( $args );

			woocommerce_product_loop_start();

			$found = false;

			while ( $loop->have_posts() ) :
				$loop->the_post();

				if ( wc_customer_bought_product( $current_user->user_email, $current_user->ID, get_the_ID() ) ) {
					wc_get_template_part( 'content', 'product' );
					$found = true;
				}
			endwhile;

			woocommerce_product_loop_end();

			if ( ! $found ) {
				/**
				 * Hook: woocommerce_no_products_found.
				 *
				 * @hooked wc_no_products_found - 10
				 */
				do_action( 'woocommerce_no_products_found' );
			}

			woocommerce_reset_loop();
			wp_reset_postdata();
			?>
		</div>
		<?php
		}

		/**
		 * Affiliate page in WC Account content
		 */
		public function affiliate_page_content() {
		?>
		<div class="content-wrapper">    
			<h2><?php esc_html_e( 'Affiliate', 'woocommerce-funnels' ); ?></h2>
			<?php echo do_shortcode( '[affiliate_area]' ); ?>
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
		 * Change url of affiliate area to the WC Endopoint
		 */
		public function affiliate_area_page_url( $affiliate_area_page_url, $affiliate_area_page_id, $tab ) {

			$affiliate_area_page_url = wc_get_endpoint_url( 'affiliate' );

			if ( ! empty( $tab ) && array_key_exists( $tab, affwp_get_affiliate_area_tabs() ) ) {
				$affiliate_area_page_url = add_query_arg( array( 'tab' => $tab ), $affiliate_area_page_url );
			}

			return $affiliate_area_page_url;
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
				$headers = str_replace( 'Reply-to: ' . $object->get_billing_first_name() . ' ' . $object->get_billing_last_name() . ' <' . $object->get_billing_email() . ">\r\n", '' );
			}

			return $headers;
		}

		public function woocommerce_edit_account() {

			if ( shortcode_exists( 'avatar_upload' ) ) {
				remove_action( 'wpua_before_avatar', 'wpua_do_before_avatar' );
				remove_action( 'wpua_after_avatar', 'wpua_do_after_avatar' );

			?>
			<section id="user-avatar">
				<h2><?php esc_html_e( 'Profile Image', 'giordanoguerrieri' ); ?></h2>
				<?php echo do_shortcode( '[avatar_upload]' ); ?>
			</section>
			<?php
				add_action( 'wpua_before_avatar', 'wpua_do_before_avatar' );
				add_action( 'wpua_after_avatar', 'wpua_do_after_avatar' );
			}
			?>

			<section id="edit-address">
				<h2><?php esc_html_e( 'Edit Adresses', 'giordanoguerrieri' ); ?></h2>
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
				<h2><?php esc_html_e( 'Payment Methods', 'giordanoguerrieri' ); ?></h2>
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
				'show_option_none' => __( 'Default order summary', 'woocommerce' ),
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
					'_funnels_add_to_cart_text'         => FILTER_SANITIZE_ENCODED,
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

		public function woocommerce_before_template_parts( $template_name, $template_path, $located, $args ) {

			if ( ( 'checkout/thankyou.php' === $template_name ) && ( isset( $args['order'] ) ) ) {

				$order = $args['order'];
				$items = $order->get_items();

				$thankyou_page_id = null;

				foreach ( $items as $item ) {
					$thankyou_page_id = get_post_meta( $item->get_product_id(), '_funnels_thankyou_page', true );

					if ( $thankyou_page_id ) {
						break;
					}
				}

				if ( ! $thankyou_page_id ) {
					return;
				}

				$tq = new \WP_Query(
					array(
						'p'           => intval( $thankyou_page_id ),
						'post_type'   => 'any',
						'post_status' => 'publish',
					)
				);

				if ( $tq->have_posts() ) :
					$tq->the_post();
					get_template_part( 'template-parts/content', 'page' );
				endif;

				wp_reset_postdata();
			}

		}

		/**
		 * Redirects the customer to the upsell product
		 */
		public function wooocommerce_product_upsell_redirect() {
			global $wp;

			if ( is_checkout() && ! empty( $wp->query_vars['order-received'] ) ) {

				$order_id  = absint( $wp->query_vars['order-received'] );
				$order_key = wc_clean( filter_input( INPUT_GET, 'key', FILTER_SANITIZE_ENCODED ) );

				if ( $order_id !== intval( wc_get_order_id_by_order_key( $order_key ) ) ) {
					wp_die( __( 'Invalid order key', 'woocommerce-funnels' ) );
				}

				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					wp_die( __( 'Invalid order', 'woocommerce-funnels' ) );
				}

				$order_items = $order->get_items();

				foreach ( $order_items as $item ) {
					$direct_upsell_product = get_post_meta( $item->get_product_id(), '_funnels_direct_upsell_product', true );

					if ( $direct_upsell_product ) {
						break;
					}
				}

				$product_url = get_permalink( $direct_upsell_product );

				$redirect = add_query_arg(
					array(
						'order' => $order_id,
						'key'   => $order_key,
					), $product_url
				);

				wp_safe_redirect( $redirect );
				exit;
			}
		}



	}
endif;
