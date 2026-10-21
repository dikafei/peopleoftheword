<?php
/**
 * WP-CLI: wp potw-lessons …
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class POTW_LI_CLI {

	/**
	 * Import lessons from the old site.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Re-import every post, not just new / changed ones.
	 *
	 * [--id=<id>]
	 * : Import a single old-site post ID (always re-writes it).
	 *
	 * ## EXAMPLES
	 *
	 *     wp potw-lessons sync
	 *     wp potw-lessons sync --all
	 *     wp potw-lessons sync --id=12496
	 */
	public function sync( $args, $assoc ) {
		if ( ! empty( $assoc['id'] ) ) {
			$this->print_result( POTW_LI_Importer::import_one( (int) $assoc['id'], array( 'force' => true ) ) );
			return;
		}
		$mode = empty( $assoc['all'] ) ? 'changed' : 'all';
		$scan = POTW_LI_Importer::scan( $mode );
		if ( is_wp_error( $scan ) ) {
			WP_CLI::error( $scan->get_error_message() );
		}
		WP_CLI::log(
			sprintf(
				'Old site: %d posts — %d new, %d changed, %d unchanged, %d locked, %d orphaned. Queued: %d',
				$scan['source_total'],
				count( $scan['new'] ),
				count( $scan['changed'] ),
				$scan['unchanged'],
				count( $scan['locked'] ),
				count( $scan['orphans'] ),
				count( $scan['queue'] )
			)
		);
		POTW_LI_Importer::set_queue( $scan['queue'], 'all' === $mode );
		POTW_LI_Importer::process_for( PHP_INT_MAX, array( $this, 'print_result' ) );
		WP_CLI::success( 'Done.' );
	}

	/**
	 * Show how one old-site post would be mapped, without changing anything.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Old-site post ID.
	 */
	public function preview( $args ) {
		$r = POTW_LI_Importer::import_one( (int) $args[0], array( 'dry_run' => true ) );
		WP_CLI::log( wp_json_encode( $r['preview'] ? $r['preview'] : $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Compare old site with imported lessons.
	 */
	public function status() {
		$scan = POTW_LI_Importer::scan( 'changed' );
		if ( is_wp_error( $scan ) ) {
			WP_CLI::error( $scan->get_error_message() );
		}
		WP_CLI::log(
			sprintf(
				'Old site: %d posts — %d new, %d changed, %d unchanged, %d locked, %d orphaned.',
				$scan['source_total'],
				count( $scan['new'] ),
				count( $scan['changed'] ),
				$scan['unchanged'],
				count( $scan['locked'] ),
				count( $scan['orphans'] )
			)
		);
	}

	public function print_result( $r ) {
		WP_CLI::log( sprintf( '[%s] #%d %s %s', $r['status'], $r['source_id'], $r['title'], implode( ' | ', $r['log'] ) ) );
	}
}
