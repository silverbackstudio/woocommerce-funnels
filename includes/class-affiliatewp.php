<?php

namespace Svbk\WP\Plugins\WooCommerce\Funnels;

use Svbk\WP\Helpers;
use Wp_Query;


/**
 * Sensei Funnels Integration.
 *
 * @package  Sensei_Funnels
 * @category Integration
 * @author   Brando Meniconi
 */


class AffiliateWP {

	public $integration;
	
	public $affiliate_menu_label;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct( $integration ) {
		
		$this->integration = $integration;
		
		$integration->form_fields['affiliate_menu_label'] = array(
			'title'       => __( 'Affiliate Menu Label', 'woocommerce-funnels' ),
			'type'        => 'text',
			'description' => __( 'The label for Courses in account menu', 'woocommerce-funnels' ),
			'desc_tip'    => true,
			'default'     => __( 'Affiliate', 'woocommerce-funnels' ),
		);
		
		$this->affiliate_menu_label = $this->integration->get_option( 'affiliate_menu_label' );

		$this->hooks();
	}

	public function hooks() {
		
		add_action( 'init', array( $this, 'endpoints' ), 99 );
		add_action( 'woocommerce_account_affiliate_endpoint', array( $this, 'affiliate_page_content' ) );
		add_filter( 'affwp_affiliate_area_page_url', array( $this, 'affiliate_area_page_url' ), 10, 3 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ), 90 );
	}
	
	public function menu_items( $items ) {

		$new_items = array();

		$new_items['affiliate'] = $this->affiliate_menu_label ?: __( 'Affiliate', 'woocommerce-funnels' );
		
		$items = Helpers\Lists\Utils::keyInsert( $items, $new_items, 'orders', 'before' );
		
		return $items;
	}
	
	public function endpoints() {
		add_rewrite_endpoint( 'affiliate', EP_PAGES );
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
	 * Change url of affiliate area to the WC Endopoint
	 */
	public function affiliate_area_page_url( $affiliate_area_page_url, $affiliate_area_page_id, $tab ) {

		$affiliate_area_page_url = wc_get_endpoint_url( 'affiliate' );

		if ( ! empty( $tab ) && array_key_exists( $tab, affwp_get_affiliate_area_tabs() ) ) {
			$affiliate_area_page_url = add_query_arg( array( 'tab' => $tab ), $affiliate_area_page_url );
		}

		return $affiliate_area_page_url;
	}	
	
	
}

