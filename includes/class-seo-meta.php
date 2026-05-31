<?php
/**
 * SEO-plugin meta abstraction.
 *
 * Reads a post's SEO title / meta description / canonical / focus keyword from
 * whichever SEO plugin the site uses (Yoast or Rank Math), falling back to
 * WordPress core, so the SEO audit reports against what the site actually
 * outputs instead of hard-coupling to one plugin.
 *
 * @package Elementor_MCP
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves SEO meta across Yoast / Rank Math / core.
 *
 * @since 1.8.0
 */
final class Elementor_MCP_Seo_Meta {

	/**
	 * Returns the resolved SEO meta for a post.
	 *
	 * @since 1.8.0
	 *
	 * @param int $post_id The post ID.
	 * @return array {
	 *     @type string $source         'yoast' | 'rankmath' | 'core'
	 *     @type string $title          Resolved SEO title (may contain plugin template vars).
	 *     @type string $description    Resolved meta description.
	 *     @type string $canonical      Canonical URL (empty if not explicitly set).
	 *     @type string $focus_keyword  Focus keyword, if the SEO plugin stores one.
	 *     @type bool   $title_is_template Whether the title still contains %%...%% / {{...}} tokens.
	 * }
	 */
	public static function get( int $post_id ): array {
		$yoast = self::read_yoast( $post_id );
		if ( null !== $yoast ) {
			return self::finalize( $yoast, 'yoast', $post_id );
		}

		$rankmath = self::read_rankmath( $post_id );
		if ( null !== $rankmath ) {
			return self::finalize( $rankmath, 'rankmath', $post_id );
		}

		return self::finalize( array(), 'core', $post_id );
	}

	/**
	 * Writes an SEO title + meta description to the active SEO plugin's meta
	 * keys (Yoast or Rank Math). Core-only sites have no standard meta-description
	 * store, so nothing is written and `source` is 'none'.
	 *
	 * @since 1.8.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $title       SEO title ('' to skip).
	 * @param string $description Meta description ('' to skip).
	 * @return array { @type bool $written, @type string $source, @type string[] $fields }
	 */
	public static function write( int $post_id, string $title, string $description ): array {
		$result = array( 'written' => false, 'source' => 'none', 'fields' => array() );
		if ( ! function_exists( 'update_post_meta' ) ) {
			return $result;
		}

		$is_yoast    = defined( 'WPSEO_VERSION' ) || '' !== self::meta( $post_id, '_yoast_wpseo_title' ) || '' !== self::meta( $post_id, '_yoast_wpseo_metadesc' );
		$is_rankmath = defined( 'RANK_MATH_VERSION' ) || '' !== self::meta( $post_id, 'rank_math_title' ) || '' !== self::meta( $post_id, 'rank_math_description' );

		if ( $is_yoast ) {
			$result['source'] = 'yoast';
			$title_key        = '_yoast_wpseo_title';
			$desc_key         = '_yoast_wpseo_metadesc';
		} elseif ( $is_rankmath ) {
			$result['source'] = 'rankmath';
			$title_key        = 'rank_math_title';
			$desc_key         = 'rank_math_description';
		} else {
			return $result; // No SEO plugin to persist into.
		}

		if ( '' !== $title ) {
			update_post_meta( $post_id, $title_key, $title );
			$result['fields'][] = $title_key;
		}
		if ( '' !== $description ) {
			update_post_meta( $post_id, $desc_key, $description );
			$result['fields'][] = $desc_key;
		}
		$result['written'] = ! empty( $result['fields'] );
		return $result;
	}

	/**
	 * Reads Yoast meta, or null if Yoast isn't the active/source-of-truth plugin.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private static function read_yoast( int $post_id ): ?array {
		$active = defined( 'WPSEO_VERSION' );
		$title  = self::meta( $post_id, '_yoast_wpseo_title' );
		$desc   = self::meta( $post_id, '_yoast_wpseo_metadesc' );
		$canon  = self::meta( $post_id, '_yoast_wpseo_canonical' );
		$focus  = self::meta( $post_id, '_yoast_wpseo_focuskw' );

		if ( ! $active && '' === $title && '' === $desc && '' === $canon && '' === $focus ) {
			return null;
		}

		return array(
			'title'         => $title,
			'description'   => $desc,
			'canonical'     => $canon,
			'focus_keyword' => $focus,
		);
	}

	/**
	 * Reads Rank Math meta, or null if Rank Math isn't the active/source-of-truth plugin.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private static function read_rankmath( int $post_id ): ?array {
		$active = defined( 'RANK_MATH_VERSION' );
		$title  = self::meta( $post_id, 'rank_math_title' );
		$desc   = self::meta( $post_id, 'rank_math_description' );
		$canon  = self::meta( $post_id, 'rank_math_canonical_url' );
		$focus  = self::meta( $post_id, 'rank_math_focus_keyword' );

		if ( ! $active && '' === $title && '' === $desc && '' === $canon && '' === $focus ) {
			return null;
		}

		return array(
			'title'         => $title,
			'description'   => $desc,
			'canonical'     => $canon,
			'focus_keyword' => $focus,
		);
	}

	/**
	 * Fills gaps from WordPress core and normalizes the return shape.
	 *
	 * @param array  $data    Partial data from an SEO plugin (may be empty).
	 * @param string $source  Source label.
	 * @param int    $post_id Post ID.
	 * @return array
	 */
	private static function finalize( array $data, string $source, int $post_id ): array {
		$title = isset( $data['title'] ) ? (string) $data['title'] : '';
		$desc  = isset( $data['description'] ) ? (string) $data['description'] : '';
		$canon = isset( $data['canonical'] ) ? (string) $data['canonical'] : '';
		$focus = isset( $data['focus_keyword'] ) ? (string) $data['focus_keyword'] : '';

		// Core fallbacks for empty fields.
		if ( '' === $title && function_exists( 'get_the_title' ) ) {
			$title = (string) get_the_title( $post_id );
		}
		if ( '' === $desc && function_exists( 'get_post_field' ) ) {
			$excerpt = get_post_field( 'post_excerpt', $post_id );
			$desc    = is_string( $excerpt ) ? $excerpt : '';
		}
		if ( '' === $canon && function_exists( 'get_permalink' ) ) {
			$permalink = get_permalink( $post_id );
			$canon     = is_string( $permalink ) ? $permalink : '';
		}

		$title = trim( $title );
		$desc  = trim( $desc );

		return array(
			'source'            => $source,
			'title'             => $title,
			'description'       => $desc,
			'canonical'         => trim( $canon ),
			'focus_keyword'     => trim( $focus ),
			'title_is_template' => self::looks_templated( $title ) || self::looks_templated( $desc ),
		);
	}

	/**
	 * Reads a single post-meta string, guarded for the unit-test environment.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private static function meta( int $post_id, string $key ): string {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$val = get_post_meta( $post_id, $key, true );
		return is_string( $val ) ? trim( $val ) : '';
	}

	/**
	 * Whether a string still contains SEO-plugin template tokens (so length
	 * checks against it are advisory, not literal).
	 *
	 * @param string $str Candidate string.
	 * @return bool
	 */
	private static function looks_templated( string $str ): bool {
		return (bool) preg_match( '/%%[^%]+%%|\{\{[^}]+\}\}/', $str );
	}
}
