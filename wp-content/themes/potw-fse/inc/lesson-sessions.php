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

/** queryId of the homepage "Lesson of The Week" list (same list, current study year only). */
const POTW_HOME_QUERY_ID = 916;

/** Begin date (ACF lesson_start_date, Ymd) as a timestamp; falls back to the publish date. */
function potw_lesson_start_ts( $start, $post_date ) {
	if ( $start && preg_match( '/^\d{8}$/', $start ) ) {
		return strtotime( $start . ' 12:00:00 UTC' );
	}
	return strtotime( $post_date );
}

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
	$cached = get_transient( 'potw_lesson_sessions_v2' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT p.ID, p.post_date, sd.meta_value AS start_date, t.name AS book
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} sd ON sd.post_id = p.ID AND sd.meta_key = 'lesson_start_date'
		 LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_potw_archive'
		 LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		 LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
		 LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 WHERE p.post_type = 'lesson' AND p.post_status = 'publish'
		   AND ( a.meta_value IS NULL OR a.meta_value <> '1' )"
	);

	// Extras are not part of a study year's package: leave them out of the counts and the book.
	$extra_ids = array();
	if ( defined( 'POTW_EXTRA_SLUG' ) ) {
		$term = get_term_by( 'slug', POTW_EXTRA_SLUG, POTW_LESSON_TYPE );
		if ( $term ) {
			$extra_ids = array_flip( array_map( 'intval', (array) get_objects_in_term( $term->term_id, POTW_LESSON_TYPE ) ) );
		}
	}

	$sessions = array();
	$seen     = array();
	$books    = array();
	foreach ( $rows as $r ) {
		if ( isset( $extra_ids[ (int) $r->ID ] ) ) {
			continue;
		}
		$s = potw_session_of( potw_lesson_start_ts( $r->start_date, $r->post_date ) );
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

	set_transient( 'potw_lesson_sessions_v2', $sessions, DAY_IN_SECONDS );
	return $sessions;
}

function potw_sessions_flush( $post_id = 0 ) {
	if ( ! $post_id || 'lesson' === get_post_type( $post_id ) ) {
		delete_transient( 'potw_lesson_sessions_v2' );
		delete_transient( 'potw_older_ids_v2' );
	}
}
add_action( 'save_post_lesson', 'potw_sessions_flush' );
add_action(
	'set_object_terms',
	function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'lesson_type' === $taxonomy || 'category' === $taxonomy ) {
			potw_sessions_flush( $object_id );
		}
	},
	10,
	4
);
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

/** "Everything else": ?session=older on Current Session (lessons before the first study year, and the old Archive). */
function potw_is_older_view() {
	return potw_is_session_page() && isset( $_GET['session'] ) && 'older' === $_GET['session']; // phpcs:ignore WordPress.Security.NonceVerification
}

/** Oldest study year with its own button. */
function potw_oldest_session() {
	$sessions = potw_sessions();
	return $sessions ? (int) min( array_keys( $sessions ) ) : 0;
}

/** Query args for "Everything else". */
function potw_older_meta_query() {
	return array(
		'relation' => 'OR',
		array(
			'key'   => '_potw_archive',
			'value' => '1',
		),
		array(
			'key'     => 'lesson_start_date',
			'value'   => potw_oldest_session() . '0801',
			'compare' => '<',
			'type'    => 'CHAR',
		),
	);
}

/**
 * IDs for "Everything else", newest first (cached). Leaves out Church History
 * Biographies / Lectures (they have their own pages) and old Archive posts the
 * old site reused for recent years (files from the first study year onwards).
 */
