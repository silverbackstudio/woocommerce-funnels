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
		include_once 'includes/class-sensei.php';
		include_once 'includes/class-affiliatewp.php';
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

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\frontend_scripts' );

/**
 * Register frontend scripts and styles.
 */
function frontend_scripts() {
	wp_enqueue_style( 'woocommerce-funnels', plugins_url( '/assets/css/frontend.css', __FILE__ ) );

	$js_deps = array( 'jquery' );

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		wp_enqueue_script( 'codice-fiscale', 'https://cdn.jsdelivr.net/npm/codice-fiscale-js@1.3.0/dist/codice.fiscale.umd.min.js', array(), '1.3.0', true );
		$js_deps[] = 'codice-fiscale';
	}

	wp_enqueue_script( 'woocommerce-funnels', plugins_url( '/assets/js/frontend.js', __FILE__ ), $js_deps, '1.1.0', true );
    wp_localize_script( 'woocommerce-funnels', 'woocommerceFunnels',
        array( 
            'cvcInstructions' => array(
            	'buttonText' => __('Where?', 'woocommerce-funnels'),
            	'text' => __('If you use Mastercard, Visa and Diners, 
            	the security code is shown on the back of your credit card near 
            	the space reserved for your signature, and consists of 3 digits. 
            	If you use American Express, the secure code is placed on the 
            	front of your card and consists of 4 digits.', 
            	'woocommerce-funnels'),
            	'imageUrl' => plugin_url( 'assets/media/cvc-instructions.png' ),
            	'closeText' => __('Close', 'woocommerce-funnels'),
            ),
        )
    );	
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_scripts' );

/**
 * Register admin scripts and styles.
 */
function admin_scripts() {
	wp_enqueue_style( 'woocommerce-funnels-admin', plugins_url( '/assets/css/admin.css', __FILE__ ), false, '1.0.0' );
}

function plugin_dir() {
	return plugin_dir_path( __FILE__ );
}


function plugin_url( $path ) {
	return plugins_url( $path, __FILE__ );
}