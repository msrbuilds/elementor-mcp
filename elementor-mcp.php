<?php
/**
 * Plugin Name:       MCP Tools for Elementor
 * Plugin URI:        https://github.com/msrbuilds/elementor-mcpelementor-mcp
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           1.9.0
 * Requires at least: 6.9
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Author:            Mian Shahzad Raza
 * Author URI:        https://msrbuilds.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elementor-mcp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ELEMENTOR_MCP_VERSION', '1.9.0' );
define( 'ELEMENTOR_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTOR_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'ELEMENTOR_MCP_BASENAME', plugin_basename( __FILE__ ) );

if ( ! function_exists( 'emcp_pro_fs' ) ) {
    // Create a helper function for easy SDK access.
    function emcp_pro_fs() {
        global $emcp_pro_fs;

        if ( ! isset( $emcp_pro_fs ) ) {
            // Activate multisite network integration.
            if ( ! defined( 'WP_FS__PRODUCT_30577_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_30577_MULTISITE', true );
            }

            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/includes/vendors/fremius/start.php';

            $emcp_pro_fs = fs_dynamic_init( array(
                'id'                  => '30577',
                'slug'                => 'elementor-mcp',
                'premium_slug'        => 'emcp-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_2b2a026d5c27655581635abcd4556',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'is_org_compliant'    => false,
                'has_affiliation'     => 'selected',
                'menu'                => array(
                    'slug'           => 'elementor-mcp',
                    'support'        => false,
                ),
            ) );
        }

        return $emcp_pro_fs;
    }

    // Init Freemius.
    emcp_pro_fs();
    // Signal that SDK was initiated.
    do_action( 'emcp_pro_fs_loaded' );
}

/**
 * Canonical "Upgrade to Pro" URL — the external pricing page on the EMCP
 * Tools website. Used by every upgrade CTA in the plugin admin so users
 * land on the public pricing page (with full plan comparison + FAQ) rather
 * than Freemius's bundled in-admin pricing iframe.
 *
 * @since 1.7.1
 *
 * @return string
 */
function elementor_mcp_upgrade_url(): string {
    return 'https://emcp.msrbuilds.com/pricing';
}

/**
 * Removes plugin-owned options on uninstall.
 *
 * @since 1.6.1
 */
function elementor_mcp_after_uninstall() {
    delete_option( 'elementor_mcp_disabled_tools' );
    delete_option( 'elementor_mcp_low_tool_mode' );
    delete_option( 'elementor_mcp_defaults_applied' );
    delete_transient( 'elementor_mcp_pro_prompts_bundle' );
    delete_transient( 'elementor_mcp_pro_templates_bundle' );
    delete_transient( 'elementor_mcp_pro_brand_kits_bundle' );
    // Drop the dismissal flag from every user.
    delete_metadata( 'user', 0, 'elementor_mcp_upgrade_notice_dismissed', '', true );
    // Brand-kit backups (emcp_kit_backup CPT) are intentionally LEFT in place
    // on uninstall — treated as recoverable user content so a user who removes
    // the plugin can still roll back their pre-kit brand after reinstalling.

    // Widget Builder: generated executable PHP must NOT survive uninstall —
    // delete every emcp_widget post and remove the uploads sandbox tree.
    if ( ! class_exists( 'Elementor_MCP_Widget_Store' ) ) {
        require_once ELEMENTOR_MCP_DIR . 'includes/class-widget-store.php';
    }
    if ( class_exists( 'Elementor_MCP_Widget_Store' ) ) {
        Elementor_MCP_Widget_Store::uninstall_cleanup();
    }
}

/**
 * AJAX handler for the "Sync Library" button on the Pro Prompts page.
 *
 * @since 1.7.0
 */
function elementor_mcp_sync_pro_prompts_ajax() {
    check_ajax_referer( 'elementor_mcp_sync_pro_prompts', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to sync prompts.', 'elementor-mcp' ) ), 403 );
    }

    $bundle = Elementor_MCP_Pro_Prompts::get_bundle( true );
    if ( is_wp_error( $bundle ) ) {
        wp_send_json_error( array( 'message' => $bundle->get_error_message() ), 400 );
    }

    $total = 0;
    foreach ( $bundle['categories'] as $category ) {
        $total += isset( $category['prompts'] ) && is_array( $category['prompts'] ) ? count( $category['prompts'] ) : 0;
    }

    wp_send_json_success(
        array(
            'message'    => sprintf(
                /* translators: %1$d: total prompts, %2$d: total categories */
                __( 'Synced %1$d prompts across %2$d categories.', 'elementor-mcp' ),
                $total,
                count( $bundle['categories'] )
            ),
            'fetched_at' => $bundle['fetched_at'],
        )
    );
}

