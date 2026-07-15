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
	'active'     => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
	'pro'        => '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>',
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
		'desc'  => __( 'Toggle the ~140 abilities your AI client can call — Elementor, WordPress core, and Gutenberg.', 'emcp-tools' ),
		'href'  => admin_url( 'admin.php?page=' . $emcp_page . '-tools' ),
		'show'  => true,
	),
	array(
		'icon'  => 'dashicons-admin-links',
		'title' => __( 'Connection', 'emcp-tools' ),
		'desc'  => __( 'Connect Claude, Cursor, the ChatGPT App and more — copy-paste configs, app passwords, and a one-click bundle.', 'emcp-tools' ),
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
		'desc'  => __( 'Review every change your AI made and roll any of them back — a unified change ledger with one-click undo.', 'emcp-tools' ),
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
		'desc'  => __( 'Build headers, footers, and dynamic layouts with any page builder — assigned by display conditions.', 'emcp-tools' ),
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
		'desc'  => __( 'Review and activate AI-authored PHP snippets behind a human approval gate — nothing runs unattended.', 'emcp-tools' ),
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

	<?php
	// Usage widget (Pro): this site's activity + the globally-popular templates.
	if ( class_exists( 'EMCP_Tools_Pro_Usage' ) ) :
		$emcp_usage_local = EMCP_Tools_Pro_Usage::local_summary();
		// Cached-only — never block the dashboard on a network fetch. The
		// templates page warms this transient when a Pro user opens it.
		$emcp_usage_counts = EMCP_Tools_Pro_Usage::cached_counts();
		// Top 5 templates by global usage.
		$emcp_pop = array();
		foreach ( $emcp_usage_counts as $emcp_k => $emcp_n ) {
			if ( 0 === strpos( $emcp_k, 'template:' ) ) {
				$emcp_pop[ substr( $emcp_k, 9 ) ] = (int) $emcp_n;
			}
		}
		arsort( $emcp_pop );
		$emcp_pop = array_slice( $emcp_pop, 0, 5, true );
		?>
		<section class="emcp-dash-section" aria-labelledby="emcp-dash-usage-h">
			<div class="emcp-dash-section-head">
				<h2 id="emcp-dash-usage-h" class="emcp-dash-section-title"><?php esc_html_e( 'Your usage', 'emcp-tools' ); ?></h2>
				<p class="emcp-dash-section-sub"><?php esc_html_e( 'What you have applied on this site, plus what is popular across everyone.', 'emcp-tools' ); ?></p>
			</div>
			<div class="emcp-dash-usage">
				<div class="emcp-dash-usage-kpis">
					<div class="emcp-dash-usage-kpi">
						<span class="emcp-dash-usage-num"><?php echo esc_html( number_format_i18n( $emcp_usage_local['templates'] ) ); ?></span>
						<span class="emcp-dash-usage-lbl"><?php esc_html_e( 'templates applied', 'emcp-tools' ); ?></span>
					</div>
					<div class="emcp-dash-usage-kpi">
						<span class="emcp-dash-usage-num"><?php echo esc_html( number_format_i18n( $emcp_usage_local['prompts'] ) ); ?></span>
						<span class="emcp-dash-usage-lbl"><?php esc_html_e( 'prompts copied', 'emcp-tools' ); ?></span>
					</div>
				</div>
				<?php if ( ! empty( $emcp_pop ) ) : ?>
					<div class="emcp-dash-usage-pop">
						<div class="emcp-dash-usage-pop-h"><?php esc_html_e( 'Popular templates', 'emcp-tools' ); ?></div>
						<ul class="emcp-dash-usage-list">
							<?php foreach ( $emcp_pop as $emcp_slug => $emcp_used ) : ?>
								<li>
									<span class="emcp-dash-usage-slug"><?php echo esc_html( $emcp_slug ); ?></span>
									<span class="emcp-dash-usage-count"><?php echo esc_html( sprintf( /* translators: %s: number of uses */ _n( '%s use', '%s uses', $emcp_used, 'emcp-tools' ), number_format_i18n( $emcp_used ) ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Feature sneak peek -->
	<section class="emcp-dash-section" aria-labelledby="emcp-dash-features-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-features-h" class="emcp-dash-section-title"><?php esc_html_e( 'Explore your toolkit', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Everything this plugin can do — jump straight in.', 'emcp-tools' ); ?></p>
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

	<!-- Video guides + help, side by side (70/30) -->
	<div class="emcp-dash-row">

	<!-- Featured video guides -->
	<section class="emcp-dash-section emcp-dash-section--videos" aria-labelledby="emcp-dash-videos-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-videos-h" class="emcp-dash-section-title"><?php esc_html_e( 'Featured video guides', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Watch and learn — from first connection to full-page builds.', 'emcp-tools' ); ?></p>
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
							esc_html__( 'You have v%1$s — v%2$s is ready to install.', 'emcp-tools' ),
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
