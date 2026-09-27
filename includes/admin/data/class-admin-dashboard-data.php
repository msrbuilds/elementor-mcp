<?php
/**
 * Dashboard screen data (spec 8.1). Cached reads only: the frame never makes
 * a remote request.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the Dashboard boot payload and the range refetch.
 */
final class EMCP_Tools_Admin_Dashboard_Data {

	const RANGES     = array( 7, 14, 30 );
	const CLIENT_TTL = 86400;
	const TUTORIALS  = 'https://emcptools.com/tutorials';

	/** @var EMCP_Tools_Admin|null */
	private $admin;

	/**
	 * @param EMCP_Tools_Admin|null $admin Admin instance (tool counts, tab visibility).
	 */
	public function __construct( ?EMCP_Tools_Admin $admin = null ) {
		$this->admin = $admin;
	}

	/**
	 * A requested range, or 14.
	 *
	 * @param mixed $raw Raw value.
	 */
	public static function range_of( $raw ): int {
		$r = (int) $raw;
		return in_array( $r, self::RANGES, true ) ? $r : 14;
	}

	/**
	 * Clients seen in the last day of the MCP log (pure).
	 *
	 * @param array $rows MCP log rows, any order.
	 * @param int   $now  Timestamp.
	 * @return array{count: int, latest: string, method: string}
	 */
	public static function clients_from_log( array $rows, int $now ): array {
		$names  = array();
		$latest = null;
		foreach ( $rows as $r ) {
			$ts     = (int) ( $r['ts'] ?? 0 );
			$client = (string) ( $r['client'] ?? '' );
			if ( '' === $client || 'success' !== ( $r['status'] ?? '' ) || $now - $ts > self::CLIENT_TTL ) {
				continue;
			}
			$names[ $client ] = true;
			if ( null === $latest || $ts > (int) $latest['ts'] ) {
				$latest = $r;
			}
		}
		if ( null === $latest ) {
			return array(
				'count'  => 0,
				'latest' => '',
				'method' => '',
			);
		}
		$methods = array(
			'oauth' => __( 'OAuth', 'emcp-tools' ),
			'app'   => __( 'Application password', 'emcp-tools' ),
			'cli'   => __( 'WP-CLI', 'emcp-tools' ),
		);
		$prefix  = (string) strtok( (string) ( $latest['credential'] ?? '' ), ':' );
		return array(
			'count'  => count( $names ),
			'latest' => (string) $latest['client'],
			'method' => (string) ( $methods[ $prefix ] ?? '' ),
		);
	}

	/**
	 * The four KPIs (pure). Without Pro usage the Templates and Prompts KPIs
	 * are replaced by Tool calls and Errors (spec 8.1).
	 *
	 * @param int        $range   Days.
	 * @param int        $changes Changes recorded.
	 * @param int        $rolled  Changes rolled back.
	 * @param int        $calls   Tool calls.
	 * @param int        $errors  Failed calls.
	 * @param array|null $usage   EMCP_Tools_Pro_Usage::local_summary(), or null.
	 */
	public static function kpis( int $range, int $changes, int $rolled, int $calls, int $errors, ?array $usage ): array {
		/* translators: %d: number of days. */
		$last = sprintf( _n( 'Last %d day', 'Last %d days', $range, 'emcp-tools' ), $range );
		$pct  = $changes ? (int) round( 100 * $rolled / $changes ) : 0;
		$kpis = array(
			array(
				'key'   => 'changes',
				'label' => __( 'Changes recorded', 'emcp-tools' ),
				'value' => $changes,
				'sub'   => $last,
			),
			array(
				'key'   => 'rolled',
				'label' => __( 'Rolled back', 'emcp-tools' ),
				'value' => $rolled,
				/* translators: %d: percentage of changes rolled back. */
				'sub'   => sprintf( __( '%d%% of changes', 'emcp-tools' ), $pct ),
			),
		);
		if ( null !== $usage ) {
			$kpis[] = array(
				'key'   => 'templates',
				'label' => __( 'Templates applied', 'emcp-tools' ),
				'value' => (int) ( $usage['templates'] ?? 0 ),
				'sub'   => __( 'All time', 'emcp-tools' ),
			);
			$kpis[] = array(
				'key'   => 'prompts',
				'label' => __( 'Prompts copied', 'emcp-tools' ),
				'value' => (int) ( $usage['prompts'] ?? 0 ),
				'sub'   => __( 'All time', 'emcp-tools' ),
			);
			return $kpis;
		}
		$kpis[] = array(
			'key'   => 'calls',
			'label' => __( 'Tool calls', 'emcp-tools' ),
			'value' => $calls,
			'sub'   => $last,
		);
		$kpis[] = array(
			'key'   => 'errors',
			'label' => __( 'Errors', 'emcp-tools' ),
			'value' => $errors,
			/* translators: %d: percentage of calls that failed. */
			'sub'   => sprintf( __( '%d%% of calls', 'emcp-tools' ), $calls ? (int) round( 100 * $errors / $calls ) : 0 ),
		);
		return $kpis;
	}

