<?php
/**
 * Command palette library (spec 7): prompt and template titles, fetched by
 * the palette on the first keystroke. Cached reads only.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Palette extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route( 'palette/library', array( 'methods' => 'GET', 'callback' => array( $this, 'library' ) ) );
	}

	public function library( $request ) {
		$items = EMCP_Tools_Admin_Prompts_Data::palette_items();
		$items = (array) apply_filters( 'emcp_tools_palette_library', $items );
		return new WP_REST_Response( array( 'items' => array_values( $items ) ) );
	}
}
