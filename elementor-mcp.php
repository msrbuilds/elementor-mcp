<?php
/**
 * Plugin Name:       MCP Tools for Elementor
 * Plugin URI:        https://github.com/msrbuilds/elementor-mcpelementor-mcp
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           1.7.0
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
define( 'ELEMENTOR_MCP_VERSION', '1.7.0' );
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
                'premium_suffix'      => 'Pro',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
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

    // Freemius requires uninstall logic to live on its after_uninstall hook
    // instead of WordPress's uninstall.php, so its own cleanup and ours run
    // in the right order.
    emcp_pro_fs()->add_action( 'after_uninstall', 'elementor_mcp_after_uninstall' );
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

	// MCP Adapter must be active.
	if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
		$missing[] = 'WordPress MCP Adapter';
	}

	// WordPress Abilities API must be available.
	if ( ! function_exists( 'wp_register_ability' ) ) {
		$missing[] = 'WordPress Abilities API (requires WordPress 6.9+)';
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

		// Branded chrome around the Freemius pricing screen.
		if ( function_exists( 'emcp_pro_fs' ) ) {
			require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-pricing-page.php';
			( new Elementor_MCP_Pricing_Page() )->init();

			require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-pro-prompts.php';
			add_action( 'wp_ajax_elementor_mcp_sync_pro_prompts', 'elementor_mcp_sync_pro_prompts_ajax' );
		}
	}

	// Boot the plugin.
	Elementor_MCP_Plugin::instance();
}
add_action( 'plugins_loaded', 'elementor_mcp_init', 20 );