/**
 * AJAX handler for the Sync Library button on the Pro Templates page.
 *
 * @since 1.7.1
 */
function elementor_mcp_sync_pro_templates_ajax() {
    check_ajax_referer( 'elementor_mcp_sync_pro_templates', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to sync templates.', 'elementor-mcp' ) ), 403 );
    }

    $bundle = Elementor_MCP_Pro_Templates::get_bundle( true );
    if ( is_wp_error( $bundle ) ) {
        wp_send_json_error( array( 'message' => $bundle->get_error_message() ), 400 );
    }

    $total = 0;
    foreach ( $bundle['categories'] as $category ) {
        $total += isset( $category['templates'] ) && is_array( $category['templates'] ) ? count( $category['templates'] ) : 0;
    }

    wp_send_json_success(
        array(
            'message'    => sprintf(
                /* translators: %1$d: total templates, %2$d: total categories */
                __( 'Synced %1$d templates across %2$d categories.', 'elementor-mcp' ),
                $total,
                count( $bundle['categories'] )
            ),
            'fetched_at' => $bundle['fetched_at'],
        )
    );
}

/**
 * AJAX handler for applying a template to a new (or existing) page.
 *
 * @since 1.7.1
 */
function elementor_mcp_apply_pro_template_ajax() {
    check_ajax_referer( 'elementor_mcp_apply_pro_template', 'nonce' );

    if ( ! current_user_can( 'edit_pages' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to create pages.', 'elementor-mcp' ) ), 403 );
    }

    $category_slug  = isset( $_POST['category_slug'] ) ? sanitize_key( wp_unslash( $_POST['category_slug'] ) ) : '';
    $template_slug  = isset( $_POST['template_slug'] ) ? sanitize_key( wp_unslash( $_POST['template_slug'] ) ) : '';
    $target_post_id = isset( $_POST['target_post_id'] ) ? absint( wp_unslash( $_POST['target_post_id'] ) ) : 0;

    if ( '' === $category_slug || '' === $template_slug ) {
        wp_send_json_error( array( 'message' => __( 'Missing category or template slug.', 'elementor-mcp' ) ), 400 );
    }

    $result = Elementor_MCP_Pro_Templates::apply_template( $category_slug, $template_slug, $target_post_id );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
    }

    wp_send_json_success( $result );
}

/**
 * AJAX handler for importing a template into Elementor's Saved Templates library.
 *
 * @since 1.7.1
 */
function elementor_mcp_import_pro_template_ajax() {
    check_ajax_referer( 'elementor_mcp_import_pro_template', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to import templates.', 'elementor-mcp' ) ), 403 );
    }

    $category_slug = isset( $_POST['category_slug'] ) ? sanitize_key( wp_unslash( $_POST['category_slug'] ) ) : '';
    $template_slug = isset( $_POST['template_slug'] ) ? sanitize_key( wp_unslash( $_POST['template_slug'] ) ) : '';

    if ( '' === $category_slug || '' === $template_slug ) {
        wp_send_json_error( array( 'message' => __( 'Missing category or template slug.', 'elementor-mcp' ) ), 400 );
    }

    $result = Elementor_MCP_Pro_Templates::import_to_library( $category_slug, $template_slug );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
    }

    wp_send_json_success( $result );
}

/**
 * AJAX handler for the Sync Library button on the Brand Kits page.
 *
 * @since 1.8.0
 */
function elementor_mcp_sync_pro_brand_kits_ajax() {
    check_ajax_referer( 'elementor_mcp_sync_pro_brand_kits', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) || ! Elementor_MCP_Pro_Brand_Kits::user_has_access() ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to sync brand kits.', 'elementor-mcp' ) ), 403 );
    }

    $bundle = Elementor_MCP_Pro_Brand_Kits::get_bundle( true );
    if ( is_wp_error( $bundle ) ) {
        wp_send_json_error( array( 'message' => $bundle->get_error_message() ), 400 );
    }

    $total = 0;
    foreach ( $bundle['categories'] as $category ) {
        $total += isset( $category['kits'] ) && is_array( $category['kits'] ) ? count( $category['kits'] ) : 0;
    }

    wp_send_json_success(
        array(
            'message'    => sprintf(
                /* translators: %1$d: total kits, %2$d: total categories */
                __( 'Synced %1$d brand kits across %2$d categories.', 'elementor-mcp' ),
                $total,
                count( $bundle['categories'] )
            ),
            'fetched_at' => $bundle['fetched_at'],
        )
    );
}

