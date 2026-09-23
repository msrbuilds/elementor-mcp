<?php
/**
 * Loop Grid and Loop Carousel Gutenberg blocks: attributes, inspector
 * control descriptors, and the attributes to element args mapping.
 *
 * The mapping translates block attributes (camelCase) into the Elementor
 * widget's setting names and hands them to EMCP_Tools_Themer_Loop_Widget_Map,
 * so both builders share one mapping: the responsive cascade, the date
 * trimming, the exclude-current split by source and the element-default
 * fallbacks are never written twice. The block then differs in two ways
 * only: the element prints its custom properties inline (a block has no
 * Elementor selectors), and there is no local id (a block passes its
 * anchor only; identical blocks are separated by the element's occurrence
 * counter).
 *
 * Attribute defaults are read from the element's own defaults() and the
 * loop query's own defaults, so the two cannot drift apart.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.18.0
 */
class EMCP_Tools_Themer_Loop_Block_Map {

	/** Block keys (registered as emcp/{key}) to their element kind. */
	const BLOCKS = array(
		'loop-grid'     => 'grid',
		'loop-carousel' => 'carousel',
	);

	/**
	 * Block attributes whose saved values must always be among the
	 * inspector's options, grouped by the option list they belong to.
	 */
	const SAVED_GROUPS = array(
		'templates'  => array( 'templateId' ),
		'terms'      => array( 'terms', 'excludeTerms' ),
		'authors'    => array( 'authors' ),
		'postTypes'  => array( 'postTypes' ),
		'taxonomies' => array( 'relatedTaxonomy' ),
	);

	/**
	 * Block attribute => widget setting, for the attributes both blocks
	 * share. Values pass through as they are, except the conversions
	 * to_settings() makes (booleans, id lists, the AJAX switch).
	 */
	const COMMON = array(
		'templateId'            => 'emcp_template_id',
		'emptyMessage'          => 'emcp_empty_message',
		'itemTag'               => 'emcp_item_tag',
		'anchor'                => '_element_id',
		'source'                => 'emcp_source',
		'postTypes'             => 'emcp_post_types',
		'perPage'               => 'emcp_per_page',
		'offset'                => 'emcp_offset',
		'orderby'               => 'emcp_orderby',
		'order'                 => 'emcp_order',
		'metaKey'               => 'emcp_meta_key',
		'terms'                 => 'emcp_terms',
		'excludeTerms'          => 'emcp_exclude_terms',
		'authors'               => 'emcp_authors',
		'includeIds'            => 'emcp_include_ids',
		'excludeIds'            => 'emcp_exclude_ids',
		'excludeCurrent'        => 'emcp_exclude_current',
		'relatedExcludeCurrent' => 'emcp_related_exclude_current',
		'ignoreSticky'          => 'emcp_ignore_sticky',
		'date'                  => 'emcp_date',
		'after'                 => 'emcp_after',
		'before'                => 'emcp_before',
		'relatedTaxonomy'       => 'emcp_related_taxonomy',
		'hideOutOfStock'        => 'emcp_hide_out_of_stock',
		'onSaleOnly'            => 'emcp_on_sale_only',
		'featuredOnly'          => 'emcp_featured_only',
	);

	/** Grid block attribute => widget setting. */
	const GRID = array(
		'columns'        => 'emcp_columns',
		'columnsTablet'  => 'emcp_columns_tablet',
		'columnsMobile'  => 'emcp_columns_mobile',
		'gapX'           => 'emcp_gap_x',
		'gapY'           => 'emcp_gap_y',
		'masonry'        => 'emcp_masonry',
		'equalHeight'    => 'emcp_equal_height',
		'firstItemSpan'  => 'emcp_first_item_span',
		'hoverEffect'    => 'emcp_hover_effect',
		'animation'      => 'emcp_animation',
		'animationStep'  => 'emcp_animation_step',
		'pagination'     => 'emcp_pagination',
		'pageLimit'      => 'emcp_page_limit',
		'shorten'        => 'emcp_shorten',
		'prevLabel'      => 'emcp_prev_label',
		'nextLabel'      => 'emcp_next_label',
		'loadMoreLabel'  => 'emcp_load_more_label',
		'ajax'           => 'emcp_load_type',
		'infiniteOffset' => 'emcp_infinite_offset',
	);

