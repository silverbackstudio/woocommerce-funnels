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

<div id="myAccount-dashboard">

	<?php
	$integrations = WC()->integrations->get_integrations();

	if ( isset( $integrations['silverback-funnels'] ) && $integrations['silverback-funnels']->dashboard_content_page ) {

		$dashboard_query = new WP_Query(
			array(
				'post_type' => 'page',
				'p' => $integrations['silverback-funnels']->dashboard_content_page,
			)
		);

		$orders_url = wc_get_endpoint_url( 'orders' );
		$edit_address = wc_get_endpoint_url( 'edit-address' ) . '#edit-address';
		$edit_account = wc_get_endpoint_url( 'edit-account' );
		$affiliate = wc_get_endpoint_url( 'affiliate' );
		$mycourses = wc_get_endpoint_url( 'mycourses' );

		while ( $dashboard_query->have_posts() ) :
			$dashboard_query->the_post();
		?>
		<header class="privatearea-header">
			<?php the_post_thumbnail( 'content-full' ); ?>
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			<div class="dashboard__intro-text">
			<?php
				$introtext_args = 	array(
					esc_url( $orders_url ),
					esc_url( $edit_address ),
					esc_url( $edit_account ),
					esc_url( $affiliate ),
					esc_url( $mycourses )
				);
		
				$template = isset( $integrations['silverback-funnels'] ) ? $integrations['silverback-funnels']->dashboard_intro_text : '' ;

				echo apply_filters_ref_array( 'woocommerce_account_dashboard_introtext', array_merge( array( vsprintf( $template, $introtext_args ) ), $introtext_args ) );
			?>
			</div>
		</header><!-- .privatearea-header -->			

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
		?>

		<div class="entry-content"><?php the_content(); ?></div>

		<?php
		endwhile;

		wp_reset_postdata();
	}

	?>
	
	
	<?php
		/**
		 * Deprecated woocommerce_after_my_account action.
		 *
		 * @deprecated 2.6.0
		 */
		do_action( 'woocommerce_after_my_account' );

	/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
	?>
</div>
