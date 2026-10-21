<?php
/**
 * Reads posts from the old WordPress.com site through the public REST API.
 *
 * @package potw-lesson-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class POTW_LI_Source {

	/** @var string e.g. peopleofthewordonline.wordpress.com */
	private $site;

	public function __construct( $site ) {
		$this->site = trim( preg_replace( '#^https?://|/.*$#', '', (string) $site ) );
	}

	private function base() {
		return 'https://public-api.wordpress.com/wp/v2/sites/' . rawurlencode( $this->site ) . '/';
	}

	/**
	 * GET a REST path. Returns [ body(array), headers ] or WP_Error.
	 */
	private function get( $path, array $args = array() ) {
		$url = add_query_arg( $args, $this->base() . ltrim( $path, '/' ) );

		$tries = 0;
		do {
			$res = wp_remote_get(
				$url,
				array(
					'timeout' => 45,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);
			$tries++;
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( 200 === $code ) {
				break;
			}
			if ( $tries < 3 ) {
				sleep( 2 * $tries );
			}
		} while ( $tries < 3 );

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== $code ) {
			return new WP_Error( 'potw_li_http', sprintf( 'Source API returned HTTP %d for %s', $code, $path ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'potw_li_json', 'Source API returned invalid JSON for ' . $path );
		}
		return array( $body, wp_remote_retrieve_headers( $res ) );
	}

	/**
	 * Light index of every published source post: id => [modified_gmt, date, title].
	 *
	 * @return array|WP_Error
	 */
	public function index() {
		$out  = array();
		$page = 1;
		do {
			$r = $this->get(
				'posts',
				array(
					'per_page' => 100,
					'page'     => $page,
					'orderby'  => 'date',
					'order'    => 'asc',
					'_fields'  => 'id,date,modified_gmt,title',
				)
			);
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			list( $rows, $headers ) = $r;
			foreach ( $rows as $row ) {
				$out[ (int) $row['id'] ] = array(
					'modified_gmt' => $row['modified_gmt'],
					'date'         => $row['date'],
					'title'        => POTW_LI_Parser::clean_text( $row['title']['rendered'] ?? '' ),
				);
			}
			$pages = (int) ( $headers['x-wp-totalpages'] ?? 1 );
			$page++;
		} while ( $page <= $pages && $rows );

		return $out;
	}

	/**
	 * One full source post.
	 *
	 * @return array|WP_Error
	 */
	public function post( $id ) {
		$r = $this->get(
			'posts/' . (int) $id,
			array( '_fields' => 'id,date,date_gmt,modified_gmt,slug,status,link,title,content,categories,tags' )
		);
		return is_wp_error( $r ) ? $r : $r[0];
	}

	/**
	 * Source category id => name (cached for an hour).
	 */
	public function categories() {
		$key   = 'potw_li_src_cats_' . md5( $this->site );
		$cache = get_transient( $key );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$out  = array();
		$page = 1;
		do {
			$r = $this->get(
				'categories',
				array(
					'per_page' => 100,
					'page'     => $page,
					'_fields'  => 'id,name',
				)
			);
			if ( is_wp_error( $r ) ) {
				return $out;
			}
			list( $rows, $headers ) = $r;
			foreach ( $rows as $row ) {
				$out[ (int) $row['id'] ] = POTW_LI_Parser::clean_text( $row['name'] );
			}
			$pages = (int) ( $headers['x-wp-totalpages'] ?? 1 );
			$page++;
		} while ( $page <= $pages && $rows );

		set_transient( $key, $out, HOUR_IN_SECONDS );
		return $out;
	}
}
