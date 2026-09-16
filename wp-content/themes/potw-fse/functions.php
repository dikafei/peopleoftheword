<?php
/**
 * People of the Word — theme functions.
 *
 * A native WordPress full-site-editing (block) theme. All layout/design
 * lives in theme.json + templates/parts/patterns; this file only wires up
 * theme supports, assets, and a couple of small helpers used by patterns.
 *
 * @package potw-fse
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POTW_FSE_VERSION', '1.0.0' );

/**
 * Theme setup.
 */
function potw_fse_setup() {
	// Block themes handle their own <title>, thumbnails, etc. via theme.json,
	// but these supports are still required for WordPress to switch features on.
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	add_editor_style( 'assets/editor-style.css' );

	register_block_pattern_category(
		'potw',
		array( 'label' => __( 'People of the Word', 'potw-fse' ) )
	);
}
add_action( 'after_setup_theme', 'potw_fse_setup' );

/**
 * Front-end + editor assets.
 *
 * Fonts (DM Serif Display, Epunda Slab, Fauna One) are loaded from Google
 * Fonts here for simplicity. For production/GDPR compliance, self-host
 * these via theme.json fontFace + local .woff2 files instead — see
 * https://developer.wordpress.org/themes/functionality/font-face/
 */
function potw_fse_assets() {
	wp_enqueue_style(
		'potw-fse-google-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Epunda+Slab:wght@400;500;600;700&family=Fauna+One&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'potw-fse-style',
		get_stylesheet_uri(),
		array( 'potw-fse-google-fonts' ),
		POTW_FSE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'potw_fse_assets' );

/**
 * Load the same Google Fonts inside the block editor so patterns preview
 * with the correct typography.
 */
function potw_fse_editor_assets() {
	wp_enqueue_style(
		'potw-fse-editor-google-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Epunda+Slab:wght@400;500;600;700&family=Fauna+One&display=swap',
		array(),
		null
	);
}
add_action( 'enqueue_block_editor_assets', 'potw_fse_editor_assets' );

/**
 * Register navigation menu fallback locations (used only if a site admin
 * assigns a classic menu; the Navigation block otherwise manages its own
 * wp_navigation content).
 */
function potw_fse_menus() {
	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'potw-fse' ),
			'footer-explore'   => __( 'Footer — Explore', 'potw-fse' ),
			'footer-resources' => __( 'Footer — Resources', 'potw-fse' ),
		)
	);
}
add_action( 'after_setup_theme', 'potw_fse_menus' );


/**
 * Active the attribute panel in block editor
 */
add_filter( 'acf/settings/enable_datastore', '__return_true' );

/**
 * Add new styles in block editor
 */
add_action( 'init', function() {
	// Cross divider
    wp_register_style(
        'cross-divider-style',
        get_stylesheet_directory_uri() . '/css/cross-divider.css',
        array(),
        '1.0'
    );

    register_block_style(
        'core/separator',
        array(
            'name'         => 'cross-divider',
            'label'        => __( 'Cross Divider', 'textdomain' ),
            'style_handle' => 'cross-divider-style',
        )
    );

	// Vertical divider
    wp_register_style(
        'vertical-separator-style',
        get_stylesheet_directory_uri() . '/css/vertical-separator.css',
        array(),
        '1.0'
    );

    register_block_style(
        'core/separator',
        array(
            'name'         => 'vertical',
            'label'        => __( 'Vertical', 'textdomain' ),
            'style_handle' => 'vertical-separator-style',
        )
    );

    // Green button
    wp_register_style(
        'olive-button-style',
        get_stylesheet_directory_uri() . '/css/olive-button.css',
        array(),
        '1.0'
    );

    register_block_style(
        'core/button',
        array(
            'name'         => 'olive-button',
            'label'        => __( 'Olive', 'textdomain' ),
            'style_handle' => 'olive-button-style',
        )
    );

	// Narrow group
    wp_register_style(
        'narrow-group-style',
        get_stylesheet_directory_uri() . '/css/narrow-group.css',
        array(),
        '1.0'
    );

    register_block_style(
        'core/group',
        array(
            'name'         => 'narrow',
            'label'        => __( 'Narrow', 'textdomain' ),
            'style_handle' => 'narrow-group-style',
        )
    );
} );