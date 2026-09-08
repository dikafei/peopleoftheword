<?php
/**
 * Get post types that have a specific taxonomy (a combination of get_post_types and get_object_taxonomies)
 * 
 * @author Joshua David Nelson, josh@joshuadnelson.com
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2.0
 * 
 * @see register_post_types(), get_post_types(), get_object_taxonomies()
 * 
 * @param string		$taxonomy	Required. The name of the taxonomy the post type(s) supports.
 * @param array | string	$args		Optional. An array of key => value arguments to match 
 *						against the post type objects. Default empty array.
 * @param string		$output		Optional. The type of output to return in the array.
 * 						Accepts post type 'names' or 'objects'. Default 'names'.
 * 
 * @return array | boolean	A list of post type names or objects that have the taxonomy 
 *				or false if nothing found.
 */
if( !function_exists( 'get_post_types_by_taxonomy' ) ) {
	function get_post_types_by_taxonomy( $taxonomy, $args = array(), $output = 'names' ) {
		// Get all post types
		$post_types = get_post_types( $args, $output );
		
		// We just need the taxonomy name
		if( is_object( $taxonomy ) ){
			$taxonomy = $taxonomy->name;
			
		// If it's not an object or a string, it won't work, so send it back
		} elseif( !is_string( $taxonomy ) ) {
			return false;
		}
		
		// setup the finished product
		$post_types_with_tax = array();
		foreach( $post_types as $post_type ) {
			// If post types are objects
			if( is_object( $post_type ) ) {
				$taxonomies = get_object_taxonomies( $post_type->name, 'names' );
				if( in_array( $taxonomy, $taxonomies ) ) {
					$post_types_with_tax[] = $post_type;
				}
				
			// If post types are strings
			} elseif( is_string( $post_type ) ) {
				$taxonomies = get_object_taxonomies( $post_type, 'names' );
				if( in_array( $taxonomy, $taxonomies ) ) {
					$post_types_with_tax[] = $post_type;
				}
			}
		}
		
		// If there aren't any results, return false
		if( empty( $post_types_with_tax ) ) {
			return false;
		} else {
			return $post_types_with_tax;
		}
	}
}