<?php
/**
 * "Current Session" page: show one whole study year at a time, with a
 * study-year switcher instead of Previous / Next.
 *
 * A study year runs August → July (lessons run September–May), so the
 * 2026–27 session holds lessons dated 1 Aug 2026 – 31 Jul 2027.
 * Lessons that were in the old site's "Archive" category (meta _potw_archive,
 * set by the POTW Lesson Importer) are not part of any study year.
 *
 * Applies to the Query Loop that lists lessons on the page whose slug is
 * POTW_SESSION_PAGE.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POTW_SESSION_PAGE = 'current-session';

/** Study year a timestamp/date belongs to (the year it starts in). */
function potw_session_of( $date ) {
	$ts = is_numeric( $date ) ? (int) $date : strtotime( $date );
	$y  = (int) gmdate( 'Y', $ts );
	return (int) gmdate( 'n', $ts ) >= 8 ? $y : $y - 1;
}

function potw_session_label( $year, $short = false ) {
	return $short ? $year . '–' . substr( (string) ( $year + 1 ), -2 ) : $year . '–' . ( $year + 1 );
}

/**
 * All study years that have lessons: year => [ 'count' => n, 'book' => 'Acts' ].
 * Newest first. Cached until a lesson changes.
 */
function potw_sessions() {
	$cached = get_transient( 'potw_lesson_sessions' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT p.ID, p.post_date, t.name AS book
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_potw_archive'
		 LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		 LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
		 LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 WHERE p.post_type = 'lesson' AND p.post_status = 'publish'
		   AND ( a.meta_value IS NULL OR a.meta_value <> '1' )"
	);

	$sessions = array();
	$seen     = array();
	$books    = array();
	foreach ( $rows as $r ) {
		$s = potw_session_of( $r->post_date );
		if ( empty( $seen[ $r->ID ] ) ) {
			$seen[ $r->ID ] = true;
			$sessions[ $s ] = array( 'count' => ( $sessions[ $s ]['count'] ?? 0 ) + 1 );
		}
		if ( $r->book && 'Uncategorized' !== $r->book ) {
			$books[ $s ][ $r->book ] = ( $books[ $s ][ $r->book ] ?? 0 ) + 1;
		}
	}
	foreach ( $sessions as $s => &$info ) {
		$info['book'] = '';
		if ( ! empty( $books[ $s ] ) ) {
			arsort( $books[ $s ] );
			$info['book'] = (string) key( $books[ $s ] );
		}
	}
	unset( $info );
	krsort( $sessions );

	set_transient( 'potw_lesson_sessions', $sessions, DAY_IN_SECONDS );
	return $sessions;
}

function potw_sessions_flush( $post_id = 0 ) {
	if ( ! $post_id || 'lesson' === get_post_type( $post_id ) ) {
		delete_transient( 'potw_lesson_sessions' );
	}
}
add_action( 'save_post_lesson', 'potw_sessions_flush' );
add_action( 'deleted_post', 'potw_sessions_flush' );
add_action( 'trashed_post', 'potw_sessions_flush' );

/** The study year being viewed: ?session=2025, else the current one (or the latest with lessons). */
function potw_requested_session() {
	$sessions = potw_sessions();
	$asked    = isset( $_GET['session'] ) ? (int) $_GET['session'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $asked && isset( $sessions[ $asked ] ) ) {
		return $asked;
	}
	$current = potw_session_of( current_time( 'timestamp' ) );
	if ( isset( $sessions[ $current ] ) || ! $sessions ) {
		return $current;
	}
	return (int) key( $sessions ); // newest with lessons.
}

function potw_is_session_page() {
	return is_page( POTW_SESSION_PAGE );
}

/** Query: the whole study year, oldest first (lesson 1, 2, 3 …). */
add_filter(
	'query_loop_block_query_vars',
	function ( $query ) {
		if ( ! potw_is_session_page() || 'lesson' !== ( $query['post_type'] ?? '' ) ) {
			return $query;
		}
		$s = potw_requested_session();

		$query['posts_per_page'] = 200;
		$query['paged']          = 1;
		$query['offset']         = 0;
		$query['orderby']        = 'date';
		$query['order']          = 'ASC';
		$query['date_query']     = array(
			array(
				'after'     => $s . '-08-01 00:00:00',
				'before'    => ( $s + 1 ) . '-07-31 23:59:59',
				'inclusive' => true,
			),
		);
		$query['meta_query']   = (array) ( $query['meta_query'] ?? array() );
		$query['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_potw_archive',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_potw_archive',
				'value'   => '1',
				'compare' => '!=',
			),
		);
		return $query;
	}
);

/** Study-year switcher markup. */
function potw_session_nav( $where = 'top' ) {
	$sessions = potw_sessions();
	if ( ! $sessions ) {
		return '';
	}
	$active = potw_requested_session();
	$base   = get_permalink();

	$html  = '<nav class="potw-sessions potw-sessions--' . esc_attr( $where ) . '" aria-label="' . esc_attr__( 'Study year', 'potw-fse' ) . '">';
	if ( 'top' === $where ) {
		$info  = $sessions[ $active ] ?? array( 'count' => 0, 'book' => '' );
		$html .= '<p class="potw-sessions__now"><strong>' . esc_html( potw_session_label( $active ) ) . '</strong>';
		if ( $info['book'] ) {
			$html .= ' · ' . esc_html( $info['book'] );
		}
		$html .= ' · ' . sprintf( esc_html( _n( '%d lesson', '%d lessons', $info['count'], 'potw-fse' ) ), (int) $info['count'] ) . '</p>';
	} else {
		$html .= '<p class="potw-sessions__now">' . esc_html__( 'Other study years', 'potw-fse' ) . '</p>';
	}
	$html .= '<ul class="potw-sessions__list">';
	foreach ( $sessions as $year => $info ) {
		$is   = (int) $year === (int) $active;
		$url  = $year === potw_session_of( current_time( 'timestamp' ) ) ? $base : add_query_arg( 'session', (int) $year, $base );
		$html .= '<li><a class="potw-sessions__item' . ( $is ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $is ? ' aria-current="page"' : '' ) . '>';
		$html .= '<span class="potw-sessions__year">' . esc_html( potw_session_label( $year, true ) ) . '</span>';
		if ( $info['book'] ) {
			$html .= '<span class="potw-sessions__book">' . esc_html( $info['book'] ) . '</span>';
		}
		$html .= '</a></li>';
	}
	return $html . '</ul></nav>';
}

/** Put the switcher above the list… */
add_filter(
	'render_block_core/query',
	function ( $content, $block ) {
		if ( ! potw_is_session_page() || 'lesson' !== ( $block['attrs']['query']['postType'] ?? '' ) ) {
			return $content;
		}
		return potw_session_nav( 'top' ) . $content;
	},
	10,
	2
);

/** …and replace Previous / Next under it. */
add_filter(
	'render_block_core/query-pagination',
	function ( $content ) {
		return potw_is_session_page() ? potw_session_nav( 'bottom' ) : $content;
	}
);
