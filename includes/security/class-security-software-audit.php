<?php
/**
 * Outdated / abandoned software audit.
 *
 * Every evaluate_*() is pure (unit-tested). run() gathers live update transients
 * + bounded plugins_api lookups (verified live). Read-only. No external CVE DB.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted).
 *
 * @package Elementor_MCP
 * @since   1.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.12.0
 */
class Elementor_MCP_Security_Software_Audit {

	const MAX_ABANDONED_LOOKUPS = 30;
	const ABANDONED_CACHE_TTL   = 43200;  // 12 hours — cache per-slug closed/open status.

	public function evaluate_core_update( bool $available, string $current, string $new ): array {
		return $available
			? Elementor_MCP_Security_Finding::make( 'software_core_update', 'software', 'WordPress core', 'warning', array( 'current' => $current, 'new' => $new ), sprintf( 'WordPress core is outdated (%s → %s).', $current, $new ), 'Update WordPress core; releases include security fixes.' )
			: Elementor_MCP_Security_Finding::make( 'software_core_update', 'software', 'WordPress core', 'pass', $current, sprintf( 'WordPress core is up to date (%s).', $current ) );
	}

	/**
	 * @param array<int,array{name:string,current:string,new:string}> $updates
	 * @param string                                                   $kind 'plugin'|'theme'
	 * @return array Finding[]
	 */
	public function evaluate_updates( array $updates, string $kind ): array {
		$label = 'theme' === $kind ? 'Outdated theme' : 'Outdated plugin';
		$id    = 'theme' === $kind ? 'software_theme_update' : 'software_plugin_update';
		$out   = array();
		foreach ( $updates as $u ) {
			$name    = (string) ( $u['name'] ?? 'unknown' );
			$current = (string) ( $u['current'] ?? '?' );
			$new     = (string) ( $u['new'] ?? '?' );
			$out[]   = Elementor_MCP_Security_Finding::make(
				$id, 'software', $label, 'warning',
				array( 'name' => $name, 'current' => $current, 'new' => $new ),
				sprintf( '%s "%s" is outdated (%s → %s).', ucfirst( $kind ), $name, $current, $new ),
				sprintf( 'Update %s "%s"; outdated %ss are a leading source of site compromise.', $kind, $name, $kind )
			);
		}
		return $out;
	}

	/**
	 * @param string[] $slugs Plugin slugs that are closed/removed/abandoned.
	 * @return array Finding[]
	 */
	public function evaluate_abandoned( array $slugs ): array {
		$out = array();
		foreach ( $slugs as $slug ) {
			$out[] = Elementor_MCP_Security_Finding::make(
				'software_abandoned', 'software', 'Abandoned plugin', 'warning', (string) $slug,
				sprintf( 'Plugin "%s" appears closed or removed from the wordpress.org directory.', $slug ),
				'Plugins removed from the directory often have unpatched security issues. Replace it with a maintained alternative.'
			);
		}
		return $out;
	}

	public function evaluate_inactive( int $count ): array {
		return $count > 0
			? Elementor_MCP_Security_Finding::make( 'software_inactive', 'software', 'Inactive components', 'info', $count, sprintf( '%d inactive plugin(s)/theme(s) installed.', $count ), 'Delete plugins and themes you do not use; inactive code can still be exploited if reachable.' )
			: Elementor_MCP_Security_Finding::make( 'software_inactive', 'software', 'Inactive components', 'pass', 0, 'No inactive plugins or themes.' );
	}

	/**
	 * Live gather.
	 *
	 * @return array Finding[]
	 */
	public function run(): array {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$findings = array();

		// Core.
		$core_updates = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
		global $wp_version;
		if ( ! empty( $core_updates ) && is_array( $core_updates ) && isset( $core_updates[0]->response ) && 'upgrade' === $core_updates[0]->response ) {
			$findings[] = $this->evaluate_core_update( true, (string) $wp_version, (string) ( $core_updates[0]->version ?? '?' ) );
		} else {
			$findings[] = $this->evaluate_core_update( false, (string) $wp_version, (string) $wp_version );
		}

		// Plugins.
		$plugin_updates = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();
		$p_norm         = array();
		foreach ( (array) $plugin_updates as $data ) {
			$p_norm[] = array(
				'name'    => (string) ( $data->Name ?? '' ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'current' => (string) ( $data->Version ?? '?' ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'new'     => (string) ( $data->update->new_version ?? '?' ),
			);
		}
		$findings = array_merge( $findings, $this->evaluate_updates( $p_norm, 'plugin' ) );

		// Themes.
		$theme_updates = function_exists( 'get_theme_updates' ) ? get_theme_updates() : array();
		$t_norm        = array();
		foreach ( (array) $theme_updates as $theme ) {
			$t_norm[] = array(
				'name'    => (string) ( is_object( $theme ) && method_exists( $theme, 'get' ) ? $theme->get( 'Name' ) : '' ),
				'current' => (string) ( is_object( $theme ) && method_exists( $theme, 'get' ) ? $theme->get( 'Version' ) : '?' ),
				'new'     => (string) ( $theme->update['new_version'] ?? '?' ),
			);
		}
		$findings = array_merge( $findings, $this->evaluate_updates( $t_norm, 'theme' ) );

		// Inactive count.
		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$inactive       = max( 0, count( $all_plugins ) - count( $active_plugins ) );
		$findings[]     = $this->evaluate_inactive( $inactive );

		// Abandoned (bounded plugins_api lookups for active wp.org plugins).
		$findings = array_merge( $findings, $this->evaluate_abandoned( $this->detect_abandoned( $active_plugins ) ) );

		return $findings;
	}

	/**
	 * Detect plugins closed/removed from the wordpress.org directory.
	 *
	 * Per-slug results are cached in a transient so repeat scans are instant; the
	 * MAX_ABANDONED_LOOKUPS cap bounds only live API calls (cached slugs are always
	 * resolved). A WP_Error (premium plugin / API down) is neither flagged nor
	 * cached, so a transient outage is retried next scan.
	 *
	 * @param string[] $active_plugins
	 * @return string[] closed/removed slugs
	 */
	private function detect_abandoned( array $active_plugins ): array {
		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		$closed  = array();
		$checked = 0;
		foreach ( $active_plugins as $file ) {
			$slug = strtok( (string) $file, '/' );
			if ( '' === $slug ) {
				continue;
			}

			$cache_key = 'elementor_mcp_sec_abandoned_' . md5( $slug );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				if ( 'closed' === $cached ) {
					$closed[] = $slug;
				}
				continue;
			}

			if ( $checked >= self::MAX_ABANDONED_LOOKUPS ) {
				continue; // Live API-call budget spent; skip uncached slugs this run.
			}
			$checked++;

			$info = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $info ) ) {
				continue; // Not on wordpress.org, or API down — don't flag, don't cache.
			}
			$state = ! empty( $info->closed ) ? 'closed' : 'open';
			set_transient( $cache_key, $state, self::ABANDONED_CACHE_TTL );
			if ( 'closed' === $state ) {
				$closed[] = $slug;
			}
		}
		return $closed;
	}
}
