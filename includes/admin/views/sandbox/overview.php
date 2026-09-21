<?php
/**
 * Sandbox overview — the parent Sandbox page.
 *
 * Three flat navigation cards (Blocks / Widgets / PHP Snippets). Each card is a
 * single click target into that pillar's full management screen
 * (?page=emcp-tools-widgets&view=blocks|widgets|snippets), with a compact count
 * line and a hover affordance. No gradients.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_sb_base_url    = menu_page_url( 'emcp-tools-widgets', false );
$emcp_sb_upgrade_url = function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : '#';

/**
 * Splits a store's list into an [active, draft] count pair.
 *
 * @param array $list Rows each carrying a 'status' key.
 * @return array{0:int,1:int}
 */
$emcp_sb_counts = static function ( array $list ): array {
	$active = 0;
	$draft  = 0;
	foreach ( $list as $row ) {
		if ( 'active' === ( $row['status'] ?? '' ) ) {
			++$active;
		} else {
			++$draft;
		}
	}
	return array( $active, $draft );
};

// ----- Blocks (Pro) -----
$emcp_sb_blocks_ok = class_exists( 'EMCP_Tools_Block_Store' ) && EMCP_Tools_Block_Store::user_has_access();
list( $emcp_sb_bl_active, $emcp_sb_bl_draft ) = $emcp_sb_counts(
	$emcp_sb_blocks_ok ? EMCP_Tools_Block_Store::instance()->list_blocks( 'any' ) : array()
);

// ----- Widgets (Pro) -----
$emcp_sb_widgets_ok = class_exists( 'EMCP_Tools_Widget_Store' ) && EMCP_Tools_Widget_Store::user_has_access();
list( $emcp_sb_wd_active, $emcp_sb_wd_draft ) = $emcp_sb_counts(
	$emcp_sb_widgets_ok ? EMCP_Tools_Widget_Store::list_widgets( 'any' ) : array()
);

// ----- PHP Snippets (Free, capability-gated) -----
$emcp_sb_snip_ok = class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) && EMCP_Tools_PHP_Snippet_Store::can_edit();
list( $emcp_sb_sn_active, $emcp_sb_sn_draft ) = $emcp_sb_counts(
	class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) ? EMCP_Tools_PHP_Snippet_Store::list_snippets( 'any' ) : array()
);

$emcp_sb_cards = array(
	array(
		'view'        => 'blocks',
		'label'       => __( 'Blocks', 'emcp-tools' ),
		'badge'       => 'pro',
		'badge_label' => __( 'PRO', 'emcp-tools' ),
		'icon'        => 'dashicons-block-default',
		'ico_mod'     => 'usage',
		'desc'        => __( 'AI-generated custom Gutenberg blocks, compiled from a spec and reviewed here before they go live.', 'emcp-tools' ),
		'available'   => $emcp_sb_blocks_ok,
		'active'      => $emcp_sb_bl_active,
		'draft'       => $emcp_sb_bl_draft,
		'note'        => __( 'Requires Pro', 'emcp-tools' ),
	),
	array(
		'view'        => 'widgets',
		'label'       => __( 'Widgets', 'emcp-tools' ),
		'badge'       => 'pro',
		'badge_label' => __( 'PRO', 'emcp-tools' ),
		'icon'        => 'dashicons-screenoptions',
		'ico_mod'     => 'tools',
		'desc'        => __( 'AI-generated custom Elementor widgets, sandboxed and shown in the panel under "Custom (EMCP)".', 'emcp-tools' ),
		'available'   => $emcp_sb_widgets_ok,
		'active'      => $emcp_sb_wd_active,
		'draft'       => $emcp_sb_wd_draft,
		'note'        => __( 'Requires Pro', 'emcp-tools' ),
	),
	array(
		'view'        => 'snippets',
		'label'       => __( 'PHP Snippets', 'emcp-tools' ),
		'badge'       => 'free',
		'badge_label' => __( 'FREE', 'emcp-tools' ),
		'icon'        => 'dashicons-editor-code',
		'ico_mod'     => 'sandbox',
		'desc'        => __( 'Small PHP snippets an AI agent can draft, run as a shortcode or on a hook, that stay inactive until you review and activate them.', 'emcp-tools' ),
		'available'   => $emcp_sb_snip_ok,
		'active'      => $emcp_sb_sn_active,
		'draft'       => $emcp_sb_sn_draft,
		'note'        => __( 'Needs permission', 'emcp-tools' ),
	),
);

