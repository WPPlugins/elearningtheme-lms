<?php
/**
 * Read and write course data: curriculum, enrolments, progress and quiz attempts.
 *
 * Everything a lesson page needs costs a fixed number of queries: the course
 * outline (post meta, already cached), one query to prime the item posts, one
 * for the enrolment and one for the completed items.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Data {

	/** @var array Per-request caches. */
	private static $cache = array();

	/* Curriculum ---------------------------------------------------------- */

	/**
	 * Sections of a course: [ [ 'title' => string, 'items' => int[] ], ... ].
	 * Only published lessons and quizzes are returned.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function curriculum( $course_id ) {
		$course_id = (int) $course_id;
		$key       = 'cur_' . $course_id;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		$raw  = json_decode( (string) get_post_meta( $course_id, '_eltlms_curriculum', true ), true );
		$raw  = is_array( $raw ) ? $raw : array();
		$ids  = array();
		foreach ( $raw as $s ) {
			foreach ( (array) ( $s['items'] ?? array() ) as $id ) {
				$ids[] = (int) $id;
			}
		}
		$live = array();
		if ( $ids ) {
			// Posts and their meta in two queries (no separate ID query), only for items not cached yet.
			_prime_post_caches( $ids, false, true );
			$ok = current_user_can( 'edit_posts' ) ? array( 'publish', 'draft', 'private' ) : array( 'publish' );
			foreach ( $ids as $id ) {
				$p = get_post( $id );
				if ( $p && in_array( $p->post_status, $ok, true ) && in_array( $p->post_type, array( ELTLMS_Content::LESSON, ELTLMS_Content::QUIZ ), true ) ) {
					$live[ $id ] = true;
				}
			}
		}
		$out = array();
		foreach ( $raw as $s ) {
			$items = array();
			foreach ( (array) ( $s['items'] ?? array() ) as $id ) {
				if ( isset( $live[ (int) $id ] ) ) {
					$items[] = (int) $id;
				}
			}
			$out[] = array(
				'title' => sanitize_text_field( (string) ( $s['title'] ?? '' ) ),
				'items' => $items,
			);
		}
		return self::$cache[ $key ] = $out;
	}

	public static function items( $course_id ) {
		$ids = array();
		foreach ( self::curriculum( $course_id ) as $s ) {
			foreach ( $s['items'] as $id ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Numbers like "2.3": section, then position inside the section.
	 *
	 * @param int $course_id Course.
	 * @return array item ID => label.
	 */
	public static function numbers( $course_id ) {
		$n = array();
		foreach ( self::curriculum( $course_id ) as $si => $s ) {
			foreach ( $s['items'] as $ii => $id ) {
				$n[ $id ] = ( $si + 1 ) . '.' . ( $ii + 1 );
			}
		}
		return $n;
	}

	public static function course_of( $item_id ) {
		return (int) get_post_meta( (int) $item_id, '_eltlms_course', true );
	}

	public static function is_item( $post ) {
		$post = get_post( $post );
		return $post && in_array( $post->post_type, array( ELTLMS_Content::LESSON, ELTLMS_Content::QUIZ ), true );
	}

	public static function is_preview( $item_id ) {
		return (bool) get_post_meta( (int) $item_id, '_eltlms_preview', true );
	}

	public static function neighbours( $item_id ) {
		$ids = self::items( self::course_of( $item_id ) );
		$i   = array_search( (int) $item_id, $ids, true );
		if ( false === $i ) {
			return array( 0, 0 );
		}
		return array( $ids[ $i - 1 ] ?? 0, $ids[ $i + 1 ] ?? 0 );
	}

	/* Enrolment ----------------------------------------------------------- */

	/**
	 * Enrolment row and completed items for one user and course, in one query.
	 */
	private static function load_student( $user_id, $course_id ) {
		global $wpdb;
		$t    = ELTLMS_Install::tables();
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; results are cached per request in self::$cache.
			$wpdb->prepare(
				"SELECT 'e' AS k, last_item_id AS v, enrolled_at, completed_at FROM %i WHERE user_id = %d AND course_id = %d
				UNION ALL SELECT 'p' AS k, item_id AS v, NULL, NULL FROM %i WHERE user_id = %d AND course_id = %d",
				$t['enrolments'],
				$user_id,
				$course_id,
				$t['progress'],
				$user_id,
				$course_id
			)
		);
		$enr  = null;
		$done = array();
		foreach ( (array) $rows as $r ) {
			if ( 'e' === $r->k ) {
				$enr = (object) array(
					'user_id'      => $user_id,
					'course_id'    => $course_id,
					'enrolled_at'  => $r->enrolled_at,
					'completed_at' => $r->completed_at,
					'last_item_id' => (int) $r->v,
				);
			} else {
				$done[ (int) $r->v ] = true;
			}
		}
		self::$cache[ "enr_{$user_id}_{$course_id}" ]  = $enr;
		self::$cache[ "done_{$user_id}_{$course_id}" ] = $done;
	}

	/**
	 * Load what a list of courses needs in a fixed number of queries, however many
	 * courses there are: the courses, their lessons and quizzes, their pictures and,
	 * with a user, that student's enrolments and progress.
	 *
	 * @param int[] $course_ids Course IDs.
	 * @param int   $user_id    Optional student.
	 */
	public static function prime_courses( array $course_ids, $user_id = 0 ) {
		global $wpdb;
		$course_ids = array_values( array_unique( array_filter( array_map( 'intval', $course_ids ) ) ) );
		if ( ! $course_ids ) {
			return;
		}
		_prime_post_caches( $course_ids, false, true );
		$more = array();
		foreach ( $course_ids as $id ) {
			$raw = json_decode( (string) get_post_meta( $id, '_eltlms_curriculum', true ), true );
			foreach ( is_array( $raw ) ? $raw : array() as $s ) {
				foreach ( (array) ( $s['items'] ?? array() ) as $item ) {
					$more[] = (int) $item;
				}
			}
			$more[] = (int) get_post_meta( $id, '_thumbnail_id', true );
		}
		$more = array_values( array_unique( array_filter( $more ) ) );
		if ( $more ) {
			_prime_post_caches( $more, false, true );
		}
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return;
		}
		$t    = ELTLMS_Install::tables();
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables; cached per request in self::$cache.
			$wpdb->prepare(
				"SELECT 'e' AS k, course_id, last_item_id AS v, enrolled_at, completed_at FROM %i WHERE user_id = %d
				UNION ALL SELECT 'p' AS k, course_id, item_id AS v, NULL, NULL FROM %i WHERE user_id = %d",
				$t['enrolments'],
				$user_id,
				$t['progress'],
				$user_id
			)
		);
		$enr  = array();
		$done = array();
		foreach ( (array) $rows as $r ) {
			$c = (int) $r->course_id;
			if ( 'e' === $r->k ) {
				$enr[ $c ] = (object) array(
					'user_id'      => $user_id,
					'course_id'    => $c,
					'enrolled_at'  => $r->enrolled_at,
					'completed_at' => $r->completed_at,
					'last_item_id' => (int) $r->v,
				);
			} else {
				$done[ $c ][ (int) $r->v ] = true;
			}
		}
		foreach ( $course_ids as $c ) {
			if ( ! array_key_exists( "enr_{$user_id}_{$c}", self::$cache ) ) {
				self::$cache[ "enr_{$user_id}_{$c}" ]  = $enr[ $c ] ?? null;
				self::$cache[ "done_{$user_id}_{$c}" ] = $done[ $c ] ?? array();
			}
		}
	}

	public static function enrolment( $user_id, $course_id ) {
		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		if ( ! $user_id || ! $course_id ) {
			return null;
		}
		$key = "enr_{$user_id}_{$course_id}";
		if ( ! array_key_exists( $key, self::$cache ) ) {
			self::load_student( $user_id, $course_id );
		}
		return self::$cache[ $key ];
	}

	public static function is_enrolled( $user_id, $course_id ) {
		return null !== self::enrolment( $user_id, $course_id );
	}

	/**
	 * Enrol a user in a course.
	 *
	 * @param int  $user_id   User.
	 * @param int  $course_id Course.
	 * @param bool $force     Skip the eltlms_can_enrol filter, for enrolments made by
	 *                        staff (Courses › Students), e.g. into a paid course.
	 * @return bool True when a new enrolment was made.
	 */
	public static function enrol( $user_id, $course_id, $force = false ) {
		global $wpdb;
		if ( ! $user_id || get_post_type( $course_id ) !== ELTLMS_Content::COURSE || self::is_enrolled( $user_id, $course_id ) ) {
			return false;
		}
		/**
		 * Filters free enrolment. Pro returns false for paid courses and enrols after payment.
		 */
		if ( ! $force && ! apply_filters( 'eltlms_can_enrol', true, (int) $user_id, (int) $course_id ) ) {
			return false;
		}
		$t  = ELTLMS_Install::tables();
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
			$t['enrolments'],
			array(
				'user_id'     => (int) $user_id,
				'course_id'   => (int) $course_id,
				'enrolled_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s' )
		);
		unset( self::$cache[ "enr_{$user_id}_{$course_id}" ] );
		if ( $ok ) {
			do_action( 'eltlms_enrolled', (int) $user_id, (int) $course_id );
		}
		return (bool) $ok;
	}

	/**
	 * Remove an enrolment (for example when a subscription ends). Progress is kept,
	 * so it comes back if the student enrols again.
	 */
	public static function unenrol( $user_id, $course_id ) {
		global $wpdb;
		$t  = ELTLMS_Install::tables();
		$ok = $wpdb->delete( $t['enrolments'], array( 'user_id' => (int) $user_id, 'course_id' => (int) $course_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
		unset( self::$cache[ "enr_{$user_id}_{$course_id}" ], self::$cache[ "done_{$user_id}_{$course_id}" ] );
		if ( $ok ) {
			do_action( 'eltlms_unenrolled', (int) $user_id, (int) $course_id );
		}
		return (bool) $ok;
	}

	/**
	 * Courses a user is enrolled in, newest activity first.
	 *
	 * @param int $user_id User.
	 * @return array Rows with course_id, enrolled_at, completed_at, last_item_id.
	 */
	public static function user_enrolments( $user_id ) {
		global $wpdb;
		$t = ELTLMS_Install::tables();
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY enrolled_at DESC', $t['enrolments'], (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; results are cached per request.
	}

	/* Progress ------------------------------------------------------------ */

	public static function completed( $user_id, $course_id ) {
		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		if ( ! $user_id ) {
			return array();
		}
		$key = "done_{$user_id}_{$course_id}";
		if ( ! isset( self::$cache[ $key ] ) ) {
			self::load_student( $user_id, $course_id );
		}
		return self::$cache[ $key ];
	}

	public static function is_complete( $user_id, $item_id ) {
		return isset( self::completed( $user_id, self::course_of( $item_id ) )[ (int) $item_id ] );
	}

	/**
	 * @return array [ done, total, percent ]
	 */
	public static function progress( $user_id, $course_id ) {
		$items = self::items( $course_id );
		$done  = self::completed( $user_id, $course_id );
		$count = 0;
		foreach ( $items as $id ) {
			if ( isset( $done[ $id ] ) ) {
				++$count;
			}
		}
		$total = count( $items );
		return array( $count, $total, $total ? (int) floor( 100 * $count / $total ) : 0 );
	}

	public static function complete( $user_id, $item_id ) {
		global $wpdb;
		$user_id   = (int) $user_id;
		$item_id   = (int) $item_id;
		$course_id = self::course_of( $item_id );
		if ( ! $user_id || ! $course_id || ! self::is_enrolled( $user_id, $course_id ) ) {
			return false;
		}
		$t = ELTLMS_Install::tables();
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (user_id, item_id, course_id, completed_at) VALUES (%d, %d, %d, %s)', $t['progress'], $user_id, $item_id, $course_id, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
		unset( self::$cache[ "done_{$user_id}_{$course_id}" ], self::$cache[ "enr_{$user_id}_{$course_id}" ] );
		self::touch( $user_id, $item_id );

		list( $done, $total ) = self::progress( $user_id, $course_id );
		if ( $total && $done >= $total ) {
			$e = self::enrolment( $user_id, $course_id );
			if ( $e && ! $e->completed_at ) {
				$wpdb->update( $t['enrolments'], array( 'completed_at' => current_time( 'mysql', true ) ), array( 'user_id' => $user_id, 'course_id' => $course_id ), array( '%s' ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
				unset( self::$cache[ "enr_{$user_id}_{$course_id}" ] );
				do_action( 'eltlms_course_completed', $user_id, $course_id );
			}
		}
		return true;
	}

	/**
	 * Start a course again: the student keeps the enrolment but their progress is
	 * cleared, so they work through every lesson and quiz again and complete the
	 * course anew (Pro uses this to renew a certificate). Earlier quiz attempts
	 * stay in the history but no longer count for this round.
	 *
	 * @return bool True when the student was enrolled and the course was reset.
	 */
	public static function restart( $user_id, $course_id ) {
		global $wpdb;
		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		if ( ! self::is_enrolled( $user_id, $course_id ) ) {
			return false;
		}
		$t = ELTLMS_Install::tables();
		$wpdb->delete( $t['progress'], array( 'user_id' => $user_id, 'course_id' => $course_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET completed_at = NULL, last_item_id = 0 WHERE user_id = %d AND course_id = %d', $t['enrolments'], $user_id, $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
		update_user_meta( $user_id, '_eltlms_restart_' . $course_id, current_time( 'mysql', true ) );
		unset( self::$cache[ "enr_{$user_id}_{$course_id}" ], self::$cache[ "done_{$user_id}_{$course_id}" ] );
		do_action( 'eltlms_course_restarted', $user_id, $course_id );
		return true;
	}

	/**
	 * Quiz attempts made before this time (UTC, MySQL format) belong to an
	 * earlier round of the course.
	 */
	public static function since( $user_id, $course_id ) {
		$at = $user_id && $course_id ? (string) get_user_meta( (int) $user_id, '_eltlms_restart_' . (int) $course_id, true ) : '';
		return '' !== $at ? $at : '1970-01-01 00:00:00';
	}

	/**
	 * Remember the last opened item, for "Continue where you left off".
	 */
	public static function touch( $user_id, $item_id ) {
		global $wpdb;
		$course_id = self::course_of( $item_id );
		$e         = self::enrolment( $user_id, $course_id );
		if ( ! $e || (int) $e->last_item_id === (int) $item_id ) {
			return;
		}
		$t = ELTLMS_Install::tables();
		$wpdb->update( $t['enrolments'], array( 'last_item_id' => (int) $item_id ), array( 'user_id' => (int) $user_id, 'course_id' => $course_id ), array( '%d' ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
		$e->last_item_id = (int) $item_id;
	}

	/**
	 * The item to continue with: the last opened one if unfinished, else the first unfinished one.
	 */
	public static function next_item( $user_id, $course_id ) {
		$items = self::items( $course_id );
		$done  = self::completed( $user_id, $course_id );
		$e     = self::enrolment( $user_id, $course_id );
		if ( $e && $e->last_item_id && in_array( (int) $e->last_item_id, $items, true ) && ! isset( $done[ (int) $e->last_item_id ] ) ) {
			return (int) $e->last_item_id;
		}
		foreach ( $items as $id ) {
			if ( ! isset( $done[ $id ] ) ) {
				return $id;
			}
		}
		return $items[0] ?? 0;
	}

	/* Access -------------------------------------------------------------- */

	public static function can_view( $user_id, $item_id ) {
		if ( current_user_can( 'edit_post', $item_id ) ) {
			return true;
		}
		if ( self::is_preview( $item_id ) ) {
			return true;
		}
		$allowed = self::is_enrolled( $user_id, self::course_of( $item_id ) );
		/**
		 * Filters access to a lesson or quiz. Pro uses this for drip and prerequisites.
		 */
		return (bool) apply_filters( 'eltlms_can_view', $allowed, (int) $user_id, (int) $item_id );
	}

	/* Quizzes ------------------------------------------------------------- */

	/**
	 * Questions: [ [ 'q' => string, 'options' => string[], 'correct' => int ], ... ].
	 * True/false questions are stored as two options.
	 */
	/**
	 * Question types. The free plugin has single choice (which also covers
	 * true/false). Add-ons may register more (Pro: written answers); they must
	 * then render them (eltlms_render_question_input) and grade them
	 * (eltlms_grade_question). Questions of unknown types are skipped.
	 *
	 * @return array type => label.
	 */
	public static function question_types() {
		return (array) apply_filters( 'eltlms_question_types', array( 'choice' => __( 'Single choice', 'elearningtheme-lms' ) ) );
	}

	/**
	 * Questions stored on a quiz (or on any post that uses the question editor,
	 * such as a Pro question bank). Each question has a stable key and a type.
	 *
	 * @param int $post_id Quiz or bank.
	 * @return array
	 */
	public static function questions( $post_id ) {
		$raw   = json_decode( (string) get_post_meta( (int) $post_id, '_eltlms_questions', true ), true );
		$types = self::question_types();
		$out   = array();
		foreach ( (array) $raw as $n => $q ) {
			$type = (string) ( $q['type'] ?? 'choice' );
			$text = trim( (string) ( $q['q'] ?? '' ) );
			if ( ! isset( $types[ $type ] ) || '' === $text ) {
				continue;
			}
			$item = array(
				'key'  => (int) $post_id . ':' . substr( md5( $n . '|' . $text ), 0, 10 ),
				'type' => $type,
				'q'    => $text,
			);
			if ( 'choice' === $type ) {
				$opts = array_values( array_filter( array_map( 'strval', (array) ( $q['options'] ?? array() ) ), 'strlen' ) );
				if ( count( $opts ) < 2 ) {
					continue;
				}
				$item['options'] = $opts;
				$item['correct'] = min( max( 0, (int) ( $q['correct'] ?? 0 ) ), count( $opts ) - 1 );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * How many questions one attempt has (Pro may ask a random subset).
	 */
	public static function question_count( $quiz_id ) {
		return (int) apply_filters( 'eltlms_quiz_question_count', count( self::questions( $quiz_id ) ), (int) $quiz_id );
	}

	/**
	 * The questions one student gets in this attempt, in order. Add-ons may draw
	 * from banks, pick a subset or shuffle answers here; they must return the
	 * same set for the form and for grading.
	 */
	public static function attempt_questions( $quiz_id, $user_id ) {
		return array_values( (array) apply_filters( 'eltlms_quiz_questions', self::questions( $quiz_id ), (int) $quiz_id, (int) $user_id ) );
	}

	public static function pass_mark( $quiz_id ) {
		$p = (int) get_post_meta( (int) $quiz_id, '_eltlms_pass', true );
		return $p > 0 ? min( 100, $p ) : 80;
	}

	public static function last_attempt( $user_id, $quiz_id ) {
		global $wpdb;
		if ( ! $user_id ) {
			return null;
		}
		$t = ELTLMS_Install::tables();
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND quiz_id = %d AND created_at >= %s ORDER BY id DESC LIMIT 1', $t['attempts'], (int) $user_id, (int) $quiz_id, self::since( $user_id, self::course_of( $quiz_id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; results are cached per request; read once per quiz page.
	}

	/**
	 * Grade one answer: true, false, or null when a person must review it.
	 */
	public static function grade_one( array $q, $answer ) {
		if ( 'choice' === $q['type'] ) {
			return (int) $answer === (int) $q['correct'];
		}
		$r = apply_filters( 'eltlms_grade_question', null, $q, $answer );
		return null === $r ? null : (bool) $r;
	}

	/**
	 * Grade on the server and store the attempt with a snapshot of the questions
	 * the student saw, so a review later shows exactly that attempt.
	 *
	 * @param int    $user_id User.
	 * @param int    $quiz_id Quiz.
	 * @param array  $answers Question position => answer (option index, or text).
	 * @param string $status  '' to grade, or 'expired' when the time limit passed.
	 * @return object|null The stored attempt.
	 */
	public static function grade( $user_id, $quiz_id, array $answers, $status = '' ) {
		global $wpdb;
		$qs      = self::attempt_questions( $quiz_id, $user_id );
		$score   = 0;
		$pending = false;
		$given   = array();
		$marks   = array();
		foreach ( $qs as $i => $q ) {
			$a           = $answers[ $i ] ?? null;
			$given[ $i ] = 'choice' === $q['type'] ? ( null === $a ? -1 : (int) $a ) : (string) $a;
			$m           = 'expired' === $status ? false : self::grade_one( $q, $given[ $i ] );
			$marks[ $i ] = $m;
			if ( null === $m ) {
				$pending = true;
			} elseif ( $m ) {
				++$score;
			}
		}
		$total  = count( $qs );
		$status = $status ? $status : ( $pending ? 'pending' : 'graded' );
		$passed = 'graded' === $status && $total && ( 100 * $score / $total ) >= self::pass_mark( $quiz_id );
		$t      = ELTLMS_Install::tables();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own table; the request cache is cleared below.
			$t['attempts'],
			array(
				'user_id'    => (int) $user_id,
				'quiz_id'    => (int) $quiz_id,
				'score'      => $score,
				'total'      => $total,
				'passed'     => $passed ? 1 : 0,
				'answers'    => wp_json_encode( array( 'v' => 2, 'questions' => $qs, 'given' => $given, 'marks' => $marks, 'feedback' => array() ) ),
				'status'     => $status,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		$id = (int) $wpdb->insert_id;
		if ( $passed ) {
			self::complete( $user_id, $quiz_id );
		}
		/**
		 * After an attempt is stored. Pro clears the student's draw and timer here.
		 */
		do_action( 'eltlms_quiz_graded', $id, (int) $user_id, (int) $quiz_id, $status, $passed );
		return self::last_attempt( $user_id, $quiz_id );
	}

	public static function attempt( $attempt_id ) {
		global $wpdb;
		$t = ELTLMS_Install::tables();
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t['attempts'], (int) $attempt_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
	}

	public static function attempts_count( $user_id, $quiz_id ) {
		global $wpdb;
		$t = ELTLMS_Install::tables();
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND quiz_id = %d AND created_at >= %s', $t['attempts'], (int) $user_id, (int) $quiz_id, self::since( $user_id, self::course_of( $quiz_id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
	}

	/**
	 * An attempt decoded: the questions as the student saw them, the answers,
	 * the marks (true, false or null = waiting for review) and teacher feedback.
	 * Attempts stored before 0.1.7 hold only the chosen options.
	 */
	public static function attempt_detail( $row ) {
		$d = json_decode( (string) $row->answers, true );
		if ( is_array( $d ) && isset( $d['v'] ) ) {
			return $d + array( 'questions' => array(), 'given' => array(), 'marks' => array(), 'feedback' => array() );
		}
		$qs    = self::questions( (int) $row->quiz_id );
		$given = is_array( $d ) ? $d : array();
		$marks = array();
		foreach ( $qs as $i => $q ) {
			$marks[ $i ] = isset( $given[ $i ] ) && (int) $given[ $i ] === $q['correct'];
		}
		return array( 'questions' => $qs, 'given' => $given, 'marks' => $marks, 'feedback' => array() );
	}

	/**
	 * Finish a teacher's review of an attempt that waits for one.
	 *
	 * @param int   $attempt_id Attempt.
	 * @param array $marks      Question position => true/false, for every question waiting for review.
	 * @param array $feedback   Question position => feedback text (optional).
	 * @return bool
	 */
	public static function review_attempt( $attempt_id, array $marks, array $feedback = array() ) {
		global $wpdb;
		$row = self::attempt( $attempt_id );
		if ( ! $row || 'pending' !== $row->status ) {
			return false;
		}
		$d = self::attempt_detail( $row );
		foreach ( $d['questions'] as $i => $q ) {
			if ( null === ( $d['marks'][ $i ] ?? null ) ) {
				if ( ! isset( $marks[ $i ] ) ) {
					return false;
				}
				$d['marks'][ $i ] = (bool) $marks[ $i ];
			}
			if ( isset( $feedback[ $i ] ) && '' !== trim( (string) $feedback[ $i ] ) ) {
				$d['feedback'][ $i ] = sanitize_textarea_field( (string) $feedback[ $i ] );
			}
		}
		$total  = count( $d['questions'] );
		$score  = count( array_filter( $d['marks'] ) );
		$passed = $total && ( 100 * $score / $total ) >= self::pass_mark( (int) $row->quiz_id );
		$t      = ELTLMS_Install::tables();
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin table.
			$t['attempts'],
			array( 'score' => $score, 'passed' => $passed ? 1 : 0, 'status' => 'graded', 'answers' => wp_json_encode( $d ) ),
			array( 'id' => (int) $attempt_id ),
			array( '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
		if ( $passed ) {
			self::complete( (int) $row->user_id, (int) $row->quiz_id );
		}
		/**
		 * After a teacher finished reviewing an attempt.
		 */
		do_action( 'eltlms_quiz_reviewed', (int) $attempt_id, (int) $row->user_id, (int) $row->quiz_id, $passed );
		return true;
	}
}
