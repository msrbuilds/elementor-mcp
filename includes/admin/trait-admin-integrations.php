<?php
/**
 * Platform grouping, integration availability, and requirement labels.
 *
 * Internal implementation of EMCP_Tools_Admin; loaded by class-admin.php.
 * Methods retain the admin class scope for existing callbacks and callers.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Platform grouping, integration availability, and requirement labels.
 */
trait EMCP_Tools_Admin_Integrations_Trait {

	/**
	 * True when BeTheme is the active theme.
	 *
	 * Keys off the template rather than the stylesheet so a child theme of
	 * BeTheme counts, which is how most production BeTheme sites are built.
	 *
	 * @since 3.14.0
	 * @return bool
	 */
	public static function betheme_available(): bool {
		return 'betheme' === strtolower( (string) get_template() );
	}

	/**
	 * The ordered platform sub-tabs for the Tools page. Keyed by the `platform`
	 * value a category carries; the value is the display label. A future page
	 * builder is added by giving its categories a new platform value and adding
	 * a matching entry here.
	 *
	 * @since 3.0.0
	 * @return array<string,string>
	 */
	public static function platform_tabs(): array {
		return array(
			'elementor' => __( 'Elementor', 'emcp-tools' ),
			'bebuilder' => __( 'BeBuilder', 'emcp-tools' ),
			'bricks'    => __( 'Bricks', 'emcp-tools' ),
			'breakdance' => __( 'Breakdance', 'emcp-tools' ),
			'beaver' => __('Beaver Builder','emcp-tools'),
			'visual-composer' => __('Visual Composer','emcp-tools'),
			'wpbakery' => __('WPBakery','emcp-tools'),
			'kirki' => __('Kirki','emcp-tools'),
			'oxygen' => __('Oxygen','emcp-tools'),
			'avada' => __( 'Avada', 'emcp-tools' ),
			'divi' => __( 'Divi', 'emcp-tools' ),
			'thrive' => __( 'Thrive Architect', 'emcp-tools' ),
			'spectra' => __( 'Spectra', 'emcp-tools' ),
			'kadence-blocks' => __( 'Kadence Blocks', 'emcp-tools' ),
			'generateblocks' => __( 'GenerateBlocks', 'emcp-tools' ),
			'blocksy-blocks' => __( 'Blocksy Blocks', 'emcp-tools' ),
			'otter' => __( 'Otter Blocks', 'emcp-tools' ),
			'wordpress' => __( 'WordPress', 'emcp-tools' ),
			'plugins'   => __( 'Plugins', 'emcp-tools' ),
			'themes'    => __( 'Themes', 'emcp-tools' ),
			'gutenberg' => __( 'Gutenberg', 'emcp-tools' ),
			// EMCP's own subsystems: features this plugin implements rather than
			// WordPress APIs it drives. Keeps the WordPress tab to core management.
			'modules'   => __( 'EMCP Modules', 'emcp-tools' ),
		);
	}

