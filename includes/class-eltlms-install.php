<?php
/**
 * Database tables. Progress lives in indexed tables, not post meta, so a
 * lesson page needs a fixed, small number of queries (weight budget: <= 5).
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

final class ELTLMS_Install {

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
	}

	public static function load_textdomain() {
		// Bundled translations ship in /languages for the six launch languages; language packs override them.
		load_plugin_textdomain( 'elearningtheme-lms', false, dirname( plugin_basename( ELTLMS_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	}

	public static function tables() {
		global $wpdb;
		return array(
			'enrolments' => $wpdb->prefix . 'eltlms_enrolments',
			'progress'   => $wpdb->prefix . 'eltlms_progress',
			'attempts'   => $wpdb->prefix . 'eltlms_quiz_attempts',
		);
	}

	public static function activate() {
		self::create_tables();
		ELTLMS_Content::register();
		self::ensure_dashboard_page();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'eltlms_db_version' ) !== ELTLMS_DB_VERSION ) {
			self::create_tables();
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::tables();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$t['enrolments']} (
				user_id bigint(20) unsigned NOT NULL,
				course_id bigint(20) unsigned NOT NULL,
				enrolled_at datetime NOT NULL,
				completed_at datetime DEFAULT NULL,
				last_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (user_id,course_id),
				KEY course_id (course_id)
			) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$t['progress']} (
				user_id bigint(20) unsigned NOT NULL,
				item_id bigint(20) unsigned NOT NULL,
				course_id bigint(20) unsigned NOT NULL,
				completed_at datetime NOT NULL,
				PRIMARY KEY  (user_id,item_id),
				KEY user_course (user_id,course_id)
			) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$t['attempts']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				quiz_id bigint(20) unsigned NOT NULL,
				score smallint(5) unsigned NOT NULL DEFAULT 0,
				total smallint(5) unsigned NOT NULL DEFAULT 0,
				passed tinyint(1) unsigned NOT NULL DEFAULT 0,
				answers longtext NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'graded',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_quiz (user_id,quiz_id),
				KEY quiz_status (quiz_id,status)
			) $charset;"
		);
		update_option( 'eltlms_db_version', ELTLMS_DB_VERSION );
	}

	/**
	 * A "My learning" page holding the dashboard block, created once.
	 */
	private static function ensure_dashboard_page() {
		$id = (int) get_option( 'eltlms_dashboard_page' );
		if ( $id && get_post( $id ) ) {
			return;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'My learning', 'elearningtheme-lms' ),
				'post_name'    => 'my-learning',
				'post_content' => '<!-- wp:elearningtheme-lms/dashboard /-->',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'eltlms_dashboard_page', (int) $id );
			// The dashboard has its own heading; use the theme's title-less template when there is one.
			if ( get_block_template( get_stylesheet() . '//page-wide' ) ) {
				update_post_meta( $id, '_wp_page_template', 'page-wide' );
			}
		}
	}
}
