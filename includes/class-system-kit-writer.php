<?php
/**
 * System Kit Writer — the single, gated write path for brand-kit changes.
 *
 * This is the ONLY class in the plugin that mutates the active Elementor kit's
 * `system_colors`, `system_typography`, or brand-kit `custom_colors`. The
 * brand-kit abilities, the `apply_kit()` orchestrator, and the restore flow all
 * route through it. Centralising the writes here gives us one place to enforce
 * the Pro gate, the full per-control-type typography reset, the `_brand_kit_`
 * custom-colors cleanup contract, and the verified persistence fallback for
 * CLI / proxy contexts where `Document::save()` is unreliable.
 *
 * See docs/BRAND_KITS_PLAN.md §§ 2.1, 2.1.1, 4.2.1, 4.3, 6.1.
 *
 * @package Elementor_MCP
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomic writer for the active Elementor kit's global colors & typography.
 *
 * @since 1.8.0
 */
class Elementor_MCP_System_Kit_Writer {

	/**
	 * Prefix applied to the `_id` of every custom color a brand kit adds, so
	 * the cleanup contract (§ 2.1.1) can distinguish kit-owned tokens from
	 * tokens the site builder added by hand.
	 *
	 * @var string
	 */
	const BRAND_PREFIX = '_brand_kit_';

	/**
	 * Elementor's four fixed system slot IDs. Brand kits replace exactly these.
	 *
	 * @var string[]
	 */
	const SYSTEM_SLOTS = array( 'primary', 'secondary', 'text', 'accent' );

	/**
	 * Elementor's typography sub-fields and their control type. Used by the
	 * full-reset map so a blank field clears inherited state with the value
	 * Elementor treats as "no override" for that control — never a literal 0.
	 *
	 * 'slider' → blank resets to array() (empty); 'select'/'font' → blank
	 * resets to ''.
	 *
	 * @var array<string, string>
	 */
	const TYPO_FIELDS = array(
		'font_family'     => 'font',
		'font_weight'     => 'select',
		'font_size'       => 'slider',
		'line_height'     => 'slider',
		'letter_spacing'  => 'slider',
		'word_spacing'    => 'slider',
		'text_transform'  => 'select',
		'font_style'      => 'select',
		'text_decoration' => 'select',
		'direction'       => 'select',
	);

	// -------------------------------------------------------------------------
	// Gate
	// -------------------------------------------------------------------------

