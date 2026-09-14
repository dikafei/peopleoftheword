<?php
/**
 * Title: Lesson Table of Contents (sidebar)
 * Slug: potw-fse/single-toc
 * Categories: potw
 * Description: Sticky "Table of Contents" card for the single lesson/post sidebar.
 */

$items = array(
	array( 'n' => '1', 'label' => 'See the Power of God’s Voice' ),
	array( 'n' => '2', 'label' => 'Hear the Praise of His Friends' ),
	array( 'n' => '3', 'label' => 'Enjoy the Peace of His People' ),
	array( 'n' => '4', 'label' => 'Hear and Respond to Him' ),
	array( 'n' => '4.1', 'label' => 'Begin with Bible (Access His Voice)', 'sub' => true ),
	array( 'n' => '4.2', 'label' => 'Move to Meditation (Sit with His Voice)', 'sub' => true ),
	array( 'n' => '4.3', 'label' => 'Polish with Prayer (Reply with Your Voice)', 'sub' => true ),
	array( 'n' => '5', 'label' => 'The Voice of the Lord in His Son' ),
);
?>
<!-- wp:group {"className":"potw-toc","style":{"spacing":{"padding":{"top":"30px","bottom":"30px","left":"30px","right":"30px"}},"position":{"type":"sticky","top":"20px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group potw-toc" style="padding-top:30px;padding-right:30px;padding-bottom:30px;padding-left:30px;position:sticky;top:20px">
	<!-- wp:heading {"level":4,"style":{"typography":{"fontSize":"1.5rem"}}} -->
	<h4 class="wp-block-heading" style="font-size:1.5rem">Table of Contents</h4>
	<!-- /wp:heading -->
	<!-- wp:html -->
	<ol>
		<?php foreach ( $items as $item ) : ?>
		<li class="<?php echo ! empty( $item['sub'] ) ? 'potw-toc-sub' : ''; ?>">
			<span class="potw-toc-index"><?php echo esc_html( $item['n'] ); ?></span>
			<a href="#toc-<?php echo esc_attr( str_replace( '.', '-', $item['n'] ) ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
		</li>
		<?php endforeach; ?>
	</ol>
	<!-- /wp:html -->
</div>
<!-- /wp:group -->
