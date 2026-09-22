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

	/** @var bool True while this route renders a page of Loop Items. */
	private static $rendering = false;

	/**
	 * Whether the current render is one this route is making. A loop element
	 * nested in a card checks this (never REST_REQUEST alone, which is also
	 * true for the block renderer's editor previews).
	 *
	 * @return bool
	 */
	public static function is_rendering(): bool {
		return self::$rendering;
	}

	/**
	 * Test seam.
	 *
	 * @param bool $on Flag value.
	 */
	public static function set_rendering_for_tests( bool $on ): void {
		self::$rendering = $on;
	}

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
		// WordPress bakes the cookie user's REST nonce into wp-api-fetch's and
		// wp-api-request's localized data the moment the scripts object is
		// first built (wp_default_scripts). collect_assets() returns exactly
		// that localized data for every queued handle, and a REST response
		// reflects the request Origin with credentials, so a cookie-but-no-
		// nonce request (the one core refuses to trust) must never reach
		// rendering, or it would get the logged-in visitor's nonce back in
		// the asset list. $GLOBALS['wp_rest_auth_cookie'] is exactly true
		// only for a VALID cookie (rest_cookie_collect_status()); any other
		// cookie status is a non-empty string ('malformed', 'expired', ...),
		// so the comparison must be strict, matching core's own check at
		// rest-api.php:1142. Do not remove this as redundant with core: a
		// plugin can build the scripts object at init or during user
		// determination, both of which run before REST authentication
		// demotes this request, so core's own demotion can come too late.
		if ( true === ( $GLOBALS['wp_rest_auth_cookie'] ?? null ) && 0 === get_current_user_id() ) {
			return self::respond( array( 'code' => 'rest_cookie_invalid_nonce', 'message' => __( 'Cookie check failed.', 'emcp-tools' ) ), 403 );
		}

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
		$per_page    = max( 1, min( EMCP_Tools_Themer_Loop_Query::MAX_PER_PAGE, (int) ( $layout['per_page'] ?? EMCP_Tools_Themer_Loop_Query::DEFAULT_PER_PAGE ) ) );
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

		$tag_raw      = (string) ( $layout['tag'] ?? 'div' );
		$tag          = in_array( $tag_raw, EMCP_Tools_Themer_Loop_Renderer::TAGS, true ) ? $tag_raw : 'div';
		$item_classes = 'carousel' === (string) ( $layout['kind'] ?? 'grid' ) ? array( 'swiper-slide' ) : array();
		$was          = self::$rendering;
		self::$rendering = true;
		try {
			$items = EMCP_Tools_Themer_Loop_Renderer::render_items(
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
		} finally {
			self::$rendering = $was;
		}
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
					$external = '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css" href="' . esc_url( self::src_url( $item, $styles, (string) $handle, 'style_loader_src' ) ) . '" media="all">';
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
					// A script's translations are part of its external markup,
					// printed BEFORE the file as core prints them (the file reads
					// its locale data as it runs). print_translations() with
					// $display false returns bare JS, not a tag, so it is wrapped
					// here with core's own id. Guarded by method_exists so a
					// dependencies stub without it is never called.
					if ( method_exists( $scripts, 'print_translations' ) ) {
						$translations = $scripts->print_translations( $handle, false );
						if ( is_string( $translations ) && '' !== trim( $translations ) ) {
							$external .= wp_get_inline_script_tag( $translations, array( 'id' => $handle . '-js-translations' ) );
						}
					}
					$external .= '<script src="' . esc_url( self::src_url( $item, $scripts, (string) $handle, 'script_loader_src' ) ) . '" id="' . esc_attr( $handle ) . '-js"></script>';
				}
				$assets[] = array( 'handle' => (string) $handle, 'type' => 'script', 'external' => $external, 'config' => $config, 'init' => $init );
			}
		}

		return array( 'output' => $output, 'assets' => $assets );
	}

	/**
	 * A handle's URL with its version, as WordPress would print it.
	 *
	 * Mirrors WP_Scripts::do_item() / WP_Styles::do_item(): a $ver of exactly
	 * null means no ver argument at all (the dependency opts out of
	 * cache-busting on purpose); false or '' falls back to the dependencies
	 * object's own default_version. The result is run through the same
	 * script_loader_src / style_loader_src filter core runs it through, by
	 * handle, so a site rewriting asset URLs (a CDN, an offloader) rewrites
	 * these the same way it rewrites a normally-printed tag.
	 *
	 * @param object $item   Registered dependency.
	 * @param object $deps   The WP_Dependencies instance.
	 * @param string $handle The handle.
	 * @param string $filter 'script_loader_src' or 'style_loader_src'.
	 * @return string
	 */
	private static function src_url( $item, $deps, string $handle, string $filter ): string {
		$src = (string) $item->src;
		if ( '' !== $src && 0 !== strpos( $src, 'http' ) && 0 !== strpos( $src, '//' ) && isset( $deps->base_url ) ) {
			$src = (string) $deps->base_url . $src;
		}
		$ver = isset( $item->ver ) ? $item->ver : false;
		if ( null !== $ver ) {
			$ver = $ver ? $ver : ( isset( $deps->default_version ) ? (string) $deps->default_version : '' );
			if ( '' !== $ver ) {
				$src = add_query_arg( 'ver', (string) $ver, $src );
			}
		}
		return (string) apply_filters( $filter, $src, $handle );
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
		$value = (string) apply_filters( 'emcp_themer_loop_rest_cache_control', 'no-store' );

		// A response to an authenticated visitor carries that visitor's own
		// data (their nonce among the queued assets, and any card content
		// that varies by who is viewing it); a site opting into shared
		// caching must never have that response handed to a CDN or another
		// visitor. This override cannot be filtered away.
		return is_user_logged_in() ? 'no-store' : $value;
	}

	/**
	 * @param array $data   Body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function respond( array $data, int $status ) {
		$res = new WP_REST_Response( $data, $status );
		// An error response is never shared. cache_control()'s own public
		// value is meant for a page of cards, not a refusal; a site that
		// opted into shared caching must not have a CDN cache one visitor's
		// 403/400 and serve it to every other visitor as if it were theirs.
		$res->header( 'Cache-Control', $status >= 400 ? 'no-store' : self::cache_control() );
		return $res;
	}
}
