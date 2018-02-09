<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @author      WooThemes
 * @package     WooCommerce/Templates
 * @version     2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

do_action( 'woocommerce_before_account_dashboard' );

?>

<div class="content-wrapper">
	<header class="entry-header">
		<h1><?php _e('Dashboard', 'woocommerce-funnels'); ?></h1>
	</header><!-- .entry-header -->
	
	<?php
	$integrations =  WC()->integrations->get_integrations();
	
	if( isset( $integrations['silverback-funnels'] ) && $integrations['silverback-funnels']->dashboard_content_page )  {
		
		$dashboard_query = new WP_Query(
			array(
				'post_type' => 'page',
				'p' => $integrations['silverback-funnels']->dashboard_content_page
			)
		);
		
		while( $dashboard_query->have_posts() ) : $dashboard_query->the_post(); ?>
			<?php the_post_thumbnail( 'content-full' ); ?>
			<div class="entry-content"><?php the_content(); ?></div>
		<?php
		endwhile;
		
		wp_reset_postdata();
	}
	
	?>
	
	
	<?php
		/**
		 * My Account dashboard.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_dashboard' );
	
		/**
		 * Deprecated woocommerce_before_my_account action.
		 *
		 * @deprecated 2.6.0
		 */
		do_action( 'woocommerce_before_my_account' );
	
		/**
		 * Deprecated woocommerce_after_my_account action.
		 *
		 * @deprecated 2.6.0
		 */
		do_action( 'woocommerce_after_my_account' );
	
	/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
	?>
</div>