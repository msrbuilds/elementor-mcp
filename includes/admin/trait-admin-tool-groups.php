<?php
/**
 * Tool slug families used by settings defaults and availability filtering.
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
 * Tool slug families used by settings defaults and availability filtering.
 */
trait EMCP_Tools_Admin_Tool_Groups_Trait {

	/**
	 * SEO/A11y Pro MCP tool slugs that ship disabled-by-default (v2 defaults).
	 *
	 * @since 1.8.0
	 *
	 * @return string[]
	 */
	public static function seo_a11y_tool_slugs(): array {
		return array(
			'emcp-tools/audit-page-seo',
			'emcp-tools/extract-keywords-from-content',
			'emcp-tools/generate-meta-tags',
			'emcp-tools/generate-schema-markup',
			'emcp-tools/set-social-image',
			'emcp-tools/audit-page-a11y',
			'emcp-tools/fix-color-contrast',
			'emcp-tools/add-alt-text-from-context',
		);
	}

	/**
	 * Widget Builder Pro MCP tool slugs that ship disabled-by-default (v3).
	 *
	 * @since 1.9.0
	 *
	 * @return string[]
	 */
	public static function widget_builder_tool_slugs(): array {
		return array(
			'emcp-tools/list-control-types',
			'emcp-tools/validate-widget-spec',
			'emcp-tools/create-custom-widget',
			'emcp-tools/update-custom-widget',
			'emcp-tools/get-custom-widget',
			'emcp-tools/list-custom-widgets',
			'emcp-tools/set-widget-status',
			'emcp-tools/delete-custom-widget',
		);
	}

	/**
	 * Block Builder Pro MCP tool slugs that ship disabled-by-default (v24).
	 *
	 * @since 3.7.0
	 *
	 * @return string[]
	 */
	public static function block_tool_slugs(): array {
		return array(
			'emcp-tools/list-block-control-types',
			'emcp-tools/validate-block-spec',
			'emcp-tools/create-custom-block',
			'emcp-tools/update-custom-block',
			'emcp-tools/get-custom-block',
			'emcp-tools/list-custom-blocks',
			'emcp-tools/set-block-status',
			'emcp-tools/delete-custom-block',
		);
	}

	/**
	 * Project Memory Pro MCP tool slugs that ship disabled-by-default (v25). The
	 * always-on value (approved-guidance injection) works with these tools off.
	 *
	 * @since 3.7.0
	 *
	 * @return string[]
	 */
	public static function memory_tool_slugs(): array {
		return array(
			'emcp-tools/recall',
			'emcp-tools/remember',
			'emcp-tools/save-session-summary',
		);
	}

	/**
	 * The PHP Snippet (Sandbox) tool slugs. Free, but powerful, so they ship
	 * disabled-by-default and the admin opts in on the Tools tab.
	 *
	 * @since 2.1.0
	 *
	 * @return string[]
	 */
	public static function php_snippet_tool_slugs(): array {
		return array(
			'emcp-tools/validate-php-snippet',
			'emcp-tools/create-php-snippet',
			'emcp-tools/update-php-snippet',
			'emcp-tools/get-php-snippet',
			'emcp-tools/list-php-snippets',
			'emcp-tools/delete-php-snippet',
		);
	}

	/**
	 * The EMCP Cloud + Marketplace tool slugs.
	 *
	 * Doubly conditional: the Cloud module must be on AND the site must be
	 * connected to an account, so their absence is the normal case and must not
	 * read as drift.
	 *
	 * @since 3.13.0
	 * @return string[]
	 */
	public static function cloud_tool_slugs(): array {
		return array(
			'emcp-tools/cloud-status',
			'emcp-tools/cloud-list',
			'emcp-tools/cloud-backup',
			'emcp-tools/cloud-pull',
			'emcp-tools/cloud-config-sync',
			'emcp-tools/cloud-marketplace-list',
			'emcp-tools/cloud-marketplace-install',
		);
	}

	public static function themer_tool_slugs(): array {
		return array(
			'emcp-tools/create-theme-template',
			'emcp-tools/list-theme-templates',
			'emcp-tools/get-theme-template',
			'emcp-tools/update-theme-template',
			'emcp-tools/set-template-conditions',
			'emcp-tools/delete-theme-template',
			'emcp-tools/resolve-template',
			'emcp-tools/list-condition-targets',
			'emcp-tools/list-dynamic-sources',
		);
	}

	public static function themer_php_tool_slugs(): array {
		return array(
			'emcp-tools/create-theme-php-template',
			'emcp-tools/list-theme-php-templates',
			'emcp-tools/get-theme-php-template',
			'emcp-tools/update-theme-php-template',
			'emcp-tools/delete-theme-php-template',
		);
	}

