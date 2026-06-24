<?php
/**
 * WooCommerce widget catalog data.
 *
 * Harvested from the WooCommerce convenience-tool registrations in
 * class-widget-abilities.php (require Elementor Pro + WooCommerce).
 * Plain data — see EMCP_Tools_Widget_Catalog for the read API.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'woocommerce-products' => array(
		'tier'     => 'woo',
		'title'    => 'WooCommerce Products',
		'category' => 'woocommerce',
		'requires' => 'woocommerce',
		'use_case' => 'Displays a grid of WooCommerce products with column/row count, ordering, and query controls.',
		'keywords' => array( 'woocommerce', 'products', 'grid', 'shop', 'store' ),
		'params'   => array(
			'columns'           => array( 'type' => 'number', 'description' => 'Number of columns. Default: 4.' ),
			'rows'              => array( 'type' => 'number', 'description' => 'Number of rows. Default: 1.' ),
			'paginate'          => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => 'Show pagination.' ),
			'orderby'           => array( 'type' => 'string', 'enum' => array( 'date', 'title', 'price', 'popularity', 'rating', 'rand', 'menu_order' ), 'description' => 'Order by. Default: date.' ),
			'order'             => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => 'Sort order. Default: desc.' ),
			'query_post_type'   => array( 'type' => 'string', 'enum' => array( 'product', 'current_query', 'by_id', 'related' ), 'description' => 'Query source. Default: product.' ),
			'show_result_count' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => 'Show result count.' ),
			'allow_order'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => 'Allow ordering.' ),
		),
		'required' => array(),
		'defaults' => array( 'columns' => 4, 'rows' => 1 ),
	),
	'wc-add-to-cart' => array(
		'tier'     => 'woo',
		'title'    => 'WooCommerce Add to Cart',
		'category' => 'woocommerce',
		'requires' => 'woocommerce',
		'use_case' => 'Add-to-cart button for a specific product with optional quantity input and layout view.',
		'keywords' => array( 'woocommerce', 'cart', 'add', 'button', 'product' ),
		'params'   => array(
			'product_id'    => array( 'type' => 'integer', 'description' => 'Product ID to link to.' ),
			'show_quantity' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => 'Show quantity input.' ),
			'quantity'      => array( 'type' => 'number', 'description' => 'Default quantity.' ),
			'view'          => array( 'type' => 'string', 'enum' => array( '', 'stacked', 'inline' ), 'description' => 'Layout view.' ),
		),
		'required' => array(),
		'defaults' => array(),
	),
	'woocommerce-cart' => array(
		'tier'     => 'woo',
		'title'    => 'WooCommerce Cart',
		'category' => 'woocommerce',
		'requires' => 'woocommerce',
		'use_case' => 'Renders the full WooCommerce cart page.',
		'keywords' => array( 'woocommerce', 'cart', 'basket', 'checkout', 'shop' ),
		'params'   => array(),
		'required' => array(),
		'defaults' => array(),
	),
	'woocommerce-checkout-page' => array(
		'tier'     => 'woo',
		'title'    => 'WooCommerce Checkout',
		'category' => 'woocommerce',
		'requires' => 'woocommerce',
		'use_case' => 'Renders the full WooCommerce checkout page.',
		'keywords' => array( 'woocommerce', 'checkout', 'payment', 'order', 'shop' ),
		'params'   => array(),
		'required' => array(),
		'defaults' => array(),
	),
	'woocommerce-menu-cart' => array(
		'tier'     => 'woo',
		'title'    => 'WooCommerce Menu Cart',
		'category' => 'woocommerce',
		'requires' => 'woocommerce',
		'use_case' => 'Mini cart icon for the menu/header with an items indicator and alignment options.',
		'keywords' => array( 'woocommerce', 'menu', 'cart', 'mini', 'icon' ),
		'params'   => array(
			'icon'                 => array( 'type' => 'object', 'description' => 'Cart icon object.' ),
			'items_indicator'      => array( 'type' => 'string', 'enum' => array( 'none', 'bubble', 'plain' ), 'description' => 'Items indicator style.' ),
			'hide_empty_indicator' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => 'Hide when cart is empty.' ),
			'alignment'            => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => 'Alignment.' ),
		),
		'required' => array(),
		'defaults' => array(),
	),
);
