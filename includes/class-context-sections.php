<?php
/**
 * Named sections of the discovery context and the site profile (spec 9.4).
 * A section switched off drops out of the server instructions and of
 * list-tools' context.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Context_Sections {

	const OPTION         = 'emcp_tools_context_sections';
	const PROFILE_OPTION = 'emcp_tools_site_profile';
	const CACHE_KEY      = 'emcp_tools_context_detected';
	const CACHE_TTL      = 43200;
	const VOICE_MAX      = 8;

	/** @return array<string, array{label:string, icon:string, default:bool}> */
	public static function definitions(): array {
		return array(
			'builder'            => array( 'label' => __( 'Page builder', 'emcp-tools' ), 'icon' => 'layout-template', 'default' => true ),
			'theme'              => array( 'label' => __( 'Theme', 'emcp-tools' ), 'icon' => 'palette', 'default' => true ),
			'global_styles'      => array( 'label' => __( 'Global styles', 'emcp-tools' ), 'icon' => 'sparkles', 'default' => true ),
			'structure'          => array( 'label' => __( 'Site structure', 'emcp-tools' ), 'icon' => 'layout-grid', 'default' => true ),
			'plugins'            => array( 'label' => __( 'Active plugins', 'emcp-tools' ), 'icon' => 'plug', 'default' => true ),
			'woocommerce'        => array( 'label' => __( 'WooCommerce catalog', 'emcp-tools' ), 'icon' => 'store', 'default' => false ),
			'elementor_mcp_note' => array( 'label' => __( "Elementor's MCP server", 'emcp-tools' ), 'icon' => 'server', 'default' => true ),
			'skills'             => array( 'label' => __( 'Skills catalog', 'emcp-tools' ), 'icon' => 'brain', 'default' => true ),
			'memory'             => array( 'label' => __( 'Project memory', 'emcp-tools' ), 'icon' => 'history', 'default' => true ),
		);
	}

	/**
	 * Section switches, stored values over defaults.
	 *
	 * @param array|null $override Draft map (id => bool) for the preview.
	 * @return array<string,bool>
	 */
	public static function enabled( ?array $override = null ): array {
		$stored = null === $override ? get_option( self::OPTION, array() ) : $override;
		$stored = is_array( $stored ) ? $stored : array();
		$out    = array();
		foreach ( self::definitions() as $id => $def ) {
			$out[ $id ] = array_key_exists( $id, $stored ) ? (bool) $stored[ $id ] : $def['default'];
		}
		return $out;
	}

	public static function woocommerce_active(): bool {
		return in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ), true );
	}

	/** @return string[] Section ids that apply on this site right now. */
	public static function available(): array {
		return array_values(
			array_filter(
				array_keys( self::definitions() ),
				static function ( string $id ): bool {
					switch ( $id ) {
						case 'woocommerce':
							return self::woocommerce_active();
						case 'elementor_mcp_note':
							return EMCP_Tools_Site_Context::elementor_mcp_enabled();
						case 'skills':
							return '' !== (string) apply_filters( 'emcp_tools_discovery_skills', '' );
						case 'memory':
							return '' !== (string) apply_filters( 'emcp_tools_discovery_memory', '' );
						default:
							return true;
					}
				}
			)
		);
	}

	/**
	 * Summaries of the sections added in 3.18.0 (theme, global styles,
	 * structure, WooCommerce), cached so the MCP request path stays cheap.
	 *
	 * @param bool $refresh Recompute.
	 * @return array<string,mixed>
	 */
	public static function detected( bool $refresh = false ): array {
		if ( ! $refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$out = array(
			'theme'         => self::theme_summary(),
			'global_styles' => self::styles_summary(),
			'structure'     => self::structure_summary(),
			'woocommerce'   => self::woocommerce_active() ? self::woocommerce_summary() : '',
			'refreshedAt'   => time(),
		);
		set_transient( self::CACHE_KEY, $out, self::CACHE_TTL );
		return $out;
	}

	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	private static function theme_summary(): string {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}
		$theme  = wp_get_theme();
		$text   = trim( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) );
		$parent = $theme->parent();
		if ( is_object( $parent ) && method_exists( $parent, 'get' ) ) {
			/* translators: %s: parent theme name. */
			$text .= ' ' . sprintf( __( '(child of %s)', 'emcp-tools' ), $parent->get( 'Name' ) );
		}
		return $text;
	}

	private static function styles_summary(): string {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			return '';
		}
		$kits   = \Elementor\Plugin::$instance->kits_manager;
		$kit_id = method_exists( $kits, 'get_active_id' ) ? (int) $kits->get_active_id() : 0;
		if ( $kit_id <= 0 ) {
			return '';
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();
		$colors   = count( (array) ( $settings['system_colors'] ?? array() ) ) + count( (array) ( $settings['custom_colors'] ?? array() ) );
		$fonts    = count( (array) ( $settings['system_typography'] ?? array() ) ) + count( (array) ( $settings['custom_typography'] ?? array() ) );
		/* translators: 1: colour count, 2: font count. */
		return sprintf( __( '%1$d colors, %2$d fonts (Elementor kit)', 'emcp-tools' ), $colors, $fonts );
	}

	private static function structure_summary(): string {
		$pages = function_exists( 'wp_count_posts' ) ? (int) ( wp_count_posts( 'page' )->publish ?? 0 ) : 0;
		$posts = function_exists( 'wp_count_posts' ) ? (int) ( wp_count_posts( 'post' )->publish ?? 0 ) : 0;
		$menus = function_exists( 'wp_get_nav_menus' ) ? count( (array) wp_get_nav_menus() ) : 0;
		return implode(
			', ',
			array(
				/* translators: %d: number of pages. */
				sprintf( _n( '%d page', '%d pages', $pages, 'emcp-tools' ), $pages ),
				/* translators: %d: number of posts. */
				sprintf( _n( '%d post', '%d posts', $posts, 'emcp-tools' ), $posts ),
				/* translators: %d: number of menus. */
				sprintf( _n( '%d menu', '%d menus', $menus, 'emcp-tools' ), $menus ),
			)
		);
	}

	private static function woocommerce_summary(): string {
		$products = function_exists( 'wp_count_posts' ) ? (int) ( wp_count_posts( 'product' )->publish ?? 0 ) : 0;
		$cats     = function_exists( 'wp_count_terms' ) ? wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) : 0;
		$cats     = is_numeric( $cats ) ? (int) $cats : 0;
		/* translators: 1: products, 2: categories. */
		return sprintf( __( '%1$d products, %2$d categories', 'emcp-tools' ), $products, $cats );
	}

	/**
	 * One summary line per available section, for the Context screen.
	 *
	 * @param bool $refresh Recompute the cached ones.
	 * @return array<string,string>
	 */
	public static function summaries( bool $refresh = false ): array {
		$detected = self::detected( $refresh );
		$active   = (array) get_option( 'active_plugins', array() );
		$out      = array();
		foreach ( self::available() as $id ) {
			switch ( $id ) {
				case 'builder':
					$out[ $id ] = defined( 'ELEMENTOR_VERSION' )
						? 'Elementor ' . ELEMENTOR_VERSION . ( defined( 'ELEMENTOR_PRO_VERSION' ) ? ' + Pro ' . ELEMENTOR_PRO_VERSION : '' )
						: __( 'Gutenberg', 'emcp-tools' );
					break;
				case 'plugins':
					/* translators: %d: number of active plugins. */
					$out[ $id ] = sprintf( _n( '%d active plugin', '%d active plugins', count( $active ), 'emcp-tools' ), count( $active ) );
					break;
				case 'elementor_mcp_note':
					$out[ $id ] = __( 'Tells agents which server to use for what', 'emcp-tools' );
					break;
				case 'skills':
				case 'memory':
					$text  = (string) apply_filters( 'skills' === $id ? 'emcp_tools_discovery_skills' : 'emcp_tools_discovery_memory', '' );
					$count = (int) preg_match_all( '/^- /m', $text );
					/* translators: %d: number of entries. */
					$out[ $id ] = sprintf( _n( '%d entry', '%d entries', $count, 'emcp-tools' ), $count );
					break;
				default:
					$out[ $id ] = (string) ( $detected[ $id ] ?? '' );
			}
		}
		return $out;
	}

	/** @return string[] */
	public static function industries(): array {
		return array( 'Automotive', 'Education', 'Food & Dining', 'Health & Wellness', 'Home Services', 'Lifestyle & Entertainment', 'Nonprofit', 'Pets', 'Professional Services', 'Real Estate', 'Retail', 'Software & SaaS', 'Weddings', 'Other' );
	}

	/** @return string[] */
	public static function voices(): array {
		return array( 'Clear', 'Friendly', 'Technical', 'Playful', 'Formal', 'Warm', 'Bold', 'Concise' );
	}

	/**
	 * @param mixed $raw Submitted or stored profile.
	 * @return array{name:string, industry:string, purpose:string, voice:string[]}
	 */
	public static function sanitize_profile( $raw ): array {
		$raw   = is_array( $raw ) ? $raw : array();
		$voice = array();
		foreach ( (array) ( $raw['voice'] ?? array() ) as $v ) {
			$v = mb_substr( sanitize_text_field( (string) $v ), 0, 40 );
			if ( '' !== $v && ! in_array( $v, $voice, true ) ) {
				$voice[] = $v;
			}
		}
		return array(
			'name'     => mb_substr( sanitize_text_field( (string) ( $raw['name'] ?? '' ) ), 0, 120 ),
			'industry' => mb_substr( sanitize_text_field( (string) ( $raw['industry'] ?? '' ) ), 0, 80 ),
			'purpose'  => mb_substr( sanitize_textarea_field( (string) ( $raw['purpose'] ?? '' ) ), 0, 1000 ),
			'voice'    => array_slice( $voice, 0, self::VOICE_MAX ),
		);
	}

	/**
	 * @param array|null $override Draft profile for the preview.
	 */
	public static function profile( ?array $override = null ): array {
		return self::sanitize_profile( null === $override ? get_option( self::PROFILE_OPTION, array() ) : $override );
	}
}
