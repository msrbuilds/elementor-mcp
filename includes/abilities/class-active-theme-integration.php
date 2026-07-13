<?php
/**
 * Active Theme integration — the framework-agnostic Themes-tab pack.
 *
 * `theme-read`  : get-theme-context, get-mods
 * `theme-write` : set-mods (allowlist-guarded), create-child-theme (confirm-gated)
 *
 * Works for ANY active theme. Building pages reuses the Gutenberg/Elementor
 * tools; this integration supplies the context the agent reasons over. File
 * edits happen via the Filesystem tools after create-child-theme.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The framework-agnostic active-theme integration.
 */
class EMCP_Tools_Active_Theme_Integration extends EMCP_Tools_Theme_Integration {

	/**
	 * Known theme-framework template slugs (for the `framework` context hint).
	 *
	 * @var string[]
	 */
	const KNOWN_FRAMEWORKS = array( 'astra', 'kadence', 'generatepress', 'oceanwp', 'blocksy', 'neve', 'hello-elementor' );

	/**
	 * Theme mods this pack refuses to write (owned by other domains / structural).
	 *
	 * @var string[]
	 */
	const REFUSED_MODS = array( 'nav_menu_locations', 'sidebars_widgets', 'custom_css_post_id' );

	/**
	 * Theme-support features probed for the context.
	 *
	 * @var string[]
	 */
	const PROBED_SUPPORTS = array( 'post-thumbnails', 'custom-logo', 'editor-styles', 'wp-block-styles', 'align-wide', 'responsive-embeds', 'custom-background', 'html5' );

	public function id(): string {
		return 'theme';
	}

	public function label(): string {
		return __( 'Active Theme', 'emcp-tools' );
	}

	public function is_available(): bool {
		return true;
	}

	protected function operations(): array {
		return array(
			'get-theme-context'  => array(
				'mode' => 'read',
				'run'  => array( $this, 'execute_get_context' ),
				'perm' => array( $this, 'can_read' ),
				'desc' => __( 'Active theme identity + capabilities: parent/child, detected framework, is_block_theme, template dir, theme supports, registered menu locations, and whether a child theme exists. Call this first.', 'emcp-tools' ),
			),
			'get-mods'           => array(
				'mode' => 'read',
				'run'  => array( $this, 'execute_get_mods' ),
				'perm' => array( $this, 'can_read' ),
				'desc' => __( 'All theme_mod values for the active theme (customizer-backed state).', 'emcp-tools' ),
			),
			'set-mods'           => array(
				'mode' => 'write',
				'run'  => array( $this, 'execute_set_mods' ),
				'perm' => array( $this, 'can_write' ),
				'desc' => __( 'Set theme_mod values ({ values: { key: value } }). Structural mods (menu locations, widgets) are refused.', 'emcp-tools' ),
			),
			'create-child-theme' => array(
				'mode' => 'write',
				'run'  => array( $this, 'execute_create_child_theme' ),
				'perm' => array( $this, 'can_manage_theme' ),
				'desc' => __( 'Create + activate a child theme of the active parent so the agent can safely edit theme files (requires { confirm: true }).', 'emcp-tools' ),
			),
		);
	}

	/**
	 * @return bool switch_themes gate for create-child-theme.
	 */
	public function can_manage_theme(): bool {
		return current_user_can( 'switch_themes' ) && current_user_can( 'edit_theme_options' );
	}

	/**
	 * @param array $input Unused.
	 * @return array
	 */
	public function execute_get_context( $input ): array {
		$stylesheet = (string) get_stylesheet();
		$template   = (string) get_template();
		$theme      = wp_get_theme();

		$supports = array();
		foreach ( self::PROBED_SUPPORTS as $feature ) {
			if ( current_theme_supports( $feature ) ) {
				$supports[] = $feature;
			}
		}

		return array(
			'active'         => $stylesheet,
			'parent'         => $template,
			'is_child'       => $stylesheet !== $template,
			'framework'      => in_array( $template, self::KNOWN_FRAMEWORKS, true ) ? $template : null,
			'is_block_theme' => function_exists( 'wp_is_block_theme' ) ? (bool) wp_is_block_theme() : false,
			'name'           => $theme ? (string) $theme->get( 'Name' ) : $stylesheet,
			'version'        => $theme ? (string) $theme->get( 'Version' ) : '',
			'template_dir'   => (string) get_stylesheet_directory(),
			'supports'       => $supports,
			'menu_locations' => function_exists( 'get_registered_nav_menus' ) ? (array) get_registered_nav_menus() : array(),
			'has_child'      => EMCP_Tools_Child_Theme_Builder::child_exists(),
		);
	}

	/**
	 * @param array $input Unused.
	 * @return array
	 */
	public function execute_get_mods( $input ): array {
		$mods = get_theme_mods();
		return array( 'mods' => is_array( $mods ) ? $mods : array() );
	}

	/**
	 * @param array $input { values: { key: value } }.
	 * @return array|WP_Error
	 */
	public function execute_set_mods( $input ) {
		$values = ( isset( $input['values'] ) && is_array( $input['values'] ) ) ? $input['values'] : array();
		if ( empty( $values ) ) {
			return new WP_Error( 'missing_values', __( 'Provide a "values" object of theme_mod key => value pairs.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$updated = array();
		$skipped = array();
		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, self::REFUSED_MODS, true ) ) {
				$skipped[] = $key;
				continue;
			}
			set_theme_mod( $key, $value );
			$updated[] = $key;
		}

		return array(
			'updated' => $updated,
			'skipped' => $skipped,
		);
	}

	/**
	 * @param array $input { confirm: true }.
	 * @return array|WP_Error
	 */
	public function execute_create_child_theme( $input ) {
		if ( true !== ( $input['confirm'] ?? null ) ) {
			return new WP_Error(
				'confirm_required',
				__( 'Creating and activating a child theme changes the active theme and enables file writes; pass { confirm: true } to proceed.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}
		return EMCP_Tools_Child_Theme_Builder::create();
	}
}
