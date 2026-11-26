<?php
/**
 * Designed HTML emails with one shared 600px template that takes its colours
 * from the active design. Table layout and inline styles, so Gmail, Outlook and
 * Apple Mail show the same thing; a plain-text part is sent alongside.
 *
 * Sends: enrolment confirmation, course completed. Restyles WordPress's own
 * password-reset and new-account emails with the same template.
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Mail {

	/** @var string Plain-text part for the message being sent. */
	private static $alt = '';

	public static function init() {
		add_action( 'eltlms_enrolled', array( __CLASS__, 'enrolled' ), 10, 2 );
		add_action( 'eltlms_course_completed', array( __CLASS__, 'completed' ), 20, 2 ); // After Pro issues the certificate.
		add_filter( 'retrieve_password_notification_email', array( __CLASS__, 'password_reset' ), 10, 4 );
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'new_user' ), 10, 3 );
		add_action( 'phpmailer_init', array( __CLASS__, 'alt_body' ) );
	}

	/* Template ------------------------------------------------------------ */

	/**
	 * Design colours as hex, from the active theme and style variation.
	 */
	public static function colors() {
		$c = array(
			'base'       => '#ffffff',
			'surface'    => '#f4f2ec',
			'ink'        => '#141414',
			'muted'      => '#55534d',
			'line'       => '#d8d4ca',
			'accent'     => '#765410',
			'accent-ink' => '#ffffff',
		);
		$palette = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( array( 'color', 'palette' ) ) : array();
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( (array) ( $palette[ $origin ] ?? array() ) as $p ) {
				if ( isset( $p['slug'], $p['color'] ) && isset( $c[ $p['slug'] ] ) && preg_match( '/^#[0-9a-f]{3,8}$/i', $p['color'] ) ) {
					$c[ $p['slug'] ] = $p['color'];
				}
			}
		}
		// Email clients render best on a light card: keep the design's accent,
		// fall back to neutral light colours where the design is dark.
		$lum = static function ( $hex ) {
			$h = ltrim( $hex, '#' );
			if ( 3 === strlen( $h ) ) {
				$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
			}
			$rgb = array_map( static function ( $v ) { $v = hexdec( $v ) / 255; return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ); }, str_split( substr( $h, 0, 6 ), 2 ) );
			return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
		};
		$ratio = static function ( $a, $b ) use ( $lum ) {
			$x = $lum( $a );
			$y = $lum( $b );
			return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
		};
		$out = array(
			'card'       => '#ffffff',
			'page'       => $lum( $c['surface'] ) > 0.75 ? $c['surface'] : '#f4f4f2',
			'ink'        => $ratio( $c['ink'], '#ffffff' ) >= 7 ? $c['ink'] : '#141414',
			'muted'      => $ratio( $c['muted'], '#ffffff' ) >= 4.5 ? $c['muted'] : '#55534d',
			'line'       => $lum( $c['line'] ) > 0.4 ? $c['line'] : '#dcd9d2',
			'accent'     => $c['accent'],
			'accent-ink' => $ratio( $c['accent-ink'], $c['accent'] ) >= 4.5 ? $c['accent-ink'] : ( $ratio( '#ffffff', $c['accent'] ) >= 4.5 ? '#ffffff' : '#141414' ),
		);
		$out['link'] = $ratio( $c['accent'], '#ffffff' ) >= 4.5 ? $c['accent'] : $out['ink'];
		return apply_filters( 'eltlms_email_colors', $out );
	}

	/**
	 * Build one email.
	 *
	 * @param array $m {
	 *     @type string $preheader Short preview text.
	 *     @type string $eyebrow   Small label above the heading.
	 *     @type string $heading   Main heading.
	 *     @type array  $paragraphs Plain-text paragraphs.
	 *     @type array  $facts     Label => value rows (optional).
	 *     @type array  $button    [ label, url ] (optional).
	 *     @type array  $links     [ [ label, url ], ... ] secondary links (optional).
	 *     @type string $note      Small print under the button (optional).
	 * }
	 * @return array [ html, text ]
	 */
	/** The Site Logo (Site Editor or Customizer), or ''. */
	public static function site_logo() {
		$id = (int) get_option( 'site_logo' );
		$id = $id ? $id : (int) get_theme_mod( 'custom_logo' );
		$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		return $url ? (string) $url : '';
	}

	public static function build( array $m ) {
		$c     = self::colors();
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$font  = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
		$mono  = "ui-monospace,SFMono-Regular,Menlo,Consolas,monospace";
		$e     = static function ( $s ) { return esc_html( (string) $s ); };
		$text  = array();

		$body = '';
		if ( ! empty( $m['eyebrow'] ) ) {
			$body  .= '<p style="margin:0 0 12px;font-family:' . $mono . ';font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:' . $c['muted'] . ';">' . $e( $m['eyebrow'] ) . '</p>';
			$text[] = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $m['eyebrow'], 'UTF-8' ) : strtoupper( $m['eyebrow'] );
		}
		$body  .= '<h1 style="margin:0 0 20px;font-family:' . $font . ';font-size:28px;line-height:1.15;font-weight:700;letter-spacing:-0.5px;color:' . $c['ink'] . ';">' . $e( $m['heading'] ) . '</h1>';
		$text[] = $m['heading'];
		$text[] = '';
		foreach ( (array) ( $m['paragraphs'] ?? array() ) as $p ) {
			$body  .= '<p style="margin:0 0 16px;font-family:' . $font . ';font-size:16px;line-height:1.6;color:' . $c['ink'] . ';">' . $e( $p ) . '</p>';
			$text[] = $p;
			$text[] = '';
		}
		if ( ! empty( $m['facts'] ) ) {
			$rows = '';
			foreach ( $m['facts'] as $k => $v ) {
				$rows  .= '<tr><td style="padding:10px 16px 10px 0;border-top:1px solid ' . $c['line'] . ';font-family:' . $mono . ';font-size:11px;letter-spacing:1px;text-transform:uppercase;color:' . $c['muted'] . ';white-space:nowrap;vertical-align:top;">' . $e( $k ) . '</td><td style="padding:10px 0;border-top:1px solid ' . $c['line'] . ';font-family:' . $font . ';font-size:15px;color:' . $c['ink'] . ';">' . $e( $v ) . '</td></tr>';
				$text[] = $k . ': ' . $v;
			}
			$body  .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 24px;border-bottom:1px solid ' . $c['line'] . ';">' . $rows . '</table>';
			$text[] = '';
		}
		if ( ! empty( $m['button'] ) ) {
			list( $label, $url ) = $m['button'];
			$body  .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px;"><tr><td bgcolor="' . $c['accent'] . '" style="background:' . $c['accent'] . ';border-radius:4px;">'
				. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 26px;font-family:' . $font . ';font-size:16px;font-weight:600;line-height:1;color:' . $c['accent-ink'] . ';text-decoration:none;border-radius:4px;">' . $e( $label ) . '</a></td></tr></table>';
			$text[] = $label . ': ' . $url;
			$text[] = '';
		}
		if ( ! empty( $m['note'] ) ) {
			$body  .= '<p style="margin:0 0 16px;font-family:' . $font . ';font-size:13px;line-height:1.5;color:' . $c['muted'] . ';">' . $e( $m['note'] ) . '</p>';
			$text[] = $m['note'];
			$text[] = '';
		}
		if ( ! empty( $m['links'] ) ) {
			$l = array();
			foreach ( $m['links'] as $link ) {
				$l[]    = '<a href="' . esc_url( $link[1] ) . '" style="color:' . $c['link'] . ';text-decoration:underline;">' . $e( $link[0] ) . '</a>';
				$text[] = $link[0] . ': ' . $link[1];
			}
			$body .= '<p style="margin:8px 0 0;font-family:' . $font . ';font-size:14px;line-height:1.6;color:' . $c['ink'] . ';">' . implode( ' &nbsp;·&nbsp; ', $l ) . '</p>';
		}

		$home   = home_url( '/' );
		/**
		 * The logo at the top of every email: the site logo by default, '' for the site name as text.
		 */
		$logo = (string) apply_filters( 'eltlms_email_logo', self::site_logo() );
		/* translators: %s: site name. */
		$foot   = sprintf( __( 'You get this email because you have an account at %s.', 'elearningtheme-lms' ), $site );
		$text[] = '';
		$text[] = '—';
		$text[] = $site . ' · ' . $home;
		$text[] = $foot;

		$pre  = (string) ( $m['preheader'] ?? '' );
		$html = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . $e( $m['heading'] ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:' . $c['page'] . ';-webkit-text-size-adjust:100%;">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $e( $pre ) . '&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="' . $c['page'] . '" style="background:' . $c['page'] . ';"><tr><td align="center" style="padding:24px 8px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;">'
			. '<tr><td style="padding:0 4px 16px;font-family:' . $font . ';font-size:17px;font-weight:700;letter-spacing:-0.2px;color:' . $c['ink'] . ';"><a href="' . esc_url( $home ) . '" style="color:' . $c['ink'] . ';text-decoration:none;">' . ( '' !== $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" height="40" style="display:block;height:40px;width:auto;max-width:240px;border:0;">' : $e( $site ) ) . '</a></td></tr>'
			. '<tr><td bgcolor="' . $c['card'] . '" style="background:' . $c['card'] . ';border:1px solid ' . $c['line'] . ';border-top:4px solid ' . $c['accent'] . ';padding:32px 28px;">' . $body . '</td></tr>'
			. '<tr><td style="padding:20px 4px 0;font-family:' . $font . ';font-size:12px;line-height:1.6;color:' . $c['muted'] . ';">' . $e( $foot ) . '<br><a href="' . esc_url( $home ) . '" style="color:' . $c['muted'] . ';">' . $e( wp_parse_url( $home, PHP_URL_HOST ) ) . '</a></td></tr>'
			. '</table></td></tr></table></body></html>';

		return array( $html, implode( "\n", $text ) );
	}

	/**
	 * Send a built email as HTML with a plain-text alternative.
	 */
	public static function send( $to, $subject, array $m ) {
		if ( ! apply_filters( 'eltlms_send_email', true, $to, $subject, $m ) ) {
			return false;
		}
		list( $html, $text ) = self::build( $m );
		self::$alt            = $text;
		$ok                   = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		self::$alt            = '';
		return $ok;
	}

	public static function alt_body( $phpmailer ) {
		if ( '' !== self::$alt ) {
			$phpmailer->AltBody = self::$alt; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			self::$alt          = '';
		}
	}

	/* Messages ------------------------------------------------------------ */

	public static function enrolled( $user_id, $course_id ) {
		ELTLMS_Lang::as_user(
			$user_id,
			static function () use ( $user_id, $course_id ) {
				self::enrolled_message( $user_id, $course_id );
			}
		);
	}

	private static function enrolled_message( $user_id, $course_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return;
		}
		$first = ELTLMS_Data::next_item( $user_id, $course_id );
		$items = ELTLMS_Data::items( $course_id );
		$facts = array(
			/* translators: used as a label in an email. */
			__( 'Course', 'elearningtheme-lms' )  => get_the_title( $course_id ),
			__( 'Lessons', 'elearningtheme-lms' ) => (string) count( $items ),
		);
		$dur = (string) get_post_meta( $course_id, '_eltlms_duration', true );
		if ( $dur ) {
			$facts[ __( 'Duration', 'elearningtheme-lms' ) ] = $dur;
		}
		$dash = (int) get_option( 'eltlms_dashboard_page' );
		self::send(
			$u->user_email,
			/* translators: %s: course title. */
			sprintf( __( 'You’re enrolled: %s', 'elearningtheme-lms' ), get_the_title( $course_id ) ),
			array(
				/* translators: %s: course title. */
				'preheader'  => sprintf( __( 'Your first lesson of %s is ready.', 'elearningtheme-lms' ), get_the_title( $course_id ) ),
				'eyebrow'    => __( 'Enrolment confirmed', 'elearningtheme-lms' ),
				/* translators: %s: student's name. */
				'heading'    => sprintf( __( 'Welcome, %s.', 'elearningtheme-lms' ), $u->display_name ),
				'paragraphs' => array(
					/* translators: %s: course title. */
					sprintf( __( 'You’re now enrolled in %s. Your progress is saved as you go, so you can stop after any lesson and pick up where you left off.', 'elearningtheme-lms' ), get_the_title( $course_id ) ),
				),
				'facts'      => $facts,
				'button'     => array( __( 'Start the first lesson', 'elearningtheme-lms' ), $first ? get_permalink( $first ) : get_permalink( $course_id ) ),
				'links'      => array_filter( array( $dash ? array( __( 'My learning', 'elearningtheme-lms' ), get_permalink( $dash ) ) : null, array( __( 'Course page', 'elearningtheme-lms' ), get_permalink( $course_id ) ) ) ),
			)
		);
	}

	public static function completed( $user_id, $course_id ) {
		ELTLMS_Lang::as_user(
			$user_id,
			static function () use ( $user_id, $course_id ) {
				self::completed_message( $user_id, $course_id );
			}
		);
	}

	private static function completed_message( $user_id, $course_id ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			return;
		}
		/**
		 * Main button of the course-completed email: [ label, url ]. Pro links the certificate.
		 */
		$button = apply_filters( 'eltlms_completed_email_button', array( __( 'Find your next course', 'elearningtheme-lms' ), get_post_type_archive_link( ELTLMS_Content::COURSE ) ), $user_id, $course_id );
		self::send(
			$u->user_email,
			/* translators: %s: course title. */
			sprintf( __( 'You completed %s', 'elearningtheme-lms' ), get_the_title( $course_id ) ),
			array(
				'preheader'  => __( 'Every lesson done. Well done.', 'elearningtheme-lms' ),
				'eyebrow'    => __( 'Course completed', 'elearningtheme-lms' ),
				/* translators: %s: student's name. */
				'heading'    => sprintf( __( 'Well done, %s.', 'elearningtheme-lms' ), $u->display_name ),
				'paragraphs' => array(
					/* translators: %s: course title. */
					sprintf( __( 'You finished every lesson of %s. That takes patience, and it shows.', 'elearningtheme-lms' ), get_the_title( $course_id ) ),
				),
				'facts'      => array(
					__( 'Course', 'elearningtheme-lms' )    => get_the_title( $course_id ),
					__( 'Completed', 'elearningtheme-lms' ) => wp_date( get_option( 'date_format' ) ),
				),
				'button'     => $button,
				'links'      => array( array( __( 'Course page', 'elearningtheme-lms' ), get_permalink( $course_id ) ) ),
			)
		);
	}

	/**
	 * WordPress's password-reset email, in the same template.
	 */
	public static function password_reset( $defaults, $key, $user_login, $user ) {
		$url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' );
		list( $html, $text ) = self::build(
			array(
				'preheader'  => __( 'Someone asked to reset your password.', 'elearningtheme-lms' ),
				'eyebrow'    => __( 'Password reset', 'elearningtheme-lms' ),
				'heading'    => __( 'Choose a new password', 'elearningtheme-lms' ),
				'paragraphs' => array(
					/* translators: %s: username. */
					sprintf( __( 'Someone asked for a new password for the account %s. If that was you, use the button below. The link works once.', 'elearningtheme-lms' ), $user_login ),
				),
				'button'     => array( __( 'Set a new password', 'elearningtheme-lms' ), $url ),
				'note'       => __( 'If you didn’t ask for this, ignore this email; your password stays the same.', 'elearningtheme-lms' ),
			)
		);
		self::$alt              = $text;
		$defaults['message']    = $html;
		$defaults['headers']    = array( 'Content-Type: text/html; charset=UTF-8' );
		return $defaults;
	}

	/**
	 * WordPress's new-account email (also used for team invites), in the same template.
	 */
	public static function new_user( $email, $user, $blogname ) {
		if ( ! preg_match( '#(https?://\S*wp-login\.php\?\S*action=rp\S*)#', (string) $email['message'], $mm ) ) {
			return $email;
		}
		$url = rtrim( $mm[1], '>' );
		list( $html, $text ) = self::build(
			array(
				/* translators: %s: site name. */
				'preheader'  => sprintf( __( 'Your account at %s is ready.', 'elearningtheme-lms' ), $blogname ),
				'eyebrow'    => __( 'Your account', 'elearningtheme-lms' ),
				/* translators: %s: person's name. */
				'heading'    => sprintf( __( 'Welcome, %s.', 'elearningtheme-lms' ), $user->display_name ),
				'paragraphs' => array(
					/* translators: %s: site name. */
					sprintf( __( 'An account was made for you at %s. Choose a password to log in and start learning.', 'elearningtheme-lms' ), $blogname ),
				),
				/* translators: used as a label in an email. */
				'facts'      => array( __( 'Username', 'elearningtheme-lms' ) => $user->user_login ),
				'button'     => array( __( 'Choose your password', 'elearningtheme-lms' ), $url ),
				'note'       => __( 'The link works once. If it has expired, use “Lost your password?” on the login page.', 'elearningtheme-lms' ),
			)
		);
		self::$alt         = $text;
		/* translators: %s: site name. */
		$email['subject']  = sprintf( __( 'Your account at %s', 'elearningtheme-lms' ), $blogname );
		$email['message']  = $html;
		$email['headers']  = array( 'Content-Type: text/html; charset=UTF-8' );
		return $email;
	}
}
