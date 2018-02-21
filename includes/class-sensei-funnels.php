<?php

namespace Svbk\WP\Plugins\WooCommerce\Funnels;

use Svbk\WP\Helpers;
use Sensei_Utils;
use Sensei_Lesson;
use Wp_Query;


/**
 * Sensei Funnels Integration.
 *
 * @package  Sensei_Funnels
 * @category Integration
 * @author   Brando Meniconi
 */

if ( ! class_exists( __NAMESPACE__ . '\\Sensei' ) ) :

	class Sensei_Funnels {

		public $integration;

		/**
		 * Init and hook in the integration.
		 */
		public function __construct( $integration ) {
			$this->integration = $integration;
			
			$integration->form_fields['disable_sensei_styles'] = array(
				'title'       => __( 'Disable Sensei Styles', 'woocommerce-funnels' ),
				'type'        => 'checkbox',
				'description' => __( 'Disable Sensei CSS styles', 'woocommerce-funnels' ),
				'desc_tip'    => true,
				'default'     => false,
			);
			
			$this->sensei_hooks();
		}


		public function sensei_hooks() {

			add_action( 'init', array( $this, 'sensei_endpoints' ), 99 );

			// Hack to edit the shortcode tabs url.
			add_action( 'sensei_loop_course_before', array( $this, 'sensei_replace_mycourses_url_activate' ), 9 );
			add_action( 'woocommerce_account_mycourses_endpoint', array( $this, 'myaccount_user_courses_page_content' ) );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'sensei_menu_items' ), 90 );

			add_filter( 'sensei_show_lesson_numbers', '__return_true' );

			if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
				add_action( 'woocommerce_account_messages_endpoint', array( $this, 'sensei_messages_page_content' ) );
			}

			add_filter( 'post_type_archive_link', array( $this, 'sensei_post_types_archive_link' ), 10, 2 );
			
			if( $this->integration->disable_sensei_styles ) {
				add_filter( 'sensei_disable_styles', '__return_true' );
			}
			
			add_action( 'sensei_before_main_content',  array( $this, 'sensei_before_main_content') );
			add_action( 'sensei_after_main_content',   array( $this, 'sensei_after_main_content') );
			add_action( 'sensei_course_content_after', array( $this, 'sensei_course_button') , 20 );
			
			remove_action( 'sensei_single_course_content_inside_before', array( 'Sensei_Course', 'the_title'), 10 );
			add_action	 ( 'sensei_single_course_content_inside_before', array( 'Sensei_Course', 'the_title'), 25 );
			
			remove_action( 'sensei_single_course_content_inside_before', array( Sensei()->course, 'the_progress_statement' ), 15 );
			remove_action( 'sensei_single_course_content_inside_before', array( Sensei()->course, 'the_progress_meter' ), 16 );			

			add_filter('sensei_the_module_status_html', '__return_empty_string');
			
			remove_action( 'sensei_pagination', array( Sensei()->frontend, 'sensei_breadcrumb' ), 80 );
			
			add_action( 'sensei_before_main_content', array( Sensei()->frontend, 'sensei_breadcrumb' ), 10 );

			remove_action('sensei_single_lesson_content_inside_before', array( 'Sensei_Lesson', 'the_lesson_image' ), 17 );
			add_action	 ('sensei_single_lesson_content_inside_before', array( $this, 'sensei_single_lesson_heading' ), 1);
			
			add_filter('post_class', array( $this, 'sensei_item_classes'), 10, 3);
			add_action( 'sensei_content_lesson_after', array( $this, 'sensei_lesson_button') );
			add_action( 'sensei_course_content_inside_before' , array( Sensei()->course, 'the_progress_meter' ), 11 );
			
			add_action( 'sensei_single_course_content_inside_before',  array( $this, 'sensei_course_lessons_start') , 9);
			
			add_action( 'sensei_single_course_content_inside_after',  array( $this, 'sensei_course_lessons_end'), 5);
			add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_course_modules_start'), 5);
			add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_course_modules_end'), 8);	
			add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_single_course_meta'), 4);			
			
		}


		public function sensei_menu_items( $items ) {

			$new_items = array();

			$new_items['mycourses'] = __( 'My Courses', 'woocommerce-funnels' );

			if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
				$new_items['messages'] = __( 'Messages', 'woocommerce-funnels' );
			}

			$items = Helpers\Lists\Utils::keyInsert( $items, $new_items, 'dashboard' );

			return $items;
		}


		public function sensei_endpoints() {

			add_rewrite_endpoint( 'mycourses', EP_PAGES );

			if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
				add_rewrite_endpoint( 'messages', EP_PAGES );
			}

		}

		public function sensei_replace_mycourses_url_activate( $url ) {
			add_filter( 'page_link', array($this, 'sensei_replace_mycourses_url_apply') );
		}

		public function sensei_replace_mycourses_url_apply( $url ) {
			remove_filter( 'page_link', array($this, 'sensei_replace_mycourses_url_apply') );
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
		
		function sensei_before_main_content() {
			wc_print_notices();
			/**
			 * My Account navigation.
			 * @since 2.6.0
			 */
			do_action( 'woocommerce_account_navigation' ); ?>
		    <div class="woocommerce-MyAccount-content sensei-account">
			<?php
		}
		
		function sensei_after_main_content() { ?>
			</div><!-- .woocommerce-MyAccount-content -->
			<?php
		}		
		
		function sensei_course_button() { ?>
			<a class="button" href="<?php the_permalink() ?>"><?php  _e('View Course', 'woocommerce-funnels');  ?></a>
			<?php 
		}		

		/**
		 * Courses page in WC Account content
		 */
		public function myaccount_user_courses_page_content() {
		?>
		<section id="dashboard-courses">
			<h2><?php esc_html_e( 'Courses', 'woocommerce-funnels' ); ?></h2>
			<?php echo do_shortcode( '[sensei_courses]' ); ?>
		</section>    
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

		function sensei_single_lesson_heading() { 
			global $post;
		
			if( !get_post_meta( $post->ID, '_lesson_video_embed', true ) ) {
				add_action('sensei_single_lesson_content_inside_before', array( 'Sensei_Lesson', 'the_lesson_image' ), 10 );
			}
		}

		function sensei_item_classes( $classes, $class, $post_id ){
			
			if( 'lesson' !== get_post_type( $post_id ) ) {
				return $classes;
			}
		
			if( Sensei_Utils::user_completed_lesson( $post_id, get_current_user_id() ) ) {
				$classes[] = 'lesson-completed';
			}
		
			if( !Sensei_Lesson::is_prerequisite_complete( $post_id, get_current_user_id() ) ) {
				$classes[] = 'lesson-locked';
			}	
			
			return $classes;
		}
		
		function sensei_lesson_button( $lesson_id ){ 
			if( Sensei_Utils::user_completed_lesson( $lesson_id, get_current_user_id() ) ) : ?>
				<a class="button to-single completed" href="<?php the_permalink(); ?>"><?php _e('Completed', 'woocommerce-funnels'); ?></a>
			<?php elseif( !Sensei_Lesson::is_prerequisite_complete( $lesson_id, get_current_user_id() ) ) : ?>
				<a class="button to-single locked" href="<?php the_permalink(); ?>"><?php _e('Lesson Locked', 'woocommerce-funnels'); ?></a>
			<?php else : ?>
				<a class="button to-single" href="<?php the_permalink(); ?>"><?php _e('Go to Lesson', 'woocommerce-funnels'); ?></a>
			<?php endif;		
		} 
		
		function sensei_course_modules_start(){
			echo '<section id="course-modules" class="content-wrapper">';
		}
		
		function sensei_course_modules_end(){
			echo '</section><!-- #course-modules -->';
		}	
		
		function sensei_course_lessons_start(){
			echo '<section id="course-intro" class="content-wrapper">';
		}
		
		function sensei_course_lessons_end(){
			echo '</section><!-- #course-intro -->';
		}
		
		function sensei_single_course_meta( $course_id ){
			
			echo '<div class="course-meta">';
			
			Sensei()->course->the_progress_statement($course_id);
			Sensei()->course->the_progress_meter($course_id);
			
			$this->sensei_teacher();
			
			echo '</div><!-- #course-meta --!>';
		}
		
		function sensei_teacher(){ ?>
			<div class="author vcard">
				<?php echo get_avatar( get_the_author_meta( 'ID' ), 64 ); ?>		
				<span class="role"><?php _e('Teacher', 'woocommerce-funnels') ?>:</span>
				<span class="fn n" ><?php echo esc_html( get_the_author() ) ?></span>
			</div>
		<?php }		
				

}
endif;