	/** Carousel block attribute => widget setting. */
	const CAROUSEL = array(
		'slides'               => 'emcp_slides',
		'slidesTablet'         => 'emcp_slides_tablet',
		'slidesMobile'         => 'emcp_slides_mobile',
		'slidesToScroll'       => 'emcp_slides_to_scroll',
		'slidesToScrollTablet' => 'emcp_slides_to_scroll_tablet',
		'slidesToScrollMobile' => 'emcp_slides_to_scroll_mobile',
		'gap'                  => 'emcp_gap',
		'gapTablet'            => 'emcp_gap_tablet',
		'gapMobile'            => 'emcp_gap_mobile',
		'height'               => 'emcp_height',
		'autoplay'             => 'emcp_autoplay',
		'autoplayDelay'        => 'emcp_autoplay_delay',
		'pauseOnHover'         => 'emcp_pause_on_hover',
		'pauseOnInteraction'   => 'emcp_pause_on_interaction',
		'loop'                 => 'emcp_loop',
		'speed'                => 'emcp_speed',
		'direction'            => 'emcp_direction',
		'centered'             => 'emcp_centered',
		'offsetSides'          => 'emcp_offset_sides',
		'offsetWidth'          => 'emcp_offset_width',
		'effect'               => 'emcp_effect',
		'keyboard'             => 'emcp_keyboard',
		'mousewheel'           => 'emcp_mousewheel',
		'arrows'               => 'emcp_arrows',
		'arrowsPosition'       => 'emcp_arrows_position',
		'arrowsHideMobile'     => 'emcp_hide_arrows_mobile',
		'dots'                 => 'emcp_dots',
		'dotsPosition'         => 'emcp_dots_position',
	);

	/** Attributes holding a list of ids, edited as comma-separated text. */
	const ID_LISTS = array( 'includeIds', 'excludeIds' );

	/**
	 * Map a loop block's attributes to its element's args.
	 *
	 * @param string $key Block key (loop-grid or loop-carousel).
	 * @param array  $a   Block attributes.
	 * @return array Element args; empty for an unknown key.
	 */
	public static function attributes_to_args( string $key, array $a ): array {
		if ( ! isset( self::BLOCKS[ $key ] ) ) {
			return array();
		}
		$grid = 'grid' === self::BLOCKS[ $key ];
		$s    = self::to_settings( $a, array_merge( self::COMMON, $grid ? self::GRID : self::CAROUSEL ) );
		$args = $grid
			? EMCP_Tools_Themer_Loop_Widget_Map::grid( $s, '' )
			: EMCP_Tools_Themer_Loop_Widget_Map::carousel( $s, '' );
		// A block has no Elementor selectors, so the element prints the
		// custom properties itself. The carousel always prints them and has
		// no such argument.
		if ( $grid ) {
			$args['inline_vars'] = true;
		}
		// A block passes its anchor only: identical blocks are separated by
		// the element's own occurrence counter.
		unset( $args['local_id'] );
		return $args;
	}

	/**
	 * Block attributes in the widget's setting shape.
	 *
	 * @param array                $a   Attributes.
	 * @param array<string,string> $map Attribute => setting.
	 * @return array
	 */
	private static function to_settings( array $a, array $map ): array {
		$s = array();
		foreach ( $map as $attr => $setting ) {
			if ( ! array_key_exists( $attr, $a ) || null === $a[ $attr ] ) {
				continue; // Absent: the widget map applies the element default.
			}
			$v = $a[ $attr ];
			if ( is_bool( $v ) ) {
				$v = $v ? 'yes' : '';
			}
			if ( in_array( $attr, self::ID_LISTS, true ) && is_array( $v ) ) {
				$v = implode( ',', array_map( 'strval', array_filter( $v, 'is_scalar' ) ) );
			}
			if ( 'ajax' === $attr ) {
				$v = 'yes' === $v ? 'ajax' : 'reload';
			}
			$s[ $setting ] = $v;
		}
		return $s;
	}

