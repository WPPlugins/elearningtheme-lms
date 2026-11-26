<?php
/**
 * Languages: the school offers several interface languages and each visitor or
 * student picks one. The choice changes what the theme and the plugins print
 * (buttons, labels, the checkout, emails); course content stays as it was written.
 *
 * Off until the site owner ticks at least one more language under Courses > Languages.
 *
 * @package ELearningThemeLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Lang {

	const OPTION = 'eltlms_languages';
	const COOKIE = 'eltlms_lang';
	const META   = 'eltlms_locale';
	const PARAM  = 'eltlms_lang';
	const SLUG   = 'eltlms-languages';

	/** Locales forced for the current task (an email in the student's language), innermost last. */
	private static $forced = array();
	/** Whether WordPress itself switched for each forced locale. */
	private static $core = array();
	private static $busy = false;
	/** The visitor's choice once the current user is known (after init), so it is worked out once per request. */
	private static $known = null;

	public static function init() {
		add_filter( 'locale', array( __CLASS__, 'locale' ) );
		add_filter( 'determine_locale', array( __CLASS__, 'forced' ) );
		add_action( 'init', array( __CLASS__, 'handle' ), 1 );
		add_filter( 'hooked_block_types', array( __CLASS__, 'hook_header' ), 10, 4 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_post_eltlms_save_languages', array( __CLASS__, 'save' ) );
		}
	}

	/** Names of the languages the plugin ships with, in the language itself. */
	private static function names() {
		return array(
			'en_US' => 'English',
			'es_ES' => 'Español',
			'fr_FR' => 'Français',
			'de_DE' => 'Deutsch',
			'pt_PT' => 'Português',
			'it_IT' => 'Italiano',
			'hu_HU' => 'Magyar',
		);
	}

	/** The site's own language, without our filter. */
	public static function site() {
		$l = (string) get_option( 'WPLANG' );
		return '' === $l ? 'en_US' : $l;
	}

	private static function settings() {
		$o = get_option( self::OPTION );
		return is_array( $o ) ? $o : array();
	}

	/**
	 * The languages on offer: locale => name. One entry (the site language) means the feature is off.
	 */
	public static function enabled() {
		$names = self::names();
		$site  = self::site();
		$out   = array( $site => $names[ $site ] ?? $site );
		foreach ( (array) ( self::settings()['list'] ?? array() ) as $locale => $label ) {
			if ( is_string( $locale ) && preg_match( '/^[a-z]{2,3}(_[A-Za-z0-9_]+)?$/', $locale ) ) {
				$out[ $locale ] = '' !== (string) $label ? (string) $label : ( $names[ $locale ] ?? $locale );
			}
		}
		/**
		 * The languages visitors and students can choose from: locale => name shown in the switcher.
		 */
		return (array) apply_filters( 'eltlms_languages', $out );
	}

	public static function is_on() {
		return count( self::enabled() ) > 1;
	}

	/** A student's saved language, or '' (then they get the site language). */
	public static function of_user( $user_id ) {
		$l = (string) get_user_meta( (int) $user_id, self::META, true );
		return '' !== $l && isset( self::enabled()[ $l ] ) ? $l : '';
	}

	/** This visitor's choice, or ''. A logged-in student's saved choice wins over the cookie. */
	private static function choice() {
		if ( null !== self::$known ) {
			return self::$known;
		}
		if ( self::$busy ) {
			return '';
		}
		self::$busy = true;
		$l          = '';
		if ( self::is_on() ) {
			if ( function_exists( 'is_user_logged_in' ) && function_exists( 'wp_get_current_user' ) && is_user_logged_in() ) {
				$l = self::of_user( get_current_user_id() );
			}
			if ( '' === $l && isset( $_COOKIE[ self::COOKIE ] ) ) {
				$c = preg_replace( '/[^A-Za-z0-9_]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- reduced to a locale code and checked against the list.
				$l = isset( self::enabled()[ $c ] ) ? $c : '';
			}
		}
		self::$busy = false;
		if ( did_action( 'init' ) ) {
			self::$known = $l;
		}
		return $l;
	}

	/** The front end in the visitor's language; the admin keeps the site (or profile) language. */
	public static function locale( $locale ) {
		if ( self::$forced ) {
			return end( self::$forced );
		}
		if ( is_admin() ) {
			return $locale;
		}
		$c = self::choice();
		return '' !== $c ? $c : $locale;
	}

	public static function forced( $locale ) {
		return self::$forced ? end( self::$forced ) : $locale;
	}

	/**
	 * The link that switches to a language. A logged-in student's choice is saved with
	 * their account, so their link carries a nonce; a visitor's link only sets a cookie
	 * in their own browser and has none, so it keeps working on cached pages.
	 */
	public static function url( $code ) {
		$url = add_query_arg( self::PARAM, $code, remove_query_arg( array( self::PARAM, '_eltlms_lang_nonce' ) ) );
		return is_user_logged_in() ? add_query_arg( '_eltlms_lang_nonce', wp_create_nonce( 'eltlms_lang' ), $url ) : $url;
	}

	/** A click on the switcher: remember the language, then show the same page without the parameters. */
	public static function handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below for logged-in users; a visitor's choice only sets a cookie in their own browser.
		if ( is_admin() || ! isset( $_GET[ self::PARAM ] ) || ! self::is_on() ) {
			return;
		}
		$l = preg_replace( '/[^A-Za-z0-9_]/', '', sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$ok = isset( self::enabled()[ $l ] );
		if ( $ok && is_user_logged_in() ) {
			$nonce = isset( $_GET['_eltlms_lang_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_eltlms_lang_nonce'] ) ) : '';
			$ok    = (bool) wp_verify_nonce( $nonce, 'eltlms_lang' ) && current_user_can( 'read' );
			if ( $ok ) {
				update_user_meta( get_current_user_id(), self::META, $l );
			}
		}
		if ( $ok && ! headers_sent() ) {
			setcookie( self::COOKIE, $l, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		nocache_headers();
		wp_safe_redirect( remove_query_arg( array( self::PARAM, '_eltlms_lang_nonce' ) ), 303 );
		exit;
	}

	/**
	 * The switcher: a native disclosure, no JavaScript.
	 */
	public static function switcher() {
		$langs = self::enabled();
		if ( count( $langs ) < 2 ) {
			return '';
		}
		$now = get_locale();
		$now = isset( $langs[ $now ] ) ? $now : self::site();
		$li  = '';
		foreach ( $langs as $code => $name ) {
			$tag = str_replace( '_', '-', $code );
			$li .= '<li><a href="' . esc_url( self::url( $code ) ) . '" lang="' . esc_attr( $tag ) . '" hreflang="' . esc_attr( $tag ) . '" rel="nofollow"'
				. ( $code === $now ? ' aria-current="true"' : '' ) . '>' . esc_html( $name ) . '</a></li>';
		}
		return '<details class="eltlms-lang"><summary>' . ELTLMS_Render::icon( 'globe', 16 )
			. '<span class="screen-reader-text">' . esc_html__( 'Language', 'elearningtheme-lms' ) . ': </span><span>' . esc_html( $langs[ $now ] ) . '</span>'
			. ELTLMS_Render::icon( 'chevron', 12 ) . '</summary><ul>' . $li . '</ul></details>';
	}

	/** With "show in the header" on, the switcher follows the navigation in the header. */
	public static function hook_header( $hooked, $position, $anchor, $context ) {
		if ( 'after' !== $position || 'core/navigation' !== $anchor || ! self::is_on() || empty( self::settings()['header'] ) ) {
			return $hooked;
		}
		$header = ( $context instanceof WP_Block_Template && 'wp_template_part' === $context->type && 'header' === $context->area )
			|| ( is_array( $context ) && in_array( 'core/template-part/header', (array) ( $context['blockTypes'] ?? array() ), true ) );
		if ( $header ) {
			$hooked[] = ELTLMS_Blocks::NS . 'language-switcher';
		}
		return $hooked;
	}

	/* Emails -------------------------------------------------------------- */

	/**
	 * Run something (build and send an email) in a student's language.
	 *
	 * @param int      $user_id The student.
	 * @param callable $fn      What to run.
	 * @return mixed Whatever $fn returns.
	 */
	public static function as_user( $user_id, $fn ) {
		$locale = self::of_user( $user_id );
		if ( '' === $locale || $locale === determine_locale() ) {
			return $fn();
		}
		self::$forced[] = $locale;
		$core           = function_exists( 'switch_to_locale' ) && switch_to_locale( $locale );
		self::$core[]   = $core;
		if ( ! $core ) {
			self::reload( $locale ); // No WordPress language pack for it: our own translations still switch.
		}
		try {
			return $fn();
		} finally {
			array_pop( self::$forced );
			if ( array_pop( self::$core ) ) {
				restore_previous_locale();
			} else {
				self::reload( determine_locale() );
			}
		}
	}

	private static function reload( $locale ) {
		if ( class_exists( 'WP_Translation_Controller' ) ) {
			WP_Translation_Controller::get_instance()->set_locale( $locale );
		}
		/**
		 * Text domains to reload when an email is written in a student's language.
		 */
		foreach ( (array) apply_filters( 'eltlms_text_domains', array( 'elearningtheme-lms' ) ) as $domain ) {
			unload_textdomain( $domain, true );
		}
		ELTLMS_Install::load_textdomain();
		/**
		 * Fires after the plugin's translations were reloaded for another language.
		 */
		do_action( 'eltlms_locale_switched', $locale );
	}

	/* Settings ------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . ELTLMS_Content::COURSE,
			__( 'Languages', 'elearningtheme-lms' ),
			__( 'Languages', 'elearningtheme-lms' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
	}

	/** Every language we can offer: bundled translations and installed WordPress languages. */
	private static function available() {
		$names = self::names();
		$out   = array( 'en_US' => $names['en_US'] );
		foreach ( (array) glob( ELTLMS_DIR . '/languages/*.mo' ) as $f ) {
			if ( preg_match( '/-([a-z]{2,3}_[A-Z]{2})\.mo$/', (string) $f, $m ) ) {
				$out[ $m[1] ] = $names[ $m[1] ] ?? $m[1];
			}
		}
		$installed = get_available_languages();
		$installed[] = self::site();
		$known       = array();
		if ( array_diff( $installed, array_keys( $out ) ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$known = wp_get_available_translations();
		}
		foreach ( $installed as $l ) {
			if ( ! isset( $out[ $l ] ) ) {
				$out[ $l ] = isset( $known[ $l ]['native_name'] ) ? $known[ $l ]['native_name'] : $l;
			}
		}
		return $out;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$site = self::site();
		$on   = self::enabled();
		$s    = self::settings();
		echo '<div class="wrap"><h1>' . esc_html__( 'Languages', 'elearningtheme-lms' ) . '</h1>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Languages saved.', 'elearningtheme-lms' ) . '</p></div>';
		}
		echo '<p style="max-width:46rem">' . esc_html__( 'Let visitors and students choose their language. The buttons, labels and emails of your school follow their choice; your course titles and lessons stay as you wrote them. The admin stays in your site language.', 'elearningtheme-lms' ) . '</p>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eltlms_save_languages' );
		echo '<input type="hidden" name="action" value="eltlms_save_languages">'
			. '<table class="form-table" role="presentation"><tr><th scope="row">' . esc_html__( 'Languages on offer', 'elearningtheme-lms' ) . '</th><td><fieldset>';
		foreach ( self::available() as $code => $name ) {
			$is_site = $code === $site;
			echo '<label style="display:block;margin:.3rem 0"><input type="checkbox" name="langs[]" value="' . esc_attr( $code ) . '"' . checked( isset( $on[ $code ] ), true, false ) . disabled( $is_site, true, false ) . '> '
				. esc_html( $name ) . ' <code>' . esc_html( $code ) . '</code>' . ( $is_site ? ' — ' . esc_html__( 'site language', 'elearningtheme-lms' ) : '' ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Tick at least one more language to switch this on. WordPress downloads its own translation for each one, so dates are right too.', 'elearningtheme-lms' ) . '</p></fieldset></td></tr>'
			. '<tr><th scope="row">' . esc_html__( 'Switcher', 'elearningtheme-lms' ) . '</th><td><label><input type="checkbox" name="header" value="1"' . checked( ! isset( $s['header'] ) || ! empty( $s['header'] ), true, false ) . '> '
			. esc_html__( 'Show the language switcher in the site header, after the menu', 'elearningtheme-lms' ) . '</label>'
			. '<p class="description">' . esc_html__( 'It is always on the My learning page. To put it somewhere else, add the “Language switcher” block in the Site Editor.', 'elearningtheme-lms' ) . '</p></td></tr></table>';
		submit_button();
		echo '</form></div>';
	}

	public static function save() {
		check_admin_referer( 'eltlms_save_languages' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'elearningtheme-lms' ), 403 );
		}
		$all  = self::available();
		$site = self::site();
		$list = array();
		foreach ( isset( $_POST['langs'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['langs'] ) ) : array() as $code ) {
			if ( isset( $all[ $code ] ) && $code !== $site ) {
				$list[ $code ] = $all[ $code ];
			}
		}
		update_option( self::OPTION, array( 'list' => $list, 'header' => ! empty( $_POST['header'] ) ) );
		// WordPress's own translations (dates, month names), when the site may install them.
		if ( $list && current_user_can( 'install_languages' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			if ( wp_can_install_language_pack() ) {
				$have = get_available_languages();
				foreach ( array_keys( $list ) as $code ) {
					if ( 'en_US' !== $code && ! in_array( $code, $have, true ) ) {
						wp_download_language_pack( $code );
					}
				}
			}
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => ELTLMS_Content::COURSE, 'page' => self::SLUG, 'saved' => 1 ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
