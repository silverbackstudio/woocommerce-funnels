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

class Sensei {

	public $integration;

	public $show_user_courses_page = true;
	public $user_courses_page_description;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct( $integration ) {

		$this->integration = $integration;

		$integration->form_fields['show_user_courses_page'] = array(
			'title'       => __( 'Show user courses page', 'woocommerce-funnels' ),
			'type'        => 'checkbox',
			'description' => __( 'Show user courses page in user Account', 'woocommerce-funnels' ),
			'desc_tip'    => true,
			'default'     => true,
		);

		$integration->form_fields['mycourses_menu_label'] = array(
			'title'       => __( 'Courses Menu Label', 'woocommerce-funnels' ),
			'type'        => 'text',
			'description' => __( 'The label for Courses in account menu', 'woocommerce-funnels' ),
			'desc_tip'    => true,
			'default'     => __( 'My Courses', 'woocommerce-funnels' ),
		);

		$integration->form_fields['user_courses_page_description'] = array(
			'title'       => __( 'User courses page description', 'woocommerce-funnels' ),
			'type'        => 'textarea',
			'description' => __( 'The description shown after the title in courses page', 'woocommerce-funnels' ),
			'desc_tip'    => true,
			'default'     => '',
		);

		$this->show_user_courses_page  = $this->integration->get_option( 'show_user_courses_page' );
		$this->user_courses_page_description  = $this->integration->get_option( 'user_courses_page_description' );

		$this->hooks();
	}


	public function hooks() {
		
		add_action( 'init', array( $this, 'sensei_endpoints' ), 99 );

		// Hack to edit the shortcode tabs url.
		add_action( 'sensei_loop_course_before', array( $this, 'sensei_replace_mycourses_url_activate' ), 9 );
		add_action( 'sensei_loop_course_after', array( $this, 'sensei_replace_mycourses_url_deactivate' ) );

		add_action( 'woocommerce_account_mycourses_endpoint', array( $this, 'myaccount_user_courses_page_content' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'sensei_menu_items' ), 90 );

		add_filter( 'sensei_show_lesson_numbers', '__return_true' );

		if ( ! Sensei()->settings->get( 'messages_disable' ) ) {
			add_action( 'woocommerce_account_messages_endpoint', array( $this, 'sensei_messages_page_content' ) );
		}

		add_filter( 'post_type_archive_link', array( $this, 'sensei_post_types_archive_link' ), 10, 2 );

		add_action( 'sensei_before_main_content', array( $this, 'sensei_before_main_content' ) );
		add_action( 'sensei_after_main_content', array( $this, 'sensei_after_main_content' ) );
		add_action( 'sensei_course_content_after', array( $this, 'sensei_course_button' ), 20 );

		remove_action( 'sensei_single_course_content_inside_before', array( 'Sensei_Course', 'the_title' ), 10 );
		add_action( 'sensei_single_course_content_inside_before', array( 'Sensei_Course', 'the_title' ), 25 );

		remove_action( 'sensei_single_course_content_inside_before', array( Sensei()->course, 'the_progress_statement' ), 15 );
		remove_action( 'sensei_single_course_content_inside_before', array( Sensei()->course, 'the_progress_meter' ), 16 );

		add_filter( 'sensei_the_module_status_html', '__return_empty_string' );

		remove_action( 'sensei_pagination', array( Sensei()->frontend, 'sensei_breadcrumb' ), 80 );

		add_action( 'sensei_before_main_content', array( Sensei()->frontend, 'sensei_breadcrumb' ), 10 );

		remove_action( 'sensei_single_lesson_content_inside_before', array( 'Sensei_Lesson', 'the_lesson_image' ), 17 );
		add_action( 'sensei_single_lesson_content_inside_before', array( $this, 'sensei_single_lesson_heading' ), 1 );

		add_filter( 'post_class', array( $this, 'sensei_item_classes' ), 10, 3 );
		add_action( 'sensei_content_lesson_after', array( $this, 'sensei_lesson_button' ) );
		add_action( 'sensei_course_content_inside_before', array( Sensei()->course, 'the_progress_meter' ), 11 );

		add_action( 'sensei_single_course_content_inside_before', array( $this, 'sensei_course_lessons_start' ), 9 );

		add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_course_lessons_end' ), 5 );
		add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_course_modules_start' ), 5 );
		add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_course_modules_end' ), 8 );
		add_action( 'sensei_single_course_content_inside_after', array( $this, 'sensei_single_course_meta' ), 4 );

		add_filter( 'sensei_results_links', '__return_empty_string' );

		add_action( 'sensei_my_courses_before', array( $this, 'remove_meter_from_course_loop' ) );

		add_filter( 'sensei_locate_template', array( $this, 'plugin_template' ), 10, 3 );

		remove_action( 'sensei_single_lesson_content_inside_after', array( 'Sensei_Lesson', 'footer_quiz_call_to_action' ) );
		add_action( 'sensei_single_lesson_content_inside_after', array( 'Sensei_Lesson', 'footer_quiz_call_to_action' ), 20 );

		add_filter( 'sensei_single_lesson_content_inside_after', array( $this, 'lesson_quiz_button_intro' ), 15 );

		add_filter( 'sensei_register_post_type_course', array( $this, 'register_post_type_course_attr' ) );

		remove_action( 'sensei_content_lesson_inside_before', array( 'Sensei_Lesson', 'the_lesson_thumbnail' ), 30 );
		add_action( 'sensei_content_lesson_inside_before', array( 'Sensei_Lesson', 'the_lesson_thumbnail' ), 15 );

		add_filter( 'comment_form_defaults', array( $this, 'comment_form_defaults' ) );
	}

