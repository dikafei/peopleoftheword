<?php
/**
 * The template for displaying the header.
 *
 * @package GeneratePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?> <?php generate_do_microdata( 'body' ); ?>>
	<?php		
		do_action( 'wp_body_open' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP hook.
		
		// @hooked generate_do_skip_to_content_link - 2
		// @hooked generate_top_bar - 5
		// @hooked generate_add_navigation_before_header - 5
		do_action( 'generate_before_header' );
		
		// @hooked generate_construct_header - 10
		//do_action( 'generate_header' );

		?>
			<header <?php generate_do_attr( 'header' ); ?>>
				<div class="container">
					<div <?php generate_do_attr( 'inside-header' ); ?>>					
						<?php				
							// generate_before_header_content hook.				 
							do_action( 'generate_before_header_content' );

							if ( ! generate_is_using_flexbox() ) {
								// Add our main header items.
								generate_header_items();
							}
						?>

						<div class="header-right">
							<?php
								// @hooked generate_add_navigation_float_right - 5
								do_action( 'generate_after_header_content' );
							?>
							<a href="#">
								<button>
									<img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/register.svg">
									<?php _e( 'Register', 'potw' ); ?>
								</button>
							</a>
						</div>
					</div>
				</div>
			</header>
		<?php
	
		// @hooked generate_featured_page_header - 10
		do_action( 'generate_after_header' );
	?>

	<div <?php generate_do_attr( 'page' ); ?>>
		<?php		
			do_action( 'generate_inside_site_container' );
		?>
		<div <?php generate_do_attr( 'site-content' ); ?>>
			<?php			
				do_action( 'generate_inside_container' );
