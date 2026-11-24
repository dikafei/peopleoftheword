<?php
/**
 * Study years: one entry per study year with its theme and intro
 * ("Experiencing God's Power Through Acts", dates, verses...).
 *
 * - Lessons → Study years in wp-admin. Set the year (the year it starts, e.g. 2026)
 *   and optionally the dates line.
 * - Current Session shows the full intro above that year's table.
 * - The homepage shows a short version (excerpt, or the first paragraph) with a link.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const POTW_STUDY_YEAR = 'study_year';

add_action(
	'init',
	function () {
		register_post_type(
			POTW_STUDY_YEAR,
			array(
				'labels'       => array(
					'name'          => __( 'Study years', 'potw-fse' ),
					'singular_name' => __( 'Study year', 'potw-fse' ),
					'add_new_item'  => __( 'Add study year', 'potw-fse' ),
					'edit_item'     => __( 'Edit study year', 'potw-fse' ),
					'all_items'     => __( 'Study years', 'potw-fse' ),
				),
				'description'  => __( 'Theme and intro for each study year, shown above the lessons on Current Session and the homepage.', 'potw-fse' ),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=lesson',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'menu_icon'    => 'dashicons-calendar-alt',
			)
		);
		foreach ( array( '_potw_year' => 'integer', '_potw_dates' => 'string' ) as $key => $type ) {
			register_post_meta(
				POTW_STUDY_YEAR,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
);

/* Year + dates box in the editor. */
add_action(
	'add_meta_boxes_' . POTW_STUDY_YEAR,
	function () {
		add_meta_box(
			'potw_study_year',
			__( 'Study year', 'potw-fse' ),
			function ( $post ) {
				wp_nonce_field( 'potw_study_year', 'potw_study_year_nonce' );
				$year  = (int) get_post_meta( $post->ID, '_potw_year', true );
				$dates = (string) get_post_meta( $post->ID, '_potw_dates', true );
				echo '<p><label for="potw_year"><strong>' . esc_html__( 'Starts in (year)', 'potw-fse' ) . '</strong></label><br><input type="number" id="potw_year" name="potw_year" min="1974" max="2100" value="' . esc_attr( $year ? $year : '' ) . '" style="width:100%"><br><small>' . esc_html__( 'e.g. 2026 for the 2026–27 study (Aug–Jul).', 'potw-fse' ) . '</small></p>';
				echo '<p><label for="potw_dates"><strong>' . esc_html__( 'Dates line (optional)', 'potw-fse' ) . '</strong></label><br><input type="text" id="potw_dates" name="potw_dates" value="' . esc_attr( $dates ) . '" placeholder="September 10, 2026 – May 20, 2027" style="width:100%"></p>';
				echo '<p><small>' . esc_html__( 'The homepage shows the Excerpt if there is one, otherwise the first paragraph.', 'potw-fse' ) . '</small></p>';
			},
			POTW_STUDY_YEAR,
			'side',
			'high'
		);
	}
);

