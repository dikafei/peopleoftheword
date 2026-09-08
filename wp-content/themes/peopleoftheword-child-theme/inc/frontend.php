<?php
	/*	 	  
	 * WORDPRESS: Functions
	 * Extend Wordpress built in functions and GeneratePress's functions.
	 */
	 
	/* 
	 * Table of contents:
	 * 1. Constants
	 * 2. Register Style/Script	
	 * 3. Prevent Render Blocking 
	 * 4. Quick Edit Link
	 * 5. GeneratePress - Default Settings 
	 * 6. GeneratePress - Change Burger Icon
	 * 7. Theme Setup	  
	 *    a. Dequeue Leaks
	 *	  b. Theme Support
	 *    c. Image Size
	 *    d. Sidebar
	 *	  e. Excerpt
	 * 8. Theme Customizer
	 * 9. Gutenberg - Register Block Style
	 * 10. WPCF7
	 * 11. ACF Blocks
	 * 12. Custom Post Taxonomy	 
	 * 13. Woocommerce
	 */

	// Constants
		define( 'THEMEDIR', get_template_directory() . '/' );
		define( 'THEMEURI', get_stylesheet_directory_uri() . '/' );
		
	// Register Style/Script
		// Frontend
			add_action( 'wp_enqueue_scripts', 'enqueue_list', 20 );
		
			function enqueue_list() {
				// Style											
					wp_enqueue_style( 'swiper-css',  THEMEURI . ( $css_path = 'assets/vendor/swiper/swiper-bundle.min.css' ) );					
					wp_enqueue_style( 'font-dmsans', 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap' );									
					wp_enqueue_style( 'font-fraunces', 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&display=swap' );	
					wp_enqueue_style( 'font-baskervville', 'https://fonts.googleapis.com/css2?family=Baskervville:ital,wght@0,400..700;1,400..700&display=swap' );									
					wp_enqueue_style( 'default-css',  THEMEURI . ( $css_path = '/assets/scss/style.min.css' ), array( 'generate-style' ), get_version( $css_path ) );			
				
				// Script			
					wp_enqueue_script( 'jquery' );					
					wp_enqueue_script( 'swiper-js', THEMEURI . 'assets/vendor/swiper/swiper-bundle.min.js',  array( 'jquery' ), null, true );				
					wp_enqueue_script( 'default-js', THEMEURI . ( $js_path = '/assets/js/script.js' ), array( 'jquery' ), get_version( $js_path ), true );

					wp_add_inline_script('default-js', 'const HC =' . json_encode(array(
						'ajaxurl' => admin_url('admin-ajax.php'),
						'nonce' => wp_create_nonce('ajax-search-nonce'),
					)), 'before');	
			}		
	
		// Backend
			add_action('admin_enqueue_scripts', 'admin_enqueue_list', 22);
			
			function admin_enqueue_list( $hook_suffix ) 
			{		
				wp_enqueue_style( 'font-dmsans', 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap' );					
				wp_enqueue_style( 'font-fraunces', 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&display=swap' );
				wp_enqueue_style( 'font-baskervville', 'https://fonts.googleapis.com/css2?family=Baskervville:ital,wght@0,400..700;1,400..700&display=swap' );	
				wp_enqueue_style( 'admin-css', THEMEURI . ( $css_path_admin = '/assets/scss/style-admin.min.css' ), array(), get_version( $css_path_admin ) );

				if( 'post.php' == $hook_suffix || 'post-new.php' == $hook_suffix || 'widgets.php' == $hook_suffix ) 
				{						
					wp_enqueue_style( 'gutenberg-css', THEMEURI . ( $css_path_gutenberg = '/assets/scss/style-gutenberg.min.css' ), array(), get_version( $css_path_gutenberg ) );
				}
			}

			add_action( 'enqueue_block_editor_assets', 'block_editor_scripts' );

			function block_editor_scripts() {
				wp_enqueue_script( 'block-editor', THEMEURI . 'assets/js/editor.js', array( 'wp-blocks', 'wp-dom' ) );
			}			

		// Replaces cache busting versioning
			function get_version($relative_file_path) {
				return date("ymd-Gis", filemtime( get_stylesheet_directory() . $relative_file_path ));
			}
			
	// Prevent Render Blocking
		add_filter( 'style_loader_tag', 'add_google_font_stylesheet_attributes', 10, 2 );
		
		function add_google_font_stylesheet_attributes( $html, $handle ) {
			if ( 'font-dmsans' === $handle || 'font-fraunces' === $handle || 'font-baskervville' === $handle ) 
			{
				return str_replace( "rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $html );
			}
			
			return $html;
		}

	// Quick Edit Link
		add_action('wp_footer', 'add_quick_edit_link');

		function add_quick_edit_link() {
			if ( ! current_user_can('administrator') || ( ! is_single() && ! is_front_page() ) ) {
				return;
			}

			global $post;
			$edit_link = html_entity_decode(get_edit_post_link($post->ID));

			?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					var editLink = $('<a></a>');
					editLink.attr('href', '<?php echo $edit_link; ?>');
					editLink.text('Quick Edit');
					editLink.css({
						'float': 'right',
						'backgroundColor': '#fff',
						'border': '1px solid #ccc'
					});

					$('#wpadminbar .quicklinks').prepend(editLink);
				});
			</script>
			<?php
		}		
		
	// GeneratePress - Default Settings
		if ( ! function_exists( 'generate_get_defaults' ) ) {
			/**
			 * Set default options
			 *
			 * @since 0.1
			 */
			 
			function generate_get_defaults() {
				
				return apply_filters(
					'generate_option_defaults',
					array(
						'hide_title' => true,
						'hide_tagline' => true,
						'logo' => '',
						'inline_logo_site_branding' => false,
						'retina_logo' => '',
						'logo_width' => '',
						'top_bar_width' => 'full',
						'top_bar_inner_width' => 'contained',
						'top_bar_alignment' => 'right',
						'container_width' => '1140',
						'container_alignment' => 'text',
						'header_layout_setting' => 'fluid-header',
						'header_inner_width' => 'contained',
						'nav_alignment_setting' => is_rtl() ? 'right' : 'left',
						'header_alignment_setting' => is_rtl() ? 'right' : 'left',
						'nav_layout_setting' => 'fluid-nav',
						'nav_inner_width' => 'contained',
						'nav_position_setting' => 'nav-float-right',
						'nav_drop_point' => '',
						'nav_dropdown_type' => 'hover',
						'nav_dropdown_direction' => is_rtl() ? 'left' : 'right',
						'nav_search' => 'disable',
						'nav_search_modal' => true,
						'content_layout_setting' => 'separate-containers',
						'layout_setting' => 'right-sidebar',
						'blog_layout_setting' => 'right-sidebar',
						'single_layout_setting' => 'right-sidebar',
						'post_content' => 'excerpt',
						'footer_layout_setting' => 'fluid-footer',
						'footer_inner_width' => 'contained',
						'footer_widget_setting' => '3',
						'footer_bar_alignment' => 'right',
						'back_to_top' => '',
						'background_color' => 'var(--base-2)',
						'text_color' => 'var(--contrast)',
						'link_color' => 'var(--accent)',
						'link_color_hover' => 'var(--contrast)',
						'link_color_visited' => '',
						'font_awesome_essentials' => true,
						'icons' => 'svg',
						'combine_css' => true,
						'dynamic_css_cache' => true,
						'structure' => 'flexbox',
						'underline_links' => 'always',
						'font_manager' => array(),
						'typography' => array(),
						'google_font_display' => 'auto',
						'use_dynamic_typography' => true,
						'global_colors' => array(
							array(
								'name' => __( 'Primary', 'generatepress' ),
								'slug' => 'primary',
								'color' => '#d49f53',
							),
							array(
								'name' => __( 'Secondary', 'generatepress' ),
								'slug' => 'secondary',
								'color' => '#7e8d68',
							),
							array(
								'name' => __( 'Accent 1', 'generatepress' ),
								'slug' => 'accent-1',
								'color' => '#403429',
							),
							array(
								'name' => __( 'Accent 2', 'generatepress' ),
								'slug' => 'accent-2',
								'color' => '#544435',
							),
							array(
								'name' => __( 'Accent 3', 'generatepress' ),
								'slug' => 'accent-3',
								'color' => '#cdb59b',
							),
							array(
								'name' => __( 'Accent 4', 'generatepress' ),
								'slug' => 'accent-4',
								'color' => '#e4d3bf',
							),
							array(
								'name' => __( 'Accent 5', 'generatepress' ),
								'slug' => 'accent-5',
								'color' => '#f9f1e8',
							),
							array(
								'name' => __( 'Accent 6', 'generatepress' ),
								'slug' => 'accent-6',
								'color' => '#9d3c21',
							),							
									
							array(
								'name' => __( 'Border', 'generatepress' ),
								'slug' => 'border',
								'color' => '#eeeeee',
							),	
							array(
								'name' => __( 'Background', 'generatepress' ),
								'slug' => 'background',
								'color' => '#fdf9f5',
							),
							array(
								'name' => __( 'White', 'generatepress' ),
								'slug' => 'white',
								'color' => '#ffffff',
							),			
							array(
								'name' => __( 'Foreground', 'generatepress' ),
								'slug' => 'foreground',
								'color' => '#333333',
							),
							array(
								'name' => __( 'Heading', 'generatepress' ),
								'slug' => 'heading',
								'color' => '#222222',
							),
							array(
								'name' => __( 'Black', 'generatepress' ),
								'slug' => 'black',
								'color' => '#000000',
							)																						
						)
					)
				);
			}
		}

	// Generate Press - Change Burger Icon
		add_filter( 'generate_svg_icon', function( $output, $icon ) {
			if ( 'menu-bars' === $icon ) {
				$output = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>';
			}

			return $output;
		}, 10, 2 );

		/*add_filter( 'generate_svg_icon', function( $output, $icon ) {
			if ( 'menu-bars' === $icon ) {
				$output =   '<svg width="55" height="30" viewBox="0 0 55 30" fill="none" xmlns="http://www.w3.org/2000/svg">
								<rect width="55" height="30" fill="white"/>
								<line x1="15" y1="9.5" x2="40" y2="9.5" stroke="#55ACEF"/>
								<line x1="15" y1="19.5" x2="40" y2="19.5" stroke="#55ACEF"/>
							</svg>';
			}

			return $output;
		}, 10, 2 );*/

	// Generate Press - Fix Widget page error
		add_action( 'admin_footer', function() {
			$screen = get_current_screen();
			
			if ( 'widgets' === $screen->base ) {
				?>
					<script>
						wp.domReady( function() {
							var unregisterPlugin = wp.plugins.unregisterPlugin;

							unregisterPlugin( 'generatepress-content-width' );
						} );
					</script>
				<?php
			}
		} );

	// Theme Setup
		add_action( 'after_setup_theme', 'initial_setup', 12 );
	
		function initial_setup()
		{	
			// Dequeue Leaks
				if ( !is_admin() ) {
					add_action( 'wp_enqueue_scripts', 'dequeue_leaks');
					
					function dequeue_leaks() {
						wp_deregister_style('common');
					}				
				}

			// Remove GP inline styling - good tapi bermasalah di mobile menu
				//remove_action( 'wp_enqueue_scripts', 'generate_enqueue_dynamic_css', 50 );

			// Remove Comments
				remove_action( 'generate_after_do_template_part', 'generate_do_comments_template', 15);
		
			// Theme Support
				if ( function_exists( 'add_theme_support' ) ) {		
					add_theme_support( 'post-thumbnails', array( 'post' ) );
					add_theme_support( 'editor-styles' );
					add_theme_support( 'align-wide' );	
					
					// Non square custom logo
					add_theme_support( 'custom-logo', array(
						 'flex-height' => true,
						 'flex-width' => true
					) );						
			
					// Editor - Font Size
					add_theme_support(
						'editor-font-sizes',
						array(
							array(
								'name'      => __( 'Span', 'generatepress' ),
								'shortName' => __( 'span', 'generatepress' ),
								'size'      => 14,
								'slug'      => 'span',
							),
							array(
								'name'      => __( 'H6', 'generatepress' ),
								'shortName' => __( 'H6', 'generatepress' ),
								'size'      => 16,
								'slug'      => 'H6',
							),
							array(
								'name'      => __( 'H5', 'generatepress' ),
								'shortName' => __( 'H5', 'generatepress' ),
								'size'      => 18,
								'slug'      => 'H5',
							),
							array(
								'name'      => __( 'H4', 'generatepress' ),
								'shortName' => __( 'H4', 'generatepress' ),
								'size'      => 24,
								'slug'      => 'H4',
							),
							array(
								'name'      => __( 'H3', 'generatepress' ),
								'shortName' => __( 'H3', 'generatepress' ),
								'size'      => 30,
								'slug'      => 'H3',
							),
							array(
								'name'      => __( 'H2', 'generatepress' ),
								'shortName' => __( 'H2', 'generatepress' ),
								'size'      => 36,
								'slug'      => 'H2',
							),
							array(
								'name'      => __( 'H1', 'generatepress' ),
								'shortName' => __( 'H1', 'generatepress' ),
								'size'      => 48,
								'slug'      => 'H1',
							),
							array(
								'name'      => __( 'H0', 'generatepress' ),
								'shortName' => __( 'H0', 'generatepress' ),
								'size'      => 60,
								'slug'      => 'H0',
							),
							array(
								'name'      => __( 'H00', 'generatepress' ),
								'shortName' => __( 'H00', 'generatepress' ),
								'size'      => 72,
								'slug'      => 'H00',
							)
						)
					);
				}
		
			// Image Size
				if ( function_exists( 'add_image_size' ) ) {
					add_image_size( 'post', 1140 );
					add_image_size( 'thumb', 570 );
				}
				
			// Sidebar
				/*register_sidebar( array(
					'name'          => 'Article Sidebar',
					'id'            => 'article-sidebar',
					'before_widget' => '<div class="single-widget">',
					'after_widget'  => '</div>',
					'before_title'  => '<h4>',
					'after_title'   => '</h4>',
				) );

				register_sidebar( array(
					'name'          => 'Shop Sidebar',
					'id'            => 'shop-sidebar',
					'before_widget' => '<div class="single-widget">',
					'after_widget'  => '</div>',
					'before_title'  => '<h4>',
					'after_title'   => '</h4>',
				) );*/

				// Remove GeneratePress Sidebar
				add_action( 'widgets_init', 'remove_generatepress_sidebars', 11 );

				function remove_generatepress_sidebars(){
					unregister_sidebar( 'sidebar-1' );
					unregister_sidebar( 'sidebar-2' );
					unregister_sidebar( 'header' );
					/*unregister_sidebar( 'footer-1' );
					unregister_sidebar( 'footer-2' );
					unregister_sidebar( 'footer-3' );
					unregister_sidebar( 'footer-4' );
					unregister_sidebar( 'footer-5' );
					unregister_sidebar( 'footer-bar' );
					unregister_sidebar( 'top-bar' );*/
				}
				
			// Excerpt
				function custom_excerpt_length( $length ) 
				{					
					return 15;
				}
				add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );
		}	

	// Theme Customizer		
		// Alternate Logo
		add_action( 'customize_register', 'themecustomizer_alternate_logo' );
		
		function themecustomizer_alternate_logo($wp_customize)
		{ 
			$wp_customize->add_setting( 'alternate_logo', array(
				'default' => get_theme_file_uri('assets/img/selectlogo.png'), // Default
				'sanitize_callback' => 'esc_url_raw'
			));
		 
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'alternate_logo_control', array(
				'label' => 'Alternate Logo',
				'priority' => 9,
				'section' => 'title_tagline',
				'settings' => 'alternate_logo',
				'button_labels' => array(
									  'select' => 'Select logo',
									  'remove' => 'Remove',
									  'change' => 'Change logo',
								   )
			)));		 
		}		

	// Metabox		
		// PAGE Metabox
		add_action( 'add_meta_boxes', 'add_page_metabox' );
		add_action( 'save_post', 'page_metabox_save' );	
		
		function add_page_metabox() 
		{		
			add_meta_box(
				'page_metabox', 
				'Page Options',
				'page_metabox_html', 
				'page',
				'side',
				'low'
			);		
		}	
		
		function page_metabox_html( $post ) 
		{
			//$show_page_title = get_post_meta( $post->ID, '_show_page_title_meta_key', true );
			$transparent_menu = get_post_meta( $post->ID, '_transparent_menu_meta_key', true );
			$hide_margin = get_post_meta( $post->ID, '_hide_margin_meta_key', true );
			
			if ( empty( $transparent_menu ) ) {
				$transparent_menu = 'no';	
			}
			if ( empty( $hide_margin ) ) {
				$hide_margin = 'no';	
			}
			
			/*
				<div class="metabox-row">
					<label for="show_page_title_field">Show Page Title</label>
					<select name="show_page_title_field" id="show_page_title_field" class="postbox">
						<option value="yes" <?php selected( $show_page_title, 'yes' ); ?>>Yes</option>
						<option value="no" <?php selected( $show_page_title, 'no' ); ?>>No</option>
					</select>
				</div>	
			*/
			?>
				<div class="metabox-row">
					<label for="transparent_menu_field">Transparent Header</label>
					<select name="transparent_menu_field" id="transparent_menu_field">
						<option value="yes" <?php selected( $transparent_menu, 'yes' ); ?>>Yes</option>
						<option value="no" <?php selected( $transparent_menu, 'no' ); ?>>No</option>
					</select>
					<div class="metabox-description">
						Make header in this page transparent without background color<br>
						<?php //Alternate logo will be used if you upload it in theme customizer (Appearance - Customize - Site identity). ?>
					</div>
				</div>			
					
				<div class="metabox-row">
                    <label for="hide_margin_field">Remove top and bottom margin on this page</label>
                    <select name="hide_margin_field" id="hide_margin_field">
                        <option value="yes" <?php selected( $hide_margin, 'yes' ); ?>>Yes</option>
                        <option value="no" <?php selected( $hide_margin, 'no' ); ?>>No</option>
                    </select>	
					<div class="metabox-description">
						For more seamless page design. Use block cover as first and/or last block.
                    </div>					
                </div>
			<?php
		}		
		
		function page_metabox_save( $post_id ) 
		{			
			/*if ( array_key_exists( 'show_page_title_field', $_POST ) ) {
				update_post_meta(
					$post_id,
					'_show_page_title_meta_key',
					$_POST['show_page_title_field']
				);
			}*/
			
			if ( array_key_exists( 'transparent_menu_field', $_POST ) ) {
				update_post_meta(
					$post_id,
					'_transparent_menu_meta_key',
					$_POST['transparent_menu_field']
				);
			}

			if ( array_key_exists( 'hide_margin_field', $_POST ) ) {
				update_post_meta(
					$post_id,
					'_hide_margin_meta_key',
					$_POST['hide_margin_field']
				);
			}
		}

		// Body Class 
		add_filter( 'body_class','page_options' );

		function page_options( $classes ) 
		{	
			if ( is_page() )
			{
				global $post;	
				$disable_header = get_post_meta( $post->ID, '_generate-disable-header', true );
				$disable_nav = get_post_meta( $post->ID, '_generate-disable-nav', true );
				$disable_secondary_nav = get_post_meta( $post->ID, '_generate-disable-secondary-nav', true );
				$disable_post_image = get_post_meta( $post->ID, '_generate-disable-post-image', true );
				$disable_headline = get_post_meta( $post->ID, '_generate-disable-headline', true );
				$disable_footer = get_post_meta( $post->ID, '_generate-disable-footer', true );	

				$transparent_menu = get_post_meta( $post->ID, '_transparent_menu_meta_key', true );
				$hide_margin = get_post_meta( $post->ID, '_hide_margin_meta_key', true );				

				if ( empty( $transparent_menu ) ) { $transparent_menu = 'no'; }
				if ( empty( $hide_margin ) ) { $hide_margin = 'no'; }
				
				if ( $disable_headline == true ) { $classes[] = 'no-page-title'; }
				if ( $transparent_menu == 'yes' ) {	$classes[] = 'transparent-header'; }	
				if ( $hide_margin == 'yes' ) {	$classes[] = 'hide-margin'; }
			}			
			
			return $classes;					
		}

	// Gutenberg - Register Block Style
		// Block - Columns
		register_block_style(
			'core/columns',
			array(
				'name'         => 'center',
				'label'        => 'Center',
				'style_handle' => 'center-style',
			)
		);

		// Block - Column
		register_block_style(
			'core/column',
			array(
				'name'         => 'card',
				'label'        => 'Card',
				'style_handle' => 'card-style',
			)
		);

		// Block - Column
		register_block_style(
			'core/list',
			array(
				'name'         => 'stylized',
				'label'        => 'Stylized',
				'style_handle' => 'stylized-style',
			)
		);

		// Block - Button
		register_block_style(
			'core/button',
			array(
				'name'         => 'caramel',
				'label'        => 'Caramel',
				'style_handle' => 'caramel-style',
			)
		);

		// Block - Paragraph
		register_block_style(
			'core/paragraph',
			array(
				'name'         => 'nomargin',
				'label'        => 'No Margin',
				'style_handle' => 'nomargin-style',
			)
		);

	// WPCF7
		// Validate Email
		add_filter( 'wpcf7_validate_email*', 'custom_email_confirmation_validation_filter', 20, 2 );

		function custom_email_confirmation_validation_filter( $result, $tag ) 
		{
			if ( 'email-address-confirmation' == $tag->name ) {
				$your_email = isset( $_POST['email-address'] ) ? trim( $_POST['email-address'] ) : '';
				$your_email_confirm = isset( $_POST['email-address-confirmation'] ) ? trim( $_POST['email-address-confirmation'] ) : '';
		
				if ( $your_email != $your_email_confirm ) {
					$result->invalidate( $tag, "Email address doesn't match." );
				}
			}

			return $result;
		}	

	// ACF BLOCKS
		// Category Block
			/*add_filter( 'block_categories', 'backbone_block_categories' );

			function backbone_block_categories( $categories ) {
				$category_slugs = wp_list_pluck( $categories, 'slug' );
				return in_array( 'generatepress', $category_slugs, true ) ? $categories : array_merge(
					$categories,
					array(
						array(
							'slug'  => 'backbone',
							'title' => __( 'Backbone', 'generatepress' ),
							'icon'  => null,
						),
					)
				);
			}*/

		// Blocks
			/*add_action('acf/init', 'acf_init_block_types');

			function acf_init_block_types() 
			{	
				if( function_exists('acf_register_block_type') ) 
				{
					// Accordion Group
					acf_register_block_type(array(
						'name'              => 'accordion-group',
						'title'             => 'Accordion',
						'description'       => 'Create accordion.',
						'category'          => 'formatting',
						'mode'              => 'preview',
						'icon'				=> 'list-view',
						'supports'          => array(
							'align' => true,
							'mode' => false,
							'jsx' => true
						),
						'render_template' => 'template-parts/blocks/accordion-group.php',
					));

					// Accordion Item
					acf_register_block_type(array(
						'name'              => 'accordion-item',
						'title'             => 'Accordion Item',
						'description'       => 'Create accordion item.',
						'category'          => 'formatting',
						'mode'              => 'preview',
						'icon'				=> 'align-center',
						'supports'          => array(
							'align' => true,
							'mode' => false,
							'jsx' => true
						),
						'render_template' => 'template-parts/blocks/accordion-item.php',
					));

					// Highlight Product - SCRAP
					acf_register_block_type(array(
						'name'              => 'highlight-product',
						'title'             => 'Highlight Product',
						'description'       => 'Show and highlight a product.',
						'category'          => 'formatting',
						'mode'              => 'preview',
						'icon'				=> 'megaphone',
						'supports'          => array(
							'align' => true,
							'mode' => false,
							'jsx' => true
						),
						'render_template' => 'template-parts/blocks/highlight-product.php',
					));

					// Product Card Group
					acf_register_block_type(array(
						'name'              => 'product-card-group',
						'title'             => 'Product Card Group',
						'description'       => 'Show single product.',
						'category'          => 'formatting',
						'mode'              => 'preview',
						'icon'				=> 'columns',
						'supports'          => array(
							'align' => true,
							'mode' => false,
							'jsx' => true
						),
						'render_template'	=> 'template-parts/blocks/product-card-group.php'
					));

					// Product Card
					acf_register_block_type(array(
						'name'              => 'product-card',
						'title'             => 'Product Card',
						'description'       => 'Show single product.',
						'category'          => 'widgets',
						'mode'              => 'preview',
						'icon'				=> 'star-filled',
						'supports'          => array(
							'align' => true,
							'mode' => false,
							'jsx' => true
						),
						'render_template'	=> 'template-parts/blocks/product-card.php'
					));

					// Product Card - Upsell
					acf_register_block_type(array(
						'name'              => 'product-card-upsell',
						'title'             => 'Product Card Upsell',
						'description'       => 'Show single product - upsell version.',
						'category'          => 'widgets',
						'mode'              => 'preview',
						'icon'				=> 'star-empty',
						'supports'          => array(
							'align' => true,
							'mode' => false,
							'jsx' => true
						),
						'render_template'	=> 'template-parts/blocks/product-card-upsell.php'
					));
				}
			}*/
	
	// Custom Post Taxonomy
		// CPT - Retail		
			/*add_action('init', 'register_cpt_retail');
			
			function register_cpt_retail() {
				register_post_type(
					'retail',
					array(
						'labels' => array(
							'name' 			=> _x( 'Retails', 'generatepress' ),
							'singular_name' => _x( 'Retail', 'generatepress' ),
							'menu_name' 	=> __( 'Retails', 'generatepress' ),					
							'add_new'		=> __( 'Add New Retail', 'generatepress' ),
							'add_new_item'	=> __( 'Add New Retail', 'generatepress' ),
							'edit_item'		=> __( 'Edit Retail', 'generatepress' ),					
							'search_items'	=> __( 'Search Retails', 'generatepress' ),
							'not_found'		=> __( 'No Retail found', 'generatepress' ),
							'not_found_in_trash' => __( 'No Retail found in Trash', 'generatepress' ),					
						),
						'public'			=> true,
						'has_archive' 		=> false,
						'rewrite' 			=> array( 
							'slug' 			=> 'retail',
							'with_front' 	=> false
						),
						'show_in_rest'		=> true,
						'supports' 			=> array( 
												'title', 
												'editor', 										
												'thumbnail',
												'revisions'
											),
						'menu_position' 	=> 5,
						'menu_icon' 		=> 'dashicons-location',
						'can_export' 		=> true,
					)
				);
				
				// Add day archive (and pagination)
				add_rewrite_rule("retail/([0-9]{4})/([0-9]{2})/([0-9]{2})/page/?([0-9]{1,})/?",'index.php?post_type=retail&year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]','top');
				add_rewrite_rule("retail/([0-9]{4})/([0-9]{2})/([0-9]{2})/?",'index.php?post_type=retail&year=$matches[1]&monthnum=$matches[2]&day=$matches[3]','top');
				
				// Add month archive (and pagination)
				add_rewrite_rule("retail/([0-9]{4})/([0-9]{2})/page/?([0-9]{1,})/?",'index.php?post_type=retail&year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]','top');
				add_rewrite_rule("retail/([0-9]{4})/([0-9]{2})/?",'index.php?post_type=retail&year=$matches[1]&monthnum=$matches[2]','top');
				
				// Add year archive (and pagination)
				add_rewrite_rule("retail/([0-9]{4})/page/?([0-9]{1,})/?",'index.php?post_type=retail&year=$matches[1]&paged=$matches[2]','top');
				add_rewrite_rule("retail/([0-9]{4})/?",'index.php?post_type=retail&year=$matches[1]','top');
				
			}	
			
			// CPT Retail - Columns List
			add_filter( 'manage_retail_posts_columns', 'cpt_retail_columns' );
			
			function cpt_retail_columns( $retailColumns )
			{
				$retailColumns = array(
					'cb' => '<input type="checkbox">',
					//'article_featured_image' => __( 'Featured Image', 'generatepress' ),
					'title' => __( 'Title', 'generatepress' ),			
					'author' => __( 'Author', 'generatepress' ),
					'comments' => '<span class="vers"><div title="Comments" class="comment-grey-bubble"></div></span>',
					'date' => __( 'Date', 'generatepress' ) 
				);
				
				return $retailColumns;
			}	
			
			// CPT Retail - Custom Columns
			add_action( 'manage_posts_custom_column', 'cpt_retail_custom_columns' );
			
			function cpt_retail_custom_columns( $retailColumns )
			{
				global $post;
			
				switch ( $retailColumns )
				{
					case 'article_featured_image':			
						if ( has_post_thumbnail() )
						{
							the_post_thumbnail( 'medium' );
						}				
						break;
				}		
			} 
			
			// CPT Retail - Category
			add_action( 'init', 'cpt_retail_category', 0 );
		
			function cpt_retail_category() {	 
				$labels = array(
					'name' => _x( 'Retail Categories', 'generatepress' ),
					'singular_name' => _x( 'Category', 'generatepress' ),
					'search_items' =>  __( 'Search Categories' ),
					'all_items' => __( 'All Categories' ),
					'parent_item' => __( 'Parent Category' ),
					'parent_item_colon' => __( 'Parent Category:' ),
					'edit_item' => __( 'Edit Category' ), 
					'update_item' => __( 'Update Category' ),
					'add_new_item' => __( 'Add New Category' ),
					'new_item_name' => __( 'New Category Name' ),
					'menu_name' => __( 'Categories' ),
				); 	
				
				register_taxonomy(
					'retail_category',
					array('retail'), 
					array(
						'hierarchical' => true,
						'labels' => $labels,
						'show_ui' => true,
						'show_admin_column' => true,
						'show_in_rest'      => true,
						'query_var' => true,
						'rewrite' => array( 'slug' => 'retail-category' ),
					)
				);
			}
			
			// CPT Retail - Location
			add_action( 'init', 'cpt_retail_location', 0 );
		
			function cpt_retail_location() {	 
				$labels = array(
					'name' => _x( 'Retail Locations', 'generatepress' ),
					'singular_name' => _x( 'Location', 'generatepress' ),
					'search_items' =>  __( 'Search Locations' ),
					'all_items' => __( 'All Locations' ),
					'parent_item' => __( 'Parent Location' ),
					'parent_item_colon' => __( 'Parent Location:' ),
					'edit_item' => __( 'Edit Location' ), 
					'update_item' => __( 'Update Location' ),
					'add_new_item' => __( 'Add New Location' ),
					'new_item_name' => __( 'New Location Name' ),
					'menu_name' => __( 'Locations' ),
				); 	
				
				register_taxonomy(
					'retail_location',
					array('retail'), 
					array(
						'hierarchical' => true,
						'labels' => $labels,
						'show_ui' => true,
						'show_admin_column' => true,
						'show_in_rest'      => true,
						'query_var' => true,
						'rewrite' => array( 'slug' => 'retail-location' ),
					)
				);
			}*/

	// Woocommerce
		// Breadcrumb 
			add_filter( 'woocommerce_breadcrumb_defaults', 'wcc_change_breadcrumb_delimiter' );

			function wcc_change_breadcrumb_delimiter( $defaults ) {				
				$defaults['delimiter'] = '<span class="breadcrumb-arrow"></span>';
				return $defaults;
			}
?>
