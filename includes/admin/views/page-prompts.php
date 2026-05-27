<?php
/**
 * Prompts tab view for the MCP Tools for Elementor admin settings page.
 *
 * Free section: 5 bundled sample prompts.
 * Premium section: 50+ categorized prompts fetched from the EMCP Tools Pro
 * server when a valid license is active.
 *
 * @package Elementor_MCP
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sample prompt metadata: filename (without .md) => title, industry tag, description.
 */
$elementor_mcp_prompt_meta = array(
	'LOCAL_BUSINESS'          => array(
		'title'       => __( 'Local Business', 'elementor-mcp' ),
		'industry'    => __( 'General', 'elementor-mcp' ),
		'description' => __( 'Multi-purpose small business landing page with hero, services, testimonials, and contact section.', 'elementor-mcp' ),
	),
	'DENTAL_CLINIC'           => array(
		'title'       => __( 'Dental Clinic', 'elementor-mcp' ),
		'industry'    => __( 'Health & Wellness', 'elementor-mcp' ),
		'description' => __( 'Professional dental practice with services grid, team profiles, insurance info, and appointment booking.', 'elementor-mcp' ),
	),
	'WEB_DEVELOPER_PORTFOLIO' => array(
		'title'       => __( 'Web Developer Portfolio', 'elementor-mcp' ),
		'industry'    => __( 'Professional Services', 'elementor-mcp' ),
		'description' => __( 'Developer portfolio with project showcase, tech stack, GitHub stats, and contact form.', 'elementor-mcp' ),
	),
	'HAIR_SALON'              => array(
		'title'       => __( 'Hair Salon', 'elementor-mcp' ),
		'industry'    => __( 'Lifestyle', 'elementor-mcp' ),
		'description' => __( 'Stylish salon page with services menu, stylist profiles, gallery, and online booking.', 'elementor-mcp' ),
	),
	'CAR_WASH'                => array(
		'title'       => __( 'Car Wash', 'elementor-mcp' ),
		'industry'    => __( 'Lifestyle', 'elementor-mcp' ),
		'description' => __( 'Car wash site with wash packages, add-on services, membership plans, and booking form.', 'elementor-mcp' ),
	),
);

$elementor_mcp_prompts_dir = ELEMENTOR_MCP_DIR . 'prompts/';

$elementor_mcp_has_pro    = class_exists( 'Elementor_MCP_Pro_Prompts' ) && Elementor_MCP_Pro_Prompts::user_has_access();
$elementor_mcp_pro_bundle = null;
$elementor_mcp_pro_error  = null;
if ( $elementor_mcp_has_pro ) {
	$elementor_mcp_pro_result = Elementor_MCP_Pro_Prompts::get_bundle();
	if ( is_wp_error( $elementor_mcp_pro_result ) ) {
		$elementor_mcp_pro_error = $elementor_mcp_pro_result->get_error_message();
	} else {
		$elementor_mcp_pro_bundle = $elementor_mcp_pro_result;
	}
}

$elementor_mcp_upgrade_url = function_exists( 'emcp_pro_fs' )
	? emcp_pro_fs()->get_upgrade_url()
	: 'https://wpacademy.gumroad.com/l/vlrihk';
?>

