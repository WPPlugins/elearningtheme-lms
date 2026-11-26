<?php
/**
 * Block templates for courses, lessons, quizzes and the catalog. A theme can
 * override any of them with its own single-elt_lesson.html and so on, and
 * users can edit them in the Site Editor.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Templates {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		$templates = array(
			'single-elt_course'  => array( __( 'Single course', 'elearningtheme-lms' ), __( 'A course page with the enrol box and the curriculum.', 'elearningtheme-lms' ) ),
			'single-elt_lesson'  => array( __( 'Single lesson', 'elearningtheme-lms' ), __( 'A lesson with the course contents, video, text and navigation.', 'elearningtheme-lms' ) ),
			'single-elt_quiz'    => array( __( 'Single quiz', 'elearningtheme-lms' ), __( 'A quiz with the course contents and the questions.', 'elearningtheme-lms' ) ),
			'archive-elt_course' => array( __( 'Course catalog', 'elearningtheme-lms' ), __( 'All courses, with categories and search.', 'elearningtheme-lms' ) ),
		);
		foreach ( $templates as $slug => $t ) {
			$file = ELTLMS_DIR . '/templates/' . $slug . '.html';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			register_block_template(
				'elearningtheme-lms//' . $slug,
				array(
					'title'       => $t[0],
					'description' => $t[1],
					'content'     => (string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				)
			);
		}
	}
}
