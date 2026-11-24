<?php
/**
 * Lesson type: lessons marked "Extra" are not part of a study year's package.
 * They stay out of the year's table (Current Session + homepage) and are listed
 * under it as "Also this year". They keep their own page and book category.
 *
 * Any Query Loop can list them: filter it by Lesson type → Extra.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POTW_LESSON_TYPE = 'lesson_type';
const POTW_EXTRA_SLUG  = 'extra';

add_action(
	'init',
	function () {
		register_taxonomy(
			POTW_LESSON_TYPE,
			'lesson',
			array(
				'labels'            => array(
					'name'          => __( 'Lesson types', 'potw-fse' ),
					'singular_name' => __( 'Lesson type', 'potw-fse' ),
					'menu_name'     => __( 'Lesson types', 'potw-fse' ),
					'all_items'     => __( 'All lesson types', 'potw-fse' ),
					'edit_item'     => __( 'Edit lesson type', 'potw-fse' ),
					'add_new_item'  => __( 'Add lesson type', 'potw-fse' ),
				),
				'description'       => __( 'Mark a lesson "Extra" to keep it out of the study year table.', 'potw-fse' ),
				'hierarchical'      => true, // checkbox UI in the editor.
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'lesson-type' ),
			)
		);
		if ( ! term_exists( POTW_EXTRA_SLUG, POTW_LESSON_TYPE ) ) {
			wp_insert_term(
				'Extra',
				POTW_LESSON_TYPE,
				array(
					'slug'        => POTW_EXTRA_SLUG,
					'description' => 'Not part of the study year package: listed under the table as "Also this year".',
				)
			);
		}
	}
);

/** Is this lesson an Extra? */
function potw_is_extra( $post_id ) {
	return has_term( POTW_EXTRA_SLUG, POTW_LESSON_TYPE, $post_id );
}

/** tax_query clause that leaves Extras out. */
function potw_not_extra_clause() {
	return array(
		'taxonomy' => POTW_LESSON_TYPE,
		'field'    => 'slug',
		'terms'    => array( POTW_EXTRA_SLUG ),
		'operator' => 'NOT IN',
	);
}

/** Extras whose Begin date falls in study year $s (Aug → Jul), oldest first. */
function potw_session_extras( $s ) {
	return get_posts(
		array(
			'post_type'      => 'lesson',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => POTW_LESSON_TYPE,
					'field'    => 'slug',
					'terms'    => array( POTW_EXTRA_SLUG ),
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'start' => array(
					'key'     => 'lesson_start_date',
					'value'   => array( $s . '0801', ( $s + 1 ) . '0731' ),
					'compare' => 'BETWEEN',
					'type'    => 'CHAR',
				),
			),
			'orderby'        => array( 'start' => 'ASC' ),
		)
	);
}

/** "Also this year" list markup for study year $s. */
function potw_session_extras_html( $s ) {
	$extras = potw_session_extras( $s );
	if ( ! $extras ) {
		return '';
	}
	$html = '<section class="potw-extras" aria-labelledby="potw-extras-title"><h2 id="potw-extras-title" class="potw-extras__title">' . esc_html__( 'Also this year', 'potw-fse' ) . '</h2><ul class="potw-extras__list">';
	foreach ( $extras as $p ) {
		$start = (string) get_post_meta( $p->ID, 'lesson_start_date', true );
		$cats  = get_the_terms( $p->ID, 'category' );
		$book  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
		$date  = $start ? date_i18n( get_option( 'date_format' ), strtotime( $start . ' 12:00:00' ) ) : '';
		$html .= '<li class="potw-extras__item"><a href="' . esc_url( get_permalink( $p ) ) . '">';
		$html .= '<span class="potw-extras__name">' . esc_html( get_the_title( $p ) ) . '</span>';
		$html .= '<span class="potw-extras__meta">' . esc_html( implode( ' · ', array_filter( array( $book, $date ) ) ) ) . '</span>';
		$html .= '</a></li>';
	}
	return $html . '</ul></section>';
}

/**
 * Admin tool: mark lessons that are not about the study year's main book as Extra.
 * admin-ajax.php?action=potw_auto_extras&apply=0|1 (wp_rest nonce for apply=1).
 * Main book = the category most lessons of that year use. Dry run by default.
 */
add_action(
	'wp_ajax_potw_auto_extras',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		$apply = ! empty( $_REQUEST['apply'] ); // phpcs:ignore
		if ( $apply && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'wp_rest' ) ) {
			wp_send_json_error( 'Bad nonce', 403 );
		}
		$ids = get_posts(
			array(
				'post_type'      => 'lesson',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$years = array();
		foreach ( $ids as $id ) {
			if ( '1' === get_post_meta( $id, '_potw_archive', true ) ) {
				continue;
			}
			$s    = potw_session_of( potw_lesson_start_ts( get_post_meta( $id, 'lesson_start_date', true ), get_post_field( 'post_date', $id ) ) );
			$cats = wp_get_post_terms( $id, 'category', array( 'fields' => 'names' ) );
			$cats = array_values( array_diff( (array) $cats, array( 'Uncategorized' ) ) );
			$years[ $s ][ $id ] = $cats;
		}
		krsort( $years );
		$report = array();
		foreach ( $years as $s => $lessons ) {
			$count = array();
			foreach ( $lessons as $cats ) {
				foreach ( $cats as $c ) {
					$count[ $c ] = ( $count[ $c ] ?? 0 ) + 1;
				}
			}
			arsort( $count );
			$main   = (string) key( $count );
			$extras = array();
			foreach ( $lessons as $id => $cats ) {
				if ( $main && ! in_array( $main, $cats, true ) ) {
					$extras[] = array(
						'id'    => $id,
						'title' => get_the_title( $id ),
						'cats'  => implode( ', ', $cats ),
						'was'   => potw_is_extra( $id ),
					);
					if ( $apply ) {
						wp_set_object_terms( $id, POTW_EXTRA_SLUG, POTW_LESSON_TYPE, true );
					}
				}
			}
			$report[] = array(
				'year'   => potw_session_label( $s, true ),
				'main'   => $main,
				'total'  => count( $lessons ),
				'books'  => $count,
				'extras' => $extras,
			);
		}
		if ( $apply ) {
			potw_sessions_flush();
		}
		wp_send_json_success( array( 'applied' => $apply, 'years' => $report ) );
	}
);

/**
 * Admin tool: lock lessons so the importer skips them (same as ticking "Lock" in the editor).
 * POST admin-ajax.php action=potw_lock_lessons&ids=1,2,3&_wpnonce=(wp_rest)
 */
add_action(
	'wp_ajax_potw_lock_lessons',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'wp_rest' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		$out = array();
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) ( $_REQUEST['ids'] ?? '' ) ) ) ) as $id ) { // phpcs:ignore
			if ( 'lesson' === get_post_type( $id ) ) {
				update_post_meta( $id, '_potw_import_lock', 1 );
				$out[ $id ] = get_the_title( $id );
			}
		}
		wp_send_json_success( $out );
	}
);
