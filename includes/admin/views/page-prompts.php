<?php
/**
 * Prompts tab view for the MCP Tools for Elementor admin settings page.
 *
 * Free section: 5 bundled sample prompts.
 * Premium section: 50+ categorized prompts fetched from the EMCP Tools Pro
 * server when a valid license is active.
 *
 * @package EMCP_Tools
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sample prompt metadata: filename (without .md) => title, industry tag, description.
 */
$emcp_tools_prompt_meta = array(
	'LOCAL_BUSINESS'          => array(
		'title'       => __( 'Local Business', 'emcp-tools' ),
		'industry'    => __( 'General', 'emcp-tools' ),
		'description' => __( 'Multi-purpose small business landing page with hero, services, testimonials, and contact section.', 'emcp-tools' ),
	),
	'DENTAL_CLINIC'           => array(
		'title'       => __( 'Dental Clinic', 'emcp-tools' ),
		'industry'    => __( 'Health & Wellness', 'emcp-tools' ),
		'description' => __( 'Professional dental practice with services grid, team profiles, insurance info, and appointment booking.', 'emcp-tools' ),
	),
	'WEB_DEVELOPER_PORTFOLIO' => array(
		'title'       => __( 'Web Developer Portfolio', 'emcp-tools' ),
		'industry'    => __( 'Professional Services', 'emcp-tools' ),
		'description' => __( 'Developer portfolio with project showcase, tech stack, GitHub stats, and contact form.', 'emcp-tools' ),
	),
	'HAIR_SALON'              => array(
		'title'       => __( 'Hair Salon', 'emcp-tools' ),
		'industry'    => __( 'Lifestyle', 'emcp-tools' ),
		'description' => __( 'Stylish salon page with services menu, stylist profiles, gallery, and online booking.', 'emcp-tools' ),
	),
	'CAR_WASH'                => array(
		'title'       => __( 'Car Wash', 'emcp-tools' ),
		'industry'    => __( 'Lifestyle', 'emcp-tools' ),
		'description' => __( 'Car wash site with wash packages, add-on services, membership plans, and booking form.', 'emcp-tools' ),
	),
);

$emcp_tools_prompts_dir = EMCP_TOOLS_DIR . 'prompts/';

$emcp_tools_has_pro    = class_exists( 'EMCP_Tools_Pro_Prompts' ) && EMCP_Tools_Pro_Prompts::user_has_access();
$emcp_tools_pro_bundle = null;
$emcp_tools_pro_error  = null;
if ( $emcp_tools_has_pro ) {
	$emcp_tools_pro_result = EMCP_Tools_Pro_Prompts::get_bundle();
	if ( is_wp_error( $emcp_tools_pro_result ) ) {
		$emcp_tools_pro_error = $emcp_tools_pro_result->get_error_message();
	} else {
		$emcp_tools_pro_bundle = $emcp_tools_pro_result;
	}
}

$emcp_tools_upgrade_url = emcp_tools_upgrade_url();
?>

