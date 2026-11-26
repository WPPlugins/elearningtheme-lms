<?php
/**
 * Dynamic blocks. Each is rendered on the server; the editor shows a live
 * preview through ServerSideRender. Lesson and quiz content is gated here.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Blocks {

	const NS = 'elearningtheme-lms/';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'render_block_core/post-content', array( __CLASS__, 'gate_content' ), 10, 2 );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ) );
	}

	public static function category( $cats ) {
		array_unshift( $cats, array( 'slug' => 'elearningtheme-lms', 'title' => __( 'Courses', 'elearningtheme-lms' ) ) );
		return $cats;
	}

	/**
	 * name => [ title, render callback, attributes ].
	 */
	private static function blocks() {
		return array(
			'course-header' => array( __( 'Course header', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::course_header( ELTLMS_Render::course_id_for_context() ); } ),
			'enrol'         => array( __( 'Enrol box', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::enrol_box( ELTLMS_Render::course_id_for_context() ); } ),
			'learn'         => array( __( 'What you’ll learn', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::learn_list( ELTLMS_Render::course_id_for_context() ); } ),
			'curriculum'    => array( __( 'Curriculum', 'elearningtheme-lms' ), static function () {
				$cid = ELTLMS_Render::course_id_for_context();
				return '<section class="eltlms-course-curriculum" id="curriculum"><h2 class="wp-block-heading">' . esc_html__( 'Curriculum', 'elearningtheme-lms' ) . '</h2>' . ELTLMS_Render::curriculum( $cid ) . '</section>';
			} ),
			'lesson-sidebar' => array( __( 'Course contents sidebar', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::sidebar( get_the_ID() ); } ),
			'lesson-header'  => array( __( 'Lesson header', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::item_header( get_the_ID() ); } ),
			'lesson-video'   => array( __( 'Lesson video', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::video( get_the_ID() ); } ),
			'lesson-nav'     => array( __( 'Lesson navigation', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::item_nav( get_the_ID() ); } ),
			'quiz'           => array( __( 'Quiz', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::quiz( get_the_ID() ); } ),
			'dashboard'      => array( __( 'Student dashboard', 'elearningtheme-lms' ), static function () { return ELTLMS_Render::dashboard(); } ),
			'language-switcher' => array( __( 'Language switcher', 'elearningtheme-lms' ), static function () { return ELTLMS_Lang::switcher(); } ),
			'course-list'    => array(
				__( 'Course catalog', 'elearningtheme-lms' ),
				static function ( $attrs ) { return ELTLMS_Render::course_list( $attrs ); },
				array(
					'perPage'     => array( 'type' => 'number', 'default' => 12 ),
					'showFilters' => array( 'type' => 'boolean', 'default' => true ),
					'author'      => array( 'type' => 'number', 'default' => 0 ),
				),
			),
		);
	}

	public static function register() {
		wp_register_style( 'elearningtheme-lms', plugins_url( 'assets/lms.css', ELTLMS_FILE ), array(), ELTLMS_VERSION );
		wp_register_script(
			'elearningtheme-lms-editor',
			plugins_url( 'assets/editor.js', ELTLMS_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			ELTLMS_VERSION,
			true
		);
		wp_set_script_translations( 'elearningtheme-lms-editor', 'elearningtheme-lms', ELTLMS_DIR . '/languages' );
		$list = array();
		foreach ( self::blocks() as $name => $b ) {
			$cb = $b[1];
			register_block_type(
				self::NS . $name,
				array(
					'api_version'     => 3,
					'title'           => $b[0],
					'category'        => 'elearningtheme-lms',
					'attributes'      => $b[2] ?? array(),
					'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
					'editor_script'   => 'elearningtheme-lms-editor',
					'style'           => 'elearningtheme-lms',
					'render_callback' => static function ( $attrs ) use ( $cb, $name ) {
						$html = ELTLMS_Render::kses( $cb( $attrs ) );
						return '' === $html ? '' : '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
					},
				)
			);
			$list[] = array( 'name' => self::NS . $name, 'title' => $b[0], 'attributes' => array_keys( $b[2] ?? array() ) );
		}
		wp_add_inline_script( 'elearningtheme-lms-editor', 'window.eltlmsBlocks = ' . wp_json_encode( $list ) . ';', 'before' );
	}

	/**
	 * Lessons and quizzes show their content only to students who may open them.
	 */
	public static function gate_content( $html, $block ) {
		if ( is_admin() || ! is_singular() ) {
			return $html;
		}
		$id = get_the_ID();
		if ( ! ELTLMS_Data::is_item( $id ) ) {
			return $html;
		}
		if ( ELTLMS_Data::can_view( get_current_user_id(), $id ) ) {
			return $html;
		}
		wp_enqueue_style( 'elearningtheme-lms' );
		return '<div class="wp-block-post-content">' . ELTLMS_Render::kses( ELTLMS_Render::locked( $id ) ) . '</div>';
	}
}
