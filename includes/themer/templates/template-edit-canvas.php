<?php
/**
 * Singular preview canvas for a Themer template (`emcp_theme_template`).
 *
 * A blank, full-width document that calls `the_content()`. Two jobs:
 *   1. Elementor's editor loads the post's front-end URL in its preview iframe
 *      and refuses to attach unless the template calls `the_content()` ("Sorry,
 *      the content area was not found in your page."). This canvas provides it.
 *   2. Viewing a template at its own URL renders its built content standalone.
 *
 * This is used ONLY when the CURRENT request is the template's own singular view
 * (editing/previewing the template itself) — never when applying a template to a
 * real request. The render controller routes to it and skips Themer resolution.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<?php
$emcp_tpl_id  = (int) get_queried_object_id();
$emcp_is_loop = class_exists( 'EMCP_Tools_Themer_CPT' ) && 'loop' === EMCP_Tools_Themer_CPT::template_type( $emcp_tpl_id );
$emcp_width   = $emcp_is_loop ? (int) EMCP_Tools_Themer_CPT::loop_preview( $emcp_tpl_id )['width'] : 0;
?>
<body <?php body_class( 'emcp-themer-edit-canvas' . ( $emcp_is_loop ? ' emcp-themer-loop-canvas' : '' ) ); ?>>
<?php
if ( $emcp_is_loop ) {
	// A card is narrow; previewing it full-width would misrepresent every Loop Grid.
	printf( '<div class="emcp-themer-loop-preview" style="max-width:%dpx;">', $emcp_width );
}
while ( have_posts() ) :
	the_post();
	the_content();
endwhile;
if ( $emcp_is_loop ) {
	echo '</div>';
}
wp_footer();
?>
</body>
</html>
