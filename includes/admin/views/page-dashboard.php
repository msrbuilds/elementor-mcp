<?php
/**
 * Dashboard tab — the landing screen for EMCP Tools.
 *
 * Shows the headline stat cards (large format), a sneak-peek grid of every
 * feature area that doubles as fast navigation, a row of featured video guides,
 * and a help & resources panel. Included from EMCP_Tools_Admin::render_page(),
 * so `$this` is the admin instance.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 *
 * @var EMCP_Tools_Admin $this
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_page    = EMCP_Tools_Admin::PAGE_SLUG;
$emcp_is_free = ! function_exists( 'emcp_tools_fs' ) || ! emcp_tools_fs()->can_use_premium_code();

/**
 * Inline SVGs for the headline stat cards, keyed by the stat `key` returned by
 * EMCP_Tools_Admin::get_dashboard_stats(). Kept here (not in the class) so the
 * data method stays markup-free.
 */
$emcp_stat_svgs = array(
	'tools'      => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
	'site'       => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>',
	'active'     => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
	'pro'        => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>',
	'modules'    => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 3.5a1.5 1.5 0 013 0V4a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-.5a1.5 1.5 0 000 3h.5a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-.5a1.5 1.5 0 00-3 0v.5a1 1 0 01-1 1H6a1 1 0 01-1-1v-3a1 1 0 00-1-1h-.5a1.5 1.5 0 010-3H4a1 1 0 001-1V6a1 1 0 011-1h3a1 1 0 001-1v-.5z"/></svg>',
	'prompts'    => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>',
	'brand-kits' => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 5a2 2 0 012-2h3a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm6.5 9.5L12 6l3.8 1.5a1 1 0 01.56 1.3l-3 7.5a2 2 0 01-2.6 1.1l-2.26-.9zM11 4a2 2 0 114 0 2 2 0 01-4 0z"/></svg>',
	'templates'  => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 5a1 1 0 011-1h6a1 1 0 011 1v7a1 1 0 01-1 1H4a1 1 0 01-1-1V9zm10 0a1 1 0 011-1h2a1 1 0 011 1v7a1 1 0 01-1 1h-2a1 1 0 01-1-1V9z"/></svg>',
);

/**
 * Feature sneak-peek cards. `href` is the destination; `pro` badges a
 * premium-tier area; `show` gates visibility (module-backed cards drop when
 * their module is off, matching the tab nav).
 */