<div class="elementor-mcp-prompts">

	<?php
	// Hide the bundled sample-prompts section when the user has Pro AND the
	// premium bundle loaded successfully — the 5 samples are a subset of the
	// 50+ premium prompts, so showing both is duplication. Free users (and
	// Pro users hitting a fetch error) still get the samples.
	$emcp_tools_show_samples = ! ( $emcp_tools_has_pro && is_array( $emcp_tools_pro_bundle ) );
	?>

	<?php if ( $emcp_tools_show_samples ) : ?>
		<div class="elementor-mcp-prompts-intro">
			<h2><?php esc_html_e( 'Sample Prompts', 'emcp-tools' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Ready-to-use landing page blueprints for AI agents. Copy any prompt below and paste it into your AI client (Claude, Cursor, etc.) — it will automatically build a complete Elementor page using MCP tools.', 'emcp-tools' ); ?>
			</p>
		</div>

		<div class="elementor-mcp-prompts-grid">
			<?php foreach ( $emcp_tools_prompt_meta as $emcp_tools_slug => $emcp_tools_meta ) :
				$emcp_tools_file_path = $emcp_tools_prompts_dir . $emcp_tools_slug . '.md';
				if ( ! file_exists( $emcp_tools_file_path ) ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
				$emcp_tools_content = file_get_contents( $emcp_tools_file_path );
				$emcp_tools_copy_id = 'elementor-mcp-prompt-' . sanitize_title( $emcp_tools_slug );
			?>
				<div class="elementor-mcp-prompt-card">
					<div class="elementor-mcp-prompt-header">
						<h3 class="elementor-mcp-prompt-title"><?php echo esc_html( $emcp_tools_meta['title'] ); ?></h3>
						<span class="elementor-mcp-prompt-tag"><?php echo esc_html( $emcp_tools_meta['industry'] ); ?></span>
					</div>
					<p class="elementor-mcp-prompt-desc"><?php echo esc_html( $emcp_tools_meta['description'] ); ?></p>
					<div class="elementor-mcp-prompt-actions">
						<button type="button" class="button elementor-mcp-copy-btn" data-target="<?php echo esc_attr( $emcp_tools_copy_id ); ?>">
							<svg viewBox="0 0 20 20" width="14" height="14" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
						<?php esc_html_e( 'Copy Prompt', 'emcp-tools' ); ?>
						</button>
					</div>
					<textarea id="<?php echo esc_attr( $emcp_tools_copy_id ); ?>" class="elementor-mcp-copy-source"><?php echo esc_textarea( $emcp_tools_content ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php // -------------------------------------------------------------------
	// Premium prompts library.
	// ------------------------------------------------------------------- ?>

	<?php if ( $emcp_tools_has_pro && is_array( $emcp_tools_pro_bundle ) ) : ?>

		<div class="elementor-mcp-pro-prompts">
			<div class="elementor-mcp-pro-prompts-header">
				<div class="elementor-mcp-pro-prompts-heading">
					<h2>
						<?php esc_html_e( 'Premium Prompts Library', 'emcp-tools' ); ?>
						<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
					</h2>
					<p class="description">
						<?php
						$emcp_tools_total = 0;
						foreach ( $emcp_tools_pro_bundle['categories'] as $emcp_tools_cat ) {
							$emcp_tools_total += is_array( $emcp_tools_cat['prompts'] ?? null ) ? count( $emcp_tools_cat['prompts'] ) : 0;
						}
						printf(
							/* translators: %1$d: prompts, %2$d: categories */
							esc_html__( '%1$d prompts across %2$d categories. Updated automatically.', 'emcp-tools' ),
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
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_sync_pro_prompts' ) ); ?>"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Sync Library', 'emcp-tools' ); ?>
				</button>
			</div>

			<div class="elementor-mcp-pro-filters" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'emcp-tools' ); ?>">
				<button type="button" class="elementor-mcp-pro-filter is-active" data-category="all">
					<?php esc_html_e( 'All', 'emcp-tools' ); ?>
					<span class="elementor-mcp-pro-filter-count"><?php echo (int) $emcp_tools_total; ?></span>
				</button>
				<?php foreach ( $emcp_tools_pro_bundle['categories'] as $emcp_tools_cat ) :
					$emcp_tools_cat_slug  = isset( $emcp_tools_cat['slug'] ) ? sanitize_key( $emcp_tools_cat['slug'] ) : '';
					$emcp_tools_cat_label = isset( $emcp_tools_cat['label'] ) ? (string) $emcp_tools_cat['label'] : '';
					$emcp_tools_cat_count = is_array( $emcp_tools_cat['prompts'] ?? null ) ? count( $emcp_tools_cat['prompts'] ) : 0;
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

			<div class="elementor-mcp-prompts-grid elementor-mcp-pro-prompts-grid">
				<?php foreach ( $emcp_tools_pro_bundle['categories'] as $emcp_tools_cat ) :
					$emcp_tools_cat_slug  = isset( $emcp_tools_cat['slug'] ) ? sanitize_key( $emcp_tools_cat['slug'] ) : '';
					$emcp_tools_cat_label = isset( $emcp_tools_cat['label'] ) ? (string) $emcp_tools_cat['label'] : '';
					if ( '' === $emcp_tools_cat_slug || empty( $emcp_tools_cat['prompts'] ) ) {
						continue;
					}
					foreach ( $emcp_tools_cat['prompts'] as $emcp_tools_prompt ) :
						$emcp_tools_p_slug    = isset( $emcp_tools_prompt['slug'] ) ? sanitize_key( $emcp_tools_prompt['slug'] ) : '';
						$emcp_tools_p_title   = isset( $emcp_tools_prompt['title'] ) ? (string) $emcp_tools_prompt['title'] : '';
						$emcp_tools_p_desc    = isset( $emcp_tools_prompt['description'] ) ? (string) $emcp_tools_prompt['description'] : '';
						$emcp_tools_p_content = isset( $emcp_tools_prompt['content'] ) ? (string) $emcp_tools_prompt['content'] : '';
						if ( '' === $emcp_tools_p_slug || '' === $emcp_tools_p_content ) {
							continue;
						}
						$emcp_tools_copy_id = 'elementor-mcp-pro-prompt-' . $emcp_tools_cat_slug . '-' . $emcp_tools_p_slug;
					?>
						<div class="elementor-mcp-prompt-card elementor-mcp-pro-prompt-card" data-category="<?php echo esc_attr( $emcp_tools_cat_slug ); ?>">
							<div class="elementor-mcp-prompt-header">
								<h3 class="elementor-mcp-prompt-title"><?php echo esc_html( $emcp_tools_p_title ); ?></h3>
								<span class="elementor-mcp-prompt-tag"><?php echo esc_html( $emcp_tools_cat_label ); ?></span>
							</div>
							<p class="elementor-mcp-prompt-desc"><?php echo esc_html( $emcp_tools_p_desc ); ?></p>
							<div class="elementor-mcp-prompt-actions">
								<button type="button" class="button elementor-mcp-copy-btn" data-target="<?php echo esc_attr( $emcp_tools_copy_id ); ?>">
									<svg viewBox="0 0 20 20" width="14" height="14" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
									<?php esc_html_e( 'Copy Prompt', 'emcp-tools' ); ?>
								</button>
							</div>
							<textarea id="<?php echo esc_attr( $emcp_tools_copy_id ); ?>" class="elementor-mcp-copy-source"><?php echo esc_textarea( $emcp_tools_p_content ); ?></textarea>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>

	<?php elseif ( $emcp_tools_has_pro && $emcp_tools_pro_error ) : ?>

		<div class="elementor-mcp-pro-prompts">
			<div class="notice notice-warning inline">
				<p>
					<?php echo esc_html( $emcp_tools_pro_error ); ?>
				</p>
				<p>
					<button
						type="button"
						class="button elementor-mcp-pro-sync-btn"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_tools_sync_pro_prompts' ) ); ?>"
					>
						<?php esc_html_e( 'Retry Sync', 'emcp-tools' ); ?>
					</button>
				</p>
			</div>
		</div>

	<?php else : ?>

		<div class="elementor-mcp-prompts-cta">
			<div class="elementor-mcp-prompts-cta-content">
				<h3><?php esc_html_e( 'Unlock 50+ Premium Prompts', 'emcp-tools' ); ?></h3>
				<p><?php esc_html_e( 'Industry-specific landing page blueprints across 10 categories — restaurants, dental clinics, law firms, photographers, wedding venues, and more. Auto-synced to your site when you upgrade.', 'emcp-tools' ); ?></p>
				<a href="<?php echo esc_url( $emcp_tools_upgrade_url ); ?>" class="button button-primary elementor-mcp-prompts-cta-btn" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					<?php esc_html_e( 'Upgrade to Pro', 'emcp-tools' ); ?>
				</a>
			</div>
		</div>

	<?php endif; ?>

</div>
