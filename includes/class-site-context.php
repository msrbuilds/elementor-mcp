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
	public static function environment_summary(): string {
		$wp    = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';
		$php   = PHP_VERSION;
		$lines = array( '## Environment' );
		$lines[] = sprintf( '- WordPress %s · PHP %s', $wp, $php );

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$atomic  = version_compare( ELEMENTOR_VERSION, '4.0.0', '>=' ) ? ' (atomic elements supported)' : '';
			$pro     = defined( 'ELEMENTOR_PRO_VERSION' ) ? ' + Pro ' . ELEMENTOR_PRO_VERSION : '';
			$lines[] = sprintf( '- Elementor %s%s%s', ELEMENTOR_VERSION, $pro, $atomic );
		} else {
			$lines[] = '- Elementor: not active (Elementor tools are unavailable; use the WordPress/Gutenberg tools)';
		}

		$inventory = self::plugin_inventory();
		if ( '' !== $inventory ) {
			$lines[] = '- Active plugins of note: ' . $inventory;
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
