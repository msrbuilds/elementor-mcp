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
	 * Core sections, always available and computed in-process.
	 */
	const CORE_SECTIONS = array( 'post', 'structure', 'tokens', 'responsive', 'content', 'seo_lite', 'warnings' );

	/**
	 * Heavy, opt-in sections (loopback fetch / WCAG math). performance is free;
	 * a11y + seo are Pro (resolved via the seam).
	 */
	const HEAVY_SECTIONS = array( 'performance', 'a11y', 'seo' );

	/**
	 * Build a page snapshot.
	 *
	 * @param int   $post_id Target post.
	 * @param array $args    { builder?, post?, sections?, include?, fresh?, url? }.
	 * @return array
	 */
	public function build( int $post_id, array $args = array() ): array {
		$sections = ( ! empty( $args['sections'] ) && is_array( $args['sections'] ) )
			? array_values( array_intersect( self::CORE_SECTIONS, $args['sections'] ) )
			: self::CORE_SECTIONS;
		$include  = ( ! empty( $args['include'] ) && is_array( $args['include'] ) )
			? array_values( array_intersect( self::HEAVY_SECTIONS, $args['include'] ) )
			: array();

		$builder  = isset( $args['builder'] ) ? (string) $args['builder'] : 'elementor';
		$elements = ( 'elementor' === $builder ) ? (array) $this->data->get_page_data( $post_id ) : array();

		$norm    = self::normalize_tree( $elements );
		$content = self::content_stats( $elements );
		$out     = array();

		if ( in_array( 'post', $sections, true ) ) {
			$out['post'] = ( isset( $args['post'] ) && is_array( $args['post'] ) )
				? $args['post']
				: array(
					'id'      => $post_id,
					'builder' => $builder,
				);
		}
		if ( in_array( 'structure', $sections, true ) ) {
			$out['structure'] = $norm;
		}
		if ( in_array( 'tokens', $sections, true ) ) {
			$out['tokens'] = self::extract_tokens( $elements );
		}
		if ( in_array( 'responsive', $sections, true ) ) {
			$out['responsive'] = self::detect_responsive( $elements );
		}
		if ( in_array( 'content', $sections, true ) ) {
			$out['content'] = $content;
		}
		if ( in_array( 'seo_lite', $sections, true ) ) {
			$out['seo_lite'] = self::seo_lite( $post_id, $content );
		}
		if ( in_array( 'warnings', $sections, true ) ) {
			$out['warnings'] = self::warnings( $elements, $norm['counts'], $content );
		}

		// Heavy, opt-in sections resolved via the seam (see heavy_sections()).
		if ( $include ) {
			foreach ( $this->heavy_sections( $post_id, $include, $args ) as $key => $section ) {
				$out[ $key ] = $section;
			}
		}

		return $out;
	}

	/**
	 * Free SEO-lite read: h1 count from content + meta title/description/canonical/og
	 * read directly from Yoast / Rank Math / core meta (no Pro dependency).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $content From content_stats().
	 * @return array{h1_count:int,meta_title:string,meta_description:string,canonical:string,og_image:string}
	 */
	public static function seo_lite( int $post_id, array $content ): array {
		$h1 = 0;
		foreach ( ( $content['headings'] ?? array() ) as $h ) {
			if ( isset( $h['tag'] ) && 'h1' === strtolower( (string) $h['tag'] ) ) {
				++$h1;
			}
		}

		$read = static function ( array $keys ) use ( $post_id ): string {
			if ( ! function_exists( 'get_post_meta' ) || $post_id <= 0 ) {
				return '';
			}
			foreach ( $keys as $key ) {
				$v = get_post_meta( $post_id, $key, true );
				if ( is_string( $v ) && '' !== $v ) {
					return $v;
				}
			}
			return '';
		};

		return array(
			'h1_count'         => $h1,
			'meta_title'       => $read( array( '_yoast_wpseo_title', 'rank_math_title' ) ),
			'meta_description' => $read( array( '_yoast_wpseo_metadesc', 'rank_math_description' ) ),
			'canonical'        => $read( array( '_yoast_wpseo_canonical', 'rank_math_canonical_url' ) ),
			'og_image'         => $read( array( '_yoast_wpseo_opengraph-image', 'rank_math_facebook_image' ) ),
		);
	}

	/**
	 * Resolve opt-in heavy sections. Free-core supplies `performance`; the Pro overlay
	 * hooks `emcp_tools_page_snapshot_sections` for `a11y` + deep `seo`. Anything still
	 * unresolved degrades to a pro-gated/unavailable stub. Heavy sections are
	 * transient-cached (15 min) unless $args['fresh'].
	 *
	 * @param int   $post_id Post ID.
	 * @param array $include Requested heavy sections.
	 * @param array $args    Build args.
	 * @return array<string,array>
	 */
	protected function heavy_sections( int $post_id, array $include, array $args ): array {
		$fresh    = ! empty( $args['fresh'] );
		$sections = array();

		// Free: performance (page-level; needs manage_options).
		if ( in_array( 'performance', $include, true ) ) {
			$sections['performance'] = $this->cached_section(
				$post_id,
				'performance',
				$fresh,
				function () use ( $post_id, $args ) {
					if ( ! ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) ) {
						return array(
							'available' => false,
							'reason'    => 'permission',
						);
					}
					if ( ! class_exists( 'EMCP_Tools_Performance_Analyzer' ) ) {
						return array(
							'available' => false,
							'reason'    => 'unavailable',
						);
					}
					$analyzer = new EMCP_Tools_Performance_Analyzer();
					$input    = array( 'post_id' => $post_id );
					if ( ! empty( $args['url'] ) ) {
						$input['url'] = (string) $args['url'];
					}
					$report = $analyzer->analyze( $input );
					if ( function_exists( 'is_wp_error' ) && is_wp_error( $report ) ) {
						return array(
							'available' => false,
							'reason'    => 'error',
						);
					}
					$report = (array) $report;
					return array(
						'available'       => true,
						'score'           => $report['score'] ?? null,
						'grade'           => $report['grade'] ?? null,
						'recommendations' => array_slice( (array) ( $report['top_recommendations'] ?? array() ), 0, 5 ),
					);
				}
			);
		}

		// Pro sections via the seam.
		$seam = apply_filters( 'emcp_tools_page_snapshot_sections', array(), $post_id, $include, $args );
		foreach ( array( 'a11y', 'seo' ) as $key ) {
			if ( ! in_array( $key, $include, true ) ) {
				continue;
			}
			if ( isset( $seam[ $key ] ) && is_array( $seam[ $key ] ) ) {
				$sections[ $key ] = $seam[ $key ];
			} else {
				$sections[ $key ] = array(
					'available'  => false,
					'pro_gated'  => true,
				);
			}
		}

		return $sections;
	}

	/**
	 * Transient wrapper for a heavy section.
	 *
	 * @param int      $post_id Post ID.
	 * @param string   $section Section key.
	 * @param bool     $fresh   Bypass the cache.
	 * @param callable $compute Producer returning the section array.
	 * @return array
	 */
	private function cached_section( int $post_id, string $section, bool $fresh, callable $compute ): array {
		$key = 'emcp_snap_' . $post_id . '_' . $section;
		if ( ! $fresh && function_exists( 'get_transient' ) ) {
			$hit = get_transient( $key );
			if ( is_array( $hit ) ) {
				$hit['cached'] = true;
				return $hit;
			}
		}
		$val = $compute();
		if ( function_exists( 'set_transient' ) && ! empty( $val['available'] ) ) {
			set_transient( $key, $val, 15 * ( defined( 'MINUTE_IN_SECONDS' ) ? MINUTE_IN_SECONDS : 60 ) );
		}
		$val['cached'] = false;
		return $val;
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
