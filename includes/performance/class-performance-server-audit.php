<?php
/**
 * Server / WordPress / database performance audit (in-process, read-only).
 *
 * Every check is a pure evaluate_*() returning a Finding; run() gathers real
 * environment + DB values and delegates to them. No writes. No HTTP.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted).
 *
 * @package Elementor_MCP
 * @since   1.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 1.11.0
 */
class Elementor_MCP_Performance_Server_Audit {

	const MIN_MEMORY_BYTES      = 134217728;   // 128 MB
	const AUTOLOAD_WARN_BYTES   = 1048576;     // 1 MB
	const AUTOLOAD_CRIT_BYTES   = 3145728;     // 3 MB
	const PLUGIN_WARN_COUNT     = 40;
	const REVISIONS_WARN_COUNT  = 1000;
	const TOP_TABLES            = 5;
	const TOP_AUTOLOAD_OPTIONS  = 5;

	/**
	 * Run every server/DB/config check against the live environment.
	 *
	 * @return array Finding[]
	 */
	public function run(): array {
		global $wpdb;
		$findings = array();

		$findings[] = $this->evaluate_php_version( PHP_VERSION );
		$findings[] = $this->evaluate_memory_limit( (string) ini_get( 'memory_limit' ) );
		$findings[] = $this->evaluate_opcache( $this->opcache_enabled() );
		$findings[] = $this->evaluate_object_cache( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() );
		$findings[] = $this->evaluate_image_lib( extension_loaded( 'imagick' ), extension_loaded( 'gd' ) );

		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$findings[] = $this->evaluate_wp_debug( defined( 'WP_DEBUG' ) && WP_DEBUG, $env );

		$findings[] = $this->evaluate_plugin_count( count( (array) get_option( 'active_plugins', array() ) ) );
		$findings[] = $this->evaluate_revisions( $this->count_revisions( $wpdb ) );
		$findings[] = $this->evaluate_cron_backlog( $this->count_overdue_cron() );

		list( $autoload_bytes, $top_autoload ) = $this->autoload_stats( $wpdb );
		$findings[] = $this->evaluate_autoload_size( $autoload_bytes, $top_autoload );

		list( $db_bytes, $top_tables ) = $this->database_stats( $wpdb );
		$findings[] = $this->evaluate_database_size( $db_bytes, $top_tables );

		return $findings;
	}

	// ---- Pure evaluators (unit-tested) --------------------------------

