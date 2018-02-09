<?php
/**
 * Template part for displaying product linked page thumbs/previews
 *
 * @package woocommerce-funnels
 */

?>

<div id="post-<?php the_ID(); ?>" <?php post_class( array( 'post-thumb' ) ); ?>>

	<?php if ( has_post_thumbnail() ) : ?>
	<a href="<?php the_permalink(); ?>" rel="bookmark" ><?php the_post_thumbnail( 'thumbnail' ); ?></a>
	<?php endif; ?>

	<div class="entry-header">
		<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
		<div class="entry-excerpt"><?php the_excerpt(); ?></div>
		<a class="readmore button" href="<?php the_permalink(); ?>" >
		<?php
		echo apply_filters( 'purchased_product_page_readmore_label', get_post_meta( get_the_ID(), 'purchased_product_readmore_label', true ) ?: __( 'View the product content', 'woocommerce-funnels' ) );
		?>
		</a>
	</div><!-- .entry-header -->

</div><!-- #post-## -->
