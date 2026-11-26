<?php
/**
 * Form actions: enrol, mark complete, submit a quiz. Plain POST forms with
 * nonces, so the front end needs no JavaScript. Each ends in a redirect.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Actions {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'remember_last_item' ), 20 );
	}

	/**
	 * Hidden fields for an action form.
	 *
	 * @param string $action enrol|complete|quiz.
	 * @param int    $id     Course or item ID.
	 * @return string
	 */
	public static function fields( $action, $id ) {
		return sprintf(
			'<input type="hidden" name="eltlms_action" value="%1$s"><input type="hidden" name="eltlms_id" value="%2$d">%3$s',
			esc_attr( $action ),
			(int) $id,
			wp_nonce_field( 'eltlms_' . $action . '_' . (int) $id, 'eltlms_nonce', false, false )
		);
	}

	public static function handle() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['eltlms_action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['eltlms_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id     = isset( $_POST['eltlms_id'] ) ? absint( $_POST['eltlms_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce  = isset( $_POST['eltlms_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['eltlms_nonce'] ) ) : '';

		if ( ! in_array( $action, array( 'enrol', 'complete', 'quiz' ), true ) || ! $id ) {
			return;
		}
		$user = get_current_user_id();
		if ( ! $user ) {
			wp_safe_redirect( wp_login_url( get_permalink( $id ) ) );
			exit;
		}
		if ( ! wp_verify_nonce( $nonce, 'eltlms_' . $action . '_' . $id ) ) {
			wp_die( esc_html__( 'This form has expired. Go back, reload the page and try again.', 'elearningtheme-lms' ), '', array( 'response' => 403, 'back_link' => true ) );
		}

		if ( 'enrol' === $action ) {
			ELTLMS_Data::enrol( $user, $id );
			$first = ELTLMS_Data::next_item( $user, $id );
			self::go( $first ? get_permalink( $first ) : get_permalink( $id ) );
		}

		if ( 'complete' === $action ) {
			if ( ELTLMS_Data::can_view( $user, $id ) ) {
				ELTLMS_Data::complete( $user, $id );
			}
			$course = ELTLMS_Data::course_of( $id );
			$e      = ELTLMS_Data::enrolment( $user, $course );
			if ( $e && $e->completed_at && ! ELTLMS_Data::neighbours( $id )[1] ) {
				self::go( add_query_arg( 'completed', '1', get_permalink( $course ) ) );
			}
			$next = ELTLMS_Data::neighbours( $id )[1];
			self::go( $next ? get_permalink( $next ) : get_permalink( $course ) );
		}

		if ( 'quiz' === $action ) {
			if ( get_post_type( $id ) !== ELTLMS_Content::QUIZ || ! ELTLMS_Data::can_view( $user, $id ) ) {
				self::go( get_permalink( $id ) );
			}
			/**
			 * May this attempt be submitted? Return '' for yes, 'expired' to record it as
			 * "time ran out", or another code (no_attempts, not_started) to refuse it.
			 */
			$check = (string) apply_filters( 'eltlms_quiz_submit_check', '', $id, $user );
			if ( 'expired' === $check ) {
				ELTLMS_Data::grade( $user, $id, array(), 'expired' );
				self::go( add_query_arg( 'result', '1', get_permalink( $id ) ) . '#eltlms-result' );
			}
			if ( '' !== $check ) {
				self::go( add_query_arg( 'quiz_msg', sanitize_key( $check ), get_permalink( $id ) ) . '#eltlms-quiz' );
			}
			$answers = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; each answer is sanitised by type below.
			$raw = isset( $_POST['eltlms_answer'] ) && is_array( $_POST['eltlms_answer'] ) ? wp_unslash( $_POST['eltlms_answer'] ) : array();
			foreach ( ELTLMS_Data::attempt_questions( $id, $user ) as $qi => $q ) {
				if ( ! isset( $raw[ $qi ] ) || is_array( $raw[ $qi ] ) ) {
					continue;
				}
				$answers[ $qi ] = 'choice' === $q['type'] ? absint( $raw[ $qi ] ) : sanitize_textarea_field( mb_substr( (string) $raw[ $qi ], 0, 10000 ) );
			}
			ELTLMS_Data::grade( $user, $id, $answers );
			self::go( add_query_arg( 'result', '1', get_permalink( $id ) ) . '#eltlms-result' );
		}
	}

	private static function go( $url ) {
		wp_safe_redirect( $url, 303 );
		exit;
	}

	public static function remember_last_item() {
		if ( ! is_singular( array( ELTLMS_Content::LESSON, ELTLMS_Content::QUIZ ) ) || ! is_user_logged_in() ) {
			return;
		}
		ELTLMS_Data::touch( get_current_user_id(), get_queried_object_id() );
	}
}
