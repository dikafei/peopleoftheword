<?php
/**
 * Creates / updates Lesson posts from old-site posts.
 *
 * Ownership rules (so re-imports never clobber hand edits):
 *  - The importer WRITES: title, content, publish date, category, lesson_verse,
 *    lesson_start_date, lesson_end_date, lesson_file, homework_file.
 *  - The importer NEVER touches: memory_verse, lesson_podcast, lesson_video,
 *    featured image, tags, or any other field.
 *  - Slug is only set when the lesson is first created.
 *  - A lesson with "Lock" ticked (meta box on the edit screen) is skipped.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class POTW_LI_Importer {

	/**
	 * Bump this whenever the mapping logic changes: every lesson imported with an
	 * older version is treated as "changed" on the next sync.
	 */
	const MAPPING_VERSION = 4; // 2: lecture/document bodies. 3: heading clean-up rules. 4: quotes, prayer box, empty headings.

	/** Lesson meta: md5 of the body the importer last wrote (detects hand edits). */
	const M_BODY_HASH = '_potw_body_hash';
	const M_BODY_LOG  = '_potw_body_report';

	const POST_TYPE   = 'lesson';
	const OPT         = 'potw_li_settings';
	const QUEUE_OPT   = 'potw_li_queue';
	const LAST_RUN    = 'potw_li_last_run';
	const M_SOURCE_ID = '_potw_source_id';
	const M_MODIFIED  = '_potw_source_modified_gmt';
	const M_LINK      = '_potw_source_link';
	const M_VERSION   = '_potw_import_version';
	const M_HASH      = '_potw_source_hash';
	const M_AT        = '_potw_imported_at';
	const M_LOCK      = '_potw_import_lock';
	const M_FILE_KEY  = '_potw_source_key';
	const M_FILE_URL  = '_potw_source_url';

	/** Fallback ACF field keys (looked up by name at runtime when ACF is active). */
	const FIELD_KEYS = array(
		'lesson_verse'      => 'field_6aaa27fadea1d',
		'lesson_start_date' => 'field_6ab0b4dd8c2b7',
		'lesson_end_date'   => 'field_6ab0b65c8c2b8',
		'lesson_file'       => 'field_6aaa1de47362b',
		'homework_file'     => 'field_6aaa1f8f73630',
	);

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'source_site'     => 'peopleofthewordonline.wordpress.com',
			'category_mode'   => 'auto', // auto | book | source | none
			'skip_categories' => 'Archive, Uncategorized, Lectures, Old Testament, New Testament',
			'auto_sync'       => 0,
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPT, array() ), self::defaults() );
	}

	public static function source() {
		return new POTW_LI_Source( self::settings()['source_site'] );
	}

	/* ------------------------------------------------------------------ */
	/* Lookup                                                              */
	/* ------------------------------------------------------------------ */

	public static function find_lesson( $source_id ) {
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => self::M_SOURCE_ID,
				'meta_value'       => (string) (int) $source_id,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** source_id => [lesson_id, modified, version, lock] for every imported lesson. */
	public static function imported_map() {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, s.meta_value AS sid, m.meta_value AS modified, v.meta_value AS ver, l.meta_value AS lck
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft')",
				self::M_SOURCE_ID,
				self::M_MODIFIED,
				self::M_VERSION,
				self::M_LOCK,
				self::POST_TYPE
			)
		);
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->sid ] = array(
				'lesson_id' => (int) $r->ID,
				'modified'  => (string) $r->modified,
				'version'   => (int) $r->ver,
				'lock'      => ! empty( $r->lck ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Scan → queue                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Compare the source with what is already imported.
	 *
	 * @param string $mode 'changed' (new + edited) or 'all' (everything).
	 * @return array|WP_Error { new[], changed[], unchanged, locked[], orphans[], queue[] }
	 */
	public static function scan( $mode = 'changed' ) {
		$index = self::source()->index();
		if ( is_wp_error( $index ) ) {
			return $index;
		}
		$map = self::imported_map();

		$new = $changed = $locked = $queue = array();
		$unchanged = 0;

		foreach ( $index as $sid => $info ) {
			if ( ! isset( $map[ $sid ] ) ) {
				$new[]   = $sid;
				$queue[] = $sid;
				continue;
			}
			$m = $map[ $sid ];
			if ( $m['lock'] ) {
				$locked[] = $sid;
				continue;
			}
			$is_changed = $m['modified'] !== $info['modified_gmt'] || $m['version'] < self::MAPPING_VERSION;
			if ( $is_changed ) {
				$changed[] = $sid;
			} else {
				$unchanged++;
			}
			if ( $is_changed || 'all' === $mode ) {
				$queue[] = $sid;
			}
		}

		$orphans = array();
		foreach ( $map as $sid => $m ) {
			if ( ! isset( $index[ $sid ] ) ) {
				$orphans[] = $m['lesson_id'];
			}
		}

		return array(
			'source_total' => count( $index ),
			'new'          => $new,
			'changed'      => $changed,
			'unchanged'    => $unchanged,
			'locked'       => $locked,
			'orphans'      => $orphans,
			'queue'        => $queue,
			'titles'       => wp_list_pluck( $index, 'title' ),
		);
	}

	public static function get_queue() {
		$q = get_option( self::QUEUE_OPT, array() );
		return is_array( $q ) ? $q : array();
	}

	public static function set_queue( array $q, $force = false ) {
		update_option(
			self::QUEUE_OPT,
			array(
				'ids'   => array_values( array_map( 'intval', $q ) ),
				'force' => (bool) $force,
				'total' => count( $q ),
			),
			false
		);
	}

	/**
	 * Import the next queued post. Returns null when the queue is empty.
	 */
	public static function process_next() {
		$q = self::get_queue();
		if ( empty( $q['ids'] ) ) {
			return null;
		}
		$sid = array_shift( $q['ids'] );
		update_option( self::QUEUE_OPT, $q, false );

		$result              = self::import_one( $sid, array( 'force' => ! empty( $q['force'] ) ) );
		$result['remaining'] = count( $q['ids'] );
		$result['total']     = (int) ( $q['total'] ?? 0 );

		if ( 0 === $result['remaining'] ) {
			update_option( self::LAST_RUN, current_time( 'mysql' ), false );
		}
		return $result;
	}

	/** Work through the queue for up to $seconds (used by cron + WP-CLI). */
	public static function process_for( $seconds, $logger = null ) {
		$start = microtime( true );
		$done  = 0;
		while ( microtime( true ) - $start < $seconds ) {
			$r = self::process_next();
			if ( null === $r ) {
				break;
			}
			$done++;
			if ( $logger ) {
				call_user_func( $logger, $r );
			}
		}
		return $done;
	}

	/* ------------------------------------------------------------------ */
	/* Import one                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * @param int   $source_id Old-site post ID.
	 * @param array $args      force (bool) re-write even if unchanged; dry_run (bool).
	 * @return array { status: created|updated|unchanged|skipped|error|preview, lesson_id, log[] }
	 */
	public static function import_one( $source_id, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'force'          => false,
				'dry_run'        => false,
				'overwrite_body' => false, // true = replace the body even if it was edited on this site.
			)
		);
		$log  = array();
		$sid  = (int) $source_id;

		$src = self::source()->post( $sid );
		if ( is_wp_error( $src ) ) {
			return self::result( 'error', 0, $sid, array( $src->get_error_message() ) );
		}

		$data      = POTW_LI_Parser::parse( $src );
		$lesson_id = self::find_lesson( $sid );
		$cats      = self::category_names( $data, $src );

		if ( $args['dry_run'] ) {
			return self::result( 'preview', $lesson_id, $sid, array(), self::preview( $data, $cats ), $data['title'] );
		}

		if ( $lesson_id && get_post_meta( $lesson_id, self::M_LOCK, true ) ) {
			return self::result( 'skipped', $lesson_id, $sid, array( 'Locked — skipped.' ), null, $data['title'] );
		}

		$hash = md5( wp_json_encode( array( $data, $cats, self::MAPPING_VERSION ) ) );
		if ( $lesson_id && ! $args['force'] && get_post_meta( $lesson_id, self::M_HASH, true ) === $hash ) {
			update_post_meta( $lesson_id, self::M_MODIFIED, $src['modified_gmt'] );
			update_post_meta( $lesson_id, self::M_VERSION, self::MAPPING_VERSION );
			return self::result( 'unchanged', $lesson_id, $sid, array( 'No content changes.' ), null, $data['title'] );
		}

		if ( ! function_exists( 'update_field' ) ) {
			return self::result( 'error', $lesson_id, $sid, array( 'Advanced Custom Fields is not active.' ) );
		}

		// 1. Post.
		$postarr = array(
			'post_type'     => self::POST_TYPE,
			'post_title'    => $data['title'],
			'post_status'   => 'publish',
			'post_date'     => $data['date'],
			'post_date_gmt' => str_replace( 'T', ' ', $src['date_gmt'] ?? '' ),
			'edit_date'     => true,
		);
		if ( $lesson_id ) {
			$body_before   = (string) get_post_field( 'post_content', $lesson_id );
			$body_hash     = (string) get_post_meta( $lesson_id, self::M_BODY_HASH, true );
			$body_edited   = '' !== $body_hash && '' !== trim( $body_before ) && $body_hash !== md5( $body_before );
			$postarr['ID'] = $lesson_id;
			$res           = wp_update_post( wp_slash( $postarr ), true );
			$status        = 'updated';
		} else {
			$postarr['post_name'] = $src['slug'] ?? '';
			$body_edited          = false;
			$res                  = wp_insert_post( wp_slash( $postarr ), true );
			$status               = 'created';
		}
		if ( is_wp_error( $res ) ) {
			return self::result( 'error', $lesson_id, $sid, array( $res->get_error_message() ) );
		}
		$lesson_id = (int) $res;

		// 2. Files → attachments.
		$errors        = 0;
		$lesson_rows   = array();
		$homework_rows = array();
		foreach ( array( 'lesson_rows', 'homework_rows' ) as $group ) {
			foreach ( $data[ $group ] as $row ) {
				$ids = array();
				foreach ( array( 'pdf', 'word' ) as $slot ) {
					$ids[ $slot ] = '';
					if ( ! empty( $row[ $slot ] ) ) {
						$att = self::import_file( $row[ $slot ], $lesson_id );
						if ( is_wp_error( $att ) ) {
							$errors++;
							$log[] = 'File failed: ' . $row[ $slot ]['url'] . ' — ' . $att->get_error_message();
						} else {
							$ids[ $slot ] = $att;
						}
					}
				}
				if ( 'lesson_rows' === $group ) {
					$lesson_rows[] = array(
						'lecturer_name' => $row['name'],
						'lesson_year'   => $row['year'] ? $row['year'] : '',
						'lesson_pdf'    => $ids['pdf'],
						'lesson_word'   => $ids['word'],
					);
				} else {
					$homework_rows[] = array(
						'homework_lecturer_name' => $row['name'],
						'homework_year'          => $row['year'] ? $row['year'] : '',
						'homework_pdf'           => $ids['pdf'],
						'homework_word'          => $ids['word'],
					);
				}
			}
		}

		// 3. Body: lecture documents converted to blocks (one tab per lecture).
		if ( $body_edited && ! $args['overwrite_body'] ) {
			$log[] = 'Body was edited on this site — kept (use Re-import on the lesson to replace it).';
		} else {
			$body = self::build_body( $data, $lesson_rows, $lesson_id );
			// Keep the embedded PDF <object> even when run from cron / WP-CLI (no unfiltered_html user).
			$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
			if ( $kses ) {
				kses_remove_filters();
			}
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $lesson_id,
						'post_content' => $body['content'],
					)
				)
			);
			if ( $kses ) {
				kses_init_filters();
			}
			update_post_meta( $lesson_id, self::M_BODY_HASH, md5( (string) get_post_field( 'post_content', $lesson_id ) ) );
			update_post_meta( $lesson_id, self::M_BODY_LOG, $body['report'] );
			$log = array_merge( $log, $body['report'] );
		}

		// 4. ACF fields.
		update_field( self::key( 'lesson_verse' ), $data['verse'], $lesson_id );
		update_field( self::key( 'lesson_start_date' ), $data['start'], $lesson_id );
		update_field( self::key( 'lesson_end_date' ), $data['end'], $lesson_id );
		update_field( self::key( 'lesson_file' ), $lesson_rows, $lesson_id );
		update_field( self::key( 'homework_file' ), $homework_rows, $lesson_id );

		// 5. Category.
		if ( $cats ) {
			$term_ids = array();
			foreach ( $cats as $name ) {
				$term = term_exists( $name, 'category' );
				if ( ! $term ) {
					$term = wp_insert_term( $name, 'category' );
				}
				if ( ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term['term_id'];
				}
			}
			wp_set_post_terms( $lesson_id, $term_ids, 'category', false );
		}

		// 6. Bookkeeping. If any file failed, don't store the hash so the next sync retries.
		update_post_meta( $lesson_id, self::M_SOURCE_ID, (string) $sid );
		update_post_meta( $lesson_id, self::M_MODIFIED, $errors ? 'retry' : $src['modified_gmt'] );
		update_post_meta( $lesson_id, self::M_LINK, $src['link'] ?? '' );

		// Old-site categories, and whether the post was in its "Archive" (not part of a study year).
		$src_names = array();
		$all_names = self::source()->categories();
		foreach ( (array) ( $src['categories'] ?? array() ) as $cid ) {
			if ( isset( $all_names[ $cid ] ) ) {
				$src_names[] = $all_names[ $cid ];
			}
		}
		update_post_meta( $lesson_id, '_potw_source_categories', $src_names );
		update_post_meta( $lesson_id, '_potw_archive', in_array( 'archive', array_map( 'strtolower', $src_names ), true ) ? '1' : '0' );
		update_post_meta( $lesson_id, self::M_VERSION, self::MAPPING_VERSION );
		update_post_meta( $lesson_id, self::M_HASH, $errors ? '' : $hash );
		update_post_meta( $lesson_id, self::M_AT, current_time( 'mysql' ) );

		$log[] = sprintf(
			'%d lesson file row(s), %d homework row(s)%s',
			count( $lesson_rows ),
			count( $homework_rows ),
			$errors ? ", {$errors} file error(s) — will retry next sync" : ''
		);

		return self::result( $errors ? 'partial' : $status, $lesson_id, $sid, $log, null, $data['title'] );
	}

	private static function result( $status, $lesson_id, $sid, array $log, $preview = null, $title = '' ) {
		return array(
			'status'    => $status,
			'lesson_id' => (int) $lesson_id,
			'source_id' => (int) $sid,
			'title'     => $title,
			'edit_link' => $lesson_id ? get_edit_post_link( $lesson_id, 'raw' ) : '',
			'log'       => $log,
			'preview'   => $preview,
		);
	}

	private static function preview( array $data, array $cats ) {
		$fmt = function ( $rows, $kind ) {
			return array_map(
				function ( $r ) use ( $kind ) {
					return array(
						'name' => $r['name'],
						'year' => $r['year'],
						'kind' => $r['kind'],
						'pdf'  => $r['pdf'] ? basename( $r['pdf']['url'] ) : '',
						'word' => $r['word'] ? basename( $r['word']['url'] ) : '',
					);
				},
				$rows
			);
		};
		return array(
			'title'         => $data['title'],
			'verse'         => $data['verse'],
			'start'         => $data['start'],
			'end'           => $data['end'],
			'categories'    => $cats,
			'lesson_file'   => $fmt( $data['lesson_rows'], 'lesson' ),
			'homework_file' => $fmt( $data['homework_rows'], 'homework' ),
			'content'       => wp_trim_words( wp_strip_all_tags( $data['content'] ), 40 ),
		);
	}

	private static function key( $name ) {
		if ( function_exists( 'acf_get_field' ) ) {
			$f = acf_get_field( $name );
			if ( $f && ! empty( $f['key'] ) && empty( $f['parent_repeater'] ) ) {
				return $f['key'];
			}
		}
		return self::FIELD_KEYS[ $name ] ?? $name;
	}

	/* ------------------------------------------------------------------ */
	/* Body                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Lecture .docx files → one group block per lecture, newest first, followed
	 * by any other content from the old post (e.g. video buttons).
	 *
	 * @param array $data        Parsed source data.
	 * @param array $lesson_rows Rows as written to ACF (with attachment IDs).
	 * @return array { content, report[] }
	 */
	public static function build_body( array $data, array $lesson_rows, $lesson_id ) {
		$report = array();
		$ctx    = array(
			'verse'        => $data['verse'] ? $data['verse'] : $data['title'],
			'lesson_title' => $data['title'],
			'known_names'  => array_merge( self::known_names(), wp_list_pluck( $data['lesson_rows'], 'name' ), wp_list_pluck( $data['homework_rows'], 'name' ) ),
		);

		// 1. Lectures (Word .docx / .doc), newest first.
		$lectures = self::convert_rows( $data, $lesson_rows, array( 'lecture' ), $ctx, $report );
		usort(
			$lectures,
			function ( $a, $b ) {
				return $b['year'] <=> $a['year'] ?: $a['i'] <=> $b['i'];
			}
		);
		$kind = 'lecture';

		// 2. No lecture: use the other documents (bios, schedules, devotions…), in the old post's order.
		if ( ! $lectures ) {
			$lectures = self::convert_rows( $data, $lesson_rows, array( 'other', 'devotion' ), $ctx, $report );
			$kind     = 'document';
		}

		// 3. Still nothing but a PDF: show the PDF inline.
		$pdf_embed = '';
		if ( ! $lectures ) {
			foreach ( $data['lesson_rows'] as $i => $row ) {
				$pdf_id = (int) ( $lesson_rows[ $i ]['lesson_pdf'] ?? 0 );
				if ( $pdf_id && 'homework' !== $row['kind'] ) {
					$url       = wp_get_attachment_url( $pdf_id );
					$name      = $row['name'] ? $row['name'] : $data['title'];
					$pdf_embed = "<!-- wp:html -->\n<div class=\"potw-pdf-embed\"><object data=\"" . esc_url( $url ) . '" type="application/pdf" aria-label="' . esc_attr( $name ) . '"><p><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . " (PDF)</a></p></object></div>\n<!-- /wp:html -->";
					$report[]  = sprintf( '"%s": PDF only — shown as an embedded PDF.', $name );
					break;
				}
			}
		}

		$single = 1 === count( $lectures ) && 'document' === $kind;
		$out = '';
		if ( $lectures ) {
			$out .= "<!-- wp:group {\"className\":\"potw-lectures\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group potw-lectures\">";
			foreach ( $lectures as $l ) {
				$meta = wp_json_encode( array( 'name' => 'Lecture – ' . $l['label'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				$out .= "\n<!-- wp:group {\"metadata\":{$meta},\"className\":\"potw-lecture\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group potw-lecture\">";
				if ( ! $single ) {
					$out .= "\n<!-- wp:paragraph {\"className\":\"potw-lecture-meta\"} -->\n<p class=\"potw-lecture-meta\">" . esc_html( $l['label'] ) . "</p>\n<!-- /wp:paragraph -->\n\n";
				}
				if ( $l['title'] ) {
					$out .= "<!-- wp:paragraph {\"className\":\"potw-lecture-title\"} -->\n<p class=\"potw-lecture-title\">" . esc_html( $l['title'] ) . "</p>\n<!-- /wp:paragraph -->\n\n";
				}
				$out .= $l['blocks'];
				$out .= "\n</div>\n<!-- /wp:group -->";
			}
			$out .= "\n</div>\n<!-- /wp:group -->";
		}

		if ( $pdf_embed ) {
			$out .= $pdf_embed;
		}

		if ( '' !== trim( $data['content'] ) ) {
			$out .= ( $out ? "\n\n" : '' ) . "<!-- wp:html -->\n" . $data['content'] . "\n<!-- /wp:html -->";
		}

		return array(
			'content' => $out,
			'report'  => $report,
		);
	}

	/**
	 * Every lecturer name already on the site (used so a name on its own line
	 * is never turned into a heading).
	 */
	public static function known_names() {
		static $names = null;
		if ( null === $names ) {
			global $wpdb;
			$names = $wpdb->get_col(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
				 WHERE meta_key REGEXP '^(lesson_file|homework_file)_[0-9]+_(lecturer_name|homework_lecturer_name)$'
				   AND meta_value <> '' AND CHAR_LENGTH(meta_value) < 60"
			);
		}
		return $names;
	}

	/**
	 * Convert the Word file of every row of the given kinds.
	 *
	 * @return array[] { i, name, year, label, title, blocks }
	 */
	private static function convert_rows( array $data, array $lesson_rows, array $kinds, array $ctx, array &$report ) {
		$out = array();
		foreach ( $data['lesson_rows'] as $i => $row ) {
			if ( ! in_array( $row['kind'], $kinds, true ) ) {
				continue;
			}
			$label   = trim( $row['name'] . ( $row['year'] && 'lecture' === $row['kind'] ? ' · ' . $row['year'] : '' ) );
			$label   = '' === $label ? $data['title'] : $label;
			$word_id = (int) ( $lesson_rows[ $i ]['lesson_word'] ?? 0 );
			$path    = $word_id ? get_attached_file( $word_id ) : '';
			$ext     = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';
			if ( ! $path || ! file_exists( $path ) || ! in_array( $ext, array( 'docx', 'doc' ), true ) ) {
				$report[] = sprintf( '"%s": no Word file — download only.', $label );
				continue;
			}
			$c    = $ctx + array( 'lecturer' => $row['name'] );
			// Trust the file's content, not its name: some ".doc" files are really .docx (ZIP).
			$head = (string) @file_get_contents( $path, false, null, 0, 8 ); // phpcs:ignore
			if ( 0 === strpos( $head, "PK\x03\x04" ) ) {
				$ext = 'docx';
			} elseif ( 0 === strpos( $head, "\xD0\xCF\x11\xE0" ) ) {
				$ext = 'doc';
			}
			$conv = 'docx' === $ext ? POTW_LI_Docx::convert( $path, $c ) : POTW_LI_Doc::convert( $path, $c );
			if ( is_wp_error( $conv ) || '' === trim( $conv['blocks'] ) ) {
				$report[] = sprintf( '"%s": could not read the .%s%s — download only.', $label, $ext, is_wp_error( $conv ) ? ' (' . $conv->get_error_message() . ')' : '' );
				continue;
			}
			$report[] = sprintf( '"%s" (.%s): %d blocks, %d heading(s)%s', $label, $ext, $conv['paragraphs'], $conv['headings'], $conv['warnings'] ? ' — ' . implode( ' ', $conv['warnings'] ) : '' );
			$out[]    = array(
				'i'      => $i,
				'name'   => $row['name'],
				'year'   => (int) $row['year'],
				'label'  => $label,
				'title'  => $conv['title'],
				'blocks' => $conv['blocks'],
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Categories                                                          */
	/* ------------------------------------------------------------------ */

	public static function category_names( array $data, array $src ) {
		$s    = self::settings();
		$mode = $s['category_mode'];
		$out  = array();

		if ( in_array( $mode, array( 'auto', 'book' ), true ) && $data['book'] ) {
			$out[] = $data['book'];
		}
		if ( 'source' === $mode || ( 'auto' === $mode && ! $out ) ) {
			$skip  = array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', $s['skip_categories'] ) ) ) );
			$names = self::source()->categories();
			foreach ( (array) ( $src['categories'] ?? array() ) as $cid ) {
				$name = $names[ $cid ] ?? '';
				if ( $name && ! in_array( strtolower( $name ), $skip, true ) ) {
					$out[] = $name;
				}
			}
		}

		/**
		 * Filter the category names a lesson is filed under.
		 *
		 * @param string[] $out  Category names.
		 * @param array    $data Parsed lesson data.
		 * @param array    $src  Raw source post.
		 */
		return array_values( array_unique( apply_filters( 'potw_li_category_names', $out, $data, $src ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Files                                                               */
	/* ------------------------------------------------------------------ */

	/** Existing attachment for a source file, or 0. */
	public static function find_attachment( $key ) {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => self::M_FILE_KEY,
				'meta_value'       => $key,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Download a source document into the Media Library once; reuse it after.
	 * Files are keyed by their path on the old site (e.g. "2026/10/x.pdf"), so
	 * a file re-uploaded on the old site (new URL) is downloaded fresh.
	 *
	 * @return int|WP_Error Attachment ID.
	 */
	public static function import_file( array $file, $lesson_id ) {
		$key      = POTW_LI_Parser::source_key( $file['url'] );
		$existing = self::find_attachment( $key );
		if ( $existing ) {
			if ( ! wp_get_post_parent_id( $existing ) ) {
				wp_update_post(
					array(
						'ID'          => $existing,
						'post_parent' => $lesson_id,
					)
				);
			}
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = null;
		foreach ( self::url_candidates( $file['url'] ) as $url ) {
			$tmp = download_url( $url, 300 );
			if ( ! is_wp_error( $tmp ) ) {
				break;
			}
		}
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Keep the old site's year/month folder (uploads/2026/10/…).
		$subdir     = preg_match( '#^(\d{4}/\d{2})/#', $key, $m ) ? '/' . $m[1] : '';
		$dir_filter = function ( $dirs ) use ( $subdir ) {
			if ( $subdir ) {
				$dirs['path']   = $dirs['basedir'] . $subdir;
				$dirs['url']    = $dirs['baseurl'] . $subdir;
				$dirs['subdir'] = $subdir;
			}
			return $dirs;
		};
		// Trust .pdf/.doc/.docx by extension (some hosts sniff .docx as zip).
		$type_filter = function ( $data, $path, $filename ) {
			$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$types = array(
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			);
			if ( isset( $types[ $ext ] ) ) {
				$data['ext']  = $ext;
				$data['type'] = $types[ $ext ];
			}
			return $data;
		};
		add_filter( 'upload_dir', $dir_filter );
		add_filter( 'wp_check_filetype_and_ext', $type_filter, 99, 3 );

		$att = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( basename( $key ) ),
				'tmp_name' => $tmp,
			),
			$lesson_id,
			$file['label'] ? $file['label'] : null
		);

		remove_filter( 'upload_dir', $dir_filter );
		remove_filter( 'wp_check_filetype_and_ext', $type_filter, 99 );

		if ( is_wp_error( $att ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return $att;
		}

		update_post_meta( $att, self::M_FILE_KEY, $key );
		update_post_meta( $att, self::M_FILE_URL, $file['url'] );
		return (int) $att;
	}

	/** The old site serves uploads from two hosts — try both. */
	private static function url_candidates( $url ) {
		$hosts = array( 'peopleofthewordonline.wordpress.com', 'peopleoftheword.online' );
		$out   = array( $url );
		$host  = (string) parse_url( $url, PHP_URL_HOST );
		foreach ( $hosts as $h ) {
			if ( $h !== $host && in_array( $host, $hosts, true ) ) {
				$out[] = preg_replace( '#^(https?://)[^/]+#', '$1' . $h, $url );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Cron                                                                */
	/* ------------------------------------------------------------------ */

	public static function cron_daily() {
		if ( empty( self::settings()['auto_sync'] ) ) {
			return;
		}
		$q = self::get_queue();
		if ( ! empty( $q['ids'] ) ) {
			// Something is still queued — just keep working on it.
			self::schedule_work();
			return;
		}
		$scan = self::scan( 'changed' );
		if ( is_wp_error( $scan ) || ! $scan['queue'] ) {
			update_option( self::LAST_RUN, current_time( 'mysql' ), false );
			return;
		}
		self::set_queue( $scan['queue'], false );
		self::schedule_work();
	}

	public static function cron_work() {
		if ( get_transient( 'potw_li_working' ) ) {
			return;
		}
		set_transient( 'potw_li_working', 1, 2 * MINUTE_IN_SECONDS );
		self::process_for( 40 );
		delete_transient( 'potw_li_working' );

		$q = self::get_queue();
		if ( ! empty( $q['ids'] ) ) {
			self::schedule_work( 60 );
		}
	}

	public static function schedule_work( $delay = 5 ) {
		if ( ! wp_next_scheduled( 'potw_li_work' ) ) {
			wp_schedule_single_event( time() + $delay, 'potw_li_work' );
		}
	}
}