/**
 * AJAX handler for applying a brand kit from the admin page.
 *
 * @since 1.8.0
 */
function elementor_mcp_apply_pro_brand_kit_ajax() {
    check_ajax_referer( 'elementor_mcp_apply_pro_brand_kit', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to apply brand kits.', 'elementor-mcp' ) ), 403 );
    }

    $kit_slug      = isset( $_POST['kit_slug'] ) ? sanitize_key( wp_unslash( $_POST['kit_slug'] ) ) : '';
    $category_slug = isset( $_POST['category_slug'] ) ? sanitize_key( wp_unslash( $_POST['category_slug'] ) ) : '';
    $do_backup     = ! isset( $_POST['backup'] ) || '0' !== (string) wp_unslash( $_POST['backup'] );

    if ( '' === $kit_slug ) {
        wp_send_json_error( array( 'message' => __( 'Missing kit slug.', 'elementor-mcp' ) ), 400 );
    }

    // Resolve the kit from the Pro remote library when the site has it, falling
    // back to the 10 bundled free kits otherwise. Applying is a free feature;
    // the Pro library is just a larger pool of kits to apply from.
    $kit = null;
    if ( class_exists( 'Elementor_MCP_Pro_Brand_Kits' ) && Elementor_MCP_Pro_Brand_Kits::user_has_access() ) {
        $kit = Elementor_MCP_Pro_Brand_Kits::find_kit( $kit_slug, $category_slug );
    }
    if ( null === $kit && class_exists( 'Elementor_MCP_Free_Brand_Kits' ) ) {
        $kit = Elementor_MCP_Free_Brand_Kits::find_kit( $kit_slug, $category_slug );
    }
    if ( null === $kit ) {
        wp_send_json_error( array( 'message' => __( 'Brand kit not found. Try syncing the library first.', 'elementor-mcp' ) ), 404 );
    }

    $backup_id = null;
    if ( $do_backup ) {
        $backup = Elementor_MCP_Kit_Backup_Store::create( isset( $kit['title'] ) ? (string) $kit['title'] : $kit_slug );
        if ( ! is_wp_error( $backup ) ) {
            $backup_id = (int) $backup;
        }
    }

    $result = Elementor_MCP_Pro_Brand_Kits::apply_kit( $kit );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
    }

    $result['backup_id'] = $backup_id;
    $result['view_url']  = elementor_mcp_recent_elementor_page_url();
    wp_send_json_success( $result );
}

/**
 * AJAX handler for restoring a brand kit backup from the admin page.
 *
 * @since 1.8.0
 */
function elementor_mcp_restore_pro_brand_kit_ajax() {
    check_ajax_referer( 'elementor_mcp_restore_pro_brand_kit', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to restore brand kits.', 'elementor-mcp' ) ), 403 );
    }

    $backup_id    = isset( $_POST['backup_id'] ) ? absint( wp_unslash( $_POST['backup_id'] ) ) : 0;
    $full_clobber = isset( $_POST['full_clobber'] ) && '1' === (string) wp_unslash( $_POST['full_clobber'] );

    if ( $backup_id <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Missing or invalid backup.', 'elementor-mcp' ) ), 400 );
    }

    $result = Elementor_MCP_Kit_Backup_Store::restore( $backup_id, $full_clobber );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
    }

    wp_send_json_success(
        array(
            'message'  => __( 'Brand restored from backup.', 'elementor-mcp' ),
            'view_url' => elementor_mcp_recent_elementor_page_url(),
        )
    );
}

/**
 * URL of the most-recently-modified Elementor page (builder mode), or the
 * site homepage as a fallback. Used by the apply/restore toasts so the user
 * lands somewhere that actually showcases the change.
 *
 * @since 1.8.0
 *
 * @return string
 */
function elementor_mcp_recent_elementor_page_url(): string {
    $query = new WP_Query(
        array(
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key'   => '_elementor_edit_mode',
                    'value' => 'builder',
                ),
            ),
        )
    );

    if ( ! empty( $query->posts ) ) {
        $permalink = get_permalink( $query->posts[0] );
        if ( $permalink ) {
            return $permalink;
        }
    }

    return home_url( '/' );
}

