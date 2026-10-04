<?php
/**
 * Settings registration, sanitization, and versioned defaults.
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
 * Settings registration, sanitization, and versioned defaults.
 */
trait EMCP_Tools_Admin_Settings_Trait {

	public function maybe_apply_default_disabled_tools(): void {
		$applied = (int) get_option( self::OPTION_DEFAULTS_APPLIED, 0 );
		if ( $applied >= self::DEFAULTS_VERSION ) {
			return;
		}

		$existing = get_option( self::OPTION_DISABLED_TOOLS, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$changes  = $this->default_disabled_changes( $applied );
		$existing = array_values( array_diff( $existing, $changes['strip'] ) );
		$merged   = array_values( array_unique( array_merge( $existing, $changes['add'] ) ) );
		update_option( self::OPTION_DISABLED_TOOLS, $merged );
		update_option( self::OPTION_DEFAULTS_APPLIED, (string) self::DEFAULTS_VERSION );
	}

	/**
	 * The versioned additions to the disabled set above `$applied`, and the
	 * stale slugs to strip from the stored set. Shared by the upgrade seeding
	 * and Tools > Reset to defaults (spec 8.3).
	 *
	 * @param int $applied Last defaults version applied (0 for a fresh install).
	 * @return array{add: string[], strip: string[]}
	 */
	public function default_disabled_changes( int $applied ): array {
		$add   = array();
		$strip = array();

		// v1 — every Pro-badged tool. Only seeded on a truly fresh install
		// (applied < 1); re-running on an upgrade would clobber user re-enables.
		// add-pro-widget stays on (see v5): it registers only with Elementor Pro
		// active and only adds widgets.
		if ( $applied < 1 ) {
			foreach ( $this->get_all_tools() as $category ) {
				foreach ( $category['tools'] as $slug => $tool ) {
					if ( 'emcp-tools/add-pro-widget' === $slug ) {
						continue;
					}
					if ( in_array( 'pro', $tool['badges'], true ) || in_array( 'elementor-pro', $tool['badges'], true ) ) {
						$add[] = $slug;
					}
				}
			}
		}

		// v2 — SEO/A11y Pro MCP tools ship disabled-by-default. Adding only the
		// new slugs means an existing user's other choices survive the upgrade.
		if ( $applied < 2 ) {
			$add = array_merge( $add, self::seo_a11y_tool_slugs() );
		}

		// v3 — Widget Builder Pro MCP tools ship disabled-by-default.
		if ( $applied < 3 ) {
			$add = array_merge( $add, self::widget_builder_tool_slugs() );
		}

		// v4 — PHP Snippet (Sandbox) MCP tools ship disabled-by-default.
		if ( $applied < 4 ) {
			$add = array_merge( $add, self::php_snippet_tool_slugs() );
		}

		// v5 — Widget consolidation (3.0.0). The 62 per-widget Pro slugs seeded
		// disabled in v1 no longer exist; strip them so they don't linger in the
		// stored option. add-pro-widget is a single tool, left ENABLED by default
		// (it only registers when Elementor Pro is active anyway).
		if ( $applied < 5 ) {
			$strip = array_merge( $strip, self::removed_widget_tool_slugs() );
		}

		// v6 — Plugins & Themes mutation tools ship disabled-by-default
		// (powerful: install/activate/deactivate/update/delete). Reads stay on.
		if ( $applied < 6 ) {
			$add = array_merge( $add, self::package_write_tool_slugs() );
		}

		// v7 — delete-media ships disabled-by-default (permanent deletion).
		if ( $applied < 7 ) {
			$add = array_merge( $add, self::media_write_tool_slugs() );
		}

		// v8 — Users mutation tools ship disabled-by-default (account changes).
		if ( $applied < 8 ) {
			$add = array_merge( $add, self::user_write_tool_slugs() );
		}

		// v9 — Filesystem mutation tools ship disabled-by-default (write/edit/delete).
		if ( $applied < 9 ) {
			$add = array_merge( $add, self::filesystem_write_tool_slugs() );
		}

		// v10 — Database mutation tools ship disabled-by-default (insert/update/delete).
		if ( $applied < 10 ) {
			$add = array_merge( $add, self::database_write_tool_slugs() );
		}

		// v11 — Themer PHP-template tools ship disabled-by-default (raw PHP; gated
		// behind the master switch too). The admin opts in on the Tools tab.
		if ( $applied < 11 ) {
			$add = array_merge( $add, self::themer_php_tool_slugs() );
		}

		// v14 — ACF is exposed as two dispatcher tools (acf-read / acf-write).
		// The write dispatcher ships disabled-by-default; the read dispatcher
		// stays on. Also strip any pre-release per-operation ACF slugs left in
		// the stored option from the earlier 15-tool layout. (Supersedes the
		// v12/v13 per-tool ACF seeding, which targeted slugs that no longer
		// exist as individual tools.)
		if ( $applied < 14 ) {
			$strip = array_merge( $strip, self::legacy_acf_operation_slugs() );
			$add[]    = 'emcp-tools/acf-write';
		}

		// v15 — set-social-image (Pro SEO) ships disabled-by-default, consistent
		// with the rest of the SEO/A11y toolkit.
		if ( $applied < 15 ) {
			$add[] = 'emcp-tools/set-social-image';
		}

		// v16 — Themes-domain write dispatchers ship disabled-by-default (theme_mod
		// writes + child-theme creation; per-framework settings writes). Reads on.
		if ( $applied < 16 ) {
			$add[] = 'emcp-tools/theme-write';
			$add[] = 'emcp-tools/astra-write';
		}

		// v17 — Spectra Blocks write dispatcher (add-block) ships disabled-by-default.
		if ( $applied < 17 ) {
			$add[] = 'emcp-tools/spectra-write';
		}

		// v18 — WP-CLI tools (run + background jobs) ship disabled-by-default
		// (command execution surface). All four are off until the admin opts in.
		if ( $applied < 18 ) {
			$add = array_merge( $add, EMCP_Tools_WPCLI_Abilities::slugs() );
		}

		// v19 — WooCommerce + Meta Box write dispatchers ship disabled-by-default.
		// Woo write is the money/PII surface; Meta Box write edits custom-field
		// values. Both read dispatchers stay enabled.
		if ( $applied < 19 ) {
			$add[] = 'emcp-tools/woo-write';
			$add[] = 'emcp-tools/metabox-write';
		}

		// v20 — Forms domain writes ship disabled-by-default (all six plugins).
		// Reads stay enabled; the five Pro reads render locked on free builds via
		// the get_all_tools() Pro-lock post-process.
		if ( $applied < 20 ) {
			$add[] = 'emcp-tools/cf7-write';
			$add[] = 'emcp-tools/wpforms-write';
			$add[] = 'emcp-tools/gravityforms-write';
			$add[] = 'emcp-tools/fluentforms-write';
			$add[] = 'emcp-tools/ninjaforms-write';
			$add[] = 'emcp-tools/formidable-write';
		}

		// v21 — MetForm + SureForms writes disabled-by-default.
		if ( $applied < 21 ) {
			$add[] = 'emcp-tools/metform-write';
			$add[] = 'emcp-tools/sureforms-write';
		}

		// v22 — SEO-plugin writes disabled-by-default (all 7 plugins).
		if ( $applied < 22 ) {
			$add[] = 'emcp-tools/slimseo-write';
			$add[] = 'emcp-tools/yoast-write';
			$add[] = 'emcp-tools/rankmath-write';
			$add[] = 'emcp-tools/aioseo-write';
			$add[] = 'emcp-tools/seopress-write';
			$add[] = 'emcp-tools/seoframework-write';
			$add[] = 'emcp-tools/surerank-write';
		}

		// v23 — Elementor addon domain. Only UAE has a write tool; Essential and
		// Premium Addons are discovery-only (placement stays on add-free-widget),
		// so there is nothing of theirs to disable.
		if ( $applied < 23 ) {
			$add[] = 'emcp-tools/uae-write';
		}

		// v24 — Block Builder Pro MCP tools ship disabled-by-default (author executable
		// block code; same posture as the Widget Builder + PHP Snippets).
		if ( $applied < 24 ) {
			$add = array_merge( $add, self::block_tool_slugs() );
		}

		// v25 — Project Memory Pro MCP tools ship disabled-by-default. The always-on
		// value (approved-guidance injection) works with the tools off.
		if ( $applied < 25 ) {
			$add = array_merge( $add, self::memory_tool_slugs() );
		}

		// v26 — Forminator write (delete-entry) disabled-by-default.
		if ( $applied < 26 ) {
			$add[] = 'emcp-tools/forminator-write';
		}

		// v27 — Kadence theme + Kadence Blocks write dispatchers disabled-by-default.
		if ( $applied < 27 ) {
			$add[] = 'emcp-tools/kadence-write';
			$add[] = 'emcp-tools/kadence-blocks-write';
		}

		// v28 — Elementor v4 Global Class write tools disabled-by-default.
		if ( $applied < 28 ) {
			$add[] = 'emcp-tools/create-global-class';
			$add[] = 'emcp-tools/update-global-class';
			$add[] = 'emcp-tools/delete-global-class';
		}

		// v29 — reorder-global-classes write tool disabled-by-default.
		if ( $applied < 29 ) {
			$add[] = 'emcp-tools/reorder-global-classes';
		}

		// v30 — GeneratePress + GenerateBlocks write dispatchers disabled-by-default.
		if ( $applied < 30 ) {
			$add[] = 'emcp-tools/generatepress-write';
			$add[] = 'emcp-tools/generateblocks-write';
		}

		// v31 — Blocksy write dispatchers disabled-by-default.
		if ( $applied < 31 ) {
			$add[] = 'emcp-tools/blocksy-blocks-write';
			$add[] = 'emcp-tools/blocksy-extensions-write';
		}

		// v32 — Redirect Manager write tools ship disabled-by-default (create/
		// update/delete a redirect). The reads (list-redirects/find-broken-links)
		// stay enabled. The admin opts in on the Tools tab.
		if ( $applied < 32 ) {
			$add = array_merge( $add, self::redirect_tool_slugs() );
		}

		// v33 — Backup/Migrate/Sync destructive MCP tools ship disabled-by-default
		// (migrate-site/sync-to-live push to and overwrite a live target). The
		// reads (create-backup/list-backups) stay enabled.
		if ( $applied < 33 ) {
			$add[] = 'emcp-tools/migrate-site';
			$add[] = 'emcp-tools/sync-to-live';
		}

		// v34 — the content-sync push tool overwrites an item on the live site, so
		// it ships disabled-by-default. The list + discard reads stay enabled.
		if ( $applied < 34 ) {
			$add[] = 'emcp-tools/sync-content-item';
		}

		// v35 — BeTheme write disabled-by-default, matching the other theme write
		// dispatchers. It changes global theme settings and can replace a page's
		// whole BeBuilder content, so an admin opts in on the Tools tab.
		if ( $applied < 35 ) {
			$add[] = 'emcp-tools/betheme-write';
		}

		// v36 — Elementor Global Variables mutate the site-wide design-token
		// system, so writes ship disabled-by-default. Listing stays enabled.
		if ( $applied < 36 ) {
			$add[] = 'emcp-tools/create-variable';
			$add[] = 'emcp-tools/update-variable';
			$add[] = 'emcp-tools/delete-variable';
			$add[] = 'emcp-tools/restore-variable';
			$add[] = 'emcp-tools/batch-variables';
		}

		// v37 — Cache regeneration can affect site-wide rendering; require opt-in.
		if ( $applied < 37 ) {
			$add[] = 'emcp-tools/regenerate-css';
		}

		if ( $applied < 38 ) {
			foreach ( array( 'create-page', 'set-page-elements', 'add-element', 'update-element', 'move-element', 'remove-element' ) as $slug ) {
				$add[] = 'emcp-tools/bricks-' . $slug;
			}
		}
		if ( $applied < 39 ) {
			foreach ( array( 'create-page', 'set-page-tree', 'add-element', 'update-element', 'move-element', 'remove-element' ) as $slug ) {
				$add[] = 'emcp-tools/breakdance-' . $slug;
			}
		}
		if ( $applied < 40 ) {
			foreach ( array( 'create-page', 'set-page-tree', 'add-element', 'update-element', 'move-element', 'remove-element' ) as $slug ) {
				$add[] = 'emcp-tools/avada-' . $slug;
			}
		}
		if ( $applied < 41 ) {
			foreach ( array( 'create-page', 'set-page-tree', 'add-element', 'update-element', 'move-element', 'remove-element', 'create-library-layout', 'create-theme-layout', 'create-template', 'update-template', 'remove-template', 'update-theme-options' ) as $slug ) {
				$add[] = 'emcp-tools/divi-' . $slug;
			}
		}
		if ( $applied < 42 ) {
			foreach ( array( 'create-page', 'set-page-content', 'add-element', 'replace-element', 'move-element', 'remove-element' ) as $slug ) { $add[] = 'emcp-tools/thrive-' . $slug; }
		}
		if ( $applied < 43 ) {
			foreach ( EMCP_Tools_Page_Builders::block_packs() as $id => $pack ) {
				if (!empty($pack['native_tools'])) { continue; }
				foreach ( (new EMCP_Tools_Block_Pack_Integration($id))->definitions() as $slug => $definition ) {
					if ($definition[1]) { $add[] = 'emcp-tools/'.$id.'-'.$slug; }
				}
			}
		}
		if ($applied < 50) {
			foreach (array('add-block','update-block','move-block','remove-block') as $slug) { $add[]='emcp-tools/blocksy-blocks-'.$slug; }
			$add[]='emcp-tools/blocksy-theme-write';
			$add[]='emcp-tools/blocksy-content-write';
		}
		if ($applied < 49) { foreach (array('create-page','set-page-layout','add-node','update-node','move-node','remove-node','set-page-styles','save-template','apply-template','restore-revision','publish-page') as $slug) { $add[]='emcp-tools/beaver-'.$slug; } }
		if ($applied < 48) { foreach (array('create-page','set-page-content','replace-fragment','set-page-styles','apply-template','restore-revision','publish-page') as $slug) { $add[]='emcp-tools/wpbakery-'.$slug; } }
		if ($applied < 47) { foreach (array('create-page','set-page-content','add-element','update-element','set-page-styles','publish-page','restore-version','set-page-canvas') as $slug) { $add[]='emcp-tools/kirki-'.$slug; } }
		if ($applied < 46) { foreach (array('restore-revision','define-component-property','save-design-state','restore-design-revision','manage-document','set-native-document','configure-design-library','import-design-library') as $slug) { $add[]='emcp-tools/oxygen-'.$slug; } }
		if ($applied < 45) { foreach (array('create-template','update-template-settings','copy-library-item','insert-component') as $slug) { $add[]='emcp-tools/oxygen-'.$slug; } }
		if ($applied < 44) { foreach (array('create-page','set-page-tree','add-element','update-element','move-element','remove-element','save-class') as $slug) { $add[]='emcp-tools/oxygen-'.$slug; } }
		if ($applied < 51) { foreach (array('create-page','stage-document','apply-template','restore-revision','discard-draft','publish-page') as $slug) { $add[]='emcp-tools/visual-composer-'.$slug; } }
		if ($applied < 52) { foreach (array('create-page','set-page-blocks','publish-page','add-block','update-block','move-block','remove-block','insert-pattern','rebuild-page-styles','update-settings') as $slug) { $add[]='emcp-tools/otter-'.$slug; } }
		// v53: Visibility write dispatcher disabled-by-default, like every SEO write (#141).
		if ( $applied < 53 ) {
			$add[] = 'emcp-tools/visibility-write';
		}
		// v54: the FunnelKit write dispatchers (3.19.0) ship off; their reads stay on.
		if ( $applied < 54 ) {
			$add[] = 'emcp-tools/funnelkit-write';
			$add[] = 'emcp-tools/funnelkit-automations-write';
		}
		// v55: the Polylang write dispatcher (3.19.0) ships off; its read stays on.
		if ( $applied < 55 ) {
			$add[] = 'emcp-tools/polylang-write';
		}
		// v56: the TranslatePress write dispatcher (3.19.0) ships off; its read stays on.
		if ( $applied < 56 ) {
			$add[] = 'emcp-tools/translatepress-write';
		}
		// v57: the TablePress write dispatcher (3.19.0) ships off; its read stays on.
		if ( $applied < 57 ) {
			$add[] = 'emcp-tools/tablepress-write';
		}
		// v58: the Tutor LMS write dispatcher (3.19.0) ships off; its read stays on.
		if ( $applied < 58 ) {
			$add[] = 'emcp-tools/tutor-write';
		}
		// v59: the LifterLMS write dispatcher (3.19.0) ships off; its read stays on.
		if ( $applied < 59 ) {
			$add[] = 'emcp-tools/lifterlms-write';
		}
		return array(
			'add'   => array_values( array_unique( $add ) ),
			'strip' => array_values( array_unique( $strip ) ),
		);
	}

	/**
	 * The disabled set a fresh install starts with: the target of Tools >
	 * Bulk actions > Reset to defaults (spec 8.3).
	 *
	 * @return string[]
	 */
	public function default_disabled_tool_slugs(): array {
		$changes = $this->default_disabled_changes( 0 );
		return array_values( array_intersect( array_diff( $changes['add'], $changes['strip'] ), $this->get_all_tool_slugs() ) );
	}

	/**
	 * Register the settings with the WordPress Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting( EMCP_Tools_Page_Builders::SETTINGS_GROUP, EMCP_Tools_Page_Builders::BLOCK_PACK_OPTION, array(
			'type'=>'array', 'sanitize_callback'=>array('EMCP_Tools_Page_Builders','sanitize_block_packs')
		) );
		register_setting( EMCP_Tools_Page_Builders::SETTINGS_GROUP, EMCP_Tools_Page_Builders::OPTION, array(
			'type' => 'string',
			'sanitize_callback' => array( 'EMCP_Tools_Page_Builders', 'sanitize' ),
		) );
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_DISABLED_TOOLS,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_disabled_tools' ),
			)
		);

		// Compact tool mode (dispatcher) — Tools tab. OFF by default; surfaces 3
		// meta-tools (list-tools / get-tool-schema / call-tool) instead of every
		// individual tool for clients that cap the tool count. Registered under the
		// Tools form group so its toggle lives alongside the per-tool grid.
		register_setting(
			self::SETTINGS_GROUP,
			EMCP_Tools_Plugin::OPTION_DISPATCHER_MODE,
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Themer PHP Templates master switch (Tools tab). Off by default — the
		// feature lets AI author raw PHP region templates, so the admin opts in.
		register_setting(
			self::SETTINGS_GROUP,
			EMCP_Tools_Themer_PHP::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Content mirror auto-export (Tools tab). Off by default — when on, saving an
		// Elementor page/template also writes its JSON to uploads/emcp-content-mirror/
		// for external version control. The MCP export/restore tools work regardless.
		register_setting(
			self::SETTINGS_GROUP,
			EMCP_Tools_Content_Mirror::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// "Activate Abilities API for EMCP" server gate (Connection tab). On by
		// default; an absent checkbox on submit sanitizes to '0' (off).
		register_setting(
			self::SETTINGS_GROUP_SERVER,
			EMCP_Tools_Plugin::OPTION_SERVER_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// OAuth sign-in for MCP clients (Connection tab). No stored default — the
		// effective default is "on when HTTPS", enforced by
		// EMCP_Tools_OAuth_Server (is_available). The form posts a hidden 0 +
		// checkbox 1 so an unchecked box saves '0'.
		register_setting(
			self::SETTINGS_GROUP_SERVER,
			EMCP_Tools_OAuth_Server::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// OpenAI-strict tool schemas (Connection tab). OFF by default — it's only
		// for OpenAI-compatible strict function-calling clients (CrewAI, etc.) and
		// would otherwise break Gemini/Antigravity. (GitHub #42)
		register_setting(
			self::SETTINGS_GROUP_SERVER,
			'emcp_tools_strict_schemas',
			array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Server URL override (Connection tab). Empty = auto-detect from the REST
		// API. Set it when the site is served on a different URL than WordPress's
		// configured Site Address (e.g. staging with a pinned domain) so the
		// bundle / OAuth / configs use the reachable host. Accepts only http(s).
		register_setting(
			self::SETTINGS_GROUP_SERVER,
			EMCP_Tools_Site_Context::OPTION_BASE_URL,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					$value = trim( (string) $value );
					if ( '' === $value ) {
						return '';
					}
					$value  = esc_url_raw( $value );
					$scheme = wp_parse_url( $value, PHP_URL_SCHEME );
					if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
						return '';
					}
					return rtrim( $value, '/' );
				},
			)
		);

		// WP-CLI base command (Connection → 3rd Party Services) — the `wp` launcher
		// used for the shell / background-job path over HTTP (e.g. "wp" or
		// "php /path/to/wp-cli.phar"). Empty = in-process only (WP-CLI stdio).
		register_setting(
			self::SETTINGS_GROUP_SERVICES,
			'emcp_tools_wpcli_command',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					return sanitize_text_field( (string) $value );
				},
			)
		);

		// Stock-image provider API keys (Connection → 3rd Party Services sub-tab)
		// — power the stock-image tools (search-images / add-stock-image). All
		// three are free keys. Registered in their own group so that sub-tab's
		// form saves without touching the server-gate toggles. Keys are stored
		// encrypted at rest (EMCP_Tools_Secret) and never rendered back to the
		// form: the field posts empty when unchanged (we keep the stored value),
		// a per-field "__clear" checkbox removes it, and a new value is encrypted.
		// The remote-data provider keys (Weather, Google Reviews, Yelp, generic JSON
		// slots) share the exact same storage contract.
		$emcp_service_options = array_merge(
			array( EMCP_Tools_Unsplash_Client::OPTION, EMCP_Tools_Pexels_Client::OPTION, EMCP_Tools_Pixabay_Client::OPTION ),
			class_exists( 'EMCP_Tools_Remote_Keys' ) ? EMCP_Tools_Remote_Keys::options() : array()
		);
		foreach ( $emcp_service_options as $emcp_stock_option ) {
			register_setting(
				self::SETTINGS_GROUP_SERVICES,
				$emcp_stock_option,
				array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => static function ( $value ) use ( $emcp_stock_option ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings-group nonce before this runs.
						if ( ! empty( $_POST[ $emcp_stock_option . '__clear' ] ) ) {
							return '';
						}
						$value = sanitize_text_field( (string) $value );
						if ( '' === $value ) {
							// Unchanged (masked) submit — keep the stored value.
							return (string) get_option( $emcp_stock_option, '' );
						}
						// The Settings API can run this callback twice per save;
						// don't re-encrypt an already-encrypted token (would nest).
						if ( EMCP_Tools_Secret::is_encrypted( $value ) ) {
							return $value;
						}
						return EMCP_Tools_Secret::encrypt( $value );
					},
				)
			);
		}

		// Context page — the site-wide guidance + its on/off toggle.
		register_setting(
			self::SETTINGS_GROUP_CONTEXT,
			EMCP_Tools_Site_Context::OPTION_CONTEXT,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					$value = sanitize_textarea_field( (string) $value );
					return mb_substr( $value, 0, EMCP_Tools_Site_Context::MAX_CHARS );
				},
			)
		);
		register_setting(
			self::SETTINGS_GROUP_CONTEXT,
			EMCP_Tools_Site_Context::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => static function ( $value ) {
					return '1' === (string) $value ? '1' : '0';
				},
			)
		);

		// Modules tab — the active-modules list + each registered module's own
		// option keys (declared by the module's settings_fields()).
		register_setting(
			self::SETTINGS_GROUP_MODULES,
			EMCP_Tools_Module::OPTION_ACTIVE,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => static function ( $value ) {
					$value = is_array( $value ) ? $value : array();
					return array_values( array_map( 'sanitize_key', $value ) );
				},
			)
		);
		if ( class_exists( 'EMCP_Tools_Modules_Registry' ) ) {
			foreach ( EMCP_Tools_Modules_Registry::instance()->all() as $emcp_module ) {
				// Each module's keys live in the module's own group so its overlay
				// settings form saves independently of the active-modules toggles.
				$emcp_group = $emcp_module->settings_group();
				foreach ( $emcp_module->settings_fields() as $emcp_key => $emcp_args ) {
					register_setting( $emcp_group, $emcp_key, $emcp_args );
				}
			}
		}
	}

	/**
	 * Sanitize the disabled tools option value.
	 *
	 * The form submits an array of enabled tool slugs. We compute the
	 * disabled list as the difference between all known tools and the
	 * enabled ones submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The raw form input.
	 * @return string[] Sanitized array of disabled tool slugs.
	 */
	public function sanitize_disabled_tools( $input ): array {
		$all_tools = $this->get_all_tool_slugs();

		// Only when the Tools settings form is being submitted do we INVERT the
		// posted "enabled" checkboxes into a disabled list. We read the enabled
		// set straight from $_POST (not from $input) so this callback is
		// IDEMPOTENT: WordPress re-runs sanitize_option a second time via
		// add_option() the first time the option is created, and inverting
		// $input twice would zero the result (all -> none). It also keeps
		// programmatic update_option() calls (e.g. the default-disabled seeder)
		// from being inverted at all.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the settings nonce before sanitization runs.
		$is_settings_form = isset( $_POST['option_page'] )
			&& self::SETTINGS_GROUP === sanitize_text_field( wp_unslash( $_POST['option_page'] ) );

		if ( $is_settings_form ) {
			$enabled = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ self::OPTION_DISABLED_TOOLS ] ) && is_array( $_POST[ self::OPTION_DISABLED_TOOLS ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$enabled = array_map( 'sanitize_text_field', wp_unslash( $_POST[ self::OPTION_DISABLED_TOOLS ] ) );
			}
			// Hidden builder tools keep their settings when another builder is selected.
			$visible = $this->get_available_tool_slugs();
			$hidden = array_diff( $all_tools, $visible );
			$previous = (array) get_option( self::OPTION_DISABLED_TOOLS, array() );
			return array_values( array_unique( array_merge(
				array_diff( $visible, $enabled ),
				array_intersect( $hidden, $previous )
			) ) );
		}

		// Any other context: $input is already the final disabled list (e.g. the
		// default-disabled seeder). Clean against the known slugs and return —
		// this is idempotent, so a second sanitize pass leaves it unchanged.
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_values( array_intersect( $all_tools, array_map( 'sanitize_text_field', $input ) ) );
	}
}
