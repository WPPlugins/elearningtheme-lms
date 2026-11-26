<?php
/**
 * Post types: courses, lessons and quizzes.
 *
 * A course stores its curriculum as one ordered list of sections, each with
 * the IDs of its lessons and quizzes. One meta read gives the whole outline.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Content {

	const COURSE = 'elt_course';
	const LESSON = 'elt_lesson';
	const QUIZ   = 'elt_quiz';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		foreach ( array( self::LESSON, self::QUIZ ) as $type ) {
			add_filter( 'rest_prepare_' . $type, array( __CLASS__, 'protect_rest' ), 10, 2 );
		}
		add_filter( 'the_excerpt_embed', array( __CLASS__, 'protect_embed' ) );
	}

	/**
	 * Locked lessons must not leak through the REST API.
	 */
	public static function protect_rest( $response, $post ) {
		if ( ! ELTLMS_Data::can_view( get_current_user_id(), $post->ID ) ) {
			$data = $response->get_data();
			foreach ( array( 'content', 'excerpt' ) as $k ) {
				if ( isset( $data[ $k ] ) ) {
					$data[ $k ] = array( 'rendered' => '', 'protected' => true );
				}
			}
			$response->set_data( $data );
		}
		return $response;
	}

	/**
	 * Nor through oEmbed cards.
	 */
	public static function protect_embed( $excerpt ) {
		$id = get_the_ID();
		return ( ELTLMS_Data::is_item( $id ) && ! ELTLMS_Data::can_view( get_current_user_id(), $id ) ) ? '' : $excerpt;
	}

	public static function register() {
		$common = array(
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-welcome-learn-more',
		);

		register_post_type(
			self::COURSE,
			$common + array(
				'labels'       => array(
					'name'          => __( 'Courses', 'elearningtheme-lms' ),
					'singular_name' => __( 'Course', 'elearningtheme-lms' ),
					'add_new_item'  => __( 'Add new course', 'elearningtheme-lms' ),
					'edit_item'     => __( 'Edit course', 'elearningtheme-lms' ),
					'all_items'     => __( 'All courses', 'elearningtheme-lms' ),
					'menu_name'     => __( 'Courses', 'elearningtheme-lms' ),
					'not_found'     => __( 'No courses yet.', 'elearningtheme-lms' ),
				),
				'has_archive'  => 'courses',
				'rewrite'      => array( 'slug' => 'courses', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ),
				'show_in_menu' => true,
			)
		);

		register_post_type(
			self::LESSON,
			$common + array(
				'labels'       => array(
					'name'          => __( 'Lessons', 'elearningtheme-lms' ),
					'singular_name' => __( 'Lesson', 'elearningtheme-lms' ),
					'add_new_item'  => __( 'Add new lesson', 'elearningtheme-lms' ),
					'edit_item'     => __( 'Edit lesson', 'elearningtheme-lms' ),
					'all_items'     => __( 'Lessons', 'elearningtheme-lms' ),
				),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'rewrite'             => array( 'slug' => 'lesson', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_menu' => 'edit.php?post_type=' . self::COURSE,
			)
		);

		register_post_type(
			self::QUIZ,
			$common + array(
				'labels'       => array(
					'name'          => __( 'Quizzes', 'elearningtheme-lms' ),
					'singular_name' => __( 'Quiz', 'elearningtheme-lms' ),
					'add_new_item'  => __( 'Add new quiz', 'elearningtheme-lms' ),
					'edit_item'     => __( 'Edit quiz', 'elearningtheme-lms' ),
					'all_items'     => __( 'Quizzes', 'elearningtheme-lms' ),
				),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'rewrite'             => array( 'slug' => 'quiz', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_menu' => 'edit.php?post_type=' . self::COURSE,
			)
		);

		register_taxonomy(
			'elt_course_category',
			self::COURSE,
			array(
				'labels'            => array(
					'name'          => __( 'Course categories', 'elearningtheme-lms' ),
					'singular_name' => __( 'Course category', 'elearningtheme-lms' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'course-category', 'with_front' => false ),
			)
		);

		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};
		$meta = array(
			self::COURSE => array(
				'_eltlms_curriculum' => 'string',
				'_eltlms_level'      => 'string',
				'_eltlms_duration'   => 'string',
				'_eltlms_learn'      => 'string',
			),
			self::LESSON => array(
				'_eltlms_course'   => 'integer',
				'_eltlms_type'     => 'string',
				'_eltlms_video'    => 'string',
				'_eltlms_duration' => 'string',
				'_eltlms_preview'  => 'boolean',
			),
			self::QUIZ   => array(
				'_eltlms_course'    => 'integer',
				'_eltlms_questions' => 'string',
				'_eltlms_pass'      => 'integer',
			),
		);
		foreach ( $meta as $type => $keys ) {
			foreach ( $keys as $key => $kind ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'          => $kind,
						'single'        => true,
						// Quiz questions hold the answers: never exposed over REST.
						'show_in_rest'  => '_eltlms_questions' !== $key,
						'auth_callback' => $auth,
					)
				);
			}
		}
	}
}