	/**
	 * Activity card: the per-day series, KPIs and most used tools.
	 *
	 * @param int      $range 7, 14 or 30.
	 * @param int|null $now   Timestamp (tests).
	 */
	public function activity( int $range, ?int $now = null ): array {
		$now     = $now ?? time();
		$range   = self::range_of( $range );
		$stats   = EMCP_Tools_Activity_Stats::range( $range, $now );
		$ledger  = EMCP_Tools_Change_Log::daily( $range, $now );
		$days    = array();
		$changes = 0;
		$rolled  = 0;
		$calls   = 0;
		$errors  = 0;
		$by_date = array_column( $stats['days'], null, 'date' );
		foreach ( $ledger as $d ) {
			$s        = $by_date[ $d['date'] ] ?? array(
				'calls'  => 0,
				'errors' => 0,
			);
			$changes += $d['kept'] + $d['rolled'];
			$rolled  += $d['rolled'];
			$calls   += (int) $s['calls'];
			$errors  += (int) $s['errors'];
			$days[]   = array(
				'date'   => $d['date'],
				'kept'   => $d['kept'],
				'rolled' => $d['rolled'],
				'calls'  => (int) $s['calls'],
				'errors' => (int) $s['errors'],
			);
		}
		$usage = self::usage();
		$most  = array();
		foreach ( array_slice( $stats['tools'], 0, 5, true ) as $tool => $count ) {
			$most[] = array(
				'tool'  => (string) $tool,
				'count' => (int) $count,
			);
		}
		return array(
			'range'    => $range,
			'days'     => $days,
			'kpis'     => self::kpis( $range, $changes, $rolled, $calls, $errors, $usage ),
			'mostUsed' => $most,
		);
	}

