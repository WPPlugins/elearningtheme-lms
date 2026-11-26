<?php
/**
 * Plugin Name:       eLearningTheme LMS
 * Plugin URI:        https://elearningtheme.com
 * Description:       A lightweight LMS: courses, sections, lessons, quizzes graded on the server, progress and a student dashboard. Made for the eLearningTheme block theme, works with any block theme.
 * Version:           0.2.1
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            eLearningTheme
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elearningtheme-lms
 *
 * @package ELTLMS
 */

defined( 'ABSPATH' ) || exit;

define( 'ELTLMS_VERSION', '0.2.1' );
define( 'ELTLMS_DB_VERSION', '2' );
define( 'ELTLMS_FILE', __FILE__ );
define( 'ELTLMS_DIR', __DIR__ );

require_once ELTLMS_DIR . '/includes/class-eltlms-install.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-content.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-data.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-actions.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-render.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-blocks.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-templates.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-admin.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-students.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-mail.php';
require_once ELTLMS_DIR . '/includes/class-eltlms-lang.php';

register_activation_hook( __FILE__, array( 'ELTLMS_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ELTLMS_Install', 'deactivate' ) );

ELTLMS_Install::init();
ELTLMS_Content::init();
ELTLMS_Actions::init();
ELTLMS_Blocks::init();
ELTLMS_Templates::init();
ELTLMS_Mail::init();
ELTLMS_Lang::init();
if ( is_admin() ) {
	ELTLMS_Admin::init();
	ELTLMS_Students::init();
}
