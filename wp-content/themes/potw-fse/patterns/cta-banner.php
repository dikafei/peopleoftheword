<?php
/**
 * Title: "Grow Together in His Word" CTA Banner
 * Slug: potw-fse/cta-banner
 * Categories: potw
 * Description: Register CTA banner used on both the homepage and single post/lesson template.
 */

$image_id  = 99;
$image_url = wp_get_attachment_image_url( $image_id, 'large' );
?>
<!-- wp:columns {"verticalAlignment":"center","align":"wide","className":"potw-cta-banner","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center potw-cta-banner" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)">

	<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
	<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%;padding:50px">
		<!-- wp:html -->
		<div class="potw-divider" aria-hidden="true"><svg viewBox="0 0 8 12"><path d="M0 0l8 6-8 6z"/></svg></div>
		<!-- /wp:html -->
		<!-- wp:heading {"level":2,"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center">Grow Together in His Word</h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center">Join People of the Word to access lectures, lessons, and a structured approach to understanding Scripture.</p>
		<!-- /wp:paragraph -->
		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Register</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
	<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
		<!-- wp:image {"id":99,"sizeSlug":"large"} -->
		<figure class="wp-block-image size-large"><img src="<?php echo esc_url( $image_url ); ?>" alt="Grow together in His Word" class="wp-image-99"/></figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:column -->

</div>
<!-- /wp:columns -->