	/** Tabs belonging to integrations that can currently expose tools. */
	public static function visible_platform_tabs(): array {
		return array_filter( self::platform_tabs(), static function ( string $id ): bool {
			return (! isset( EMCP_Tools_Page_Builders::catalog()[ $id ] ) && ! isset( EMCP_Tools_Page_Builders::block_packs()[ $id ] )) || EMCP_Tools_Page_Builders::enabled( $id );
		}, ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Plugin-integration groups, in display order. Categories on the Plugins tab
	 * carry a `group` key naming one of these; the Tools screen clusters each
	 * plugin card under its group heading so the tab stays organized as the
	 * number of integrations grows. A category with no (or an unknown) group
	 * renders inline, ungrouped.
	 *
	 * @since 3.4.3
	 * @return array<string,array{label:string,desc:string}>
	 */
	public static function plugin_groups(): array {
		return array(
			'dynamic'   => array(
				'label' => __( 'Dynamic Content', 'emcp-tools' ),
				'desc'  => __( 'Custom fields & metadata, read and write dynamic content.', 'emcp-tools' ),
			),
			'ecommerce' => array(
				'label' => __( 'E-Commerce', 'emcp-tools' ),
				'desc'  => __( 'Stores, products, orders, and customers.', 'emcp-tools' ),
			),
			'forms'     => array(
				'label' => __( 'Forms', 'emcp-tools' ),
				'desc'  => __( 'Form definitions and submissions.', 'emcp-tools' ),
			),
			'seo'       => array(
				'label' => __( 'SEO', 'emcp-tools' ),
				'desc'  => __( 'Read & write the SEO metadata your SEO plugin stores.', 'emcp-tools' ),
			),
			'multilingual' => array(
				'label' => __( 'Multilingual', 'emcp-tools' ),
				'desc'  => __( 'Languages, translated pages and string translations.', 'emcp-tools' ),
			),
			'tables'       => array(
				'label' => __( 'Tables', 'emcp-tools' ),
				'desc'  => __( 'Tables and their data.', 'emcp-tools' ),
			),
			'lms'          => array(
				'label' => __( 'LMS', 'emcp-tools' ),
				'desc'  => __( 'Courses, lessons and students.', 'emcp-tools' ),
			),
			'events'       => array(
				'label' => __( 'Events and bookings', 'emcp-tools' ),
				'desc'  => __( 'Events, venues, organizers, services and appointments.', 'emcp-tools' ),
			),
			'marketing'    => array(
				'label' => __( 'CRM and email marketing', 'emcp-tools' ),
				'desc'  => __( 'Contacts, lists, campaigns and automations.', 'emcp-tools' ),
			),
			'addons'    => array(
				'label' => __( 'Elementor Addons', 'emcp-tools' ),
				'desc'  => __( 'Discover addon widget packs, and manage Ultimate Addons for Elementor templates.', 'emcp-tools' ),
			),
			'other'     => array(
				'label' => __( 'Other Integrations', 'emcp-tools' ),
				'desc'  => __( 'Additional plugin integrations.', 'emcp-tools' ),
			),
		);
	}

	/**
	 * Group a tool-category map into one bucket per platform tab, preserving
	 * category order within each bucket. A category with a missing or unknown
	 * `platform` falls into the default ('elementor') bucket.
	 *
	 * @since 3.0.0
	 * @param array $categories Category map (id => category array) from get_all_tools().
	 * @return array<string,array> [ 'elementor' => [...], 'wordpress' => [...] ]
	 */
	public static function partition_by_platform( array $categories ): array {
		$buckets = array();
		foreach ( array_keys( self::platform_tabs() ) as $tab_id ) {
			$buckets[ $tab_id ] = array();
		}
		foreach ( $categories as $id => $cat ) {
			$platform = ( isset( $cat['platform'] ) && isset( $buckets[ $cat['platform'] ] ) ) ? $cat['platform'] : 'elementor';
			$buckets[ $platform ][ $id ] = $cat;
		}
		// Sort danger categories (filesystem/database) to the end of their tab —
		// the most powerful/destructive groups live at the bottom. Relative order
		// is otherwise preserved.
		foreach ( $buckets as $tab_id => $cats ) {
			$normal = array();
			$danger = array();
			foreach ( $cats as $id => $cat ) {
				if ( ! empty( $cat['danger'] ) ) {
					$danger[ $id ] = $cat;
				} else {
					$normal[ $id ] = $cat;
				}
			}
			$buckets[ $tab_id ] = $normal + $danger;
		}
		return $buckets;
	}

	/**
	 * Whether a tool category belongs to the Elementor platform (the default
	 * when no platform key is set), i.e. it is unavailable when Elementor
	 * is inactive.
	 *
	 * @since 3.0.0
	 *
	 * @param array $category A get_all_tools() category entry.
	 * @return bool
	 */
	public static function is_elementor_category( array $category ): bool {
		return 'elementor' === ( $category['platform'] ?? 'elementor' );
	}

	/**
	 * Whether the Astra theme integration's tools are available (Astra is the
	 * active parent theme). When false the admin greys out + disables the Astra
	 * toggles, the same way Elementor tools are gated when Elementor is inactive.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public static function astra_available(): bool {
		return function_exists( 'get_template' ) && 'astra' === get_template();
	}

	/**
	 * Whether the Kadence theme integration's tools are available (Kadence is the
	 * active theme). When false the admin greys out + disables the Kadence
	 * theme-settings toggles.
	 *
	 * @since 3.9.0
	 * @return bool
	 */
	public static function kadence_available(): bool {
		return function_exists( 'get_template' ) && 'kadence' === get_template();
	}

	/**
	 * Whether the Kadence Blocks integration's tools are available (the Kadence
	 * Blocks plugin is active — independent of the active theme).
	 *
	 * @since 3.9.0
	 * @return bool
	 */
	public static function kadence_blocks_available(): bool {
		return class_exists( 'EMCP_Tools_Kadence_Blocks_Catalog' ) && EMCP_Tools_Kadence_Blocks_Catalog::is_active();
	}

	/**
	 * Whether the GeneratePress theme integration's tools are available
	 * (GeneratePress is the active theme; Pro).
	 *
	 * @since 3.9.1
	 * @return bool
	 */
	public static function generatepress_available(): bool {
		return function_exists( 'get_template' ) && 'generatepress' === get_template();
	}

	/**
	 * Whether the GenerateBlocks integration's tools are available (the
	 * GenerateBlocks plugin is active; Pro).
	 *
	 * @since 3.9.1
	 * @return bool
	 */
	public static function generateblocks_available(): bool {
		return class_exists( 'EMCP_Tools_GenerateBlocks_Catalog' ) && EMCP_Tools_GenerateBlocks_Catalog::is_active();
	}

	/**
	 * Whether the Blocksy blocks integration's tools are available (Blocksy
	 * Companion active; Pro).
	 *
	 * @since 3.9.1
	 * @return bool
	 */
	public static function blocksy_blocks_available(): bool {
		return class_exists( 'EMCP_Tools_Blocksy_Blocks_Catalog' ) && EMCP_Tools_Blocksy_Blocks_Catalog::is_active();
	}

	/**
	 * Whether the Blocksy extensions integration's tools are available (the Blocksy
	 * ExtensionsManager exists; Pro).
	 *
	 * @since 3.9.1
	 * @return bool
	 */
	public static function blocksy_extensions_available(): bool {
		return class_exists( '\\Blocksy\\ExtensionsManager' );
	}

	/**
	 * Whether the WooCommerce integration's tools are available (WooCommerce
	 * installed and active).
	 *
	 * @since 3.4.2
	 * @return bool
	 */
	public static function woo_available(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/** @since 3.16.0 */
	public static function funnelkit_available(): bool {
		return defined( 'WFFN_VERSION' ) && function_exists( 'WFFN_Core' );
	}

	/** Same detector as EMCP_Tools_FunnelKit_Automations::automations_active(). @since 3.19.0 */
	public static function funnelkit_automations_available(): bool {
		return defined( 'BWFAN_VERSION' ) && class_exists( 'BWFAN_Core' );
	}

	/** Same detector as EMCP_Tools_TablePress_Integration::tablepress_active(). @since 3.19.0 */
	public static function tablepress_available(): bool {
		return class_exists( 'TablePress' );
	}

	/** Same detector as EMCP_Tools_Tutor_Integration::tutor_active(). @since 3.19.0 */
	public static function tutor_available(): bool {
		return function_exists( 'tutor' ) && defined( 'TUTOR_VERSION' );
	}

	/** Same detector as EMCP_Tools_FluentCRM_Integration::fluentcrm_active(). @since 3.19.0 */
	public static function fluentcrm_available(): bool {
		return defined( 'FLUENTCRM_PLUGIN_VERSION' ) && function_exists( 'fluentCrmDb' );
	}

	/** Same detector as EMCP_Tools_Amelia_Integration::amelia_active(). @since 3.19.0 */
	public static function amelia_available(): bool {
		return defined( 'AMELIA_VERSION' ) && class_exists( 'AmeliaBooking\Plugin' );
	}

	/** Same detector as EMCP_Tools_TEC_Integration::tec_active(). @since 3.19.0 */
	public static function tec_available(): bool {
		return class_exists( 'Tribe__Events__Main' );
	}

	/** Same detector as EMCP_Tools_LifterLMS_Integration::lifterlms_active(). @since 3.19.0 */
	public static function lifterlms_available(): bool {
		return function_exists( 'llms' );
	}

	/** Same detector as EMCP_Tools_TranslatePress_Integration::translatepress_active(). @since 3.19.0 */
	public static function translatepress_available(): bool {
		return class_exists( 'TRP_Translate_Press' );
	}

	/** Same detector as EMCP_Tools_Polylang_Integration::polylang_active(). @since 3.19.0 */
	public static function polylang_available(): bool {
		return function_exists( 'pll_languages_list' ) && function_exists( 'PLL' );
	}

	/**
	 * Form-plugin availability — mirrors each adapter's is_active() so the admin
	 * card greys out its toggles when the plugin is inactive. Detection is
	 * reconciled with the adapter's own is_active() in the adapter tasks.
	 *
	 * @since 3.5.0
	 */
	public static function cf7_available(): bool {
		return class_exists( 'WPCF7_ContactForm' ) || defined( 'WPCF7_VERSION' );
	}

	/** @since 3.5.0 */
	public static function wpforms_available(): bool {
		return function_exists( 'wpforms' );
	}

	/** @since 3.5.0 */
	public static function gravityforms_available(): bool {
		return class_exists( 'GFForms' ) || class_exists( 'GFAPI' );
	}

	/** @since 3.5.0 */
	public static function fluentforms_available(): bool {
		return defined( 'FLUENTFORM_VERSION' ) || function_exists( 'wpFluentForm' );
	}

	/** @since 3.5.0 */
	public static function ninjaforms_available(): bool {
		return function_exists( 'Ninja_Forms' );
	}

	/** @since 3.5.0 */
	public static function formidable_available(): bool {
		return class_exists( 'FrmForm' ) || class_exists( 'FrmAppHelper' );
	}

	/** @since 3.5.0 */
	public static function metform_available(): bool {
		return defined( 'METFORM_VERSION' ) || post_type_exists( 'metform-form' );
	}

	/** @since 3.5.0 */
	public static function sureforms_available(): bool {
		return defined( 'SRFM_VER' ) || post_type_exists( 'sureforms_form' );
	}

	/** @since 3.8.0 */
	public static function forminator_available(): bool {
		return class_exists( 'Forminator_API' ) || defined( 'FORMINATOR_VERSION' );
	}

	/**
	 * SEO-plugin availability — mirrors each adapter's is_active() so the admin
	 * card greys out its toggles when the plugin is inactive. Reconciled with the
	 * adapter's own is_active() in the adapter tasks.
	 *
	 * @since 3.5.0
	 */
	public static function slimseo_available(): bool {
		return defined( 'SLIM_SEO_VER' ) || class_exists( '\\SlimSEO\\Plugin' );
	}

	/**
	 * Whether Visibility (native-aeo-pack) is active.
	 *
	 * @since 3.17.1
	 */
	public static function visibility_available(): bool {
		return defined( 'NATIVE_AEO_PACK_VERSION' );
	}

	/** @since 3.5.0 */
	public static function yoast_available(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	/** @since 3.5.0 */
	public static function rankmath_available(): bool {
		return class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
	}

	/** @since 3.5.0 */
	public static function aioseo_available(): bool {
		return function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' );
	}

	/** @since 3.5.0 */
	public static function seopress_available(): bool {
		return defined( 'SEOPRESS_VERSION' );
	}

	/** @since 3.5.0 */
	public static function seoframework_available(): bool {
		return defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'tsf' );
	}

	/** @since 3.5.0 */
	public static function surerank_available(): bool {
		return defined( 'SURERANK_VERSION' ) || class_exists( '\\SureRank\\Inc\\Meta_Data' );
	}

	/**
	 * The short status a card shows when a tool needs software that is missing.
	 *
	 * A greyed-out toggle looks identical whether the admin switched a tool off
	 * or the tool cannot be switched on at all, and users read the second case as
	 * "broken" or "not in my plan". Naming the missing dependency on the card
	 * removes that ambiguity at a glance.
	 *
	 * @since 3.14.0
	 * @param array $tool A tool entry from the catalog.
	 * @return string Badge text, or '' when the tool is available.
	 */
	public static function requirement_badge( array $tool ): string {
		$name = isset( $tool['requires']['name'] ) ? (string) $tool['requires']['name'] : '';
		if ( '' !== $name ) {
			/* translators: %s: the name of a required plugin or theme */
			return sprintf( __( 'Needs %s', 'emcp-tools' ), $name );
		}
		// Pro-locked tools already say so in their own badge, so a second one
		// would be noise.
		if ( in_array( 'pro', (array) ( $tool['badges'] ?? array() ), true ) ) {
			return '';
		}
		return __( 'Unavailable', 'emcp-tools' );
	}

	/**
	 * The sentence explaining what to do about a missing dependency.
	 *
	 * Built from the recorded name rather than written out per tool, so the two
	 * phrasings are translated once instead of twenty-seven times.
	 *
	 * @since 3.14.0
	 * @param array $tool A tool entry from the catalog.
	 * @return string
	 */
	public static function requirement_note( array $tool ): string {
		if ( ! empty( $tool['unavailable_note'] ) ) {
			return (string) $tool['unavailable_note'];
		}
		$name = isset( $tool['requires']['name'] ) ? (string) $tool['requires']['name'] : '';
		if ( '' === $name ) {
			return '';
		}
		if ( 'theme' === ( $tool['requires']['kind'] ?? 'plugin' ) ) {
			/* translators: %s: theme name */
			return sprintf( __( 'This tool works with the %s theme, which is not the active theme on this site.', 'emcp-tools' ), $name );
		}
		/* translators: %s: plugin name */
		return sprintf( __( 'This tool reads %s, which is not installed and active on this site.', 'emcp-tools' ), $name );
	}

	/**
	 * True when Essential Addons (Lite or Pro) is active.
	 *
	 * @since 3.6.0
	 * @return bool
	 */
	public static function essential_addons_available(): bool {
		return defined( 'EAEL_PLUGIN_VERSION' )
			|| class_exists( '\Essential_Addons_Elementor\Classes\Bootstrap' );
	}

	/**
	 * True when Premium Addons is active.
	 *
	 * @since 3.6.0
	 * @return bool
	 */
	public static function premium_addons_available(): bool {
		return defined( 'PREMIUM_ADDONS_VERSION' )
			|| defined( 'PREMIUM_ADDONS_FILE' )
			|| class_exists( 'PremiumAddons\Includes\Addons_Integration' );
	}

	/**
	 * True when the free Ultimate Addons for Elementor plugin (formerly Header
	 * Footer Elementor) is active. Its own identifiers still say HFE.
	 *
	 * This is what gates TEMPLATES: the `elementor-hf` CPT and its `ehf_*`
	 * display-condition meta belong to the free plugin.
	 *
	 * @since 3.6.2
	 * @return bool
	 */
	public static function uae_templates_available(): bool {
		return class_exists( 'Header_Footer_Elementor' ) || post_type_exists( 'elementor-hf' );
	}

	/**
	 * True when UAE Pro is active.
	 *
	 * UAE Pro ("Ultimate Addons for Elementor Pro", slug `ultimate-elementor`)
	 * is a SEPARATE standalone plugin, not an add-on to the free one, and can be
	 * installed on its own.
	 *
	 * @since 3.6.2
	 * @return bool
	 */
	public static function uae_pro_available(): bool {
		return defined( 'UAEL_VER' ) || class_exists( 'UAEL_Loader' );
	}

	/**
	 * True when either UAE plugin is active.
	 *
	 * @since 3.6.0
	 * @return bool
	 */
	public static function uae_available(): bool {
		return self::uae_templates_available() || self::uae_pro_available();
	}

	/**
	 * Whether Freemius's Affiliation page actually exists right now.
	 *
	 * We hide the Affiliation submenu (see the `is_submenu_visible` filter in
	 * the bootstrap) and link to it from the header instead. Hiding keeps the
	 * page URL-reachable, BUT Freemius only *registers* its submenu pages when
	 * `should_add_submenu_or_action_links()` passes — which is false in
	 * **activation mode**. A fresh install (free especially) sits in activation
	 * mode until the user opts in or skips, so the page doesn't exist yet and
	 * linking to it yields "Sorry, you are not allowed to access this page."
	 * Mirror Freemius's own condition so the link only shows when it works.
	 *
	 * @since 3.4.2
	 * @return bool
	 */
	public static function affiliation_page_available(): bool {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return false;
		}
		$fs = emcp_tools_fs();
		return $fs->has_affiliate_program() && ! $fs->is_activation_mode();
	}

	/**
	 * Whether the Spectra Blocks integration's tools are available (the Spectra
	 * plugin — Ultimate Addons for Gutenberg — is installed and active).
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public static function spectra_available(): bool {
		return class_exists( 'EMCP_Tools_Spectra_Catalog' ) && EMCP_Tools_Spectra_Catalog::is_active();
	}

	/**
	 * Whether Spectra is set to generate separate CSS/JS files (as opposed to its
	 * default inline CSS). In file mode, pages an AI builds or edits over MCP can
	 * render with stale cached CSS until the assets are regenerated — so the combo
	 * section shows a heads-up to switch to inline while building.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public static function spectra_file_generation_on(): bool {
		if ( ! self::spectra_available() ) {
			return false;
		}
		// Spectra's default is inline CSS; treat an absent option as inline.
		if ( class_exists( 'UAGB_Admin_Helper' ) && method_exists( 'UAGB_Admin_Helper', 'get_admin_settings_option' ) ) {
			return 'enabled' === UAGB_Admin_Helper::get_admin_settings_option( '_uagb_allow_file_generation', 'disabled' );
		}
		return 'enabled' === get_option( '_uagb_allow_file_generation', 'disabled' );
	}

	/**
	 * The Astra + Spectra section notice, or null. Returns an actionable warning
	 * only when Spectra's separate-file CSS generation is on (the state that
	 * causes stale styling for AI-built pages).
	 *
	 * @since 3.4.0
	 *
	 * @return array{type:string,message:string}|null
	 */
	public static function spectra_file_generation_notice(): ?array {
		if ( ! self::spectra_file_generation_on() ) {
			return null;
		}
		return array(
			'type'    => 'warning',
			'message' => __( 'Spectra is set to generate separate CSS files. When an AI builds or edits pages over MCP, those cached files can go stale and a page may look unstyled until they are rebuilt. While building with AI, turn OFF Spectra → Settings → Asset Generation → File Generation (use inline CSS), or click "Regenerate Assets" there after edits.', 'emcp-tools' ),
		);
	}

	/**
	 * Returns the categories with the Elementor-platform ones removed. Used for
	 * truthful tool counts when Elementor is inactive (those tools never register).
	 *
	 * @since 3.0.0
	 *
	 * @param array $categories get_all_tools() output.
	 * @return array
	 */
	public static function filter_out_elementor( array $categories ): array {
		return array_filter(
			$categories,
			static function ( $cat ) {
				return ! self::is_elementor_category( $cat );
			}
		);
	}
}
