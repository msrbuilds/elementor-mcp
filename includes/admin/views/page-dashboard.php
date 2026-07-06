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
 * @since   3.2.0
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
		'desc'  => __( 'Connect Claude, Cursor, Codex and more — copy-paste configs, app passwords, and a one-click bundle.', 'emcp-tools' ),
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
 * Featured video guides. Curated placeholders — swap the `url` values for real
 * tutorial links as they publish. `duration` is display-only.
 */
$emcp_videos = array(
	array(
		'title'    => __( 'Getting started: connect your first AI client', 'emcp-tools' ),
		'duration' => '4:12',
		'url'      => 'https://emcptools.com/tutorials',
	),
	array(
		'title'    => __( 'Build a landing page with AI, start to finish', 'emcp-tools' ),
		'duration' => '8:47',
		'url'      => 'https://emcptools.com/tutorials',
	),
	array(
		'title'    => __( 'Editing pages with the in-editor AI Chat', 'emcp-tools' ),
		'duration' => '5:30',
		'url'      => 'https://emcptools.com/tutorials',
	),
	array(
		'title'    => __( 'Theme building with EMCP Themer', 'emcp-tools' ),
		'duration' => '6:58',
		'url'      => 'https://emcptools.com/tutorials',
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

	<!-- Featured video guides -->
	<section class="emcp-dash-section" aria-labelledby="emcp-dash-videos-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-videos-h" class="emcp-dash-section-title"><?php esc_html_e( 'Featured video guides', 'emcp-tools' ); ?></h2>
			<p class="emcp-dash-section-sub"><?php esc_html_e( 'Watch and learn — from first connection to full-page builds.', 'emcp-tools' ); ?></p>
		</div>
		<div class="emcp-dash-videos">
			<?php foreach ( $emcp_videos as $emcp_video ) : ?>
				<a class="emcp-dash-video" href="<?php echo esc_url( $emcp_video['url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<span class="emcp-dash-video-thumb">
						<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
						<span class="emcp-dash-video-duration"><?php echo esc_html( $emcp_video['duration'] ); ?></span>
					</span>
					<span class="emcp-dash-video-title"><?php echo esc_html( $emcp_video['title'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- Help & resources -->
	<section class="emcp-dash-section" aria-labelledby="emcp-dash-help-h">
		<div class="emcp-dash-section-head">
			<h2 id="emcp-dash-help-h" class="emcp-dash-section-title"><?php esc_html_e( 'Help &amp; resources', 'emcp-tools' ); ?></h2>
		</div>
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

</div>