	/**
	 * The 9 Plugins & Themes mutation tool slugs. Powerful (install/delete/
	 * activate), so they ship disabled-by-default; reads stay enabled. The admin
	 * opts in on the Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function package_write_tool_slugs(): array {
		return array(
			'emcp-tools/install-plugin',
			'emcp-tools/activate-plugin',
			'emcp-tools/deactivate-plugin',
			'emcp-tools/update-plugin',
			'emcp-tools/delete-plugin',
			'emcp-tools/install-theme',
			'emcp-tools/switch-theme',
			'emcp-tools/update-theme',
			'emcp-tools/delete-theme',
		);
	}

	/**
	 * Media tool slugs that ship disabled-by-default. Only delete-media (the
	 * destructive, effectively-permanent op); get-media / update-media stay on.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function media_write_tool_slugs(): array {
		return array( 'emcp-tools/delete-media' );
	}

	/**
	 * Users mutation tool slugs that ship disabled-by-default. The reads
	 * (list-users/get-user) stay enabled. The admin opts in on the Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function user_write_tool_slugs(): array {
		return array( 'emcp-tools/create-user', 'emcp-tools/update-user' );
	}

	/**
	 * Filesystem mutation tool slugs that ship disabled-by-default. The reads
	 * (read-file/list-directory/search-files) stay enabled.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function filesystem_write_tool_slugs(): array {
		return array( 'emcp-tools/write-file', 'emcp-tools/edit-file', 'emcp-tools/delete-file' );
	}

	/**
	 * Database mutation tool slugs that ship disabled-by-default. The reads
	 * (list-tables/describe-table/query) stay enabled.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function database_write_tool_slugs(): array {
		return array( 'emcp-tools/insert-row', 'emcp-tools/update-rows', 'emcp-tools/delete-rows' );
	}

	/**
	 * Redirect Manager write tool slugs that ship disabled-by-default. The reads
	 * (list-redirects/find-broken-links) stay enabled. The admin opts in on the
	 * Tools tab.
	 *
	 * @since 3.11.0
	 * @return string[]
	 */
	public static function redirect_tool_slugs(): array {
		return array( 'emcp-tools/create-redirect', 'emcp-tools/update-redirect', 'emcp-tools/delete-redirect' );
	}

	/**
	 * The Backup/Migrate/Sync MCP tool slugs (drift-guard exclusion — the group
	 * only registers when the Migrate module is active + premium). The two
	 * destructive tools (migrate-site/sync-to-live) ship disabled-by-default.
	 *
	 * @since 3.15.0
	 * @return string[]
	 */
	public static function migrate_tool_slugs(): array {
		return array(
			'emcp-tools/create-backup',
			'emcp-tools/list-backups',
			'emcp-tools/migrate-site',
			'emcp-tools/sync-to-live',
			'emcp-tools/list-syncable-changes',
			'emcp-tools/sync-content-item',
			'emcp-tools/discard-sync-change',
		);
	}

	/**
	 * The ACF dispatcher tool slugs. The domain registers as two dispatcher
	 * tools (acf-read enabled by default, acf-write disabled by default); the
	 * 15 operations live behind them. Both slugs are excluded from the drift
	 * guard since the domain only registers when ACF (free or Pro) is active.
	 *
	 * @since 3.2.1
	 * @return string[]
	 */
	public static function acf_tool_slugs(): array {
		return array(
			'emcp-tools/acf-read',
			'emcp-tools/acf-write',
		);
	}

	/**
	 * The WooCommerce integration's dispatcher slugs (drift-guard exclusion).
	 *
	 * @since 3.4.2
	 * @return string[]
	 */
	public static function woo_tool_slugs(): array {
		return array(
			'emcp-tools/woo-read',
			'emcp-tools/woo-write',
		);
	}

	/** The FunnelKit integration's conditional dispatcher slug. */
	public static function funnelkit_tool_slugs(): array {
		return array( 'emcp-tools/funnelkit-read', 'emcp-tools/funnelkit-write' );
	}

	/** The FunnelKit Automations integration's conditional dispatcher slugs (3.19.0). */
	public static function funnelkit_automations_tool_slugs(): array {
		return array( 'emcp-tools/funnelkit-automations-read', 'emcp-tools/funnelkit-automations-write' );
	}

	/** The TablePress integration's conditional dispatcher slugs (3.19.0). */
	public static function tablepress_tool_slugs(): array {
		return array( 'emcp-tools/tablepress-read', 'emcp-tools/tablepress-write' );
	}

	/** The Tutor LMS integration's conditional dispatcher slugs (3.19.0). */
	public static function tutor_tool_slugs(): array {
		return array( 'emcp-tools/tutor-read', 'emcp-tools/tutor-write' );
	}

