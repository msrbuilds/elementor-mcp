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
$emcp_tools_compact_mode  = '1' === (string) get_option( EMCP_Tools_Plugin::OPTION_DISPATCHER_MODE, '0' );

$emcp_tools_tabs               = EMCP_Tools_Admin::platform_tabs();
$emcp_tools_buckets            = EMCP_Tools_Admin::partition_by_platform( $emcp_tools_all_tools );
$emcp_tools_elementor_active   = EMCP_Tools_Bootstrap::elementor_active();

/**
 * Per-tab enabled/total counts, computed from the stored disabled set.
 */
$emcp_tools_tab_counts = array();
foreach ( $emcp_tools_buckets as $emcp_tools_tab_id => $emcp_tools_tab_cats ) {
	$emcp_tools_t_total   = 0;
	$emcp_tools_t_enabled = 0;
	foreach ( $emcp_tools_tab_cats as $emcp_tools_cat ) {
		foreach ( $emcp_tools_cat['tools'] as $emcp_tools_s => $emcp_tools_unused ) {
			$emcp_tools_t_total++;
			if ( ! in_array( $emcp_tools_s, $emcp_tools_disabled, true ) ) {
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

<form method="post" action="options.php" id="elementor-mcp-tools-form">
	<?php settings_fields( EMCP_Tools_Admin::SETTINGS_GROUP ); ?>

	<div class="elementor-mcp-mode-cards">
	<div class="elementor-mcp-low-mode-card">
		<label class="elementor-mcp-low-mode-toggle">
			<input type="hidden" name="<?php echo esc_attr( EMCP_Tools_Plugin::OPTION_DISPATCHER_MODE ); ?>" value="0" />
			<input
				type="checkbox"
				name="<?php echo esc_attr( EMCP_Tools_Plugin::OPTION_DISPATCHER_MODE ); ?>"
				value="1"
				<?php checked( $emcp_tools_compact_mode ); ?>
			/>
			<span class="elementor-mcp-toggle" aria-hidden="true">
				<span class="elementor-mcp-toggle-track"></span>
			</span>
			<span class="elementor-mcp-low-mode-info">
				<span class="elementor-mcp-low-mode-title">
					<?php esc_html_e( 'Compact tool mode', 'emcp-tools' ); ?>
				</span>
				<span class="elementor-mcp-low-mode-desc">
					<?php esc_html_e( 'Exposes 3 dispatcher tools (list-tools, get-tool-schema, call-tool) instead of every individual tool, so MCP clients that cap the tool count can still reach the whole surface. Your per-tool toggles below stay in effect — call-tool refuses any tool you disable. Reconnect your client after changing this.', 'emcp-tools' ); ?>
				</span>
			</span>
		</label>
	</div>

	<?php if ( class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled() ) : ?>
		<?php $emcp_tools_themer_php_on = '1' === (string) get_option( EMCP_Tools_Themer_PHP::OPTION_ENABLED, '0' ); ?>
		<div class="elementor-mcp-low-mode-card">
			<label class="elementor-mcp-low-mode-toggle">
				<input type="hidden" name="<?php echo esc_attr( EMCP_Tools_Themer_PHP::OPTION_ENABLED ); ?>" value="0" />
				<input
					type="checkbox"
					name="<?php echo esc_attr( EMCP_Tools_Themer_PHP::OPTION_ENABLED ); ?>"
					value="1"
					<?php checked( $emcp_tools_themer_php_on ); ?>
				/>
				<span class="elementor-mcp-toggle" aria-hidden="true">
					<span class="elementor-mcp-toggle-track"></span>
				</span>
				<span class="elementor-mcp-low-mode-info">
					<span class="elementor-mcp-low-mode-title">
						<?php esc_html_e( 'Themer PHP Templates (advanced)', 'emcp-tools' ); ?>
					</span>
					<span class="elementor-mcp-low-mode-desc">
						<?php esc_html_e( 'Lets AI agents author raw PHP region templates (header/footer/single/archive) into a validated sandbox, which you then select on a template to take over its render. Off by default — enabling it also reveals the “PHP Templates” screen under the EMCP Themer menu and the per-template selector. The 5 MCP tools below still ship disabled until you enable them.', 'emcp-tools' ); ?>
					</span>
				</span>
			</label>
		</div>
	<?php endif; ?>
	</div><!-- .elementor-mcp-mode-cards -->

	<?php if ( $emcp_tools_compact_mode ) : ?>
		<div class="elementor-mcp-compact-banner">
			<p>
				<?php esc_html_e( 'Compact tool mode is active — your client sees only the 3 dispatcher tools (list-tools, get-tool-schema, call-tool). The per-tool toggles below still control what call-tool is allowed to run.', 'emcp-tools' ); ?>
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
				class="elementor-mcp-subtab <?php echo esc_attr( $emcp_tools_first ? 'is-active' : '' ); ?>"
				role="tab"
				data-tab="<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
				aria-selected="<?php echo esc_attr( $emcp_tools_first ? 'true' : 'false' ); ?>"
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
			class="elementor-mcp-tabpanel <?php echo esc_attr( $emcp_tools_first_panel ? 'is-active' : '' ); ?>"
			id="emcp-tabpanel-<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
			role="tabpanel"
			data-tab="<?php echo esc_attr( $emcp_tools_tab_id ); ?>"
		>
			<?php if ( 'elementor' === $emcp_tools_tab_id && ! $emcp_tools_elementor_active ) : ?>
			<div class="notice notice-warning inline elementor-mcp-elementor-inactive">
				<p>
					<?php esc_html_e( 'Elementor is not active. Install and activate Elementor to use these tools.', 'emcp-tools' ); ?>
					<a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' ) ); ?>">
						<?php esc_html_e( 'Install Elementor', 'emcp-tools' ); ?>
					</a>
				</p>
			</div>
			<?php endif; ?>
			<?php
			// Plugin-integration explainer: rendered once at the very top of the
			// tab, above every category section. The note text lives on the
			// category data (first category that carries one wins).
			$emcp_tools_tab_note = '';
			foreach ( $emcp_tools_tab_cats as $emcp_tools_note_cat ) {
				if ( ! empty( $emcp_tools_note_cat['note'] ) ) {
					$emcp_tools_tab_note = (string) $emcp_tools_note_cat['note'];
					break;
				}
			}
			?>
			<?php if ( '' !== $emcp_tools_tab_note ) : ?>
				<p class="elementor-mcp-cat-note elementor-mcp-tab-note">
					<span class="elementor-mcp-cat-note-icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" width="15" height="15"><path fill="currentColor" d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 12H9v-4h2v4zm0-6H9V6h2v2z"/></svg>
					</span>
					<span><?php echo esc_html( $emcp_tools_tab_note ); ?></span>
				</p>
			<?php endif; ?>
			<?php foreach ( $emcp_tools_tab_cats as $emcp_tools_category_id => $emcp_tools_category ) : ?>
				<div class="elementor-mcp-category <?php echo esc_attr( ! empty( $emcp_tools_category['danger'] ) ? 'is-danger' : '' ); ?>" data-category="<?php echo esc_attr( $emcp_tools_category_id ); ?>">
					<?php
					$emcp_tools_cat_total   = count( $emcp_tools_category['tools'] );
					$emcp_tools_cat_enabled = 0;
					foreach ( $emcp_tools_category['tools'] as $emcp_tools_slug => $emcp_tools_tool ) {
						if ( ! in_array( $emcp_tools_slug, $emcp_tools_disabled, true ) ) {
							$emcp_tools_cat_enabled++;
						}
					}
					$emcp_tools_grid_id       = 'emcp-cat-' . $emcp_tools_category_id;
					$emcp_tools_cat_unavailable = ( ! $emcp_tools_elementor_active && EMCP_Tools_Admin::is_elementor_category( $emcp_tools_category ) );
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

					<?php
					// Dispatcher-style categories (plugin integrations) lay their
					// cards out two-up with operation pills; the explanatory note
					// is rendered once at the top of the tab (see above).
					$emcp_tools_first_tool = reset( $emcp_tools_category['tools'] );
					$emcp_tools_has_ops    = ! empty( $emcp_tools_first_tool['operations'] );
					?>
					<div class="elementor-mcp-tools-grid <?php echo esc_attr( $emcp_tools_has_ops ? 'is-two-up' : '' ); ?>" id="<?php echo esc_attr( $emcp_tools_grid_id ); ?>">
						<?php foreach ( $emcp_tools_category['tools'] as $emcp_tools_slug => $emcp_tools_tool ) : ?>
							<?php
							$emcp_tools_is_enabled = ! in_array( $emcp_tools_slug, $emcp_tools_disabled, true );
							// A tool is unavailable when its whole platform is off (Elementor
							// inactive) OR the tool carries an explicit availability flag that
							// is false (e.g. the Astra tools when Astra is not the active
							// theme, the Spectra tools when the Spectra plugin is inactive).
							$emcp_tools_tool_unavailable = $emcp_tools_cat_unavailable
								|| ( array_key_exists( 'available', $emcp_tools_tool ) && ! $emcp_tools_tool['available'] );
							?>
							<label class="elementor-mcp-tool-card <?php echo esc_attr( ( $emcp_tools_is_enabled ? 'is-enabled' : 'is-disabled' ) . ( $emcp_tools_tool_unavailable ? ' is-unavailable' : '' ) ); ?>">
								<input
									type="checkbox"
									name="<?php echo esc_attr( EMCP_Tools_Admin::OPTION_DISABLED_TOOLS ); ?>[]"
									value="<?php echo esc_attr( $emcp_tools_slug ); ?>"
									data-stored-enabled="<?php echo esc_attr( in_array( $emcp_tools_slug, $emcp_tools_disabled, true ) ? '0' : '1' ); ?>"
									<?php checked( $emcp_tools_is_enabled ); ?>
									<?php disabled( $emcp_tools_tool_unavailable ); ?>
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
									<?php if ( $emcp_tools_tool_unavailable && ! empty( $emcp_tools_tool['unavailable_note'] ) ) : ?>
										<span class="elementor-mcp-tool-unavailable-note"><?php echo esc_html( $emcp_tools_tool['unavailable_note'] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $emcp_tools_tool['operations'] ) ) : ?>
										<span class="elementor-mcp-tool-ops">
											<span class="elementor-mcp-tool-ops-label">
												<?php
												printf(
													/* translators: %d: number of operations */
													esc_html( _n( '%d operation', '%d operations', count( $emcp_tools_tool['operations'] ), 'emcp-tools' ) ),
													count( $emcp_tools_tool['operations'] )
												);
												?>
											</span>
											<span class="elementor-mcp-op-pills">
												<?php foreach ( $emcp_tools_tool['operations'] as $emcp_tools_op ) : ?>
													<span class="elementor-mcp-op-pill"><?php echo esc_html( $emcp_tools_op ); ?></span>
												<?php endforeach; ?>
											</span>
										</span>
									<?php endif; ?>
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
