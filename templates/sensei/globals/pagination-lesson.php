<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Pagination - Lesson
 *
 * @author 		Automattic
 * @package 	Sensei
 * @category    Templates
 * @version     1.9.0
 */

global $post;

$course_id = Sensei()->lesson->get_course_id( $post->ID );
$modules_and_lessons = sensei_get_modules_and_lessons( $course_id );

if ( count( $modules_and_lessons > 0 ) ) {
	$found = false;

	foreach ( $modules_and_lessons as $item ) {
		if ( $found ) {
			$next = $item;
			break;
		}

		if ( is_tax( Sensei()->modules->taxonomy ) ) {	// Module
			if ( $item->term_id == get_queried_object()->term_id ) {
				$found = true;
			} else {
				$previous = $item;
			}
		} else if ( $item->ID == $post->ID ) {	// Lesson or quiz
			$found = true;
		} else {
			$previous = $item;
		}
	}
}


// Output HTML
if ( isset( $next ) ) { ?>
	<nav id="post-entries" class="post-entries fix">
		<div class="nav-next fr">
		    <a href="<?php echo esc_url( get_permalink( $next ) ); ?>" rel="prev">
		        <?php echo get_the_post_thumbnail($next, 'thumbnail'); ?>
    			<?php if( Sensei_Utils::user_completed_lesson( $post->ID, get_current_user_id() ) ) : ?>
                    <span class="motivation notice"><?php _e( 'Excellent, keep it up!', 'woocommerce-funnels' ); ?></span>
    			<?php  endif; ?>	        
		        <span class="goto"><?php _e( 'View next lesson', 'woocommerce-funnels' ); ?></span>    			
		        <span class="next-title"><?php echo get_the_title( $next ); ?></span>
		        <span class="meta-nav"></span>
		    </a>
		</div>
    </nav><!-- #post-entries -->
<?php } ?>