<?php
/**
 * GET /emcp-tools/v1/themer/loop: one more page of a loop element.
 *
 * Replays a config the page already rendered and this site signed, for one
 * page, and returns the item HTML with the complete dependency set the cards
 * need, split by lifetime: external assets (load once), config chunks (apply
 * before the script), init chunks (run after the cards are inserted).
 *
 * Public route. The signature authenticates the config, never the visitor;
 * a logged-in visitor stays logged in through the X-WP-Nonce header the
 * script sends. Responses are no-store by default because a card can depend
 * on the visitor.
 *
 * The requested page is attacker-controlled (this route has no permission
 * check), so it is bounded twice: first against a hard, arbitrary ceiling
 * before any offset arithmetic runs, then against the config's own
 * page_limit before any query runs. A page beyond what the query actually
 * has is answered with an empty page rather than the last page, because in
 * append mode the client already holds every earlier page and re-serving
 * the last one would duplicate its cards.
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
class EMCP_Tools_Themer_Loop_REST {

	const NAMESPACE = 'emcp-tools/v1';
	const ROUTE     = '/themer/loop';
	const MODES     = array( 'append', 'replace' );

	/** Hook route registration. */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/** Register the route. */
	public static function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'config' => array( 'type' => 'string', 'required' => true ),
					'sig'    => array( 'type' => 'string', 'required' => true ),
					'page'   => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => EMCP_Tools_Themer_Element_Loop_Base::MAX_PAGE ),
					'mode'   => array( 'type' => 'string', 'default' => 'append', 'enum' => self::MODES ),
					'tax'    => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle( $request ) {
		$config = EMCP_Tools_Themer_Loop_Config::decode( (string) $request->get_param( 'config' ), (string) $request->get_param( 'sig' ) );
		if ( null === $config ) {
			return self::respond( array( 'code' => 'bad_config', 'message' => __( 'The loop configuration is missing or not signed by this site.', 'emcp-tools' ) ), 400 );
		}
		$page = max( 1, (int) $request->get_param( 'page' ) );
		$mode = in_array( (string) $request->get_param( 'mode' ), self::MODES, true ) ? (string) $request->get_param( 'mode' ) : 'append';

		if ( class_exists( 'EMCP_Tools_Themer_Loop_Assets' ) ) {
			EMCP_Tools_Themer_Loop_Assets::register();
		}
		$collected = self::collect_assets(
			static function () use ( $config, $page, $mode ) {
				return EMCP_Tools_Themer_Loop_REST::render_page( $config, $page, $mode );
			}
		);
		$data           = $collected['output'];
		$data['assets'] = $collected['assets'];
		return self::respond( $data, 200 );
	}

	/**
	 * Render one page of items.
	 *
	 * The page number reaching here already survived max(1, ...) in handle();
	 * it is bounded further, twice, before it can do any harm: a hard
	 * ceiling first (MAX_PAGE, regardless of what the config says), then the
	 * config's own page_limit (an author-set cap, never exceeded even for a
	 * page the query itself would still be able to answer). Neither bound
	 * clamps down to the nearest valid page: an out-of-range request gets an
	 * empty page, not a repeat of the last one, so append mode never
	 * duplicates cards already in the grid.
	 *
	 * @param array  $config Verified config.
	 * @param int    $page   1-based page.
	 * @param string $mode   append|replace.
	 * @return array{html:string,page:int,max_pages:int,count:int}
	 */
	public static function render_page( array $config, int $page, string $mode ): array {
		$template_id = (int) ( $config['template_id'] ?? 0 );
		$layout      = is_array( $config['layout'] ?? null ) ? $config['layout'] : array();
		$query       = is_array( $config['query'] ?? null ) ? $config['query'] : array();
		$ctx         = is_array( $config['ctx'] ?? null ) ? $config['ctx'] : array();
		$per_page    = max( 1, (int) ( $layout['per_page'] ?? EMCP_Tools_Themer_Loop_Query::DEFAULT_PER_PAGE ) );
		$uid         = (string) ( $config['uid'] ?? '' );
		$page_limit  = max( 0, (int) ( $layout['page_limit'] ?? 0 ) );

		// A hard, arbitrary ceiling applied before the page can reach any
		// offset arithmetic. This is not the real ceiling (the query's own
		// page count, and page_limit, both applied below); it exists only so
		// an absurd or malicious page number can never drive a deep-offset
		// query or overflow the arithmetic that builds one.
		$page = min( $page, EMCP_Tools_Themer_Element_Loop_Base::MAX_PAGE );

		if ( ! EMCP_Tools_Themer_CPT::is_published_loop_template( $template_id ) ) {
			return array( 'html' => '', 'page' => $page, 'max_pages' => 0, 'count' => 0 );
		}

		// The author-set ceiling. A page beyond it is never queried at all:
		// the ceiling exists so the site owner can cap how deep pagination
		// goes, and running the query anyway would defeat the point of it.
		if ( $page_limit > 0 && $page > $page_limit ) {
			return array( 'html' => '', 'page' => $page, 'max_pages' => $page_limit, 'count' => 0 );
		}

		$result    = EMCP_Tools_Themer_Loop_Query::run( $query, $ctx, $page );
		$available = (int) $result['max_pages'];
		if ( $page_limit > 0 ) {
			$available = min( $available, $page_limit );
		}

		// Unlike the element (which clamps an out-of-range first render down
		// to the last page, so it is never blank), the route answers an
		// out-of-range page with nothing: in append mode the client already
		// holds every earlier page, and re-serving the last one would
		// duplicate its cards on screen.
		if ( $available > 0 && $page > $available ) {
			return array( 'html' => '', 'page' => $page, 'max_pages' => $available, 'count' => 0 );
		}

		$tag          = in_array( (string) ( $layout['tag'] ?? 'div' ), EMCP_Tools_Themer_Loop_Renderer::TAGS, true ) ? (string) $layout['tag'] : 'div';
		$item_classes = 'carousel' === (string) ( $layout['kind'] ?? 'grid' ) ? array( 'swiper-slide' ) : array();
		$items        = EMCP_Tools_Themer_Loop_Renderer::render_items(
			$template_id,
			$result['posts'],
			array(
				'uid'          => $uid,
				'index_base'   => 'append' === $mode ? ( $page - 1 ) * $per_page : 0,
				'tag'          => $tag,
				'config'       => $config,
				'item_classes' => $item_classes,
			)
		);
		return array(
			'html'      => implode( '', $items ),
			'page'      => $page,
			'max_pages' => $available,
			'count'     => count( $items ),
		);
	}

	/**
	 * Run a render and describe every asset the request ended up needing.
	 *
	 * Uses all_deps() over the full queue (not a diff), so a handle enqueued
	 * before the render is included too. Each handle is split by lifetime:
	 * external markup (load once), config chunks (data/before, apply before
	 * the script runs), init chunks (after, run once the cards are in the
	 * DOM). Every entry states its own type: a style with no src prints no
	 * <link> and its 'external' would otherwise be indistinguishable from a
	 * script with no src.
	 *
	 * @param callable $render Produces the output.
	 * @return array{output:mixed,assets:array}
	 */
	public static function collect_assets( callable $render ): array {
		$output = $render();
		$assets = array();

		$styles = function_exists( 'wp_styles' ) ? wp_styles() : null;
		if ( is_object( $styles ) && method_exists( $styles, 'all_deps' ) ) {
			$styles->all_deps( (array) $styles->queue );
			foreach ( (array) $styles->to_do as $handle ) {
				$item = $styles->registered[ $handle ] ?? null;
				if ( ! $item ) {
					continue;
				}
				$external = '';
				if ( ! empty( $item->src ) ) {
					$external = '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css" href="' . esc_url( self::src_url( $item, $styles ) ) . '" media="all">';
				}
				$after    = $styles->get_data( $handle, 'after' );
				$assets[] = array(
					'handle'   => (string) $handle,
					'type'     => 'style',
					'external' => $external,
					'config'   => array(),
					'init'     => is_array( $after ) ? array_values( array_filter( array_map( 'strval', $after ), 'strlen' ) ) : array(),
				);
			}
		}

		$scripts = function_exists( 'wp_scripts' ) ? wp_scripts() : null;
		if ( is_object( $scripts ) && method_exists( $scripts, 'all_deps' ) ) {
			$scripts->all_deps( (array) $scripts->queue );
			foreach ( (array) $scripts->to_do as $handle ) {
				$item = $scripts->registered[ $handle ] ?? null;
				if ( ! $item ) {
					continue;
				}
				$config = array();
				$data   = $scripts->get_data( $handle, 'data' );
				if ( is_string( $data ) && '' !== trim( $data ) ) {
					$config[] = $data;
				}
				foreach ( (array) $scripts->get_data( $handle, 'before' ) as $chunk ) {
					if ( is_string( $chunk ) && '' !== trim( $chunk ) ) {
						$config[] = $chunk;
					}
				}
				$init = array();
				foreach ( (array) $scripts->get_data( $handle, 'after' ) as $chunk ) {
					if ( is_string( $chunk ) && '' !== trim( $chunk ) ) {
						$init[] = $chunk;
					}
				}
				$external = '';
				if ( ! empty( $item->src ) ) {
					$external = '<script src="' . esc_url( self::src_url( $item, $scripts ) ) . '" id="' . esc_attr( $handle ) . '-js"></script>';
					// A script's translations are part of its external markup.
					// Guarded by method_exists so the test stub, which does not
					// implement it, is never called.
					if ( method_exists( $scripts, 'print_translations' ) ) {
						$translations = $scripts->print_translations( $handle, false );
						if ( is_string( $translations ) && '' !== $translations ) {
							$external .= $translations;
						}
					}
				}
				$assets[] = array( 'handle' => (string) $handle, 'type' => 'script', 'external' => $external, 'config' => $config, 'init' => $init );
			}
		}

		return array( 'output' => $output, 'assets' => $assets );
	}

	/**
	 * A handle's URL with its version, as WordPress would print it.
	 *
	 * @param object $item Registered dependency.
	 * @param object $deps The WP_Dependencies instance.
	 * @return string
	 */
	private static function src_url( $item, $deps ): string {
		$src = (string) $item->src;
		if ( '' !== $src && 0 !== strpos( $src, 'http' ) && 0 !== strpos( $src, '//' ) && isset( $deps->base_url ) ) {
			$src = (string) $deps->base_url . $src;
		}
		$ver = isset( $item->ver ) ? $item->ver : false;
		if ( null !== $ver && false !== $ver ) {
			$src = add_query_arg( 'ver', '' === $ver ? ( defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '' ) : (string) $ver, $src );
		}
		return $src;
	}

	/**
	 * The response cache policy. A card can depend on the visitor, so the
	 * default is no-store; a site whose cards are anonymous can opt into
	 * shared caching.
	 *
	 * @return string
	 */
	public static function cache_control(): string {
		/**
		 * Filters the Cache-Control header of loop REST responses.
		 *
		 * @since 3.18.0
		 * @param string $value Header value. Default 'no-store'.
		 */
		return (string) apply_filters( 'emcp_themer_loop_rest_cache_control', 'no-store' );
	}

	/**
	 * @param array $data   Body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function respond( array $data, int $status ) {
		$res = new WP_REST_Response( $data, $status );
		$res->header( 'Cache-Control', self::cache_control() );
		return $res;
	}
}