	/** The FluentCRM integration's conditional dispatcher slugs (3.19.0). */
	public static function fluentcrm_tool_slugs(): array {
		return array( 'emcp-tools/fluentcrm-read', 'emcp-tools/fluentcrm-write' );
	}

	/** The Amelia integration's conditional dispatcher slugs (3.19.0). */
	public static function amelia_tool_slugs(): array {
		return array( 'emcp-tools/amelia-read', 'emcp-tools/amelia-write' );
	}

	/** The Events Calendar integration's conditional dispatcher slugs (3.19.0). */
	public static function tec_tool_slugs(): array {
		return array( 'emcp-tools/events-calendar-read', 'emcp-tools/events-calendar-write' );
	}

	/** The LifterLMS integration's conditional dispatcher slugs (3.19.0). */
	public static function lifterlms_tool_slugs(): array {
		return array( 'emcp-tools/lifterlms-read', 'emcp-tools/lifterlms-write' );
	}

	/** The TranslatePress integration's conditional dispatcher slugs (3.19.0). */
	public static function translatepress_tool_slugs(): array {
		return array( 'emcp-tools/translatepress-read', 'emcp-tools/translatepress-write' );
	}

	/** The Polylang integration's conditional dispatcher slugs (3.19.0). */
	public static function polylang_tool_slugs(): array {
		return array( 'emcp-tools/polylang-read', 'emcp-tools/polylang-write' );
	}

	/**
	 * The Meta Box dispatcher tool slugs. The domain registers as two dispatcher
	 * tools (metabox-read enabled by default, metabox-write disabled by default);
	 * the operations live behind them. Both slugs are excluded from the drift
	 * guard since the domain only registers when Meta Box is active.
	 *
	 * @since 3.4.2
	 * @return string[]
	 */
	public static function metabox_tool_slugs(): array {
		return array(
			'emcp-tools/metabox-read',
			'emcp-tools/metabox-write',
		);
	}

	/**
	 * The pre-release per-operation ACF slugs (the earlier 15-tool layout).
	 * Kept only so the defaults step can strip them from the stored option on
	 * sites that seeded them before the 2-dispatcher consolidation.
	 *
	 * @since 3.2.1
	 * @return string[]
	 */
	public static function legacy_acf_operation_slugs(): array {
		return array(
			'emcp-tools/list-acf-field-groups',
			'emcp-tools/get-acf-field-group',
			'emcp-tools/list-acf-options-pages',
			'emcp-tools/get-acf-fields',
			'emcp-tools/update-acf-fields',
			'emcp-tools/create-acf-field-group',
			'emcp-tools/update-acf-field-group',
			'emcp-tools/list-acf-post-types',
			'emcp-tools/get-acf-post-type',
			'emcp-tools/create-acf-post-type',
			'emcp-tools/update-acf-post-type',
			'emcp-tools/list-acf-taxonomies',
			'emcp-tools/get-acf-taxonomy',
			'emcp-tools/create-acf-taxonomy',
			'emcp-tools/update-acf-taxonomy',
		);
	}

	public static function theme_tool_slugs(): array {
		return array(
			'emcp-tools/theme-read',
			'emcp-tools/theme-write',
			'emcp-tools/astra-read',
			'emcp-tools/astra-write',
			'emcp-tools/spectra-read',
			'emcp-tools/spectra-write',
			'emcp-tools/kadence-read',
			'emcp-tools/kadence-write',
			'emcp-tools/kadence-blocks-read',
			'emcp-tools/kadence-blocks-write',
			'emcp-tools/generatepress-read',
			'emcp-tools/generatepress-write',
			'emcp-tools/generateblocks-read',
			'emcp-tools/generateblocks-write',
			'emcp-tools/blocksy-blocks-read',
			'emcp-tools/blocksy-blocks-write',
			'emcp-tools/betheme-read',
			'emcp-tools/betheme-write',
			'emcp-tools/blocksy-extensions-read',
			'emcp-tools/blocksy-extensions-write',
			'emcp-tools/blocksy-theme-read',
			'emcp-tools/blocksy-theme-write',
			'emcp-tools/blocksy-content-read',
			'emcp-tools/blocksy-content-write',
		);
	}