	/**
	 * The query attributes both blocks share, with the loop query's own
	 * defaults.
	 *
	 * @return array
	 */
	private static function query_attributes(): array {
		$q = EMCP_Tools_Themer_Loop_Query::sanitize( array() );
		return array(
			'templateId'            => array( 'type' => 'number', 'default' => 0 ),
			'source'                => array( 'type' => 'string', 'default' => $q['source'] ),
			'postTypes'             => array( 'type' => 'array', 'default' => $q['post_types'] ),
			'perPage'               => array( 'type' => 'number', 'default' => $q['per_page'] ),
			'offset'                => array( 'type' => 'number', 'default' => $q['offset'] ),
			'orderby'               => array( 'type' => 'string', 'default' => $q['orderby'] ),
			'order'                 => array( 'type' => 'string', 'default' => strtolower( $q['order'] ) ),
			'metaKey'               => array( 'type' => 'string', 'default' => $q['meta_key'] ),
			'terms'                 => array( 'type' => 'array', 'default' => array() ),
			'excludeTerms'          => array( 'type' => 'array', 'default' => array() ),
			'authors'               => array( 'type' => 'array', 'default' => array() ),
			'includeIds'            => array( 'type' => 'array', 'default' => array() ),
			'excludeIds'            => array( 'type' => 'array', 'default' => array() ),
			// Posts and products: off. Related: on. The query's own
			// source-aware default, split into one switch per source kind as
			// the widget does.
			'excludeCurrent'        => array( 'type' => 'boolean', 'default' => false ),
			'relatedExcludeCurrent' => array( 'type' => 'boolean', 'default' => true ),
			'ignoreSticky'          => array( 'type' => 'boolean', 'default' => $q['ignore_sticky'] ),
			'date'                  => array( 'type' => 'string', 'default' => $q['date'] ),
			'after'                 => array( 'type' => 'string', 'default' => '' ),
			'before'                => array( 'type' => 'string', 'default' => '' ),
			'relatedTaxonomy'       => array( 'type' => 'string', 'default' => $q['related_taxonomy'] ),
			'hideOutOfStock'        => array( 'type' => 'boolean', 'default' => $q['hide_out_of_stock'] ),
			'onSaleOnly'            => array( 'type' => 'boolean', 'default' => $q['on_sale_only'] ),
			'featuredOnly'          => array( 'type' => 'boolean', 'default' => $q['featured_only'] ),
			'itemTag'               => array( 'type' => 'string', 'default' => 'div' ),
			'emptyMessage'          => array( 'type' => 'string', 'default' => '' ),
			'anchor'                => array( 'type' => 'string', 'default' => '' ),
		);
	}

	/**
	 * A block's attribute schema.
	 *
	 * @param string $key Block key.
	 * @return array
	 */
	public static function attributes( string $key ): array {
		if ( ! isset( self::BLOCKS[ $key ] ) ) {
			return array();
		}
		$out = self::query_attributes();
		if ( 'grid' === self::BLOCKS[ $key ] ) {
			$d   = EMCP_Tools_Themer_Element_Loop_Grid::defaults();
			$map = self::GRID;
			$src = array(
				'columns' => 'columns', 'columnsTablet' => 'columns_tablet', 'columnsMobile' => 'columns_mobile',
				'gapX' => 'gap_x', 'gapY' => 'gap_y', 'masonry' => 'masonry', 'equalHeight' => 'equal_height',
				'firstItemSpan' => 'first_item_span', 'hoverEffect' => 'hover_effect', 'animation' => 'animation',
				'animationStep' => 'animation_step', 'pagination' => 'pagination', 'pageLimit' => 'page_limit',
				'shorten' => 'shorten', 'prevLabel' => 'prev_label', 'nextLabel' => 'next_label',
				'loadMoreLabel' => 'load_more_label', 'ajax' => 'ajax', 'infiniteOffset' => 'infinite_offset',
			);
			$out['itemTag']['default'] = (string) $d['tag'];
		} else {
			$d   = EMCP_Tools_Themer_Element_Loop_Carousel::defaults();
			$map = self::CAROUSEL;
			$src = array();
			foreach ( array_keys( $map ) as $attr ) {
				$src[ $attr ] = strtolower( (string) preg_replace( '/([A-Z])/', '_$1', $attr ) );
			}
		}
		foreach ( array_keys( $map ) as $attr ) {
			$value        = $d[ $src[ $attr ] ];
			$out[ $attr ] = array(
				'type'    => is_bool( $value ) ? 'boolean' : ( is_int( $value ) ? 'number' : 'string' ),
				'default' => $value,
			);
		}
		return $out;
	}