	public function evaluate_php_version( string $version ): array {
		if ( version_compare( $version, '8.2', '>=' ) ) {
			return Elementor_MCP_Performance_Finding::make( 'php_version', 'server', 'PHP version', 'pass', $version, sprintf( 'PHP %s is current and supported.', $version ) );
		}
		if ( version_compare( $version, '8.0', '>=' ) ) {
			return Elementor_MCP_Performance_Finding::make( 'php_version', 'server', 'PHP version', 'warning', $version, sprintf( 'PHP %s is nearing end of life.', $version ), 'Upgrade to PHP 8.2 or newer for better performance and security support.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'php_version', 'server', 'PHP version', 'critical', $version, sprintf( 'PHP %s is end-of-life and unsupported.', $version ), 'Upgrade to PHP 8.2+ immediately; old PHP is slow and a security risk.' );
	}

	public function evaluate_memory_limit( string $limit ): array {
		$bytes = $this->to_bytes( $limit );
		if ( $bytes < 0 ) { // -1 = unlimited.
			return Elementor_MCP_Performance_Finding::make( 'memory_limit', 'server', 'PHP memory limit', 'pass', $limit, 'PHP memory is unlimited.' );
		}
		if ( $bytes >= self::MIN_MEMORY_BYTES ) {
			return Elementor_MCP_Performance_Finding::make( 'memory_limit', 'server', 'PHP memory limit', 'pass', $limit, sprintf( 'PHP memory limit is %s.', $limit ) );
		}
		return Elementor_MCP_Performance_Finding::make( 'memory_limit', 'server', 'PHP memory limit', 'warning', $limit, sprintf( 'PHP memory limit is only %s.', $limit ), 'Raise memory_limit (and WP_MEMORY_LIMIT) to at least 128M to avoid out-of-memory errors under load.' );
	}

	public function evaluate_opcache( bool $enabled ): array {
		return $enabled
			? Elementor_MCP_Performance_Finding::make( 'opcache', 'server', 'PHP OPcache', 'pass', true, 'OPcache is enabled.' )
			: Elementor_MCP_Performance_Finding::make( 'opcache', 'server', 'PHP OPcache', 'warning', false, 'OPcache is disabled.', 'Enable the Zend OPcache extension — it caches compiled PHP and dramatically reduces request time.' );
	}

	public function evaluate_object_cache( bool $persistent ): array {
		return $persistent
			? Elementor_MCP_Performance_Finding::make( 'object_cache', 'server', 'Persistent object cache', 'pass', true, 'A persistent object cache is active.' )
			: Elementor_MCP_Performance_Finding::make( 'object_cache', 'server', 'Persistent object cache', 'warning', false, 'No persistent object cache detected.', 'Add Redis or Memcached with a drop-in (e.g. redis-cache) to cache DB queries across requests.' );
	}

	public function evaluate_image_lib( bool $imagick, bool $gd ): array {
		if ( $imagick || $gd ) {
			return Elementor_MCP_Performance_Finding::make( 'image_lib', 'server', 'Image library', 'pass', $imagick ? 'imagick' : 'gd', 'An image processing library is available.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'image_lib', 'server', 'Image library', 'warning', 'none', 'No image library (Imagick/GD) detected.', 'Install Imagick or GD so WordPress can generate optimized image sizes.' );
	}

	public function evaluate_wp_debug( bool $on, string $environment ): array {
		if ( ! $on ) {
			return Elementor_MCP_Performance_Finding::make( 'wp_debug', 'config', 'WP_DEBUG', 'pass', false, 'WP_DEBUG is off.' );
		}
		if ( 'production' === $environment ) {
			return Elementor_MCP_Performance_Finding::make( 'wp_debug', 'config', 'WP_DEBUG', 'warning', true, 'WP_DEBUG is ON in production.', 'Turn off WP_DEBUG on production — debug logging and notices add overhead and leak information.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'wp_debug', 'config', 'WP_DEBUG', 'info', true, sprintf( 'WP_DEBUG is on (environment: %s).', $environment ) );
	}

	public function evaluate_plugin_count( int $count ): array {
		if ( $count > self::PLUGIN_WARN_COUNT ) {
			return Elementor_MCP_Performance_Finding::make( 'plugin_count', 'config', 'Active plugins', 'warning', $count, sprintf( '%d active plugins.', $count ), 'A large plugin count compounds per-request overhead. Audit for unused or overlapping plugins.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'plugin_count', 'config', 'Active plugins', 'info', $count, sprintf( '%d active plugins.', $count ) );
	}

	public function evaluate_revisions( int $count ): array {
		if ( $count > self::REVISIONS_WARN_COUNT ) {
			return Elementor_MCP_Performance_Finding::make( 'post_revisions', 'database', 'Post revisions', 'warning', $count, sprintf( '%d post revisions stored.', $count ), 'Cap revisions with define( "WP_POST_REVISIONS", 5 ) and clean old ones to shrink the posts table.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'post_revisions', 'database', 'Post revisions', 'info', $count, sprintf( '%d post revisions stored.', $count ) );
	}

	public function evaluate_cron_backlog( int $overdue ): array {
		if ( $overdue > 0 ) {
			return Elementor_MCP_Performance_Finding::make( 'cron_backlog', 'config', 'WP-Cron backlog', 'warning', $overdue, sprintf( '%d overdue cron events.', $overdue ), 'A backlog means cron is not firing (low traffic, or DISABLE_WP_CRON without a real cron job). Add a server cron hitting wp-cron.php.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'cron_backlog', 'config', 'WP-Cron backlog', 'pass', 0, 'No overdue cron events.' );
	}

	public function evaluate_autoload_size( int $bytes, array $top_options ): array {
		$human = $this->human_bytes( $bytes );
		$value = array( 'bytes' => $bytes, 'top' => $top_options );
		if ( $bytes >= self::AUTOLOAD_CRIT_BYTES ) {
			return Elementor_MCP_Performance_Finding::make( 'autoload_size', 'database', 'Autoloaded options', 'critical', $value, sprintf( 'Autoloaded options total %s — loaded on every request.', $human ), 'Find and disable autoload for the largest offenders (often stale plugin caches). See the listed options.' );
		}
		if ( $bytes >= self::AUTOLOAD_WARN_BYTES ) {
			return Elementor_MCP_Performance_Finding::make( 'autoload_size', 'database', 'Autoloaded options', 'warning', $value, sprintf( 'Autoloaded options total %s.', $human ), 'Trim autoloaded options above ~1 MB; large autoload bloats every request.' );
		}
		return Elementor_MCP_Performance_Finding::make( 'autoload_size', 'database', 'Autoloaded options', 'pass', $value, sprintf( 'Autoloaded options total %s.', $human ) );
	}

	public function evaluate_database_size( int $bytes, array $top_tables ): array {
		return Elementor_MCP_Performance_Finding::make( 'database_size', 'database', 'Database size', 'info', array( 'bytes' => $bytes, 'top_tables' => $top_tables ), sprintf( 'Database is %s; %d largest tables listed.', $this->human_bytes( $bytes ), count( $top_tables ) ) );
	}

	// ---- Live gatherers (exercised in live verification, not unit tests) ----

	private function opcache_enabled(): bool {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return false;
		}
		$status = @opcache_get_status( false );
		return is_array( $status ) && ! empty( $status['opcache_enabled'] );
	}

	private function count_revisions( $wpdb ): int {
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'revision' ) );
	}

	private function count_overdue_cron(): int {
		$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
		if ( ! is_array( $crons ) ) {
			return 0;
		}
		$now     = time();
		$overdue = 0;
		foreach ( array_keys( $crons ) as $timestamp ) {
			if ( (int) $timestamp < ( $now - 300 ) ) { // >5 min late.
				$overdue++;
			}
		}
		return $overdue;
	}

	/**
	 * Pure: the SQL predicate selecting autoloaded options. WordPress 6.6+ stores
	 * autoloaded options under any of the values in Core's
	 * wp_autoload_values_to_autoload() — 'yes','on','auto-on','auto' — so all four
	 * must be counted. Omitting 'auto-on' silently excludes large 6.6+ rows from
	 * the autoload total, producing a false "pass".
	 *
	 * @return string
	 */
	public function autoload_where_clause(): string {
		return "autoload IN ('yes','on','auto-on','auto')";
	}

	private function autoload_stats( $wpdb ): array {
		$where = $this->autoload_where_clause();
		$bytes = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE {$where}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS sz FROM {$wpdb->options} WHERE {$where} ORDER BY sz DESC LIMIT %d",
				self::TOP_AUTOLOAD_OPTIONS
			),
			ARRAY_A
		);
		$top = array();
		foreach ( (array) $rows as $r ) {
			$top[] = array( 'option' => (string) $r['option_name'], 'bytes' => (int) $r['sz'] );
		}
		return array( $bytes, $top );
	}

	private function database_stats( $wpdb ): array {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS n, (data_length + index_length) AS sz FROM information_schema.TABLES WHERE table_schema = %s ORDER BY sz DESC LIMIT %d',
				DB_NAME,
				self::TOP_TABLES
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s',
				DB_NAME
			)
		);
		$top = array();
		foreach ( (array) $rows as $r ) {
			$top[] = array( 'table' => (string) $r['n'], 'bytes' => (int) $r['sz'] );
		}
		return array( $total, $top );
	}

	// ---- Small helpers ------------------------------------------------

	private function to_bytes( string $value ): int {
		$value = trim( $value );
		if ( '-1' === $value ) {
			return -1;
		}
		$unit = strtolower( substr( $value, -1 ) );
		$num  = (int) $value;
		switch ( $unit ) {
			case 'g': return $num * 1024 * 1024 * 1024;
			case 'm': return $num * 1024 * 1024;
			case 'k': return $num * 1024;
			default:  return (int) $value;
		}
	}

	private function human_bytes( int $bytes ): string {
		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 1 ) . ' MB';
		}
		if ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}
		return $bytes . ' B';
	}
}