	/**
	 * The per-widget convenience tool slugs removed in 3.0.0 (widget
	 * consolidation). Used by the v5 defaults step to clear orphaned disabled
	 * entries from the stored option.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function removed_widget_tool_slugs(): array {
		return array(
			'emcp-tools/add-widget',
			'emcp-tools/add-heading', 'emcp-tools/add-text-editor', 'emcp-tools/add-image',
			'emcp-tools/add-button', 'emcp-tools/add-video', 'emcp-tools/add-icon',
			'emcp-tools/add-spacer', 'emcp-tools/add-divider', 'emcp-tools/add-icon-box',
			'emcp-tools/add-accordion', 'emcp-tools/add-alert', 'emcp-tools/add-counter',
			'emcp-tools/add-google-maps', 'emcp-tools/add-icon-list', 'emcp-tools/add-image-box',
			'emcp-tools/add-image-carousel', 'emcp-tools/add-progress', 'emcp-tools/add-social-icons',
			'emcp-tools/add-star-rating', 'emcp-tools/add-tabs', 'emcp-tools/add-testimonial',
			'emcp-tools/add-toggle', 'emcp-tools/add-html', 'emcp-tools/add-menu-anchor',
			'emcp-tools/add-shortcode', 'emcp-tools/add-rating', 'emcp-tools/add-text-path',
			'emcp-tools/add-form', 'emcp-tools/add-posts-grid', 'emcp-tools/add-countdown',
			'emcp-tools/add-price-table', 'emcp-tools/add-flip-box', 'emcp-tools/add-animated-headline',
			'emcp-tools/add-call-to-action', 'emcp-tools/add-slides', 'emcp-tools/add-testimonial-carousel',
			'emcp-tools/add-price-list', 'emcp-tools/add-gallery', 'emcp-tools/add-share-buttons',
			'emcp-tools/add-table-of-contents', 'emcp-tools/add-blockquote', 'emcp-tools/add-lottie',
			'emcp-tools/add-hotspot', 'emcp-tools/add-nav-menu', 'emcp-tools/add-loop-grid',
			'emcp-tools/add-loop-carousel', 'emcp-tools/add-media-carousel', 'emcp-tools/add-nested-tabs',
			'emcp-tools/add-nested-accordion', 'emcp-tools/add-portfolio', 'emcp-tools/add-author-box',
			'emcp-tools/add-login', 'emcp-tools/add-code-highlight', 'emcp-tools/add-reviews',
			'emcp-tools/add-off-canvas', 'emcp-tools/add-progress-tracker', 'emcp-tools/add-search',
			'emcp-tools/add-wc-products', 'emcp-tools/add-wc-add-to-cart', 'emcp-tools/add-wc-cart',
			'emcp-tools/add-wc-checkout', 'emcp-tools/add-wc-menu-cart',
		);
	}

	/**
	 * The 16 SEO dispatcher slugs — drift-guard exclusion (registered only when
	 * their plugin is active / Pro).
	 *
	 * @since 3.5.0
	 * @return string[]
	 */
	public static function seo_tool_slugs(): array {
		return array(
			'emcp-tools/slimseo-read',
			'emcp-tools/slimseo-write',
			'emcp-tools/visibility-read',
			'emcp-tools/visibility-write',
			'emcp-tools/yoast-read',
			'emcp-tools/yoast-write',
			'emcp-tools/rankmath-read',
			'emcp-tools/rankmath-write',
			'emcp-tools/aioseo-read',
			'emcp-tools/aioseo-write',
			'emcp-tools/seopress-read',
			'emcp-tools/seopress-write',
			'emcp-tools/seoframework-read',
			'emcp-tools/seoframework-write',
			'emcp-tools/surerank-read',
			'emcp-tools/surerank-write',
		);
	}

	/**
	 * Elementor addon-domain tool slugs (Pro).
	 *
	 * The two widget packs contribute a single read tool each: they exist for
	 * discovery and curation, because their widgets are placed with the generic
	 * add-free-widget tool. HFE is a data plugin and keeps the read/write pair.
	 *
	 * @since 3.6.0
	 * @return string[]
	 */
	public static function addon_tool_slugs(): array {
		return array(
			'emcp-tools/essential-addons-read',
			'emcp-tools/premium-addons-read',
			'emcp-tools/uae-read',
			'emcp-tools/uae-write',
		);
	}

	public static function form_tool_slugs(): array {
		return array(
			'emcp-tools/cf7-read',
			'emcp-tools/cf7-write',
			'emcp-tools/wpforms-read',
			'emcp-tools/wpforms-write',
			'emcp-tools/gravityforms-read',
			'emcp-tools/gravityforms-write',
			'emcp-tools/fluentforms-read',
			'emcp-tools/fluentforms-write',
			'emcp-tools/ninjaforms-read',
			'emcp-tools/ninjaforms-write',
			'emcp-tools/formidable-read',
			'emcp-tools/formidable-write',
			'emcp-tools/metform-read',
			'emcp-tools/metform-write',
			'emcp-tools/sureforms-read',
			'emcp-tools/sureforms-write',
			'emcp-tools/forminator-read',
			'emcp-tools/forminator-write',
		);
	}
}
