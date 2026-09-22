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

	// ==============================
	// Paragraph style
    // ==============================

	// Narrow paragraph
	wp_register_style(
		'narrow-paragraph-style',
		get_stylesheet_directory_uri() . '/css/narrow-paragraph.css',
		array(),
		'1.0'
	);

	register_block_style(
		'core/paragraph',
		array(
			'name'         => 'narrow',
			'label'        => __( 'Narrow', 'textdomain' ),
			'style_handle' => 'narrow-paragraph-style',
		)
	);

    // ==============================
	// Separator style
    // ==============================

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

    // ==============================
    // Button style
    // ==============================

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

    // ==============================
	// Group style
    // ==============================

    // Rounded group
    wp_register_style(
	    'rounded-group-style',
	    get_stylesheet_directory_uri() . '/css/rounded-group.css',
	    array(),
	    '1.0'
    );

    register_block_style(
	'core/group',
	    array(
		    'name'         => 'rounded',
		    'label'        => __( 'Rounded', 'textdomain' ),
		    'style_handle' => 'rounded-group-style',
	    )
    );

    // Rounded Cover
    wp_register_style(
	    'rounded-cover-style',
	    get_stylesheet_directory_uri() . '/css/rounded-cover.css',
	    array(),
	    '1.0'
    );

    register_block_style(
	'core/cover',
	    array(
		    'name'         => 'rounded',
		    'label'        => __( 'Rounded', 'textdomain' ),
		    'style_handle' => 'rounded-cover-style',
	    )
    );

    // ==============================
    // Column style
    // ==============================

    // Rounded column
	wp_register_style(
		'rounded-columns-style',
		get_stylesheet_directory_uri() . '/css/rounded-columns.css',
		array(),
		'1.0'
	);

	register_block_style(
		'core/columns',
		array(
			'name'         => 'rounded',
			'label'        => __( 'Rounded', 'textdomain' ),
			'style_handle' => 'rounded-columns-style',
		)
	);
} );

/**
 * Toggle for narrow width in group block
 */
function potw_group_editor_controls() {

	wp_enqueue_script(
		'potw-group-narrow-toggle',
		get_stylesheet_directory_uri() . '/assets/js/group-narrow-toggle.js',
		array(
			'wp-block-editor',
			'wp-components',
			'wp-compose',
			'wp-element',
			'wp-hooks',
		),
		filemtime( get_stylesheet_directory() . '/assets/js/group-narrow-toggle.js' ),
		true
	);

}
add_action( 'enqueue_block_editor_assets', 'potw_group_editor_controls' );


/**
 * Get a list of H2 & H3 headings from a lesson, complete with
 * nested numbering (1, 2, 3... and 4.1, 4.2, 4.3 for H3s below the 4th H2).
 * Results are cached per request to avoid repeated parsing.
 */
function lesson_toc_get_headings( $post_id ) {
    static $cache = array();

    if ( isset( $cache[ $post_id ] ) ) {
        return $cache[ $post_id ];
    }

    $content = get_post_field( 'post_content', $post_id );

    if ( ! preg_match_all( '/<h([2-3])[^>]*>(.*?)<\/h[2-3]>/is', $content, $matches, PREG_SET_ORDER ) ) {
        return $cache[ $post_id ] = array();
    }

    $headings   = array();
    $index      = 0;
    $h2_counter = 0;
    $h3_counter = 0;

    foreach ( $matches as $match ) {
        $level = (int) $match[1];
        $title = wp_strip_all_tags( $match[2] );
        $slug  = sanitize_title( $title ) . '-' . $index++;

        if ( 2 === $level ) {
            $h2_counter++;
            $h3_counter = 0;
            $number = (string) $h2_counter;
        } else {
            $h3_counter++;
            $number = $h2_counter . '.' . $h3_counter;
        }

        $headings[] = array(
            'level'  => $level,
            'title'  => $title,
            'slug'   => $slug,
            'number' => $number,
            'raw'    => $match[0],
        );
    }

    return $cache[ $post_id ] = $headings;
}

/**
 * Insert an ID for each H2/H3 heading in the lesson content,
 * so links from the TOC can jump to the correct section.
 */
add_filter( 'the_content', 'lesson_toc_inject_heading_ids' );

function lesson_toc_inject_heading_ids( $content ) {
    if ( ! is_singular( 'lesson' ) || ! is_main_query() || ! in_the_loop() ) {
        return $content;
    }

    foreach ( lesson_toc_get_headings( get_the_ID() ) as $heading ) {
        $with_id = preg_replace( '/<h(\d)([^>]*)>/i', '<h$1$2 id="' . esc_attr( $heading['slug'] ) . '">', $heading['raw'], 1 );
        $content = preg_replace( '/' . preg_quote( $heading['raw'], '/' ) . '/', $with_id, $content, 1 );
    }

    return $content;
}

