<?php
/**
 * Title: "The Study Is For…" Cards
 * Slug: potw-fse/study-is-for
 * Categories: potw
 * Description: Three-column audience card row.
 */

$cards = array(
	array(
		'title'    => 'Those who want a deeper understanding of the Bible',
		'image_id' => 99,
	),
	array(
		'title'    => 'People looking for structured study',
		'image_id' => 100,
	),
	array(
		'title'    => 'Anyone seeking spiritual growth through Christ',
		'image_id' => 101,
	),
);
?>
<!-- wp:group {"tagName":"section","align":"wide","backgroundColor":"accent-5","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignwide has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

	<!-- wp:heading {"textAlign":"center","style":{"typography":{"fontSize":"2.25rem"}}} -->
	<h2 class="wp-block-heading has-text-align-center" style="font-size:2.25rem">The Study is for…</h2>
	<!-- /wp:heading -->

	<!-- wp:spacer {"height":"30px"} -->
	<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"align":"wide"} -->
	<div class="wp-block-columns alignwide">
		<?php foreach ( $cards as $card ) : ?>
		<!-- wp:column {"className":"potw-audience-card"} -->
		<div class="wp-block-column potw-audience-card">
			<!-- wp:image {"id":<?php echo (int) $card['image_id']; ?>,"sizeSlug":"large"} -->
			<figure class="wp-block-image size-large"><img src="<?php echo esc_url( wp_get_attachment_image_url( $card['image_id'], 'large' ) ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" class="wp-image-<?php echo esc_attr( $card['image_id'] ); ?>"/></figure>
			<!-- /wp:image -->
			<!-- wp:paragraph {"align":"center","style":{"typography":{"fontFamily":"var(--wp--preset--font-family--heading)"}}} -->
			<p class="has-text-align-center"><?php echo esc_html( $card['title'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->

</section>
<!-- /wp:group -->
