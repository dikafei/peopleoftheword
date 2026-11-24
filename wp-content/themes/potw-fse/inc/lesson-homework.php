<?php
/**
 * Homework that lives on another post.
 *
 * On the old site the homework files for a lesson were often attached to an
 * older post with the same title (e.g. the 2021 "Acts 1:1-26" post carries the
 * 2026 homework), while the new post only has the lecture. This copies those
 * homework rows onto the lesson that is missing them, so they show in the
 * editor and in the right-hand download column.
 *
 * Only copies when the homework year matches one of the lesson's lecture years.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POTW_HOMEWORK_FIELD = 'field_6aaa1f8f73630';

/** Normalised title for matching ("Acts 1:1-26" == "Acts 1:1–26 "). */
function potw_title_key( $post_id ) {
	$t = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	$t = str_replace( array( '–', '—', ' ' ), array( '-', '-', ' ' ), $t );
	return strtolower( trim( preg_replace( '/\s+/', ' ', $t ) ) );
}

/** Years on the lesson's lecture rows. */
function potw_lecture_years( $post_id ) {
	$years = array();
	$rows  = (int) get_post_meta( $post_id, 'lesson_file', true );
	for ( $i = 0; $i < $rows; $i++ ) {
		$y = (int) get_post_meta( $post_id, "lesson_file_{$i}_lesson_year", true );
		if ( $y ) {
			$years[] = $y;
		}
	}
	return array_unique( $years );
}

/** Homework rows (raw values) of a lesson. */
function potw_homework_rows( $post_id ) {
	$out  = array();
	$rows = (int) get_post_meta( $post_id, 'homework_file', true );
	for ( $i = 0; $i < $rows; $i++ ) {
		$out[] = array(
			'homework_lecturer_name' => (string) get_post_meta( $post_id, "homework_file_{$i}_homework_lecturer_name", true ),
			'homework_year'          => (int) get_post_meta( $post_id, "homework_file_{$i}_homework_year", true ),
			'homework_pdf'           => (int) get_post_meta( $post_id, "homework_file_{$i}_homework_pdf", true ),
			'homework_word'          => (int) get_post_meta( $post_id, "homework_file_{$i}_homework_word", true ),
		);
	}
	return $out;
}

/**
 * Homework rows for a lesson that has none, taken from same-title lessons,
 * keeping only rows whose year matches the lesson's lecture years.
 * Returns [ source_id => rows ].
 */
function potw_find_homework( $post_id ) {
	if ( potw_homework_rows( $post_id ) ) {
		return array();
	}
	$years = potw_lecture_years( $post_id );
	if ( ! $years ) {
		return array();
	}
	$key  = potw_title_key( $post_id );
	$cand = get_posts(
		array(
			'post_type'      => 'lesson',
			'post_status'    => array( 'publish', 'private', 'draft', 'future' ),
			'posts_per_page' => 20,
			'post__not_in'   => array( $post_id ),
			's'              => get_the_title( $post_id ),
			'search_columns' => array( 'post_title' ),
			'fields'         => 'ids',
		)
	);
	$found = array();
	foreach ( $cand as $cid ) {
		if ( potw_title_key( $cid ) !== $key ) {
			continue;
		}
		$rows = array_values(
			array_filter(
				potw_homework_rows( $cid ),
				function ( $r ) use ( $years ) {
					return in_array( (int) $r['homework_year'], $years, true ) && ( $r['homework_pdf'] || $r['homework_word'] );
				}
			)
		);
		if ( $rows ) {
			$found[ $cid ] = $rows;
		}
	}
	return $found;
}

/** Copy the matching homework onto the lesson. Returns number of rows copied. */
function potw_fill_homework( $post_id ) {
	$found = potw_find_homework( $post_id );
	if ( ! $found || ! function_exists( 'update_field' ) ) {
		return 0;
	}
	$rows = array();
	$seen = array();
	foreach ( $found as $rs ) {
		foreach ( $rs as $r ) {
			$k = $r['homework_pdf'] . '|' . $r['homework_word'];
			if ( ! isset( $seen[ $k ] ) ) {
				$seen[ $k ] = true;
				$rows[]     = $r;
			}
		}
	}
	update_field( POTW_HOMEWORK_FIELD, $rows, $post_id );
	update_post_meta( $post_id, '_potw_homework_from', implode( ',', array_keys( $found ) ) );
	return count( $rows );
}

/**
 * New or re-imported lessons: check a minute after saving (the importer writes
 * the file fields after the post itself is saved).
 */
add_action(
	'save_post_lesson',
	function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_next_scheduled( 'potw_fill_homework_one', array( (int) $post_id ) ) ) {
			return;
		}
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'potw_fill_homework_one', array( (int) $post_id ) );
	}
);
add_action( 'potw_fill_homework_one', 'potw_fill_homework' );

/** Admin tool: admin-ajax.php?action=potw_fill_homework&apply=0|1 (wp_rest nonce to apply). */
add_action(
	'wp_ajax_potw_fill_homework',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		$apply = ! empty( $_REQUEST['apply'] ); // phpcs:ignore
		if ( $apply && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'wp_rest' ) ) {
			wp_send_json_error( 'Bad nonce', 403 );
		}
		@set_time_limit( 300 ); // phpcs:ignore
		$ids = get_posts(
			array(
				'post_type'      => 'lesson',
				'post_status'    => array( 'publish', 'private', 'draft', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$report = array();
		$empty  = 0;
		foreach ( $ids as $id ) {
			if ( potw_homework_rows( $id ) ) {
				continue;
			}
			$empty++;
			$found = potw_find_homework( $id );
			if ( ! $found ) {
				continue;
			}
			$report[] = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'from'  => array_keys( $found ),
				'rows'  => $apply ? potw_fill_homework( $id ) : array_sum( array_map( 'count', $found ) ),
			);
		}
		wp_send_json_success(
			array(
				'applied'          => $apply,
				'without_homework' => $empty,
				'fillable'         => count( $report ),
				'lessons'          => $report,
			)
		);
	}
);
