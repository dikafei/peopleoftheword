<?php
/**
 * Plugin Name: POTW Lesson Importer
 * Description: Imports posts (with their PDF / Word files) from the old People of the Word WordPress.com site into Lessons. Re-runnable: syncs only new and edited posts, or re-imports everything.
 * Version:     1.0.0
 * Author:      People of the Word
 * Requires PHP: 7.4
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POTW_LI_VERSION', '1.0.0' );
define( 'POTW_LI_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-parser.php';
require_once __DIR__ . '/includes/class-docx.php';
require_once __DIR__ . '/includes/class-doc.php';
require_once __DIR__ . '/includes/class-source.php';
require_once __DIR__ . '/includes/class-importer.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-diagnostics.php';

POTW_LI_Admin::init();
POTW_LI_Diagnostics::init();

add_action( 'potw_li_daily', array( 'POTW_LI_Importer', 'cron_daily' ) );
add_action( 'potw_li_work', array( 'POTW_LI_Importer', 'cron_work' ) );

/** Keep the daily event in step with the "Automatic sync" setting. */
function potw_li_sync_cron() {
	$on   = ! empty( POTW_LI_Importer::settings()['auto_sync'] );
	$next = wp_next_scheduled( 'potw_li_daily' );
	if ( $on && ! $next ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'potw_li_daily' );
	} elseif ( ! $on && $next ) {
		wp_clear_scheduled_hook( 'potw_li_daily' );
	}
}

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'potw_li_daily' );
		wp_clear_scheduled_hook( 'potw_li_work' );
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli.php';
	WP_CLI::add_command( 'potw-lessons', 'POTW_LI_CLI' );
}
