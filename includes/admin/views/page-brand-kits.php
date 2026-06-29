<?php
/**
 * Brand Kits tab view.
 *
 * Free users: a curated set of 10 bundled brand kits (coordinated colors +
 * typography) they can apply, with backup-before-apply + restore, plus an
 * upgrade banner to unlock the full library.
 * Pro users: the full categorized library fetched from the server (50+), a
 * "Sync Library" refresh, apply confirmation modal, and restore.
 *
 * A single rendering path is fed by `$emcp_tools_render` — the Pro bundle
 * when the site has Pro AND it loaded, otherwise the bundled free set (which
 * also serves as a graceful fallback if the Pro fetch errors). Applying and
 * backup/restore are free features as of 1.9.0; the Pro value is the bigger
 * library + the MCP brand-kit tools.
 *
 * Previews use pre-rendered, font-outlined SVGs (thumbnail_url) — bundled in the
 * plugin for the free set, served from the bundle for Pro. When absent we fall
 * back to a CSS swatch strip; no Google Fonts are ever loaded in wp-admin.
 *
 * @package EMCP_Tools
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_has_pro    = class_exists( 'EMCP_Tools_Pro_Brand_Kits' ) && EMCP_Tools_Pro_Brand_Kits::user_has_access();
$emcp_tools_pro_bundle = null;
$emcp_tools_pro_error  = null;
if ( $emcp_tools_has_pro ) {
	$emcp_tools_pro_result = EMCP_Tools_Pro_Brand_Kits::get_bundle();
	if ( is_wp_error( $emcp_tools_pro_result ) ) {
		$emcp_tools_pro_error = $emcp_tools_pro_result->get_error_message();
	} else {
		$emcp_tools_pro_bundle = $emcp_tools_pro_result;
	}
}

$emcp_tools_free_bundle = class_exists( 'EMCP_Tools_Free_Brand_Kits' )
	? EMCP_Tools_Free_Brand_Kits::get_bundle()
	: array( 'categories' => array() );

// Pick what to render: the Pro library when present, else the free set (which
// also covers a Pro fetch error as a graceful fallback).
$emcp_tools_render      = ( $emcp_tools_has_pro && is_array( $emcp_tools_pro_bundle ) )
	? $emcp_tools_pro_bundle
	: $emcp_tools_free_bundle;
$emcp_tools_is_free_set = ! ( $emcp_tools_has_pro && is_array( $emcp_tools_pro_bundle ) );
// Only nudge genuine free users — never nag a paying customer who hit a fetch error.
$emcp_tools_show_upgrade = $emcp_tools_is_free_set && ! $emcp_tools_has_pro;

$emcp_tools_upgrade_url = emcp_tools_upgrade_url();
$emcp_tools_bk_backups  = class_exists( 'EMCP_Tools_Kit_Backup_Store' )
	? EMCP_Tools_Kit_Backup_Store::list_backups()
	: array();

$emcp_tools_bk_total = 0;
foreach ( $emcp_tools_render['categories'] as $emcp_tools_bk_cat ) {
	$emcp_tools_bk_total += is_array( $emcp_tools_bk_cat['kits'] ?? null ) ? count( $emcp_tools_bk_cat['kits'] ) : 0;
}
?>

<div class="elementor-mcp-brand-kits">

	<?php if ( ! EMCP_Tools_Bootstrap::elementor_active() ) : ?>
	<div class="notice notice-warning inline">
		<p>
			<?php esc_html_e( 'Brand Kits apply colors and typography to your Elementor kit. Install and activate Elementor to use this feature.', 'emcp-tools' ); ?>
			<a href="<?php echo esc_url( self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' ) ); ?>">
				<?php esc_html_e( 'Install Elementor', 'emcp-tools' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<?php if ( $emcp_tools_bk_total > 0 ) : ?>

		<div class="elementor-mcp-pro-prompts">
			<div class="elementor-mcp-pro-prompts-header">
				<div class="elementor-mcp-pro-prompts-heading">
					<h2>
						<?php esc_html_e( 'Brand Kits Library', 'emcp-tools' ); ?>
						<?php if ( $emcp_tools_is_free_set ) : ?>
							<span class="elementor-mcp-badge elementor-mcp-badge--free"><?php esc_html_e( 'FREE', 'emcp-tools' ); ?></span>
						<?php else : ?>
							<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
						<?php endif; ?>
					</h2>
					<p class="description">
						<?php if ( $emcp_tools_is_free_set ) : ?>
							<?php
							printf(
								/* translators: %d: number of free brand kits */
								esc_html__( '%d coordinated color + typography kits, free to apply. One click replaces your site\'s global palette and fonts — back up first and restore any time.', 'emcp-tools' ),
								(int) $emcp_tools_bk_total
							);
							?>
						<?php else : ?>
							<?php
							printf(
								/* translators: %1$d: kits, %2$d: categories */
								esc_html__( '%1$d coordinated color + typography kits across %2$d categories. One click replaces your site\'s global palette and fonts.', 'emcp-tools' ),
								(int) $emcp_tools_bk_total,
								(int) count( $emcp_tools_render['categories'] )
							);
							?>
							<?php if ( ! empty( $emcp_tools_render['fetched_at'] ) ) : ?>
								<span class="elementor-mcp-pro-prompts-meta">
									<?php
									printf(
										/* translators: %s: human-readable time since last sync */
										esc_html__( 'Last synced %s ago.', 'emcp-tools' ),
										esc_html( human_time_diff( (int) $emcp_tools_render['fetched_at'], time() ) )
									);
									?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</p>
				</div>
				<?php if ( ! $emcp_tools_is_free_set ) : ?>
					<button
						type="button"
						class="button elementor-mcp-pro-sync-btn"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_sync_pro_brand_kits' ) ); ?>"
						data-sync-action="emcp_tools_sync_pro_brand_kits"
					>
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Sync Library', 'emcp-tools' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $emcp_tools_has_pro && $emcp_tools_pro_error ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php echo esc_html( $emcp_tools_pro_error ); ?>
						<?php esc_html_e( 'Showing the bundled starter kits in the meantime.', 'emcp-tools' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $emcp_tools_show_upgrade ) : ?>
				<div class="elementor-mcp-coming-soon" role="status">
					<span class="elementor-mcp-coming-soon__icon" aria-hidden="true">
						<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					</span>
					<div class="elementor-mcp-coming-soon__text">
						<strong><?php esc_html_e( 'Unlock 40+ more brand kits with Pro.', 'emcp-tools' ); ?></strong>
						<?php esc_html_e( 'The full library spans corporate, creative, e-commerce, editorial, hospitality, trades, and wellness — plus MCP tools so AI agents can re-skin sites for you.', 'emcp-tools' ); ?>
						<a href="<?php echo esc_url( $emcp_tools_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade to Pro →', 'emcp-tools' ); ?></a>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( count( $emcp_tools_render['categories'] ) > 1 ) : ?>
				<div class="elementor-mcp-pro-filters" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'emcp-tools' ); ?>">
					<button type="button" class="elementor-mcp-pro-filter is-active" data-category="all">
						<?php esc_html_e( 'All', 'emcp-tools' ); ?>
						<span class="elementor-mcp-pro-filter-count"><?php echo (int) $emcp_tools_bk_total; ?></span>
					</button>
					<?php foreach ( $emcp_tools_render['categories'] as $emcp_tools_bk_cat ) :
						$emcp_tools_cat_slug  = isset( $emcp_tools_bk_cat['slug'] ) ? sanitize_key( $emcp_tools_bk_cat['slug'] ) : '';
						$emcp_tools_cat_label = isset( $emcp_tools_bk_cat['label'] ) ? (string) $emcp_tools_bk_cat['label'] : '';
						$emcp_tools_cat_count = is_array( $emcp_tools_bk_cat['kits'] ?? null ) ? count( $emcp_tools_bk_cat['kits'] ) : 0;
						if ( '' === $emcp_tools_cat_slug || '' === $emcp_tools_cat_label ) {
							continue;
						}
					?>
						<button type="button" class="elementor-mcp-pro-filter" data-category="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>">
							<?php echo esc_html( $emcp_tools_cat_label ); ?>
							<span class="elementor-mcp-pro-filter-count"><?php echo (int) $emcp_tools_cat_count; ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div
				class="elementor-mcp-brand-kit-grid"
				data-apply-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_apply_pro_brand_kit' ) ); ?>"
			>
				<?php foreach ( $emcp_tools_render['categories'] as $emcp_tools_bk_cat ) :
					$emcp_tools_cat_slug  = isset( $emcp_tools_bk_cat['slug'] ) ? sanitize_key( $emcp_tools_bk_cat['slug'] ) : '';
					$emcp_tools_cat_label = isset( $emcp_tools_bk_cat['label'] ) ? (string) $emcp_tools_bk_cat['label'] : '';
					if ( '' === $emcp_tools_cat_slug || empty( $emcp_tools_bk_cat['kits'] ) ) {
						continue;
					}
					foreach ( $emcp_tools_bk_cat['kits'] as $emcp_tools_kit ) :
						$emcp_tools_k_slug  = isset( $emcp_tools_kit['slug'] ) ? sanitize_key( $emcp_tools_kit['slug'] ) : '';
						$emcp_tools_k_title = isset( $emcp_tools_kit['title'] ) ? (string) $emcp_tools_kit['title'] : '';
						$emcp_tools_k_desc  = isset( $emcp_tools_kit['description'] ) ? (string) $emcp_tools_kit['description'] : '';
						$emcp_tools_k_thumb = isset( $emcp_tools_kit['thumbnail_url'] ) ? (string) $emcp_tools_kit['thumbnail_url'] : '';
						if ( '' === $emcp_tools_k_slug ) {
							continue;
						}

						// Swatch fallback when no pre-rendered preview ships.
						$emcp_tools_swatches = array();
						if ( isset( $emcp_tools_kit['preview']['swatches'] ) && is_array( $emcp_tools_kit['preview']['swatches'] ) ) {
							$emcp_tools_swatches = $emcp_tools_kit['preview']['swatches'];
						} elseif ( isset( $emcp_tools_kit['colors'] ) && is_array( $emcp_tools_kit['colors'] ) ) {
							foreach ( array( 'primary', 'secondary', 'text', 'accent' ) as $emcp_tools_slot ) {
								if ( isset( $emcp_tools_kit['colors'][ $emcp_tools_slot ]['color'] ) ) {
									$emcp_tools_swatches[] = $emcp_tools_kit['colors'][ $emcp_tools_slot ]['color'];
								}
							}
						}
				?>
						<div class="elementor-mcp-brand-kit-card" data-category="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>">
							<?php if ( '' !== $emcp_tools_k_thumb ) : ?>
								<div class="elementor-mcp-brand-kit-preview">
									<img src="<?php echo esc_url( $emcp_tools_k_thumb ); ?>" alt="<?php echo esc_attr( $emcp_tools_k_title ); ?>" loading="lazy" />
								</div>
							<?php elseif ( ! empty( $emcp_tools_swatches ) ) : ?>
								<div class="elementor-mcp-brand-kit-swatches" aria-hidden="true">
									<?php
									$emcp_tools_widths = array( '50%', '25%', '15%', '10%' );
									foreach ( array_slice( $emcp_tools_swatches, 0, 4 ) as $emcp_tools_i => $emcp_tools_hex ) :
										$emcp_tools_hex_safe = sanitize_hex_color( (string) $emcp_tools_hex );
										if ( empty( $emcp_tools_hex_safe ) ) {
											continue;
										}
									?>
										<span
											class="elementor-mcp-brand-kit-swatch"
											style="width:<?php echo esc_attr( $emcp_tools_widths[ $emcp_tools_i ] ?? '25%' ); ?>;background-color:<?php echo esc_attr( $emcp_tools_hex_safe ); ?>;"
										></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="elementor-mcp-brand-kit-body">
								<div class="elementor-mcp-brand-kit-header">
									<h3 class="elementor-mcp-brand-kit-title"><?php echo esc_html( $emcp_tools_k_title ); ?></h3>
									<span class="elementor-mcp-prompt-tag"><?php echo esc_html( $emcp_tools_cat_label ); ?></span>
								</div>
								<?php if ( '' !== $emcp_tools_k_desc ) : ?>
									<p class="elementor-mcp-brand-kit-desc"><?php echo esc_html( $emcp_tools_k_desc ); ?></p>
								<?php endif; ?>
								<div class="elementor-mcp-brand-kit-actions">
									<button
										type="button"
										class="button button-primary elementor-mcp-brand-kit-apply"
										data-category-slug="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>"
										data-kit-slug="<?php echo esc_attr( $emcp_tools_k_slug ); ?>"
										data-kit-title="<?php echo esc_attr( $emcp_tools_k_title ); ?>"
									>
										<?php esc_html_e( 'Apply Kit', 'emcp-tools' ); ?>
									</button>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>

			<!-- Restore from backup -->
			<div class="elementor-mcp-brand-kit-restore" data-restore-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_restore_pro_brand_kit' ) ); ?>">
				<h3><?php esc_html_e( 'Restore from backup', 'emcp-tools' ); ?></h3>
				<?php if ( ! empty( $emcp_tools_bk_backups ) ) : ?>
					<p class="description"><?php esc_html_e( 'Roll your global colors and typography back to a saved point. By default only kit-applied tokens are restored; tick the box to clobber your custom colors/typography exactly as they were.', 'emcp-tools' ); ?></p>
					<div class="elementor-mcp-brand-kit-restore-row">
						<select class="elementor-mcp-brand-kit-backup-select">
							<?php foreach ( $emcp_tools_bk_backups as $emcp_tools_backup ) : ?>
								<option value="<?php echo esc_attr( (int) $emcp_tools_backup['id'] ); ?>">
									<?php echo esc_html( $emcp_tools_backup['title'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button elementor-mcp-brand-kit-restore-btn">
							<?php esc_html_e( 'Restore', 'emcp-tools' ); ?>
						</button>
					</div>
					<label class="elementor-mcp-brand-kit-clobber">
						<input type="checkbox" class="elementor-mcp-brand-kit-clobber-input" value="1" />
						<?php esc_html_e( 'Also restore my custom colors and typography exactly as they were', 'emcp-tools' ); ?>
					</label>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No backups yet. The first time you apply a kit (with the backup option checked), a restore point will appear here.', 'emcp-tools' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Apply confirmation modal -->
		<div class="elementor-mcp-brand-kit-modal" hidden>
			<div class="elementor-mcp-brand-kit-modal__backdrop" data-modal-dismiss></div>
			<div class="elementor-mcp-brand-kit-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="elementor-mcp-bk-modal-title">
				<h3 id="elementor-mcp-bk-modal-title" class="elementor-mcp-brand-kit-modal__title"></h3>
				<p class="elementor-mcp-brand-kit-modal__body">
					<?php esc_html_e( 'This will replace your site\'s global colors and typography. Every widget using global color/type tokens will switch to the new palette.', 'emcp-tools' ); ?>
				</p>
				<label class="elementor-mcp-brand-kit-modal__backup">
					<input type="checkbox" class="elementor-mcp-brand-kit-modal__backup-input" value="1" checked />
					<?php esc_html_e( 'Back up current global settings (recommended)', 'emcp-tools' ); ?>
				</label>
				<div class="elementor-mcp-brand-kit-modal__actions">
					<button type="button" class="button" data-modal-dismiss><?php esc_html_e( 'Cancel', 'emcp-tools' ); ?></button>
					<button type="button" class="button button-primary elementor-mcp-brand-kit-modal__confirm"><?php esc_html_e( 'Apply Brand Kit', 'emcp-tools' ); ?></button>
				</div>
			</div>
		</div>

	<?php else : ?>

		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'No brand kits are available right now.', 'emcp-tools' ); ?></p>
		</div>

	<?php endif; ?>

</div>
