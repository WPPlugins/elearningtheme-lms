<?php
/**
 * Courses › Students: see who is enrolled in a course, add a student by email
 * or username, and remove one. Removing keeps the student's progress, so it
 * comes back if they are added again.
 *
 * Anyone who can edit a course can manage its students (teachers: their own
 * courses). Plain forms, no JavaScript.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Students {

	const SLUG     = 'eltlms-students';
	const PER_PAGE = 50;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_eltlms_add_student', array( __CLASS__, 'add' ) );
		add_action( 'admin_post_eltlms_remove_student', array( __CLASS__, 'remove' ) );
		add_action( 'admin_post_eltlms_reset_student', array( __CLASS__, 'reset' ) );
		add_action( 'admin_post_eltlms_import_students', array( __CLASS__, 'import' ) );
	}

	public static function menu() {
		$hook = add_submenu_page(
			'edit.php?post_type=' . ELTLMS_Content::COURSE,
			__( 'Students', 'elearningtheme-lms' ),
			__( 'Students', 'elearningtheme-lms' ),
			'edit_posts',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
		add_action(
			'admin_enqueue_scripts',
			static function ( $current ) use ( $hook ) {
				if ( $current !== $hook ) {
					return;
				}
				wp_enqueue_style( 'elearningtheme-lms-admin', plugins_url( 'assets/admin.css', ELTLMS_FILE ), array(), ELTLMS_VERSION );
				wp_register_script( 'elearningtheme-lms-students', false, array(), ELTLMS_VERSION, true );
				wp_enqueue_script( 'elearningtheme-lms-students' );
				wp_add_inline_script( 'elearningtheme-lms-students', 'document.addEventListener("click",function(e){var b=e.target.closest("[data-confirm]");if(b&&!window.confirm(b.getAttribute("data-confirm"))){e.preventDefault();}});' );
			}
		);
	}

	public static function url( $course_id, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => ELTLMS_Content::COURSE,
					'page'      => self::SLUG,
					'course'    => (int) $course_id,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Courses the current user may manage.
	 */
	private static function courses() {
		$args = array(
			'post_type'      => ELTLMS_Content::COURSE,
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		return array_values( array_filter( get_posts( $args ), static function ( $c ) { return current_user_can( 'edit_post', $c->ID ); } ) );
	}

	/* Actions ------------------------------------------------------------- */

	/**
	 * Check the nonce first, then that the user may edit this course.
	 *
	 * @return int The course ID.
	 */
	private static function guard() {
		check_admin_referer( 'eltlms_students' );
		$course_id = isset( $_POST['course'] ) ? absint( $_POST['course'] ) : 0;
		if ( get_post_type( $course_id ) !== ELTLMS_Content::COURSE || ! current_user_can( 'edit_post', $course_id ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the students of this course.', 'elearningtheme-lms' ), 403 );
		}
		return $course_id;
	}

	public static function add() {
		$course_id = self::guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$who  = isset( $_POST['who'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['who'] ) ) ) : '';
		$user = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
		if ( ! $user ) {
			$msg = 'notfound';
		} elseif ( ELTLMS_Data::is_enrolled( $user->ID, $course_id ) ) {
			$msg = 'already';
		} else {
			$msg = ELTLMS_Data::enrol( $user->ID, $course_id, true ) ? 'added' : 'error';
		}
		wp_safe_redirect( self::url( $course_id, array( 'msg' => $msg, 'who' => rawurlencode( $who ) ) ) );
		exit;
	}

	public static function remove() {
		$course_id = self::guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$user_id = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		$msg     = ELTLMS_Data::unenrol( $user_id, $course_id ) ? 'removed' : 'error';
		wp_safe_redirect( self::url( $course_id, array( 'msg' => $msg ) ) );
		exit;
	}

	/**
	 * Clear a student's progress so they take the course again from the start.
	 */
	public static function reset() {
		$course_id = self::guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$user_id = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		$msg     = ELTLMS_Data::restart( $user_id, $course_id ) ? 'reset' : 'error';
		wp_safe_redirect( self::url( $course_id, array( 'msg' => $msg ) ) );
		exit;
	}

	/**
	 * Import students from a CSV file: one per line, an email and optionally a
	 * name, in any order. Comma or semicolon. New emails can get an account.
	 */
	public static function import() {
		$course_id = self::guard();
		$create    = ! empty( $_POST['create'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$file      = isset( $_FILES['csv']['tmp_name'] ) ? (string) $_FILES['csv']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in guard(); a temp path.
		$name      = isset( $_FILES['csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$size      = isset( $_FILES['csv']['size'] ) ? (int) $_FILES['csv']['size'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sum       = array( 'added' => 0, 'created' => 0, 'already' => 0, 'invalid' => array(), 'error' => '' );
		if ( '' === $file || ! is_uploaded_file( $file ) || ! preg_match( '/\.(csv|txt)$/i', $name ) ) {
			$sum['error'] = 'file';
		} elseif ( $size > 2 * MB_IN_BYTES ) {
			$sum['error'] = 'size';
		} else {
			$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- uploaded temp file.
			$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
			$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );
			$lines = array_slice( explode( "\n", $raw ), 0, 5000 );
			$first = (string) ( $lines[0] ?? '' );
			$sep   = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
			$seen  = array();
			foreach ( $lines as $n => $line ) {
				if ( '' === trim( $line ) ) {
					continue;
				}
				$cells = array_map( 'trim', str_getcsv( $line, $sep ) );
				$email = '';
				$label = '';
				foreach ( $cells as $c ) {
					if ( '' === $email && false !== strpos( $c, '@' ) ) {
						$email = sanitize_email( $c );
					} elseif ( '' === $label && '' !== $c ) {
						$label = sanitize_text_field( $c );
					}
				}
				if ( '' === $email ) {
					if ( 0 === $n ) {
						continue; // A header line.
					}
					$sum['invalid'][] = $n + 1;
					continue;
				}
				if ( ! is_email( $email ) ) {
					$sum['invalid'][] = $n + 1;
					continue;
				}
				$key = strtolower( $email );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$user         = get_user_by( 'email', $email );
				if ( ! $user && $create ) {
					$uid = self::create_user( $email, $label );
					if ( $uid ) {
						$user = get_user_by( 'id', $uid );
						++$sum['created'];
					}
				}
				if ( ! $user ) {
					$sum['invalid'][] = $n + 1;
					continue;
				}
				if ( ELTLMS_Data::is_enrolled( $user->ID, $course_id ) ) {
					++$sum['already'];
				} elseif ( ELTLMS_Data::enrol( $user->ID, $course_id, true ) ) {
					++$sum['added'];
				}
			}
		}
		set_transient( 'eltlms_import_' . get_current_user_id(), $sum, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( $course_id, array( 'msg' => 'imported' ) ) );
		exit;
	}

	/**
	 * A new subscriber account; WordPress emails them a link to set a password.
	 */
	private static function create_user( $email, $label ) {
		$base  = sanitize_user( strstr( $email, '@', true ), true );
		$base  = '' !== $base ? $base : 'student';
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i;
		}
		$uid = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => '' !== $label ? $label : $base,
				'first_name'   => $label,
				'role'         => get_option( 'default_role', 'subscriber' ),
			)
		);
		if ( is_wp_error( $uid ) ) {
			return 0;
		}
		wp_new_user_notification( $uid, null, 'user' );
		return (int) $uid;
	}

	/* Data ---------------------------------------------------------------- */

	/**
	 * One page of enrolments with the user's name and email.
	 *
	 * @return array [ rows, total ]
	 */
	private static function rows( $course_id, $search, $paged ) {
		global $wpdb;
		$t      = ELTLMS_Install::tables();
		$offset = ( $paged - 1 ) * self::PER_PAGE;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, admin screen.
		if ( '' === $search ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d', $t['enrolments'], $course_id ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT e.user_id, e.enrolled_at, e.completed_at, u.display_name, u.user_email FROM %i e JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d ORDER BY e.enrolled_at DESC LIMIT %d OFFSET %d', $t['enrolments'], $wpdb->users, $course_id, self::PER_PAGE, $offset ) );
		} else {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i e JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d AND ( u.user_email LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s )', $t['enrolments'], $wpdb->users, $course_id, $like, $like, $like ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT e.user_id, e.enrolled_at, e.completed_at, u.display_name, u.user_email FROM %i e JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d AND ( u.user_email LIKE %s OR u.user_login LIKE %s OR u.display_name LIKE %s ) ORDER BY e.enrolled_at DESC LIMIT %d OFFSET %d', $t['enrolments'], $wpdb->users, $course_id, $like, $like, $like, self::PER_PAGE, $offset ) );
		}
		// phpcs:enable
		return array( (array) $rows, $total );
	}

	/**
	 * Completed items per user for the users on this page, in one query.
	 */
	private static function done_counts( $course_id, $user_ids, $items ) {
		global $wpdb;
		if ( ! $user_ids || ! $items ) {
			return array();
		}
		$t      = ELTLMS_Install::tables();
		$u_ph   = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$i_ph   = implode( ',', array_fill( 0, count( $items ), '%d' ) );
		$params = array_merge( array( $t['progress'], (int) $course_id ), array_map( 'intval', $user_ids ), array_map( 'intval', $items ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Custom table; the IN lists are built from %d placeholders only.
		$res = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, COUNT(*) AS n FROM %i WHERE course_id = %d AND user_id IN ($u_ph) AND item_id IN ($i_ph) GROUP BY user_id", $params ) );
		$out = array();
		foreach ( (array) $res as $r ) {
			$out[ (int) $r->user_id ] = (int) $r->n;
		}
		return $out;
	}

	/* Screen -------------------------------------------------------------- */

	private static function notice( $msg, $who ) {
		$map = array(
			'added'    => array( 'success', __( 'Student added. They got the enrolment email and can start straight away.', 'elearningtheme-lms' ) ),
			'removed'  => array( 'success', __( 'Student removed from the course. Their progress is kept, in case you add them again.', 'elearningtheme-lms' ) ),
			/* translators: %s: what was typed (email or username). */
			'notfound' => array( 'error', sprintf( __( 'No user with the email or username “%s”. Create the account under Users › Add New User first, then add it here.', 'elearningtheme-lms' ), $who ) ),
			'reset'    => array( 'success', __( 'Progress reset. The student starts the course again from the first lesson; their earlier quiz attempts no longer count.', 'elearningtheme-lms' ) ),
			'already'  => array( 'warning', __( 'This person is already enrolled in the course.', 'elearningtheme-lms' ) ),
			'error'    => array( 'error', __( 'Something went wrong. Please try again.', 'elearningtheme-lms' ) ),
		);
		if ( 'imported' === $msg ) {
			self::import_notice();
			return;
		}
		if ( ! isset( $map[ $msg ] ) ) {
			return;
		}
		$link = 'notfound' === $msg && current_user_can( 'create_users' ) ? ' <a href="' . esc_url( admin_url( 'user-new.php' ) ) . '">' . esc_html__( 'Add New User', 'elearningtheme-lms' ) . '</a>' : '';
		echo '<div class="notice notice-' . esc_attr( $map[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $map[ $msg ][1] ) . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link is escaped above.
	}

	private static function import_notice() {
		$sum = get_transient( 'eltlms_import_' . get_current_user_id() );
		if ( ! is_array( $sum ) ) {
			return;
		}
		delete_transient( 'eltlms_import_' . get_current_user_id() );
		if ( 'file' === $sum['error'] || 'size' === $sum['error'] ) {
			$text = 'size' === $sum['error'] ? __( 'The file is too large. Split it into files of up to 2 MB.', 'elearningtheme-lms' ) : __( 'Please choose a CSV file (.csv).', 'elearningtheme-lms' );
			echo '<div class="notice notice-error"><p>' . esc_html( $text ) . '</p></div>';
			return;
		}
		/* translators: %d: number of students. */
		$parts = array( sprintf( _n( '%d student added to the course.', '%d students added to the course.', $sum['added'], 'elearningtheme-lms' ), $sum['added'] ) );
		if ( $sum['created'] ) {
			/* translators: %d: number of new accounts. */
			$parts[] = sprintf( _n( '%d new account was created; we emailed a link to set a password.', '%d new accounts were created; we emailed each a link to set a password.', $sum['created'], 'elearningtheme-lms' ), $sum['created'] );
		}
		if ( $sum['already'] ) {
			/* translators: %d: number of students. */
			$parts[] = sprintf( _n( '%d was already enrolled.', '%d were already enrolled.', $sum['already'], 'elearningtheme-lms' ), $sum['already'] );
		}
		$type = 'success';
		if ( $sum['invalid'] ) {
			$type = 'warning';
			/* translators: %s: line numbers. */
			$parts[] = sprintf( __( 'Skipped lines without a valid email address or account: %s.', 'elearningtheme-lms' ), implode( ', ', array_slice( $sum['invalid'], 0, 20 ) ) . ( count( $sum['invalid'] ) > 20 ? ' …' : '' ) );
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( implode( ' ', $parts ) ) . '</p></div>';
	}

	public static function page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen filters.
		$courses = self::courses();
		echo '<div class="wrap eltlms-students"><h1 class="wp-heading-inline">' . esc_html__( 'Students', 'elearningtheme-lms' ) . '</h1>';
		if ( ! $courses ) {
			echo '<p>' . esc_html__( 'No courses yet. Create a course first, then manage its students here.', 'elearningtheme-lms' ) . '</p></div>';
			return;
		}
		$ids       = wp_list_pluck( $courses, 'ID' );
		$course_id = isset( $_GET['course'] ) ? absint( $_GET['course'] ) : 0;
		$course_id = in_array( $course_id, $ids, true ) ? $course_id : (int) $ids[0];
		$search    = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$msg       = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';
		$who       = isset( $_GET['who'] ) ? sanitize_text_field( wp_unslash( $_GET['who'] ) ) : '';
		// phpcs:enable

		echo '<hr class="wp-header-end">';
		self::notice( $msg, $who );

		// Course picker.
		echo '<form method="get" class="eltlms-students__pick"><input type="hidden" name="post_type" value="' . esc_attr( ELTLMS_Content::COURSE ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">'
			. '<label for="eltlms-students-course">' . esc_html__( 'Course', 'elearningtheme-lms' ) . '</label> <select id="eltlms-students-course" name="course">';
		foreach ( $courses as $c ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $c->ID, selected( $course_id, $c->ID, false ), esc_html( $c->post_title ? $c->post_title : __( '(no title)', 'elearningtheme-lms' ) ) );
		}
		echo '</select> <button type="submit" class="button">' . esc_html__( 'Show', 'elearningtheme-lms' ) . '</button></form>';

		// Add a student.
		echo '<div class="eltlms-students__add"><h2>' . esc_html__( 'Add a student', 'elearningtheme-lms' ) . '</h2>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eltlms_add_student"><input type="hidden" name="course" value="' . (int) $course_id . '">';
		wp_nonce_field( 'eltlms_students' );
		echo '<label for="eltlms-students-who">' . esc_html__( 'Email or username', 'elearningtheme-lms' ) . '</label> '
			. '<input type="text" id="eltlms-students-who" name="who" class="regular-text" required autocomplete="off"> '
			. '<button type="submit" class="button button-primary">' . esc_html__( 'Add to course', 'elearningtheme-lms' ) . '</button>'
			. '<p class="description">' . esc_html__( 'The person needs an account on this site. Students you add here get the enrolment email, and paid courses are opened without payment.', 'elearningtheme-lms' ) . '</p></form>';

		// Import many at once.
		echo '<details class="eltlms-students__import"><summary>' . esc_html__( 'Import students from a CSV file', 'elearningtheme-lms' ) . '</summary>'
			. '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eltlms_import_students"><input type="hidden" name="course" value="' . (int) $course_id . '">';
		wp_nonce_field( 'eltlms_students' );
		echo '<p class="description">' . esc_html__( 'One student per line: an email address and, if you like, a name, separated by a comma or a semicolon. A header line is fine. You can save this from Excel or Google Sheets as CSV.', 'elearningtheme-lms' ) . '</p>'
			. '<p><label for="eltlms-students-csv">' . esc_html__( 'CSV file', 'elearningtheme-lms' ) . '</label> <input type="file" id="eltlms-students-csv" name="csv" accept=".csv,text/csv" required></p>'
			. '<p><label><input type="checkbox" name="create" value="1" checked> ' . esc_html__( 'Create accounts for new email addresses and email them a link to set a password', 'elearningtheme-lms' ) . '</label></p>'
			. '<p><button type="submit" class="button button-primary">' . esc_html__( 'Import and enrol', 'elearningtheme-lms' ) . '</button></p></form></details></div>';

		list( $rows, $total ) = self::rows( $course_id, $search, $paged );
		$items                = ELTLMS_Data::items( $course_id );
		$done                 = self::done_counts( $course_id, wp_list_pluck( $rows, 'user_id' ), $items );

		/* translators: %d: number of students. */
		echo '<h2>' . esc_html( sprintf( _n( '%d student', '%d students', $total, 'elearningtheme-lms' ), $total ) ) . '</h2>';

		// Search.
		echo '<form method="get" class="eltlms-students__search"><input type="hidden" name="post_type" value="' . esc_attr( ELTLMS_Content::COURSE ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="course" value="' . (int) $course_id . '">'
			. '<label class="screen-reader-text" for="eltlms-students-s">' . esc_html__( 'Search students', 'elearningtheme-lms' ) . '</label><input type="search" id="eltlms-students-s" name="s" value="' . esc_attr( $search ) . '"> '
			. '<button type="submit" class="button">' . esc_html__( 'Search students', 'elearningtheme-lms' ) . '</button></form>';

		if ( ! $rows ) {
			echo '<p>' . esc_html( '' !== $search ? __( 'No students match your search.', 'elearningtheme-lms' ) : __( 'No students in this course yet.', 'elearningtheme-lms' ) ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped eltlms-students__table"><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Student', 'elearningtheme-lms' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Email', 'elearningtheme-lms' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Enrolled', 'elearningtheme-lms' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Progress', 'elearningtheme-lms' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Completed', 'elearningtheme-lms' ) . '</th>'
			. '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'elearningtheme-lms' ) . '</span></th></tr></thead><tbody>';
		$count = count( $items );
		$fmt   = get_option( 'date_format' );
		foreach ( $rows as $r ) {
			$uid  = (int) $r->user_id;
			$n    = min( $count, $done[ $uid ] ?? 0 );
			$pct  = $count ? (int) floor( 100 * $n / $count ) : 0;
			$name = current_user_can( 'edit_user', $uid ) ? '<a href="' . esc_url( get_edit_user_link( $uid ) ) . '">' . esc_html( $r->display_name ) . '</a>' : esc_html( $r->display_name );
			echo '<tr><td>' . $name . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				. '<td>' . esc_html( $r->user_email ) . '</td>'
				. '<td>' . esc_html( mysql2date( $fmt, get_date_from_gmt( $r->enrolled_at ) ) ) . '</td>'
				/* translators: 1: done, 2: total, 3: percent. */
				. '<td><span class="eltlms-students__bar" aria-hidden="true"><span style="width:' . (int) $pct . '%"></span></span> ' . esc_html( sprintf( __( '%1$d of %2$d · %3$d%%', 'elearningtheme-lms' ), $n, $count, $pct ) ) . '</td>'
				. '<td>' . ( $r->completed_at ? esc_html( mysql2date( $fmt, get_date_from_gmt( $r->completed_at ) ) ) : '—' ) . '</td>'
				. '<td class="eltlms-students__actions">';
			if ( $n || $r->completed_at ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eltlms_reset_student"><input type="hidden" name="course" value="' . (int) $course_id . '"><input type="hidden" name="user" value="' . (int) $uid . '">';
				wp_nonce_field( 'eltlms_students', '_wpnonce', false );
				/* translators: %s: student name. */
				echo '<button type="submit" class="button-link eltlms-students__reset" data-confirm="' . esc_attr__( 'Reset this student’s progress? They will take every lesson and quiz again.', 'elearningtheme-lms' ) . '" aria-label="' . esc_attr( sprintf( __( 'Reset the progress of %s', 'elearningtheme-lms' ), $r->display_name ) ) . '">' . esc_html__( 'Reset progress', 'elearningtheme-lms' ) . '</button></form>';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eltlms_remove_student"><input type="hidden" name="course" value="' . (int) $course_id . '"><input type="hidden" name="user" value="' . (int) $uid . '">';
			wp_nonce_field( 'eltlms_students', '_wpnonce', false );
			/* translators: %s: student name. */
			echo '<button type="submit" class="button-link eltlms-students__remove" aria-label="' . esc_attr( sprintf( __( 'Remove %s from the course', 'elearningtheme-lms' ), $r->display_name ) ) . '">' . esc_html__( 'Remove', 'elearningtheme-lms' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';

		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function, escaped.
				array(
					'base'    => add_query_arg( 'paged', '%#%', self::url( $course_id, '' !== $search ? array( 's' => $search ) : array() ) ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			) . '</div></div>';
		}
		echo '</div>';
	}
}
