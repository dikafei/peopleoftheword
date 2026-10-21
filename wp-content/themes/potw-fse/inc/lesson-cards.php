<?php
/**
 * Lesson cards & memory verse: hide what is empty.
 *
 * 1. A group block named "Memory Verse" (List View → rename) is removed when
 *    the lesson it shows has no Memory Verse.
 * 2. In lesson lists (query loops), a lesson without a featured image gets a
 *    patterned placeholder with its book name instead of an empty space.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lesson ID a block is rendering for (query loop item, else the current post). */
function potw_block_post_id( $instance ) {
	if ( $instance instanceof WP_Block && ! empty( $instance->context['postId'] ) ) {
		return (int) $instance->context['postId'];
	}
	return (int) get_the_ID();
}

/** Book (category) name for a lesson, used on placeholders. */
function potw_lesson_book( $post_id ) {
	foreach ( (array) get_the_category( $post_id ) as $term ) {
		if ( 'uncategorized' !== $term->slug ) {
			return $term->name;
		}
	}
	return '';
}

/**
 * 1. Memory Verse: drop the whole group when the field is empty.
 */
add_filter(
	'render_block_core/group',
	function ( $content, $block, $instance = null ) {
		$name = $block['attrs']['metadata']['name'] ?? '';
		if ( 0 !== strcasecmp( trim( $name ), 'Memory Verse' ) ) {
			return $content;
		}
		// The memory verse belongs to the lesson inside the query loop around this group.
		$post_id = potw_block_post_id( $instance );
		if ( ! $post_id && ! empty( $GLOBALS['potw_latest_lesson_id'] ) ) {
			$post_id = (int) $GLOBALS['potw_latest_lesson_id'];
		}
		$verse = $post_id && function_exists( 'get_field' ) ? get_field( 'memory_verse', $post_id ) : '';
		return '' === trim( wp_strip_all_tags( (string) $verse ) ) ? '' : $content;
	},
	10,
	3
);

/**
 * 2. Featured image placeholder inside query loops.
 */
add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block, $instance = null ) {
		if ( '' !== trim( $content ) ) {
			return $content;
		}
		$post_id = potw_block_post_id( $instance );
		// Only in lists of lessons — not for the lesson page's own (missing) image.
		if ( ! $post_id || 'lesson' !== get_post_type( $post_id ) || ( is_singular() && $post_id === get_queried_object_id() ) ) {
			return $content;
		}
		$book   = potw_lesson_book( $post_id );
		$height = (string) ( $block['attrs']['height'] ?? '' );
		$style  = ( '' !== $height && false === strpos( $height, '%' ) ) ? ' style="height:' . esc_attr( $height ) . ';min-height:0"' : '';
		return '<figure class="wp-block-post-featured-image potw-thumb-placeholder"' . $style . ' aria-hidden="true"><span>' . esc_html( $book ? $book : get_the_title( $post_id ) ) . '</span></figure>';
	},
	10,
	3
);

/**
 * 3. Don't repeat the verse: when a lesson's Verse is the same as its title
 *    ("Acts 6:1-15" / "Acts 6:1-15"), drop the verse paragraph and the vertical
 *    divider that follows it (single lesson, lesson cards, homepage).
 */
function potw_same_text( $a, $b ) {
	$n = function ( $s ) {
		$s = html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' );
		return strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', $s ) );
	};
	return '' !== $n( $a ) && $n( $a ) === $n( $b );
}

$GLOBALS['potw_skip_divider'] = false;

add_filter(
	'render_block_core/paragraph',
	function ( $content, $block, $instance = null ) {
		$GLOBALS['potw_skip_divider'] = false;
		$key = $block['attrs']['metadata']['bindings']['content']['args']['key'] ?? '';
		if ( 'lesson_verse' !== $key ) {
			return $content;
		}
		$post_id = potw_block_post_id( $instance );
		$text    = trim( html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES, 'UTF-8' ) );
		// No verse (topical lessons, bios) or the verse is just the title again: drop it and its divider.
		if ( '' === $text || ( $post_id && potw_same_text( $text, get_the_title( $post_id ) ) ) ) {
			$GLOBALS['potw_skip_divider'] = true;
			return '';
		}
		return $content;
	},
	10,
	3
);

add_filter(
	'render_block_core/separator',
	function ( $content, $block ) {
		if ( ! empty( $GLOBALS['potw_skip_divider'] ) && false !== strpos( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-vertical' ) ) {
			$GLOBALS['potw_skip_divider'] = false;
			return '';
		}
		return $content;
	},
	10,
	2
);

/**
 * 4. Category / tag archives list lessons too (the archive template's query
 *    follows the page's own query, which by default only holds blog posts).
 */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! ( $query->is_category() || $query->is_tag() ) ) {
			return;
		}
		$query->set( 'post_type', array( 'post', 'lesson' ) );
		$query->set( 'posts_per_page', 12 );
	}
);

/**
 * 5. Embedded PDFs open fitted to the width, without the thumbnail sidebar.
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( false === strpos( $content, 'potw-pdf-embed' ) ) {
			return $content;
		}
		return preg_replace( '#(<object\b[^>]*\bdata="[^"\#]+\.pdf)(")#i', '$1#view=FitH&amp;navpanes=0$2', $content );
	},
	20
);
