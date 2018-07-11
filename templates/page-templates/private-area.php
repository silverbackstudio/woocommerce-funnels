<?php
/**
 * Template Name: Private Area
 *
 * This template has the following changes:
 * HEADER: no menu, no top header
 * FOOTER: no logo, no fixed bar, company info
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package woocommerce-funnels
 */


get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php
			while ( have_posts() ) :
				the_post();

				wc_print_notices();
				/**
				 * My Account navigation.
				 *
				 * @since 2.6.0
				 */
				do_action( 'woocommerce_account_navigation' );
				?>
				
				<div class="woocommerce-MyAccount-content content-wrapper">
					<?php
						/**
						 * My Account content.
						 *
						 * @since 2.6.0
						 */
						get_template_part( 'template-parts/content', 'page' );
					?>
				</div>

				<?php
			endwhile; // End of the loop.
			?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar('privatearea');
get_footer();
