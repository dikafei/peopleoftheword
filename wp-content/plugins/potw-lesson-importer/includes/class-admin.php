<?php
/**
 * Admin screen: Lessons → Import from old site.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class POTW_LI_Admin {

	const SLUG  = 'potw-lesson-importer';
	const NONCE = 'potw_li';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_potw_li_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_potw_li_reimport', array( __CLASS__, 'reimport_single' ) );
		add_action( 'wp_ajax_potw_li_scan', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_potw_li_process', array( __CLASS__, 'ajax_process' ) );
		add_action( 'wp_ajax_potw_li_stop', array( __CLASS__, 'ajax_stop' ) );
		add_action( 'wp_ajax_potw_li_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'add_meta_boxes_' . POTW_LI_Importer::POST_TYPE, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . POTW_LI_Importer::POST_TYPE, array( __CLASS__, 'save_lock' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function cap() {
		return current_user_can( 'manage_options' );
	}

	public static function menu() {
		$hook = add_submenu_page(
			'edit.php?post_type=' . POTW_LI_Importer::POST_TYPE,
			'Import from old site',
			'Import from old site',
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'page' )
		);
		add_action(
			'admin_print_scripts-' . $hook,
			function () {
				wp_enqueue_script( 'potw-li-admin', POTW_LI_URL . 'assets/admin.js', array(), POTW_LI_VERSION, true );
				wp_localize_script(
					'potw-li-admin',
					'POTW_LI',
					array(
						'ajax'  => admin_url( 'admin-ajax.php' ),
						'nonce' => wp_create_nonce( self::NONCE ),
					)
				);
			}
		);
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! self::cap() ) {
			return;
		}
		$s        = POTW_LI_Importer::settings();
		$q        = POTW_LI_Importer::get_queue();
		$queued   = ! empty( $q['ids'] ) ? count( $q['ids'] ) : 0;
		$last     = get_option( POTW_LI_Importer::LAST_RUN );
		$imported = count( POTW_LI_Importer::imported_map() );
		$next     = wp_next_scheduled( 'potw_li_daily' );
		?>
		<div class="wrap potw-li">
			<h1>Import lessons from old site</h1>
			<p>
				Source: <code><?php echo esc_html( $s['source_site'] ); ?></code> (posts) → <strong>Lessons</strong> on this site.
				Imported so far: <strong><?php echo (int) $imported; ?></strong>.
				Last completed sync: <strong><?php echo $last ? esc_html( $last ) : 'never'; ?></strong>.
				<?php if ( $queued ) : ?>
					<br><strong><?php echo (int) $queued; ?> post(s) still queued</strong> — press “Resume queue” to finish them.
				<?php endif; ?>
			</p>

			<h2>1. Sync</h2>
			<p class="description">
				<strong>Sync new &amp; changed</strong> only imports posts that are new on the old site, were edited there since the last import, or whose last import had a file error. Safe to run any time.<br>
				<strong>Re-import everything</strong> re-writes every lesson from the old site (files already downloaded are reused, not downloaded again).<br>
				Fields you add on this site (Memory Verse, Podcast, Video, featured image) are never overwritten. Tick “Lock” on a lesson to stop the importer touching it at all.
			</p>
			<p>
				<button class="button button-primary" data-potw-scan="changed">Sync new &amp; changed</button>
				<button class="button" data-potw-scan="all">Re-import everything</button>
				<button class="button" data-potw-resume <?php disabled( ! $queued ); ?>>Resume queue</button>
				<button class="button" data-potw-stop disabled>Stop</button>
			</p>

			<div id="potw-li-progress" hidden>
				<progress max="100" value="0" style="width:100%;max-width:600px;height:20px"></progress>
				<p id="potw-li-status"></p>
			</div>
			<div id="potw-li-summary"></div>
			<ol id="potw-li-log" style="max-height:420px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:8px 8px 8px 40px;font:12px/1.6 monospace" hidden></ol>

			<h2>2. Preview one post (no changes made)</h2>
			<p>
				<input type="number" id="potw-li-preview-id" placeholder="Old post ID, e.g. 12496" class="regular-text">
				<button class="button" data-potw-preview>Preview mapping</button>
				<button class="button" data-potw-import-one>Import this one now</button>
			</p>
			<pre id="potw-li-preview" style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:420px;overflow:auto" hidden></pre>

			<h2>3. Settings</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="potw_li_save">
				<?php wp_nonce_field( 'potw_li_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="potw-src">Old site</label></th>
						<td><input id="potw-src" name="source_site" class="regular-text" value="<?php echo esc_attr( $s['source_site'] ); ?>">
							<p class="description">The WordPress.com site address. Read through the public API — no login needed.</p></td>
					</tr>
					<tr>
						<th scope="row">Category</th>
						<td>
							<?php
							$modes = array(
								'auto'   => 'Bible book from the verse (e.g. “Acts”); fall back to the old post’s categories',
								'book'   => 'Bible book only',
								'source' => 'Old post’s categories only',
								'none'   => 'Don’t set categories',
							);
							foreach ( $modes as $k => $label ) {
								printf(
									'<label style="display:block"><input type="radio" name="category_mode" value="%s" %s> %s</label>',
									esc_attr( $k ),
									checked( $s['category_mode'], $k, false ),
									esc_html( $label )
								);
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="potw-skip">Ignore old categories</label></th>
						<td><input id="potw-skip" name="skip_categories" class="large-text" value="<?php echo esc_attr( $s['skip_categories'] ); ?>">
							<p class="description">Comma-separated. These old categories are never copied over.</p></td>
					</tr>
					<tr>
						<th scope="row">Automatic sync</th>
						<td><label><input type="checkbox" name="auto_sync" value="1" <?php checked( $s['auto_sync'] ); ?>> Run “Sync new &amp; changed” once a day in the background</label>
							<?php if ( $next ) : ?>
								<p class="description">Next run: <?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ) ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save settings' ); ?>
			</form>

			<h2>Command line</h2>
			<p class="description">On hosts with WP-CLI (WordPress.com SSH): <code>wp potw-lessons sync</code>, <code>wp potw-lessons sync --all</code>, <code>wp potw-lessons sync --id=12496</code>, <code>wp potw-lessons preview 12496</code>, <code>wp potw-lessons status</code>.</p>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! self::cap() ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'potw_li_save' );
		$modes = array( 'auto', 'book', 'source', 'none' );
		$new   = array(
			'source_site'     => sanitize_text_field( wp_unslash( $_POST['source_site'] ?? '' ) ),
			'category_mode'   => in_array( $_POST['category_mode'] ?? '', $modes, true ) ? $_POST['category_mode'] : 'auto', // phpcs:ignore
			'skip_categories' => sanitize_text_field( wp_unslash( $_POST['skip_categories'] ?? '' ) ),
			'auto_sync'       => empty( $_POST['auto_sync'] ) ? 0 : 1,
		);
		if ( '' === $new['source_site'] ) {
			$new['source_site'] = POTW_LI_Importer::defaults()['source_site'];
		}
		update_option( POTW_LI_Importer::OPT, $new, false );
		potw_li_sync_cron();
		wp_safe_redirect( add_query_arg( 'potw_li_msg', 'saved', wp_get_referer() ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	private static function guard() {
		if ( ! self::cap() || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
	}

	public static function ajax_scan() {
		self::guard();
		$mode = ( 'all' === ( $_POST['mode'] ?? '' ) ) ? 'all' : 'changed';
		$scan = POTW_LI_Importer::scan( $mode );
		if ( is_wp_error( $scan ) ) {
			wp_send_json_error( $scan->get_error_message() );
		}
		POTW_LI_Importer::set_queue( $scan['queue'], 'all' === $mode );

		wp_send_json_success(
			array(
				'source_total' => $scan['source_total'],
				'new'          => count( $scan['new'] ),
				'changed'      => count( $scan['changed'] ),
				'unchanged'    => $scan['unchanged'],
				'locked'       => count( $scan['locked'] ),
				'orphans'      => array_map(
					function ( $id ) {
						return array(
							'id'    => $id,
							'title' => get_the_title( $id ),
							'link'  => get_edit_post_link( $id, 'raw' ),
						);
					},
					$scan['orphans']
				),
				'queued'       => count( $scan['queue'] ),
			)
		);
	}

	public static function ajax_process() {
		self::guard();
		$r = POTW_LI_Importer::process_next();
		if ( null === $r ) {
			wp_send_json_success( array( 'done' => true ) );
		}
		wp_send_json_success( $r );
	}

	public static function ajax_stop() {
		self::guard();
		// Keep the queue so "Resume queue" can pick it up.
		wp_send_json_success();
	}

	public static function ajax_preview() {
		self::guard();
		$id  = (int) ( $_POST['id'] ?? 0 );
		$now = ! empty( $_POST['import'] );
		if ( ! $id ) {
			wp_send_json_error( 'Enter an old post ID.' );
		}
		$r = POTW_LI_Importer::import_one(
			$id,
			array(
				'dry_run'        => ! $now,
				'force'          => $now,
				'overwrite_body' => $now,
			)
		);
		wp_send_json_success( $r );
	}

	/* ------------------------------------------------------------------ */
	/* Per-lesson tools                                                    */
	/* ------------------------------------------------------------------ */

	public static function meta_box() {
		global $post;
		if ( ! $post || ! get_post_meta( $post->ID, POTW_LI_Importer::M_SOURCE_ID, true ) ) {
			return;
		}
		add_meta_box( 'potw-li-source', 'Imported from old site', array( __CLASS__, 'render_meta_box' ), null, 'side', 'low' );
	}

	public static function render_meta_box( $post ) {
		$sid  = get_post_meta( $post->ID, POTW_LI_Importer::M_SOURCE_ID, true );
		$link = get_post_meta( $post->ID, POTW_LI_Importer::M_LINK, true );
		$at   = get_post_meta( $post->ID, POTW_LI_Importer::M_AT, true );
		$lock = get_post_meta( $post->ID, POTW_LI_Importer::M_LOCK, true );
		wp_nonce_field( 'potw_li_lock', 'potw_li_lock_nonce' );
		?>
		<p>Old post #<?php echo (int) $sid; ?>
			<?php if ( $link ) : ?>
				— <a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">view</a>
			<?php endif; ?>
			<br>Last imported: <?php echo esc_html( $at ? $at : '—' ); ?></p>
		<p><label><input type="checkbox" name="potw_li_lock" value="1" <?php checked( (bool) $lock ); ?>>
			<strong>Lock</strong> — never overwrite this lesson on re-import</label></p>
		<p><a class="button" href="<?php echo esc_url( self::reimport_url( $post->ID ) ); ?>">Re-import from old site now</a></p>
		<?php
	}

	public static function save_lock( $post_id, $post ) {
		if ( ! isset( $_POST['potw_li_lock_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['potw_li_lock_nonce'] ), 'potw_li_lock' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['potw_li_lock'] ) ) {
			update_post_meta( $post_id, POTW_LI_Importer::M_LOCK, 1 );
		} else {
			delete_post_meta( $post_id, POTW_LI_Importer::M_LOCK );
		}
	}

	private static function reimport_url( $lesson_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=potw_li_reimport&lesson=' . (int) $lesson_id ),
			'potw_li_reimport_' . (int) $lesson_id
		);
	}

	public static function row_actions( $actions, $post ) {
		if ( POTW_LI_Importer::POST_TYPE === $post->post_type && self::cap() && get_post_meta( $post->ID, POTW_LI_Importer::M_SOURCE_ID, true ) ) {
			$actions['potw_li_reimport'] = '<a href="' . esc_url( self::reimport_url( $post->ID ) ) . '">Re-import</a>';
		}
		return $actions;
	}

	public static function reimport_single() {
		$lesson_id = (int) ( $_GET['lesson'] ?? 0 );
		if ( ! self::cap() ) {
			wp_die( 'Not allowed' );
		}
		check_admin_referer( 'potw_li_reimport_' . $lesson_id );
		@set_time_limit( 300 ); // phpcs:ignore

		$sid = (int) get_post_meta( $lesson_id, POTW_LI_Importer::M_SOURCE_ID, true );
		$msg = 'reimport_fail';
		if ( $sid ) {
			if ( get_post_meta( $lesson_id, POTW_LI_Importer::M_LOCK, true ) ) {
				$msg = 'locked';
			} else {
				$r   = POTW_LI_Importer::import_one(
					$sid,
					array(
						'force'          => true,
						'overwrite_body' => true,
					)
				);
				$msg = in_array( $r['status'], array( 'updated', 'created', 'unchanged' ), true ) ? 'reimported' : 'reimport_fail';
			}
		}
		wp_safe_redirect( add_query_arg( 'potw_li_msg', $msg, wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=lesson' ) ) );
		exit;
	}

	public static function notices() {
		$msg   = sanitize_key( $_GET['potw_li_msg'] ?? '' );
		$texts = array(
			'saved'         => array( 'success', 'Importer settings saved.' ),
			'reimported'    => array( 'success', 'Lesson re-imported from the old site.' ),
			'locked'        => array( 'warning', 'This lesson is locked — untick “Lock” first to re-import it.' ),
			'reimport_fail' => array( 'error', 'Re-import failed. Try “Preview” on the importer page to see why.' ),
		);
		if ( isset( $texts[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $texts[ $msg ][0] ), esc_html( $texts[ $msg ][1] ) );
		}
	}
}
