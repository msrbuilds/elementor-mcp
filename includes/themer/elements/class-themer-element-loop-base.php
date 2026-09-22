<?php
/**
 * Shared behaviour of the Loop Grid and Loop Carousel elements.
 *
 * Both elements: validate the Loop Item, run the query, render the items,
 * and print a signed config the REST route replays for later pages. This
 * base holds all of that so the two elements differ only in markup.
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
abstract class EMCP_Tools_Themer_Element_Loop_Base extends EMCP_Tools_Themer_Element_Base {

	const PAGINATION   = array( 'none', 'numbers', 'prev_next', 'numbers_prev_next', 'load_more', 'infinite' );
	const APPEND_MODES = array( 'load_more', 'infinite' );

	/**
	 * A hard ceiling on the requested page, applied before it can reach any
	 * offset arithmetic. A page number this large is never legitimate; the
	 * real ceiling (the query's own available pages, and page_limit) is
	 * applied once the query has run.
	 */
	const MAX_PAGE = 1000000;

	/** @var array<string,int> Occurrence counter per "scope|local_id". */
	private static $instances = array();

	/** Test seam. */
	public static function reset_instances_for_tests(): void {
		self::$instances = array();
	}

	/**
	 * A per-instance id, unique on the page and stable across loads.
	 *
	 * local id (element id, anchor, or attribute hash) + a hash of the loop
	 * scope (so a grid nested in a repeated card differs per card) + an
	 * occurrence counter per scope (so two identical blocks differ).
	 *
	 * A caller that can supply a genuinely stable id (an element id or an
	 * anchor) should pass it as $local_id. This hash is only the fallback
	 * for a caller that cannot: it is built from identity alone (which
	 * template, what it queries, which tag it renders as), never from
	 * cosmetic settings, so changing a gap or a label does not change the
	 * uid and therefore does not break a bookmarked or indexed pagination
	 * URL built from it.
	 *
	 * @param string $local_id Caller-provided id ('' = hash the identity args).
	 * @param array  $args     Element args (only template_id/query/tag are hashed).
	 * @return string
	 */
	public static function instance_uid( string $local_id, array $args ): string {
		$local_id = sanitize_key( $local_id );
		if ( '' === $local_id ) {
			$identity = EMCP_Tools_Themer_Loop_Config::sort_recursive(
				array(
					'template_id' => $args['template_id'] ?? 0,
					'query'       => is_array( $args['query'] ?? null ) ? $args['query'] : array(),
					'tag'         => $args['tag'] ?? 'div',
				)
			);
			$local_id = substr( md5( (string) wp_json_encode( $identity ) ), 0, 8 );
		}
		$scope = EMCP_Tools_Themer_Loop_Context::scope_key();
		$uid   = '' === $scope ? $local_id : $local_id . '-' . substr( md5( $scope ), 0, 6 );

		$key                     = $scope . '|' . $local_id;
		self::$instances[ $key ] = ( self::$instances[ $key ] ?? 0 ) + 1;
		if ( self::$instances[ $key ] > 1 ) {
			$uid .= '-' . self::$instances[ $key ];
		}
		return $uid;
	}

	/**
	 * @param string $uid Instance id.
	 * @return string
	 */
	public static function page_var( string $uid ): string {
		return 'emcp-page-' . $uid;
	}

	/**
	 * The page this render shows.
	 *
	 * @param array  $q   Query settings.
	 * @param string $uid Instance id.
	 * @return int
	 */
	public static function current_page( array $q, string $uid ): int {
		if ( 'current' === (string) ( $q['source'] ?? 'posts' ) ) {
			return max( 1, (int) get_query_var( 'paged', 1 ) );
		}
		$var  = self::page_var( $uid );
		$page = (int) get_query_var( $var, 0 );
		if ( $page < 1 && isset( $_GET[ $var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public pagination.
			$page = (int) $_GET[ $var ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return max( 1, $page );
	}

	/**
	 * Query context.
	 *
	 * @return array{current_post_id:int}
	 */
	public static function context(): array {
		return array( 'current_post_id' => self::post_id() );
	}

	/**
	 * Everything a render needs, or null when there is nothing to render.
	 *
	 * @param array  $args Element args.
	 * @param string $kind grid|carousel.
	 * @return array|null
	 */
	protected static function prepare( array $args, string $kind ): ?array {
		$template_id = (int) ( $args['template_id'] ?? 0 );
		if ( ! EMCP_Tools_Themer_CPT::is_published_loop_template( $template_id ) ) {
			return null;
		}
		$uid = self::instance_uid( (string) ( $args['anchor'] ?? '' ) !== '' ? (string) $args['anchor'] : (string) ( $args['local_id'] ?? '' ), $args );

		$q = EMCP_Tools_Themer_Loop_Query::sanitize( is_array( $args['query'] ?? null ) ? $args['query'] : array() );
		$notes = array();
		$ajax  = ! empty( $args['ajax'] ) && self::truthy( $args['ajax'] );
		// The carousel does not declare 'pagination' among its own args (it has no
		// pagination feature), so the raw value must be captured once here: a
		// second, uncoalesced read of $args['pagination'] in the ternary's true
		// branch would warn on every caller that omits the key.
		$pagination_raw = (string) ( $args['pagination'] ?? 'none' );
		$pagination     = in_array( $pagination_raw, self::PAGINATION, true ) ? $pagination_raw : 'none';
		if ( in_array( $pagination, self::APPEND_MODES, true ) ) {
			$ajax = true; // load more and infinite scroll are AJAX by nature.
		}

		$page_limit = max( 0, (int) ( $args['page_limit'] ?? 0 ) );

		// Cap the requested page before it can reach the offset arithmetic
		// below: a huge or malicious page number must not overflow the
		// multiplication or trigger a deep-pagination query. The real
		// ceiling (available pages, and page_limit) is applied once the
		// query has run, further down.
		$page = min( self::current_page( $q, $uid ), self::MAX_PAGE );
		$ctx  = self::context();

		$main   = null;
		$reused = null;

		if ( 'current' === $q['source'] ) {
			$main                  = $GLOBALS['wp_query'] ?? null;
			$q['current_snapshot'] = EMCP_Tools_Themer_Loop_Query::snapshot_main_query( $main );

			if ( $ajax && 'none' !== $pagination ) {
				// Run the replay once, at the page we are about to render, and
				// reuse it below instead of running the same query twice.
				$reused = EMCP_Tools_Themer_Loop_Query::run( $q, $ctx, $page );
				if ( ! self::replay_matches_result( $reused, $main ) ) {
					// The snapshot cannot reproduce the live query (a main-query-only
					// filter is at work). Reload pagination still works; say why.
					$ajax    = false;
					$notes[] = self::admin_comment( 'replay_mismatch', 'the main query cannot be replayed, using reload pagination' );
					if ( in_array( $pagination, self::APPEND_MODES, true ) ) {
						$pagination = 'numbers';
					}
				}
			}
		}

		// A snapshot with its own base offset cannot be compared 1:1 with the
		// main query's own totals (see max_pages()'s base-offset subtraction),
		// so route it through the same code path as every other page rather
		// than let page one and page two disagree on the total.
		$has_offset         = 'current' === $q['source'] && (int) ( $q['current_snapshot']['offset'] ?? 0 ) > 0;
		$use_main_shortcut  = ( 'current' === $q['source'] && 1 === $page && ! $has_offset && is_object( $main ) && ! self::in_loop_render() );

		if ( $use_main_shortcut ) {
			$result = self::main_query_result( $q );
		} elseif ( null !== $reused ) {
			$result = $reused;
		} else {
			$result = EMCP_Tools_Themer_Loop_Query::run( $q, $ctx, $page );
		}

		// Clamp to the last available page (page_limit included) rather than
		// rendering an empty grid for an out-of-range request; there is no
		// way back from an empty page with no pagination on it.
		$available = (int) $result['max_pages'];
		if ( $page_limit > 0 ) {
			$available = min( $available, $page_limit );
		}
		if ( $available > 0 && $page > $available ) {
			$page   = $available;
			$result = EMCP_Tools_Themer_Loop_Query::run( $q, $ctx, $page );
		}
		$result['max_pages'] = $available;

		$per_page = 'current' === $q['source'] ? max( 1, (int) ( $q['current_snapshot']['posts_per_page'] ?? get_option( 'posts_per_page', 10 ) ) ) : $q['per_page'];
		// Same reasoning as $pagination_raw above: the carousel does not declare
		// 'tag' among its own args, so this must not re-read $args['tag'] raw.
		$tag_raw  = (string) ( $args['tag'] ?? 'div' );
		$tag      = in_array( $tag_raw, EMCP_Tools_Themer_Loop_Renderer::TAGS, true ) ? $tag_raw : 'div';

		$config = array(
			'uid'         => $uid,
			'template_id' => $template_id,
			'query'       => $q,
			'layout'      => array_merge(
				array(
					'kind'       => $kind,
					'tag'        => $tag,
					'per_page'   => $per_page,
					'page_limit' => $page_limit,
					'alternates' => self::normalize_alternates( $args['alternates'] ?? array() ),
				),
				static::layout_config( $args )
			),
			'ctx'         => $ctx,
		);

		$items = EMCP_Tools_Themer_Loop_Renderer::render_items(
			$template_id,
			$result['posts'],
			array( 'uid' => $uid, 'index_base' => ( $page - 1 ) * $per_page, 'tag' => $tag, 'config' => $config )
		);

		return array(
			'args'         => $args,
			'uid'          => $uid,
			'template_id'  => $template_id,
			'query'        => $q,
			'page'         => $page,
			'per_page'     => $per_page,
			'page_limit'   => $page_limit,
			'pagination'   => $pagination,
			'ajax'         => $ajax,
			'result'       => $result,
			'config'       => $config,
			'config_attrs' => EMCP_Tools_Themer_Loop_Config::attributes( $config ),
			'items'        => $items,
			'notes'        => $notes,
		);
	}

	/**
	 * Does an already-computed result reproduce the live main query? Mirrors
	 * EMCP_Tools_Themer_Loop_Query::replay_matches()'s comparison, but takes a
	 * result the caller already has instead of running the query again.
	 *
	 * @param array         $result Result from EMCP_Tools_Themer_Loop_Query::run().
	 * @param WP_Query|null $main   The live main query.
	 * @return bool
	 */
	private static function replay_matches_result( array $result, $main ): bool {
		if ( ! is_object( $main ) ) {
			return false;
		}
		$live = array_map( 'intval', wp_list_pluck( (array) $main->posts, 'ID' ) );
		$ours = array_map( 'intval', wp_list_pluck( (array) $result['posts'], 'ID' ) );
		if ( $live !== $ours ) {
			return false;
		}
		$ours_pages = isset( $result['query'] ) && is_object( $result['query'] ) ? (int) $result['query']->max_num_pages : 0;
		return (int) $main->max_num_pages === $ours_pages;
	}

	/**
	 * Extra layout keys a subclass wants in the signed config.
	 *
	 * @param array $args Element args.
	 * @return array
	 */
	protected static function layout_config( array $args ): array {
		return array();
	}

	/**
	 * Normalise alternate-template rules (the Pro overlay applies them).
	 *
	 * @param mixed $raw Rules.
	 * @return array<int,array{template_id:int,position:int,repeat:bool,column_span:int}>
	 */
	public static function normalize_alternates( $raw ): array {
		$out = array();
		foreach ( (array) $raw as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$tid = (int) ( $rule['template_id'] ?? 0 );
			$pos = (int) ( $rule['position'] ?? 0 );
			if ( $tid <= 0 || $pos <= 0 ) {
				continue;
			}
			$out[] = array(
				'template_id' => $tid,
				'position'    => $pos,
				'repeat'      => ! empty( $rule['repeat'] ) && self::truthy( $rule['repeat'] ),
				'column_span' => max( 1, min( 6, (int) ( $rule['column_span'] ?? 1 ) ) ),
			);
		}
		return $out;
	}

	/**
	 * The main query's own posts for page 1 of source current (never iterated).
	 *
	 * @param array $q Sanitized settings.
	 * @return array{posts:array,found:int,max_pages:int,page:int,query:object}
	 */
	private static function main_query_result( array $q ): array {
		$main = $GLOBALS['wp_query'];
		return array(
			'posts'     => (array) $main->posts,
			'found'     => (int) $main->found_posts,
			'max_pages' => (int) $main->max_num_pages,
			'page'      => 1,
			'query'     => $main,
		);
	}

	/** @return bool Whether we are already inside an item render. */
	private static function in_loop_render(): bool {
		return EMCP_Tools_Themer_Loop_Context::in_loop();
	}

	/**
	 * The shared data attributes on the element wrapper.
	 *
	 * @param array $p Prepared render.
	 * @return string
	 */
	protected static function data_attributes( array $p ): string {
		$mode = in_array( $p['pagination'], self::APPEND_MODES, true ) ? 'append' : 'replace';
		return 'data-emcp-loop="' . esc_attr( $p['config_attrs']['config'] ) . '" data-emcp-sig="' . esc_attr( $p['config_attrs']['sig'] ) . '"'
			. ' data-emcp-page="' . (int) $p['page'] . '" data-emcp-pages="' . (int) $p['result']['max_pages'] . '" data-emcp-total="' . (int) $p['result']['found'] . '"'
			. ' data-emcp-mode="' . $mode . '" data-emcp-ajax="' . ( $p['ajax'] ? '1' : '0' ) . '" data-emcp-page-var="' . esc_attr( self::page_var( $p['uid'] ) ) . '"';
	}

	/**
	 * @param array $p Prepared render.
	 * @return string
	 */
	protected static function items_html( array $p ): string {
		return implode( '', $p['items'] );
	}

	/**
	 * @param array $args Element args.
	 * @return string
	 */
	protected static function empty_html( array $args ): string {
		$msg = isset( $args['empty_message'] ) && '' !== trim( (string) $args['empty_message'] )
			? (string) $args['empty_message']
			: __( 'No posts found.', 'emcp-tools' );
		return '<p class="emcp-loop__empty">' . esc_html( $msg ) . '</p>';
	}

	/**
	 * An HTML comment for editors, nothing for visitors.
	 *
	 * @param string $code   Short code.
	 * @param string $detail Detail.
	 * @return string
	 */
	public static function admin_comment( string $code, string $detail ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<!-- emcp-loop: ' . esc_html( str_replace( '--', '', $code . ': ' . $detail ) ) . ' -->';
	}
}
