<?php
/**
 * Atomic element props helper.
 *
 * Wraps and unwraps Elementor 4.0 typed prop values ($$type system).
 * MCP tools accept simple flat values from AI agents; this class converts
 * them to/from the $$type format that Elementor's atomic engine requires.
 *
 * @package EMCP_Tools
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helpers for building and reading atomic prop values.
 *
 * @since 1.5.0
 */
class EMCP_Tools_Atomic_Props {

	/**
	 * Elements Elementor treats as rich-text nodes inside an `html-v3` value.
	 *
	 * Read from Elementor's own INLINE_ELEMENTS set (editor-props, 4.2). Anything
	 * outside this list is descended through rather than recorded, which is why a
	 * `<div>` wrapper contributes its inline descendants but no node of its own.
	 *
	 * @since 3.13.2
	 * @var string[]
	 */
	const INLINE_TAGS = array( 'span', 'b', 'strong', 'i', 'em', 'u', 'a', 'del', 'sup', 'sub', 's' );

	/**
	 * Splits rich-text markup into the `content` / `children` pair `html-v3` stores.
	 *
	 * An `html-v3` value holds the same text twice: as markup in `content`, and as
	 * a node tree in `children` that the editor's rich-text control renders from.
	 * The two have to agree. Building only one of them leaves an element that
	 * renders correctly on the front end but opens empty in the editor, with
	 * nothing to signal the damage (#121).
	 *
	 * Both halves are produced from a single parse, and any id generated for a
	 * node is written back into the markup before it is re-serialized, so the
	 * pair is keyed to itself by construction rather than by two passes agreeing.
	 *
	 * Mirrors `parseHtmlChildren()` in Elementor's editor-props package.
	 *
	 * @since 3.13.2
	 * @param string $text Rich-text markup, possibly with no tags at all.
	 * @return array{content:string,children:array<int,array<string,mixed>>}
	 */
	public static function parse_html_children( string $text ): array {
		$plain = array(
			'content'  => $text,
			'children' => array(),
		);

		// Nothing to parse, and no reason to pay for a DOM round-trip.
		if ( '' === $text || false === strpos( $text, '<' ) ) {
			return $plain;
		}

		$root = self::load_fragment( $text );
		if ( null === $root ) {
			// Elementor keeps the original content on a parse failure rather than
			// discarding it; an unparseable string is still the author's text.
			return $plain;
		}

		$children = self::traverse_children( $root );

		return array(
			// Re-serialized AFTER traversal, so generated ids are present here too.
			'content'  => self::inner_html( $root ),
			'children' => $children,
		);
	}

	/**
	 * Parses a markup fragment and returns the wrapper element holding it.
	 *
	 * libxml decodes input as ISO-8859-1, so non-ASCII text would be mangled.
	 * Encoding it to numeric entities first and decoding on the way out is the
	 * portable fix; it works on the PHP 8.1 floor, where the spec-compliant
	 * `Dom\HTMLDocument` (8.4+) is not available.
	 *
	 * @since 3.13.2
	 * @param string $text Markup fragment.
	 * @return DOMElement|null Wrapper element, or null if it could not be parsed.
	 */
	private static function load_fragment( string $text ): ?DOMElement {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return null;
		}

		$encoded = mb_encode_numericentity( $text, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8' );

		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadHTML(
			'<div>' . $encoded . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $ok || ! $doc->documentElement instanceof DOMElement ) {
			return null;
		}
		return $doc->documentElement;
	}

	/**
	 * Serializes an element's children back to markup.
	 *
	 * @since 3.13.2
	 * @param DOMElement $root Wrapper element.
	 * @return string
	 */
	private static function inner_html( DOMElement $root ): string {
		$out = '';
		foreach ( $root->childNodes as $child ) {
			$html = $root->ownerDocument->saveHTML( $child );
			if ( is_string( $html ) ) {
				$out .= $html;
			}
		}
		return mb_decode_numericentity( $out, array( 0x80, 0x10FFFF, 0, 0x1FFFFF ), 'UTF-8' );
	}

	/**
	 * Walks the element tree and records the inline nodes.
	 *
	 * @since 3.13.2
	 * @param DOMElement $node Element whose children are walked.
	 * @return array<int,array<string,mixed>>
	 */
	private static function traverse_children( DOMElement $node ): array {
		$result = array();

		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$tag = strtolower( $child->tagName );

			// A non-inline wrapper contributes its inline descendants, not itself.
			if ( ! in_array( $tag, self::INLINE_TAGS, true ) ) {
				foreach ( self::traverse_children( $child ) as $nested ) {
					$result[] = $nested;
				}
				continue;
			}

			$id = $child->getAttribute( 'id' );
			if ( '' === $id ) {
				$id = self::generate_node_id();
				// Written into the DOM so the re-serialized content carries it.
				$child->setAttribute( 'id', $id );
			}

			$entry = array(
				'id'   => $id,
				'type' => $tag,
			);

			// The whole subtree's text, matching Elementor's use of textContent.
			$content = trim( (string) $child->textContent );
			if ( '' !== $content ) {
				$entry['content'] = $content;
			}

			$nested_children = self::traverse_children( $child );
			if ( $nested_children ) {
				$entry['children'] = $nested_children;
			}

			$result[] = $entry;
		}