add_action(
	'save_post_' . POTW_STUDY_YEAR,
	function ( $post_id ) {
		if ( ! isset( $_POST['potw_study_year_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['potw_study_year_nonce'] ), 'potw_study_year' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$year = isset( $_POST['potw_year'] ) ? (int) $_POST['potw_year'] : 0;
		$year ? update_post_meta( $post_id, '_potw_year', $year ) : delete_post_meta( $post_id, '_potw_year' );
		$dates = isset( $_POST['potw_dates'] ) ? sanitize_text_field( wp_unslash( $_POST['potw_dates'] ) ) : '';
		$dates ? update_post_meta( $post_id, '_potw_dates', $dates ) : delete_post_meta( $post_id, '_potw_dates' );
	}
);

/* Year column in the list. */
add_filter(
	'manage_' . POTW_STUDY_YEAR . '_posts_columns',
	function ( $cols ) {
		return array_slice( $cols, 0, 2, true ) + array( 'potw_year' => __( 'Year', 'potw-fse' ) ) + $cols;
	}
);
add_action(
	'manage_' . POTW_STUDY_YEAR . '_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'potw_year' === $col ) {
			$y = (int) get_post_meta( $post_id, '_potw_year', true );
			echo $y ? esc_html( function_exists( 'potw_session_label' ) ? potw_session_label( $y ) : $y ) : '—';
		}
	},
	10,
	2
);

/** The published study-year entry for year $s, or null. */
function potw_study_year_post( $s ) {
	$p = get_posts(
		array(
			'post_type'      => POTW_STUDY_YEAR,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_potw_year', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (int) $s, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $p ? $p[0] : null;
}

/**
 * Intro markup for year $s.
 * $mode 'full' (Current Session) or 'short' (homepage: excerpt / first paragraph + link).
 */
function potw_study_year_intro( $s, $mode = 'full' ) {
	$post = potw_study_year_post( $s );
	if ( ! $post ) {
		return '';
	}
	$is_current = function_exists( 'potw_session_of' ) && (int) $s === potw_session_of( current_time( 'timestamp' ) );
	$eyebrow    = $is_current ? __( 'This year’s study', 'potw-fse' ) : sprintf( /* translators: study year like 2025–2026 */ __( 'The %s study', 'potw-fse' ), potw_session_label( $s ) );
	$dates      = (string) get_post_meta( $post->ID, '_potw_dates', true );

	$html  = '<section class="potw-year-intro potw-year-intro--' . esc_attr( $mode ) . '">';
	$html .= '<p class="potw-eyebrow">' . esc_html( $eyebrow ) . '</p>';
	$html .= '<h2 class="potw-year-intro__title">' . esc_html( get_the_title( $post ) ) . '</h2>';
	if ( $dates ) {
		$html .= '<p class="potw-year-intro__dates">' . esc_html( $dates ) . '</p>';
	}

	if ( 'short' === $mode ) {
		$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		if ( ! $text ) {
			$blocks = parse_blocks( $post->post_content );
			foreach ( $blocks as $b ) {
				if ( 'core/paragraph' === $b['blockName'] && trim( wp_strip_all_tags( $b['innerHTML'] ) ) ) {
					$text = trim( wp_strip_all_tags( $b['innerHTML'] ) );
					break;
				}
			}
		}
		if ( $text ) {
			$html .= '<p class="potw-year-intro__lead">' . esc_html( $text ) . '</p>';
		}
		$page = get_page_by_path( defined( 'POTW_SESSION_PAGE' ) ? POTW_SESSION_PAGE : 'current-session' );
		if ( $page ) {
			$html .= '<p class="potw-year-intro__more"><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html__( 'Read more about this year’s study', 'potw-fse' ) . ' →</a></p>';
		}
	} else {
		// Same filters as post content, without the global post loop.
		// First paragraph always visible; the rest folds away behind "Read more".
		$first = '';
		$rest  = '';
		foreach ( parse_blocks( $post->post_content ) as $b ) {
			if ( empty( $b['blockName'] ) && '' === trim( $b['innerHTML'] ) ) {
				continue;
			}
			$out = render_block( $b );
			if ( '' === $first && 'core/paragraph' === $b['blockName'] ) {
				$first = $out;
			} else {
				$rest .= $out;
			}
		}
		$html .= '<div class="potw-year-intro__body">' . $first;
		if ( '' !== trim( $rest ) ) {
			$html .= '<details class="potw-year-intro__details"><summary><span class="potw-year-intro__open">' . esc_html__( 'Read more', 'potw-fse' ) . '</span><span class="potw-year-intro__close">' . esc_html__( 'Show less', 'potw-fse' ) . '</span></summary><div class="potw-year-intro__rest">' . $rest . '</div></details>';
		}
		$html .= '</div>';
	}
	return $html . '</section>';
}
