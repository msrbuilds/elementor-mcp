<?php
/**
 * Page Snapshot builder — one normalized page digest for MCP agents.
 *
 * Assembles a compact, normalized view of a page (structure tree + counts,
 * global tokens actually in use, per-device responsive overrides, content
 * outline, SEO-lite) so an AI agent can reason about a page from a single
 * call instead of chaining get-page-structure / get-global-settings /
 * list-global-classes and reassembling. Heavy audit summaries are opt-in and
 * resolved through the `emcp_tools_page_snapshot_sections` filter seam.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds normalized page snapshots.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Page_Snapshot {

	/**
	 * The data access layer.
	 *
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param EMCP_Tools_Data $data The data access layer.
	 */
	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	/**
	 * Recursively normalize an Elementor elements array into a compact tree + counts.
	 *
	 * @param array $elements Elementor elements array.
	 * @param int   $depth    Current depth (0 at top).
	 * @return array{tree:array,counts:array}
	 */
	public static function normalize_tree( array $elements, int $depth = 0 ): array {
		$tree   = array();
		$counts = array(
			'containers'     => 0,
			'widgets'        => 0,
			'by_widget_type' => array(),
			'max_depth'      => $depth,
			'total_elements' => 0,
		);

		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type   = isset( $el['elType'] ) ? (string) $el['elType'] : '';
			$is_widget = ( 'widget' === $el_type );

			$node = array(
				'id'    => isset( $el['id'] ) ? (string) $el['id'] : '',
				'kind'  => $is_widget ? 'widget' : 'container',
				'depth' => $depth,
			);

			if ( $is_widget ) {
				$wt                  = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
				$node['widget_type'] = $wt;
				++$counts['widgets'];
				if ( '' !== $wt ) {
					$counts['by_widget_type'][ $wt ] = ( $counts['by_widget_type'][ $wt ] ?? 0 ) + 1;
				}
			} else {
				++$counts['containers'];
			}
			++$counts['total_elements'];

			$label = self::element_label( $el );
			if ( '' !== $label ) {
				$node['label'] = $label;
			}

			$children = ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) ? $el['elements'] : array();
			if ( $children ) {
				$sub                       = self::normalize_tree( $children, $depth + 1 );
				$node['children']          = $sub['tree'];
				$counts['containers']     += $sub['counts']['containers'];
				$counts['widgets']        += $sub['counts']['widgets'];
				$counts['total_elements'] += $sub['counts']['total_elements'];
				$counts['max_depth']       = max( $counts['max_depth'], $sub['counts']['max_depth'] );
				foreach ( $sub['counts']['by_widget_type'] as $k => $v ) {
					$counts['by_widget_type'][ $k ] = ( $counts['by_widget_type'][ $k ] ?? 0 ) + $v;
				}
			} else {
				$node['children'] = array();
			}

			$tree[] = $node;
		}

		return array(
			'tree'   => $tree,
			'counts' => $counts,
		);
	}

	/**
	 * Derive a short human label for an element from its settings.
	 *
	 * @param array $el Element array.
	 * @return string Short label, or '' when none derivable.
	 */
	public static function element_label( array $el ): string {
		$s = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();
		foreach ( array( '_title', 'title', 'text', 'editor', 'heading_title' ) as $k ) {
			if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
				$plain = trim( (string) preg_replace( '/<[^>]*>/', '', $s[ $k ] ) );
				if ( '' !== $plain ) {
					return self::snippet( $plain, 60 );
				}
			}
		}
		return '';
	}

	/**
	 * Walk elements collecting which global colors/typography, g- classes, and raw
	 * fonts are actually referenced, with usage counts.
	 *
	 * @param array $elements Elementor elements array.
	 * @return array{global_colors:array<string,int>,global_typography:array<string,int>,global_classes:array<string,int>,fonts_in_use:string[],colors_in_use:string[]}
	 */
	public static function extract_tokens( array $elements ): array {
		$acc = array(
			'global_colors'     => array(),
			'global_typography' => array(),
			'global_classes'    => array(),
			'fonts_in_use'      => array(),
			'colors_in_use'     => array(),
		);
		self::walk_tokens( $elements, $acc );
		$acc['fonts_in_use']  = array_values( array_unique( $acc['fonts_in_use'] ) );
		$acc['colors_in_use'] = array_values( array_unique( $acc['colors_in_use'] ) );
		return $acc;
	}

	/**
	 * Recursive token collector.
	 *
	 * @param array $elements Elements.
	 * @param array $acc      Accumulator (by reference).
	 */
	private static function walk_tokens( array $elements, array &$acc ): void {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$s = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();

			// Global color/typography refs live in __globals__: "globals/colors?id=primary".
			if ( ! empty( $s['__globals__'] ) && is_array( $s['__globals__'] ) ) {
				foreach ( $s['__globals__'] as $ref ) {
					if ( ! is_string( $ref ) ) {
						continue;
					}
					if ( preg_match( '#globals/colors\?id=([\w-]+)#', $ref, $m ) ) {
						$acc['global_colors'][ $m[1] ] = ( $acc['global_colors'][ $m[1] ] ?? 0 ) + 1;
					} elseif ( preg_match( '#globals/typography\?id=([\w-]+)#', $ref, $m ) ) {
						$acc['global_typography'][ $m[1] ] = ( $acc['global_typography'][ $m[1] ] ?? 0 ) + 1;
					}
				}
			}

			// g- global classes appear in _css_classes / classes (string), or atomic classes.value (array).
			foreach ( array( '_css_classes', 'classes' ) as $ck ) {
				if ( ! empty( $s[ $ck ] ) && is_string( $s[ $ck ] ) ) {
					foreach ( preg_split( '/\s+/', $s[ $ck ] ) as $cls ) {
						if ( '' !== $cls && 0 === strpos( $cls, 'g-' ) ) {
							$acc['global_classes'][ $cls ] = ( $acc['global_classes'][ $cls ] ?? 0 ) + 1;
						}
					}
				}
			}
			if ( isset( $s['classes']['value'] ) && is_array( $s['classes']['value'] ) ) {
				foreach ( $s['classes']['value'] as $cls ) {
					if ( is_string( $cls ) && 0 === strpos( $cls, 'g-' ) ) {
						$acc['global_classes'][ $cls ] = ( $acc['global_classes'][ $cls ] ?? 0 ) + 1;
					}
				}
			}

			// Raw fonts / hex colors in use.
			foreach ( $s as $key => $val ) {
				if ( is_string( $val ) && '' !== $val ) {
					if ( false !== strpos( (string) $key, 'font_family' ) ) {
						$acc['fonts_in_use'][] = $val;
					} elseif ( ( false !== strpos( (string) $key, 'color' ) ) && preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $val ) ) {
						$acc['colors_in_use'][] = strtolower( $val );
					}
				}
			}

			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_tokens( $el['elements'], $acc );
			}
		}
	}

	/**
	 * Detect which elements carry per-device (_tablet / _mobile / _laptop / _widescreen)
	 * setting overrides.
	 *
	 * @param array $elements Elementor elements array.
	 * @return array{overrides:array<int,array{element_id:string,widget_type:string,settings:string[]}>,counts:array{desktop_only:int,has_tablet:int,has_mobile:int}}
	 */
	public static function detect_responsive( array $elements ): array {
		$overrides = array();
		$counts    = array(
			'desktop_only' => 0,
			'has_tablet'   => 0,
			'has_mobile'   => 0,
		);
		self::walk_responsive( $elements, $overrides, $counts );
		return array(
			'overrides' => $overrides,
			'counts'    => $counts,
		);
	}

	/**
	 * Recursive responsive-override collector.
	 *
	 * @param array $elements  Elements.
	 * @param array $overrides Accumulator (by reference).
	 * @param array $counts    Accumulator (by reference).
	 */
	private static function walk_responsive( array $elements, array &$overrides, array &$counts ): void {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$s          = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();
			$keys       = array();
			$has_tablet = false;
			$has_mobile = false;
			foreach ( array_keys( $s ) as $k ) {
				if ( preg_match( '/_(tablet|mobile|laptop|widescreen|mobile_extra|tablet_extra)$/', (string) $k, $m ) ) {
					$keys[] = $k;
					if ( false !== strpos( $m[1], 'tablet' ) ) {
						$has_tablet = true;
					}
					if ( false !== strpos( $m[1], 'mobile' ) ) {
						$has_mobile = true;
					}
				}
			}
			if ( $keys ) {
				$overrides[] = array(
					'element_id'  => isset( $el['id'] ) ? (string) $el['id'] : '',
					'widget_type' => isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '',
					'settings'    => $keys,
				);
				if ( $has_tablet ) {
					++$counts['has_tablet'];
				}
				if ( $has_mobile ) {
					++$counts['has_mobile'];
				}
			} else {
				++$counts['desktop_only'];
			}

			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_responsive( $el['elements'], $overrides, $counts );
			}
		}
	}

	/**
	 * Content-level stats: heading outline, word/image/link/button counts, missing alts.
	 *
	 * @param array $elements Elementor elements array.
	 * @return array{headings:array,word_count:int,image_count:int,images_missing_alt:int,link_count:int,button_count:int}
	 */
	public static function content_stats( array $elements ): array {
		$acc = array(
			'headings'           => array(),
			'word_count'         => 0,
			'image_count'        => 0,
			'images_missing_alt' => 0,
			'link_count'         => 0,
			'button_count'       => 0,
		);
		self::walk_content( $elements, $acc );
		return $acc;
	}

	/**
	 * Recursive content collector.
	 *
	 * @param array $elements Elements.
	 * @param array $acc      Accumulator (by reference).
	 */
	private static function walk_content( array $elements, array &$acc ): void {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$wt = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
			$s  = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();

			if ( 'heading' === $wt && ! empty( $s['title'] ) && is_string( $s['title'] ) ) {
				$tag               = ( ! empty( $s['header_size'] ) && is_string( $s['header_size'] ) ) ? $s['header_size'] : 'h2';
				$acc['headings'][] = array(
					'tag'        => $tag,
					'text'       => self::snippet( trim( (string) preg_replace( '/<[^>]*>/', '', $s['title'] ) ), 120 ),
					'element_id' => isset( $el['id'] ) ? (string) $el['id'] : '',
				);
			}

			// Word count from common text fields.
			foreach ( array( 'title', 'editor', 'text', 'description_text', 'testimonial_content' ) as $tk ) {
				if ( ! empty( $s[ $tk ] ) && is_string( $s[ $tk ] ) ) {
					$plain = trim( (string) preg_replace( '/\s+/', ' ', (string) preg_replace( '/<[^>]*>/', ' ', $s[ $tk ] ) ) );
					if ( '' !== $plain ) {
						$acc['word_count'] += count( preg_split( '/\s+/', $plain ) );
					}
				}
			}

			if ( 'image' === $wt || 'theme-site-logo' === $wt ) {
				if ( isset( $s['image'] ) && is_array( $s['image'] ) ) {
					++$acc['image_count'];
					if ( empty( $s['image']['alt'] ) ) {
						++$acc['images_missing_alt'];
					}
				}
			}

			if ( 'button' === $wt ) {
				++$acc['button_count'];
			}
			if ( isset( $s['link']['url'] ) && '' !== $s['link']['url'] ) {
				++$acc['link_count'];
			}

			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk_content( $el['elements'], $acc );
			}
		}
	}

	/**
	 * Structural smell warnings.
	 *
	 * @param array $elements Raw elements (for empty-container detection).
	 * @param array $counts   From normalize_tree()['counts'].
	 * @param array $content  From content_stats().
	 * @return array<int,array{code:string,message:string}>
	 */
	public static function warnings( array $elements, array $counts, array $content ): array {
		$w  = array();
		$h1 = 0;
		foreach ( ( $content['headings'] ?? array() ) as $h ) {
			if ( isset( $h['tag'] ) && 'h1' === strtolower( (string) $h['tag'] ) ) {
				++$h1;
			}
		}
		if ( 0 === $h1 ) {
			$w[] = array(
				'code'    => 'no_h1',
				'message' => 'Page has no H1 heading.',
			);
		}
		if ( $h1 > 1 ) {
			$w[] = array(
				'code'    => 'multiple_h1',
				'message' => 'Page has more than one H1 heading.',
			);
		}
		if ( isset( $counts['max_depth'] ) && $counts['max_depth'] >= 6 ) {
			$w[] = array(
				'code'    => 'deep_nesting',
				'message' => 'Container nesting is 6+ levels deep.',
			);
		}
		if ( self::has_empty_container( $elements ) ) {
			$w[] = array(
				'code'    => 'empty_container',
				'message' => 'One or more containers have no children.',
			);
		}
		return $w;
	}

	/**
	 * Whether any non-widget element has no children.
	 *
	 * @param array $elements Elements.
	 * @return bool
	 */
	private static function has_empty_container( array $elements ): bool {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$is_widget = ( isset( $el['elType'] ) && 'widget' === $el['elType'] );
			$children  = ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) ? $el['elements'] : array();
			if ( ! $is_widget && ! $children ) {
				return true;
			}
			if ( $children && self::has_empty_container( $children ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Truncate to a max length with an ellipsis.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max length.
	 * @return string
	 */
	public static function snippet( string $text, int $max ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $len <= $max ) {
			return $text;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max - 1 ) : substr( $text, 0, $max - 1 );
		return $cut . '…';
	}
}