		return $result;
	}

	/**
	 * A node id in the shape the editor generates: `e-{base36 time}-{7 chars}`.
	 *
	 * @since 3.13.2
	 * @return string
	 */
	private static function generate_node_id(): string {
		$stamp  = base_convert( (string) (int) round( microtime( true ) * 1000 ), 10, 36 );
		$random = substr( base_convert( bin2hex( random_bytes( 8 ) ), 16, 36 ), 0, 7 );
		return 'e-' . $stamp . '-' . str_pad( $random, 7, '0' );
	}

	/**
	 * Wraps a plain string into a typed prop.
	 *
	 * @param string $value The string value.
	 * @return array Typed prop: { $$type: "string", value: "..." }
	 */
	public static function string( string $value ): array {
		return array(
			'$$type' => 'string',
			'value'  => $value,
		);
	}

	/**
	 * Wraps a number into a typed prop.
	 *
	 * @param int|float $value The numeric value.
	 * @return array Typed prop: { $$type: "number", value: N }
	 */
	public static function number( $value ): array {
		return array(
			'$$type' => 'number',
			'value'  => $value,
		);
	}

	/**
	 * Wraps a boolean into a typed prop.
	 *
	 * @param bool $value The boolean value.
	 * @return array Typed prop: { $$type: "boolean", value: true|false }
	 */
	public static function boolean( bool $value ): array {
		return array(
			'$$type' => 'boolean',
			'value'  => $value,
		);
	}

	/**
	 * Wraps a size value (number + unit) into a typed prop.
	 *
	 * @param int|float $size The size number.
	 * @param string    $unit The CSS unit (px, em, rem, %, vw, vh).
	 * @return array Typed prop: { $$type: "size", value: { size, unit } }
	 */
	public static function size( $size, string $unit = 'px' ): array {
		return array(
			'$$type' => 'size',
			'value'  => array(
				'size' => $size,
				'unit' => $unit,
			),
		);
	}

	/**
	 * Parse a friendly size (number, "100%", or clamp()/var()) into { size, unit }.
	 *
	 * Casting every string through `(float)` used to turn `100%` into `100px`
	 * and `clamp(...)` into `0`. Callers must treat null as "unparseable".
	 *
	 * @since 3.14.0
	 *
	 * @param mixed  $value         Raw input.
	 * @param string $explicit_unit Optional unit from a sibling `*_unit` key.
	 * @return array{size:int|float|string,unit:string}|null
	 */
	public static function parse_size_input( $value, string $explicit_unit = '' ): ?array {
		if ( is_array( $value ) ) {
			if ( isset( $value['$$type'], $value['value'] ) && is_array( $value['value'] ) && isset( $value['value']['size'] ) ) {
				$unit = (string) ( $value['value']['unit'] ?? ( '' !== $explicit_unit ? $explicit_unit : 'px' ) );
				return array(
					'size' => $value['value']['size'],
					'unit' => $unit,
				);
			}
			if ( isset( $value['size'] ) ) {
				return array(
					'size' => $value['size'],
					'unit' => (string) ( $value['unit'] ?? ( '' !== $explicit_unit ? $explicit_unit : 'px' ) ),
				);
			}
			return null;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return array(
				'size' => $value,
				'unit' => '' !== $explicit_unit ? $explicit_unit : 'px',
			);
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		$raw = trim( $value );
		if ( '' === $raw ) {
			return null;
		}

		if ( 'custom' === $explicit_unit || preg_match( '/^(clamp|min|max|calc|var)\s*\(/i', $raw ) ) {
			return array(
				'size' => $raw,
				'unit' => 'custom',
			);
		}

		if ( preg_match( '/^([+-]?\d*\.?\d+)\s*(px|%|em|rem|vh|vw|vmin|vmax|ch|ex|cm|mm|in|pt|pc|svw|svh|lvw|lvh|dvw|dvh|fr)?$/i', $raw, $matches ) ) {
			$unit = ( isset( $matches[2] ) && '' !== $matches[2] )
				? $matches[2]
				: ( '' !== $explicit_unit ? $explicit_unit : 'px' );
			return array(
				'size' => (float) $matches[1],
				'unit' => $unit,
			);
		}

		if ( is_numeric( $raw ) ) {
			return array(
				'size' => (float) $raw,
				'unit' => '' !== $explicit_unit ? $explicit_unit : 'px',
			);
		}

		return null;
	}

	/**
	 * Wraps a color into a typed prop.
	 *
	 * Elementor's `color` style prop is a Color_Prop_Type, so it needs the
	 * `color` envelope, NOT `string`. A plain string envelope is rejected and
	 * the color is dropped.
	 *
	 * @since 3.6.2
	 *
	 * @param string $color A CSS color (hex/rgb/rgba/named).
	 * @return array Typed prop: { $$type: "color", value: "..." }
	 */
	public static function color( string $color ): array {
		return array(
			'$$type' => 'color',
			'value'  => $color,
		);
	}

	/**
	 * Builds the `background` style prop from a color.
	 *
	 * Elementor stores background as a Background_Prop_Type whose `color` field
	 * is a color prop. There is no `background-color` style prop, so writing one
	 * is silently discarded.
	 *
	 * @since 3.6.2
	 *
	 * @param string $color A CSS color.
	 * @return array Typed `background` prop.
	 */
	public static function background_color( string $color ): array {
		return array(
			'$$type' => 'background',
			'value'  => array(
				'color' => self::color( $color ),
			),
		);
	}

	/**
	 * Builds a `dimensions` prop (four sides) from typed size values.
	 *
	 * Used for the `padding`/`margin` shorthand style props, whose union accepts
	 * a dimensions shape keyed block-start / block-end / inline-start /
	 * inline-end. Elementor has no per-side `padding-block-start` style prop, so
	 * writing those individually is discarded.
	 *
	 * @since 3.6.2
	 *
	 * @param array $sides Map with any of block-start|block-end|inline-start|inline-end => size prop.
	 * @return array Typed prop: { $$type: "dimensions", value: {...} }
	 */
	public static function dimensions( array $sides ): array {
		return array(
			'$$type' => 'dimensions',
			'value'  => $sides,
		);
	}

	/**
	 * Wraps text content into an html-v3 typed prop.
	 *
	 * @param string $text Plain text content.
	 * @return array Typed prop with html-v3 structure.
	 */
	public static function html( string $text ): array {
		$parsed = self::parse_html_children( $text );

		return array(
			'$$type' => 'html-v3',
			'value'  => array(
				'content'  => self::string( $parsed['content'] ),
				'children' => $parsed['children'],
			),
		);
	}

	/**
	 * Wraps a URL into a typed prop.
	 *
	 * @param string $url The URL string.
	 * @return array Typed prop: { $$type: "url", value: "..." }
	 */
	public static function url( string $url ): array {
		return array(
			'$$type' => 'url',
			'value'  => $url,
		);
	}

	/**
	 * Builds a link prop from a URL string.
	 *
	 * @param string $url           The destination URL.
	 * @param bool   $target_blank  Whether to open in new tab.
	 * @return array Typed link prop.
	 */
	public static function link( string $url, bool $target_blank = false ): array {
		$link_value = array(
			'destination' => self::url( $url ),
			'tag'         => self::string( 'a' ),
		);

		if ( $target_blank ) {
			$link_value['isTargetBlank'] = self::boolean( true );
		}

		return array(
			'$$type' => 'link',
			'value'  => $link_value,
		);
	}

	/**
	 * Builds a classes prop from an array of class IDs.
	 *
	 * @param string[] $class_ids Array of class identifiers.
	 * @return array Typed classes prop.
	 */
	public static function classes( array $class_ids = array() ): array {
		return array(
			'$$type' => 'classes',
			'value'  => $class_ids,
		);
	}

	/**
	 * Wraps a WordPress media image reference for the atomic `e-image` widget.
	 *
	 * Elementor's Image_Src_Prop_Type enforces `id XOR url` — exactly one of the
	 * two may be set, the other MUST be null — and the id must be an
	 * `image-attachment-id`, not a plain `number`. Passing both (or a `number`
	 * id) makes Elementor reject the value with `image: invalid_value` (#74).
	 *
	 * Alt text belongs INSIDE this shape, as `src.alt`. There is no top-level
	 * `alt` prop on `e-image`, so writing one is silently discarded along with
	 * the text, the same trap as issue #102. For an attachment, Elementor
	 * ignores `src.alt` entirely and renders the media library's own alt text
	 * (`_wp_attachment_image_alt`), so the caller must write it there instead.
	 *
	 * @since 3.6.2 Accepts alt text.
	 *
	 * @param int    $image_id  The attachment ID (0 to use a url instead).
	 * @param string $image_url The image URL (used only when $image_id is 0).
	 * @param string $alt       Alt text; only reaches the markup for a url image.
	 * @return array Typed image prop.
	 */
	public static function image( int $image_id, string $image_url = '', string $alt = '' ): array {
		$src = self::image_src_value( $image_id, $image_url );

		if ( '' !== $alt ) {
			$src['alt'] = self::string( $alt );
		}

		return array(
			'$$type' => 'image',
			'value'  => array(
				'src' => array(
					'$$type' => 'image-src',
					'value'  => $src,
				),
			),
		);
	}

	/**
	 * Wraps a video reference for the atomic `e-self-hosted-video` widget.
	 *
	 * Its `source` prop is a `video-src` SHAPE (`id` XOR `url`), not the plain
	 * `url` that `e-youtube`'s `source` takes. Passing a bare url envelope made
	 * Elementor reject the element outright with `source: invalid_value`, so
	 * add-atomic-video could not place a video at all on Elementor 4.2.
	 *
	 * Video_Src_Prop_Type requires EXACTLY ONE of the two keys to be non-empty,
	 * so the unused one is omitted rather than sent as null.
	 *
	 * @since 3.6.2
	 *
	 * @param int    $video_id  The attachment ID (0 to use a url instead).
	 * @param string $video_url The video URL (used only when $video_id is 0).
	 * @return array Typed video-src prop.
	 */
	public static function video_src( int $video_id, string $video_url = '' ): array {
		$value = $video_id > 0
			? array(
				'id' => array(
					'$$type' => 'video-attachment-id',
					'value'  => $video_id,
				),
			)
			: array( 'url' => self::url( $video_url ) );

		return array(
			'$$type' => 'video-src',
			'value'  => $value,
		);
	}

	/**
	 * Wraps a WordPress media SVG reference for the atomic `e-svg` widget.
	 *
	 * The `e-svg` widget's `svg` prop is a distinct `svg-src` type — NOT the
	 * `image`/`image-src` type used by `e-image` — so it must not be built with
	 * image() (#74). Its shape mirrors image-src (id/url) with at least one set.
	 *
	 * @param int    $svg_id  The attachment ID (0 to use a url instead).
	 * @param string $svg_url The SVG URL (used only when $svg_id is 0).
	 * @return array Typed svg-src prop.
	 */
	public static function svg( int $svg_id, string $svg_url = '' ): array {
		return array(
			'$$type' => 'svg-src',
			'value'  => self::image_src_value( $svg_id, $svg_url ),
		);
	}

	/**
	 * Builds the inner id/url value shared by image-src and svg-src: an
	 * `image-attachment-id` when an id is given (url null), otherwise a `url`
	 * (id null).
	 *
	 * @param int    $id  The attachment ID (0 to use a url).
	 * @param string $url The URL (used only when $id is 0).
	 * @return array{id:?array,url:?array}
	 */
	private static function image_src_value( int $id, string $url ): array {
		if ( $id > 0 ) {
			return array(
				'id'  => array(
					'$$type' => 'image-attachment-id',
					'value'  => $id,
				),
				'url' => null,
			);
		}

		return array(
			'id'  => null,
			'url' => '' === $url ? null : self::url( $url ),
		);
	}

	/**
	 * Returns an atomic widget's prop schema, or an empty array when Elementor
	 * or the widget type isn't available.
	 *
	 * Cached per widget type: a page-wide coercion pass asks for the same handful
	 * of schemas hundreds of times.
	 *
	 * @since 3.6.1
	 *
	 * @param string $widget_type Atomic widget type, e.g. 'e-heading'.
	 * @return array<string, object>
	 */
	public static function props_schema( string $widget_type ): array {
		static $cache = array();

		if ( array_key_exists( $widget_type, $cache ) ) {
			return $cache[ $widget_type ];
		}

		$cache[ $widget_type ] = self::load_props_schema( $widget_type );

		return $cache[ $widget_type ];
	}

	/**
	 * Uncached schema lookup.
	 *
	 * @since 3.6.2
	 *
	 * @param string $widget_type Atomic widget type.
	 * @return array<string, object>
	 */
	private static function load_props_schema( string $widget_type ): array {
		if ( '' === $widget_type || ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}

		$manager = \Elementor\Plugin::$instance->widgets_manager ?? null;
		if ( ! $manager || ! method_exists( $manager, 'get_widget_types' ) ) {
			return array();
		}

		try {
			$widget = $manager->get_widget_types( $widget_type );
		} catch ( \Throwable $e ) {
			return array();
		}

		if ( ! $widget || ! method_exists( $widget, 'get_props_schema' ) ) {
			return array();
		}

		try {
			return (array) $widget::get_props_schema();
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/**
	 * Coerces atomic settings into the `$$type` envelopes Elementor expects.
	 *
	 * Atomic props are typed: `tag` wants `{$$type:'string'}`, `title` wants
	 * `{$$type:'html-v3'}`, and so on. A raw value like `'Hello'` is rejected.
	 * The trouble is that raw values were still *written* to `_elementor_data`,
	 * where they do lasting damage: Elementor falls back to the prop default, so
	 * the element renders placeholder text, and every later save of that page
	 * throws `Settings validation failed`. The page becomes uneditable through
	 * both the API and the editor (issue #101).
	 *
	 * Passing a plain string is the obvious thing for an agent to do, so accept
	 * it and wrap it rather than corrupting the page. Because this runs on the
	 * MERGED settings, it also repairs values a previous version already wrote.
	 *
	 * Elementor's own prop types are the oracle: candidate envelopes are offered
	 * to `validate()` and the first accepted one wins. Nothing here hardcodes
	 * which type a prop wants, so it keeps working when Elementor revises them
	 * (`html` -> `html-v2` -> `html-v3` already happened).
	 *
	 * @since 3.6.1
	 *
	 * @param string $widget_type Atomic widget type, e.g. 'e-heading'.
	 * @param array  $settings    Settings to coerce.
	 * @return array
	 */
	public static function coerce_settings( string $widget_type, array $settings ): array {
		return self::coerce_with_schema( self::props_schema( $widget_type ), $settings );
	}

	/**
	 * The coercion itself, against a supplied prop schema.
	 *
	 * Split out from coerce_settings() so it can be exercised without a live
	 * Elementor: it needs only objects exposing `validate()`.
	 *
	 * @since 3.6.1
	 *
	 * @param array $schema   Map of prop name => Elementor prop type.
	 * @param array $settings Settings to coerce.
	 * @return array
	 */
	public static function coerce_with_schema( array $schema, array $settings ): array {
		if ( empty( $schema ) ) {
			return $settings;
		}

		$settings = self::apply_prop_aliases( $schema, $settings );

		foreach ( $settings as $key => $value ) {
			$prop = $schema[ $key ] ?? null;
			if ( ! is_object( $prop ) || ! method_exists( $prop, 'validate' ) ) {
				continue;
			}

			$settings[ $key ] = self::coerce_against_prop( $prop, $value );
		}

		return $settings;
	}

	/**
	 * Renames alias keys onto the prop name Elementor actually stores.
	 *
	 * Elementor's atomic widgets declare alternative names for their content
	 * prop, e.g. `e-paragraph`'s `paragraph` accepts `text` and `content`, and
	 * `e-heading`'s `title` accepts `text`, `content` and `heading`. Those names
	 * are the obvious guesses, and an agent writing `content` on a paragraph is
	 * guessing exactly what Elementor itself advertises.
	 *
	 * Nothing on the PHP save path consumes those aliases. Worse, Elementor's
	 * Props_Parser SILENTLY DISCARDS keys it does not recognise and still
	 * reports the result as valid, so an aliased key is not rejected, it is
	 * deleted along with its text. That only became visible once saves started
	 * succeeding again: before, an unrelated invalid prop blocked the whole
	 * save, so the bad key survived in the database untouched (issue #102).
	 *
	 * Renaming here, before Elementor sees the settings, preserves the content.
	 * The alias list is read from Elementor's own prop metadata rather than
	 * hardcoded, so widgets that gain aliases later are covered automatically.
	 *
	 * A canonical value already present always wins; an alias never overwrites
	 * it, since the canonical key is what Elementor will actually render.
	 *
	 * @since 3.6.2
	 *
	 * @param array $schema   Map of prop name => Elementor prop type.
	 * @param array $settings Settings to rewrite.
	 * @return array
	 */
	private static function apply_prop_aliases( array $schema, array $settings ): array {
		foreach ( $schema as $canonical => $prop ) {
			if ( ! is_object( $prop ) || ! method_exists( $prop, 'get_meta_item' ) ) {
				continue;
			}

			// Already supplied under its real name: nothing to recover.
			if ( array_key_exists( $canonical, $settings ) ) {
				continue;
			}

			$aliases = $prop->get_meta_item( 'aliases' );
			if ( ! is_array( $aliases ) ) {
				continue;
			}

			foreach ( $aliases as $alias ) {
				if ( ! is_string( $alias ) || ! array_key_exists( $alias, $settings ) ) {
					continue;
				}

				// An alias that is itself a real prop belongs to that prop.
				if ( isset( $schema[ $alias ] ) ) {
					continue;
				}

				$settings[ $canonical ] = $settings[ $alias ];
				unset( $settings[ $alias ] );
				break;
			}
		}

		return $settings;
	}

	/**
	 * Coerces every atomic widget in an element tree.
	 *
	 * Elementor validates the WHOLE tree on save, so a single un-converted
	 * widget anywhere on the page blocks the save, including the very edit
	 * meant to repair the page. Fixing only the element being written left
	 * users stuck on a page they could not unstick (issue #102).
	 *
	 * Runs on save so any write repairs the entire page. It only ever turns
	 * invalid values into valid ones: anything Elementor already accepts is
	 * returned untouched, and anything nothing fits is left alone.
	 *
	 * @since 3.6.2
	 *
	 * @param array $elements Element tree (Elementor's `elements` array).
	 * @return array
	 */
	public static function coerce_tree( array $elements ): array {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if (
				'widget' === ( $element['elType'] ?? '' )
				&& ! empty( $element['widgetType'] )
				&& ! empty( $element['settings'] )
				&& is_array( $element['settings'] )
			) {
				$element['settings'] = self::coerce_settings(
					(string) $element['widgetType'],
					$element['settings']
				);
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::coerce_tree( $element['elements'] );
			}
		}
		unset( $element );

		return $elements;
	}

	/**
	 * Coerces one value into whatever its prop type accepts.
	 *
	 * The prop type describes itself, so nothing here hardcodes envelope names.
	 * A union is asked for its members (`get_prop_types()`), each member for its
	 * key (`get_key()`), and an object-shaped member for its inner shape
	 * (`get_shape()`), which is then coerced recursively.
	 *
	 * That generality is the point. The first version of this guessed candidate
	 * envelopes and handled only strings and rich text, so `e-button`'s `link`
	 * still failed and the page stayed locked (issue #102). Reading the shape
	 * instead covers link, and whatever object-shaped prop Elementor adds next.
	 *
	 * @since 3.6.2
	 *
	 * @param object $prop  An Elementor prop type.
	 * @param mixed  $value The value to coerce.
	 * @return mixed The coerced value, or the original when nothing fits.
	 */
	protected static function coerce_against_prop( $prop, $value ) {
		if ( self::prop_accepts( $prop, $value ) ) {
			return $value;
		}

		foreach ( self::candidates_for( $prop, $value ) as $candidate ) {
			if ( self::prop_accepts( $prop, $candidate ) ) {
				return $candidate;
			}
		}

		// Nothing fits. Leave it alone so Elementor reports a precise error
		// rather than us silently storing something reshaped.
		return $value;
	}

	/**
	 * Builds candidate envelopes for a value from the prop type's own members.
	 *
	 * @since 3.6.2
	 *
	 * @param object $prop  An Elementor prop type.
	 * @param mixed  $value The raw value.
	 * @return array<int, mixed>
	 */
	protected static function candidates_for( $prop, $value ): array {
		$members = array( $prop );
		if ( method_exists( $prop, 'get_prop_types' ) ) {
			try {
				$found = (array) $prop->get_prop_types();
				if ( ! empty( $found ) ) {
					$members = array_values( $found );
				}
			} catch ( \Throwable $e ) {
				$members = array( $prop );
			}
		}

		$candidates = array();

		foreach ( $members as $member ) {
			if ( ! is_object( $member ) || ! method_exists( $member, 'get_key' ) ) {
				continue;
			}

			try {
				$key = $member->get_key();
			} catch ( \Throwable $e ) {
				continue;
			}

			// Object-shaped: coerce the inner shape, then wrap. A prop type can
			// expose get_shape() and still return nothing useful, so fall through
			// to the primitive handling below rather than dropping the candidate.
			if ( method_exists( $member, 'get_shape' ) ) {
				$inner = self::coerce_shape( $member, $value );
				if ( null !== $inner ) {
					$candidates[] = array(
						'$$type' => $key,
						'value'  => $inner,
					);
					continue;
				}
			}

			// Primitive: wrap the value, and offer a cast where it is lossless.
			if ( is_scalar( $value ) || null === $value ) {
				$candidates[] = array(
					'$$type' => $key,
					'value'  => $value,
				);
				if ( is_scalar( $value ) ) {
					$candidates[] = array(
						'$$type' => $key,
						'value'  => (string) $value,
					);
				}
			}
		}

		// Fallbacks for prop types that do not describe themselves. Everything
		// Elementor ships exposes get_key(), but a prop type offering only
		// validate() would otherwise yield no candidates at all, making this
		// weaker than the version it replaced. These are the common envelopes.
		if ( is_scalar( $value ) ) {
			$text = (string) $value;
			if ( is_bool( $value ) ) {
				$candidates[] = self::boolean( $value );
			}
			if ( is_int( $value ) || is_float( $value ) ) {
				$candidates[] = self::number( $value );
			}
			$candidates[] = self::string( $text );
			$candidates[] = self::html( $text );
		} elseif ( is_array( $value ) && ! isset( $value['$$type'] ) ) {
			$inner = $value;
			if ( isset( $inner['content'] ) && is_string( $inner['content'] ) ) {
				// Same rule as the plain-string branch: children are derived from
				// the markup, never left empty beside markup that has tags in it.
				// A caller that supplies its own tree keeps it.
				$parsed           = self::parse_html_children( $inner['content'] );
				$inner['content'] = self::string( $parsed['content'] );
				if ( ! isset( $inner['children'] ) || ! is_array( $inner['children'] ) ) {
					$inner['children'] = $parsed['children'];
				}
			}
			if ( ! isset( $inner['children'] ) || ! is_array( $inner['children'] ) ) {
				$inner['children'] = array();
			}
			$candidates[] = array(
				'$$type' => 'html-v3',
				'value'  => $inner,
			);
		}

		return $candidates;
	}

	/**
	 * Coerces a value into an object prop type's inner shape.
	 *
	 * Handles the two ways a legacy value arrives: as an array using Elementor's
	 * older key names (a v3 link stores `url` / `is_external`, while the atomic
	 * shape wants `destination` / `isTargetBlank`), or as a bare scalar that
	 * belongs in the shape's principal field (a plain string for rich text
	 * belongs in `content`).
	 *
	 * @since 3.6.2
	 *
	 * @param object $member An object-shaped Elementor prop type.
	 * @param mixed  $value  The raw value.
	 * @return array|null The coerced inner shape, or null when it cannot apply.
	 */
	protected static function coerce_shape( $member, $value ): ?array {
		try {
			$shape = (array) $member->get_shape();
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( empty( $shape ) ) {
			return null;
		}

		// Legacy and shorthand key names, mapped onto the atomic shape.
		$aliases = array(
			'destination'   => array( 'url', 'href', 'link' ),
			'isTargetBlank' => array( 'is_external', 'target_blank', 'targetBlank' ),
			'content'       => array( 'text', 'title', 'html' ),
		);

		// A bare scalar belongs in the shape's principal field.
		if ( ! is_array( $value ) ) {
			foreach ( array( 'content', 'destination', 'url', 'value' ) as $primary ) {
				if ( isset( $shape[ $primary ] ) ) {
					$value = array( $primary => $value );
					break;
				}
			}

			if ( ! is_array( $value ) ) {
				return null;
			}
		}

		$out = array();

		foreach ( $shape as $field => $sub ) {
			$raw = null;

			if ( array_key_exists( $field, $value ) ) {
				$raw = $value[ $field ];
			} else {
				foreach ( $aliases[ $field ] ?? array() as $alias ) {
					if ( array_key_exists( $alias, $value ) ) {
						$raw = $value[ $alias ];
						break;
					}
				}
			}

			if ( null === $raw ) {
				continue;
			}

			$out[ $field ] = is_object( $sub ) && method_exists( $sub, 'validate' )
				? self::coerce_against_prop( $sub, $raw )
				: $raw;
		}

		// `children` is a plain array on rich text, not a wrapped prop. Derive it
		// from whatever content we just coerced rather than defaulting to empty,
		// which would leave the pair disagreeing whenever the text has tags.
		if ( isset( $shape['children'] ) && ! isset( $out['children'] ) ) {
			$content = $out['content']['value'] ?? ( is_string( $out['content'] ?? null ) ? $out['content'] : '' );
			$out['children'] = is_string( $content ) && '' !== $content
				? self::parse_html_children( $content )['children']
				: array();
		}

		return empty( $out ) ? null : $out;
	}

	/**
	 * Whether a prop type accepts a value. Prop types can throw on odd input,
	 * which counts as "no".
	 *
	 * @since 3.6.1
	 *
	 * @param object $prop  An Elementor prop type.
	 * @param mixed  $value Candidate value.
	 * @return bool
	 */
	protected static function prop_accepts( $prop, $value ): bool {
		try {
			return (bool) $prop->validate( $value );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Unwraps a $$type-wrapped prop into a plain PHP value.
	 *
	 * Used for returning AI-friendly data from get-element-settings.
	 *
	 * @param mixed $prop The prop value (may or may not be $$type-wrapped).
	 * @return mixed The unwrapped plain value.
	 */
	public static function unwrap( $prop ) {
		if ( ! is_array( $prop ) ) {
			return $prop;
		}

		if ( isset( $prop['$$type'] ) ) {
			$type  = $prop['$$type'];
			$value = $prop['value'] ?? null;

			switch ( $type ) {
				case 'string':
				case 'number':
				case 'boolean':
				case 'url':
					return $value;

				case 'size':
					return is_array( $value )
						? ( $value['size'] ?? 0 ) . ( $value['unit'] ?? 'px' )
						: $value;

				case 'html-v3':
					if ( is_array( $value ) && isset( $value['content'] ) ) {
						return self::unwrap( $value['content'] );
					}
					return $value;

				case 'link':
					if ( is_array( $value ) && isset( $value['destination'] ) ) {
						return self::unwrap( $value['destination'] );
					}
					return $value;

				case 'classes':
					return is_array( $value ) ? $value : array();

				case 'image':
					if ( is_array( $value ) && isset( $value['src'] ) && is_array( $value['src'] ) ) {
						// src is wrapped as {$$type:image-src, value:{id,url}}; an
						// older/bare {id,url} shape is tolerated for round-trips.
						$src = isset( $value['src']['$$type'], $value['src']['value'] ) && is_array( $value['src']['value'] )
							? $value['src']['value']
							: $value['src'];
						return array(
							'id'  => self::unwrap( $src['id'] ?? 0 ),
							'url' => self::unwrap( $src['url'] ?? '' ),
						);
					}
					return $value;

				case 'svg-src':
					if ( is_array( $value ) ) {
						return array(
							'id'  => self::unwrap( $value['id'] ?? 0 ),
							'url' => self::unwrap( $value['url'] ?? '' ),
						);
					}
					return $value;

				case 'image-attachment-id':
					return $value;

				default:
					return is_array( $value ) ? self::unwrap_array( $value ) : $value;
			}
		}

		return self::unwrap_array( $prop );
	}

	/**
	 * Unwraps all values in an array recursively.
	 *
	 * @param array $arr The array to unwrap.
	 * @return array Unwrapped array.
	 */
	private static function unwrap_array( array $arr ): array {
		$result = array();
		foreach ( $arr as $key => $value ) {
			$result[ $key ] = self::unwrap( $value );
		}
		return $result;
	}

	/**
	 * Checks whether Elementor atomic (V4) elements are available **and will
	 * persist**.
	 *
	 * Detection is not version-number based. Elementor ships atomic/V4 as opt-in
	 * experiments while the core `ELEMENTOR_VERSION` constant still reports a 3.x
	 * value, so `version_compare( ELEMENTOR_VERSION, '4.0.0', '>=' )` is false on
	 * exactly the sites running atomic.
	 *
	 * Crucially, we gate on whether the atomic **element types are registered**
	 * (`e-flexbox` / `e-div-block`), not merely whether the V4 *page* editor is
	 * opted in. Those are separate experiments: a site can have `e_opt_in_v4_page`
	 * active while `e_atomic_elements` is OFF — atomic tools would register, but
	 * `Elementor\Document::save()` then silently sanitizes the unknown elements
	 * away (the write returns success yet `_elementor_data` stays empty). Keying
	 * on element-type registration means the atomic tools appear only when an
	 * atomic write will actually persist. Verified live on Elementor 3.31.5.
	 *
	 * @return bool True if atomic element types are registered/available.
	 */
	public static function is_atomic_supported(): bool {
		if ( class_exists( '\Elementor\Plugin' ) && method_exists( '\Elementor\Plugin', 'instance' ) ) {
			$elementor = \Elementor\Plugin::instance();

			// Primary, authoritative signal: the atomic element types are
			// registered server-side, so Document::save() will keep them.
			if ( isset( $elementor->elements_manager ) && is_object( $elementor->elements_manager )
				&& method_exists( $elementor->elements_manager, 'get_element_types' ) ) {
				$types = $elementor->elements_manager->get_element_types();
				if ( is_array( $types ) && ( isset( $types['e-flexbox'] ) || isset( $types['e-div-block'] ) ) ) {
					return true;
				}
			}

			// Secondary: the experiments that register the atomic element types.
			// (Deliberately NOT e_opt_in_v4_page / editor_v4 — those opt the page
			// editor into V4 without guaranteeing element registration, which is
			// the silent-no-op trap above.) method_exists-guarded so we never
			// fatal on builds/stubs without the experiments API.
			if ( isset( $elementor->experiments ) && is_object( $elementor->experiments )
				&& method_exists( $elementor->experiments, 'is_feature_active' ) ) {
				foreach ( array( 'e_atomic_elements', 'atomic_widgets' ) as $feature ) {
					if ( $elementor->experiments->is_feature_active( $feature ) ) {
						return true;
					}
				}
			}
		}

		// NB: do NOT use class_exists( '\Elementor\Modules\AtomicWidgets\Module' )
		// as a signal — that class is autoloaded even when the atomic experiment
		// is OFF and no atomic element types are registered, which would make the
		// tools register while writes silently get dropped on save.

		// Genuine 4.0+ core (kept as a forward-compatible fallback).
		return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '4.0.0', '>=' );
	}

	/**
	 * Whether Elementor's (legacy, 3.x) Flexbox **Container** is available on this
	 * site — i.e. a `container` elType will actually instantiate and render.
	 *
	 * Long-lived installs (pre-Container, ~2020) can have the Container experiment
	 * OFF (`container: false`). There, our creation tools (`add-container`,
	 * `build-page`) emit `container` elements Elementor cannot instantiate, so
	 * Elements_Manager silently skips every top-level element and the page renders
	 * an empty wrapper — no data change fixes it, only enabling the experiment (#111).
	 *
	 * Authoritative signal (mirrors is_atomic_supported): the `container` element
	 * type is registered server-side, so Document::save() keeps it. Falls back to
	 * the experiment flag, then — only when Elementor is not loaded at all (test
	 * stubs) — to true so non-WP unit paths keep their current behaviour.
	 *
	 * @since 3.12.0
	 *
	 * @return bool True if the `container` element type will render on this site.
	 */
	public static function is_container_supported(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! method_exists( '\Elementor\Plugin', 'instance' ) ) {
			// Elementor absent (e.g. unit stubs): don't block — callers that need
			// Elementor already guard on it elsewhere.
			return true;
		}

		$elementor = \Elementor\Plugin::instance();

		// Primary, authoritative signal: the `container` element type is registered.
		if ( isset( $elementor->elements_manager ) && is_object( $elementor->elements_manager )
			&& method_exists( $elementor->elements_manager, 'get_element_types' ) ) {
			$types = $elementor->elements_manager->get_element_types();
			if ( is_array( $types ) ) {
				return isset( $types['container'] );
			}
		}

		// Secondary: the Container experiment flag.
		if ( isset( $elementor->experiments ) && is_object( $elementor->experiments )
			&& method_exists( $elementor->experiments, 'is_feature_active' ) ) {
			return (bool) $elementor->experiments->is_feature_active( 'container' );
		}

		// Could not determine (unusual): keep current behaviour.
		return true;
	}
}