/**
 * Recursively removes empty strings from enum arrays in a JSON Schema.
 *
 * Some MCP clients (e.g. Gemini/Antigravity) reject empty string values
 * inside enum arrays. This sanitizer strips them from any schema structure,
 * including nested properties, items, and allOf/oneOf/anyOf.
 *
 * Also ensures empty `properties` objects serialize as JSON `{}` not `[]`.
 *
 * @since 1.4.3
 *
 * @param array $schema A JSON Schema array.
 * @return array The sanitized schema.
 */
function elementor_mcp_sanitize_schema( array $schema ): array {
	// Strip empty strings from enum arrays.
	if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
		$schema['enum'] = array_values(
			array_filter(
				$schema['enum'],
				function ( $value ) {
					return '' !== $value;
				}
			)
		);
		if ( empty( $schema['enum'] ) ) {
			unset( $schema['enum'] );
		}
	}

	// Recurse into properties.
	if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
		if ( empty( $schema['properties'] ) ) {
			$schema['properties'] = new \stdClass();
		} else {
			foreach ( $schema['properties'] as $key => $prop ) {
				if ( is_array( $prop ) ) {
					$schema['properties'][ $key ] = elementor_mcp_sanitize_schema( $prop );
				}
			}
		}
	}

	// Recurse into items.
	if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
		$schema['items'] = elementor_mcp_sanitize_schema( $schema['items'] );
	}

	// Recurse into allOf, oneOf, anyOf.
	foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $keyword ) {
		if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
			foreach ( $schema[ $keyword ] as $i => $sub ) {
				if ( is_array( $sub ) ) {
					$schema[ $keyword ][ $i ] = elementor_mcp_sanitize_schema( $sub );
				}
			}
		}
	}

	return $schema;
}

/**
 * Wrapper around wp_register_ability that sanitizes schemas for cross-client compatibility.
 *
 * @since 1.4.3
 *
 * @param string $name    The ability name.
 * @param array  $args    The ability arguments.
 * @return mixed The result of wp_register_ability().
 */
function elementor_mcp_register_ability( string $name, array $args ) {
	if ( isset( $args['input_schema'] ) && is_array( $args['input_schema'] ) ) {
		$args['input_schema'] = elementor_mcp_sanitize_schema( $args['input_schema'] );
	}
	if ( isset( $args['output_schema'] ) && is_array( $args['output_schema'] ) ) {
		$args['output_schema'] = elementor_mcp_sanitize_schema( $args['output_schema'] );
	}
	return wp_register_ability( $name, $args );
}

/**
 * Checks that all required dependencies are available.
 *
 * @since 1.0.0
 *
 * @return bool True if all dependencies are met.
 */
function elementor_mcp_check_dependencies(): bool {
	$missing = array();

	// Elementor must be active.
	if ( ! did_action( 'elementor/loaded' ) ) {
		$missing[] = 'Elementor';
	}

	// WordPress Abilities API must be available. Core in WordPress 6.9+ (and
	// 7.0); only missing on older WordPress, which the plugin doesn't support.
	if ( ! function_exists( 'wp_register_ability' ) ) {
		$missing[] = 'WordPress Abilities API (requires WordPress 6.9+)';
	}

	// MCP Adapter: as of v1.8.0 the adapter is bundled with the plugin
	// (Elementor_MCP_Adapter_Bootstrap::ensure() ran in elementor_mcp_init,
	// loading either an active standalone adapter or our bundled copy). So this
	// is normally satisfied without any separate install. It only fails if the
	// bundled source is missing/corrupt — a broken build, not a user action.
	if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
		$missing[] = 'WordPress MCP Adapter (bundled — reinstall the plugin if this persists)';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function () use ( $missing ) {
			$list = implode( ', ', $missing );
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					/* translators: %s: comma-separated list of missing dependencies */
					esc_html__( 'MCP Tools for Elementor requires the following to be installed and active: %s', 'elementor-mcp' ),
					'<strong>' . esc_html( $list ) . '</strong>'
				)
			);
		} );

		return false;
	}

	return true;
}

/**
 * Initializes the plugin.
 *
 * Hooked to `plugins_loaded` at priority 20 to ensure Elementor and
 * other dependencies are loaded first.
 *
 * @since 1.0.0
 */
