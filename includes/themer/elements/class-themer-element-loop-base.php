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
	 * @param string $local_id Caller-provided id ('' = hash the args).
	 * @param array  $args     Element args (hashed when no id is given).
	 * @return string
	 */
	public static function instance_uid( string $local_id, array $args ): string {
		$local_id = sanitize_key( $local_id );
		if ( '' === $local_id ) {
			unset( $args['local_id'], $args['anchor'] );
			ksort( $args );
			$local_id = substr( md5( (string) wp_json_encode( $args ) ), 0, 8 );
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
		return array( 'current_post_id' => EMCP_Tools_Themer_Dynamic::current_post_id() );
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
		$pagination = in_array( (string) ( $args['pagination'] ?? 'none' ), self::PAGINATION, true ) ? (string) $args['pagination'] : 'none';
		if ( in_array( $pagination, self::APPEND_MODES, true ) ) {
			$ajax = true; // load more and infinite scroll are AJAX by nature.
		}

		if ( 'current' === $q['source'] ) {
			$main = $GLOBALS['wp_query'] ?? null;
			$q['current_snapshot'] = EMCP_Tools_Themer_Loop_Query::snapshot_main_query( $main );
			if ( $ajax && 'none' !== $pagination && ! EMCP_Tools_Themer_Loop_Query::replay_matches( $q, self::context(), $main ) ) {
				// The snapshot cannot reproduce the live query (a main-query-only
				// filter is at work). Reload pagination still works; say why.
				$ajax    = false;
				$notes[] = self::admin_comment( 'replay_mismatch', 'the main query cannot be replayed, using reload pagination' );
				if ( in_array( $pagination, self::APPEND_MODES, true ) ) {
					$pagination = 'numbers';
				}
			}
		}

		$page   = self::current_page( $q, $uid );
		$ctx    = self::context();
		$result = ( 'current' === $q['source'] && 1 === $page && isset( $GLOBALS['wp_query'] ) && is_object( $GLOBALS['wp_query'] ) && ! self::in_loop_render() )
			? self::main_query_result( $q )
			: EMCP_Tools_Themer_Loop_Query::run( $q, $ctx, $page );

		$per_page = 'current' === $q['source'] ? max( 1, (int) ( $q['current_snapshot']['posts_per_page'] ?? get_option( 'posts_per_page', 10 ) ) ) : $q['per_page'];
		$tag      = in_array( (string) ( $args['tag'] ?? 'div' ), EMCP_Tools_Themer_Loop_Renderer::TAGS, true ) ? (string) $args['tag'] : 'div';

		$config = array(
			'uid'         => $uid,
			'template_id' => $template_id,
			'query'       => $q,
			'layout'      => array_merge(
				array(
					'kind'       => $kind,
					'tag'        => $tag,
					'per_page'   => $per_page,
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