	/**
	 * EMCP_Tools_Pro_Usage::local_summary(), or null on a free build. The Pro
	 * loader loads that class in wp-admin only, so REST and CLI require it
	 * here (the range refetch must not switch KPIs).
	 */
	private static function usage(): ?array {
		if ( ! class_exists( 'EMCP_Tools_Pro_Usage' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$file = EMCP_Tools_Pro_Loader::path( 'includes/admin/class-pro-usage.php' );
			if ( '' !== $file && file_exists( $file ) ) {
				require_once $file;
			}
		}
		return class_exists( 'EMCP_Tools_Pro_Usage' ) ? EMCP_Tools_Pro_Usage::local_summary() : null;
	}

	/**
	 * Health strip: server, clients, tools, version.
	 *
	 * @param int|null $now Timestamp (tests).
	 */
	public function health( ?int $now = null ): array {
		$now      = $now ?? time();
		$abilities = function_exists( 'wp_register_ability' );
		$online   = (bool) get_option( 'emcp_tools_server_enabled', true ) && $abilities;
		$clients  = self::clients_from_log( class_exists( 'EMCP_Tools_MCP_Request_Log' ) ? EMCP_Tools_MCP_Request_Log::all() : array(), $now );
		$enabled  = $this->admin ? $this->admin->get_enabled_tool_count() : 0;
		$total    = $this->admin ? $this->admin->get_total_tool_count() : 0;
		$disabled = max( 0, $total - $enabled );
		$ver      = class_exists( 'EMCP_Tools_GitHub_Updater' )
			? EMCP_Tools_GitHub_Updater::current_update_status()
			: array(
				'current'          => EMCP_TOOLS_VERSION,
				'latest'           => EMCP_TOOLS_VERSION,
				'update_available' => false,
				'update_url'       => admin_url( 'plugins.php' ),
			);
		$client   = '' !== $clients['method'] ? $clients['latest'] . ' · ' . $clients['method'] : $clients['latest'];
		return array(
			'server'  => array(
				'online' => $online,
				'label'  => $online ? __( 'MCP server online', 'emcp-tools' ) : __( 'MCP server off', 'emcp-tools' ),
				'sub'    => $abilities ? __( 'Abilities API enabled', 'emcp-tools' ) : __( 'Abilities API missing', 'emcp-tools' ),
			),
			'clients' => array(
				'count' => $clients['count'],
				/* translators: %d: number of connected clients. */
				'label' => sprintf( _n( '%d client connected', '%d clients connected', $clients['count'], 'emcp-tools' ), $clients['count'] ),
				'sub'   => $clients['count'] ? $client : __( 'None in the last 24 hours', 'emcp-tools' ),
			),
			'tools'   => array(
				'enabled'  => $enabled,
				'total'    => $total,
				'disabled' => $disabled,
				/* translators: 1: enabled tools, 2: total tools. */
				'label'    => sprintf( __( '%1$d of %2$d tools', 'emcp-tools' ), $enabled, $total ),
				/* translators: %d: number of disabled tools. */
				'sub'      => sprintf( __( '%d disabled', 'emcp-tools' ), $disabled ),
			),
			'version' => array(
				'current' => (string) $ver['current'],
				'latest'  => (string) $ver['latest'],
				'update'  => (bool) $ver['update_available'],
				'url'     => (string) $ver['update_url'],
				'label'   => 'v' . $ver['current'],
				'sub'     => $ver['update_available']
					/* translators: %s: the newer version. */
					? sprintf( __( 'v%s is ready to install', 'emcp-tools' ), $ver['latest'] )
					: __( 'You\'re on the latest version', 'emcp-tools' ),
			),
		);
	}

	/** The five newest ledger rows, in History's row shape plus the client. */
	public function recent(): array {
		$out = array();
		foreach ( EMCP_Tools_Change_Log::query(
			array(
				'limit'    => 5,
				'no_audit' => true,
			)
		)['items'] as $r ) {
			$out[] = EMCP_Tools_Admin_History_Data::row( $r ) + array( 'client' => (string) ( $r['client'] ?? '' ) );
		}
		return $out;
	}

	/** "Jump to a feature" entries, gated like the sidebar. */
	public function features(): array {
		$page    = admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG );
		$premium = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		$admin   = $this->admin;
		$list    = array(
			array( 'wrench', __( 'MCP Tools', 'emcp-tools' ), __( 'Choose what your AI can call', 'emcp-tools' ), $page . '-tools', false, true ),
			array( 'plug', __( 'Connection', 'emcp-tools' ), __( 'Claude, Cursor, ChatGPT and more', 'emcp-tools' ), $page . '-connection', false, true ),
			array( 'blocks', __( 'Modules', 'emcp-tools' ), __( 'Turn big features on and off', 'emcp-tools' ), $page . '-modules', false, true ),
			array( 'history', __( 'History', 'emcp-tools' ), __( 'One-click undo ledger', 'emcp-tools' ), $page . '-history', false, true ),
			array( 'message-square', __( 'AI Chat', 'emcp-tools' ), __( 'Chat inside the editor', 'emcp-tools' ), $page . '-ai-chat', true, $admin ? $admin->ai_chat_tab_visible() : false ),
			array( 'layout-template', __( 'EMCP Themer', 'emcp-tools' ), __( 'Headers, footers, layouts', 'emcp-tools' ), admin_url( 'edit.php?post_type=emcp_theme_template' ), false, class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled() ),
			array( 'lightbulb', __( 'Prompts', 'emcp-tools' ), __( 'Ready-to-use prompts', 'emcp-tools' ), $page . '-prompts', false, $admin ? $admin->module_tab_visible( 'prompts' ) : false ),
			array( 'layout-grid', __( 'Templates', 'emcp-tools' ), __( 'Premium page templates', 'emcp-tools' ), $page . '-templates', true, $admin ? $admin->module_tab_visible( 'templates' ) : false ),
			array( 'sparkles', __( 'Skills', 'emcp-tools' ), __( 'Claude Code skills', 'emcp-tools' ), $page . '-skills', true, true ),
			array( 'code', __( 'PHP Sandbox', 'emcp-tools' ), __( 'Approve AI snippets', 'emcp-tools' ), $page . '-widgets', false, true ),
		);
		$out = array();
		foreach ( $list as $f ) {
			if ( ! $f[5] ) {
				continue;
			}
			$out[] = array(
				'icon'  => $f[0],
				'title' => $f[1],
				'desc'  => $f[2],
				'url'   => $f[3],
				'pro'   => $f[4] && ! $premium,
			);
		}
		return $out;
	}

