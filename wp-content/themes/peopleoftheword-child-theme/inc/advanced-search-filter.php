<?php namespace Harvard;

// Categories
add_action( 'wp_ajax_nopriv_advanced_search_fetch_categories', __NAMESPACE__ . '\fetch_categories_handler' );
add_action( 'wp_ajax_advanced_search_fetch_categories', __NAMESPACE__ . '\fetch_categories_handler' );

// Tags
add_action( 'wp_ajax_nopriv_advanced_search_fetch_tags', __NAMESPACE__ . '\fetch_tags_handler' );
add_action( 'wp_ajax_advanced_search_fetch_tags', __NAMESPACE__ . '\fetch_tags_handler' );

function fetch_categories_handler() 
{
	if ( !wp_doing_ajax() ) wp_die();
	if ( !wp_verify_nonce( $_REQUEST['nonce'], 'ajax-search-nonce' ) ) wp_die();

	$content_type = $_REQUEST['content_type'];
	$categories = [];
	$output = [];

	switch($content_type) {
		case 'post':
			$categories = get_categories( [ 'taxonomy' => 'category' ] );
			break;
		case 'publications':
			$categories = get_categories( [ 'taxonomy' => 'publication_category' ] );
			break;
		case 'areas_of_work':
			$categories = get_terms( array(
				'taxonomy' => 'areas_of_work',
				'hide_empty' => false,
			) );
			break;
		case 'faculty':
			$categories = get_categories( [ 'taxonomy' => 'department' ] );
			break;
		case 'events': // no taxonomy
		default: // all
			break;
	}

	if ( $categories ) : foreach($categories as $category) :
		$category_name = $category->name;
		// change html entities to unicode
		$category_name = html_entity_decode($category_name, ENT_QUOTES, 'UTF-8');

		$output[] = [
			'id' => $category->term_id,
			'text' => $category_name,
		];
	endforeach; endif;

	echo json_encode( array(
		'data' 	  => $output,
		'success' => count( $output ) > 0 ? true : false,
	) );
	wp_die();
}

function fetch_tags_handler()
{
	if ( !wp_doing_ajax() ) wp_die();
	if ( !wp_verify_nonce( $_REQUEST['nonce'], 'ajax-search-nonce' ) ) wp_die();

	$content_type = $_REQUEST['content_type'];
	$tags = [];
	$output = [];

	switch($content_type) {
		case 'post':
			$tags = get_tags( [ 'taxonomy' => 'post_tag' ] );
			break;
		// no taxonomy
		case 'publications':
		case 'areas_of_work':
		case 'faculty':
		case 'events':
		default: // all
			break;
	}

	if ( $tags ) : foreach($tags as $tag) :
		$tag_name = $tag->name;
		// change html entities to unicode
		$tag_name = html_entity_decode($tag_name, ENT_QUOTES, 'UTF-8');

		$output[] = [
			'id' => $tag->term_id,
			'text' => $tag_name,
		];
	endforeach; endif;

	echo json_encode( array(
		'data' 	  => $output,
		'success' => count( $output ) > 0 ? true : false,
	) );
	wp_die();
}