<div class="elementor-mcp-prompts">

	<div class="elementor-mcp-prompts-intro">
		<h2><?php esc_html_e( 'Sample Prompts', 'elementor-mcp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Ready-to-use landing page blueprints for AI agents. Copy any prompt below and paste it into your AI client (Claude, Cursor, etc.) — it will automatically build a complete Elementor page using MCP tools.', 'elementor-mcp' ); ?>
		</p>
	</div>

	<div class="elementor-mcp-prompts-grid">
		<?php foreach ( $elementor_mcp_prompt_meta as $elementor_mcp_slug => $elementor_mcp_meta ) :
			$elementor_mcp_file_path = $elementor_mcp_prompts_dir . $elementor_mcp_slug . '.md';
			if ( ! file_exists( $elementor_mcp_file_path ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
			$elementor_mcp_content = file_get_contents( $elementor_mcp_file_path );
			$elementor_mcp_copy_id = 'elementor-mcp-prompt-' . sanitize_title( $elementor_mcp_slug );
		?>
			<div class="elementor-mcp-prompt-card">
				<div class="elementor-mcp-prompt-header">
					<h3 class="elementor-mcp-prompt-title"><?php echo esc_html( $elementor_mcp_meta['title'] ); ?></h3>
					<span class="elementor-mcp-prompt-tag"><?php echo esc_html( $elementor_mcp_meta['industry'] ); ?></span>
				</div>
				<p class="elementor-mcp-prompt-desc"><?php echo esc_html( $elementor_mcp_meta['description'] ); ?></p>
				<div class="elementor-mcp-prompt-actions">
					<button type="button" class="button elementor-mcp-copy-btn" data-target="<?php echo esc_attr( $elementor_mcp_copy_id ); ?>">
						<svg viewBox="0 0 20 20" width="14" height="14" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
					<?php esc_html_e( 'Copy Prompt', 'elementor-mcp' ); ?>
					</button>
				</div>
				<textarea id="<?php echo esc_attr( $elementor_mcp_copy_id ); ?>" class="elementor-mcp-copy-source"><?php echo esc_textarea( $elementor_mcp_content ); ?></textarea>
			</div>
		<?php endforeach; ?>
	</div>

	<?php // -------------------------------------------------------------------
	// Premium prompts library.
	// ------------------------------------------------------------------- ?>

	<?php if ( $elementor_mcp_has_pro && is_array( $elementor_mcp_pro_bundle ) ) : ?>

		<div class="elementor-mcp-pro-prompts">
			<div class="elementor-mcp-pro-prompts-header">
				<div class="elementor-mcp-pro-prompts-heading">
					<h2>
						<?php esc_html_e( 'Premium Prompts Library', 'elementor-mcp' ); ?>
						<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
					</h2>
					<p class="description">
						<?php
						$elementor_mcp_total = 0;
						foreach ( $elementor_mcp_pro_bundle['categories'] as $elementor_mcp_cat ) {
							$elementor_mcp_total += is_array( $elementor_mcp_cat['prompts'] ?? null ) ? count( $elementor_mcp_cat['prompts'] ) : 0;
						}
						printf(
							/* translators: %1$d: prompts, %2$d: categories */
							esc_html__( '%1$d prompts across %2$d categories. Updated automatically.', 'elementor-mcp' ),
							(int) $elementor_mcp_total,
							(int) count( $elementor_mcp_pro_bundle['categories'] )
						);
						?>
						<?php if ( ! empty( $elementor_mcp_pro_bundle['fetched_at'] ) ) : ?>
							<span class="elementor-mcp-pro-prompts-meta">
								<?php
								printf(
									/* translators: %s: human-readable time since last sync */
									esc_html__( 'Last synced %s ago.', 'elementor-mcp' ),
									esc_html( human_time_diff( (int) $elementor_mcp_pro_bundle['fetched_at'], time() ) )
								);
								?>
							</span>
						<?php endif; ?>
					</p>
				</div>
				<button
					type="button"
					class="button elementor-mcp-pro-sync-btn"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'elementor_mcp_sync_pro_prompts' ) ); ?>"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Sync Library', 'elementor-mcp' ); ?>
				</button>
			</div>

			<div class="elementor-mcp-pro-filters" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'elementor-mcp' ); ?>">
				<button type="button" class="elementor-mcp-pro-filter is-active" data-category="all">
					<?php esc_html_e( 'All', 'elementor-mcp' ); ?>
					<span class="elementor-mcp-pro-filter-count"><?php echo (int) $elementor_mcp_total; ?></span>
				</button>
				<?php foreach ( $elementor_mcp_pro_bundle['categories'] as $elementor_mcp_cat ) :
					$elementor_mcp_cat_slug  = isset( $elementor_mcp_cat['slug'] ) ? sanitize_key( $elementor_mcp_cat['slug'] ) : '';
					$elementor_mcp_cat_label = isset( $elementor_mcp_cat['label'] ) ? (string) $elementor_mcp_cat['label'] : '';
					$elementor_mcp_cat_count = is_array( $elementor_mcp_cat['prompts'] ?? null ) ? count( $elementor_mcp_cat['prompts'] ) : 0;
					if ( '' === $elementor_mcp_cat_slug || '' === $elementor_mcp_cat_label ) {
						continue;
					}
				?>
					<button type="button" class="elementor-mcp-pro-filter" data-category="<?php echo esc_attr( $elementor_mcp_cat_slug ); ?>">
						<?php echo esc_html( $elementor_mcp_cat_label ); ?>
						<span class="elementor-mcp-pro-filter-count"><?php echo (int) $elementor_mcp_cat_count; ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="elementor-mcp-prompts-grid elementor-mcp-pro-prompts-grid">
				<?php foreach ( $elementor_mcp_pro_bundle['categories'] as $elementor_mcp_cat ) :
					$elementor_mcp_cat_slug  = isset( $elementor_mcp_cat['slug'] ) ? sanitize_key( $elementor_mcp_cat['slug'] ) : '';
					$elementor_mcp_cat_label = isset( $elementor_mcp_cat['label'] ) ? (string) $elementor_mcp_cat['label'] : '';
					if ( '' === $elementor_mcp_cat_slug || empty( $elementor_mcp_cat['prompts'] ) ) {
						continue;
					}
					foreach ( $elementor_mcp_cat['prompts'] as $elementor_mcp_prompt ) :
						$elementor_mcp_p_slug    = isset( $elementor_mcp_prompt['slug'] ) ? sanitize_key( $elementor_mcp_prompt['slug'] ) : '';
						$elementor_mcp_p_title   = isset( $elementor_mcp_prompt['title'] ) ? (string) $elementor_mcp_prompt['title'] : '';
						$elementor_mcp_p_desc    = isset( $elementor_mcp_prompt['description'] ) ? (string) $elementor_mcp_prompt['description'] : '';
						$elementor_mcp_p_content = isset( $elementor_mcp_prompt['content'] ) ? (string) $elementor_mcp_prompt['content'] : '';
						if ( '' === $elementor_mcp_p_slug || '' === $elementor_mcp_p_content ) {
							continue;
						}
						$elementor_mcp_copy_id = 'elementor-mcp-pro-prompt-' . $elementor_mcp_cat_slug . '-' . $elementor_mcp_p_slug;
					?>
						<div class="elementor-mcp-prompt-card elementor-mcp-pro-prompt-card" data-category="<?php echo esc_attr( $elementor_mcp_cat_slug ); ?>">
							<div class="elementor-mcp-prompt-header">
								<h3 class="elementor-mcp-prompt-title"><?php echo esc_html( $elementor_mcp_p_title ); ?></h3>
								<span class="elementor-mcp-prompt-tag"><?php echo esc_html( $elementor_mcp_cat_label ); ?></span>
							</div>
							<p class="elementor-mcp-prompt-desc"><?php echo esc_html( $elementor_mcp_p_desc ); ?></p>
							<div class="elementor-mcp-prompt-actions">
								<button type="button" class="button elementor-mcp-copy-btn" data-target="<?php echo esc_attr( $elementor_mcp_copy_id ); ?>">
									<svg viewBox="0 0 20 20" width="14" height="14" xmlns="http://www.w3.org/2000/svg"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
									<?php esc_html_e( 'Copy Prompt', 'elementor-mcp' ); ?>
								</button>
							</div>
							<textarea id="<?php echo esc_attr( $elementor_mcp_copy_id ); ?>" class="elementor-mcp-copy-source"><?php echo esc_textarea( $elementor_mcp_p_content ); ?></textarea>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>

	<?php elseif ( $elementor_mcp_has_pro && $elementor_mcp_pro_error ) : ?>

		<div class="elementor-mcp-pro-prompts">
			<div class="notice notice-warning inline">
				<p>
					<?php echo esc_html( $elementor_mcp_pro_error ); ?>
				</p>
				<p>
					<button
						type="button"
						class="button elementor-mcp-pro-sync-btn"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'elementor_mcp_sync_pro_prompts' ) ); ?>"
					>
						<?php esc_html_e( 'Retry Sync', 'elementor-mcp' ); ?>
					</button>
				</p>
			</div>
		</div>

	<?php else : ?>

		<div class="elementor-mcp-prompts-cta">
			<div class="elementor-mcp-prompts-cta-content">
				<h3><?php esc_html_e( 'Unlock 50+ Premium Prompts', 'elementor-mcp' ); ?></h3>
				<p><?php esc_html_e( 'Industry-specific landing page blueprints across 10 categories — restaurants, dental clinics, law firms, photographers, wedding venues, and more. Auto-synced to your site when you upgrade.', 'elementor-mcp' ); ?></p>
				<a href="<?php echo esc_url( $elementor_mcp_upgrade_url ); ?>" class="button button-primary elementor-mcp-prompts-cta-btn">
					<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					<?php esc_html_e( 'Upgrade to Pro', 'elementor-mcp' ); ?>
				</a>
			</div>
		</div>

	<?php endif; ?>

</div>
