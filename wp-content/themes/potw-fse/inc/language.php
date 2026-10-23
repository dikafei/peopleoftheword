<?php
/**
 * Language switcher with flags, shown in the header next to the menu.
 * Uses TranslatePress (free). Its floating switcher is hidden in style.css.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Flag links for every published language; the current one is marked. */
function potw_language_switcher() {
	if ( ! function_exists( 'trp_custom_language_switcher' ) ) {
		return '';
	}
	$langs = trp_custom_language_switcher();
	if ( count( $langs ) < 2 ) {
		return '';
	}
	global $TRP_LANGUAGE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$current = $TRP_LANGUAGE ? $TRP_LANGUAGE : get_locale(); // phpcs:ignore
	$default = potw_default_language();

	// On the default language, only offer languages this page has actually been translated into.
	// (On a translated page the switcher always shows, so visitors can get back to English.)
	if ( $current === $default ) {
		$id = is_singular() || is_front_page() ? (int) get_queried_object_id() : 0;
		foreach ( array_keys( $langs ) as $code ) {
			if ( $code !== $default && ( ! $id || ! potw_page_has_translation( $id, $code ) ) ) {
				unset( $langs[ $code ] );
			}
		}
		if ( count( $langs ) < 2 ) {
			return '';
		}
	}

	$html = '<nav class="potw-lang" aria-label="' . esc_attr__( 'Language', 'potw-fse' ) . '" data-no-translation>';
	foreach ( $langs as $code => $l ) {
		$is   = ( $code === $current );
		$html .= sprintf(
			'<a class="potw-lang__item%1$s" href="%2$s" hreflang="%3$s" lang="%3$s" title="%4$s"%5$s><img src="%6$s" alt="" width="18" height="12"><span>%7$s</span></a>',
			$is ? ' is-current' : '',
			esc_url( $l['current_page_url'] ),
			esc_attr( str_replace( '_', '-', $code ) ),
			esc_attr( $l['language_name'] ),
			$is ? ' aria-current="true"' : '',
			esc_url( $l['flag_link'] ),
			esc_html( strtoupper( $l['short_language_name'] ) )
		);
	}
	return $html . '</nav>';
}
add_shortcode( 'potw_language', 'potw_language_switcher' );

/** TranslatePress default language, e.g. en_US. */
function potw_default_language() {
	$settings = get_option( 'trp_settings', array() );
	return $settings['default-language'] ?? 'en_US';
}

/**
 * Number of strings on a page that have a translation in $lang.
 * TranslatePress links each string to the post it was found on (trp_original_meta, post_parent_id).
 */
function potw_translated_string_count( $post_id, $lang ) {
	global $wpdb;
	static $cache = array();
	$key = $post_id . '|' . $lang;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$dict = $wpdb->prefix . 'trp_dictionary_' . strtolower( potw_default_language() ) . '_' . strtolower( $lang );
	$meta = $wpdb->prefix . 'trp_original_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dict ) ) !== $dict || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $meta ) ) !== $meta ) {
		return $cache[ $key ] = 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT d.id) FROM {$dict} d INNER JOIN {$meta} m ON m.original_id = d.original_id WHERE m.meta_key = 'post_parent_id' AND m.meta_value = %d AND d.status > 0 AND d.translated <> ''", $post_id ) );
	return $cache[ $key ] = $n;
}

/** A page counts as translated once at least one of its own strings has a translation. */
function potw_page_has_translation( $post_id, $lang ) {
	return potw_translated_string_count( $post_id, $lang ) > 0;
}

/** Admin check: admin-ajax.php?action=potw_lang_debug&post=ID */
add_action(
	'wp_ajax_potw_lang_debug',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		global $wpdb;
		$id = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore
		wp_send_json_success(
			array(
				'tables'     => $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'trp_' ) . '%' ) ),
				'default'    => potw_default_language(),
				'meta_keys'  => $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}trp_original_meta LIMIT 20" ), // phpcs:ignore
				'translated' => $id ? potw_translated_string_count( $id, 'fr_FR' ) : null,
			)
		);
	}
);

/** Put the switcher right after the main menu in both headers. */
add_filter(
	'render_block_core/navigation',
	function ( $html, $block ) {
		$ref = (int) ( $block['attrs']['ref'] ?? 0 );
		if ( in_array( $ref, array( 370, 47 ), true ) ) {
			$html .= potw_language_switcher();
		}
		return $html;
	},
	10,
	2
);

/**
 * Admin helpers for seeding translations from outside the TranslatePress editor.
 * potw_trp_strings (post, lang)            → dictionary rows found on that post.
 * potw_trp_save    (lang, items JSON {id: translation}) → saves them as human translations.
 */
add_action(
	'wp_ajax_potw_trp_strings',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		global $wpdb;
		$id   = (int) ( $_REQUEST['post'] ?? 0 ); // phpcs:ignore
		$lang = sanitize_key( $_REQUEST['lang'] ?? 'fr_fr' ); // phpcs:ignore
		$dict = $wpdb->prefix . 'trp_dictionary_' . strtolower( potw_default_language() ) . '_' . $lang;
		$meta = $wpdb->prefix . 'trp_original_meta';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT d.id, d.original, d.translated, d.status FROM {$dict} d INNER JOIN {$meta} m ON m.original_id = d.original_id WHERE m.meta_key = 'post_parent_id' AND m.meta_value = %d ORDER BY d.id", $id ), ARRAY_A );
		wp_send_json_success( $rows );
	}
);

add_action(
	'wp_ajax_potw_trp_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) ), 'wp_rest' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}
		global $wpdb;
		$lang  = sanitize_key( $_REQUEST['lang'] ?? 'fr_fr' ); // phpcs:ignore
		$dict  = $wpdb->prefix . 'trp_dictionary_' . strtolower( potw_default_language() ) . '_' . $lang;
		$items = json_decode( wp_unslash( $_REQUEST['items'] ?? '{}' ), true ); // phpcs:ignore
		$n     = 0;
		foreach ( (array) $items as $row_id => $text ) {
			$n += (int) $wpdb->update(
				$dict,
				array(
					'translated' => wp_kses_post( $text ),
					'status'     => 2,
				),
				array( 'id' => (int) $row_id )
			);
		}
		wp_send_json_success( array( 'updated' => $n ) );
	}
);