	/**
	 * Whether the current user may perform brand-kit writes. The last line of
	 * defense: the one class that can mutate global styling refuses to do so for
	 * a user without `manage_options`, regardless of caller.
	 *
	 * As of 1.9.0 applying brand kits is a FREE feature (10 bundled kits +
	 * backup/restore), so this is a capability gate, not a license gate. The
	 * Pro-only surface (the 50-kit remote library and the MCP brand-kit tools)
	 * is gated separately at its own layer.
	 *
	 * @since 1.8.0
	 *
	 * @return bool
	 */
	public static function user_has_access(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// Public write API
	// -------------------------------------------------------------------------

	/**
	 * Apply a complete brand kit (colors + typography + optional custom colors +
	 * theme-style defaults) to the active Elementor kit. The neutral, license-
	 * agnostic orchestrator shared by the free admin apply flow, the Pro service,
	 * and the MCP brand-kit ability. Each underlying write enforces the
	 * `manage_options` capability gate, so this stays a pure sequencer.
	 *
	 * @since 1.9.0
	 *
	 * @param array $kit A kit entry (slug/title/colors/typography[/custom_colors]).
	 * @return array|WP_Error Summary of what was applied.
	 */
	public static function apply_kit( array $kit ) {
		if ( empty( $kit['colors'] ) || ! is_array( $kit['colors'] ) || empty( $kit['typography'] ) || ! is_array( $kit['typography'] ) ) {
			return new WP_Error( 'invalid_kit', __( 'This brand kit is missing its colors or typography data.', 'elementor-mcp' ) );
		}

		// 1) System colors.
		$colors_result = self::replace_system_colors( $kit['colors'] );
		if ( is_wp_error( $colors_result ) ) {
			return $colors_result;
		}

		// 2) System typography.
		$typo_result = self::replace_system_typography( $kit['typography'] );
		if ( is_wp_error( $typo_result ) ) {
			return $typo_result;
		}

		// 3) Optional named custom colors.
		$custom_added = 0;
		if ( ! empty( $kit['custom_colors'] ) && is_array( $kit['custom_colors'] ) ) {
			$custom_result = self::replace_brand_custom_colors( $kit['custom_colors'] );
			if ( is_wp_error( $custom_result ) ) {
				return $custom_result;
			}
			$custom_added = (int) ( $custom_result['custom_colors_added'] ?? 0 );
		}

		// 4) Theme Style defaults — the step that actually re-skins the site.
		$theme_result = self::apply_theme_style( $kit['colors'], $kit['typography'] );
		if ( is_wp_error( $theme_result ) ) {
			return $theme_result;
		}

		return array(
			'success'             => true,
			'kit_slug'            => isset( $kit['slug'] ) ? (string) $kit['slug'] : '',
			'kit_title'           => isset( $kit['title'] ) ? (string) $kit['title'] : '',
			'colors_applied'      => (int) ( $colors_result['colors_applied'] ?? 0 ),
			'typography_applied'  => (int) ( $typo_result['typography_applied'] ?? 0 ),
			'custom_colors_added' => $custom_added,
		);
	}

	/**
	 * Replace all four system color slots atomically.
	 *
	 * @since 1.8.0
	 *
	 * @param array $colors Object keyed by slot ('primary'|'secondary'|'text'|'accent'),
	 *                      each value `[ 'title' => string, 'color' => '#hex' ]`.
	 * @return array|WP_Error { colors_applied: int } on success.
	 */
	public static function replace_system_colors( array $colors ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'insufficient_capability', __( 'You need the manage_options capability to apply brand kits.', 'elementor-mcp' ) );
		}

