<?php
/**
 * Free + core Elementor widget catalog data.
 *
 * Harvested from the convenience-tool registrations in class-widget-abilities.php.
 * Plain data — see EMCP_Tools_Widget_Catalog for the read API.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'heading' => array(
		'tier'     => 'free',
		'title'    => 'Heading',
		'category' => 'basic',
		'requires' => null,
		'use_case' => 'Section titles and headlines. One h1 per page; h2/h3 for section headers. Supports full typography, text stroke, shadow, and blend mode.',
		'keywords' => array( 'title', 'text', 'heading', 'h1', 'h2', 'headline' ),
		'params'   => array(
			'title'       => array( 'type' => 'string', 'description' => 'Heading text.' ),
			'header_size' => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => 'HTML tag. Default: h2.' ),
			'align'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => 'Text alignment. Responsive: align_tablet, align_mobile.' ),
			'title_color' => array( 'type' => 'string', 'description' => 'Heading color (hex/rgba).' ),
			'link'        => array( 'type' => 'object', 'description' => 'Link: {url, is_external, nofollow}.' ),
		),
		'required' => array( 'title' ),
		'defaults' => array( 'header_size' => 'h2' ),
	),
);