// Export as plugin (Pro module, shown only while it is switched on).
if ( class_exists( 'EMCP_Tools_Plugin_Export_Module' ) && EMCP_Tools_Plugin_Export_Module::is_enabled() ) {
	$emcp_sb_cards[] = array(
		'view'        => 'export',
		'label'       => __( 'Export as plugin', 'emcp-tools' ),
		'badge'       => 'pro',
		'badge_label' => __( 'PRO', 'emcp-tools' ),
		'icon'        => 'dashicons-download',
		'ico_mod'     => 'usage',
		'desc'        => __( 'Package every active widget, block and snippet as a standalone plugin that keeps working after EMCP Tools is removed.', 'emcp-tools' ),
		'available'   => true,
		'active'      => $emcp_sb_bl_active + $emcp_sb_wd_active + $emcp_sb_sn_active,
		'draft'       => $emcp_sb_bl_draft + $emcp_sb_wd_draft + $emcp_sb_sn_draft,
		'note'        => '',
	);
}
?>

<div class="emcp-sandbox-overview">
	<h2><?php esc_html_e( 'Sandbox', 'emcp-tools' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Code your AI agent generated through the MCP tools lives here, isolated under wp-content/emcp-sandbox, never in your theme, core, or other plugins.', 'emcp-tools' ); ?>
	</p>

	<div class="emcp-sandbox-cards">
		<?php foreach ( $emcp_sb_cards as $emcp_c ) : ?>
			<a class="emcp-sb-card" href="<?php echo esc_url( $emcp_sb_base_url . '&view=' . $emcp_c['view'] ); ?>">
				<span class="emcp-sb-card-top">
					<span class="emcp-sb-card-ico emcp-dash-ucard-ico--<?php echo esc_attr( $emcp_c['ico_mod'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $emcp_c['icon'] ); ?>" aria-hidden="true"></span>
					</span>
					<span class="emcp-sb-card-titles">
						<span class="emcp-sb-card-title"><?php echo esc_html( $emcp_c['label'] ); ?></span>
						<span class="elementor-mcp-badge elementor-mcp-badge--<?php echo esc_attr( $emcp_c['badge'] ); ?>"><?php echo esc_html( $emcp_c['badge_label'] ); ?></span>
					</span>
					<span class="emcp-sb-card-arrow" aria-hidden="true">&rarr;</span>
				</span>

				<span class="emcp-sb-card-desc"><?php echo esc_html( $emcp_c['desc'] ); ?></span>

				<span class="emcp-sb-card-meta">
					<?php if ( $emcp_c['available'] ) : ?>
						<span class="emcp-sb-card-meta-active"><?php echo esc_html( number_format_i18n( $emcp_c['active'] ) ); ?></span>
						<?php esc_html_e( 'active', 'emcp-tools' ); ?>
						<span class="emcp-sb-card-dot">&middot;</span>
						<span class="emcp-sb-card-meta-draft"><?php echo esc_html( number_format_i18n( $emcp_c['draft'] ) ); ?></span>
						<?php esc_html_e( 'drafts', 'emcp-tools' ); ?>
					<?php else : ?>
						<span class="emcp-sb-card-locked">
							<span class="dashicons dashicons-lock" aria-hidden="true"></span>
							<?php echo esc_html( $emcp_c['note'] ); ?>
						</span>
					<?php endif; ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</div>
