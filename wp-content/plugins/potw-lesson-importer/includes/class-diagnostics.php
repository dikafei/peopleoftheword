<?php
/**
 * Admin-only diagnostics: which headings do the lecture documents use?
 * AJAX action potw_li_heading_stats (offset, limit) → headings found in .docx
 * lectures and short title-like lines found in .doc lectures.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class POTW_LI_Diagnostics {

	public static function init() {
		add_action( 'wp_ajax_potw_li_heading_stats', array( __CLASS__, 'heading_stats' ) );
	}

	public static function heading_stats() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( POTW_LI_Admin::NONCE, 'nonce', false ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		@set_time_limit( 300 ); // phpcs:ignore
		$offset = (int) ( $_POST['offset'] ?? 0 );
		$limit  = min( 60, max( 1, (int) ( $_POST['limit'] ?? 40 ) ) );

		$ids = get_posts(
			array(
				'post_type'      => 'lesson',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_key'       => POTW_LI_Importer::M_SOURCE_ID,
			)
		);

		$docx = array();
		$doc  = array();
		$n    = array(
			'docx' => 0,
			'doc'  => 0,
		);
		foreach ( $ids as $id ) {
			$rows = get_post_meta( $id, 'lesson_file', true );
			for ( $i = 0; $i < (int) $rows; $i++ ) {
				$word = (int) get_post_meta( $id, "lesson_file_{$i}_lesson_word", true );
				$path = $word ? get_attached_file( $word ) : '';
				if ( ! $path || ! file_exists( $path ) ) {
					continue;
				}
				$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( 'docx' === $ext ) {
					$c = POTW_LI_Docx::convert( $path, array( 'verse' => get_the_title( $id ) ) );
					if ( is_wp_error( $c ) ) {
						continue;
					}
					$n['docx']++;
					if ( preg_match_all( '#<h([23])[^>]*>(.*?)</h\1>#s', $c['blocks'], $m ) ) {
						foreach ( $m[2] as $h ) {
							$docx[] = wp_strip_all_tags( html_entity_decode( $h ) );
						}
					}
				} elseif ( 'doc' === $ext ) {
					$paras = POTW_LI_Doc::paragraphs( $path );
					if ( is_wp_error( $paras ) ) {
						continue;
					}
					$n['doc']++;
					$count = count( $paras );
					for ( $k = 3; $k < $count; $k++ ) {
						$t = trim( $paras[ $k ] );
						if ( mb_strlen( $t ) >= 3 && mb_strlen( $t ) <= 60 && ! preg_match( '/[.,;]$/', $t ) && isset( $paras[ $k + 1 ] ) && mb_strlen( $paras[ $k + 1 ] ) > 140 ) {
							$doc[] = $t;
						}
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'done'    => count( $ids ) < $limit,
				'files'   => $n,
				'docx'    => $docx,
				'doc'     => $doc,
				'lessons' => count( $ids ),
			)
		);
	}
}
