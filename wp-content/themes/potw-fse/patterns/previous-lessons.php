<?php
/**
 * Title: Previous Lessons
 * Slug: potw-fse/previous-lessons
 * Categories: potw
 * Description: Two-up grid of past lesson cards with a "See All Lessons" link.
 */

$lessons = array(
	array( 'series' => '2 Corinthians Expository Series', 'title' => 'Walking by Faith, Not by Sight', 'meta' => '2 Corinthians 5:7 | April 13 – April 19, 2026', 'image_id' => 100 ),
	array( 'series' => '2 Corinthians Expository Series', 'title' => 'Walking by Faith, Not by Sight', 'meta' => '2 Corinthians 5:7 | April 6 – April 12, 2026', 'image_id' => 101 ),
);
?>
<!-- wp:group {"tagName":"section","align":"wide","backgroundColor":"accent-5","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

	<!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.875rem"}}} -->
		<h3 class="wp-block-heading" style="font-size:1.875rem">Previous Lessons</h3>
		<!-- /wp:heading -->
		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">See All Lessons</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:spacer {"height":"30px"} -->
	<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"align":"wide"} -->
	<div class="wp-block-columns alignwide">
		<?php foreach ( $lessons as $lesson ) : ?>
		<!-- wp:column {"className":"potw-lesson-card"} -->
		<div class="wp-block-column potw-lesson-card">
			<!-- wp:image {"id":<?php echo (int) $lesson['image_id']; ?>,"sizeSlug":"large"} -->
			<figure class="wp-block-image size-large"><img src="<?php echo esc_url( wp_get_attachment_image_url( $lesson['image_id'], 'large' ) ); ?>" alt="<?php echo esc_attr( $lesson['title'] ); ?>" class="wp-image-<?php echo esc_attr( $lesson['image_id'] ); ?>"/></figure>
			<!-- /wp:image -->
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"30px","bottom":"30px","left":"40px","right":"40px"}}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group" style="padding-top:30px;padding-right:40px;padding-bottom:30px;padding-left:40px">
				<!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic","fontSize":"1.875rem"}},"textColor":"secondary"} -->
				<p class="has-secondary-color has-text-color" style="font-style:italic;font-size:1.875rem"><?php echo esc_html( $lesson['series'] ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:heading {"level":4} -->
				<h4 class="wp-block-heading"><?php echo esc_html( $lesson['title'] ); ?></h4>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><?php echo esc_html( $lesson['meta'] ); ?></p>
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
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->

</section>
<!-- /wp:group -->