	/**
	 * Inspector control descriptors. `panel` groups controls into inspector
	 * panels; `when` shows a control only while another attribute has one
	 * of the listed values (`in`) or none of them (`notIn`). Option lists
	 * come from the editor payload's loopOptions by `optionsKey`.
	 *
	 * @param string $key Block key.
	 * @return array
	 */
	public static function controls( string $key ): array {
		if ( ! isset( self::BLOCKS[ $key ] ) ) {
			return array();
		}
		$q  = __( 'Query', 'emcp-tools' );
		$c  = static function ( string $attr, string $type, string $label, array $extra = array() ): array {
			return array_merge( array( 'key' => $attr, 'type' => $type, 'label' => $label ), $extra );
		};
		$not_current = array( 'when' => array( 'attr' => 'source', 'notIn' => array( 'current' ) ) );
		$filtering   = array( 'when' => array( 'attr' => 'source', 'in' => array( 'posts', 'products' ) ) );

		$controls = array(
			$c( 'templateId', 'select', __( 'Loop Item', 'emcp-tools' ), array( 'optionsKey' => 'templates', 'panel' => __( 'Loop Item', 'emcp-tools' ) ) ),
			$c( 'itemTag', 'select', __( 'Item HTML tag', 'emcp-tools' ), array( 'optionsKey' => 'itemTags', 'panel' => __( 'Loop Item', 'emcp-tools' ) ) ),
			$c( 'source', 'select', __( 'Source', 'emcp-tools' ), array( 'optionsKey' => 'sources', 'panel' => $q ) ),
			$c( 'postTypes', 'multiselect', __( 'Post types', 'emcp-tools' ), array( 'optionsKey' => 'postTypes', 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'posts' ) ) ) ),
			$c( 'relatedTaxonomy', 'select', __( 'Related by', 'emcp-tools' ), array( 'optionsKey' => 'taxonomies', 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'related' ) ) ) ),
			$c( 'perPage', 'number', __( 'Items per page', 'emcp-tools' ), array_merge( array( 'min' => 1, 'max' => EMCP_Tools_Themer_Loop_Query::MAX_PER_PAGE, 'panel' => $q ), $not_current ) ),
			$c( 'offset', 'number', __( 'Skip first', 'emcp-tools' ), array( 'min' => 0, 'max' => EMCP_Tools_Themer_Loop_Query::MAX_OFFSET, 'panel' => $q, 'when' => array( 'attr' => 'source', 'notIn' => array( 'current', 'manual' ) ) ) ),
			$c( 'orderby', 'select', __( 'Order by', 'emcp-tools' ), array_merge( array( 'optionsKey' => 'orderby', 'panel' => $q ), array( 'when' => array( 'attr' => 'source', 'notIn' => array( 'current', 'manual' ) ) ) ) ),
			$c( 'metaKey', 'text', __( 'Custom field key', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'orderby', 'in' => array( 'meta_value', 'meta_value_num' ) ) ) ),
			$c( 'order', 'select', __( 'Order', 'emcp-tools' ), array( 'optionsKey' => 'order', 'panel' => $q, 'when' => array( 'attr' => 'source', 'notIn' => array( 'current', 'manual' ) ) ) ),
			$c( 'terms', 'multiselect', __( 'Include terms', 'emcp-tools' ), array_merge( array( 'optionsKey' => 'terms', 'panel' => $q ), $filtering ) ),
			$c( 'excludeTerms', 'multiselect', __( 'Exclude terms', 'emcp-tools' ), array_merge( array( 'optionsKey' => 'terms', 'panel' => $q ), $filtering ) ),
			$c( 'authors', 'multiselect', __( 'Authors', 'emcp-tools' ), array( 'optionsKey' => 'authors', 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'posts' ) ) ) ),
			$c( 'includeIds', 'text-list', __( 'Include IDs (comma separated)', 'emcp-tools' ), array_merge( array( 'panel' => $q ), $not_current ) ),
			$c( 'excludeIds', 'text-list', __( 'Exclude IDs (comma separated)', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'source', 'notIn' => array( 'current', 'manual' ) ) ) ),
			$c( 'excludeCurrent', 'toggle', __( 'Exclude current post', 'emcp-tools' ), array_merge( array( 'panel' => $q ), $filtering ) ),
			$c( 'relatedExcludeCurrent', 'toggle', __( 'Exclude current post', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'related' ) ) ) ),
			$c( 'ignoreSticky', 'toggle', __( 'Ignore sticky posts', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'posts' ) ) ) ),
			$c( 'date', 'select', __( 'Date range', 'emcp-tools' ), array_merge( array( 'optionsKey' => 'dates', 'panel' => $q ), $filtering ) ),
			$c( 'after', 'text', __( 'After (YYYY-MM-DD)', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'date', 'in' => array( 'custom' ) ) ) ),
			$c( 'before', 'text', __( 'Before (YYYY-MM-DD)', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'date', 'in' => array( 'custom' ) ) ) ),
			$c( 'hideOutOfStock', 'toggle', __( 'Hide out of stock', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'products' ) ) ) ),
			$c( 'onSaleOnly', 'toggle', __( 'On sale only', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'products' ) ) ) ),
			$c( 'featuredOnly', 'toggle', __( 'Featured only', 'emcp-tools' ), array( 'panel' => $q, 'when' => array( 'attr' => 'source', 'in' => array( 'products' ) ) ) ),
		);

		if ( 'grid' === self::BLOCKS[ $key ] ) {
			$l = __( 'Layout', 'emcp-tools' );
			$p = __( 'Pagination', 'emcp-tools' );
			$controls = array_merge(
				$controls,
				array(
					$c( 'columns', 'number', __( 'Columns', 'emcp-tools' ), array( 'min' => 1, 'max' => 6, 'panel' => $l ) ),
					$c( 'columnsTablet', 'number', __( 'Columns (tablet)', 'emcp-tools' ), array( 'min' => 1, 'max' => 6, 'panel' => $l ) ),
					$c( 'columnsMobile', 'number', __( 'Columns (mobile)', 'emcp-tools' ), array( 'min' => 1, 'max' => 6, 'panel' => $l ) ),
					$c( 'gapX', 'number', __( 'Column gap (px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 120, 'panel' => $l ) ),
					$c( 'gapY', 'number', __( 'Row gap (px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 120, 'panel' => $l ) ),
					$c( 'masonry', 'toggle', __( 'Masonry', 'emcp-tools' ), array( 'panel' => $l ) ),
					$c( 'equalHeight', 'toggle', __( 'Equal height', 'emcp-tools' ), array( 'panel' => $l, 'when' => array( 'attr' => 'masonry', 'in' => array( false ) ) ) ),
					$c( 'firstItemSpan', 'number', __( 'First item spans (columns)', 'emcp-tools' ), array( 'min' => 1, 'max' => 6, 'panel' => $l ) ),
					$c( 'hoverEffect', 'select', __( 'Hover effect', 'emcp-tools' ), array( 'optionsKey' => 'hover', 'panel' => $l ) ),
					$c( 'animation', 'select', __( 'Entrance animation', 'emcp-tools' ), array( 'optionsKey' => 'animations', 'panel' => $l ) ),
					$c( 'animationStep', 'number', __( 'Animation stagger (ms)', 'emcp-tools' ), array( 'min' => 0, 'max' => 1000, 'panel' => $l, 'when' => array( 'attr' => 'animation', 'notIn' => array( 'none' ) ) ) ),
					$c( 'pagination', 'select', __( 'Pagination', 'emcp-tools' ), array( 'optionsKey' => 'pagination', 'panel' => $p ) ),
					$c( 'pageLimit', 'number', __( 'Page limit (0 = all)', 'emcp-tools' ), array( 'min' => 0, 'max' => 100, 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'notIn' => array( 'none' ) ) ) ),
					$c( 'ajax', 'toggle', __( 'Load pages without reloading', 'emcp-tools' ), array( 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'in' => array( 'numbers', 'prev_next', 'numbers_prev_next' ) ) ) ),
					$c( 'shorten', 'toggle', __( 'Shorten page numbers', 'emcp-tools' ), array( 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'in' => array( 'numbers', 'numbers_prev_next' ) ) ) ),
					$c( 'prevLabel', 'text', __( 'Previous label', 'emcp-tools' ), array( 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'in' => array( 'prev_next', 'numbers_prev_next' ) ) ) ),
					$c( 'nextLabel', 'text', __( 'Next label', 'emcp-tools' ), array( 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'in' => array( 'prev_next', 'numbers_prev_next' ) ) ) ),
					$c( 'loadMoreLabel', 'text', __( 'Load more text', 'emcp-tools' ), array( 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'in' => array( 'load_more' ) ) ) ),
					$c( 'infiniteOffset', 'number', __( 'Load when this close to the end (px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 2000, 'panel' => $p, 'when' => array( 'attr' => 'pagination', 'in' => array( 'infinite' ) ) ) ),
				)
			);
		} else {
			$l = __( 'Slides', 'emcp-tools' );
			$n = __( 'Navigation', 'emcp-tools' );
			$b = __( 'Behaviour', 'emcp-tools' );
			$controls = array_merge(
				$controls,
				array(
					$c( 'slides', 'number', __( 'Slides per view', 'emcp-tools' ), array( 'min' => 1, 'max' => 10, 'panel' => $l ) ),
					$c( 'slidesTablet', 'number', __( 'Slides per view (tablet)', 'emcp-tools' ), array( 'min' => 1, 'max' => 10, 'panel' => $l ) ),
					$c( 'slidesMobile', 'number', __( 'Slides per view (mobile)', 'emcp-tools' ), array( 'min' => 1, 'max' => 10, 'panel' => $l ) ),
					$c( 'slidesToScroll', 'number', __( 'Slides to scroll', 'emcp-tools' ), array( 'min' => 1, 'max' => 10, 'panel' => $l ) ),
					$c( 'slidesToScrollTablet', 'number', __( 'Slides to scroll (tablet)', 'emcp-tools' ), array( 'min' => 1, 'max' => 10, 'panel' => $l ) ),
					$c( 'slidesToScrollMobile', 'number', __( 'Slides to scroll (mobile)', 'emcp-tools' ), array( 'min' => 1, 'max' => 10, 'panel' => $l ) ),
					$c( 'gap', 'number', __( 'Gap (px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 120, 'panel' => $l ) ),
					$c( 'gapTablet', 'number', __( 'Gap (tablet, px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 120, 'panel' => $l ) ),
					$c( 'gapMobile', 'number', __( 'Gap (mobile, px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 120, 'panel' => $l ) ),
					$c( 'height', 'select', __( 'Height', 'emcp-tools' ), array( 'optionsKey' => 'heights', 'panel' => $l ) ),
					$c( 'offsetSides', 'select', __( 'Peek at the sides', 'emcp-tools' ), array( 'optionsKey' => 'offsets', 'panel' => $l ) ),
					$c( 'offsetWidth', 'number', __( 'Peek width (px)', 'emcp-tools' ), array( 'min' => 0, 'max' => 400, 'panel' => $l, 'when' => array( 'attr' => 'offsetSides', 'notIn' => array( 'none' ) ) ) ),
					$c( 'arrows', 'toggle', __( 'Arrows', 'emcp-tools' ), array( 'panel' => $n ) ),
					$c( 'arrowsPosition', 'select', __( 'Arrows position', 'emcp-tools' ), array( 'optionsKey' => 'arrowPositions', 'panel' => $n, 'when' => array( 'attr' => 'arrows', 'in' => array( true ) ) ) ),
					$c( 'arrowsHideMobile', 'toggle', __( 'Hide arrows on mobile', 'emcp-tools' ), array( 'panel' => $n, 'when' => array( 'attr' => 'arrows', 'in' => array( true ) ) ) ),
					$c( 'dots', 'select', __( 'Pagination', 'emcp-tools' ), array( 'optionsKey' => 'dots', 'panel' => $n ) ),
					$c( 'dotsPosition', 'select', __( 'Pagination position', 'emcp-tools' ), array( 'optionsKey' => 'dotPositions', 'panel' => $n, 'when' => array( 'attr' => 'dots', 'notIn' => array( 'none' ) ) ) ),
					$c( 'autoplay', 'toggle', __( 'Autoplay', 'emcp-tools' ), array( 'panel' => $b ) ),
					$c( 'autoplayDelay', 'number', __( 'Autoplay delay (ms)', 'emcp-tools' ), array( 'min' => 500, 'max' => 20000, 'panel' => $b, 'when' => array( 'attr' => 'autoplay', 'in' => array( true ) ) ) ),
					$c( 'pauseOnHover', 'toggle', __( 'Pause on hover', 'emcp-tools' ), array( 'panel' => $b, 'when' => array( 'attr' => 'autoplay', 'in' => array( true ) ) ) ),
					$c( 'pauseOnInteraction', 'toggle', __( 'Pause on interaction', 'emcp-tools' ), array( 'panel' => $b, 'when' => array( 'attr' => 'autoplay', 'in' => array( true ) ) ) ),
					$c( 'loop', 'toggle', __( 'Infinite loop', 'emcp-tools' ), array( 'panel' => $b ) ),
					$c( 'speed', 'number', __( 'Transition speed (ms)', 'emcp-tools' ), array( 'min' => 100, 'max' => 3000, 'panel' => $b ) ),
					$c( 'effect', 'select', __( 'Effect', 'emcp-tools' ), array( 'optionsKey' => 'effects', 'panel' => $b ) ),
					$c( 'centered', 'toggle', __( 'Centered slides', 'emcp-tools' ), array( 'panel' => $b, 'when' => array( 'attr' => 'effect', 'in' => array( 'slide' ) ) ) ),
					$c( 'direction', 'select', __( 'Direction', 'emcp-tools' ), array( 'optionsKey' => 'directions', 'panel' => $b ) ),
					$c( 'keyboard', 'toggle', __( 'Keyboard navigation', 'emcp-tools' ), array( 'panel' => $b ) ),
					$c( 'mousewheel', 'toggle', __( 'Mouse wheel', 'emcp-tools' ), array( 'panel' => $b ) ),
				)
			);
		}

		$a = __( 'Additional', 'emcp-tools' );
		$controls[] = $c( 'emptyMessage', 'text', __( 'Nothing found message', 'emcp-tools' ), array( 'panel' => $a ) );
		$controls[] = $c( 'anchor', 'text', __( 'Loop id (keeps page links stable)', 'emcp-tools' ), array( 'panel' => $a ) );
		return $controls;
	}

	/**
	 * Saved option values of every loop block in a parsed block tree
	 * (inner blocks included), so the inspector always lists them.
	 *
	 * @param array $blocks parse_blocks() output.
	 * @return array<string,string[]> SAVED_GROUPS key => unique non-empty values.
	 */
	public static function collect_saved( array $blocks ): array {
		$out = array_fill_keys( array_keys( self::SAVED_GROUPS ), array() );
		self::walk( $blocks, $out, 0 );
		foreach ( $out as $group => $values ) {
			$out[ $group ] = array_values( array_unique( $values ) );
		}
		return $out;
	}

	/**
	 * @param array $blocks Blocks.
	 * @param array $out    Collected values, by reference.
	 * @param int   $depth  Nesting depth (bounded).
	 */
	private static function walk( array $blocks, array &$out, int $depth ): void {
		if ( $depth > 50 ) {
			return;
		}
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = (string) ( $block['blockName'] ?? '' );
			if ( 0 === strpos( $name, 'emcp/' ) && isset( self::BLOCKS[ substr( $name, 5 ) ] ) ) {
				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				foreach ( self::SAVED_GROUPS as $group => $keys ) {
					foreach ( $keys as $attr ) {
						foreach ( (array) ( $attrs[ $attr ] ?? array() ) as $value ) {
							if ( is_scalar( $value ) && '' !== (string) $value && '0' !== (string) $value ) {
								$out[ $group ][] = (string) $value;
							}
						}
					}
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $out, $depth + 1 );
			}
		}
	}
}