/**
 * Shortcode: [lesson_toc]
 * Render a complete table of contents with nested numbers.
 */
add_shortcode( 'lesson_toc', 'lesson_toc_render_shortcode' );

function lesson_toc_render_shortcode( $atts ) {
    $atts    = shortcode_atts( array( 'id' => get_the_ID() ), $atts );
    $post_id = (int) $atts['id'];

    if ( ! $post_id || 'lesson' !== get_post_type( $post_id ) ) {
        return '';
    }

    $headings = lesson_toc_get_headings( $post_id );
    if ( empty( $headings ) ) {
        return '';
    }

    $html = '<nav class="lesson-toc" aria-label="Table of Contents">';
    $html .= '<p class="lesson-toc__title">Table of Contents</p><ul class="lesson-toc__list">';

    $open_sub = false;

    foreach ( $headings as $i => $h ) {
        if ( 2 === $h['level'] ) {
            $html .= $open_sub ? '</ul></li>' : ( $i > 0 ? '</li>' : '' );
            $open_sub = false;

            $has_children = isset( $headings[ $i + 1 ] ) && 3 === $headings[ $i + 1 ]['level'];

            $html .= '<li class="toc-item toc-item--h2">';
            $html .= '<span class="toc-number">' . esc_html( $h['number'] ) . '</span>';
            $html .= '<a href="#' . esc_attr( $h['slug'] ) . '">' . esc_html( $h['title'] ) . '</a>';

            if ( $has_children ) {
                $html .= '<ul class="lesson-toc__sublist">';
                $open_sub = true;
            }
        } else {
            $html .= '<li class="toc-item toc-item--h3">';
            $html .= '<span class="toc-number">' . esc_html( $h['number'] ) . '</span>';
            $html .= '<a href="#' . esc_attr( $h['slug'] ) . '">' . esc_html( $h['title'] ) . '</a></li>';
        }
    }

    $html .= $open_sub ? '</ul></li>' : '</li>';
    $html .= '</ul></nav>';

    return $html;
}


/**
 * Register lecture repeater in single lesson
 */