function potw_older_ids() {
	$cached = get_transient( 'potw_older_ids_v2' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$skip = array();
	foreach ( array( 'church-history-biographies', 'church-history-lectures' ) as $slug ) {
		$t = get_category_by_slug( $slug );
		if ( $t ) {
			$skip[] = (int) $t->term_id;
		}
	}
	$ids    = get_posts(
		array(
			'post_type'        => 'lesson',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'category__not_in' => $skip,
			'meta_query'       => potw_older_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$oldest = potw_oldest_session();
	$keep   = array();
	foreach ( $ids as $id ) {
		if ( '1' === (string) get_post_meta( $id, '_potw_archive', true ) && function_exists( 'potw_all_file_years' ) ) {
			$years = potw_all_file_years( $id );
			// Files from after the first study year (e.g. 2023 homework on a 2010 post) mean the
			// old site reused the post for a recent study, which has its own year button.
			if ( $years && max( $years ) > $oldest + 1 ) {
				continue;
			}
		}
		$keep[] = $id;
	}
	if ( function_exists( 'potw_effective_date' ) ) {
		usort(
			$keep,
			function ( $a, $b ) {
				$c = potw_effective_date( $b ) <=> potw_effective_date( $a );
				return $c ? $c : strnatcasecmp( potw_sort_title( $b ), potw_sort_title( $a ) );
			}
		);
	}
	set_transient( 'potw_older_ids_v2', $keep, DAY_IN_SECONDS );
	return $keep;
}

function potw_older_count() {
	return count( potw_older_ids() );
}

/** Query: the whole study year, oldest first (lesson 1, 2, 3 …). */
add_filter(
	'query_loop_block_query_vars',
	function ( $query, $block = null ) {
		$is_home_list = (int) ( $block->context['queryId'] ?? -1 ) === POTW_HOME_QUERY_ID;
		if ( ( ! potw_is_session_page() && ! $is_home_list ) || 'lesson' !== ( $query['post_type'] ?? '' ) ) {
			return $query;
		}
		if ( ! $is_home_list && potw_is_older_view() ) {
			// Everything else: all older lessons, latest first (sorted in 'the_posts', see archive.php).
			$query['posts_per_page'] = 800;
			$query['paged']          = 1;
			$query['offset']         = 0;
			$ids                     = potw_older_ids();
			$query['post__in']       = $ids ? $ids : array( 0 );
			$query['orderby']        = 'post__in';
			unset( $query['date_query'], $query['order'], $query['meta_query'], $query['tax_query'] );
			return $query;
		}
		$s = potw_requested_session();

		$query['posts_per_page'] = 200;
		$query['paged']          = 1;
		$query['offset']         = 0;
		// Study year and order come from the lesson's Begin date (Ymd), not its publish date.
		$query['meta_query']          = (array) ( $query['meta_query'] ?? array() );
		$query['meta_query']['start'] = array(
			'key'     => 'lesson_start_date',
			'value'   => array( $s . '0801', ( $s + 1 ) . '0731' ),
			'compare' => 'BETWEEN',
			'type'    => 'CHAR',
		);
		$query['orderby'] = array(
			'start' => 'ASC',
			'date'  => 'ASC',
		);
		unset( $query['order'], $query['date_query'] );
		if ( function_exists( 'potw_not_extra_clause' ) ) {
			$query['tax_query']   = (array) ( $query['tax_query'] ?? array() ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$query['tax_query'][] = potw_not_extra_clause();
		}
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
	},
	10,
	2
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
	if ( 'home' === $where ) {
		// Homepage: just the current study year, with a link to the others.
		$info  = $sessions[ $active ] ?? array( 'count' => 0, 'book' => '' );
		$page  = get_page_by_path( POTW_SESSION_PAGE );
		$html  = '<div class="potw-sessions potw-sessions--home"><p class="potw-sessions__now"><strong>' . esc_html( potw_session_label( $active ) ) . '</strong>';
		if ( $info['book'] ) {
			$html .= ' · ' . esc_html( $info['book'] );
		}
		$html .= ' · ' . sprintf( esc_html( _n( '%d lesson', '%d lessons', $info['count'], 'potw-fse' ) ), (int) $info['count'] ) . '</p>';
		if ( $page ) {
			$html .= '<span class="potw-sessions__links">';
			if ( function_exists( 'potw_session_homework' ) && potw_session_homework( $active ) ) {
				$html .= '<a class="potw-sessions__more" href="' . esc_url( get_permalink( $page ) ) . '#year-homework">' . esc_html__( 'All homework for the year', 'potw-fse' ) . ' →</a>';
			}
			$html .= '<a class="potw-sessions__more" href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html__( 'Other study years', 'potw-fse' ) . ' →</a></span>';
		}
		return $html . '</div>';
	}
	$older  = potw_is_older_view();
	$oldest = potw_oldest_session();
	if ( 'top' === $where && $older ) {
		$html .= '<p class="potw-sessions__now"><strong>' . esc_html__( 'Everything else', 'potw-fse' ) . '</strong> · ' . esc_html( sprintf( /* translators: study year */ __( 'before %s', 'potw-fse' ), potw_session_label( $oldest ) ) ) . ' · ' . sprintf( esc_html( _n( '%d lesson', '%d lessons', potw_older_count(), 'potw-fse' ) ), (int) potw_older_count() ) . '</p>';
	} elseif ( 'top' === $where ) {
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
		$is   = ! $older && (int) $year === (int) $active;
		$url  = $year === potw_session_of( current_time( 'timestamp' ) ) ? $base : add_query_arg( 'session', (int) $year, $base );
		$html .= '<li><a class="potw-sessions__item' . ( $is ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $is ? ' aria-current="page"' : '' ) . '>';
		$html .= '<span class="potw-sessions__year">' . esc_html( potw_session_label( $year, true ) ) . '</span>';
		if ( $info['book'] ) {
			$html .= '<span class="potw-sessions__book">' . esc_html( $info['book'] ) . '</span>';
		}
		$html .= '</a></li>';
	}
	// Everything before the first study year (incl. the old Archive), shown as one list.
	if ( $oldest ) {
		$html .= '<li><a class="potw-sessions__item potw-sessions__item--older' . ( $older ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'session', 'older', $base ) ) . '"' . ( $older ? ' aria-current="page"' : '' ) . '>';
		$html .= '<span class="potw-sessions__year">' . esc_html__( 'Everything else', 'potw-fse' ) . '</span>';
		$html .= '<span class="potw-sessions__book">' . esc_html( sprintf( /* translators: study year */ __( 'Before %s', 'potw-fse' ), potw_session_label( $oldest, true ) ) ) . '</span>';
		$html .= '</a></li>';
	}
	return $html . '</ul></nav>';
}

/** Put the switcher above the list… */
add_filter(
	'render_block_core/query',
	function ( $content, $block ) {
		$GLOBALS['potw_in_session_list'] = false;
		if ( POTW_HOME_QUERY_ID === (int) ( $block['attrs']['queryId'] ?? -1 ) ) {
			// Homepage: this year's table, then its Extras ("Also this year") if there are any.
			$extras = function_exists( 'potw_session_extras_html' ) ? potw_session_extras_html( potw_requested_session() ) : '';
			$intro  = function_exists( 'potw_study_year_intro' ) ? potw_study_year_intro( potw_requested_session(), 'short' ) : '';
			return $intro . potw_session_nav( 'home' ) . $content . $extras;
		}
		if ( ! potw_is_session_page() || 'lesson' !== ( $block['attrs']['query']['postType'] ?? '' ) ) {
			return $content;
		}
		$intro = ( ! potw_is_older_view() && function_exists( 'potw_study_year_intro' ) ) ? potw_study_year_intro( potw_requested_session(), 'full' ) : '';
		return $intro . potw_session_nav( 'top' ) . $content;
	},
	10,
	2
);

/** …and replace Previous / Next under it. */
add_filter(
	'render_block_core/query-pagination',
	function ( $content ) {
		if ( ! potw_is_session_page() ) {
			return $content;
		}
		if ( potw_is_older_view() ) {
			return potw_session_nav( 'bottom' );
		}
		// "Also this year" (Extras) sits between the table and the study-year switcher.
		$extras   = function_exists( 'potw_session_extras_html' ) ? potw_session_extras_html( potw_requested_session() ) : '';
		$homework = function_exists( 'potw_session_homework_html' ) ? potw_session_homework_html( potw_requested_session() ) : '';
		return $homework . $extras . potw_session_nav( 'bottom' );
	}
);


/**
 * Mark this week's lesson (start date <= today <= end date) in the session lists,
 * on the Current Session page and on the homepage.
 */
add_filter(
	'render_block_data',
	function ( $parsed ) {
		if ( 'core/query' === ( $parsed['blockName'] ?? '' ) && 'lesson' === ( $parsed['attrs']['query']['postType'] ?? '' )
			&& ( POTW_HOME_QUERY_ID === (int) ( $parsed['attrs']['queryId'] ?? -1 ) || potw_is_session_page() ) ) {
			$GLOBALS['potw_in_session_list'] = true;
		}
		return $parsed;
	}
);

add_filter(
	'post_class',
	function ( $classes, $class, $post_id ) {
		if ( empty( $GLOBALS['potw_in_session_list'] ) || 'lesson' !== get_post_type( $post_id ) ) {
			return $classes;
		}
		$start = (string) get_post_meta( $post_id, 'lesson_start_date', true );
		$end   = (string) get_post_meta( $post_id, 'lesson_end_date', true );
		$today = current_time( 'Ymd' );
		if ( $start && $end && $start <= $today && $today <= $end ) {
			$classes[] = 'is-this-week';
		}
		return $classes;
	},
	10,
	3
);

/** Lesson lists are always the table (the Grid / List switch is hidden), whatever view was saved before. */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_script( 'potw-grid-list', "document.documentElement.classList.remove('view-grid');document.documentElement.classList.add('view-list');try{localStorage.removeItem('potwPostView');}catch(e){}" );
	},
	20
);


/**
 * Every lesson needs a Begin date to land in a study year: if it is left empty,
 * use the publish date, with the End date a week later.
 */
add_action(
	'acf/save_post',
	function ( $post_id ) {
		if ( ! is_numeric( $post_id ) || 'lesson' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( ! get_post_meta( $post_id, 'lesson_start_date', true ) ) {
			$ts = get_post_time( 'U', false, $post_id );
			update_field( 'lesson_start_date', gmdate( 'Ymd', $ts ), $post_id );
			if ( ! get_post_meta( $post_id, 'lesson_end_date', true ) ) {
				update_field( 'lesson_end_date', gmdate( 'Ymd', $ts + 7 * DAY_IN_SECONDS ), $post_id );
			}
		}
		potw_sessions_flush( $post_id );
	},
	20
);
