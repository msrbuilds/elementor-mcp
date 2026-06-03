<?php
/**
 * Templates tab view.
 *
 * Pro users: categorized grid of premium Elementor templates with per-card
 * "Apply to new page" button + a "Sync Library" refresh button.
 * Free users: upgrade CTA.
 *
 * @package EMCP_Tools
 * @since   1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_has_pro    = class_exists( 'EMCP_Tools_Pro_Templates' ) && EMCP_Tools_Pro_Templates::user_has_access();
$emcp_tools_pro_bundle = null;
$emcp_tools_pro_error  = null;
if ( $emcp_tools_has_pro ) {
	$emcp_tools_pro_result = EMCP_Tools_Pro_Templates::get_bundle();
	if ( is_wp_error( $emcp_tools_pro_result ) ) {
		$emcp_tools_pro_error = $emcp_tools_pro_result->get_error_message();
	} else {
		$emcp_tools_pro_bundle = $emcp_tools_pro_result;
	}
}

$emcp_tools_upgrade_url = emcp_tools_upgrade_url();
?>

<div class="elementor-mcp-templates">

	<?php if ( $emcp_tools_has_pro && is_array( $emcp_tools_pro_bundle ) ) :
		$emcp_tools_total = 0;
		foreach ( $emcp_tools_pro_bundle['categories'] as $emcp_tools_cat ) {
			$emcp_tools_total += is_array( $emcp_tools_cat['templates'] ?? null ) ? count( $emcp_tools_cat['templates'] ) : 0;
		}
	?>

		<div class="elementor-mcp-pro-prompts">
			<div class="elementor-mcp-pro-prompts-header">
				<div class="elementor-mcp-pro-prompts-heading">
					<h2>
						<?php esc_html_e( 'Premium Templates Library', 'emcp-tools' ); ?>
						<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
					</h2>
					<p class="description">
						<?php
						printf(
							/* translators: %1$d: templates, %2$d: categories */
							esc_html__( '%1$d templates across %2$d categories. Create a new page from a template, or import it into Elementor\'s Saved Templates library.', 'emcp-tools' ),
							(int) $emcp_tools_total,
							(int) count( $emcp_tools_pro_bundle['categories'] )
						);
						?>
						<?php if ( ! empty( $emcp_tools_pro_bundle['fetched_at'] ) ) : ?>
							<span class="elementor-mcp-pro-prompts-meta">
								<?php
								printf(
									/* translators: %s: human-readable time since last sync */
									esc_html__( 'Last synced %s ago.', 'emcp-tools' ),
									esc_html( human_time_diff( (int) $emcp_tools_pro_bundle['fetched_at'], time() ) )
								);
								?>
							</span>
						<?php endif; ?>
					</p>
				</div>
				<button
					type="button"
					class="button elementor-mcp-pro-sync-btn"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_sync_pro_templates' ) ); ?>"
					data-sync-action="emcp_tools_sync_pro_templates"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Sync Library', 'emcp-tools' ); ?>
				</button>
			</div>

			<div class="elementor-mcp-coming-soon" role="status">
				<span class="elementor-mcp-coming-soon__icon" aria-hidden="true">
					<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M10 2a1 1 0 011 1v1.05a6.002 6.002 0 015 5.95v3.382l1.447 2.894A1 1 0 0116.553 18H3.447a1 1 0 01-.894-1.724L4 13.382V10a6.002 6.002 0 015-5.95V3a1 1 0 011-1zm-2 17a2 2 0 104 0H8z"/></svg>
				</span>
				<div class="elementor-mcp-coming-soon__text">
					<strong><?php esc_html_e( '50+ more premium templates on the way.', 'emcp-tools' ); ?></strong>
					<?php esc_html_e( 'We\'re actively expanding the library across every category. Click Sync Library above whenever you want the latest.', 'emcp-tools' ); ?>
				</div>
			</div>

			<?php if ( $emcp_tools_total > 0 ) : ?>
				<div class="elementor-mcp-pro-filters" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'emcp-tools' ); ?>">
					<button type="button" class="elementor-mcp-pro-filter is-active" data-category="all">
						<?php esc_html_e( 'All', 'emcp-tools' ); ?>
						<span class="elementor-mcp-pro-filter-count"><?php echo (int) $emcp_tools_total; ?></span>
					</button>
					<?php foreach ( $emcp_tools_pro_bundle['categories'] as $emcp_tools_cat ) :
						$emcp_tools_cat_slug  = isset( $emcp_tools_cat['slug'] ) ? sanitize_key( $emcp_tools_cat['slug'] ) : '';
						$emcp_tools_cat_label = isset( $emcp_tools_cat['label'] ) ? (string) $emcp_tools_cat['label'] : '';
						$emcp_tools_cat_count = is_array( $emcp_tools_cat['templates'] ?? null ) ? count( $emcp_tools_cat['templates'] ) : 0;
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

				<div
					class="elementor-mcp-template-grid"
					data-apply-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_apply_pro_template' ) ); ?>"
					data-import-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_import_pro_template' ) ); ?>"
				>
					<?php foreach ( $emcp_tools_pro_bundle['categories'] as $emcp_tools_cat ) :
						$emcp_tools_cat_slug  = isset( $emcp_tools_cat['slug'] ) ? sanitize_key( $emcp_tools_cat['slug'] ) : '';
						$emcp_tools_cat_label = isset( $emcp_tools_cat['label'] ) ? (string) $emcp_tools_cat['label'] : '';
						if ( '' === $emcp_tools_cat_slug || empty( $emcp_tools_cat['templates'] ) ) {
							continue;
						}
						foreach ( $emcp_tools_cat['templates'] as $emcp_tools_tpl ) :
							$emcp_tools_t_slug    = isset( $emcp_tools_tpl['slug'] ) ? sanitize_key( $emcp_tools_tpl['slug'] ) : '';
							$emcp_tools_t_title   = isset( $emcp_tools_tpl['title'] ) ? (string) $emcp_tools_tpl['title'] : '';
							$emcp_tools_t_desc    = isset( $emcp_tools_tpl['description'] ) ? (string) $emcp_tools_tpl['description'] : '';
							$emcp_tools_t_thumb   = isset( $emcp_tools_tpl['thumbnail_url'] ) ? (string) $emcp_tools_tpl['thumbnail_url'] : '';
							$emcp_tools_t_preview = isset( $emcp_tools_tpl['preview_url'] ) ? (string) $emcp_tools_tpl['preview_url'] : '';
							if ( '' === $emcp_tools_t_slug ) {
								continue;
							}
						?>
							<div class="elementor-mcp-template-card" data-category="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>">
								<?php if ( '' !== $emcp_tools_t_thumb ) : ?>
									<div class="elementor-mcp-template-thumb">
										<img src="<?php echo esc_url( $emcp_tools_t_thumb ); ?>" alt="<?php echo esc_attr( $emcp_tools_t_title ); ?>" loading="lazy" />
									</div>
								<?php else : ?>
									<div class="elementor-mcp-template-thumb elementor-mcp-template-thumb--placeholder" aria-hidden="true">
										<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 4a2 2 0 012-2h14a2 2 0 012 2v16a2 2 0 01-2 2H5a2 2 0 01-2-2V4zm2 0v16h14V4H5zm2 3h10v2H7V7zm0 4h10v2H7v-2zm0 4h6v2H7v-2z"/></svg>
									</div>
								<?php endif; ?>
								<div class="elementor-mcp-template-body">
									<div class="elementor-mcp-template-header">
										<h3 class="elementor-mcp-template-title"><?php echo esc_html( $emcp_tools_t_title ); ?></h3>
										<span class="elementor-mcp-prompt-tag"><?php echo esc_html( $emcp_tools_cat_label ); ?></span>
									</div>
									<?php if ( '' !== $emcp_tools_t_desc ) : ?>
										<p class="elementor-mcp-template-desc"><?php echo esc_html( $emcp_tools_t_desc ); ?></p>
									<?php endif; ?>
									<div class="elementor-mcp-template-actions">
										<?php if ( '' !== $emcp_tools_t_preview ) : ?>
											<a
												href="<?php echo esc_url( $emcp_tools_t_preview ); ?>"
												class="button elementor-mcp-template-preview"
												target="_blank"
												rel="noopener noreferrer"
												title="<?php esc_attr_e( 'Open the live demo of this template in a new tab.', 'emcp-tools' ); ?>"
											>
												<svg viewBox="0 0 20 20" width="14" height="14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
													<path d="M10 4C5 4 1.7 10 1.7 10S5 16 10 16s8.3-6 8.3-6S15 4 10 4zm0 10a4 4 0 110-8 4 4 0 010 8zm0-2a2 2 0 100-4 2 2 0 000 4z" />
												</svg>
												<?php esc_html_e( 'Live Preview', 'emcp-tools' ); ?>
											</a>
										<?php endif; ?>
										<div class="elementor-mcp-template-actions-row">
											<button
												type="button"
												class="button button-primary elementor-mcp-template-apply"
												data-category-slug="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>"
												data-template-slug="<?php echo esc_attr( $emcp_tools_t_slug ); ?>"
											>
												<?php esc_html_e( 'Create Page', 'emcp-tools' ); ?>
											</button>
											<button
												type="button"
												class="button elementor-mcp-template-import"
												data-category-slug="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>"
												data-template-slug="<?php echo esc_attr( $emcp_tools_t_slug ); ?>"
												title="<?php esc_attr_e( 'Add to Elementor\'s Saved Templates library — insertable from the editor\'s Add Template picker on any page.', 'emcp-tools' ); ?>"
											>
												<?php esc_html_e( 'Import to Library', 'emcp-tools' ); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>

			<?php else : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'The Premium Templates library is empty right now. Templates added on the server will appear here on the next sync.', 'emcp-tools' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

	<?php elseif ( $emcp_tools_has_pro && $emcp_tools_pro_error ) : ?>

		<div class="elementor-mcp-pro-prompts">
			<div class="notice notice-warning inline">
				<p><?php echo esc_html( $emcp_tools_pro_error ); ?></p>
				<p>
					<button
						type="button"
						class="button elementor-mcp-pro-sync-btn"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_sync_pro_templates' ) ); ?>"
						data-sync-action="emcp_tools_sync_pro_templates"
					>
						<?php esc_html_e( 'Retry Sync', 'emcp-tools' ); ?>
					</button>
				</p>
			</div>
		</div>

	<?php else : ?>

		<div class="elementor-mcp-prompts-cta">
			<div class="elementor-mcp-prompts-cta-content">
				<h3><?php esc_html_e( 'Unlock the Premium Templates Library', 'emcp-tools' ); ?></h3>
				<p><?php esc_html_e( 'Ready-to-apply Elementor page templates across hero sections, services grids, pricing tables, testimonials, and more. One-click apply creates a new page with the full design — edit visually from there.', 'emcp-tools' ); ?></p>
				<a href="<?php echo esc_url( $emcp_tools_upgrade_url ); ?>" class="button button-primary elementor-mcp-prompts-cta-btn" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
				</a>
			</div>
		</div>

	<?php endif; ?>

</div>
