<?php
/**
 * Tools tab view for the MCP Tools for Elementor admin settings page.
 *
 * Displays all MCP tools grouped by category with toggle switches.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var EMCP_Tools_Admin $this */
$emcp_tools_all_tools     = $this->get_all_tools();
$emcp_tools_disabled      = get_option( EMCP_Tools_Admin::OPTION_DISABLED_TOOLS, array() );
$emcp_tools_disabled      = is_array( $emcp_tools_disabled ) ? $emcp_tools_disabled : array();
$emcp_tools_enabled_count = $this->get_enabled_tool_count();
$emcp_tools_total_count   = $this->get_total_tool_count();
$emcp_tools_low_mode      = '1' === (string) get_option( EMCP_Tools_Admin::OPTION_LOW_TOOL_MODE, '0' );
$emcp_tools_essentials    = EMCP_Tools_Plugin::get_essential_tool_slugs();

$emcp_tools_tabs    = EMCP_Tools_Admin::platform_tabs();
$emcp_tools_buckets = EMCP_Tools_Admin::partition_by_platform( $emcp_tools_all_tools );

/**
 * Per-tab enabled/total counts, computed with the same effective-state logic
 * the category headers use (essentials in low-tools mode, else the stored set).
 */
$emcp_tools_tab_counts = array();
foreach ( $emcp_tools_buckets as $emcp_tools_tab_id => $emcp_tools_tab_cats ) {
	$emcp_tools_t_total   = 0;
	$emcp_tools_t_enabled = 0;
	foreach ( $emcp_tools_tab_cats as $emcp_tools_cat ) {
		foreach ( $emcp_tools_cat['tools'] as $emcp_tools_s => $emcp_tools_unused ) {
			$emcp_tools_t_total++;
			$emcp_tools_eff = $emcp_tools_low_mode
				? in_array( $emcp_tools_s, $emcp_tools_essentials, true )
				: ! in_array( $emcp_tools_s, $emcp_tools_disabled, true );
			if ( $emcp_tools_eff ) {
				$emcp_tools_t_enabled++;
			}
		}
	}
	$emcp_tools_tab_counts[ $emcp_tools_tab_id ] = array( 'enabled' => $emcp_tools_t_enabled, 'total' => $emcp_tools_t_total );
}

// Human-readable labels for badge slugs. "pro" means the EMCP Tools Pro
// license; "elementor-pro" means the tool needs Elementor Pro (a different
// product) — kept visually distinct so the two aren't confused.
$emcp_tools_badge_labels = array(
	'pro'           => __( 'Pro', 'emcp-tools' ),
	'elementor-pro' => __( 'Elementor Pro', 'emcp-tools' ),
	'read-only'     => __( 'read-only', 'emcp-tools' ),
	'free'          => __( 'Free', 'emcp-tools' ),
	'destructive'   => __( 'destructive', 'emcp-tools' ),
);
?>

