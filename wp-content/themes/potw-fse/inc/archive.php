<?php
/**
 * Archive, full-year homework and small list tweaks.
 *
 * 1. "Open Lesson" button says "Open Calendar" / "Open Schedule" for those entries.
 * 2. Full-year homework: every homework file for a study year, so people can work
 *    ahead (Current Session, under the table).
 * 3. [potw_archive]: browse everything by book of the Bible (Old / New Testament),
 *    plus Themes and Church History, like the old site's Archive pages.
 * 4. Book (category) pages: all lessons on one page in chapter order, and for
 *    lessons from the old Archive the year on their files instead of re-post dates.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------------ */
/* 1. Button label                                                           */
/* ------------------------------------------------------------------------ */

add_filter(
	'render_block_core/read-more',
	function ( $content, $block, $instance = null ) {
		$post_id = function_exists( 'potw_block_post_id' ) ? potw_block_post_id( $instance ) : (int) get_the_ID();
		if ( ! $post_id || 'lesson' !== get_post_type( $post_id ) ) {
			return $content;
		}
		$title = get_the_title( $post_id );
		$label = '';
		if ( preg_match( '/calendar/i', $title ) ) {
			$label = __( 'Open Calendar', 'potw-fse' );
		} elseif ( preg_match( '/schedule/i', $title ) ) {
			$label = __( 'Open Schedule', 'potw-fse' );
		}
		return $label ? str_replace( 'Open Lesson', esc_html( $label ), $content ) : $content;
	},
	10,
	3
);

/* ------------------------------------------------------------------------ */
/* Helpers                                                                   */
/* ------------------------------------------------------------------------ */

/** Years written on a lesson's files: lecture years, else homework years. */
function potw_file_years( $post_id ) {
	$years = array();
	foreach ( array( array( 'lesson_file', 'lesson_year' ), array( 'homework_file', 'homework_year' ) ) as $pair ) {
		list( $rep, $sub ) = $pair;
		$n = (int) get_post_meta( $post_id, $rep, true );
		for ( $i = 0; $i < $n; $i++ ) {
			$y = (int) get_post_meta( $post_id, "{$rep}_{$i}_{$sub}", true );
			if ( $y ) {
				$years[] = $y;
			}
		}
		if ( $years ) {
			break;
		}
	}
	$years = array_unique( $years );
	sort( $years );
	return $years;
}

/** Every year on a lesson's files (lecture and homework). */
function potw_all_file_years( $post_id ) {
	$years = array();
	foreach ( array( array( 'lesson_file', 'lesson_year' ), array( 'homework_file', 'homework_year' ) ) as $pair ) {
		list( $rep, $sub ) = $pair;
		$n = (int) get_post_meta( $post_id, $rep, true );
		for ( $i = 0; $i < $n; $i++ ) {
			$y = (int) get_post_meta( $post_id, "{$rep}_{$i}_{$sub}", true );
			if ( $y ) {
				$years[] = $y;
			}
		}
	}
	return array_values( array_unique( $years ) );
}

/**
 * Old Archive post that the old site reused for a recent study (it has files from
 * after the first study year). The recent study has its own lessons, so these
 * copies are left out of the lists.
 */
function potw_is_reused( $post_id ) {
	if ( '1' !== (string) get_post_meta( $post_id, '_potw_archive', true ) || ! function_exists( 'potw_oldest_session' ) ) {
		return false;
	}
	$years = potw_all_file_years( $post_id );
	if ( ! $years || max( $years ) <= potw_oldest_session() + 1 ) {
		return false;
	}
	// Only a copy if a current (non-Archive) lesson with the same title exists.
	return isset( potw_current_titles()[ potw_sort_title( $post_id ) ] );
}

/** Sort titles of all current (non-Archive) lessons, for spotting reused copies. */
function potw_current_titles() {
	static $titles = null;
	if ( null === $titles ) {
		$titles = array();
		$ids    = get_posts(
			array(
				'post_type'      => 'lesson',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
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
				),
			)
		);
		foreach ( $ids as $id ) {
			$titles[ potw_sort_title( $id ) ] = true;
		}
	}
	return $titles;
}

/** "2017" or "2016–2017". */
function potw_file_years_label( $post_id ) {
	$y = potw_file_years( $post_id );
	if ( ! $y ) {
		return '';
	}
	// "2021, 2019, 2018": the years the files are from, newest first (not a range).
	rsort( $y );
	return implode( ', ', $y );
}