	public function remove_meter_from_course_loop() {
		global $wp_filter;

		foreach ( $wp_filter['sensei_course_content_inside_after']->callbacks[10] as $handle => $hook ) {
			if ( strpos( $handle, 'attach_course_progress' ) !== false ) {
				unset( $wp_filter['sensei_course_content_inside_after']->callbacks[10][ $handle ] );
			}
		}

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

	public function sensei_replace_mycourses_url_activate() {
		add_filter( 'page_link', array( $this, 'sensei_replace_mycourses_url_apply' ) );
	}

	public function sensei_replace_mycourses_url_apply( $url ) {
		$this->sensei_replace_mycourses_url_deactivate();
		return wc_get_endpoint_url( 'mycourses' );
	}

	public function sensei_replace_mycourses_url_deactivate() {
		remove_filter( 'page_link', array( $this, 'sensei_replace_mycourses_url_apply' ) );
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
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_navigation' );
		?>
		<div class="woocommerce-MyAccount-content sensei-account">
		<?php
	}

	function sensei_after_main_content() {
	?>
		</div><!-- .woocommerce-MyAccount-content -->
		<?php
	}

	function sensei_course_button() {
	?>
		<a class="button" href="<?php the_permalink(); ?>"><?php _e( 'View Course', 'woocommerce-funnels' ); ?></a>
		<?php
	}

	public function plugin_template( $located, $template_name, $template_path ) {

		$override_located = wc_locate_template( $template_name, $template_path, plugin_dir() . 'templates/sensei/' );

		if ( $override_located && file_exists( $override_located ) ) {
			return $override_located;
		}

		return $located;
	}

	/**
	 * Courses page in WC Account content
	 */
	public function myaccount_user_courses_page_content() {
	?>
	<section id="dashboard-courses" >
		<header class="privatearea-header"> 
			<h2><?php esc_html_e( 'Courses', 'woocommerce-funnels' ); ?></h2>
			<?php if ( ! empty( $this->user_courses_page_description ) ) :	?>
			<p class="subtitle"><?php echo do_shortcode($this->user_courses_page_description); ?></p>
			<?php endif; ?>
		</header>		
		
		<div class="section-content post-list courses-list">
			<?php echo do_shortcode( '[sensei_user_courses]' ); ?>
		</div>
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

	public function sensei_single_lesson_heading() {
		global $post;

		if ( ! get_post_meta( $post->ID, '_lesson_video_embed', true ) ) {
			add_action( 'sensei_single_lesson_content_inside_before', array( 'Sensei_Lesson', 'the_lesson_image' ), 10 );
		}
	}

	public function sensei_item_classes( $classes, $class, $post_id ) {

		if ( 'lesson' !== get_post_type( $post_id ) ) {
			return $classes;
		}

		if ( Sensei_Utils::user_completed_lesson( $post_id, get_current_user_id() ) ) {
			$classes[] = 'lesson-completed';
			$classes[] = 'user-completed';
		}

		if ( ! Sensei_Lesson::is_prerequisite_complete( $post_id, get_current_user_id() ) ) {
			$classes[] = 'lesson-locked';
			$classes[] = 'user-locked';
		}

		return $classes;
	}

	public function sensei_lesson_button( $lesson_id ) {
		if ( Sensei_Utils::user_completed_lesson( $lesson_id, get_current_user_id() ) ) :
		?>
			<a class="button to-single completed" href="<?php the_permalink(); ?>"><?php _e( 'Completed', 'woocommerce-funnels' ); ?></a>
		<?php elseif ( ! Sensei_Lesson::is_prerequisite_complete( $lesson_id, get_current_user_id() ) ) : ?>
			<a class="button to-single locked" href="<?php the_permalink(); ?>"><?php _e( 'Lesson Locked', 'woocommerce-funnels' ); ?></a>
		<?php else : ?>
			<a class="button to-single" href="<?php the_permalink(); ?>"><?php _e( 'Go to Lesson', 'woocommerce-funnels' ); ?></a>
		<?php
		endif;
	}

	public function sensei_course_modules_start() {
		echo '<section id="course-modules" class="content-wrapper">';
	}

	public function sensei_course_modules_end() {
		echo '</section><!-- #course-modules -->';
	}

	public function sensei_course_lessons_start() {
		echo '<section id="course-intro" class="content-wrapper">';
	}

	public function sensei_course_lessons_end() {
		echo '</section><!-- #course-intro -->';
	}

	public function sensei_single_course_meta( $course_id ) {

		echo '<div class="course-meta">';

		Sensei()->course->the_progress_statement( $course_id );
		Sensei()->course->the_progress_meter( $course_id );

		$this->sensei_teacher();

		echo '</div><!-- #course-meta --!>';
	}

	public function sensei_teacher() {
	?>
		<div class="author vcard">
			<?php echo get_avatar( get_the_author_meta( 'ID' ), 64 ); ?>		
			<span class="role"><?php _e( 'Teacher', 'woocommerce-funnels' ); ?>:</span>
			<span class="fn n" ><?php echo esc_html( get_the_author() ); ?></span>
		</div>
	<?php
	}

	public function lesson_quiz_button_intro( $lesson_id ) {

		$user_id  = get_current_user_id();

		if ( ! sensei_can_user_view_lesson( $lesson_id, $user_id ) ) {
			return;
		}

		$lesson_prerequisite       = (int) get_post_meta( $lesson_id, '_lesson_prerequisite', true );
		$lesson_course_id          = (int) get_post_meta( $lesson_id, '_lesson_course', true );
		$quiz_id                   = Sensei()->lesson->lesson_quizzes( $lesson_id );
		$has_user_completed_lesson = Sensei_Utils::user_completed_lesson( intval( $lesson_id ), $user_id );
		$show_actions              = is_user_logged_in() ? true : false;

		if ( intval( $lesson_prerequisite ) > 0 ) {
			// If the user hasn't completed the prereq then hide the current actions
			$show_actions = Sensei_Utils::user_completed_lesson( $lesson_prerequisite, $user_id );
		}

		if ( $show_actions && $quiz_id && Sensei()->access_settings() ) {

			if ( Sensei_Lesson::lesson_quiz_has_questions( $lesson_id ) ) {
				?>
				<div id="quiz-intro" >
					<h2><?php _e( 'Take the quiz to complete the lesson', 'woocommerce-funnels' ); ?></h2>
					<p><?php _e( 'To check the skills you have acquired and complete the lesson, click on the button and immediately access the quiz.', 'woocommerce-funnels' ); ?></p>
				</div>
				<?php
			}
		}
	}

	public function register_post_type_course_attr( $args ) {

		$args['has_archive'] = false;

		return $args;
	}

	public function comment_form_defaults( $defaults ) {

		if ( 'lesson' === get_post_type() ) {
			$defaults['label_submit'] = _x( 'Leave a comment', 'lesson comment submit label', 'woocommerce-funnels' );
		}

		if ( 'course' === get_post_type() ) {
			$defaults['label_submit'] = _x( 'Leave a comment', 'course comment submit label', 'woocommerce-funnels' );
		}

		return $defaults;
	}

}