	/** Three featured video guides. */
	public static function videos(): array {
		$list = array(
			array( 'Build a Full WordPress Site Without Touching Elementor', 'WP Academy', 'KkOioXKT_Eo' ),
			array( 'Create Elementor Landing Pages FAST with Claude and MCP Server', 'WP Academy', 'tXCpGa-hqxk' ),
			array( 'How to Use Elementor MCP with Open Models (DeepSeek, Kimi, MiniMax)', 'WP Academy', 'wAEJORy5eek' ),
		);
		$out  = array();
		foreach ( $list as $v ) {
			$out[] = array(
				'title'   => $v[0],
				'channel' => $v[1],
				'url'     => 'https://www.youtube.com/watch?v=' . rawurlencode( $v[2] ),
				'thumb'   => 'https://i.ytimg.com/vi/' . rawurlencode( $v[2] ) . '/hqdefault.jpg',
			);
		}
		return $out;
	}

	/** Help & resources links. */
	public static function help(): array {
		return array(
			array(
				'icon'  => 'scroll-text',
				'title' => __( 'Documentation', 'emcp-tools' ),
				'desc'  => __( 'Guides and reference for every feature', 'emcp-tools' ),
				'url'   => 'https://emcptools.com/docs',
			),
			array(
				'icon'  => 'life-buoy',
				'title' => __( 'Ticket support', 'emcp-tools' ),
				'desc'  => __( 'Stuck? Open a ticket with our team', 'emcp-tools' ),
				'url'   => 'https://support.msrbuilds.com/',
			),
			array(
				'icon'  => 'message-square',
				'title' => __( 'Community', 'emcp-tools' ),
				'desc'  => __( 'Share builds, get tips', 'emcp-tools' ),
				'url'   => 'https://www.facebook.com/groups/emcptools',
			),
			array(
				'icon'  => 'gift',
				'title' => __( 'Changelog', 'emcp-tools' ),
				/* translators: %s: plugin version. */
				'desc'  => sprintf( __( 'What\'s new in %s', 'emcp-tools' ), EMCP_TOOLS_VERSION ),
				'url'   => admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG . '-changelog' ),
			),
		);
	}

	/** Items for "Needs your attention" (spec 9.7). */
	public static function attention(): array {
		return class_exists( 'EMCP_Tools_Attention' ) ? EMCP_Tools_Attention::items( get_current_user_id() ) : array();
	}

	/**
	 * The boot payload.
	 *
	 * @param int|null $now Timestamp (tests).
	 */
	public function payload( ?int $now = null ): array {
		$premium   = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		$page      = admin_url( 'admin.php?page=' . EMCP_Tools_Admin::PAGE_SLUG );
		$connected = class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected();
		return array(
			'header'     => array(
				'premium'    => $premium,
				'aiChatUrl'  => ( $this->admin && $this->admin->ai_chat_tab_visible() ) ? $page . '-ai-chat' : '',
				'connectUrl' => $page . '-connection',
				'site'       => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			),
			'health'     => $this->health( $now ),
			'activity'   => $this->activity( 14, $now ),
			'recent'     => $this->recent(),
			'attention'  => self::attention(),
			'features'   => $this->features(),
			'videos'     => self::videos(),
			'tutorials'  => self::TUTORIALS,
			'help'       => self::help(),
			'cloud'      => array(
				'connected' => $connected,
				'url'       => $connected ? EMCP_Tools_Cloud::base_url() . '/dashboard' : $page . '-connection&section=cloud',
			),
			'historyUrl' => $page . '-history',
			'logUrl'     => $page . '-mcp-log',
		);
	}
}