/** Ymd used to sort lessons by date: Begin date, or for old Archive lessons the latest year on their files. */
function potw_effective_date( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}
	if ( '1' === (string) get_post_meta( $post_id, '_potw_archive', true ) ) {
		$y = potw_file_years( $post_id );
		if ( $y ) {
			return $cache[ $post_id ] = (int) ( end( $y ) . '0101' );
		}
	}
	$start = (string) get_post_meta( $post_id, 'lesson_start_date', true );
	return $cache[ $post_id ] = preg_match( '/^\d{8}$/', $start ) ? (int) $start : (int) get_post_time( 'Ymd', false, $post_id );
}

/** Natural sort key for a title ("Isaiah 1:1–2:22" → "isaiah 1:1-2:22"). */
function potw_sort_title( $post_id ) {
	$t = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	$t = strtolower( str_replace( array( '–', '—' ), '-', $t ) );
	$t = preg_replace( '/\s*-\s*/', '-', preg_replace( '/\s+/u', ' ', trim( $t ) ) ); // "34:1 – 37:36" == "34:1-37:36".
	$t = preg_replace( '/^[^\p{L}\p{N}]+/u', '', $t ); // leading quotes.
	return preg_replace( '/^the\s+/', '', $t );         // "The Priesthood" sorts under P.
}

/* ------------------------------------------------------------------------ */
/* 2. Full-year homework                                                     */
/* ------------------------------------------------------------------------ */

/**
 * Every homework file for study year $s: homework dated $s or $s+1 on any
 * lesson of the books in that year's table. Returns rows sorted in chapter order:
 * [ 'title', 'url' (lesson), 'pdf', 'word', 'year' ].
 */
