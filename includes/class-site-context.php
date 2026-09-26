<?php
/**
 * Site-wide context: admin-authored guidance injected into the MCP server
 * `instructions` (the initialize handshake) so connected AI agents apply it
 * automatically. Loaded unconditionally — the MCP server is registered on
 * non-admin requests.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Site_Context {

	/** Option holding the admin's markdown context. */
	const OPTION_CONTEXT = 'emcp_tools_site_context';

	/** Option holding the on/off toggle ('1' or '0'). Default on. */
	const OPTION_ENABLED = 'emcp_tools_site_context_enabled';

	/** Admin override for the reachable public base URL (Connection tab). */
	const OPTION_BASE_URL = 'emcp_tools_public_base_url';

	/** Delimiter that separates the base description from the site context. */
	const DELIMITER = "\n\n## Site context\n\n";

	/** Hard cap on the stored/delivered context, in characters. */
	const MAX_CHARS = 20000;

	/**
	 * The base MCP server description (the tool-overview text). Single source
	 * of truth, reused by register_mcp_server() and the admin preview.
	 *
	 * @return string
	 */
	public static function default_base(): string {
		return __( 'Exposes Elementor data and design tools as MCP tools for AI agents.', 'emcp-tools' );
	}

	/**
	 * The reachable public base URL clients use to reach this site's MCP server.
	 *
	 * Defaults to the base the REST API actually answers on (derived from
	 * rest_url(), which is what the Connection tab shows) — NOT home_url().
	 * On some hosts (e.g. a staging site whose Site Address is pinned to a
	 * not-yet-live production domain) home_url() points at an unreachable URL
	 * while the REST API answers on the real host; using rest_url() keeps every
	 * client-facing URL reachable. An admin override (Connection tab "Server
	 * URL") wins when set, and the `emcp_tools_public_base_url` filter is the
	 * fleet-wide override seam (drop a one-line MU-plugin across many sites).
	 *
	 * Used for the .mcpb bundle WP_URL, the OAuth issuer + authorization
	 * endpoint, and the manual config examples — so they stay consistent with
	 * the rest_url()-based resource/token endpoints.
	 *
	 * @return string Base URL, no trailing slash.
	 */
	public static function public_base_url(): string {
		$override = get_option( self::OPTION_BASE_URL, '' );
		$override = is_string( $override ) ? trim( $override ) : '';
		$base     = '' !== $override ? $override : self::detected_base_url();
		/**
		 * Filter the reachable public base URL used for all client-facing
		 * endpoints (bundle WP_URL, OAuth issuer/authorization endpoint, configs).
		 *
		 * @param string $base Base URL, no trailing slash.
		 */
		$base = (string) apply_filters( 'emcp_tools_public_base_url', $base );
		return rtrim( $base, '/' );
	}

	/**
	 * The full MCP server endpoint clients call, honoring the Server URL
	 * override. With no override this is rest_url() (permalink-aware, reachable);
	 * with an override set it is the override + the pretty-permalink REST path
	 * (staging overrides are standard pretty-permalink hosts).
	 *
	 * @return string
	 */
	public static function mcp_endpoint(): string {
		return self::rest_endpoint( 'mcp/emcp-tools-server' );
	}

	/**
	 * Host clients are expected to use for MCP and OAuth requests.
	 *
	 * @return string Hostname without a port.
	 */
	public static function public_host(): string {
		return (string) wp_parse_url( self::public_base_url(), PHP_URL_HOST );
	}

	/**
	 * A REST endpoint URL on the reachable public base. With a Server URL
	 * override set, it is `override + /wp-json/<path>` (pretty permalinks);
	 * otherwise `rest_url(<path>)` (permalink-aware). Routing every OAuth + MCP
	 * endpoint (resource, token, authorize, the MCP server) through this keeps
	 * them all on one consistent, reachable host — so the override is
	 * authoritative for the whole discovery flow, not just the issuer.
	 *
	 * @param string $path REST path relative to the API root (e.g. 'mcp/…').
	 * @return string
	 */
	public static function rest_endpoint( string $path ): string {
		$override = get_option( self::OPTION_BASE_URL, '' );
		$override = is_string( $override ) ? trim( $override ) : '';
		if ( '' !== $override ) {
			return rtrim( $override, '/' ) . '/wp-json/' . ltrim( $path, '/' );
		}
		return function_exists( 'rest_url' ) ? (string) rest_url( $path ) : ( rtrim( (string) home_url(), '/' ) . '/wp-json/' . ltrim( $path, '/' ) );
	}

	/**
	 * The base URL detected from the REST API (rest_url() with the REST prefix
	 * stripped) — the origin the site actually answers on. No option/filter, so
	 * the Connection tab can show it as the detected default even when an
	 * override is set.
	 *
	 * @return string Base URL, no trailing slash.
	 */
	public static function detected_base_url(): string {
		$rest = function_exists( 'rest_url' ) ? (string) rest_url() : '';
		if ( '' === $rest ) {
			return rtrim( (string) home_url(), '/' );
		}
		// Pretty permalinks: https://host/subdir/wp-json/ → strip the REST prefix.
		$stripped = preg_replace( '#/wp-json/?$#', '', $rest );
		if ( is_string( $stripped ) && $stripped !== $rest ) {
			return rtrim( $stripped, '/' );
		}
		// Plain permalinks: https://host/index.php?rest_route=/ → keep scheme+host(+port+path),
		// dropping the index.php WordPress always inserts before the query on this permalink
		// structure — it's part of how the REST request is routed, not part of the site's base.
		$parts = wp_parse_url( $rest );
		if ( is_array( $parts ) && ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			$base = $parts['scheme'] . '://' . $parts['host'];
			if ( ! empty( $parts['port'] ) ) {
				$base .= ':' . $parts['port'];
			}
			if ( ! empty( $parts['path'] ) ) {
				$path = rtrim( (string) $parts['path'], '/' );
				$path = preg_replace( '#/index\.php$#', '', $path );
				$base .= $path;
			}
			return rtrim( $base, '/' );
		}
		return rtrim( (string) home_url(), '/' );
	}

	/**
	 * The admin's raw context markdown.
	 *
	 * @return string
	 */
	public static function get_context(): string {
		return (string) get_option( self::OPTION_CONTEXT, '' );
	}

	/**
	 * Whether context delivery is enabled (default on).
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Pure: build the instructions string from a base + raw context + toggle.
	 * Returns $base unchanged when disabled or the context is blank; otherwise
	 * appends the trimmed, capped context under the delimiter.
	 *
	 * @param string $base
	 * @param string $context
	 * @param bool   $enabled
	 * @return string
	 */
	public static function compose( string $base, string $context, bool $enabled ): string {
		$ctx = trim( $context );
		if ( ! $enabled || '' === $ctx ) {
			return $base;
		}
		return $base . self::DELIMITER . mb_substr( $ctx, 0, self::MAX_CHARS );
	}

	/**
	 * Build the live instructions string from the stored options.
	 *
	 * @param string $base
	 * @return string
	 */
	public static function compose_instructions( string $base ): string {
		return self::compose( $base, self::get_context(), self::is_enabled() );
	}

	/**
	 * A compact environment + active-plugin inventory the agent gets in the
	 * server description (and via list-tools). Adds the dispatcher usage preamble
	 * when compact tool mode is on. Read live; keep it short.
	 *
	 * @return string
	 */
	public static function environment_summary( ?array $sections = null ): string {
		$on    = EMCP_Tools_Context_Sections::enabled( $sections );
		$wp    = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';
		$php   = PHP_VERSION;
		$lines = array( '## Environment' );
		$lines[] = sprintf( '- WordPress %s · PHP %s', $wp, $php );

		if ( $on['builder'] ) {
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				$atomic  = version_compare( ELEMENTOR_VERSION, '4.0.0', '>=' ) ? ' (atomic elements supported)' : '';
				$pro     = defined( 'ELEMENTOR_PRO_VERSION' ) ? ' + Pro ' . ELEMENTOR_PRO_VERSION : '';
				$lines[] = sprintf( '- Elementor %s%s%s', ELEMENTOR_VERSION, $pro, $atomic );
			} else {
				$lines[] = '- Elementor: not active (Elementor tools are unavailable; use the WordPress/Gutenberg tools)';
			}
		}

		$inventory = self::plugin_inventory();
		if ( $on['plugins'] && '' !== $inventory ) {
			$lines[] = '- Active plugins of note: ' . $inventory;
		}

		// Sections added in 3.18.0, from a cached summary (EMCP_Tools_Context_Sections::detected()).
		$new_labels = array(
			'theme'         => 'Theme',
			'global_styles' => 'Global styles',
			'structure'     => 'Site structure',
			'woocommerce'   => 'WooCommerce',
		);
		if ( $on['theme'] || $on['global_styles'] || $on['structure'] || $on['woocommerce'] ) {
			$detected = EMCP_Tools_Context_Sections::detected();
			foreach ( $new_labels as $id => $label ) {
				if ( ! $on[ $id ] || '' === (string) ( $detected[ $id ] ?? '' ) ) {
					continue;
				}
				if ( 'woocommerce' === $id && ! EMCP_Tools_Context_Sections::woocommerce_active() ) {
					continue;
				}
				$lines[] = '- ' . $label . ': ' . $detected[ $id ];
			}
		}

		$emcp_official = $on['elementor_mcp_note'] ? self::elementor_mcp_note() : '';
		if ( '' !== $emcp_official ) {
			$lines[] = '';
			$lines[] = $emcp_official;
		}

		// Read the option directly (not EMCP_Tools_Plugin::is_dispatcher_mode())
		// so this method has no dependency on the plugin singleton — keeps it
		// unit-testable without booting EMCP_Tools_Plugin. Option name mirrors
		// EMCP_Tools_Plugin::OPTION_DISPATCHER_MODE.
		if ( function_exists( 'get_option' ) && '1' === (string) get_option( 'emcp_tools_dispatcher_mode', '0' ) ) {
			$lines[] = '';
			$lines[] = '## Compact tool mode';
			$lines[] = 'This server exposes a small set of dispatcher tools. Discover tools with `list-tools`, fetch a tool\'s inputs with `get-tool-schema`, then run it with `call-tool` (name + arguments).';
		}

		// Discovery-context skills catalog (Pro hooks this to inject a "## Skills"
		// block; free ships only the empty seam).
		$emcp_skills = $on['skills'] ? (string) apply_filters( 'emcp_tools_discovery_skills', '' ) : '';
		if ( '' !== $emcp_skills ) {
			$lines[] = '';
			$lines[] = $emcp_skills;
		}

		// Discovery-context project memory (Pro hooks this to inject a
		// "## Project memory" block of approved guidance; free ships the empty seam).
		$emcp_memory = $on['memory'] ? (string) apply_filters( 'emcp_tools_discovery_memory', '' ) : '';
		if ( '' !== $emcp_memory ) {
			$lines[] = '';
			$lines[] = $emcp_memory;
		}

		return implode( "\n", $lines );
	}

	/**
	 * The site profile as a "## Site profile" block, '' when nothing is filled.
	 *
	 * @param array $profile Sanitised profile.
	 * @return string
	 */
	public static function profile_block( array $profile ): string {
		$rows  = array(
			'Business'             => (string) ( $profile['name'] ?? '' ),
			'Industry'             => (string) ( $profile['industry'] ?? '' ),
			'What the site is for' => (string) ( $profile['purpose'] ?? '' ),
			'Brand voice'          => implode( ', ', (array) ( $profile['voice'] ?? array() ) ),
		);
		$lines = array();
		foreach ( $rows as $label => $value ) {
			if ( '' !== trim( $value ) ) {
				$lines[] = '- ' . $label . ': ' . str_replace( array( "\r\n", "\n", "\r" ), ' ', trim( $value ) );
			}
		}
		return $lines ? "## Site profile\n" . implode( "\n", $lines ) : '';
	}

	/**
	 * Exactly what the MCP server sends as its instructions. The Context
	 * screen's preview passes an unsaved draft: { profile, sections,
	 * instructions, enabled }.
	 *
	 * @param array|null $draft Draft values that override the stored ones.
	 * @return string
	 */
	public static function server_instructions( ?array $draft = null ): string {
		$base    = self::default_base() . "\n\n" . self::environment_summary( isset( $draft['sections'] ) ? (array) $draft['sections'] : null );
		$enabled = isset( $draft['enabled'] ) ? (bool) $draft['enabled'] : self::is_enabled();
		$context = isset( $draft['instructions'] ) ? (string) $draft['instructions'] : self::get_context();
		$profile = self::profile_block( EMCP_Tools_Context_Sections::profile( isset( $draft['profile'] ) ? (array) $draft['profile'] : null ) );
		if ( $enabled && '' !== $profile ) {
			$base .= "\n\n" . $profile;
		}
		return self::compose( $base, $context, $enabled );
	}

	/**
	 * Whether Elementor's own MCP server (Elementor 4.3+) is switched on.
	 *
	 * @return bool
	 */
	public static function elementor_mcp_enabled(): bool {
		$settings = '\Elementor\MCP\Composer\Admin\McpSettingsController';
		if ( ! class_exists( $settings ) || ! method_exists( $settings, 'is_enabled' ) ) {
			return false;
		}
		try {
			return (bool) $settings::is_enabled();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Tells an agent that may be connected to both servers which one to use.
	 *
	 * @return string Empty when Elementor's MCP server is off.
	 */
	public static function elementor_mcp_note(): string {
		if ( ! self::elementor_mcp_enabled() ) {
			return '';
		}
		$lines = array( "## Elementor's own MCP server" );
		if ( class_exists( 'EMCP_Tools_Page_Builders' ) && EMCP_Tools_Page_Builders::enabled( 'elementor' ) ) {
			$lines[] = "Elementor's built-in MCP server (`elementor-mcp-server`, tools named `elementor-*`) is also enabled on this site. If you are connected to both, do each step on one server only, never the same step on both:";
			$lines[] = '- This server: Elementor pages and widgets, global colors, fonts, classes and variables, EMCP Themer templates and loops, WordPress content, media, settings, plugins, users, WooCommerce, other page builders, and History rollback.';
			$lines[] = "- Elementor's server: only what this server lacks, such as Elementor components, default element styles, interactions and shareable preview links, or when the user asks for it by name.";
			$lines[] = "Both servers edit the same Elementor data, so re-read the page structure after switching servers.";
		} else {
			$lines[] = "Elementor's built-in MCP server (`elementor-mcp-server`) is also enabled, and this server's Elementor tools are off because Elementor is not the page builder selected in EMCP Tools. Edit Elementor pages with Elementor's server; use this server for everything else it offers.";
		}
		return implode( "\n", $lines );
	}

	/**
	 * Compact "name" list of active plugins the agent should know about.
	 *
	 * @return string
	 */
	private static function plugin_inventory(): string {
		$known = array(
			'woocommerce/woocommerce.php'        => 'WooCommerce',
			'advanced-custom-fields/acf.php'     => 'ACF',
			'advanced-custom-fields-pro/acf.php' => 'ACF Pro',
		);
		$active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		$out    = array();
		foreach ( $known as $file => $label ) {
			if ( in_array( $file, $active, true ) ) {
				$out[] = $label;
			}
		}
		return implode( ', ', $out );
	}
}
