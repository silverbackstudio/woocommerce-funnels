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


		public $logger;
		public $account_product_categories;
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
			$this->account_product_categories = $this->get_option( 'account_product_categories' );

			add_action( 'init', array( $this, 'init_form_fields' ), 30 );
			add_action( 'init', array( $this, 'init' ), 50 );

			// Actions.
			add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
		}

		public function init() {

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
				'account_product_categories' => array(
					'title'       => __( 'Account Product Categories', 'woocommerce-funnels' ),
					'type'        => 'multiselect',
					'description' => __( 'Select the product categories that you want to be shown in My Account Menu', 'woocommerce-funnels' ),
					'desc_tip'    => true,
					'default'     => '',
					'options'     => get_terms(
						'product_cat',
						array(
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

		protected function woocommerce_account_product_categories( $hide_empty = 1 ) {

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
							'hide_empty' => $hide_empty,
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

			$account_product_categories = $this->woocommerce_account_product_categories();

			add_action( 'init', array( $this, 'woocommerce_endpoints' ), 99 );

			if ( $account_product_categories ) {
				foreach ( $account_product_categories as $term ) {
					add_action(
						'woocommerce_account_' . $term->slug . '_endpoint',
						function ( $value ) use ( $term ) {
							$this->account_product_categories_page_content( $term );
						}
					);
				}
			}

			add_filter( 'woocommerce_custom_nav_menu_items', array( $this, 'woocommerce_nav_menu_items' ) );

			add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'maybe_redirect_cart_to_checkout' ), 15 );

			add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'cart_button_text' ), 10, 2 );
			add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'cart_button_text' ), 100, 2 );    // 2.1 +

			add_filter( 'woocommerce_add_to_cart_sold_individually_quantity', array( $this, 'woocommerce_sold_individually_quantity' ), 10, 5 );
			add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', array( $this, 'woocommerce_sold_individually_found_in_cart' ), 10, 5 );

			add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tabs' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_product_data_panels' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_product_data_save' ), 10, 2 );

			add_action( 'template_redirect', array( $this, 'woocommerce_product_page_restrict' ) );

			$this->disable_checkout_notifications();

			add_action( 'add_chained_products_actions_filters', array( $this, 'chained_products_template_enable' ) );
			add_action( 'remove_chained_products_actions_filters', array( $this, 'chained_products_template_disable' ) );
		}

		public function chained_products_template_enable() {
			remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'chained_products_free_label' ) );
			add_filter( 'woocommerce_get_price_html_from_text', array( $this, 'chained_products_price_text' ) );
		}

		public function chained_products_template_disable() {
			add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 20 );
			remove_action( 'woocommerce_after_shop_loop_item', array( $this, 'chained_products_free_label' ) );
			remove_filter( 'woocommerce_get_price_html_from_text', array( $this, 'chained_products_price_text' ) );
		}

		public function chained_products_free_label() {
			echo apply_filters( 'woocommerce_funnels_chained_free_label', '<div class="free-label">' . __( 'Free', 'woocommerce-funnels' ) . '</div>' );
		}

		public function chained_products_price_text( $from ) {
			return '<span class="from">' . _x( 'Value', 'original value', 'woocommerce-funnels' ) . ' </span>';
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

		/**
		 * Register permalinks for product categories endpoints
		 */
		public function woocommerce_endpoints() {
			$account_product_categories = $this->woocommerce_account_product_categories();

			if ( $account_product_categories ) {
				foreach ( $account_product_categories as $term ) {
					add_rewrite_endpoint( $term->slug, EP_PAGES );
				}
			}
		}

		/**
		 * Add categories as available item in WP menu editor
		 */
		public function woocommerce_nav_menu_items( $endpoints ) {

			$account_product_categories = $this->woocommerce_account_product_categories();

			if ( $account_product_categories ) {
				foreach ( $account_product_categories as $term ) {
					$endpoints[ $term->slug ] = $term->name;
				}
			}

			return $endpoints;
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
				$product_ids,
				function ( $product_id ) use ( $current_user ) {
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
			<div class="section-content post-list product-page-list">
				<?php
				while ( $pages_query->have_posts() ) :
					$pages_query->the_post();

					$templates = array(
						"template-parts/preview-productpage-{$term->slug}.php",
						'template-parts/preview-productpage.php',

						'template-parts/preview.php',

						'template-parts/content-page.php',
						'template-parts/content.php',
					);

						locate_template( $templates, true, false );
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
		 * Redirect cart to checkout
		 */
		public function maybe_redirect_cart_to_checkout( $url ) {

			$product_id = false;

			if ( !empty( $_REQUEST['add-to-cart'] ) && is_numeric( $_REQUEST['add-to-cart'] ) ) {
				$product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST['add-to-cart'] ) );
			} else if( is_product() ) {
				$product_id = get_the_ID();	
			}
			
			if ( $product_id && get_post_meta( $product_id, '_funnels_disable_cart', true ) ) {
				$url = wc_get_checkout_url();
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

					if ( ! empty( $upsell_product_ids ) ) {
						foreach ( $upsell_product_ids as $upsell_product_id ) {
							$upsell_product = wc_get_product( $upsell_product_id );
							if ( is_object( $upsell_product_ids ) ) {
								echo '<option value="' . esc_attr( $upsell_product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $upsell_product->get_formatted_name() ) . '</option>';
							}
						}
					}
					?>

					</select> <?php echo wc_help_tip( __( 'This lets you choose which product will be redirected to after checkout', 'woocommerce-funnels' ) ); // WPCS: XSS ok. ?>
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
					'_funnels_disable_cart'             => FILTER_VALIDATE_BOOLEAN,
					'_funnels_direct_upsell_product'    => array(
						'filter'  => FILTER_VALIDATE_INT,
						'flags'   => FILTER_REQUIRE_SCALAR,
						'options' => array( 'min_range' => 0 ),
					),
				)
			);

			update_post_meta( $product_id, '_funnels_add_to_cart_text', wc_clean( $data['_funnels_add_to_cart_text'] ) ?: null );
			update_post_meta( $product_id, '_funnels_disable_cart', $data['_funnels_disable_cart'] );
			update_post_meta( $product_id, '_funnels_direct_upsell_product', $data['_funnels_direct_upsell_product'] );
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
	}
endif;