function potw_session_homework( $s ) {
	$cache_key = 'potw_year_hw_' . (int) $s;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	// Books in this year's table (not Extras).
	$table = get_posts(
		array(
			'post_type'      => 'lesson',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => 'lesson_start_date',
					'value'   => array( $s . '0801', ( $s + 1 ) . '0731' ),
					'compare' => 'BETWEEN',
					'type'    => 'CHAR',
				),
			),
			'tax_query'      => function_exists( 'potw_not_extra_clause' ) ? array( potw_not_extra_clause() ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$books = array();
	foreach ( $table as $id ) {
		foreach ( wp_get_post_categories( $id ) as $c ) {
			$books[ $c ] = true;
		}
	}
	$uncat = (int) get_option( 'default_category' );
	unset( $books[ $uncat ] );
	if ( ! $books ) {
		return array();
	}

	$ids  = get_posts(
		array(
			'post_type'      => 'lesson',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'category__in'   => array_keys( $books ),
		)
	);
	$rows = array();
	$seen = array();
	foreach ( $ids as $id ) {
		$n = (int) get_post_meta( $id, 'homework_file', true );
		for ( $i = 0; $i < $n; $i++ ) {
			$year = (int) get_post_meta( $id, "homework_file_{$i}_homework_year", true );
			if ( $year !== (int) $s && $year !== (int) $s + 1 ) {
				continue;
			}
			$pdf  = (int) get_post_meta( $id, "homework_file_{$i}_homework_pdf", true );
			$word = (int) get_post_meta( $id, "homework_file_{$i}_homework_word", true );
			$key  = $pdf . '|' . $word;
			if ( ( ! $pdf && ! $word ) || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$rows[]       = array(
				'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'sort'  => potw_sort_title( $id ),
				'url'   => get_permalink( $id ),
				'pdf'   => $pdf ? wp_get_attachment_url( $pdf ) : '',
				'word'  => $word ? wp_get_attachment_url( $word ) : '',
				'year'  => $year,
			);
		}
	}
	usort(
		$rows,
		function ( $a, $b ) {
			return strnatcasecmp( $a['sort'], $b['sort'] );
		}
	);
	set_transient( $cache_key, $rows, 6 * HOUR_IN_SECONDS );
	return $rows;
}

/** Clear the cached homework lists when a lesson changes. */
add_action(
	'save_post_lesson',
	function () {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_potw\\_year\\_hw\\_%' OR option_name LIKE '\\_transient\\_timeout\\_potw\\_year\\_hw\\_%'" ); // phpcs:ignore
		delete_transient( 'potw_cat_counts_v3' );
	}
);

function potw_session_homework_html( $s ) {
	$rows = potw_session_homework( $s );
	if ( ! $rows ) {
		return '';
	}
	$html  = '<section class="potw-year-homework" id="year-homework" aria-labelledby="potw-year-homework-title">';
	$html .= '<h2 id="potw-year-homework-title" class="potw-year-homework__title">' . esc_html__( 'Homework for the whole year', 'potw-fse' ) . '</h2>';
	$html .= '<p class="potw-year-homework__intro">' . esc_html(
		sprintf(
			/* translators: %d number of homework lessons */
			_n( 'All %d homework lesson for this study, ready to download, so you can work ahead.', 'All %d homework lessons for this study, ready to download, so you can work ahead.', count( $rows ), 'potw-fse' ),
			count( $rows )
		)
	) . '</p>';
	$html .= '<ol class="potw-year-homework__list">';
	foreach ( $rows as $r ) {
		$html .= '<li class="potw-year-homework__item"><span class="potw-year-homework__name">' . esc_html( $r['title'] ) . '</span><span class="potw-year-homework__buttons">';
		if ( $r['pdf'] ) {
			$html .= '<a class="lesson-repeater-btn" href="' . esc_url( $r['pdf'] ) . '" target="_blank" rel="noopener">PDF<span class="screen-reader-text"> – ' . esc_html( $r['title'] ) . '</span></a>';
		}
		if ( $r['word'] ) {
			$html .= '<a class="lesson-repeater-btn" href="' . esc_url( $r['word'] ) . '" target="_blank" rel="noopener">Word<span class="screen-reader-text"> – ' . esc_html( $r['title'] ) . '</span></a>';
		}
		$html .= '</span></li>';
	}
	return $html . '</ol></section>';
}

/* ------------------------------------------------------------------------ */
/* 3. Archive page: [potw_archive]                                           */
/* ------------------------------------------------------------------------ */

function potw_bible_books() {
	return array(
		'ot' => array( 'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalms', 'Proverbs', 'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum', 'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi' ),
		'nt' => array( 'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy', 'Titus', 'Philemon', 'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John', 'Jude', 'Revelation' ),
	);
}

/** Lesson count per category (lessons only). */
function potw_category_lesson_counts() {
	$cached = get_transient( 'potw_cat_counts_v3' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'lesson',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		if ( potw_is_reused( $id ) ) {
			continue;
		}
		foreach ( wp_get_post_categories( $id ) as $c ) {
			$out[ $c ] = ( $out[ $c ] ?? 0 ) + 1;
		}
	}
	set_transient( 'potw_cat_counts_v3', $out, DAY_IN_SECONDS );
	return $out;
}

function potw_archive_shortcode() {
	$books  = potw_bible_books();
	$counts = potw_category_lesson_counts();
	$terms  = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	$by_book = array(
		'ot' => array(),
		'nt' => array(),
	);
	$other   = array();
	foreach ( $terms as $t ) {
		$n = $counts[ $t->term_id ] ?? 0;
		if ( ! $n || 'uncategorized' === $t->slug ) {
			continue;
		}
		$first = trim( explode( '/', $t->name )[0] );
		$placed = false;
		foreach ( $books as $testament => $list ) {
			$pos = array_search( $first, $list, true );
			if ( false !== $pos ) {
				$by_book[ $testament ][] = array( $pos, $t, $n );
				$placed                  = true;
				break;
			}
		}
		if ( ! $placed ) {
			$other[] = array( $t, $n );
		}
	}
	foreach ( $by_book as &$list ) {
		usort(
			$list,
			function ( $a, $b ) {
				return $a[0] <=> $b[0] ?: strcmp( $a[1]->name, $b[1]->name );
			}
		);
	}
	unset( $list );

	$card = function ( $t, $n ) {
		return '<li><a class="potw-archive__book" href="' . esc_url( get_term_link( $t ) ) . '"><span class="potw-archive__name">' . esc_html( $t->name ) . '</span><span class="potw-archive__count">' . esc_html( sprintf( _n( '%d lesson', '%d lessons', $n, 'potw-fse' ), $n ) ) . '</span></a></li>';
	};

	$html = '<div class="potw-archive">';
	foreach ( array( 'ot' => __( 'Old Testament', 'potw-fse' ), 'nt' => __( 'New Testament', 'potw-fse' ) ) as $key => $label ) {
		if ( ! $by_book[ $key ] ) {
			continue;
		}
		$html .= '<section class="potw-archive__section"><h2>' . esc_html( $label ) . '</h2><ul class="potw-archive__grid">';
		foreach ( $by_book[ $key ] as $row ) {
			$html .= $card( $row[1], $row[2] );
		}
		$html .= '</ul></section>';
	}
	$page = get_page_by_path( defined( 'POTW_SESSION_PAGE' ) ? POTW_SESSION_PAGE : 'current-session' );
	if ( $page ) {
		$html .= '<p class="potw-archive__years">' . esc_html__( 'Looking for a study year?', 'potw-fse' ) . ' <a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html__( 'Browse by year on Current Session', 'potw-fse' ) . ' →</a></p>';
	}
	return $html . '</div>';
}
add_shortcode( 'potw_archive', 'potw_archive_shortcode' );

/* ------------------------------------------------------------------------ */
/* 4. Book pages: all lessons, chapter order, file year for old lessons      */
/* ------------------------------------------------------------------------ */

function potw_is_book_archive() {
	return ! is_admin() && is_category();
}

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( $q->is_main_query() && ! is_admin() && $q->is_category() ) {
			$q->set( 'posts_per_page', 300 );
			$q->set( 'no_found_rows', true );
		}
	},
	20
);

