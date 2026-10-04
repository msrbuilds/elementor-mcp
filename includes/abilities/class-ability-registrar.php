<?php
/**
 * Registers all MCP Tools for Elementor abilities with the WordPress Abilities API.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registrar that coordinates registration of all ability groups.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Ability_Registrar {

	/**
	 * The data access layer.
	 *
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * The element factory.
	 *
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * The schema generator.
	 *
	 * @var EMCP_Tools_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * The settings validator.
	 *
	 * @var EMCP_Tools_Settings_Validator
	 */
	private $validator;

	/**
	 * All registered ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param EMCP_Tools_Data               $data             The data access layer.
	 * @param EMCP_Tools_Element_Factory    $factory          The element factory.
	 * @param EMCP_Tools_Schema_Generator   $schema_generator The schema generator.
	 * @param EMCP_Tools_Settings_Validator $validator        The settings validator.
	 */
	public function __construct(
		EMCP_Tools_Data $data,
		EMCP_Tools_Element_Factory $factory,
		EMCP_Tools_Schema_Generator $schema_generator,
		EMCP_Tools_Settings_Validator $validator
	) {
		$this->data             = $data;
		$this->factory          = $factory;
		$this->schema_generator = $schema_generator;
		$this->validator        = $validator;
	}

	/**
	 * Registers all abilities across all phases.
	 *
	 * Must be called during the `wp_abilities_api_init` action.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $elementor_active Whether Elementor is active. When false, only
	 *                               pure-WordPress tool groups are registered.
	 *                               Default true (preserves prior behavior for any
	 *                               caller that does not pass the flag).
	 *
	 * @return string[] Array of registered ability names.
	 */
	public function register_all( bool $elementor_active = true ): array {
		$profile = defined( 'EMCP_TOOLS_PROFILE_REGISTRATION' ) && EMCP_TOOLS_PROFILE_REGISTRATION;
		$started = $profile ? microtime( true ) : 0.0;
		try {
			$this->register_groups( $elementor_active );
		} catch ( \Throwable $e ) {
			// Ability registration runs on every admin page load and every REST
			// request, so an exception here is a site-wide fatal: wp-admin becomes
			// unreachable and the owner has to recover the site (issue #100, where a
			// host malware scanner had quarantined one of our class files, leaving
			// require_once satisfied but the class undeclared).
			//
			// No single tool group is worth locking an admin out of their own site.
			// Keep whatever registered before the failure and carry on; the tools
			// from the failed group are simply absent.
			if ( function_exists( 'error_log' ) ) {
				error_log( 'EMCP Tools: ability registration stopped early: ' . $e->getMessage() );
			}
		} finally {
			EMCP_Tools_Page_Builders::$registering = '';
		}

		if ( $profile && function_exists( 'error_log' ) ) {
			error_log( sprintf( 'EMCP Tools: ability registration took %.1f ms (%d tools).', ( microtime( true ) - $started ) * 1000, count( $this->ability_names ) ) );
		}

		/**
		 * Filters the registered ability names.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $ability_names The registered ability names.
		 */
		$this->ability_names = apply_filters( 'emcp_tools_ability_names', $this->ability_names );

		// F-013: emcp_tools_ability_names is a public seam, so a third-party
		// callback can hand back non-strings or names that don't match the MCP
		// ability-name grammar. Drop anything invalid before the list reaches
		// create_server(), which would otherwise register broken/undefined tools.
		$this->ability_names = self::sanitize_ability_names( $this->ability_names );

		return $this->ability_names;
	}

	/**
	 * Registers every ability group. Called through register_all()'s guard.
	 *
	 * @param bool $elementor_active Whether the Elementor-dependent groups load.
	 * @return void
	 */
	private function register_groups( bool $elementor_active = true ): void {
		$elementor_active = $elementor_active && EMCP_Tools_Page_Builders::enabled( 'elementor' );
		// ---- Always-on: pure-WordPress tool groups (no Elementor needed) ----

		// Media Library query ability (list/search the site's own uploads).
		$media_library = new EMCP_Tools_Media_Library_Abilities( $this->data );
		$media_library->register();
		$this->ability_names = array_merge( $this->ability_names, $media_library->get_ability_names() );

		// resize-media — only when the Image Optimization module is active (it reuses
		// that module's backup + compress + WebP machinery).
		if ( class_exists( 'EMCP_Tools_Image_Resize_Abilities' )
			&& class_exists( 'EMCP_Tools_Image_Optimization_Module' )
			&& EMCP_Tools_Image_Optimization_Module::module_is_active() ) {
			$resize = new EMCP_Tools_Image_Resize_Abilities();
			$resize->register();
			$this->ability_names = array_merge( $this->ability_names, $resize->get_ability_names() );
		}

		// WordPress Content abilities (posts/pages/CPT CRUD + taxonomy + meta).
		$content = new EMCP_Tools_Content_Abilities();
		$content->register();
		$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );

		// Redirect Manager abilities (301/302 redirects + broken-link scan; no
		// Elementor). Gated on the Redirects module (on by default) — abilities
		// register before the module boots on init:5, so gate on is_enabled().
		if ( class_exists( 'EMCP_Tools_Redirect_Module' ) && EMCP_Tools_Redirect_Module::is_enabled() ) {
			$redirects = new EMCP_Tools_Redirect_Abilities();
			$redirects->register();
			$this->ability_names = array_merge( $this->ability_names, $redirects->get_ability_names() );
		}

		// Gutenberg block abilities (discover blocks/patterns + incremental block-tree edits).
		$gutenberg = new EMCP_Tools_Gutenberg_Abilities();
		$gutenberg->register();
		$this->ability_names = array_merge( $this->ability_names, $gutenberg->get_ability_names() );

		// Page Snapshot — always-on normalized page digest (read foundation).
		$snapshot = new EMCP_Tools_Snapshot_Abilities( $this->data );
		$snapshot->register();
		$this->ability_names = array_merge( $this->ability_names, $snapshot->get_ability_names() );

		// AI-safe transactions — change ledger + rollback (always-on, write foundation).
		$transactions = new EMCP_Tools_Transaction_Abilities();
		$transactions->register();
		$this->ability_names = array_merge( $this->ability_names, $transactions->get_ability_names() );

		// Content search — lexical index over pages/templates/widgets/globals (always-on).
		$search = new EMCP_Tools_Search_Abilities();
		$search->register();
		$this->ability_names = array_merge( $this->ability_names, $search->get_ability_names() );

		// Content mirror — export/restore page content as git-trackable files (always-on).
		$mirror = new EMCP_Tools_Content_Mirror_Abilities();
		$mirror->register();
		$this->ability_names = array_merge( $this->ability_names, $mirror->get_ability_names() );

		// Compact tool mode dispatcher (list-tools/get-tool-schema/call-tool).
		// Registered ALWAYS so wp_get_ability() resolves them, but deliberately
		// NOT added to $this->ability_names — register_mcp_server() surfaces them
		// only when dispatcher mode is on (otherwise they'd double the surface).
		$dispatcher = new EMCP_Tools_Dispatcher_Abilities();
		$dispatcher->register();

		// EMCP Themer MCP tools — only when the (free) Themer module is active.
		// The module boots on init:5, after this runs, so gate on the option directly.
		if ( class_exists( 'EMCP_Tools_Themer_Abilities' )
			&& class_exists( 'EMCP_Tools_Themer_Module' )
			&& EMCP_Tools_Themer_Module::is_enabled() ) {
			$themer = new EMCP_Tools_Themer_Abilities();
			$themer->register();
			$this->ability_names = array_merge( $this->ability_names, $themer->get_ability_names() );
		}

		// Dynamic-source discovery. Gated with the Themer tools above, since a
		// source list is meaningless without templates to bind it into.
		if ( class_exists( 'EMCP_Tools_Themer_Dynamic_Abilities' )
			&& class_exists( 'EMCP_Tools_Themer_Module' )
			&& EMCP_Tools_Themer_Module::is_enabled() ) {
			$themer_dynamic = new EMCP_Tools_Themer_Dynamic_Abilities();
			$themer_dynamic->register();
			$this->ability_names = array_merge( $this->ability_names, $themer_dynamic->get_ability_names() );
		}

		// EMCP Themer PHP-Template MCP tools — only when the feature toggle is on
		// (its own option, independent of the base Themer tools above).
		if ( class_exists( 'EMCP_Tools_Themer_PHP_Abilities' )
			&& class_exists( 'EMCP_Tools_Themer_PHP' )
			&& EMCP_Tools_Themer_PHP::enabled() ) {
			$themer_php = new EMCP_Tools_Themer_PHP_Abilities();
			$themer_php->register();
			$this->ability_names = array_merge( $this->ability_names, $themer_php->get_ability_names() );
		}

		// WordPress Settings abilities (curated site-settings read/update).
		$settings = new EMCP_Tools_Settings_Abilities();
		$settings->register();
		$this->ability_names = array_merge( $this->ability_names, $settings->get_ability_names() );

		// WordPress Plugins & Themes abilities.
		$plugins = new EMCP_Tools_Plugin_Abilities();
		$plugins->register();
		$this->ability_names = array_merge( $this->ability_names, $plugins->get_ability_names() );

		$themes = new EMCP_Tools_Theme_Abilities();
		$themes->register();
		$this->ability_names = array_merge( $this->ability_names, $themes->get_ability_names() );

		// WordPress Users abilities.
		$users = new EMCP_Tools_User_Abilities();
		$users->register();
		$this->ability_names = array_merge( $this->ability_names, $users->get_ability_names() );

		// WordPress Nav Menu abilities (menus, items, theme locations, render).
		$nav_menus = new EMCP_Tools_Nav_Menu_Abilities();
		$nav_menus->register();
		$this->ability_names = array_merge( $this->ability_names, $nav_menus->get_ability_names() );

		// ACF abilities — only when Advanced Custom Fields (free or Pro) is active.
		if ( class_exists( 'EMCP_Tools_ACF_Abilities' ) && EMCP_Tools_ACF_Abilities::acf_active() ) {
			$acf = new EMCP_Tools_ACF_Abilities();
			$acf->register();
			$this->ability_names = array_merge( $this->ability_names, $acf->get_ability_names() );
		}

		// WooCommerce abilities (Pro) — only when WooCommerce is active.
		if ( class_exists( 'EMCP_Tools_Woo_Integration' ) && EMCP_Tools_Woo_Integration::woo_active() ) {
			$woo = new EMCP_Tools_Woo_Integration();
			$woo->register();
			$this->ability_names = array_merge( $this->ability_names, $woo->get_ability_names() );
		}

		// FunnelKit Funnel Builder read abilities (Pro) — only when active.
		if ( class_exists( 'EMCP_Tools_FunnelKit_Integration' ) && EMCP_Tools_FunnelKit_Integration::funnelkit_active() ) {
			$funnelkit = new EMCP_Tools_FunnelKit_Integration();
			$funnelkit->register();
			$this->ability_names = array_merge( $this->ability_names, $funnelkit->get_ability_names() );
			// funnelkit-write (3.19.0): ships disabled; the Tools grid enables it.
			if ( class_exists( 'EMCP_Tools_FunnelKit_Write' ) ) {
				$funnelkit_write = new EMCP_Tools_FunnelKit_Write();
				$funnelkit_write->register();
				$this->ability_names = array_merge( $this->ability_names, $funnelkit_write->get_ability_names() );
			}
		}

		// FunnelKit Automations read and write abilities (Pro, 3.19.0) — only when Automations is active.
		if ( class_exists( 'EMCP_Tools_FunnelKit_Automations' ) && EMCP_Tools_FunnelKit_Automations::automations_active() ) {
			$funnelkit_automations = new EMCP_Tools_FunnelKit_Automations();
			$funnelkit_automations->register();
			$this->ability_names = array_merge( $this->ability_names, $funnelkit_automations->get_ability_names() );
		}

		// Polylang (3.19.0): languages, linked translations and page text, while Polylang is active.
		if ( class_exists( 'EMCP_Tools_Polylang_Integration' ) && EMCP_Tools_Polylang_Integration::polylang_active() ) {
			$polylang = new EMCP_Tools_Polylang_Integration();
			$polylang->register();
			$this->ability_names = array_merge( $this->ability_names, $polylang->get_ability_names() );
		}

		// TranslatePress (3.19.0): page and gettext strings and the translation language, while TranslatePress is active.
		if ( class_exists( 'EMCP_Tools_TranslatePress_Integration' ) && EMCP_Tools_TranslatePress_Integration::translatepress_active() ) {
			$translatepress = new EMCP_Tools_TranslatePress_Integration();
			$translatepress->register();
			$this->ability_names = array_merge( $this->ability_names, $translatepress->get_ability_names() );
		}

		// TablePress (3.19.0): build, edit, import and place tables, while TablePress is active.
		if ( class_exists( 'EMCP_Tools_TablePress_Integration' ) && EMCP_Tools_TablePress_Integration::tablepress_active() ) {
			$tablepress = new EMCP_Tools_TablePress_Integration();
			$tablepress->register();
			$this->ability_names = array_merge( $this->ability_names, $tablepress->get_ability_names() );
		}

		// Tutor LMS (3.19.0): build courses and manage enrolments, while Tutor LMS is active.
		if ( class_exists( 'EMCP_Tools_Tutor_Integration' ) && EMCP_Tools_Tutor_Integration::tutor_active() ) {
			$tutor = new EMCP_Tools_Tutor_Integration();
			$tutor->register();
			$this->ability_names = array_merge( $this->ability_names, $tutor->get_ability_names() );
		}

		// LifterLMS (3.19.0): build courses, memberships and enrolments, while LifterLMS is active.
		if ( class_exists( 'EMCP_Tools_LifterLMS_Integration' ) && EMCP_Tools_LifterLMS_Integration::lifterlms_active() ) {
			$lifterlms = new EMCP_Tools_LifterLMS_Integration();
			$lifterlms->register();
			$this->ability_names = array_merge( $this->ability_names, $lifterlms->get_ability_names() );
		}

		// Meta Box abilities — only when Meta Box (free or extensions) is active.
		if ( class_exists( 'EMCP_Tools_Meta_Box_Abilities' ) && EMCP_Tools_Meta_Box_Abilities::metabox_active() ) {
			$metabox = new EMCP_Tools_Meta_Box_Abilities();
			$metabox->register();
			$this->ability_names = array_merge( $this->ability_names, $metabox->get_ability_names() );
		}

		// Forms-tab integrations. CF7 is free; the five entry-storing plugins are
		// Pro. Each registers only when its plugin is active (is_available()).
		$form_integrations = array();
		if ( class_exists( 'EMCP_Tools_CF7_Integration' ) ) {
			$form_integrations[] = new EMCP_Tools_CF7_Integration();
		}
		if ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) {
			foreach ( array(
				'EMCP_Tools_WPForms_Integration',
				'EMCP_Tools_GravityForms_Integration',
				'EMCP_Tools_FluentForms_Integration',
				'EMCP_Tools_NinjaForms_Integration',
				'EMCP_Tools_Formidable_Integration',
				'EMCP_Tools_MetForm_Integration',
				'EMCP_Tools_SureForms_Integration',
				'EMCP_Tools_Forminator_Integration',
			) as $emcp_form_class ) {
				if ( class_exists( $emcp_form_class ) ) {
					$form_integrations[] = new $emcp_form_class();
				}
			}
		}
		foreach ( $form_integrations as $form_integration ) {
			if ( $form_integration->is_available() ) {
				$form_integration->register();
				$this->ability_names = array_merge( $this->ability_names, $form_integration->get_ability_names() );
			}
		}

		// SEO-plugin integrations. Slim SEO is free; the other 6 are Pro. Each
		// registers only when its SEO plugin is active.
		$seo_integrations = array();
		if ( class_exists( 'EMCP_Tools_SlimSEO_Integration' ) ) {
			$seo_integrations[] = new EMCP_Tools_SlimSEO_Integration();
		}
		if ( class_exists( 'EMCP_Tools_Visibility_Integration' ) ) {
			$seo_integrations[] = new EMCP_Tools_Visibility_Integration();
		}
		if ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) {
			foreach ( array(
				'EMCP_Tools_Yoast_Integration',
				'EMCP_Tools_RankMath_Integration',
				'EMCP_Tools_AIOSEO_Integration',
				'EMCP_Tools_SeoPress_Integration',
				'EMCP_Tools_SEOFramework_Integration',
				'EMCP_Tools_SureRank_Integration',
			) as $emcp_seo_class ) {
				if ( class_exists( $emcp_seo_class ) ) {
					$seo_integrations[] = new $emcp_seo_class();
				}
			}
		}
		foreach ( $seo_integrations as $seo_integration ) {
			if ( $seo_integration->is_available() ) {
				$seo_integration->register();
				$this->ability_names = array_merge( $this->ability_names, $seo_integration->get_ability_names() );
			}
		}

		if ( EMCP_Tools_Page_Builders::enabled( 'thrive' ) ) {
			$thrive = new EMCP_Tools_Thrive_Integration();
			EMCP_Tools_Page_Builders::$registering = 'thrive';
			try { $thrive->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $thrive->get_ability_names() );
		}
		if ( EMCP_Tools_Page_Builders::enabled( 'divi' ) ) {
			$divi = new EMCP_Tools_Divi_Integration();
			EMCP_Tools_Page_Builders::$registering = 'divi';
			try { $divi->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $divi->get_ability_names() );
		}

		if ( EMCP_Tools_Page_Builders::enabled( 'avada' ) ) {
			$avada = new EMCP_Tools_Avada_Integration();
			EMCP_Tools_Page_Builders::$registering = 'avada';
			try { $avada->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $avada->get_ability_names() );
		}

		if ( EMCP_Tools_Page_Builders::enabled( 'beaver' ) ) {
			$beaver = new EMCP_Tools_Beaver_Integration();
			EMCP_Tools_Page_Builders::$registering = 'beaver';
			try { $beaver->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $beaver->get_ability_names() );
		}


		if ( EMCP_Tools_Page_Builders::enabled( 'wpbakery' ) ) {
			$wpbakery = new EMCP_Tools_WPBakery_Integration();
			EMCP_Tools_Page_Builders::$registering = 'wpbakery';
			try { $wpbakery->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $wpbakery->get_ability_names() );
		}


		if ( EMCP_Tools_Page_Builders::enabled( 'kirki' ) ) {
			$kirki = new EMCP_Tools_Kirki_Integration();
			EMCP_Tools_Page_Builders::$registering = 'kirki';
			try { $kirki->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $kirki->get_ability_names() );
		}

		if ( EMCP_Tools_Page_Builders::enabled( 'oxygen' ) ) {
			$oxygen = new EMCP_Tools_Oxygen_Integration();
			EMCP_Tools_Page_Builders::$registering = 'oxygen';
			try { $oxygen->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $oxygen->get_ability_names() );
		}

		if ( EMCP_Tools_Page_Builders::enabled( 'breakdance' ) ) {
			$breakdance = new EMCP_Tools_Breakdance_Integration();
			EMCP_Tools_Page_Builders::$registering = 'breakdance';
			try { $breakdance->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $breakdance->get_ability_names() );
		}

		if ( EMCP_Tools_Page_Builders::enabled( 'bricks' ) ) {
			$bricks = new EMCP_Tools_Bricks_Integration();
			EMCP_Tools_Page_Builders::$registering = 'bricks';
			try { $bricks->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $bricks->get_ability_names() );
		}

		// Independently enabled Gutenberg extension integrations.
		foreach ( EMCP_Tools_Page_Builders::block_packs() as $pack_id => $pack ) {
			if ( EMCP_Tools_Page_Builders::enabled( $pack_id ) ) {
				$integration = !empty($pack['native_tools']) ? new $pack['class']() : new EMCP_Tools_Block_Pack_Integration( $pack_id );
				$integration->register();
				$this->ability_names = array_merge( $this->ability_names, $integration->get_ability_names() );
			}
		}

		// Themes-tab integrations — the framework-agnostic active-theme pack always,
		// per-framework packs only when that framework is the active theme.
		$theme_integrations = array();
		if ( class_exists( 'EMCP_Tools_Active_Theme_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_Active_Theme_Integration();
		}
		if ( class_exists( 'EMCP_Tools_Astra_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_Astra_Integration();
		}
		if ( class_exists( 'EMCP_Tools_Kadence_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_Kadence_Integration();
		}
		// GeneratePress + GenerateBlocks (Pro; classes only present when Pro loaded).
		if ( class_exists( 'EMCP_Tools_GeneratePress_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_GeneratePress_Integration();
		}
		// BeTheme (Pro): theme options + BeBuilder page content. Registers only
		// when BeTheme is the active template, so a child theme counts too.
		if ( class_exists( 'EMCP_Tools_BeTheme_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_BeTheme_Integration();
		}
		// Blocksy blocks register independently above; theme and Companion have separate tools.
		if ( class_exists( 'EMCP_Tools_Blocksy_Theme_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_Blocksy_Theme_Integration();
		}
		if ( EMCP_Tools_Page_Builders::enabled( 'visual-composer' ) ) {
			$visual_composer = new EMCP_Tools_Visual_Composer_Integration();
			EMCP_Tools_Page_Builders::$registering = 'visual-composer';
			try { $visual_composer->register(); } finally { EMCP_Tools_Page_Builders::$registering = ''; }
			$this->ability_names = array_merge( $this->ability_names, $visual_composer->get_ability_names() );
		}
		if ( class_exists( 'EMCP_Tools_Blocksy_Content_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_Blocksy_Content_Integration();
		}
		if ( class_exists( 'EMCP_Tools_Blocksy_Extensions_Integration' ) ) {
			$theme_integrations[] = new EMCP_Tools_Blocksy_Extensions_Integration();
		}
		foreach ( $theme_integrations as $theme_integration ) {
			if ( 'betheme' === $theme_integration->id() && ! EMCP_Tools_Page_Builders::enabled( 'bebuilder' ) ) {
				continue;
			}
			if ( $theme_integration->is_available() ) {
				EMCP_Tools_Page_Builders::$registering = 'betheme' === $theme_integration->id() ? 'bebuilder' : '';
				try {
					$theme_integration->register();
				} finally {
					EMCP_Tools_Page_Builders::$registering = '';
				}
				$this->ability_names = array_merge( $this->ability_names, $theme_integration->get_ability_names() );
			}
		}

		// Elementor addon widget packs (Pro). Each pack contributes ONE read
		// tool for discovery + curation; widgets are placed with the generic
		// add-free-widget tool, so there is deliberately no write tool here.
		$addon_packs = array();
		foreach ( array( 'EMCP_Tools_EssentialAddons_Integration', 'EMCP_Tools_PremiumAddons_Integration' ) as $emcp_addon_class ) {
			if ( class_exists( $emcp_addon_class ) ) {
				$addon_packs[] = new $emcp_addon_class();
			}
		}
		foreach ( $addon_packs as $addon_pack ) {
			if ( $elementor_active && $addon_pack->is_available() ) {
				EMCP_Tools_Page_Builders::$registering = 'elementor';
				$addon_pack->register();
				EMCP_Tools_Page_Builders::$registering = '';
				$this->ability_names = array_merge( $this->ability_names, $addon_pack->get_ability_names() );
			}
		}

		// Ultimate Addons for Elementor (Pro), formerly Header Footer Elementor.
		// Both a widget pack AND a data plugin, so unlike the pure packs it keeps
		// the house read/write dispatcher pair: discovery + templates on read,
		// templates on write.
		if ( $elementor_active && class_exists( 'EMCP_Tools_UAE_Integration' ) ) {
			$emcp_uae = new EMCP_Tools_UAE_Integration();
			if ( $emcp_uae->is_available() ) {
				EMCP_Tools_Page_Builders::$registering = 'elementor';
				$emcp_uae->register();
				EMCP_Tools_Page_Builders::$registering = '';
				$this->ability_names = array_merge( $this->ability_names, $emcp_uae->get_ability_names() );
			}
		}

		// Performance Analyzer (read-only).
		$performance = new EMCP_Tools_Performance_Abilities();
		$performance->register();
		$this->ability_names = array_merge( $this->ability_names, $performance->get_ability_names() );

		// Filesystem abilities (writes disabled-by-default).
		$filesystem = new EMCP_Tools_Filesystem_Abilities();
		$filesystem->register();
		$this->ability_names = array_merge( $this->ability_names, $filesystem->get_ability_names() );

		// Database abilities (writes disabled-by-default).
		$database = new EMCP_Tools_Database_Abilities();
		$database->register();
		$this->ability_names = array_merge( $this->ability_names, $database->get_ability_names() );

		// WP-CLI tools (run + background jobs; disabled-by-default, manage_options).
		$wpcli = new EMCP_Tools_WPCLI_Abilities();
		$wpcli->register();
		$this->ability_names = array_merge( $this->ability_names, $wpcli->get_ability_names() );

		// Security & Malware Scanner (read-only).
		$security = new EMCP_Tools_Security_Abilities();
		$security->register();
		$this->ability_names = array_merge( $this->ability_names, $security->get_ability_names() );

		// PHP Snippet abilities (Sandbox) — free, capability-gated, no Elementor.
		if ( class_exists( 'EMCP_Tools_PHP_Snippet_Abilities' ) ) {
			$php_snippets = new EMCP_Tools_PHP_Snippet_Abilities();
			$php_snippets->register();
			$this->ability_names = array_merge( $this->ability_names, $php_snippets->get_ability_names() );
		}

		// Block Builder (Pro; self-guards on license). Gutenberg, not Elementor-gated.
		if ( class_exists( 'EMCP_Tools_Block_Builder_Abilities' ) ) {
			$block_builder = new EMCP_Tools_Block_Builder_Abilities();
			$block_builder->register();
			$this->ability_names = array_merge( $this->ability_names, $block_builder->get_ability_names() );
		}
		// Sandbox cloud export/import (free; operates over the bundle contract).
		if ( class_exists( 'EMCP_Tools_Sandbox_Cloud_Abilities' ) ) {
			$cloud = new EMCP_Tools_Sandbox_Cloud_Abilities();
			$cloud->register();
			$this->ability_names = array_merge( $this->ability_names, $cloud->get_ability_names() );
		}

		// EMCP Cloud sync tools — only when the site is connected to a cloud account.
		if ( class_exists( 'EMCP_Tools_Cloud_Abilities' ) && class_exists( 'EMCP_Tools_Cloud_Module' )
			&& EMCP_Tools_Cloud_Module::is_enabled() && EMCP_Tools_Cloud::is_connected() ) {
			$cloud_sync = new EMCP_Tools_Cloud_Abilities();
			$cloud_sync->register();
			$this->ability_names = array_merge( $this->ability_names, $cloud_sync->get_ability_names() );
		}

		// Stock-image provider tools (search-images + sideload-image) — pure WP core
		// (a stock-provider search + a Media Library sideload), no Elementor needed,
		// so they register on any site. add-stock-image (adds a widget) is gated below.
		$stock_images = new EMCP_Tools_Stock_Image_Abilities( $this->data, $this->factory );
		$stock_images->register_provider_tools();
		$this->ability_names = array_merge( $this->ability_names, $stock_images->provider_tool_names() );

		// ---- Elementor-dependent groups: only when Elementor is active ----
		if ( $elementor_active ) {
			EMCP_Tools_Page_Builders::$registering = 'elementor';
			// P0 query/discovery.
			$query = new EMCP_Tools_Query_Abilities( $this->data, $this->schema_generator );
			$query->register();
			$this->ability_names = array_merge( $this->ability_names, $query->get_ability_names() );

			// P1 page CRUD.
			$pages = new EMCP_Tools_Page_Abilities( $this->data, $this->factory );
			$pages->register();
			$this->ability_names = array_merge( $this->ability_names, $pages->get_ability_names() );

			// P1 layout/container.
			$layout = new EMCP_Tools_Layout_Abilities( $this->data, $this->factory );
			$layout->register();
			$this->ability_names = array_merge( $this->ability_names, $layout->get_ability_names() );

			// Widgets (catalog-backed).
			$widgets = new EMCP_Tools_Widget_Abilities( $this->data, $this->factory, $this->schema_generator, $this->validator );
			$widgets->register();
			$this->ability_names = array_merge( $this->ability_names, $widgets->get_ability_names() );

			// Templates.
			$templates = new EMCP_Tools_Template_Abilities( $this->data, $this->factory );
			$templates->register();
			$this->ability_names = array_merge( $this->ability_names, $templates->get_ability_names() );

			// Global settings.
			$globals = new EMCP_Tools_Global_Abilities( $this->data );
			$globals->register();
			$this->ability_names = array_merge( $this->ability_names, $globals->get_ability_names() );

			// Composite build-page.
			$composite = new EMCP_Tools_Composite_Abilities( $this->data, $this->factory );
			$composite->register();
			$this->ability_names = array_merge( $this->ability_names, $composite->get_ability_names() );

			// Stock images: the add-stock-image widget tool (provider search + sideload
			// registered unconditionally above); this one adds an image widget so it
			// needs Elementor.
			$stock_images->register_widget_tool();
			$this->ability_names[] = 'emcp-tools/add-stock-image';

			// SVG icons.
			$svg_icons = new EMCP_Tools_Svg_Icon_Abilities( $this->data, $this->factory );
			$svg_icons->register();
			$this->ability_names = array_merge( $this->ability_names, $svg_icons->get_ability_names() );

			// Custom code (CSS, JS, snippets).
			$custom_code = new EMCP_Tools_Custom_Code_Abilities( $this->data, $this->factory );
			$custom_code->register();
			$this->ability_names = array_merge( $this->ability_names, $custom_code->get_ability_names() );

			// Atomic widgets (Elementor 4.0+; self-guards on version).
			$atomic_widgets = new EMCP_Tools_Atomic_Widget_Abilities( $this->data, $this->factory );
			$atomic_widgets->register();
			$this->ability_names = array_merge( $this->ability_names, $atomic_widgets->get_ability_names() );

			// Atomic layout (Elementor 4.0+; includes detect-elementor-version).
			$atomic_layout = new EMCP_Tools_Atomic_Layout_Abilities( $this->data, $this->factory );
			$atomic_layout->register();
			$this->ability_names = array_merge( $this->ability_names, $atomic_layout->get_ability_names() );

			// Global Classes reader — self-gates on Elementor 4.0+.
			if ( class_exists( 'EMCP_Tools_Global_Classes_Abilities' ) ) {
				$global_classes = new EMCP_Tools_Global_Classes_Abilities();
				$global_classes->register();
				$this->ability_names = array_merge( $this->ability_names, $global_classes->get_ability_names() );
			}

			// Global Classes writer (create/update/delete) — self-gates on 4.0+.
			if ( class_exists( 'EMCP_Tools_Global_Classes_Write_Abilities' ) ) {
				$global_classes_write = new EMCP_Tools_Global_Classes_Write_Abilities();
				$global_classes_write->register();
				$this->ability_names = array_merge( $this->ability_names, $global_classes_write->get_ability_names() );
			}

			// Elementor Global Variables / design tokens — self-gates on 4.2+.
			if ( class_exists( 'EMCP_Tools_Global_Variables_Abilities' ) ) {
				$global_variables = new EMCP_Tools_Global_Variables_Abilities();
				$global_variables->register();
				$this->ability_names = array_merge( $this->ability_names, $global_variables->get_ability_names() );
			}

			// Brand kit / system-kit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_System_Kit_Abilities' ) ) {
				$brand_kits = new EMCP_Tools_System_Kit_Abilities();
				$brand_kits->register();
				$this->ability_names = array_merge( $this->ability_names, $brand_kits->get_ability_names() );
			}

			// SEO toolkit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_Seo_Abilities' ) ) {
				$seo = new EMCP_Tools_Seo_Abilities( $this->data );
				$seo->register();
				$this->ability_names = array_merge( $this->ability_names, $seo->get_ability_names() );
			}

			// Accessibility toolkit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_A11y_Abilities' ) ) {
				$a11y = new EMCP_Tools_A11y_Abilities( $this->data );
				$a11y->register();
				$this->ability_names = array_merge( $this->ability_names, $a11y->get_ability_names() );
			}

			// Widget Builder (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_Widget_Builder_Abilities' ) ) {
				$widget_builder = new EMCP_Tools_Widget_Builder_Abilities();
				$widget_builder->register();
				$this->ability_names = array_merge( $this->ability_names, $widget_builder->get_ability_names() );
			}
		}

		EMCP_Tools_Page_Builders::$registering = '';

		// Skills read-side (Pro; self-guards on license). Not Elementor-dependent,
		// so it registers regardless of whether Elementor is active — but gated by
		// the Agent Skills module so the admin can switch the runtime exposure off.
		if ( class_exists( 'EMCP_Tools_Skill_Abilities' )
			&& class_exists( 'EMCP_Tools_Agent_Skills_Module' )
			&& EMCP_Tools_Agent_Skills_Module::is_enabled() ) {
			$skills = new EMCP_Tools_Skill_Abilities();
			$skills->register();
			$this->ability_names = array_merge( $this->ability_names, $skills->get_ability_names() );
		}

		// Project Memory (Pro; self-guards on license). Not Elementor-dependent;
		// gated by the Memory module so the admin can switch the runtime exposure
		// off. is_enabled() runs before the module's init boot.
		if ( class_exists( 'EMCP_Tools_Memory_Abilities' )
			&& class_exists( 'EMCP_Tools_Memory_Module' )
			&& EMCP_Tools_Memory_Module::is_enabled() ) {
			$memory = new EMCP_Tools_Memory_Abilities();
			$memory->register();
			$this->ability_names = array_merge( $this->ability_names, $memory->get_ability_names() );
		}

		// Backup / Migrate / Sync MCP tools (Pro; self-guards on license). Not
		// Elementor-dependent; gated by the Migrate module. is_enabled() runs
		// before the module's init:5 boot (abilities register on
		// wp_abilities_api_init). The two destructive tools ship disabled-by-default.
		if ( class_exists( 'EMCP_Tools_Migrate_Abilities' )
			&& class_exists( 'EMCP_Tools_Migrate_Module' )
			&& EMCP_Tools_Migrate_Module::is_enabled() ) {
			$migrate = new EMCP_Tools_Migrate_Abilities();
			$migrate->register();
			$this->ability_names = array_merge( $this->ability_names, $migrate->get_ability_names() );
		}

		// GSAP Integration (Pro). The module registers local scripts site-wide;
		// these two dispatchers expose its catalog and settings over MCP.
		if ( class_exists( 'EMCP_Tools_GSAP_Abilities' )
			&& class_exists( 'EMCP_Tools_GSAP_Module' )
			&& EMCP_Tools_GSAP_Module::is_enabled() ) {
			$gsap = new EMCP_Tools_GSAP_Abilities();
			$gsap->register();
			$this->ability_names = array_merge( $this->ability_names, $gsap->get_ability_names() );
		}
	}

	/**
	 * Keeps only well-formed MCP ability names — `[a-z0-9-]+/[a-z0-9-]+` — from
	 * whatever the emcp_tools_ability_names filter returned. Non-array input
	 * yields an empty list.
	 *
	 * @since 3.3.1
	 *
	 * @param mixed $names Filter return value.
	 * @return string[] Validated ability names.
	 */
	private static function sanitize_ability_names( $names ): array {
		if ( ! is_array( $names ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$names,
				static function ( $name ) {
					return is_string( $name ) && (bool) preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name );
				}
			)
		);
	}

	/**
	 * Gets the list of registered ability names.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of ability names.
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}
}
