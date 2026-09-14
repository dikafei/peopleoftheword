<?php
/**
 * Title: Lesson of the Week
 * Slug: potw-fse/lesson-of-week
 * Categories: potw
 * Description: Featured current lesson card plus the memory-verse callout.
 */

$image_id  = 99;
$image_url = wp_get_attachment_image_url( $image_id, 'large' );
?>
<!-- wp:group {"tagName":"section","align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center">Lesson of The Week</h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center">Clear, applicable lessons provided each week.</p>
		<!-- /wp:paragraph -->
		<!-- wp:html -->
		<div class="potw-divider" aria-hidden="true"><svg viewBox="0 0 8 12"><path d="M0 0l8 6-8 6z"/></svg></div>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->

	<!-- wp:spacer {"height":"40px"} -->
	<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"verticalAlignment":"center","className":"potw-lesson-card"} -->
	<div class="wp-block-columns are-vertically-aligned-center potw-lesson-card">

		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:image {"id":99,"sizeSlug":"large"} -->
			<figure class="wp-block-image size-large"><img src="<?php echo esc_url( $image_url ); ?>" alt="Lesson of the week" class="wp-image-99"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%;padding:40px">
			<!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic","fontSize":"1.875rem"}},"textColor":"secondary"} -->
			<p class="has-secondary-color has-text-color" style="font-style:italic;font-size:1.875rem">2 Corinthians Expository Series</p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading">Walking by Faith, Not by Sight</h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size">2 Corinthians 5:7 | April 20 – April 26, 2026</p>
			<!-- /wp:paragraph -->
			<!-- wp:spacer {"height":"20px"} -->
			<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>
			<!-- /wp:spacer -->
			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-outline"} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Open Lesson</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

	<!-- wp:spacer {"height":"30px"} -->
	<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:group {"className":"potw-memory-verse","style":{"spacing":{"padding":{"top":"40px","bottom":"40px","left":"40px","right":"40px"}}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group potw-memory-verse" style="padding-top:40px;padding-right:40px;padding-bottom:40px;padding-left:40px">
		<!-- wp:heading {"level":4,"textColor":"accent-4","style":{"typography":{"fontSize":"2.25rem"}}} -->
		<h4 class="wp-block-heading has-accent-4-color has-text-color" style="font-size:2.25rem">This Week's Memory Verse</h4>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic"}}} -->
		<p style="font-style:italic">"For His anger is but for a moment, His favor is for life; weeping may endure for a night, but joy comes in the morning." — Psalm 30:5</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

</section>
<!-- /wp:group -->