/* Query Loop blocks that inherit the main query on book pages. */
add_filter(
	'query_loop_block_query_vars',
	function ( $query ) {
		if ( potw_is_book_archive() && 'lesson' === ( $query['post_type'] ?? '' ) ) {
			$query['posts_per_page'] = 300;
		}
		return $query;
	},
	20
);

add_filter(
	'the_posts',
	function ( $posts, $q ) {
		if ( is_admin() || ! ( $q->is_category() || $q->get( 'potw_older' ) ) ) {
			return $posts;
		}
		$posts = array_values(
			array_filter(
				$posts,
				function ( $p ) {
					return 'lesson' !== $p->post_type || ! potw_is_reused( $p->ID );
				}
			)
		);
		if ( count( $posts ) < 2 ) {
			return $posts;
		}
		if ( is_category( array( 'church-history-biographies', 'church-history-lectures' ) ) ) {
			// Biographies and lectures: A to Z.
			usort(
				$posts,
				function ( $a, $b ) {
					return strnatcasecmp( potw_sort_title( $a->ID ), potw_sort_title( $b->ID ) );
				}
			);
			return $posts;
		}
		usort(
			$posts,
			function ( $a, $b ) {
				// Latest first: Begin date for current lessons, the year on the files for old Archive lessons.
				$c = potw_effective_date( $b->ID ) <=> potw_effective_date( $a->ID );
				return $c ? $c : strnatcasecmp( potw_sort_title( $b->ID ), potw_sort_title( $a->ID ) ); // same date: later chapter first.
			}
		);
		return $posts;
	},
	10,
	2
);

/**
 * Lessons from the old Archive were re-posted in 2020–2021, so their Begin/End
 * dates say nothing. On book pages show the year on their files instead.
 */
add_filter(
	'render_block_core/paragraph',
	function ( $content, $block, $instance = null ) {
		$key = $block['attrs']['metadata']['bindings']['content']['args']['key'] ?? '';
		if ( ! in_array( $key, array( 'lesson_start_date', 'lesson_end_date' ), true ) ) {
			return $content;
		}
		$post_id = function_exists( 'potw_block_post_id' ) ? potw_block_post_id( $instance ) : 0;
		if ( ! $post_id || '1' !== (string) get_post_meta( $post_id, '_potw_archive', true ) ) {
			return $content;
		}
		if ( 'lesson_end_date' === $key ) {
			return '';
		}
		$label = potw_file_years_label( $post_id );
		return $label ? '<p class="potw-file-year">' . esc_html( $label ) . '</p>' : '';
	},
	20,
	3
);

/* Book pages say "Genesis", not "Category: Genesis". */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

/* Themes and Church History pages: every row would show the same category, so drop the Book column there. */
const POTW_NO_BOOK_CATEGORIES = array( 'themes', 'church-history-biographies', 'church-history-lectures' );

add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_category( POTW_NO_BOOK_CATEGORIES ) ) {
			$classes[] = 'potw-no-book';
		}
		if ( is_category( array( 'church-history-biographies', 'church-history-lectures' ) ) ) {
			$classes[] = 'potw-no-date';
		}
		return $classes;
	}
);

/* Sortable tables: each lesson row carries its sort date (Ymd) as a class, read by table-sort.js. */
add_filter(
	'post_class',
	function ( $classes, $class, $post_id ) {
		if ( 'lesson' === get_post_type( $post_id ) && ! is_singular( 'lesson' ) && function_exists( 'potw_effective_date' ) ) {
			$classes[] = 'potw-sort-' . potw_effective_date( $post_id );
		}
		return $classes;
	},
	10,
	3
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$file = get_stylesheet_directory() . '/assets/js/table-sort.js';
		if ( file_exists( $file ) ) {
			wp_enqueue_script( 'potw-table-sort', get_stylesheet_directory_uri() . '/assets/js/table-sort.js', array( 'potw-grid-list' ), filemtime( $file ), true );
		}
	},
	30
);