function lesson_repeater_shortcode() {
    if ( ! have_rows('lesson_file') ) {
        return '';
    }

    ob_start();
    ?>
    <div class="lesson-repeater-wrap">
        <h2 class="lesson-repeater-title">Lecture</h2>
        <div class="lesson-repeater-box">
            <?php
            $i = 0;
            while ( have_rows('lesson_file') ) : the_row();
                $i++;
                $lecturer_name = get_sub_field('lecturer_name');
                $lesson_year   = get_sub_field('lesson_year');
                $lesson_pdf    = get_sub_field('lesson_pdf');
                $lesson_word   = get_sub_field('lesson_word');
            ?>
            <?php if ( $i > 1 ) : ?>
                <hr class="lesson-repeater-divider">
            <?php endif; ?>

            <div class="lesson-repeater-item">
                <div class="lesson-repeater-header">
                    <?php if ( $lesson_year ) : ?>
                        <span class="lesson-repeater-year"><?php echo esc_html( $lesson_year ); ?></span>
                    <?php endif; ?>
                        <span class="lesson-repeater-name"><?php echo esc_html( $lecturer_name ); ?></span>
                </div>

                <div class="lesson-repeater-buttons">
                    <?php if ( $lesson_pdf ) : ?>
                        <a href="<?php echo esc_url( $lesson_pdf ); ?>" class="lesson-repeater-btn" target="_blank">PDF</a>
                    <?php endif; ?>

                    <?php if ( $lesson_word ) : ?>
                        <a href="<?php echo esc_url( $lesson_word ); ?>" class="lesson-repeater-btn" target="_blank">Word</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('lesson_repeater', 'lesson_repeater_shortcode');

function homework_repeater_shortcode() {
    if ( ! have_rows('homework_file') ) {
        return '';
    }

    ob_start();
    ?>
    <div class="lesson-repeater-wrap">
        <h2 class="lesson-repeater-title">Homework</h2>
        <div class="lesson-repeater-box">
            <?php
            $i = 0;
            while ( have_rows('homework_file') ) : the_row();
                $i++;
                $homework_lecturer_name = get_sub_field('homework_lecturer_name');
                $homework_year          = get_sub_field('homework_year');
                $homework_pdf           = get_sub_field('homework_pdf');
                $homework_word          = get_sub_field('homework_word');
            ?>
            <?php if ( $i > 1 ) : ?>
                <hr class="lesson-repeater-divider">
            <?php endif; ?>

            <div class="lesson-repeater-item">
                <div class="lesson-repeater-header">
                    <?php if ( $homework_year ) : ?>
                        <span class="lesson-repeater-year"><?php echo esc_html( $homework_year ); ?></span>
                    <?php endif; ?>
                        <span class="lesson-repeater-name"><?php echo esc_html( $homework_lecturer_name ); ?></span>
                </div>

                <div class="lesson-repeater-buttons">
                    <?php if ( $homework_pdf ) : ?>
                        <a href="<?php echo esc_url( $homework_pdf ); ?>" class="lesson-repeater-btn" target="_blank">PDF</a>
                    <?php endif; ?>

                    <?php if ( $homework_word ) : ?>
                        <a href="<?php echo esc_url( $homework_word ); ?>" class="lesson-repeater-btn" target="_blank">Word</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('homework_repeater', 'homework_repeater_shortcode');


/**
 * Add lesson podcast and video to single lessons
 */
function lesson_media_shortcode() {
    $video   = get_field('lesson_video');
    $podcast = get_field('lesson_podcast');

    if ( ! $video && ! $podcast ) {
        return '';
    }

    $default_tab = $video ? 'video' : 'podcast';

    $download_url  = ( $default_tab === 'video' ) ? $video['url'] : $podcast['url'];
    $download_name = ( $default_tab === 'video' ) ? $video['filename'] : $podcast['filename'];

    ob_start();
    ?>
    <div class="lesson-media-player"
         data-active="<?php echo esc_attr( $default_tab ); ?>"
         <?php if ( $video ) : ?>data-video-url="<?php echo esc_url( $video['url'] ); ?>" data-video-name="<?php echo esc_attr( $video['filename'] ); ?>"<?php endif; ?>
         <?php if ( $podcast ) : ?>data-podcast-url="<?php echo esc_url( $podcast['url'] ); ?>" data-podcast-name="<?php echo esc_attr( $podcast['filename'] ); ?>"<?php endif; ?>>

        <?php if ( $video ) : ?>
        <div class="lesson-media-panel lesson-media-video-panel" data-panel="video" <?php echo ( $default_tab !== 'video' ) ? 'style="display:none;"' : ''; ?>>
            <video class="lesson-media-video-el" src="<?php echo esc_url( $video['url'] ); ?>" controls playsinline></video>
        </div>
        <?php endif; ?>

        <?php if ( $podcast ) : ?>
        <div class="lesson-media-panel lesson-media-audio-panel" data-panel="podcast" <?php echo ( $default_tab !== 'podcast' ) ? 'style="display:none;"' : ''; ?>>
            <p class="lesson-media-title"><?php echo esc_html( get_the_title() ); ?></p>
            <audio class="lesson-media-audio-el" src="<?php echo esc_url( $podcast['url'] ); ?>" preload="metadata"></audio>
            <div class="lesson-media-audio-controls">
                <button type="button" class="lesson-media-play-btn" aria-label="Play/Pause">
                    <span class="icon-play">&#9658;</span>
                    <span class="icon-pause" style="display:none;">&#10074;&#10074;</span>
                </button>
                <span class="lesson-media-time lesson-media-current">00:00</span>
                <input type="range" class="lesson-media-seek" min="0" max="100" value="0" step="0.1">
                <span class="lesson-media-time lesson-media-remaining">-00:00</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="lesson-media-toolbar">
            <div class="lesson-media-tabs">
                <?php if ( $video ) : ?>
                    <button type="button" class="lesson-media-tab-btn <?php echo ( $default_tab === 'video' ) ? 'is-active' : ''; ?>" data-tab="video" aria-label="Video">&#127909;</button>
                <?php endif; ?>
                <?php if ( $podcast ) : ?>
                    <button type="button" class="lesson-media-tab-btn <?php echo ( $default_tab === 'podcast' ) ? 'is-active' : ''; ?>" data-tab="podcast" aria-label="Podcast">&#127908;</button>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $download_url ); ?>" class="lesson-media-download-btn" download="<?php echo esc_attr( $download_name ); ?>">&#8681; Download</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('lesson_media', 'lesson_media_shortcode');

function lesson_media_enqueue_assets() {
    if ( is_singular() ) {
        wp_enqueue_script(
            'lesson-media-js',
            get_stylesheet_directory_uri() . '/assets/js/lesson-media.js',
            array(),
            '1.0',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'lesson_media_enqueue_assets' );