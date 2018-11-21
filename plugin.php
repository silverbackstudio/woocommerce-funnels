<?php
/**
 * Mangles WooCommerce to make funnels
 *
 * @package woocommerce-funnels
 * @author  Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

/*
Plugin Name: WooCommerce - Funnels
Description: Changes the WooCommerce Purchase Flow to allow Funnels
Author: Silverback Studio
Version: 1.1.0
Author URI: http://www.silverbackstudio.it/
Text Domain: woocommerce-funnels
*/

namespace Svbk\WP\Plugins\WooCommerce\Funnels;

/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function init() {
	load_plugin_textdomain( 'woocommerce-funnels', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( '\WC_Integration' ) ) {
		return;
	}

	if ( ! class_exists( __NAMESPACE__ . '\\WC_Integration_Funnels' ) ) {
		include_once 'includes/class-wc-integration-funnels.php';
	}

	add_filter( 'woocommerce_integrations', __NAMESPACE__ . '\\add_integration' );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 99 );

/**
 * Add a new integration to WooCommerce.
 */
function add_integration( $integrations ) {
	$integrations[] = __NAMESPACE__ . '\\WC_Integration_Funnels';
	return $integrations;
}

function plugin_dir() {
	 return plugin_dir_path( __FILE__ );
}


function plugin_url( $path ) {
	return plugins_url( $path, __FILE__ );
}

function is_private_area() {
	return is_account_page() || is_sensei();
}
