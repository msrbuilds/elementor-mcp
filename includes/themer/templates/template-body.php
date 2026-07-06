<?php
/**
 * Body-only takeover: keep the active theme's header + footer, swap the content.
 *
 * Used when a Themer body template (single/archive/page/search/404) wins but the
 * user has NOT built both a Themer header and footer. Calls the theme's
 * get_header()/get_footer() so the site chrome is preserved (like Elementor Pro's
 * Single templates) and renders the Themer body between them. A standalone Themer
 * header OR footer, if present, is injected into the theme's hooks by the render
 * controller's adapter.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_slots = class_exists( 'EMCP_Tools_Themer_Render_Controller' )
	? EMCP_Tools_Themer_Render_Controller::slots()
	: array();

get_header();

if ( ! empty( $emcp_slots['body'] ) ) {
	// width:100% + flex:1 so the body fills the theme's content column even when the
	// theme wraps it in a flexbox container (e.g. Astra's ast-two-container). Without
	// this the <main> only takes its intrinsic width, leaving empty space beside it.
	echo '<main class="emcp-themer-body" style="width:100%;flex:1 1 auto;min-width:0;">';
	echo EMCP_Tools_Themer_Content_Renderer::render( (int) $emcp_slots['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</main>';
}

get_footer();