<form method="post" action="options.php" id="elementor-mcp-tools-form" class="<?php echo $emcp_tools_low_mode ? 'is-low-mode' : ''; ?>">
	<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP ); ?>

	<div class="elementor-mcp-low-mode-card">
		<label class="elementor-mcp-low-mode-toggle">
			<input type="hidden" name="<?php echo esc_attr( EMCP_Tools_Admin::OPTION_LOW_TOOL_MODE ); ?>" value="0" />
			<input
				type="checkbox"
				name="<?php echo esc_attr( EMCP_Tools_Admin::OPTION_LOW_TOOL_MODE ); ?>"
				value="1"
				<?php checked( $emcp_tools_low_mode ); ?>
			/>
			<span class="elementor-mcp-toggle" aria-hidden="true">
				<span class="elementor-mcp-toggle-track"></span>
			</span>
			<span class="elementor-mcp-low-mode-info">
				<span class="elementor-mcp-low-mode-title">
					<?php esc_html_e( 'Low-tools mode (Antigravity-friendly)', 'emcp-tools' ); ?>
				</span>
				<span class="elementor-mcp-low-mode-desc">
					<?php esc_html_e( 'Keeps the active tool count under 60 by exposing only a curated set of essential abilities. Use this when your MCP client enforces a strict tool cap. Your individual toggles below are preserved and take effect again when this is turned off.', 'emcp-tools' ); ?>
				</span>
			</span>
		</label>
	</div>

	<?php if ( $emcp_tools_low_mode ) : ?>
		<div class="elementor-mcp-lowmode-banner">
			<p>
				<?php
				printf(
					/* translators: %d: number of essential tools active in low-tools mode */
					esc_html__( 'Low-tools mode is active — only the %d essential tools (highlighted below) are exposed to your AI client. Your individual toggles are paused and resume when you turn low-tools mode off above.', 'emcp-tools' ),
					(int) $emcp_tools_enabled_count
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="elementor-mcp-bulk-actions">
		<p class="elementor-mcp-tools-summary">
			<?php
			printf(
				/* translators: %1$s: opening strong tag, %2$d: enabled count, %3$d: total count, %4$s: closing strong tag */
				esc_html__( '%1$s%2$d of %3$d%4$s tools enabled.', 'emcp-tools' ),
				'<strong>',
				(int) $emcp_tools_enabled_count,
				(int) $emcp_tools_total_count,
				'</strong>'
			);
			?>
		</p>
		<button type="button" class="button elementor-mcp-enable-all"><?php esc_html_e( 'Enable All', 'emcp-tools' ); ?></button>
		<button type="button" class="button elementor-mcp-disable-all"><?php esc_html_e( 'Disable All', 'emcp-tools' ); ?></button>
		<button type="submit" class="button button-primary elementor-mcp-bulk-save"><?php esc_html_e( 'Save Changes', 'emcp-tools' ); ?></button>
	</div>

	<div class="elementor-mcp-subtabs" role="tablist" aria-label="<?php esc_attr_e( 'Tool platforms', 'emcp-tools' ); ?>">
		<?php $emcp_tools_first = true; ?>
		<?php foreach ( $emcp_tools_tabs as $emcp_tools_tab_id => $emcp_tools_tab_label ) : ?>
			<button
				type="button"
				class="elementor-mcp-subtab <?php echo $emcp_tools_first ? 'is-active' : ''; ?>"
				role="tab"
				data-tab="<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
				aria-selected="<?php echo $emcp_tools_first ? 'true' : 'false'; ?>"
				aria-controls="emcp-tabpanel-<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
			>
				<span class="elementor-mcp-subtab-label"><?php echo esc_html( $emcp_tools_tab_label ); ?></span>
				<span class="elementor-mcp-subtab-count">
					<?php
					printf(
						/* translators: %1$d: enabled, %2$d: total */
						esc_html__( '%1$d / %2$d', 'emcp-tools' ),
						(int) $emcp_tools_tab_counts[ $emcp_tools_tab_id ]['enabled'],
						(int) $emcp_tools_tab_counts[ $emcp_tools_tab_id ]['total']
					);
					?>
				</span>
			</button>
			<?php $emcp_tools_first = false; ?>
		<?php endforeach; ?>
	</div>

	<?php $emcp_tools_first_panel = true; ?>
	<?php foreach ( $emcp_tools_buckets as $emcp_tools_tab_id => $emcp_tools_tab_cats ) : ?>
		<div
			class="elementor-mcp-tabpanel <?php echo $emcp_tools_first_panel ? 'is-active' : ''; ?>"
			id="emcp-tabpanel-<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
			role="tabpanel"
			data-tab="<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
		>
			<?php foreach ( $emcp_tools_tab_cats as $emcp_tools_category_id => $emcp_tools_category ) : ?>
				<div class="elementor-mcp-category" data-category="<?php echo esc_attr( $emcp_tools_category_id ); ?>">
					<?php
					$emcp_tools_cat_total   = count( $emcp_tools_category['tools'] );
					$emcp_tools_cat_enabled = 0;
					foreach ( $emcp_tools_category['tools'] as $emcp_tools_slug => $emcp_tools_tool ) {
						$emcp_tools_eff = $emcp_tools_low_mode
							? in_array( $emcp_tools_slug, $emcp_tools_essentials, true )
							: ! in_array( $emcp_tools_slug, $emcp_tools_disabled, true );
						if ( $emcp_tools_eff ) {
							$emcp_tools_cat_enabled++;
						}
					}
					$emcp_tools_grid_id = 'emcp-cat-' . $emcp_tools_category_id;
					?>
					<div class="elementor-mcp-category-header">
						<button
							type="button"
							class="elementor-mcp-category-toggle"
							aria-expanded="true"
							aria-controls="<?php echo esc_attr( $emcp_tools_grid_id ); ?>"
						>
							<span class="elementor-mcp-category-chevron" aria-hidden="true">
								<svg viewBox="0 0 20 20" width="14" height="14"><path d="M6 8l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
							<span class="elementor-mcp-category-title"><?php echo esc_html( $emcp_tools_category['label'] ); ?></span>
							<span class="elementor-mcp-category-count">
								<?php
								printf(
									/* translators: %1$d: enabled, %2$d: total */
									esc_html__( '%1$d / %2$d', 'emcp-tools' ),
									(int) $emcp_tools_cat_enabled,
									(int) $emcp_tools_cat_total
								);
								?>
							</span>
						</button>
						<span class="elementor-mcp-cat-toggle-group" role="group" aria-label="<?php esc_attr_e( 'Toggle all tools in this section', 'emcp-tools' ); ?>">
							<button type="button" class="elementor-mcp-cat-btn elementor-mcp-cat-enable-all"><?php esc_html_e( 'All', 'emcp-tools' ); ?></button>
							<button type="button" class="elementor-mcp-cat-btn elementor-mcp-cat-disable-all"><?php esc_html_e( 'None', 'emcp-tools' ); ?></button>
						</span>
					</div>

					<div class="elementor-mcp-tools-grid" id="<?php echo esc_attr( $emcp_tools_grid_id ); ?>">
						<?php foreach ( $emcp_tools_category['tools'] as $emcp_tools_slug => $emcp_tools_tool ) : ?>
							<?php
							$emcp_tools_is_enabled = $emcp_tools_low_mode
								? in_array( $emcp_tools_slug, $emcp_tools_essentials, true )
								: ! in_array( $emcp_tools_slug, $emcp_tools_disabled, true );
							?>
							<label class="elementor-mcp-tool-card <?php echo esc_attr( $emcp_tools_is_enabled ? 'is-enabled' : 'is-disabled' ); ?>">
								<input
									type="checkbox"
									name="<?php echo esc_attr( EMCP_Tools_Admin::OPTION_DISABLED_TOOLS ); ?>[]"
									value="<?php echo esc_attr( $emcp_tools_slug ); ?>"
									data-essential="<?php echo in_array( $emcp_tools_slug, $emcp_tools_essentials, true ) ? '1' : '0'; ?>"
									data-stored-enabled="<?php echo in_array( $emcp_tools_slug, $emcp_tools_disabled, true ) ? '0' : '1'; ?>"
									<?php checked( $emcp_tools_is_enabled ); ?>
									<?php disabled( $emcp_tools_low_mode ); ?>
								/>
								<span class="elementor-mcp-toggle" aria-hidden="true">
									<span class="elementor-mcp-toggle-track"></span>
								</span>
								<span class="elementor-mcp-tool-info">
									<span class="elementor-mcp-tool-name">
										<?php echo esc_html( $emcp_tools_tool['label'] ); ?>
										<?php foreach ( $emcp_tools_tool['badges'] as $emcp_tools_badge ) : ?>
											<span class="elementor-mcp-badge elementor-mcp-badge--<?php echo esc_attr( $emcp_tools_badge ); ?>">
												<?php echo esc_html( $emcp_tools_badge_labels[ $emcp_tools_badge ] ?? $emcp_tools_badge ); ?>
											</span>
										<?php endforeach; ?>
									</span>
									<span class="elementor-mcp-tool-desc"><?php echo esc_html( $emcp_tools_tool['description'] ); ?></span>
									<code class="elementor-mcp-tool-slug"><?php echo esc_html( $emcp_tools_slug ); ?></code>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php $emcp_tools_first_panel = false; ?>
	<?php endforeach; ?>

	<?php submit_button( __( 'Save Changes', 'emcp-tools' ) ); ?>
</form>