		$kit = self::get_kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}

		$entries = array();
		foreach ( self::SYSTEM_SLOTS as $slot ) {
			if ( ! isset( $colors[ $slot ] ) || ! is_array( $colors[ $slot ] ) ) {
				return new WP_Error(
					'incomplete_palette',
					/* translators: %s: missing system color slot name */
					sprintf( __( 'Brand kit colors must include all four system slots. Missing: %s.', 'elementor-mcp' ), $slot )
				);
			}

			$hex = sanitize_hex_color( (string) ( $colors[ $slot ]['color'] ?? '' ) );
			if ( empty( $hex ) ) {
				return new WP_Error(
					'invalid_color',
					/* translators: %s: system color slot name */
					sprintf( __( 'The "%s" slot has an invalid hex color. Aborting — a brand kit replaces the whole palette or none of it.', 'elementor-mcp' ), $slot )
				);
			}

			$title = sanitize_text_field( (string) ( $colors[ $slot ]['title'] ?? ucfirst( $slot ) ) );

			$entries[] = array(
				'_id'   => $slot,
				'title' => '' !== $title ? $title : ucfirst( $slot ),
				'color' => $hex,
			);
		}

		if ( ! self::persist( $kit, array( 'system_colors' => $entries ) ) ) {
			return new WP_Error( 'persist_failed', __( 'Could not persist the new system colors to the Elementor kit.', 'elementor-mcp' ) );
		}

		return array( 'colors_applied' => count( $entries ) );
	}

	/**
	 * Replace all four system typography slots atomically, with a full
	 * per-control-type reset on every sub-field (§ 4.3).
	 *
	 * @since 1.8.0
	 *
	 * @param array $typography Object keyed by slot, each value using the
	 *                          master-file typography shape (unprefixed keys).
	 * @return array|WP_Error { typography_applied: int } on success.
	 */
	public static function replace_system_typography( array $typography ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'insufficient_capability', __( 'You need the manage_options capability to apply brand kits.', 'elementor-mcp' ) );
		}

		$kit = self::get_kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}

		$entries = array();
		foreach ( self::SYSTEM_SLOTS as $slot ) {
			if ( ! isset( $typography[ $slot ] ) || ! is_array( $typography[ $slot ] ) ) {
				return new WP_Error(
					'incomplete_typography',
					/* translators: %s: missing system typography slot name */
					sprintf( __( 'Brand kit typography must include all four system slots. Missing: %s.', 'elementor-mcp' ), $slot )
				);
			}

			$entries[] = self::build_typography_entry( $slot, $typography[ $slot ] );
		}

		if ( ! self::persist( $kit, array( 'system_typography' => $entries ) ) ) {
			return new WP_Error( 'persist_failed', __( 'Could not persist the new system typography to the Elementor kit.', 'elementor-mcp' ) );
		}

		return array( 'typography_applied' => count( $entries ) );
	}

	/**
	 * Apply the cleanup contract (§ 2.1.1) to the kit's `custom_colors`:
	 * drop every existing `_brand_kit_*` entry, then append the new kit's
	 * custom colors (each `_id` prefixed). User-added tokens (no prefix) are
	 * never touched.
	 *
	 * @since 1.8.0
	 *
	 * @param array $custom_colors List of `[ 'title' => string, 'color' => '#hex' ]`.
	 * @return array|WP_Error { custom_colors_added: int } on success.
	 */
	public static function replace_brand_custom_colors( array $custom_colors ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'insufficient_capability', __( 'You need the manage_options capability to apply brand kits.', 'elementor-mcp' ) );
		}

		$kit = self::get_kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}

		$settings  = $kit->get_settings();
		$existing  = isset( $settings['custom_colors'] ) && is_array( $settings['custom_colors'] ) ? $settings['custom_colors'] : array();

		// Drop prior brand-kit-owned entries; keep everything the user added.
		$kept = array();
		foreach ( $existing as $entry ) {
			$id = isset( $entry['_id'] ) ? (string) $entry['_id'] : '';
			if ( 0 === strpos( $id, self::BRAND_PREFIX ) ) {
				continue;
			}
			$kept[] = $entry;
		}

		$added = 0;
		foreach ( $custom_colors as $color ) {
			if ( ! is_array( $color ) ) {
				continue;
			}
			$hex = sanitize_hex_color( (string) ( $color['color'] ?? '' ) );
			if ( empty( $hex ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $color['title'] ?? '' ) );
			$slug  = sanitize_key( '' !== $title ? $title : 'color' );

			$kept[] = array(
				'_id'   => self::BRAND_PREFIX . $slug . '_' . ( $added + 1 ),
				'title' => '' !== $title ? $title : __( 'Brand Color', 'elementor-mcp' ),
				'color' => $hex,
			);
			$added++;
		}

		if ( ! self::persist( $kit, array( 'custom_colors' => array_values( $kept ) ) ) ) {
			return new WP_Error( 'persist_failed', __( 'Could not persist the brand custom colors to the Elementor kit.', 'elementor-mcp' ) );
		}

		return array( 'custom_colors_added' => $added );
	}

	/**
	 * Apply the kit's fonts & colors to the active kit's THEME STYLE DEFAULTS.
	 *
	 * This is what actually re-skins the site. The four system_* tokens are only
	 * referenced by elements that opt into them; the Theme Style defaults
	 * (body / h1–h6 typography + body/heading/link colors) drive the default
	 * appearance of every page, which is what users expect a brand kit to change.
	 *
	 * We set font-family + font-weight (and `typography_typography = 'custom'`)
	 * but deliberately leave font-size / line-height unset so the theme's and
	 * each element's own sizing is preserved — the kit changes the *typeface and
	 * palette*, not the layout. Heading colors use `secondary` and body uses
	 * `text` (both guaranteed ≥ 4.5:1 on white by the build's contrast gate),
	 * links use `primary`, link-hover uses `accent`.
	 *
	 * @since 1.8.0
	 *
	 * @param array $colors     The kit `colors` object (4 system slots).
	 * @param array $typography The kit `typography` object (4 system slots).
	 * @return array|WP_Error { theme_style_applied: true } on success.
	 */
	public static function apply_theme_style( array $colors, array $typography ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'insufficient_capability', __( 'You need the manage_options capability to apply brand kits.', 'elementor-mcp' ) );
		}

		$kit = self::get_kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}

		$heading_family = sanitize_text_field( (string) ( $typography['primary']['font_family'] ?? '' ) );
		$heading_weight = sanitize_text_field( (string) ( $typography['primary']['font_weight'] ?? '' ) );
		$body_family    = sanitize_text_field( (string) ( $typography['text']['font_family'] ?? '' ) );
		$body_weight    = sanitize_text_field( (string) ( $typography['text']['font_weight'] ?? '' ) );

		$text_color      = sanitize_hex_color( (string) ( $colors['text']['color'] ?? '' ) );
		$secondary_color = sanitize_hex_color( (string) ( $colors['secondary']['color'] ?? '' ) );
		$primary_color   = sanitize_hex_color( (string) ( $colors['primary']['color'] ?? '' ) );
		$accent_color    = sanitize_hex_color( (string) ( $colors['accent']['color'] ?? '' ) );

		if ( '' === $body_family || '' === $heading_family || empty( $text_color ) ) {
			return new WP_Error( 'incomplete_theme_style', __( 'Brand kit is missing the fonts or text color needed to set theme defaults.', 'elementor-mcp' ) );
		}

		$settings = array(
			// Body text.
			'body_color'                  => $text_color,
			'body_typography_typography'  => 'custom',
			'body_typography_font_family' => $body_family,
			'body_typography_font_weight' => $body_weight,
			// Links.
			'link_normal_color'           => $primary_color ? $primary_color : $text_color,
			'link_hover_color'            => $accent_color ? $accent_color : $text_color,
		);

		// Headings h1–h6: heading typeface + brand heading color.
		$heading_color = $secondary_color ? $secondary_color : $text_color;
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			$settings[ $tag . '_color' ]                  = $heading_color;
			$settings[ $tag . '_typography_typography' ]  = 'custom';
			$settings[ $tag . '_typography_font_family' ] = $heading_family;
			$settings[ $tag . '_typography_font_weight' ] = $heading_weight;
		}

		if ( ! self::persist( $kit, $settings ) ) {
			return new WP_Error( 'persist_failed', __( 'Could not persist the theme style defaults to the Elementor kit.', 'elementor-mcp' ) );
		}

		return array( 'theme_style_applied' => true );
	}

	/**
	 * Read the current kit globals for backup. Captures the four arrays the
	 * restore flow can roll back: system + custom colors and typography.
	 *
	 * @since 1.8.0
	 *
	 * @return array|WP_Error
	 */
	public static function snapshot() {
		$kit = self::get_kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}

		$settings = $kit->get_settings();

		// Capture the theme-style default keys too (null when unset, so restore
		// can clear them back to "no override" if the kit added them).
		$theme_style = array();
		foreach ( self::get_theme_style_keys() as $key ) {
			$theme_style[ $key ] = array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
		}

		return array(
			'system_colors'     => isset( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ? $settings['system_colors'] : array(),
			'custom_colors'     => isset( $settings['custom_colors'] ) && is_array( $settings['custom_colors'] ) ? $settings['custom_colors'] : array(),
			'system_typography' => isset( $settings['system_typography'] ) && is_array( $settings['system_typography'] ) ? $settings['system_typography'] : array(),
			'custom_typography' => isset( $settings['custom_typography'] ) && is_array( $settings['custom_typography'] ) ? $settings['custom_typography'] : array(),
			'theme_style'       => $theme_style,
		);
	}

	/**
	 * The kit Theme Style default keys a brand kit writes (and a restore must be
	 * able to revert). Built dynamically since h1–h6 share a key pattern.
	 *
	 * @since 1.8.0
	 *
	 * @return string[]
	 */
	public static function get_theme_style_keys(): array {
		$keys = array(
			'body_color',
			'body_typography_typography',
			'body_typography_font_family',
			'body_typography_font_weight',
			'link_normal_color',
			'link_hover_color',
		);
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			$keys[] = $tag . '_color';
			$keys[] = $tag . '_typography_typography';
			$keys[] = $tag . '_typography_font_family';
			$keys[] = $tag . '_typography_font_weight';
		}
		return $keys;
	}

	/**
	 * Restore a previously captured snapshot.
	 *
	 * @since 1.8.0
	 *
	 * @param array $snapshot      A snapshot produced by snapshot().
	 * @param bool  $full_clobber  When true, restore custom_colors / custom_typography
	 *                             exactly as captured. When false (default), only
	 *                             `_brand_kit_*` custom entries are rolled back; tokens
	 *                             the user added after the backup are preserved.
	 * @return array|WP_Error
	 */
	public static function restore_snapshot( array $snapshot, bool $full_clobber = false ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'insufficient_capability', __( 'You need the manage_options capability to restore brand kits.', 'elementor-mcp' ) );
		}

		$kit = self::get_kit();
		if ( is_wp_error( $kit ) ) {
			return $kit;
		}

		$current = $kit->get_settings();
		$write   = array();

		// System slots are always fully restored — that's what the user signed
		// up to roll back.
		if ( isset( $snapshot['system_colors'] ) && is_array( $snapshot['system_colors'] ) ) {
			$write['system_colors'] = $snapshot['system_colors'];
		}
		if ( isset( $snapshot['system_typography'] ) && is_array( $snapshot['system_typography'] ) ) {
			$write['system_typography'] = $snapshot['system_typography'];
		}

		// Custom arrays: selective by default, full clobber on request.
		foreach ( array( 'custom_colors', 'custom_typography' ) as $key ) {
			$snap_val = isset( $snapshot[ $key ] ) && is_array( $snapshot[ $key ] ) ? $snapshot[ $key ] : array();

			if ( $full_clobber ) {
				$write[ $key ] = $snap_val;
				continue;
			}

			// Selective: keep current non-brand entries, restore brand entries
			// from the snapshot.
			$current_val = isset( $current[ $key ] ) && is_array( $current[ $key ] ) ? $current[ $key ] : array();

			$kept = array();
			foreach ( $current_val as $entry ) {
				$id = isset( $entry['_id'] ) ? (string) $entry['_id'] : '';
				if ( 0 === strpos( $id, self::BRAND_PREFIX ) ) {
					continue; // Drop current brand entry; snapshot's takes over.
				}
				$kept[] = $entry;
			}
			foreach ( $snap_val as $entry ) {
				$id = isset( $entry['_id'] ) ? (string) $entry['_id'] : '';
				if ( 0 === strpos( $id, self::BRAND_PREFIX ) ) {
					$kept[] = $entry;
				}
			}
			$write[ $key ] = array_values( $kept );
		}

		// Theme-style defaults: restore each captured value, or clear it back to
		// "no override" (empty string) when it was unset before the kit applied.
		// Older backups without a theme_style block simply skip this — their
		// system/custom restore still works.
		if ( isset( $snapshot['theme_style'] ) && is_array( $snapshot['theme_style'] ) ) {
			foreach ( self::get_theme_style_keys() as $key ) {
				if ( ! array_key_exists( $key, $snapshot['theme_style'] ) ) {
					continue;
				}
				$val           = $snapshot['theme_style'][ $key ];
				$write[ $key ] = ( null === $val ) ? '' : $val;
			}
		}

		if ( empty( $write ) ) {
			return new WP_Error( 'empty_snapshot', __( 'The backup did not contain any global settings to restore.', 'elementor-mcp' ) );
		}

		if ( ! self::persist( $kit, $write ) ) {
			return new WP_Error( 'persist_failed', __( 'Could not persist the restored global settings to the Elementor kit.', 'elementor-mcp' ) );
		}

		return array( 'restored' => array_keys( $write ) );
	}

	// -------------------------------------------------------------------------
	// Internals
	// -------------------------------------------------------------------------

	/**
	 * Resolve the active Elementor kit document.
	 *
	 * @since 1.8.0
	 *
	 * @return \Elementor\Core\Kits\Documents\Kit|WP_Error
	 */
	private static function get_kit() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->kits_manager ) ) {
			return new WP_Error( 'no_elementor', __( 'Elementor is not available.', 'elementor-mcp' ) );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit || ! $kit->get_id() ) {
			return new WP_Error( 'kit_not_found', __( 'Active Elementor kit not found.', 'elementor-mcp' ) );
		}

		return $kit;
	}

	/**
	 * Build one prefixed-key system_typography entry from a master-file slot,
	 * applying the full per-control-type reset (§ 4.3).
	 *
	 * @since 1.8.0
	 *
	 * @param string $slot The system slot id.
	 * @param array  $data Master-file typography values for the slot.
	 * @return array
	 */
	private static function build_typography_entry( string $slot, array $data ): array {
		$title = sanitize_text_field( (string) ( $data['title'] ?? ucfirst( $slot ) ) );

		$entry = array(
			'_id'                   => $slot,
			'title'                 => '' !== $title ? $title : ucfirst( $slot ),
			'typography_typography' => 'custom', // Activates the overrides.
		);

		foreach ( self::TYPO_FIELDS as $field => $type ) {
			$has   = array_key_exists( $field, $data );
			$value = $has ? $data[ $field ] : null;
			$blank = ( null === $value || '' === $value || ( is_array( $value ) && empty( $value ) ) );

			if ( 'slider' === $type ) {
				// Slider: pass through a well-formed size object, else reset to
				// empty array (Elementor's "no override"), NEVER {size:0}.
				if ( ! $blank && is_array( $value ) && isset( $value['size'] ) && '' !== $value['size'] ) {
					$entry[ 'typography_' . $field ] = array(
						'unit'  => isset( $value['unit'] ) ? sanitize_text_field( (string) $value['unit'] ) : 'px',
						'size'  => is_numeric( $value['size'] ) ? $value['size'] + 0 : '',
						'sizes' => array(),
					);
				} else {
					$entry[ 'typography_' . $field ] = array();
				}
			} else {
				// font / select: a plain string, else empty string.
				$entry[ 'typography_' . $field ] = $blank ? '' : sanitize_text_field( (string) $value );
			}
		}

		return $entry;
	}

	/**
	 * Persist a partial settings array onto the kit with a verified fallback.
	 *
	 * `Document::save()` returns false under the WP-CLI stdio bridge and the
	 * HTTP proxy, so we (1) write via the Kit API, (2) re-read the saved
	 * page-settings meta to confirm the keys round-tripped, and (3) fall back
	 * to a direct `update_post_meta()` merge if they didn't. Returns whether
	 * the write was confirmed.
	 *
	 * @since 1.8.0
	 *
	 * @param \Elementor\Core\Kits\Documents\Kit $kit      The active kit document.
	 * @param array                              $settings Partial settings to write.
	 * @return bool
	 */
	private static function persist( $kit, array $settings ): bool {
		$kit_id = $kit->get_id();

		// Primary path: the Kit settings API.
		$kit->update_settings( $settings );

		if ( self::meta_matches( $kit_id, $settings ) ) {
			self::clear_cache();
			return true;
		}

		// Fallback: merge directly into the page-settings meta. Elementor stores
		// this as a (serialized) PHP array, so we write an array, not JSON.
		$saved  = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$saved  = is_array( $saved ) ? $saved : array();
		$merged = array_merge( $saved, $settings );

		update_post_meta( $kit_id, '_elementor_page_settings', $merged );

		// Drop Elementor's in-memory document cache so a subsequent read of the
		// kit reflects what we just wrote.
		if ( isset( \Elementor\Plugin::$instance->documents ) && method_exists( \Elementor\Plugin::$instance->documents, 'get' ) ) {
			\Elementor\Plugin::$instance->documents->get( $kit_id, false );
		}

		self::clear_cache();

		return self::meta_matches( $kit_id, $settings );
	}

	/**
	 * Confirm that every key in $settings round-tripped to the saved kit meta.
	 *
	 * @since 1.8.0
	 *
	 * @param int   $kit_id   The kit post ID.
	 * @param array $settings The keys/values to verify.
	 * @return bool
	 */
	private static function meta_matches( int $kit_id, array $settings ): bool {
		$saved = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $saved ) ) {
			return false;
		}
		foreach ( $settings as $key => $value ) {
			if ( ! array_key_exists( $key, $saved ) ) {
				return false;
			}
			// Loose comparison handles nested arrays structurally.
			if ( $saved[ $key ] != $value ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				return false;
			}
		}
		return true;
	}

	/**
	 * Trigger Elementor's CSS regeneration so the new tokens take effect.
	 *
	 * @since 1.8.0
	 */
	private static function clear_cache(): void {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}
