<?php
/**
 * Normalized content extraction from an Elementor element tree.
 *
 * Walks a page's Elementor data once and yields the shapes the SEO and A11y
 * audits both need — headings, text blocks, images, links, form fields, a word
 * count, and best-effort text/background color pairs for contrast checks. The
 * core is static and operates on a plain element-tree array, so it unit-tests
 * with fixtures and needs no WordPress at all; alt-text resolution from the
 * media library is the only optional WP touch (guarded by function_exists).
 *
 * Native-widget-first: bespoke markup inside HTML/text-editor widgets is parsed
 * with light regex (headings, links, <img> alt), so audits don't silently
 * under-count content that lives in raw HTML.
 *
 * @package EMCP_Tools
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts a normalized, audit-ready view of a page's content.
 *
 * @since 1.8.0
 */
final class EMCP_Tools_Content_Extractor {

	/**
	 * Extracts the normalized content view from an element tree.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $elements  The Elementor element tree (array of elements).
	 * @param string $site_host Optional site host (e.g. "example.com") for
	 *                          internal/external link classification.
	 * @return array {
	 *     @type array $headings            [ ['level'=>int,'text'=>string,'element_id'=>string], ... ]
	 *     @type array $text_blocks         [ ['text'=>string,'element_id'=>string], ... ]
	 *     @type array $images              [ ['element_id'=>string,'attachment_id'=>int,'url'=>string,'alt'=>string], ... ]
	 *     @type array $links               [ ['url'=>string,'text'=>string,'internal'=>bool,'element_id'=>string], ... ]
	 *     @type array $form_fields         [ ['label'=>string,'type'=>string,'element_id'=>string], ... ]
	 *     @type array $text_style_contexts [ ['element_id'=>string,'color'=>string,'background'=>?string,'background_source'=>string], ... ]
	 *     @type int   $word_count          Total words across headings + text blocks.
	 * }
	 */
	public static function extract( array $elements, string $site_host = '' ): array {
		$out = array(
			'headings'            => array(),
			'text_blocks'         => array(),
			'images'              => array(),
			'links'               => array(),
			'form_fields'         => array(),
			'text_style_contexts' => array(),
			'word_count'          => 0,
		);

		$out['_current_heading'] = '';
		self::walk( $elements, $site_host, null, $out );
		unset( $out['_current_heading'] );

		// Word count from headings + text blocks.
		$words = 0;
		foreach ( $out['headings'] as $h ) {
			$words += self::count_words( $h['text'] );
		}
		foreach ( $out['text_blocks'] as $t ) {
			$words += self::count_words( $t['text'] );
		}
		$out['word_count'] = $words;

		return $out;
	}

