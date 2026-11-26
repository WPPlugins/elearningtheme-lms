<?php
/**
 * Admin screens in native WordPress style: the course builder, lesson settings
 * and the quiz editor (meta boxes), plus an enrolment count in the course list.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'classic_for_structure' ), 10, 2 );
		add_action( 'save_post_' . ELTLMS_Content::COURSE, array( __CLASS__, 'save_course' ), 10, 2 );
		add_action( 'save_post_' . ELTLMS_Content::LESSON, array( __CLASS__, 'save_lesson' ), 10, 2 );
		add_action( 'save_post_' . ELTLMS_Content::QUIZ, array( __CLASS__, 'save_quiz' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_' . ELTLMS_Content::COURSE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . ELTLMS_Content::COURSE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Courses and quizzes are mostly structure (curriculum, questions), so they use
	 * the classic screen where those boxes come first. Lessons keep the block editor.
	 */
	public static function classic_for_structure( $use, $post_type ) {
		return in_array( $post_type, array( ELTLMS_Content::COURSE, ELTLMS_Content::QUIZ ), true ) ? false : $use;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		/**
		 * Post types whose edit screens load the builder and question editor (Pro adds question banks).
		 */
		$types = (array) apply_filters( 'eltlms_admin_post_types', array( ELTLMS_Content::COURSE, ELTLMS_Content::LESSON, ELTLMS_Content::QUIZ ) );
		if ( ! $screen || ! in_array( $screen->post_type, $types, true ) || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'elearningtheme-lms-admin', plugins_url( 'assets/admin.css', ELTLMS_FILE ), array(), ELTLMS_VERSION );
		wp_enqueue_script( 'elearningtheme-lms-admin', plugins_url( 'assets/admin.js', ELTLMS_FILE ), array( 'jquery', 'jquery-ui-sortable' ), ELTLMS_VERSION, true );
		wp_localize_script(
			'elearningtheme-lms-admin',
			'eltlmsAdmin',
			array(
				'removeSection' => __( 'Remove this section? Its lessons stay in WordPress, only the link to this course is removed.', 'elearningtheme-lms' ),
				'already'       => __( 'This item is already in the curriculum.', 'elearningtheme-lms' ),
				/* translators: %d: section number. */
				'section'       => __( 'Section %d', 'elearningtheme-lms' ),
			)
		);
	}

	public static function boxes() {
		add_meta_box( 'eltlms-builder', __( 'Curriculum', 'elearningtheme-lms' ), array( __CLASS__, 'builder' ), ELTLMS_Content::COURSE, 'normal', 'high' );
		add_meta_box( 'eltlms-course', __( 'Course details', 'elearningtheme-lms' ), array( __CLASS__, 'course_box' ), ELTLMS_Content::COURSE, 'side' );
		add_meta_box( 'eltlms-lesson', __( 'Lesson settings', 'elearningtheme-lms' ), array( __CLASS__, 'lesson_box' ), ELTLMS_Content::LESSON, 'side' );
		add_meta_box( 'eltlms-quiz', __( 'Questions', 'elearningtheme-lms' ), array( __CLASS__, 'quiz_box' ), ELTLMS_Content::QUIZ, 'normal', 'high' );
	}

	/* Course builder ------------------------------------------------------ */

	private static function item_row( $id ) {
		$type    = get_post_type( $id ) === ELTLMS_Content::QUIZ ? __( 'Quiz', 'elearningtheme-lms' ) : __( 'Lesson', 'elearningtheme-lms' );
		$preview = get_post_type( $id ) === ELTLMS_Content::LESSON;
		return '<li class="eltlms-b-item" data-id="' . (int) $id . '"><span class="dashicons dashicons-menu eltlms-b-handle" aria-hidden="true"></span>'
			. '<span class="eltlms-b-type">' . esc_html( $type ) . '</span> <a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ? get_the_title( $id ) : __( '(no title)', 'elearningtheme-lms' ) ) . '</a>'
			. ( 'publish' !== get_post_status( $id ) ? ' <em>(' . esc_html__( 'draft', 'elearningtheme-lms' ) . ')</em>' : '' )
			. ( $preview ? ' <label class="eltlms-b-preview"><input type="checkbox" value="' . (int) $id . '" name="eltlms_preview[]"' . checked( ELTLMS_Data::is_preview( $id ), true, false ) . '> ' . esc_html__( 'Free preview', 'elearningtheme-lms' ) . '</label>' : '' )
			. ' <button type="button" class="button-link eltlms-b-remove">' . esc_html__( 'Remove', 'elearningtheme-lms' ) . '</button></li>';
	}

	public static function builder( $post ) {
		wp_nonce_field( 'eltlms_course_' . $post->ID, 'eltlms_course_nonce' );
		$raw      = json_decode( (string) get_post_meta( $post->ID, '_eltlms_curriculum', true ), true );
		$sections = is_array( $raw ) && $raw ? $raw : array( array( 'title' => __( 'Section 1', 'elearningtheme-lms' ), 'items' => array() ) );
		$options  = get_posts(
			array(
				'post_type'      => array( ELTLMS_Content::LESSON, ELTLMS_Content::QUIZ ),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		echo '<p class="description">' . esc_html__( 'Drag lessons and quizzes to reorder them. Sections are numbered automatically.', 'elearningtheme-lms' ) . '</p>';
		echo '<div id="eltlms-sections">';
		foreach ( $sections as $s ) {
			echo '<div class="eltlms-b-section"><p><span class="dashicons dashicons-menu eltlms-b-shandle" aria-hidden="true"></span> <label><span class="screen-reader-text">' . esc_html__( 'Section title', 'elearningtheme-lms' ) . '</span><input type="text" class="regular-text eltlms-b-title" value="' . esc_attr( $s['title'] ?? '' ) . '"></label> <button type="button" class="button-link eltlms-b-sremove">' . esc_html__( 'Remove section', 'elearningtheme-lms' ) . '</button></p><ul class="eltlms-b-items">';
			foreach ( (array) ( $s['items'] ?? array() ) as $id ) {
				if ( get_post( (int) $id ) ) {
					echo self::item_row( (int) $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in item_row().
				}
			}
			echo '</ul></div>';
		}
		echo '</div>';
		echo '<p class="eltlms-b-add"><label for="eltlms-b-pick">' . esc_html__( 'Add to the last section:', 'elearningtheme-lms' ) . '</label> <select id="eltlms-b-pick"><option value="">' . esc_html__( 'Choose a lesson or quiz', 'elearningtheme-lms' ) . '</option>';
		foreach ( $options as $o ) {
			$label = ( ELTLMS_Content::QUIZ === $o->post_type ? __( 'Quiz', 'elearningtheme-lms' ) : __( 'Lesson', 'elearningtheme-lms' ) ) . ': ' . ( $o->post_title ? $o->post_title : __( '(no title)', 'elearningtheme-lms' ) );
			printf( '<option value="%1$d" data-row="%2$s">%3$s</option>', (int) $o->ID, esc_attr( self::item_row( $o->ID ) ), esc_html( $label ) );
		}
		echo '</select> <button type="button" class="button" id="eltlms-b-additem">' . esc_html__( 'Add', 'elearningtheme-lms' ) . '</button> <button type="button" class="button" id="eltlms-b-addsection">' . esc_html__( 'Add section', 'elearningtheme-lms' ) . '</button>';
		echo ' <a class="button-link" href="' . esc_url( admin_url( 'post-new.php?post_type=' . ELTLMS_Content::LESSON ) ) . '">' . esc_html__( 'Create a lesson', 'elearningtheme-lms' ) . '</a> · <a class="button-link" href="' . esc_url( admin_url( 'post-new.php?post_type=' . ELTLMS_Content::QUIZ ) ) . '">' . esc_html__( 'Create a quiz', 'elearningtheme-lms' ) . '</a></p>';
		echo '<input type="hidden" name="eltlms_curriculum" id="eltlms-curriculum" value="' . esc_attr( wp_json_encode( $sections ) ) . '">';
		echo '<input type="hidden" name="eltlms_builder" value="1">';
	}

	public static function course_box( $post ) {
		$level = (string) get_post_meta( $post->ID, '_eltlms_level', true );
		$dur   = (string) get_post_meta( $post->ID, '_eltlms_duration', true );
		$learn = (string) get_post_meta( $post->ID, '_eltlms_learn', true );
		echo '<p><label for="eltlms-level">' . esc_html__( 'Level', 'elearningtheme-lms' ) . '</label><br><input type="text" id="eltlms-level" name="eltlms_level" class="widefat" value="' . esc_attr( $level ) . '" placeholder="' . esc_attr__( 'Beginner', 'elearningtheme-lms' ) . '"></p>';
		echo '<p><label for="eltlms-duration">' . esc_html__( 'Duration', 'elearningtheme-lms' ) . '</label><br><input type="text" id="eltlms-duration" name="eltlms_duration" class="widefat" value="' . esc_attr( $dur ) . '" placeholder="2 h 40 min"></p>';
		echo '<p><label for="eltlms-learn">' . esc_html__( 'What you’ll learn (one per line)', 'elearningtheme-lms' ) . '</label><br><textarea id="eltlms-learn" name="eltlms_learn" class="widefat" rows="6">' . esc_textarea( $learn ) . '</textarea></p>';
	}

	public static function save_course( $post_id, $post ) {
		if ( ! isset( $_POST['eltlms_course_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eltlms_course_nonce'] ) ), 'eltlms_course_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		foreach ( array( 'level', 'duration' ) as $f ) {
			if ( isset( $_POST[ 'eltlms_' . $f ] ) ) {
				update_post_meta( $post_id, '_eltlms_' . $f, sanitize_text_field( wp_unslash( $_POST[ 'eltlms_' . $f ] ) ) );
			}
		}
		if ( isset( $_POST['eltlms_learn'] ) ) {
			update_post_meta( $post_id, '_eltlms_learn', sanitize_textarea_field( wp_unslash( $_POST['eltlms_learn'] ) ) );
		}
		if ( empty( $_POST['eltlms_builder'] ) || ! isset( $_POST['eltlms_curriculum'] ) ) {
			return;
		}
		$raw      = json_decode( wp_unslash( $_POST['eltlms_curriculum'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and sanitised below.
		$sections = array();
		$all      = array();
		foreach ( (array) $raw as $s ) {
			$items = array();
			foreach ( (array) ( $s['items'] ?? array() ) as $id ) {
				$id = absint( $id );
				if ( $id && ELTLMS_Data::is_item( $id ) && ! isset( $all[ $id ] ) && current_user_can( 'edit_post', $id ) ) {
					$items[]    = $id;
					$all[ $id ] = true;
				}
			}
			$sections[] = array(
				'title' => sanitize_text_field( (string) ( $s['title'] ?? '' ) ),
				'items' => $items,
			);
		}
		$old = ELTLMS_Data::items( $post_id );
		update_post_meta( $post_id, '_eltlms_curriculum', wp_slash( wp_json_encode( $sections ) ) );
		foreach ( $old as $gone ) {
			if ( ! isset( $all[ $gone ] ) && ELTLMS_Data::course_of( $gone ) === (int) $post_id ) {
				delete_post_meta( $gone, '_eltlms_course' );
			}
		}

		$preview = isset( $_POST['eltlms_preview'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['eltlms_preview'] ) ) : array();
		foreach ( array_keys( $all ) as $id ) {
			update_post_meta( $id, '_eltlms_course', $post_id );
			if ( get_post_type( $id ) === ELTLMS_Content::LESSON ) {
				update_post_meta( $id, '_eltlms_preview', in_array( $id, $preview, true ) );
			}
		}
	}

	/* Lesson -------------------------------------------------------------- */

	public static function lesson_box( $post ) {
		wp_nonce_field( 'eltlms_lesson_' . $post->ID, 'eltlms_lesson_nonce' );
		$type   = get_post_meta( $post->ID, '_eltlms_type', true ) === 'text' ? 'text' : 'video';
		$course = ELTLMS_Data::course_of( $post->ID );
		echo '<p><strong>' . esc_html__( 'Course', 'elearningtheme-lms' ) . ':</strong> ' . ( $course ? '<a href="' . esc_url( get_edit_post_link( $course ) ) . '">' . esc_html( get_the_title( $course ) ) . '</a>' : esc_html__( 'Not in a course yet. Add it in the course’s curriculum.', 'elearningtheme-lms' ) ) . '</p>';
		echo '<fieldset><legend>' . esc_html__( 'Type', 'elearningtheme-lms' ) . '</legend><label><input type="radio" name="eltlms_type" value="video"' . checked( $type, 'video', false ) . '> ' . esc_html__( 'Video', 'elearningtheme-lms' ) . '</label><br><label><input type="radio" name="eltlms_type" value="text"' . checked( $type, 'text', false ) . '> ' . esc_html__( 'Text', 'elearningtheme-lms' ) . '</label></fieldset>';
		echo '<p><label for="eltlms-video">' . esc_html__( 'Video URL (YouTube, Vimeo or an .mp4 file)', 'elearningtheme-lms' ) . '</label><br><input type="url" id="eltlms-video" name="eltlms_video" class="widefat" value="' . esc_attr( (string) get_post_meta( $post->ID, '_eltlms_video', true ) ) . '"></p>';
		echo '<p><label for="eltlms-ldur">' . esc_html__( 'Duration', 'elearningtheme-lms' ) . '</label><br><input type="text" id="eltlms-ldur" name="eltlms_duration" class="widefat" value="' . esc_attr( (string) get_post_meta( $post->ID, '_eltlms_duration', true ) ) . '" placeholder="11:20"></p>';
		echo '<p><label><input type="checkbox" name="eltlms_preview_one" value="1"' . checked( ELTLMS_Data::is_preview( $post->ID ), true, false ) . '> ' . esc_html__( 'Free preview: anyone can open this lesson', 'elearningtheme-lms' ) . '</label></p>';
	}

	public static function save_lesson( $post_id ) {
		if ( ! isset( $_POST['eltlms_lesson_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eltlms_lesson_nonce'] ) ), 'eltlms_lesson_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_eltlms_type', ( isset( $_POST['eltlms_type'] ) && 'text' === $_POST['eltlms_type'] ) ? 'text' : 'video' );
		update_post_meta( $post_id, '_eltlms_video', isset( $_POST['eltlms_video'] ) ? esc_url_raw( wp_unslash( $_POST['eltlms_video'] ) ) : '' );
		update_post_meta( $post_id, '_eltlms_duration', isset( $_POST['eltlms_duration'] ) ? sanitize_text_field( wp_unslash( $_POST['eltlms_duration'] ) ) : '' );
		update_post_meta( $post_id, '_eltlms_preview', ! empty( $_POST['eltlms_preview_one'] ) );
	}

	/* Quiz ---------------------------------------------------------------- */

	private static function question_fields( $i, $q ) {
		$opts  = array_pad( (array) ( $q['options'] ?? array() ), 4, '' );
		$types = ELTLMS_Data::question_types();
		$type  = (string) ( $q['type'] ?? 'choice' );
		$sel   = '';
		if ( count( $types ) > 1 ) {
			$sel = '<p><label>' . esc_html__( 'Type', 'elearningtheme-lms' ) . ' <select class="eltlms-q-type" name="eltlms_q[' . esc_attr( (string) $i ) . '][t]">';
			foreach ( $types as $k => $label ) {
				$sel .= '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$sel .= '</select></label></p>';
		}
		$out = '<fieldset class="eltlms-q is-' . esc_attr( $type ) . '" data-i="' . esc_attr( (string) $i ) . '"><legend>' . esc_html__( 'Question', 'elearningtheme-lms' ) . ' <span class="eltlms-q-n"></span></legend>' . $sel
			. '<p><label><span class="screen-reader-text">' . esc_html__( 'Question', 'elearningtheme-lms' ) . '</span><input type="text" class="large-text" name="eltlms_q[' . esc_attr( (string) $i ) . '][q]" value="' . esc_attr( $q['q'] ?? '' ) . '" placeholder="' . esc_attr__( 'Write the question', 'elearningtheme-lms' ) . '"></label></p>'
			. '<div class="eltlms-q-choice"><p class="description">' . esc_html__( 'Answers: mark the correct one. Leave unused lines empty. For true/false, use two answers.', 'elearningtheme-lms' ) . '</p><ol class="eltlms-opts">';
		foreach ( $opts as $oi => $o ) {
			$out .= '<li><label><input type="radio" name="eltlms_q[' . esc_attr( (string) $i ) . '][c]" value="' . (int) $oi . '"' . checked( (int) ( $q['correct'] ?? 0 ), $oi, false ) . '> <span class="screen-reader-text">' . esc_html__( 'Correct answer', 'elearningtheme-lms' ) . '</span></label> '
				. '<input type="text" class="regular-text" name="eltlms_q[' . esc_attr( (string) $i ) . '][o][]" value="' . esc_attr( $o ) . '"></li>';
		}
		return $out . '</ol></div><p><button type="button" class="button-link eltlms-q-remove">' . esc_html__( 'Remove question', 'elearningtheme-lms' ) . '</button></p></fieldset>';
	}

	/**
	 * The question editor, reusable for any post that stores questions (Pro: question banks).
	 */
	public static function question_editor( $post_id ) {
		$qs = ELTLMS_Data::questions( $post_id );
		wp_nonce_field( 'eltlms_questions_' . $post_id, 'eltlms_questions_nonce' );
		echo '<div id="eltlms-questions">';
		foreach ( $qs ? $qs : array( array() ) as $i => $q ) {
			echo self::question_fields( $i, $q ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in question_fields().
		}
		echo '</div><template id="eltlms-q-template">' . self::question_fields( '__i__', array() ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p><button type="button" class="button" id="eltlms-q-add">' . esc_html__( 'Add question', 'elearningtheme-lms' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'The correct answers are kept on the server and never sent to the student’s browser.', 'elearningtheme-lms' ) . '</p>';
	}

	public static function quiz_box( $post ) {
		wp_nonce_field( 'eltlms_quiz_' . $post->ID, 'eltlms_quiz_nonce' );
		$course = ELTLMS_Data::course_of( $post->ID );
		echo '<p><strong>' . esc_html__( 'Course', 'elearningtheme-lms' ) . ':</strong> ' . ( $course ? '<a href="' . esc_url( get_edit_post_link( $course ) ) . '">' . esc_html( get_the_title( $course ) ) . '</a>' : esc_html__( 'Not in a course yet. Add it in the course’s curriculum.', 'elearningtheme-lms' ) ) . '</p>';
		echo '<p><label for="eltlms-pass">' . esc_html__( 'Pass mark (%)', 'elearningtheme-lms' ) . '</label> <input type="number" min="1" max="100" id="eltlms-pass" name="eltlms_pass" value="' . (int) ELTLMS_Data::pass_mark( $post->ID ) . '" class="small-text"></p>';
		self::question_editor( $post->ID );
	}

	public static function save_quiz( $post_id ) {
		if ( ! isset( $_POST['eltlms_quiz_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eltlms_quiz_nonce'] ) ), 'eltlms_quiz_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_eltlms_pass', isset( $_POST['eltlms_pass'] ) ? max( 1, min( 100, absint( $_POST['eltlms_pass'] ) ) ) : 80 );
		self::save_questions( $post_id );
	}

	/**
	 * Save the question editor's fields (a quiz, or a question bank in Pro).
	 */
	public static function save_questions( $post_id ) {
		$nonce = isset( $_POST['eltlms_questions_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['eltlms_questions_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eltlms_questions_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$out   = array();
		$types = ELTLMS_Data::question_types();
		$raw   = isset( $_POST['eltlms_q'] ) && is_array( $_POST['eltlms_q'] ) ? wp_unslash( $_POST['eltlms_q'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the caller verified the nonce; sanitised below.
		foreach ( $raw as $q ) {
			$text = sanitize_text_field( (string) ( $q['q'] ?? '' ) );
			$type = sanitize_key( (string) ( $q['t'] ?? 'choice' ) );
			$type = isset( $types[ $type ] ) ? $type : 'choice';
			if ( '' === $text ) {
				continue;
			}
			if ( 'choice' !== $type ) {
				$out[] = array( 'type' => $type, 'q' => $text );
				continue;
			}
			$opts = array();
			$map  = array();
			foreach ( (array) ( $q['o'] ?? array() ) as $oi => $o ) {
				$o = sanitize_text_field( (string) $o );
				if ( '' !== $o ) {
					$map[ (int) $oi ] = count( $opts );
					$opts[]           = $o;
				}
			}
			if ( count( $opts ) < 2 ) {
				continue;
			}
			$c     = (int) ( $q['c'] ?? 0 );
			$out[] = array(
				'type'    => 'choice',
				'q'       => $text,
				'options' => $opts,
				'correct' => $map[ $c ] ?? 0,
			);
		}
		update_post_meta( $post_id, '_eltlms_questions', wp_slash( wp_json_encode( $out ) ) );
	}

	/* Course list column -------------------------------------------------- */

	public static function columns( $cols ) {
		$cols['eltlms_students'] = __( 'Students', 'elearningtheme-lms' );
		return $cols;
	}

	public static function column( $col, $post_id ) {
		global $wpdb;
		if ( 'eltlms_students' !== $col ) {
			return;
		}
		$t = ELTLMS_Install::tables();
		$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d', $t['enrolments'], $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; results are cached per request; admin list only.
		echo current_user_can( 'edit_post', $post_id ) ? '<a href="' . esc_url( ELTLMS_Students::url( $post_id ) ) . '">' . (int) $n . '</a>' : (int) $n; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- int and escaped URL.
	}
}