function elementor_mcp_init(): void {
	// Make the MCP Adapter available (active standalone plugin, else our bundled
	// copy) BEFORE the dependency check, so the adapter is never a "go install
	// this" blocker. The Abilities API is core in WordPress 6.9+/7.0.
	require_once ELEMENTOR_MCP_DIR . 'includes/class-mcp-adapter-bootstrap.php';
	Elementor_MCP_Adapter_Bootstrap::ensure();

	if ( ! elementor_mcp_check_dependencies() ) {
		return;
	}

	// Load class files.
	require_once ELEMENTOR_MCP_DIR . 'includes/class-id-generator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-elementor-data.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-element-factory.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/schemas/class-control-mapper.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/schemas/class-schema-generator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/validators/class-element-validator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/validators/class-settings-validator.php';
	// SEO / A11y toolkit shared helpers (used by the Pro audit abilities).
	require_once ELEMENTOR_MCP_DIR . 'includes/class-color-contrast.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-content-extractor.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-seo-meta.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-query-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-page-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-layout-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-widget-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-template-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-global-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-composite-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-openverse-client.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-stock-image-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-svg-icon-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-custom-code-abilities.php';
	// Brand Kits (Pro). The writer + backup store + fetcher + abilities load
	// unconditionally (no admin dependency) so the MCP REST/CLI/proxy surface
	// can reach them; every write method is independently Pro-gated.
	require_once ELEMENTOR_MCP_DIR . 'includes/class-system-kit-writer.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-kit-backup-store.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-free-brand-kits.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-pro-brand-kits.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-system-kit-abilities.php';
	add_action( 'init', array( 'Elementor_MCP_Kit_Backup_Store', 'register_post_type' ) );
	// Widget Builder (Pro) — sandboxed AI-generated Elementor widgets. The
	// generator/store/loader load unconditionally so the MCP surface can reach
	// them; every write + the loader itself are independently Pro-gated.
	require_once ELEMENTOR_MCP_DIR . 'includes/class-widget-generator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-widget-store.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-widget-loader.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-widget-builder-abilities.php';
	add_action( 'init', array( 'Elementor_MCP_Widget_Store', 'register_post_type' ) );
	( new Elementor_MCP_Widget_Loader() )->register_hooks();
	// SEO toolkit abilities (Pro only; self-guards on license at registration).
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-seo-abilities.php';
	// Accessibility toolkit abilities (Pro only; self-guards on license).
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-a11y-abilities.php';
	// Atomic elements support (Elementor 4.0+).
	require_once ELEMENTOR_MCP_DIR . 'includes/class-atomic-props.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-atomic-styles.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-atomic-widget-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-atomic-layout-abilities.php';

	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-ability-registrar.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-plugin.php';

	// Admin.
	if ( is_admin() ) {
		require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-admin.php';

		if ( function_exists( 'emcp_pro_fs' ) ) {
			require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-pro-prompts.php';
			add_action( 'wp_ajax_elementor_mcp_sync_pro_prompts', 'elementor_mcp_sync_pro_prompts_ajax' );

			require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-pro-templates.php';
			add_action( 'wp_ajax_elementor_mcp_sync_pro_templates', 'elementor_mcp_sync_pro_templates_ajax' );
			add_action( 'wp_ajax_elementor_mcp_apply_pro_template', 'elementor_mcp_apply_pro_template_ajax' );
			add_action( 'wp_ajax_elementor_mcp_import_pro_template', 'elementor_mcp_import_pro_template_ajax' );

			// Brand Kits (class loaded unconditionally above).
			add_action( 'wp_ajax_elementor_mcp_sync_pro_brand_kits', 'elementor_mcp_sync_pro_brand_kits_ajax' );
			add_action( 'wp_ajax_elementor_mcp_apply_pro_brand_kit', 'elementor_mcp_apply_pro_brand_kit_ajax' );
			add_action( 'wp_ajax_elementor_mcp_restore_pro_brand_kit', 'elementor_mcp_restore_pro_brand_kit_ajax' );

			require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-pro-skills.php';
			( new Elementor_MCP_Pro_Skills() )->init();

			require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-upgrade-notice.php';
			( new Elementor_MCP_Upgrade_Notice() )->init();
		}
	}

	// Boot the plugin.
	Elementor_MCP_Plugin::instance();
}
add_action( 'plugins_loaded', 'elementor_mcp_init', 20 );