$emcp_features = array(
	array(
		'icon'  => 'dashicons-admin-tools',
		'title' => __( 'MCP Tools', 'emcp-tools' ),
		'desc'  => __( 'Manage the tools your AI client can call across your selected builder, WordPress core, and Gutenberg.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-tools' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-admin-links',
		'title' => __( 'Connection', 'emcp-tools' ),
		'desc'  => __( 'Connect Claude, Cursor, the ChatGPT App and more, copy-paste configs, app passwords, and a one-click bundle.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-connection' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-screenoptions',
		'title' => __( 'Modules', 'emcp-tools' ),
		'desc'  => __( 'Turn big features on and off: AI Chat, Themer, Image Optimization, Prompts, Brand Kits and more.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-modules' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-undo',
		'title' => __( 'History', 'emcp-tools' ),
		'desc'  => __( 'Review every change your AI made and roll any of them back, a unified change ledger with one-click undo.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-history' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-format-chat',
		'title' => __( 'AI Chat', 'emcp-tools' ),
		'desc'  => __( 'Edit pages by chatting with AI right inside the Elementor and Gutenberg editors.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-ai-chat' ),
		'pro'   => true,
		'show'  => $this->ai_chat_tab_visible(),
	),
	array(
		'icon'  => 'dashicons-layout',
		'title' => __( 'EMCP Themer', 'emcp-tools' ),
		'desc'  => __( 'Build headers, footers, and dynamic layouts with any page builder, assigned by display conditions.', 'emcp-tools' ),
		'href'  => admin_url( 'edit.php?post_type=emcp_theme_template' ),
		'show'  => class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled(),
	),
	array(
		'icon'  => 'dashicons-lightbulb',
		'title' => __( 'Prompts', 'emcp-tools' ),
		'desc'  => __( 'A library of ready-to-use prompts for building pages, sections, and full sites with your AI client.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-prompts' ),
		'show'  => $this->module_tab_visible( 'prompts' ),
	),
	array(
		'icon'  => 'dashicons-art',
		'title' => __( 'Brand Kits', 'emcp-tools' ),
		'desc'  => __( 'Apply curated color palettes and typography to your site\'s global styles in one click.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-brand-kits' ),
		'show'  => $this->module_tab_visible( 'brand-kits' ),
	),
	array(
		'icon'  => 'dashicons-layout',
		'title' => __( 'Templates', 'emcp-tools' ),
		'desc'  => __( 'Import professionally designed Elementor templates straight into your pages.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-templates' ),
		'pro'   => true,
		'show'  => $this->module_tab_visible( 'templates' ),
	),
	array(
		'icon'  => 'dashicons-superhero',
		'title' => __( 'Skills', 'emcp-tools' ),
		'desc'  => __( 'Install Claude Code skills that teach your AI how to build with this plugin like an expert.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-skills' ),
		'pro'   => true,
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-editor-code',
		'title' => __( 'PHP Sandbox', 'emcp-tools' ),
		'desc'  => __( 'Review and activate AI-authored PHP snippets behind a human approval gate, nothing runs unattended.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-widgets' ),
		'show'  => true,
	),
);

/**
 * Featured video guides. Real YouTube tutorials — `id` is the video ID (used
 * for the thumbnail + watch link), `channel` is the creator. To feature a
 * different video, swap `id`/`title`/`channel` and the `watch?v=` URL.
 */
$emcp_videos = array(
	array(
		'title'   => 'Build a Full WordPress Site Without Touching Elementor',
		'channel' => 'WP Academy',
		'id'      => 'KkOioXKT_Eo',
	),
	array(
		'title'   => 'Create Elementor Landing Pages FAST with Claude and MCP Server',
		'channel' => 'WP Academy',
		'id'      => 'tXCpGa-hqxk',
	),
	array(
		'title'   => 'How to Use Elementor MCP with Open Models (DeepSeek, Kimi, MiniMax)',
		'channel' => 'WP Academy',
		'id'      => 'wAEJORy5eek',
	),
	array(
		'title'   => 'How I Use Elementor MCP + Claude Code to Create Custom Websites',
		'channel' => 'WPDev',
		'id'      => 'tCRt5m4jsY8',
	),
	array(
		'title'   => 'Create Elementor Websites with AI Agents | Urdu & Hindi Tutorial',
		'channel' => 'WP Academy',
		'id'      => 'B0K-9I4v5zc',
	),
);
?>

<div class="emcp-dash">

	<!-- Headline stats -->
	<section class="emcp-dash-stats" aria-label="<?php esc_attr_e( 'At a glance', 'emcp-tools' ); ?>">
		<?php foreach ( $this->get_dashboard_stats() as $emcp_stat ) : ?>
			<div class="emcp-dash-stat">
				<span class="emcp-dash-stat-icon emcp-dash-stat-icon--<?php echo esc_attr( $emcp_stat['key'] ); ?>">
					<?php echo isset( $emcp_stat_svgs[ $emcp_stat['key'] ] ) ? $emcp_stat_svgs[ $emcp_stat['key'] ] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted inline SVG markup. ?>
				</span>
				<span class="emcp-dash-stat-body">
					<span class="emcp-dash-stat-value"><?php echo esc_html( number_format_i18n( $emcp_stat['value'] ) ); ?></span>
					<span class="emcp-dash-stat-label"><?php echo esc_html( $emcp_stat['label'] ); ?></span>
				</span>
			</div>
		<?php endforeach; ?>
	</section>
	<p class="emcp-dash-stats-note"><?php esc_html_e( 'Catalog Tools includes supported integrations. Shown in Tools follows the selected builder; Enabled Tools follows your switches. EMCP Pro Tools is the premium portion of the catalog.', 'emcp-tools' ); ?></p>

	<?php
	// Activity pulse: usage KPIs (Pro), change-ledger overview, most-used actions,
	// and the Sandbox item count. History + Most-used + Sandbox are free features,
	// so the whole section renders on both tiers.
	$emcp_has_usage   = class_exists( 'EMCP_Tools_Pro_Usage' );
	$emcp_usage_local = $emcp_has_usage
		? EMCP_Tools_Pro_Usage::local_summary()
		: array( 'templates' => 0, 'prompts' => 0 );

	// Change-ledger overview + most-used actions.
	$emcp_log       = class_exists( 'EMCP_Tools_Change_Log' ) ? EMCP_Tools_Change_Log::all() : array();
	$emcp_changes   = count( $emcp_log );
	$emcp_rolled    = 0;
	$emcp_last_ts   = 0;
	$emcp_action_ct = array();
	foreach ( $emcp_log as $emcp_e ) {
		if ( ! empty( $emcp_e['rolled_back'] ) ) {
			++$emcp_rolled;
		}
		if ( isset( $emcp_e['ts'] ) && (int) $emcp_e['ts'] > $emcp_last_ts ) {
			$emcp_last_ts = (int) $emcp_e['ts'];
		}
		$emcp_dom = isset( $emcp_e['domain'] ) ? (string) $emcp_e['domain'] : '';
		$emcp_act = isset( $emcp_e['action'] ) ? (string) $emcp_e['action'] : '';
		if ( '' === $emcp_dom && '' === $emcp_act ) {
			continue;
		}
		$emcp_key = $emcp_dom . '|' . $emcp_act;
		if ( ! isset( $emcp_action_ct[ $emcp_key ] ) ) {
			$emcp_action_ct[ $emcp_key ] = 0;
		}
		++$emcp_action_ct[ $emcp_key ];
	}
	arsort( $emcp_action_ct );
	$emcp_top_actions = array_slice( $emcp_action_ct, 0, 4, true );

	// Sandbox items across all three pillars (blocks + widgets + snippets):
	// active = publish, drafts = draft. Blocks/widgets are Pro CPTs; on a free
	// site they simply do not exist and wp_count_posts() returns zeros.
	$emcp_snip_active = 0;
	$emcp_snip_draft  = 0;
	if ( function_exists( 'wp_count_posts' ) ) {
		foreach ( array( 'emcp_block', 'emcp_widget', 'emcp_php_snippet' ) as $emcp_sb_cpt ) {
			$emcp_ct = wp_count_posts( $emcp_sb_cpt );
			$emcp_snip_active += ( $emcp_ct && isset( $emcp_ct->publish ) ) ? (int) $emcp_ct->publish : 0;
			$emcp_snip_draft  += ( $emcp_ct && isset( $emcp_ct->draft ) ) ? (int) $emcp_ct->draft : 0;
		}
	}
	$emcp_snip_total = $emcp_snip_active + $emcp_snip_draft;

	$emcp_url_prompts = admin_url( 'admin.php?page=' . $emcp_page . '-prompts' );
	$emcp_url_history = admin_url( 'admin.php?page=' . $emcp_page . '-history' );
	$emcp_url_sandbox = admin_url( 'admin.php?page=' . $emcp_page . '-widgets' );
	?>
	<section class="emcp-dash-section" aria-labelledby="emcp-dash-usage-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-usage-h" class="emcp-dash-section-title"><?php esc_html_e( 'Your usage', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'A quick pulse on what your AI has done on this site.', 'emcp-tools' ); ?></p>
		</div>
		<div class="emcp-dash-usage-grid">

			<!-- Usage KPIs -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_prompts ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--usage"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title">
						<?php esc_html_e( 'Usage', 'emcp-tools' ); ?>
						<?php if ( ! $emcp_has_usage ) : ?>
							<span class="emcp-dash-badge emcp-dash-badge--pro"><?php esc_html_e( 'Pro', 'emcp-tools' ); ?></span>
						<?php endif; ?>
					</span>
				</span>
				<span class="emcp-dash-ucard-kpis">
					<span class="emcp-dash-ucard-kpi">
						<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_usage_local['templates'] ) ); ?></span>
						<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'templates applied', 'emcp-tools' ); ?></span>
					</span>
					<span class="emcp-dash-ucard-kpi">
						<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_usage_local['prompts'] ) ); ?></span>
						<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'prompts copied', 'emcp-tools' ); ?></span>
					</span>
				</span>
			</a>

			<!-- History overview -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_history ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--history"><span class="dashicons dashicons-undo" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title"><?php esc_html_e( 'History', 'emcp-tools' ); ?></span>
				</span>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_changes ) ); ?></span>
				<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'changes recorded', 'emcp-tools' ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					if ( $emcp_last_ts > 0 ) {
						printf(
							/* translators: 1: rolled-back count, 2: human-readable time since last change */
							esc_html__( '%1$s rolled back · last %2$s ago', 'emcp-tools' ),
							esc_html( number_format_i18n( $emcp_rolled ) ),
							esc_html( human_time_diff( $emcp_last_ts, time() ) )
						);
					} else {
						esc_html_e( 'No changes recorded yet', 'emcp-tools' );
					}
					?>
				</span>
			</a>

			<!-- Most used actions -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_history ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--tools"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title"><?php esc_html_e( 'Most used', 'emcp-tools' ); ?></span>
				</span>
				<?php if ( ! empty( $emcp_top_actions ) ) : ?>
					<ul class="emcp-dash-ucard-list">
						<?php
						foreach ( $emcp_top_actions as $emcp_key => $emcp_cnt ) :
							$emcp_parts = explode( '|', $emcp_key, 2 );
							$emcp_dom   = $emcp_parts[0];
							$emcp_act   = isset( $emcp_parts[1] ) ? $emcp_parts[1] : '';
							?>
							<li>
								<span class="emcp-dash-ucard-act"><?php if ( '' !== $emcp_dom ) : ?><span class="emcp-dash-ucard-dom"><?php echo esc_html( $emcp_dom ); ?> </span><?php endif; ?><?php echo esc_html( $emcp_act ); ?></span>
								<span class="emcp-dash-ucard-cnt"><?php echo esc_html( number_format_i18n( $emcp_cnt ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<span class="emcp-dash-ucard-empty"><?php esc_html_e( 'No activity yet', 'emcp-tools' ); ?></span>
				<?php endif; ?>
			</a>

			<!-- Sandbox items -->
			<a class="emcp-dash-ucard" href="<?php echo esc_url( $emcp_url_sandbox ); ?>">
				<span class="emcp-dash-ucard-head">
					<span class="emcp-dash-ucard-ico emcp-dash-ucard-ico--sandbox"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span></span>
					<span class="emcp-dash-ucard-title"><?php esc_html_e( 'Sandbox', 'emcp-tools' ); ?></span>
				</span>
				<span class="emcp-dash-ucard-num"><?php echo esc_html( number_format_i18n( $emcp_snip_total ) ); ?></span>
				<span class="emcp-dash-ucard-sub"><?php esc_html_e( 'Blocks, widgets & snippets', 'emcp-tools' ); ?></span>
				<span class="emcp-dash-ucard-foot">
					<?php
					printf(
						/* translators: 1: active sandbox-item count, 2: draft sandbox-item count */
						esc_html__( '%1$s active · %2$s drafts', 'emcp-tools' ),
						esc_html( number_format_i18n( $emcp_snip_active ) ),
						esc_html( number_format_i18n( $emcp_snip_draft ) )
					);
					?>
				</span>
			</a>

		</div>
	</section>

	<!-- Feature sneak peek -->
	<!-- Explore your toolkit + EMCP Cloud banner, side by side (75/25) -->
	<div class="emcp-dash-row emcp-dash-row--toolkit">
	<section class="emcp-dash-section emcp-dash-section--toolkit" aria-labelledby="emcp-dash-features-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-features-h" class="emcp-dash-section-title"><?php esc_html_e( 'Explore your toolkit', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Everything this plugin can do, jump straight in.', 'emcp-tools' ); ?></p>
		</div>
		<div class="emcp-dash-grid">
			<?php
			foreach ( $emcp_features as $emcp_feature ) :
				if ( empty( $emcp_feature['show'] ) ) {
					continue;
				}
				$emcp_is_pro_feature = ! empty( $emcp_feature['pro'] );
				?>
				<a class="emcp-dash-card" href="<?php echo esc_url( $emcp_feature['href'] ); ?>">
					<span class="emcp-dash-card-icon"><span class="dashicons <?php echo esc_attr( $emcp_feature['icon'] ); ?>" aria-hidden="true"></span></span>
					<span class="emcp-dash-card-body">
						<span class="emcp-dash-card-title">
							<?php echo esc_html( $emcp_feature['title'] ); ?>
							<?php if ( $emcp_is_pro_feature && $emcp_is_free ) : ?>
								<span class="emcp-dash-badge emcp-dash-badge--pro"><?php esc_html_e( 'Pro', 'emcp-tools' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="emcp-dash-card-desc"><?php echo esc_html( $emcp_feature['desc'] ); ?></span>
					</span>
					<span class="emcp-dash-card-arrow dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

		<aside class="emcp-dash-side">
			<!--
			EMCP Cloud. Built in markup rather than dropped in as artwork, so the
			copy is translatable, the type stays sharp at any column width, and
			the whole card reflows instead of shrinking to five-pixel text. The
			cloud and its three nodes are inline SVG for the same reason.
			-->
			<div class="emcp-dash-promo emcp-dash-promo--cloud">
				<?php
				// Cloud and stacked layers only. The full artwork also drops three
				// connected nodes below this, which is right at banner size and wrong
				// here: in a 380px column they landed on the description and the first
				// benefit row. Cropped to the part that still reads at 116px.
				?>
				<svg class="emcp-dash-promo-deco emcp-dash-promo-deco--cloud" viewBox="56 40 106 80" fill="none" aria-hidden="true" focusable="false">
					<path d="M62 84a30 30 0 0 1 29-30 34 34 0 0 1 64 8 26 26 0 0 1-8 51H88a26 26 0 0 1-26-29Z" stroke="currentColor" stroke-width="2" opacity=".5" />
					<path d="M110 78l24 13-24 13-24-13 24-13Z" fill="currentColor" opacity=".45" />
					<path d="M86 100l24 13 24-13" stroke="currentColor" stroke-width="2" opacity=".3" stroke-linecap="round" stroke-linejoin="round" />
				</svg>

				<span class="emcp-dash-promo-badge emcp-dash-promo-badge--icon">
					<span class="dashicons dashicons-cloud" aria-hidden="true"></span><?php esc_html_e( 'EMCP Cloud', 'emcp-tools' ); ?>
				</span>

				<h3 class="emcp-dash-promo-title emcp-dash-promo-title--stacked">
					<?php esc_html_e( 'Your artifacts,', 'emcp-tools' ); ?>
					<span><?php esc_html_e( 'everywhere', 'emcp-tools' ); ?></span>
				</h3>

				<p class="emcp-dash-promo-desc"><?php esc_html_e( 'Back up your blocks, widgets and snippets, sync them across sites, and publish to the marketplace.', 'emcp-tools' ); ?></p>

				<ul class="emcp-dash-promo-feats">
					<li>
						<span class="emcp-dash-promo-feat-icon"><span class="dashicons dashicons-shield" aria-hidden="true"></span></span>
						<span class="emcp-dash-promo-feat-copy">
							<strong><?php esc_html_e( 'Back up &amp; restore anywhere', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'Secure, reliable backups you can count on.', 'emcp-tools' ); ?></span>
						</span>
					</li>
					<li>
						<span class="emcp-dash-promo-feat-icon"><span class="dashicons dashicons-update-alt" aria-hidden="true"></span></span>
						<span class="emcp-dash-promo-feat-copy">
							<strong><?php esc_html_e( 'Sync across all your sites', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'Keep everything in perfect sync.', 'emcp-tools' ); ?></span>
						</span>
					</li>
					<li>
						<span class="emcp-dash-promo-feat-icon"><span class="dashicons dashicons-cart" aria-hidden="true"></span></span>
						<span class="emcp-dash-promo-feat-copy">
							<strong><?php esc_html_e( 'Publish &amp; sell on the marketplace', 'emcp-tools' ); ?></strong>
							<span><?php esc_html_e( 'Share your creations and grow.', 'emcp-tools' ); ?></span>
						</span>
					</li>
				</ul>

				<a class="emcp-dash-promo-cta emcp-dash-promo-cta--block" href="<?php echo esc_url( admin_url( 'admin.php?page=emcp-tools-connection' ) ); ?>">
					<?php esc_html_e( 'Explore EMCP Cloud', 'emcp-tools' ); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</a>
			</div>

			<!--
			EMCP Pro. Dark, to sit with the Cloud banner above it rather than
			against it. The decoration is inline SVG rather than an image so it
			stays crisp at any column width and the copy stays translatable.
			-->
			<div class="emcp-dash-promo emcp-dash-promo--pro">
				<svg class="emcp-dash-promo-deco" viewBox="0 0 220 160" fill="none" aria-hidden="true" focusable="false">
					<circle cx="176" cy="30" r="58" stroke="currentColor" stroke-width="1" opacity=".35" />
					<circle cx="176" cy="30" r="40" stroke="currentColor" stroke-width="1" opacity=".25" />
					<circle cx="176" cy="30" r="22" stroke="currentColor" stroke-width="1" opacity=".18" />
					<g opacity=".3">
						<circle cx="152" cy="96" r="1.5" fill="currentColor" />
						<circle cx="172" cy="96" r="1.5" fill="currentColor" />
						<circle cx="192" cy="96" r="1.5" fill="currentColor" />
						<circle cx="152" cy="112" r="1.5" fill="currentColor" />
						<circle cx="172" cy="112" r="1.5" fill="currentColor" />
						<circle cx="192" cy="112" r="1.5" fill="currentColor" />
					</g>
				</svg>
				<span class="emcp-dash-promo-badge"><?php esc_html_e( 'EMCP Pro', 'emcp-tools' ); ?></span>
				<?php if ( $emcp_is_free ) : ?>
					<span class="emcp-dash-promo-icon dashicons dashicons-star-filled" aria-hidden="true"></span>
					<h3 class="emcp-dash-promo-title"><?php esc_html_e( 'Unlock the full toolkit', 'emcp-tools' ); ?></h3>
					<p class="emcp-dash-promo-desc"><?php esc_html_e( 'Widget & block builder, AI Chat, SEO & accessibility, Themer, Templates, and more.', 'emcp-tools' ); ?></p>
					<a class="emcp-dash-promo-cta" href="<?php echo esc_url( function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing' ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
					</a>
				<?php else : ?>
					<span class="emcp-dash-promo-icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<h3 class="emcp-dash-promo-title"><?php esc_html_e( 'You\'re on the Pro plan', 'emcp-tools' ); ?></h3>
					<p class="emcp-dash-promo-desc"><?php esc_html_e( 'Thanks for going Pro — every premium feature is unlocked on this site.', 'emcp-tools' ); ?></p>
					<span class="emcp-dash-promo-note"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Pro plan active', 'emcp-tools' ); ?></span>
				<?php endif; ?>
			</div>
		</aside>
	</div><!-- .emcp-dash-row--toolkit -->
	<!-- Video guides + help, side by side (70/30) -->
	<div class="emcp-dash-row">

	<!-- Featured video guides -->
	<section class="emcp-dash-section emcp-dash-section--videos" aria-labelledby="emcp-dash-videos-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-videos-h" class="emcp-dash-section-title"><?php esc_html_e( 'Featured video guides', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Watch and learn, from first connection to full-page builds.', 'emcp-tools' ); ?></p>
		</div>
		<div class="emcp-dash-videos">
			<?php
			foreach ( $emcp_videos as $emcp_video ) :
				$emcp_video_url = 'https://www.youtube.com/watch?v=' . rawurlencode( $emcp_video['id'] );
				$emcp_video_img = 'https://i.ytimg.com/vi/' . rawurlencode( $emcp_video['id'] ) . '/hqdefault.jpg';
				?>
				<a class="emcp-dash-video" href="<?php echo esc_url( $emcp_video_url ); ?>" target="_blank" rel="noopener noreferrer">
					<span class="emcp-dash-video-thumb">
						<img class="emcp-dash-video-img" src="<?php echo esc_url( $emcp_video_img ); ?>" alt="" loading="lazy" />
						<span class="emcp-dash-video-play" aria-hidden="true"><span class="dashicons dashicons-controls-play"></span></span>
					</span>
					<span class="emcp-dash-video-meta">
						<span class="emcp-dash-video-title"><?php echo esc_html( $emcp_video['title'] ); ?></span>
						<span class="emcp-dash-video-channel"><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span><?php echo esc_html( $emcp_video['channel'] ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
			<a class="emcp-dash-video emcp-dash-video--more" href="https://emcptools.com/tutorials" target="_blank" rel="noopener noreferrer">
				<span class="emcp-dash-more-inner">
					<span class="emcp-dash-more-icon"><span class="dashicons dashicons-playlist-video" aria-hidden="true"></span></span>
					<span class="emcp-dash-more-title"><?php esc_html_e( 'Watch More', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-more-sub"><?php esc_html_e( 'See all tutorials', 'emcp-tools' ); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>
				</span>
			</a>
		</div>
	</section>

	<!-- Help & resources -->
	<section class="emcp-dash-section emcp-dash-section--help" aria-labelledby="emcp-dash-help-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-help-h" class="emcp-dash-section-title"><?php esc_html_e( 'Help &amp; resources', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Quick links to the free and premium support channels.', 'emcp-tools' ); ?></p>
		</div>
		<?php
		$emcp_ver = class_exists( 'EMCP_Tools_GitHub_Updater' )
			? EMCP_Tools_GitHub_Updater::current_update_status()
			: array( 'current' => EMCP_TOOLS_VERSION, 'latest' => EMCP_TOOLS_VERSION, 'update_available' => false, 'update_url' => admin_url( 'plugins.php' ) );
		?>
		<?php if ( ! empty( $emcp_ver['update_available'] ) ) : ?>
			<a class="emcp-dash-version emcp-dash-version--update" href="<?php echo esc_url( $emcp_ver['update_url'] ); ?>">
				<span class="emcp-dash-version-dot" aria-hidden="true"></span>
				<span class="emcp-dash-version-text">
					<span class="emcp-dash-version-title"><?php esc_html_e( 'Update available', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-version-sub">
						<?php
						printf(
							/* translators: 1: installed version, 2: available version */
							esc_html__( 'You have v%1$s, v%2$s is ready to install.', 'emcp-tools' ),
							esc_html( $emcp_ver['current'] ),
							esc_html( $emcp_ver['latest'] )
						);
						?>
					</span>
				</span>
				<span class="emcp-dash-version-cta"><?php esc_html_e( 'Update now', 'emcp-tools' ); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>
			</a>
		<?php else : ?>
			<div class="emcp-dash-version emcp-dash-version--ok">
				<span class="emcp-dash-version-dot" aria-hidden="true"></span>
				<span class="emcp-dash-version-text">
					<span class="emcp-dash-version-title"><?php esc_html_e( 'You\'re on the latest version', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-version-sub">
						<?php
						printf(
							/* translators: %s: installed version number */
							esc_html__( 'EMCP Tools v%s', 'emcp-tools' ),
							esc_html( $emcp_ver['current'] )
						);
						?>
					</span>
				</span>
			</div>
		<?php endif; ?>
		<div class="emcp-dash-help">
			<a class="emcp-dash-help-link" href="https://emcptools.com/docs" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-book" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Documentation', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'Guides and reference for every feature.', 'emcp-tools' ); ?></span>
				</span>
			</a>
			<a class="emcp-dash-help-link" href="https://support.msrbuilds.com/" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-sos" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Ticket Support', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'Stuck? Open a ticket with our team.', 'emcp-tools' ); ?></span>
				</span>
			</a>
			<a class="emcp-dash-help-link" href="https://www.facebook.com/groups/emcptools" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Community', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'Share builds and get tips from other users.', 'emcp-tools' ); ?></span>
				</span>
			</a>
			<a class="emcp-dash-help-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $emcp_page . '-changelog' ) ); ?>">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<span>
					<span class="emcp-dash-help-title"><?php esc_html_e( 'Changelog', 'emcp-tools' ); ?></span>
					<span class="emcp-dash-help-desc"><?php esc_html_e( 'See what\'s new in the latest releases.', 'emcp-tools' ); ?></span>
				</span>
			</a>
		</div>
	</section>

	</div><!-- .emcp-dash-row -->

</div>
