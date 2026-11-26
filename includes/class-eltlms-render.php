<?php
/**
 * HTML for the LMS blocks. Server-rendered, no JavaScript. Class names follow
 * the theme (elt-*) where the theme already styles them, eltlms-* otherwise.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Render {

	/* Icons (inline SVG, the brief's small set) --------------------------- */

	/**
	 * The HTML the LMS screens may contain: the post content tags, plus forms (enrol,
	 * quizzes, checkout buttons from add-ons), inline SVG icons, video and embeds.
	 */
	public static function allowed_html() {
		static $allowed = null;
		if ( null !== $allowed ) {
			return $allowed;
		}
		$global = array( 'class' => true, 'id' => true, 'style' => true, 'role' => true, 'title' => true, 'lang' => true, 'hidden' => true, 'tabindex' => true, 'dir' => true, 'aria-*' => true, 'data-*' => true );
		$extra  = array(
			'form'     => array( 'action' => true, 'method' => true, 'enctype' => true, 'novalidate' => true, 'autocomplete' => true, 'name' => true, 'target' => true ),
			'input'    => array( 'type' => true, 'name' => true, 'value' => true, 'checked' => true, 'disabled' => true, 'required' => true, 'readonly' => true, 'placeholder' => true, 'min' => true, 'max' => true, 'step' => true, 'maxlength' => true, 'size' => true, 'autocomplete' => true, 'inputmode' => true, 'accept' => true, 'form' => true, 'pattern' => true ),
			'button'   => array( 'type' => true, 'name' => true, 'value' => true, 'disabled' => true, 'form' => true ),
			'select'   => array( 'name' => true, 'required' => true, 'disabled' => true, 'multiple' => true, 'autocomplete' => true ),
			'option'   => array( 'value' => true, 'selected' => true, 'disabled' => true ),
			'optgroup' => array( 'label' => true, 'disabled' => true ),
			'textarea' => array( 'name' => true, 'rows' => true, 'cols' => true, 'required' => true, 'disabled' => true, 'readonly' => true, 'placeholder' => true, 'maxlength' => true ),
			'label'    => array( 'for' => true ),
			'fieldset' => array( 'disabled' => true ),
			'legend'   => array(),
			'output'   => array( 'for' => true, 'name' => true ),
			'progress' => array( 'value' => true, 'max' => true ),
			'meter'    => array( 'value' => true, 'min' => true, 'max' => true, 'low' => true, 'high' => true, 'optimum' => true ),
			'time'     => array( 'datetime' => true ),
			'details'  => array( 'open' => true, 'name' => true ),
			'summary'  => array(),
			'template' => array(),
			'nav'      => array(),
			'section'  => array(),
			'article'  => array(),
			'aside'    => array(),
			'header'   => array(),
			'footer'   => array(),
			'video'    => array( 'src' => true, 'controls' => true, 'preload' => true, 'poster' => true, 'playsinline' => true, 'width' => true, 'height' => true, 'muted' => true, 'loop' => true, 'autoplay' => true ),
			'source'   => array( 'src' => true, 'type' => true ),
			'track'    => array( 'src' => true, 'kind' => true, 'srclang' => true, 'label' => true, 'default' => true ),
			'iframe'   => array( 'src' => true, 'width' => true, 'height' => true, 'frameborder' => true, 'allow' => true, 'allowfullscreen' => true, 'referrerpolicy' => true, 'loading' => true, 'sandbox' => true, 'name' => true ),
			'svg'      => array( 'xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'focusable' => true, 'shape-rendering' => true, 'preserveaspectratio' => true ),
			'g'        => array( 'fill' => true, 'stroke' => true, 'transform' => true ),
			'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'transform' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true ),
			'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
			'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		);
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( $extra as $tag => $attrs ) {
			$allowed[ $tag ] = array_merge( $allowed[ $tag ] ?? array(), $attrs );
		}
		foreach ( $allowed as $tag => $attrs ) {
			$allowed[ $tag ] = array_merge( is_array( $attrs ) ? $attrs : array(), $global );
		}
		/**
		 * The HTML allowed in the output of the LMS blocks. Add-ons that print other tags add them here.
		 */
		$allowed = (array) apply_filters( 'eltlms_allowed_html', $allowed );
		return $allowed;
	}

	/** Escape a screen of LMS HTML on its way out. */
	public static function kses( $html ) {
		return wp_kses( (string) $html, self::allowed_html() );
	}

	public static function icon( $name, $size = 16 ) {
		$paths = array(
			'play'     => '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
			'text'     => '<path d="M5 6h14M5 10h14M5 14h10M5 18h8"/>',
			'quiz'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7"/><path d="M12 17h.01"/>',
			'check'    => '<path d="M5 12l5 5L20 7"/>',
			'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
			'lock'     => '<rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'chevron'  => '<path d="M6 9l6 6 6-6"/>',
			'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="eltlms-icon eltlms-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $name ),
			(int) $size,
			$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup above.
		);
	}

	private static function item_kind( $id ) {
		if ( get_post_type( $id ) === ELTLMS_Content::QUIZ ) {
			return 'quiz';
		}
		return 'text' === get_post_meta( $id, '_eltlms_type', true ) ? 'text' : 'play';
	}

	private static function item_meta( $id ) {
		if ( get_post_type( $id ) === ELTLMS_Content::QUIZ ) {
			$n = ELTLMS_Data::question_count( $id );
			/* translators: %d: number of questions. */
			return sprintf( _n( '%d question', '%d questions', $n, 'elearningtheme-lms' ), $n );
		}
		return (string) get_post_meta( $id, '_eltlms_duration', true );
	}

	public static function progress_bar( $pct, $label = '' ) {
		$pct = max( 0, min( 100, (int) $pct ) );
		return sprintf(
			'<div class="eltlms-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d"%2$s><span style="width:%1$d%%"></span></div>',
			$pct,
			$label ? ' aria-label="' . esc_attr( $label ) . '"' : ''
		);
	}

	/* Course ------------------------------------------------------------- */

	public static function course_id_for_context( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( get_post_type( $post_id ) === ELTLMS_Content::COURSE ) {
			return (int) $post_id;
		}
		return ELTLMS_Data::course_of( $post_id );
	}

	public static function course_header( $course_id ) {
		$cats  = get_the_terms( $course_id, 'elt_course_category' );
		$crumb = '<a href="' . esc_url( get_post_type_archive_link( ELTLMS_Content::COURSE ) ) . '">' . esc_html__( 'Courses', 'elearningtheme-lms' ) . '</a>';
		if ( $cats && ! is_wp_error( $cats ) ) {
			$crumb .= ' / ' . esc_html( $cats[0]->name );
		}
		$excerpt = has_excerpt( $course_id ) ? get_the_excerpt( $course_id ) : '';
		$items   = ELTLMS_Data::items( $course_id );
		$quizzes = 0;
		foreach ( $items as $id ) {
			if ( get_post_type( $id ) === ELTLMS_Content::QUIZ ) {
				++$quizzes;
			}
		}
		$count = count( $items );
		$facts = array(
			__( 'Teacher', 'elearningtheme-lms' ) => '__TEACHER__',
			/* translators: 1: number of lessons, 2: number of quizzes. */
			__( 'Lessons', 'elearningtheme-lms' ) => $quizzes ? sprintf( __( '%1$d, including %2$d quizzes', 'elearningtheme-lms' ), $count, $quizzes ) : (string) $count,
		);
		$dur = (string) get_post_meta( $course_id, '_eltlms_duration', true );
		if ( '' !== $dur ) {
			$facts[ __( 'Duration', 'elearningtheme-lms' ) ] = $dur;
		}
		$lvl = (string) get_post_meta( $course_id, '_eltlms_level', true );
		if ( '' !== $lvl ) {
			$facts[ __( 'Level', 'elearningtheme-lms' ) ] = $lvl;
		}
		$author  = (int) get_post_field( 'post_author', $course_id );
		$teacher = esc_html( get_the_author_meta( 'display_name', $author ) );
		/**
		 * Link for the teacher's name on the course page (Pro: the teacher profile). Return a URL or ''.
		 */
		$t_url = (string) apply_filters( 'eltlms_teacher_url', '', $author );
		if ( '' !== $t_url ) {
			$teacher = '<a href="' . esc_url( $t_url ) . '">' . $teacher . '</a>';
		}
		$f = '';
		foreach ( $facts as $k => $v ) {
			$f .= '<li><span>' . esc_html( $k ) . '</span> ' . ( '__TEACHER__' === $v ? $teacher : esc_html( $v ) ) . '</li>';
		}
		return '<div class="eltlms-course-header">'
			. '<p class="is-style-elt-eyebrow eltlms-crumb">' . $crumb . '</p>'
			. '<h1 class="wp-block-heading eltlms-title">' . esc_html( get_the_title( $course_id ) ) . '</h1>'
			. ( $excerpt ? '<p class="is-style-elt-lead">' . esc_html( $excerpt ) . '</p>' : '' )
			. '<ul class="elt-facts">' . $f . '</ul></div>';
	}

	/** The course (or lesson) picture at the top of a card, or '' without one. Decorative: the title is next to it. */
	private static function cover( $post_id, $fallback = 0 ) {
		$id = has_post_thumbnail( $post_id ) ? $post_id : ( $fallback && has_post_thumbnail( $fallback ) ? $fallback : 0 );
		return $id ? '<figure class="eltlms-cover">' . get_the_post_thumbnail( $id, 'medium_large', array( 'alt' => '', 'loading' => 'eager' ) ) . '</figure>' : '';
	}

	public static function enrol_box( $course_id ) {
		$user = get_current_user_id();
		$out  = '<div class="is-style-elt-card eltlms-enrol">';
		$out .= self::cover( $course_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['completed'] ) && $user && ( $e = ELTLMS_Data::enrolment( $user, $course_id ) ) && $e->completed_at ) {
			$out .= '<div class="eltlms-done" role="status">' . self::icon( 'check', 28 )
				. '<p class="eltlms-done__title">' . esc_html__( 'Course completed.', 'elearningtheme-lms' ) . '</p>'
				/* translators: %s: course title. */
				. '<p>' . esc_html( sprintf( __( 'You finished every lesson of %s. Well done.', 'elearningtheme-lms' ), get_the_title( $course_id ) ) ) . '</p>'
				/**
				 * Extra links in the course-completed box, e.g. the certificate (Pro). HTML, escaped by the filter.
				 */
				. (string) apply_filters( 'eltlms_completed_links', '', $user, $course_id )
				. '<p><a href="' . esc_url( get_post_type_archive_link( ELTLMS_Content::COURSE ) ) . '">' . esc_html__( 'Find your next course', 'elearningtheme-lms' ) . '</a></p></div>';
		}

		if ( $user && ELTLMS_Data::is_enrolled( $user, $course_id ) ) {
			list( $done, $total, $pct ) = ELTLMS_Data::progress( $user, $course_id );
			$next                       = ELTLMS_Data::next_item( $user, $course_id );
			$finished                   = $total && $done >= $total;
			/* translators: 1: completed items, 2: all items, 3: percent. */
			$out .= '<p class="eltlms-enrol__status">' . esc_html( sprintf( __( '%1$d of %2$d complete · %3$d%%', 'elearningtheme-lms' ), $done, $total, $pct ) ) . '</p>'
				. self::progress_bar( $pct, __( 'Course progress', 'elearningtheme-lms' ) );
			if ( $next ) {
				$out .= '<a class="wp-element-button eltlms-btn" href="' . esc_url( get_permalink( $next ) ) . '">'
					. esc_html( $finished ? __( 'Review the course', 'elearningtheme-lms' ) : ( $done ? __( 'Continue where you left off', 'elearningtheme-lms' ) : __( 'Start the first lesson', 'elearningtheme-lms' ) ) ) . '</a>';
			}
		} else {
			$out .= '<p class="eltlms-enrol__price">' . esc_html( apply_filters( 'eltlms_course_price_label', __( 'Free', 'elearningtheme-lms' ), $course_id ) ) . '</p>';
			/**
			 * Replace the enrol button, e.g. with "Buy course" for a paid course (Pro). Return HTML, or '' to keep the default.
			 */
			$cta = (string) apply_filters( 'eltlms_enrol_cta', '', $course_id, $user );
			if ( '' !== $cta ) {
				$out .= $cta;
			} elseif ( $user ) {
				$out .= '<form method="post" action="' . esc_url( get_permalink( $course_id ) ) . '">' . ELTLMS_Actions::fields( 'enrol', $course_id )
					. '<button type="submit" class="wp-element-button eltlms-btn">' . esc_html__( 'Enrol for free', 'elearningtheme-lms' ) . '</button></form>';
			} else {
				$out .= '<a class="wp-element-button eltlms-btn" href="' . esc_url( wp_login_url( get_permalink( $course_id ) ) ) . '">' . esc_html__( 'Log in to enrol', 'elearningtheme-lms' ) . '</a>';
				if ( get_option( 'users_can_register' ) ) {
					$out .= '<p class="eltlms-small">' . sprintf(
						/* translators: %s: link to create an account. */
						esc_html__( 'New here? %s', 'elearningtheme-lms' ),
						'<a href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Create a free account', 'elearningtheme-lms' ) . '</a>'
					) . '</p>';
				}
			}
			foreach ( ELTLMS_Data::items( $course_id ) as $id ) {
				if ( ELTLMS_Data::is_preview( $id ) ) {
					$out .= '<p class="eltlms-small"><a href="' . esc_url( get_permalink( $id ) ) . '">' . self::icon( 'play', 12 ) . ' ' . esc_html__( 'Watch a free preview lesson', 'elearningtheme-lms' ) . '</a></p>';
					break;
				}
			}
		}
		return $out . '</div>';
	}

	public static function learn_list( $course_id ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $course_id, '_eltlms_learn', true ) ) ) );
		if ( ! $lines ) {
			return '';
		}
		$li = '';
		foreach ( $lines as $l ) {
			$li .= '<li>' . esc_html( $l ) . '</li>';
		}
		return '<section class="eltlms-learn"><h2 class="wp-block-heading">' . esc_html__( 'What you’ll learn', 'elearningtheme-lms' ) . '</h2><ul class="elt-ticks">' . $li . '</ul></section>';
	}

	/**
	 * The curriculum list, on the course page or in the lesson sidebar.
	 *
	 * @param int  $course_id Course.
	 * @param int  $current   Current item, highlighted.
	 * @param bool $compact   Sidebar version.
	 */
	public static function curriculum( $course_id, $current = 0, $compact = false ) {
		$user     = get_current_user_id();
		$enrolled = $user && ELTLMS_Data::is_enrolled( $user, $course_id );
		$done     = ELTLMS_Data::completed( $user, $course_id );
		$sections = ELTLMS_Data::curriculum( $course_id );
		if ( ! $sections || ! ELTLMS_Data::items( $course_id ) ) {
			return '<p class="eltlms-empty">' . esc_html__( 'Lessons are on their way.', 'elearningtheme-lms' ) . '</p>';
		}
		$out = '';
		foreach ( $sections as $si => $s ) {
			$sdone = 0;
			foreach ( $s['items'] as $id ) {
				if ( isset( $done[ $id ] ) ) {
					++$sdone;
				}
			}
			$out .= '<div class="eltlms-section"><p class="elt-curriculum__section"><span class="elt-num">' . esc_html( sprintf( '%02d', $si + 1 ) ) . '</span> <strong>' . esc_html( $s['title'] ) . '</strong>'
				. ( $enrolled ? ' <span class="elt-curriculum__meta">' . esc_html( $sdone . '/' . count( $s['items'] ) ) . '</span>' : '' ) . '</p><ol class="elt-curriculum">';
			foreach ( $s['items'] as $ii => $id ) {
				$state = isset( $done[ $id ] ) ? 'done' : ( ELTLMS_Data::can_view( $user, $id ) ? 'open' : 'locked' );
				$cls   = 'eltlms-item is-' . $state . ( (int) $current === $id ? ' is-current' : '' );
				$title = esc_html( get_the_title( $id ) );
				$link  = 'locked' === $state ? '<span class="elt-curriculum__title">' . $title . '</span>'
					: '<a class="elt-curriculum__title" href="' . esc_url( get_permalink( $id ) ) . '"' . ( (int) $current === $id ? ' aria-current="page"' : '' ) . '>' . $title . '</a>';
				$badge = '';
				if ( ! $enrolled && ELTLMS_Data::is_preview( $id ) ) {
					$badge = ' <span class="elt-badge">' . esc_html__( 'Preview', 'elearningtheme-lms' ) . '</span>';
				}
				$status = '';
				if ( 'done' === $state ) {
					$status = '<span class="eltlms-state eltlms-state--done">' . self::icon( 'check', 14 ) . '<span class="screen-reader-text">' . esc_html__( 'Completed', 'elearningtheme-lms' ) . '</span></span>';
				} elseif ( 'locked' === $state ) {
					/**
					 * Short reason shown instead of "Locked", e.g. "Unlocks on 12 March". Pro uses it for drip and prerequisites.
					 */
					$reason = (string) apply_filters( 'eltlms_lock_reason', '', $id, $user );
					$status = '<span class="eltlms-state eltlms-state--locked">' . self::icon( 'lock', 14 ) . '<span class="' . ( $compact && '' === $reason ? 'screen-reader-text' : 'eltlms-state__word' ) . '">' . esc_html( '' !== $reason ? $reason : __( 'Locked', 'elearningtheme-lms' ) ) . '</span></span>';
				} else {
					$status = '<span class="eltlms-state eltlms-state--open">' . self::icon( self::item_kind( $id ), 14 ) . '</span>';
				}
				$out .= '<li class="' . esc_attr( $cls ) . '"><span class="elt-num">' . esc_html( ( $si + 1 ) . '.' . ( $ii + 1 ) ) . '</span>'
					. '<span class="eltlms-item__body">' . $link . $badge . '</span>'
					. '<span class="elt-curriculum__meta">' . $status . ' ' . esc_html( self::item_meta( $id ) ) . '</span></li>';
			}
			$out .= '</ol></div>';
		}
		return '<div class="eltlms-curriculum' . ( $compact ? ' is-compact' : '' ) . '">' . $out . '</div>';
	}

	/* Lesson and quiz pages ---------------------------------------------- */

	public static function sidebar( $item_id ) {
		$course_id = ELTLMS_Data::course_of( $item_id );
		if ( ! $course_id ) {
			return '';
		}
		$user  = get_current_user_id();
		$head  = '<p class="is-style-elt-eyebrow">' . esc_html__( 'Course', 'elearningtheme-lms' ) . '</p><p class="eltlms-side__course"><a href="' . esc_url( get_permalink( $course_id ) ) . '">' . esc_html( get_the_title( $course_id ) ) . '</a></p>';
		$state = '';
		if ( $user && ELTLMS_Data::is_enrolled( $user, $course_id ) ) {
			list( $done, $total, $pct ) = ELTLMS_Data::progress( $user, $course_id );
			/* translators: 1: completed items, 2: all items, 3: percent. */
			$state = self::progress_bar( $pct, __( 'Course progress', 'elearningtheme-lms' ) ) . '<p class="eltlms-small eltlms-mono">' . esc_html( sprintf( __( '%1$d of %2$d complete · %3$d%%', 'elearningtheme-lms' ), $done, $total, $pct ) ) . '</p>';
		}
		$list = self::curriculum( $course_id, $item_id, true );
		$nums = ELTLMS_Data::numbers( $course_id );
		/* translators: 1: lesson number like 2.3, 2: number of items in the course. */
		$sum = sprintf( __( 'Lesson %1$s of %2$d', 'elearningtheme-lms' ), $nums[ $item_id ] ?? '–', count( $nums ) );

		return '<aside class="eltlms-side" aria-label="' . esc_attr__( 'Course contents', 'elearningtheme-lms' ) . '"><div class="eltlms-side__head">' . $head . $state . '</div>' . $list . '</aside>'
			. '<details class="eltlms-mobile-contents"><summary><span><strong>' . esc_html__( 'Course contents', 'elearningtheme-lms' ) . '</strong><span class="eltlms-small eltlms-mono">' . esc_html( $sum ) . '</span></span>' . self::icon( 'chevron', 18 ) . '</summary>'
			. '<div class="eltlms-side__head">' . $head . $state . '</div>' . $list . '</details>';
	}

	public static function item_header( $item_id ) {
		$course_id = ELTLMS_Data::course_of( $item_id );
		$eyebrow   = '';
		if ( $course_id ) {
			$cur  = ELTLMS_Data::curriculum( $course_id );
			$nums = ELTLMS_Data::numbers( $course_id );
			foreach ( $cur as $si => $s ) {
				if ( in_array( (int) $item_id, $s['items'], true ) ) {
					$label   = get_post_type( $item_id ) === ELTLMS_Content::QUIZ ? __( 'Quiz', 'elearningtheme-lms' ) : __( 'Lesson', 'elearningtheme-lms' );
					$eyebrow = sprintf( '%02d · %s / %s %s', $si + 1, $s['title'], $label, $nums[ $item_id ] ?? '' );
				}
			}
		}
		$meta = '';
		if ( get_post_type( $item_id ) !== ELTLMS_Content::QUIZ ) {
			$kind = self::item_kind( $item_id );
			$dur  = (string) get_post_meta( $item_id, '_eltlms_duration', true );
			$meta = '<p class="eltlms-item-meta">' . self::icon( $kind, 14 ) . ' ' . esc_html( 'text' === $kind ? __( 'Reading', 'elearningtheme-lms' ) : __( 'Video', 'elearningtheme-lms' ) ) . ( $dur ? ' · ' . esc_html( $dur ) : '' ) . '</p>';
		}
		return '<header class="eltlms-item-header">' . ( $eyebrow ? '<p class="is-style-elt-eyebrow">' . esc_html( $eyebrow ) . '</p>' : '' )
			. '<h1 class="wp-block-heading">' . esc_html( get_the_title( $item_id ) ) . '</h1>' . $meta . '</header>'
			// A quiz or a reading lesson shows its picture here; a video lesson uses it as the poster.
			. ( has_post_thumbnail( $item_id ) && '' === (string) get_post_meta( $item_id, '_eltlms_video', true )
				? '<figure class="eltlms-item-cover">' . get_the_post_thumbnail( $item_id, 'large', array( 'alt' => '' ) ) . '</figure>' : '' );
	}

	public static function video( $item_id ) {
		$url = (string) get_post_meta( $item_id, '_eltlms_video', true );
		if ( '' === $url || ! ELTLMS_Data::can_view( get_current_user_id(), $item_id ) ) {
			return '';
		}
		if ( preg_match( '/\.(mp4|webm|ogv)(\?.*)?$/i', $url ) ) {
			// The lesson's featured image is the poster, so the player is not a black box before it starts.
			$poster = has_post_thumbnail( $item_id ) ? (string) get_the_post_thumbnail_url( $item_id, 'large' ) : '';
			$player = '<video controls preload="metadata" playsinline src="' . esc_url( $url ) . '"' . ( $poster ? ' poster="' . esc_url( $poster ) . '"' : '' ) . '></video>';
		} else {
			$player = wp_oembed_get( $url );
			if ( ! $player ) {
				$player = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the video', 'elearningtheme-lms' ) . '</a>';
			}
		}
		return '<div class="eltlms-video">' . $player . '</div>';
	}

	public static function locked( $item_id ) {
		$course_id = ELTLMS_Data::course_of( $item_id );
		$user      = get_current_user_id();
		$msg       = $user ? __( 'Enrol in the course to open this lesson.', 'elearningtheme-lms' ) : __( 'Log in and enrol in the course to open this lesson.', 'elearningtheme-lms' );
		$reason    = apply_filters( 'eltlms_locked_message', $msg, $item_id, $user );
		/**
		 * Where the locked screen's button leads: [ url, label ]. Pro points to a missing prerequisite.
		 */
		$cta       = (array) apply_filters( 'eltlms_locked_cta', array( get_permalink( $course_id ), __( 'Go to the course page', 'elearningtheme-lms' ) ), $item_id, $user );
		return '<div class="eltlms-locked" role="note">' . self::icon( 'lock', 22 ) . '<div><p class="eltlms-locked__title">' . esc_html__( 'This lesson is locked', 'elearningtheme-lms' ) . '</p><p>' . esc_html( $reason ) . '</p>'
			. '<p><a class="wp-element-button eltlms-btn" href="' . esc_url( $cta[0] ) . '">' . esc_html( $cta[1] ) . '</a></p></div></div>';
	}

	public static function item_nav( $item_id ) {
		$user                 = get_current_user_id();
		list( $prev, $next )  = ELTLMS_Data::neighbours( $item_id );
		$course_id            = ELTLMS_Data::course_of( $item_id );
		$nums                 = $course_id ? ELTLMS_Data::numbers( $course_id ) : array();
		$out                  = '';

		$is_quiz = get_post_type( $item_id ) === ELTLMS_Content::QUIZ;
		if ( ! $is_quiz && $user && ELTLMS_Data::is_enrolled( $user, $course_id ) && ELTLMS_Data::can_view( $user, $item_id ) ) {
			if ( ELTLMS_Data::is_complete( $user, $item_id ) ) {
				$out .= '<p class="eltlms-complete is-done">' . self::icon( 'check', 18 ) . ' ' . esc_html__( 'Completed', 'elearningtheme-lms' ) . '</p>';
			} else {
				$out .= '<form method="post" class="eltlms-complete" action="' . esc_url( get_permalink( $item_id ) ) . '">' . ELTLMS_Actions::fields( 'complete', $item_id )
					. '<button type="submit" class="wp-element-button eltlms-btn">' . self::icon( 'check', 16 ) . ' ' . esc_html__( 'Mark as complete', 'elearningtheme-lms' ) . '</button></form>';
			}
		}
		$link = static function ( $id, $dir ) use ( $nums ) {
			if ( ! $id ) {
				return '<span></span>';
			}
			$label = 'prev' === $dir ? __( '← Previous', 'elearningtheme-lms' ) : __( 'Next →', 'elearningtheme-lms' );
			return '<a class="eltlms-pn eltlms-pn--' . $dir . '" href="' . esc_url( get_permalink( $id ) ) . '" rel="' . ( 'prev' === $dir ? 'prev' : 'next' ) . '"><span class="eltlms-pn__label">' . esc_html( $label . ( isset( $nums[ $id ] ) ? ' · ' . $nums[ $id ] : '' ) ) . '</span><span class="eltlms-pn__title">' . esc_html( get_the_title( $id ) ) . '</span></a>';
		};
		return '<div class="eltlms-nav">' . $out . '<nav class="eltlms-pager" aria-label="' . esc_attr__( 'Lesson navigation', 'elearningtheme-lms' ) . '">' . $link( $prev, 'prev' ) . $link( $next, 'next' ) . '</nav></div>';
	}

	/* Quiz ---------------------------------------------------------------- */

	public static function quiz( $quiz_id ) {
		$user = get_current_user_id();
		if ( ! ELTLMS_Data::can_view( $user, $quiz_id ) ) {
			return '';
		}
		$count = ELTLMS_Data::question_count( $quiz_id );
		if ( ! $count ) {
			return '<p class="eltlms-empty">' . esc_html__( 'This quiz has no questions yet.', 'elearningtheme-lms' ) . '</p>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$show = isset( $_GET['result'] ) && $user ? ELTLMS_Data::last_attempt( $user, $quiz_id ) : null;
		if ( $show ) {
			return '<div class="eltlms-quiz">' . self::quiz_result( $quiz_id, $show ) . '</div>';
		}

		$pass  = ELTLMS_Data::pass_mark( $quiz_id );
		$need  = (int) ceil( $count * $pass / 100 );
		$facts = array(
			'questions' => array( __( 'Questions', 'elearningtheme-lms' ), (string) $count ),
			/* translators: 1: correct answers needed, 2: questions. */
			'pass'      => array( __( 'Pass mark', 'elearningtheme-lms' ), sprintf( __( '%1$d of %2$d', 'elearningtheme-lms' ), $need, $count ) ),
			'time'      => array( __( 'Time', 'elearningtheme-lms' ), __( 'No limit', 'elearningtheme-lms' ) ),
			'attempts'  => array( __( 'Attempts', 'elearningtheme-lms' ), __( 'Unlimited', 'elearningtheme-lms' ) ),
		);
		/**
		 * Facts above the quiz: key => [ label, value ]. Pro changes time and attempts.
		 */
		$facts = (array) apply_filters( 'eltlms_quiz_facts', $facts, $quiz_id, $user );
		$fhtml = '<ul class="elt-facts">';
		foreach ( $facts as $f ) {
			$fhtml .= '<li><span>' . esc_html( $f[0] ) . '</span> ' . esc_html( $f[1] ) . '</li>';
		}
		$fhtml .= '</ul>';

		if ( ! $user || ! ELTLMS_Data::is_enrolled( $user, ELTLMS_Data::course_of( $quiz_id ) ) ) {
			return '<div class="eltlms-quiz">' . $fhtml . '<p>' . esc_html__( 'Enrol in the course to take this quiz and save your score.', 'elearningtheme-lms' ) . '</p></div>';
		}

		$msg = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$code = isset( $_GET['quiz_msg'] ) ? sanitize_key( wp_unslash( $_GET['quiz_msg'] ) ) : '';
		$msgs = array(
			'no_attempts' => __( 'You have used all your attempts for this quiz.', 'elearningtheme-lms' ),
			'not_started' => __( 'Start the quiz first, then answer the questions.', 'elearningtheme-lms' ),
		);
		if ( isset( $msgs[ $code ] ) ) {
			$msg = '<p class="eltlms-notice" role="status">' . self::icon( 'close', 16 ) . ' ' . esc_html( $msgs[ $code ] ) . '</p>';
		}

		/**
		 * HTML shown instead of the questions, for example a "Start quiz" screen for a
		 * timed quiz or "No attempts left". Return '' to show the questions.
		 */
		$gate = (string) apply_filters( 'eltlms_quiz_gate', '', $quiz_id, $user );
		if ( '' !== $gate ) {
			return '<div class="eltlms-quiz" id="eltlms-quiz">' . $fhtml . $msg . $gate . '</div>';
		}

		$qs   = ELTLMS_Data::attempt_questions( $quiz_id, $user );
		$total = count( $qs );
		$form = '';
		foreach ( $qs as $i => $q ) {
			if ( 'choice' === $q['type'] ) {
				$input = '';
				foreach ( $q['options'] as $oi => $o ) {
					$input .= '<label class="eltlms-option"><input type="radio" name="eltlms_answer[' . (int) $i . ']" value="' . (int) $oi . '" required> <span>' . esc_html( $o ) . '</span></label>';
				}
			} else {
				/**
				 * The answer field for a question type an add-on registered. Name it eltlms_answer[ $i ].
				 */
				$input = (string) apply_filters( 'eltlms_render_question_input', '', $q, $i );
			}
			/* translators: 1: question number, 2: number of questions. */
			$form .= '<fieldset class="eltlms-question eltlms-question--' . esc_attr( $q['type'] ) . '"><legend><span class="eltlms-question__n">' . esc_html( sprintf( __( 'Question %1$d of %2$d', 'elearningtheme-lms' ), $i + 1, $total ) ) . '</span>'
				. '<span class="eltlms-question__q">' . esc_html( $q['q'] ) . '</span></legend>' . $input . '</fieldset>';
		}
		/**
		 * Note under the submit button.
		 */
		$note = (string) apply_filters( 'eltlms_quiz_form_note', __( 'Answers are checked on the server. You can try as often as you like.', 'elearningtheme-lms' ), $quiz_id, $user );
		return '<div class="eltlms-quiz" id="eltlms-quiz">' . $fhtml . $msg
			/**
			 * HTML just above the questions, for example the countdown of a timed quiz.
			 */
			. (string) apply_filters( 'eltlms_quiz_before_form', '', $quiz_id, $user )
			. '<form method="post" class="eltlms-quiz__form" action="' . esc_url( get_permalink( $quiz_id ) ) . '">' . ELTLMS_Actions::fields( 'quiz', $quiz_id ) . $form
			. '<p><button type="submit" class="wp-element-button eltlms-btn">' . esc_html__( 'Check my answers', 'elearningtheme-lms' ) . '</button></p>'
			. '<p class="eltlms-small">' . esc_html( $note ) . '</p></form></div>';
	}

	/**
	 * The result of one attempt: passed, not passed, waiting for review or time
	 * ran out, then a review of every answer as the student saw the questions.
	 */
	private static function quiz_result( $quiz_id, $row ) {
		$user   = get_current_user_id();
		$d      = ELTLMS_Data::attempt_detail( $row );
		$status = isset( $row->status ) ? (string) $row->status : 'graded';
		$passed = (bool) $row->passed;
		$total  = (int) $row->total;
		$need   = (int) ceil( $total * ELTLMS_Data::pass_mark( $quiz_id ) / 100 );
		if ( 'pending' === $status ) {
			$cls   = 'is-pending';
			$badge = self::icon( 'clock', 18 ) . ' ' . esc_html__( 'Waiting for review', 'elearningtheme-lms' );
			$head  = __( 'Your answers are with your teacher', 'elearningtheme-lms' );
			/* translators: 1: correct answers so far, 2: questions. */
			$text = sprintf( __( 'The choice questions are already checked: %1$d of %2$d correct so far. Your teacher reviews the written answers, and you get an email when the result is ready.', 'elearningtheme-lms' ), (int) $row->score, $total );
		} elseif ( 'expired' === $status ) {
			$cls   = 'is-expired';
			$badge = self::icon( 'clock', 18 ) . ' ' . esc_html__( 'Time ran out', 'elearningtheme-lms' );
			$head  = __( 'Time ran out', 'elearningtheme-lms' );
			$text  = __( 'The time limit passed before the answers were sent, so this attempt is not counted as passed.', 'elearningtheme-lms' );
		} else {
			$cls   = $passed ? 'is-passed' : 'is-failed';
			$badge = self::icon( $passed ? 'check' : 'close', 18 ) . ' ' . esc_html( $passed ? __( 'Passed', 'elearningtheme-lms' ) : __( 'Not passed yet', 'elearningtheme-lms' ) );
			/* translators: 1: correct answers, 2: questions. */
			$head = sprintf( __( '%1$d of %2$d correct', 'elearningtheme-lms' ), (int) $row->score, $total );
			/* translators: %d: correct answers needed to pass. */
			$text = sprintf( __( 'You need %d correct answers to pass.', 'elearningtheme-lms' ), $need );
		}
		$out = '<section id="eltlms-result" class="eltlms-result ' . $cls . '" aria-labelledby="eltlms-result-h">'
			. '<p class="eltlms-result__badge">' . $badge . '</p>'
			. '<h2 id="eltlms-result-h" class="wp-block-heading">' . esc_html( $head ) . '</h2><p>' . esc_html( $text ) . '</p>';
		if ( 'expired' !== $status ) {
			$out .= '<ol class="eltlms-review">';
			foreach ( $d['questions'] as $i => $q ) {
				$g    = $d['given'][ $i ] ?? null;
				$m    = array_key_exists( $i, (array) $d['marks'] ) ? $d['marks'][ $i ] : false; // null = waiting for review.
				$cls  = null === $m ? 'is-pending' : ( $m ? 'is-correct' : 'is-wrong' );
				$word = null === $m ? __( 'Waiting for review', 'elearningtheme-lms' ) : ( $m ? __( 'Correct', 'elearningtheme-lms' ) : __( 'Incorrect', 'elearningtheme-lms' ) );
				$icon = null === $m ? 'clock' : ( $m ? 'check' : 'close' );
				$out .= '<li class="' . $cls . '"><p class="eltlms-review__q">' . esc_html( $q['q'] ) . '</p>';
				if ( 'choice' === ( $q['type'] ?? 'choice' ) ) {
					$answer = $q['options'][ (int) $g ] ?? __( 'none', 'elearningtheme-lms' );
					$out   .= '<p class="eltlms-review__a">' . self::icon( $icon, 16 ) . ' <strong>' . esc_html( $word ) . '</strong> · '
						/* translators: %s: the answer the student chose. */
						. esc_html( sprintf( __( 'Your answer: %s', 'elearningtheme-lms' ), $answer ) ) . '</p>'
						/* translators: %s: the correct answer. */
						. ( $m ? '' : '<p class="eltlms-review__fix">' . esc_html( sprintf( __( 'Correct answer: %s', 'elearningtheme-lms' ), $q['options'][ $q['correct'] ] ) ) . '</p>' );
				} else {
					$out .= '<p class="eltlms-review__a">' . self::icon( $icon, 16 ) . ' <strong>' . esc_html( $word ) . '</strong></p>'
						. '<blockquote class="eltlms-review__text">' . nl2br( esc_html( '' !== (string) $g ? (string) $g : __( '(no answer)', 'elearningtheme-lms' ) ) ) . '</blockquote>';
				}
				if ( ! empty( $d['feedback'][ $i ] ) ) {
					/* translators: %s: the teacher's feedback. */
					$out .= '<p class="eltlms-review__feedback"><strong>' . esc_html__( 'Teacher’s feedback:', 'elearningtheme-lms' ) . '</strong> ' . esc_html( $d['feedback'][ $i ] ) . '</p>';
				}
				$out .= '</li>';
			}
			$out .= '</ol>';
		}
		if ( 'pending' === $status ) {
			$course = ELTLMS_Data::course_of( $quiz_id );
			$out   .= '<p><a class="wp-element-button eltlms-btn is-outline" href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html__( 'Back to the course', 'elearningtheme-lms' ) . '</a></p>';
		} elseif ( ! $passed ) {
			/**
			 * Whether the student may take the quiz again (Pro: attempt limits).
			 */
			$again = (bool) apply_filters( 'eltlms_quiz_can_retry', true, $quiz_id, $user );
			$out  .= $again
				? '<p><a class="wp-element-button eltlms-btn is-outline" href="' . esc_url( get_permalink( $quiz_id ) ) . '#eltlms-quiz">' . esc_html__( 'Try again', 'elearningtheme-lms' ) . '</a></p>'
				: '<p class="eltlms-notice">' . esc_html__( 'You have used all your attempts for this quiz.', 'elearningtheme-lms' ) . '</p>';
		} else {
			$out .= '<p><a class="wp-element-button eltlms-btn is-outline" href="' . esc_url( get_permalink( $quiz_id ) ) . '#eltlms-quiz">' . esc_html__( 'Try again', 'elearningtheme-lms' ) . '</a></p>';
		}
		return $out . '</section>';
	}

	public static function dashboard() {
		$user = get_current_user_id();
		if ( ! $user ) {
			return '<div class="eltlms-dash"><p>' . esc_html__( 'Log in to see your courses and progress.', 'elearningtheme-lms' ) . '</p>'
				. '<p><a class="wp-element-button eltlms-btn" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'elearningtheme-lms' ) . '</a></p></div>';
		}
		$rows = array();
		$mine = ELTLMS_Data::user_enrolments( $user );
		ELTLMS_Data::prime_courses( wp_list_pluck( $mine, 'course_id' ), $user );
		foreach ( $mine as $e ) {
			if ( get_post_status( (int) $e->course_id ) === 'publish' ) {
				$rows[] = $e;
			}
		}
		$me   = wp_get_current_user();
		$name = rtrim( '' !== $me->first_name ? $me->first_name : $me->display_name, '.' );
		/* translators: %s: student name. */
		$out = '<div class="eltlms-dash"><p class="is-style-elt-eyebrow">' . esc_html__( 'My learning', 'elearningtheme-lms' ) . '</p><h1 class="wp-block-heading">' . esc_html( sprintf( __( 'Good to see you, %s.', 'elearningtheme-lms' ), $name ) ) . '</h1>';

		if ( ! $rows ) {
			return $out . '<p>' . esc_html__( 'You haven’t enrolled in a course yet.', 'elearningtheme-lms' ) . '</p><p><a class="wp-element-button eltlms-btn" href="' . esc_url( get_post_type_archive_link( ELTLMS_Content::COURSE ) ) . '">' . esc_html__( 'Browse courses', 'elearningtheme-lms' ) . '</a></p>' . self::dashboard_extra( $user ) . self::account_links() . '</div>';
		}

		// Continue learning: the most recently active unfinished course.
		foreach ( $rows as $e ) {
			if ( ! $e->completed_at ) {
				$cid  = (int) $e->course_id;
				$next = ELTLMS_Data::next_item( $user, $cid );
				list( $done, $total, $pct ) = ELTLMS_Data::progress( $user, $cid );
				if ( $next ) {
					$out .= '<section class="eltlms-continue is-style-elt-card">' . self::cover( $next, $cid ) . '<p class="is-style-elt-eyebrow">' . esc_html__( 'Continue learning', 'elearningtheme-lms' ) . '</p>'
						. '<p class="eltlms-small">' . esc_html( get_the_title( $cid ) ) . '</p><p class="eltlms-continue__title">' . esc_html( get_the_title( $next ) ) . '</p>'
						. self::progress_bar( $pct, __( 'Course progress', 'elearningtheme-lms' ) )
						/* translators: 1: completed items, 2: all items. */
						. '<p class="eltlms-small eltlms-mono">' . esc_html( sprintf( __( '%1$d of %2$d lessons', 'elearningtheme-lms' ), $done, $total ) ) . '</p>'
						. '<p><a class="wp-element-button eltlms-btn" href="' . esc_url( get_permalink( $next ) ) . '">' . esc_html__( 'Continue lesson', 'elearningtheme-lms' ) . '</a></p></section>';
				}
				break;
			}
		}

		$list = '';
		$done_list = '';
		foreach ( $rows as $e ) {
			$cid                       = (int) $e->course_id;
			list( $d, $t, $pct )       = ELTLMS_Data::progress( $user, $cid );
			$status                    = $e->completed_at ? __( 'Completed', 'elearningtheme-lms' ) : ( $d ? __( 'In progress', 'elearningtheme-lms' ) : __( 'Not started', 'elearningtheme-lms' ) );
			$next                      = ELTLMS_Data::next_item( $user, $cid );
			$btn                       = $e->completed_at ? __( 'Review', 'elearningtheme-lms' ) : ( $d ? __( 'Continue', 'elearningtheme-lms' ) : __( 'Start course', 'elearningtheme-lms' ) );
			$thumb                     = has_post_thumbnail( $cid ) ? '<a class="eltlms-mycourse__thumb" href="' . esc_url( get_permalink( $cid ) ) . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $cid, 'medium', array( 'alt' => '', 'loading' => 'lazy' ) ) . '</a>' : '';
			$list                     .= '<li class="eltlms-mycourse' . ( $thumb ? ' has-thumb' : '' ) . '">' . $thumb . '<div><a class="eltlms-mycourse__title" href="' . esc_url( get_permalink( $cid ) ) . '">' . esc_html( get_the_title( $cid ) ) . '</a>'
				/* translators: 1: completed items, 2: all items. */
				. '<span class="eltlms-small eltlms-mono">' . esc_html( sprintf( __( '%1$d of %2$d lessons', 'elearningtheme-lms' ), $d, $t ) ) . '</span></div>'
				. self::progress_bar( $pct, get_the_title( $cid ) )
				. '<span class="eltlms-mycourse__status' . ( $e->completed_at ? ' is-done' : '' ) . '">' . ( $e->completed_at ? self::icon( 'check', 14 ) . ' ' : '' ) . esc_html( $status ) . '</span>'
				. '<a class="eltlms-btn-small" href="' . esc_url( get_permalink( $next ? $next : $cid ) ) . '">' . esc_html( $btn ) . '</a></li>';
			if ( $e->completed_at ) {
				/* translators: %s: completion date. */
				$done_list .= '<li><span class="eltlms-small eltlms-mono">' . esc_html( sprintf( __( 'Completed %s', 'elearningtheme-lms' ), mysql2date( get_option( 'date_format' ), get_date_from_gmt( $e->completed_at ) ) ) ) . '</span> <a href="' . esc_url( get_permalink( $cid ) ) . '">' . esc_html( get_the_title( $cid ) ) . '</a>' . (string) apply_filters( 'eltlms_completed_links', '', $user, $cid ) . '</li>';
			}
		}
		$out .= '<section class="eltlms-dash__section"><h2 class="wp-block-heading">' . esc_html__( 'My courses', 'elearningtheme-lms' ) . '</h2><ul class="eltlms-mycourses">' . $list . '</ul></section>';
		if ( $done_list ) {
			$out .= '<section class="eltlms-dash__section"><h2 class="wp-block-heading">' . esc_html__( 'Completed', 'elearningtheme-lms' ) . '</h2><ul class="eltlms-completed">' . $done_list . '</ul></section>';
		}
		return $out . self::dashboard_extra( $user ) . self::account_links() . '</div>';
	}

	/**
	 * Extra dashboard sections from add-ons (Pro: subscriptions). Return HTML.
	 */
	private static function dashboard_extra( $user ) {
		return (string) apply_filters( 'eltlms_dashboard_sections', '', (int) $user );
	}

	private static function account_links() {
		return '<div class="eltlms-account"><p>' . self::icon( 'user', 14 ) . ' <a href="' . esc_url( get_edit_profile_url() ) . '">' . esc_html__( 'Account details', 'elearningtheme-lms' ) . '</a> · <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Log out', 'elearningtheme-lms' ) . '</a></p>' . ELTLMS_Lang::switcher() . '</div>';
	}

	/* Catalog ------------------------------------------------------------- */

	public static function course_list( $attrs ) {
		$per = max( 1, min( 48, (int) ( $attrs['perPage'] ?? 12 ) ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$cat = isset( $_GET['course_category'] ) ? sanitize_title( wp_unslash( $_GET['course_category'] ) ) : '';
		$q   = isset( $_GET['course_search'] ) ? sanitize_text_field( wp_unslash( $_GET['course_search'] ) ) : '';
		// phpcs:enable
		$args = array(
			'post_type'      => ELTLMS_Content::COURSE,
			'post_status'    => 'publish',
			'posts_per_page' => $per,
			'no_found_rows'  => true,
		);
		if ( $cat ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'elt_course_category', 'field' => 'slug', 'terms' => $cat ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( '' !== $q ) {
			$args['s'] = $q;
		}
		if ( ! empty( $attrs['author'] ) ) {
			$args['author'] = (int) $attrs['author'];
		}
		$courses = get_posts( $args );
		ELTLMS_Data::prime_courses( wp_list_pluck( $courses, 'ID' ) );
		$base    = get_post_type_archive_link( ELTLMS_Content::COURSE );
		$out     = '';

		if ( ! empty( $attrs['showFilters'] ) ) {
			$terms = get_terms( array( 'taxonomy' => 'elt_course_category', 'hide_empty' => true ) );
			$chips = '<a class="eltlms-chip' . ( $cat ? '' : ' is-on' ) . '" href="' . esc_url( $base ) . '"' . ( $cat ? '' : ' aria-current="true"' ) . '>' . esc_html__( 'All', 'elearningtheme-lms' ) . '</a>';
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$on     = $t->slug === $cat;
					$chips .= '<a class="eltlms-chip' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( add_query_arg( 'course_category', $t->slug, $base ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $t->name ) . '</a>';
				}
			}
			$out .= '<div class="eltlms-filters"><nav class="eltlms-chips" aria-label="' . esc_attr__( 'Course categories', 'elearningtheme-lms' ) . '">' . $chips . '</nav>'
				. '<form class="eltlms-search" role="search" method="get" action="' . esc_url( $base ) . '"><label class="screen-reader-text" for="eltlms-q">' . esc_html__( 'Search courses', 'elearningtheme-lms' ) . '</label>'
				. '<input id="eltlms-q" type="search" name="course_search" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Search courses', 'elearningtheme-lms' ) . '"></form></div>';
		}

		if ( ! $courses ) {
			return $out . '<div class="eltlms-empty-state"><p class="eltlms-empty-state__title">' . esc_html( $q || $cat ? __( 'No courses match.', 'elearningtheme-lms' ) : __( 'No courses yet.', 'elearningtheme-lms' ) ) . '</p>'
				. '<p>' . esc_html( $q || $cat ? __( 'Try another word or category.', 'elearningtheme-lms' ) : __( 'New courses will appear here.', 'elearningtheme-lms' ) ) . '</p></div>';
		}

		$cards = '';
		foreach ( $courses as $i => $c ) {
			$terms = get_the_terms( $c, 'elt_course_category' );
			$cat_n = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
			$items = ELTLMS_Data::items( $c->ID );
			$meta  = array();
			/* translators: %d: number of lessons. */
			$meta[] = sprintf( _n( '%d lesson', '%d lessons', count( $items ), 'elearningtheme-lms' ), count( $items ) );
			$dur    = (string) get_post_meta( $c->ID, '_eltlms_duration', true );
			$lvl    = (string) get_post_meta( $c->ID, '_eltlms_level', true );
			if ( $dur ) {
				$meta[] = $dur;
			}
			if ( $lvl ) {
				$meta[] = $lvl;
			}
			$img    = has_post_thumbnail( $c ) ? get_the_post_thumbnail( $c, 'medium_large', array( 'class' => 'elt-course-card__img', 'loading' => 'lazy', 'alt' => '' ) ) : '';
			$price  = apply_filters( 'eltlms_course_price_label', __( 'Free', 'elearningtheme-lms' ), $c->ID );
			$cards .= '<article class="elt-course-card">' . $img
				. '<p class="elt-course-card__top"><span>' . esc_html( $cat_n ) . '</span> <span>' . esc_html( sprintf( /* translators: %s: course number, e.g. 01. */ __( 'No. %s', 'elearningtheme-lms' ), sprintf( '%02d', $i + 1 ) ) ) . '</span></p>'
				. '<h3 class="wp-block-heading elt-course-card__title"><a href="' . esc_url( get_permalink( $c ) ) . '">' . esc_html( get_the_title( $c ) ) . '</a></h3>'
				. '<p class="elt-course-card__teacher">' . esc_html( get_the_author_meta( 'display_name', (int) $c->post_author ) ) . '</p>'
				. '<p class="elt-course-card__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>'
				. '<p class="elt-course-card__price"><strong>' . esc_html( $price ) . '</strong> <span aria-hidden="true">→</span></p></article>';
		}
		return $out . '<div class="eltlms-grid">' . $cards . '</div>';
	}
}
