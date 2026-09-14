<?php
/**
 * Title: Homepage Hero
 * Slug: potw-fse/hero
 * Categories: potw
 * Description: Full-bleed hero cover with headline, CTA, and the two-item feature row that overlaps the hero image.
 */
?>
<!-- wp:group {"tagName":"section","align":"full","className":"potw-hero","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull potw-hero" style="padding-top:0;padding-bottom:0">

	<!-- wp:cover {"url":"<?php echo esc_url( wp_get_attachment_image_url( 98, 'full' ) ); ?>","id":98,"dimRatio":0,"minHeight":560,"minHeightUnit":"px","isDark":false,"align":"full","className":"potw-hero-cover"} -->
	<div class="wp-block-cover alignfull is-light potw-hero-cover" style="min-height:560px">
		<img class="wp-block-cover__image-background wp-image-98" alt="People of the Word" src="<?php echo esc_url( wp_get_attachment_image_url( 98, 'full' ) ); ?>" data-object-fit="cover"/>
		<div class="wp-block-cover__inner-container">

			<!-- wp:columns {"align":"wide","verticalAlignment":"center"} -->
			<div class="wp-block-columns alignwide are-vertically-aligned-center">

				<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
				<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">

					<!-- wp:heading {"level":1,"style":{"typography":{"lineHeight":"1.2","fontSize":"3rem"}}} -->
					<h1 class="wp-block-heading" style="font-size:3rem;line-height:1.2">People of the Word is a Bible teaching ministry that helps people find the truth of God through the study of His word.</h1>
					<!-- /wp:heading -->

					<!-- wp:spacer {"height":"20px"} -->
					<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>
					<!-- /wp:spacer -->

					<!-- wp:buttons -->
					<div class="wp-block-buttons">
						<!-- wp:button -->
						<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Join The Study</a></div>
						<!-- /wp:button -->
					</div>
					<!-- /wp:buttons -->

				</div>
				<!-- /wp:column -->

				<!-- wp:column {"width":"45%"} -->
				<div class="wp-block-column" style="flex-basis:45%"></div>
				<!-- /wp:column -->

			</div>
			<!-- /wp:columns -->

		</div>
	</div>
	<!-- /wp:cover -->

	<!-- wp:group {"align":"wide","backgroundColor":"background","style":{"border":{"radius":"20px"},"spacing":{"padding":{"top":"30px","bottom":"30px","left":"40px","right":"40px"},"margin":{"top":"-50px","bottom":"0"}},"shadow":"var:preset|shadow|natural"},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group alignwide has-background-background-color has-background" style="border-radius:20px;margin-top:-50px;margin-bottom:0;padding-top:30px;padding-right:40px;padding-bottom:30px;padding-left:40px;box-shadow:var(--wp--preset--shadow--natural)">

		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
			<!-- wp:html -->
			<span class="potw-icon-badge" aria-hidden="true">
				<img src="<?php echo esc_url( wp_get_attachment_image_url( 76, 'full' ) ); ?>" alt="" class="wp-image-76"/>
			</span>
			<!-- /wp:html -->
			<!-- wp:group {"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"2.25rem"}}} -->
				<h3 class="wp-block-heading" style="font-size:2.25rem">Expository Teaching</h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p>Chapter-by-chapter study of the Bible</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
			<!-- wp:html -->
			<span class="potw-icon-badge" aria-hidden="true">
				<img src="<?php echo esc_url( wp_get_attachment_image_url( 79, 'full' ) ); ?>" alt="" class="wp-image-79"/>
			</span>
			<!-- /wp:html -->
			<!-- wp:group {"layout":{"type":"constrained"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"2.25rem"}}} -->
				<h3 class="wp-block-heading" style="font-size:2.25rem">Weekly Lessons</h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p>Clear, structured teaching released each week</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

</section>
<!-- /wp:group -->