	/**
	 * Recursively walks the tree, carrying the nearest resolved ancestor
	 * background color for contrast context.
	 *
	 * @param array       $elements   Elements to walk.
	 * @param string      $site_host  Site host for link classification.
	 * @param string|null $ancestor_bg Nearest ancestor background hex, or null.
	 * @param array       $out        Accumulator (by reference).
	 */
	private static function walk( array $elements, string $site_host, ?string $ancestor_bg, array &$out ): void {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$id       = isset( $el['id'] ) ? (string) $el['id'] : '';
			$type     = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
			$el_type  = isset( $el['elType'] ) ? (string) $el['elType'] : '';
			$settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();

			// Resolve this element's own background, if any (containers mostly).
			$own_bg = self::resolve_bg( $settings );
			$bg_for_children = ( null !== $own_bg ) ? $own_bg : $ancestor_bg;

			self::collect_widget( $id, $type, $el_type, $settings, $site_host, $ancestor_bg, $out );

			// Recurse into children (containers / sections / columns / atomic).
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				self::walk( $el['elements'], $site_host, $bg_for_children, $out );
			}
		}
	}

	/**
	 * Collects content from a single element by widget type.
	 *
	 * @param string      $id          Element id.
	 * @param string      $type        widgetType.
	 * @param string      $el_type     elType.
	 * @param array       $settings    Element settings.
	 * @param string      $site_host   Site host.
	 * @param string|null $ancestor_bg Nearest ancestor background hex.
	 * @param array       $out         Accumulator (by reference).
	 */
	private static function collect_widget( string $id, string $type, string $el_type, array $settings, string $site_host, ?string $ancestor_bg, array &$out ): void {
		switch ( $type ) {
			case 'heading':
				$text  = self::scalar( $settings['title'] ?? '' );
				$level = self::header_size_to_level( self::scalar( $settings['header_size'] ?? 'h2' ) );
				if ( '' !== $text ) {
					$plain = self::plain_text( $text );
					if ( null !== $level ) {
						$out['headings'][]       = array(
							'level'      => $level,
							'text'       => $plain,
							'element_id' => $id,
						);
						$out['_current_heading'] = $plain;
					} else {
						$out['text_blocks'][] = array( 'text' => $plain, 'element_id' => $id );
					}
				}
				self::collect_text_color( $id, $settings, array( 'title_color' ), $ancestor_bg, $out );
				self::maybe_link( self::scalar( self::deep( $settings, array( 'link', 'url' ) ) ), $text, $site_host, $id, $out );
				break;

			case 'e-heading': // Atomic.
				$atext  = self::scalar( $settings['title'] ?? '' );
				$alevel = self::header_size_to_level( self::scalar( $settings['tag'] ?? 'h2' ) );
				if ( '' !== $atext && null !== $alevel ) {
					$aplain                  = self::plain_text( $atext );
					$out['headings'][]       = array( 'level' => $alevel, 'text' => $aplain, 'element_id' => $id );
					$out['_current_heading'] = $aplain;
				}
				break;

			case 'text-editor':
			case 'e-paragraph':
				$html = self::scalar( $settings['editor'] ?? ( $settings['paragraph'] ?? '' ) );
				if ( '' !== $html ) {
					$out['text_blocks'][] = array( 'text' => self::plain_text( $html ), 'element_id' => $id );
					self::parse_markup( $html, $site_host, $id, $out );
				}
				self::collect_text_color( $id, $settings, array( 'text_color', 'color' ), $ancestor_bg, $out );
				break;

			case 'button':
			case 'e-button':
				$btxt = self::plain_text( self::scalar( $settings['text'] ?? '' ) );
				if ( '' !== $btxt ) {
					$out['text_blocks'][] = array( 'text' => $btxt, 'element_id' => $id );
				}
				self::maybe_link( self::scalar( self::deep( $settings, array( 'link', 'url' ) ) ), $btxt, $site_host, $id, $out );
				break;

			case 'image':
			case 'e-image':
			case 'image-box':
				self::collect_image( $id, $settings, $out );
				if ( 'image-box' === $type ) {
					$title = self::plain_text( self::scalar( $settings['title_text'] ?? '' ) );
					$desc  = self::plain_text( self::scalar( $settings['description_text'] ?? '' ) );
					if ( '' !== $title ) {
						$lvl = self::header_size_to_level( self::scalar( $settings['title_size'] ?? 'h3' ) );
						$out['headings'][] = array( 'level' => $lvl ?? 3, 'text' => $title, 'element_id' => $id );
					}
					if ( '' !== $desc ) {
						$out['text_blocks'][] = array( 'text' => $desc, 'element_id' => $id );
					}
				}
				break;

			case 'icon-list':
				$items = $settings['icon_list'] ?? array();
				if ( is_array( $items ) ) {
					foreach ( $items as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$itxt = self::plain_text( self::scalar( $item['text'] ?? '' ) );
						if ( '' !== $itxt ) {
							$out['text_blocks'][] = array( 'text' => $itxt, 'element_id' => $id );
						}
						self::maybe_link( self::scalar( self::deep( $item, array( 'link', 'url' ) ) ), $itxt, $site_host, $id, $out );
					}
				}
				break;

			case 'html':
				$raw = self::scalar( $settings['html'] ?? '' );
				if ( '' !== $raw ) {
					$out['text_blocks'][] = array( 'text' => self::plain_text( $raw ), 'element_id' => $id );
					self::parse_markup( $raw, $site_host, $id, $out );
				}
				break;

			case 'form': // Elementor Pro.
				$fields = $settings['form_fields'] ?? array();
				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( ! is_array( $field ) ) {
							continue;
						}
						$out['form_fields'][] = array(
							'label'      => self::plain_text( self::scalar( $field['field_label'] ?? '' ) ),
							'type'       => self::scalar( $field['field_type'] ?? ( $field['type'] ?? '' ) ),
							'element_id' => $id,
						);
					}
				}
				break;
		}
	}

	/**
	 * Collects an image (native or atomic) into the accumulator.
	 *
	 * @param string $id       Element id.
	 * @param array  $settings Element settings.
	 * @param array  $out      Accumulator (by reference).
	 */
	private static function collect_image( string $id, array $settings, array &$out ): void {
		$image = $settings['image'] ?? array();
		// Atomic e-image may carry { image: { $$type, value: { id, url } } }.
		if ( isset( $image['$$type'] ) ) {
			$image = is_array( $image['value'] ?? null ) ? $image['value'] : array();
		}
		if ( ! is_array( $image ) ) {
			return;
		}

		$attachment_id = (int) self::scalar( $image['id'] ?? 0 );
		$url           = (string) self::scalar( $image['url'] ?? '' );
		// Widget-level alt override wins; otherwise the media-library alt.
		$alt = (string) self::scalar( $image['alt'] ?? ( $settings['alt'] ?? '' ) );
		if ( '' === $alt && $attachment_id > 0 && function_exists( 'get_post_meta' ) ) {
			$meta_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$alt      = is_string( $meta_alt ) ? $meta_alt : '';
		}

		if ( $attachment_id > 0 || '' !== $url ) {
			$out['images'][] = array(
				'element_id'      => $id,
				'attachment_id'   => $attachment_id,
				'url'             => $url,
				'alt'             => trim( $alt ),
				'context_heading' => isset( $out['_current_heading'] ) ? (string) $out['_current_heading'] : '',
			);
		}
	}

	/**
	 * Records a text-color / effective-background pair for contrast checks.
	 *
	 * Best-effort: a `*_color__globals__` reference can't be resolved to a hex
	 * here without the kit, so it's skipped (reported inconclusive upstream).
	 *
	 * @param string      $id          Element id.
	 * @param array       $settings    Element settings.
	 * @param string[]    $color_keys  Candidate color setting keys, in priority order.
	 * @param string|null $ancestor_bg Nearest ancestor background hex.
	 * @param array       $out         Accumulator (by reference).
	 */
	private static function collect_text_color( string $id, array $settings, array $color_keys, ?string $ancestor_bg, array &$out ): void {
		$color     = '';
		$color_key = '';
		foreach ( $color_keys as $key ) {
			$val = self::scalar( $settings[ $key ] ?? '' );
			if ( '' !== $val && '#' === substr( $val, 0, 1 ) ) {
				$color     = $val;
				$color_key = $key;
				break;
			}
		}
		if ( '' === $color ) {
			return; // No literal color (default or globals ref) — leave to upstream as inconclusive.
		}

		$own_bg = self::resolve_bg( $settings );
		$bg     = ( null !== $own_bg ) ? $own_bg : $ancestor_bg;

		$out['text_style_contexts'][] = array(
			'element_id'        => $id,
			'color'             => $color,
			'color_key'         => $color_key,
			'background'        => $bg,
			'background_source' => ( null !== $own_bg ) ? 'element' : ( ( null !== $ancestor_bg ) ? 'ancestor' : 'none' ),
		);
	}

	/**
	 * Resolves an element's own literal background hex, if it sets one.
	 *
	 * @param array $settings Element settings.
	 * @return string|null
	 */
	private static function resolve_bg( array $settings ): ?string {
		foreach ( array( 'background_color', '_background_color' ) as $key ) {
			$val = self::scalar( $settings[ $key ] ?? '' );
			if ( '' !== $val && '#' === substr( $val, 0, 1 ) ) {
				return $val;
			}
		}
		return null;
	}

	/**
	 * Parses anchors and <img> tags out of raw markup (HTML / text-editor).
	 *
	 * @param string $html      Markup.
	 * @param string $site_host Site host.
	 * @param string $id        Owning element id.
	 * @param array  $out       Accumulator (by reference).
	 */
	private static function parse_markup( string $html, string $site_host, string $id, array &$out ): void {
		// Headings inside markup.
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $hm, PREG_SET_ORDER ) ) {
			foreach ( $hm as $m ) {
				$txt = self::plain_text( $m[2] );
				if ( '' !== $txt ) {
					$out['headings'][] = array( 'level' => (int) $m[1], 'text' => $txt, 'element_id' => $id );
				}
			}
		}
		// Anchors.
		if ( preg_match_all( '/<a\b[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $am, PREG_SET_ORDER ) ) {
			foreach ( $am as $m ) {
				self::maybe_link( $m[2], self::plain_text( $m[3] ), $site_host, $id, $out );
			}
		}
		// Images (alt may be absent → empty).
		if ( preg_match_all( '/<img\b[^>]*>/is', $html, $im, PREG_SET_ORDER ) ) {
			foreach ( $im as $m ) {
				$tag = $m[0];
				$alt = '';
				if ( preg_match( '/\balt=("|\')(.*?)\1/is', $tag, $a ) ) {
					$alt = self::plain_text( $a[2] );
				}
				$src = '';
				if ( preg_match( '/\bsrc=("|\')(.*?)\1/is', $tag, $s ) ) {
					$src = $s[2];
				}
				$out['images'][] = array(
					'element_id'    => $id,
					'attachment_id' => 0,
					'url'           => $src,
					'alt'           => $alt,
				);
			}
		}
	}

	/**
	 * Adds a link entry if the URL is non-empty.
	 *
	 * @param string $url       URL.
	 * @param string $text      Link text.
	 * @param string $site_host Site host.
	 * @param string $id        Owning element id.
	 * @param array  $out       Accumulator (by reference).
	 */
	private static function maybe_link( $url, string $text, string $site_host, string $id, array &$out ): void {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return;
		}
		$out['links'][] = array(
			'url'        => $url,
			'text'       => trim( $text ),
			'internal'   => self::is_internal( $url, $site_host ),
			'element_id' => $id,
		);
	}

	/**
	 * Classifies a URL as internal to the site.
	 *
	 * @param string $url       URL.
	 * @param string $site_host Site host (may be empty).
	 * @return bool
	 */
	private static function is_internal( string $url, string $site_host ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return false;
		}
		$first = $url[0];
		if ( '#' === $first || '/' === $first ) {
			return true; // Anchor or root-relative.
		}
		if ( preg_match( '/^(mailto:|tel:|sms:|javascript:)/i', $url ) ) {
			return false;
		}
		$host = parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return true; // Relative path without scheme.
		}
		if ( '' !== $site_host && 0 === strcasecmp( ltrim( $host, 'www.' ), ltrim( $site_host, 'www.' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Maps an Elementor header_size / tag to a numeric heading level, or null
	 * when the tag is not a heading (div, p, span, etc.).
	 *
	 * @param string $tag e.g. "h2", "div".
	 * @return int|null
	 */
	private static function header_size_to_level( string $tag ): ?int {
		$tag = strtolower( trim( $tag ) );
		if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
			return (int) $m[1];
		}
		return null;
	}

	/**
	 * Unwraps an atomic `{ $$type, value }` wrapper, returning a scalar string.
	 *
	 * @param mixed $value Raw setting value.
	 * @return string
	 */
	private static function scalar( $value ): string {
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'value', $value ) && array_key_exists( '$$type', $value ) ) {
				return is_scalar( $value['value'] ) ? (string) $value['value'] : '';
			}
			return '';
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Reads a nested key path from an array, unwrapping atomic wrappers.
	 *
	 * @param array    $arr  Source array.
	 * @param string[] $path Key path.
	 * @return mixed
	 */
	private static function deep( array $arr, array $path ) {
		$cur = $arr;
		foreach ( $path as $key ) {
			if ( is_array( $cur ) && array_key_exists( 'value', $cur ) && is_array( $cur['value'] ) ) {
				$cur = $cur['value'];
			}
			if ( ! is_array( $cur ) || ! array_key_exists( $key, $cur ) ) {
				return '';
			}
			$cur = $cur[ $key ];
		}
		return $cur;
	}

	/**
	 * Strips tags + decodes entities + collapses whitespace.
	 *
	 * @param string $html Input.
	 * @return string
	 */
	private static function plain_text( string $html ): string {
		// Drop <script>/<style> blocks (including their contents) first, so code
		// never leaks into extracted text.
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', ' ', $html );
		// Replace every remaining tag with a space so adjacent inline text stays
		// word-separated ("More than</span><span>coffee" → "More than coffee",
		// not "thancoffee") — plain strip_tags would merge them.
		$text = preg_replace( '/<[^>]+>/', ' ', (string) $text );
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		// Drop the space a tag boundary can leave before sentence punctuation
		// ("coffee ." → "coffee.").
		$text = preg_replace( '/\s+([.,;:!?])/u', '$1', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Counts words in a plain-text string.
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	private static function count_words( string $text ): int {
		$text = trim( $text );
		if ( '' === $text ) {
			return 0;
		}
		return count( preg_split( '/\s+/u', $text ) ?: array() );
	}
}
