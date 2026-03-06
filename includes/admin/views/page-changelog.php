<?php
/**
 * Changelog tab view for the MCP Tools for Elementor admin settings page.
 *
 * Reads CHANGELOG.md and displays version entries as styled cards.
 *
 * @package Elementor_MCP
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$elementor_mcp_changelog_file = ELEMENTOR_MCP_DIR . 'CHANGELOG.md';

if ( ! file_exists( $elementor_mcp_changelog_file ) ) {
	echo '<p>' . esc_html__( 'Changelog file not found.', 'elementor-mcp' ) . '</p>';
	return;
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
$elementor_mcp_changelog_raw = file_get_contents( $elementor_mcp_changelog_file );

/**
 * Parse the markdown changelog into version blocks.
 * Each block: version string + array of change lines.
 */
$elementor_mcp_versions      = array();
$elementor_mcp_current_ver   = null;
$elementor_mcp_current_items = array();

foreach ( explode( "\n", $elementor_mcp_changelog_raw ) as $elementor_mcp_line ) {
	// Match version headers: ## [x.x.x]
	if ( preg_match( '/^## \[([^\]]+)\]/', $elementor_mcp_line, $elementor_mcp_matches ) ) {
		// Save previous version block.
		if ( null !== $elementor_mcp_current_ver ) {
			$elementor_mcp_versions[] = array(
				'version' => $elementor_mcp_current_ver,
				'items'   => $elementor_mcp_current_items,
			);
		}
		$elementor_mcp_current_ver   = $elementor_mcp_matches[1];
		$elementor_mcp_current_items = array();
		continue;
	}

	// Match list items: - text
	if ( preg_match( '/^- (.+)/', $elementor_mcp_line, $elementor_mcp_matches ) ) {
		$elementor_mcp_current_items[] = $elementor_mcp_matches[1];
	}
}

// Save last version block.
if ( null !== $elementor_mcp_current_ver ) {
	$elementor_mcp_versions[] = array(
		'version' => $elementor_mcp_current_ver,
		'items'   => $elementor_mcp_current_items,
	);
}
?>

<div class="elementor-mcp-changelog">

	<div class="elementor-mcp-changelog-intro">
		<h2><?php esc_html_e( 'Changelog', 'elementor-mcp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Version history for MCP Tools for Elementor. See what changed in each release.', 'elementor-mcp' ); ?>
		</p>
	</div>

	<div class="elementor-mcp-changelog-list">
		<?php foreach ( $elementor_mcp_versions as $elementor_mcp_entry ) : ?>
			<div class="elementor-mcp-changelog-version <?php echo ( $elementor_mcp_entry === reset( $elementor_mcp_versions ) ) ? 'is-latest' : ''; ?>">
				<div class="elementor-mcp-changelog-version-header">
					<h3>
						<?php
						/* translators: %s: version number */
						printf( esc_html__( 'Version %s', 'elementor-mcp' ), esc_html( $elementor_mcp_entry['version'] ) );
						?>
					</h3>
					<?php if ( $elementor_mcp_entry === reset( $elementor_mcp_versions ) ) : ?>
						<span class="elementor-mcp-changelog-badge"><?php esc_html_e( 'Latest', 'elementor-mcp' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $elementor_mcp_entry['items'] ) ) : ?>
					<ul class="elementor-mcp-changelog-items">
						<?php foreach ( $elementor_mcp_entry['items'] as $elementor_mcp_item ) : ?>
							<li>
								<?php
								$elementor_mcp_escaped = esc_html( $elementor_mcp_item );
								// Highlight prefixes: New:, Fix:, Improved:
								$elementor_mcp_escaped = preg_replace(
									'/^(New:|Fix:|Improved:|Total)/',
									'<strong>$1</strong>',
									$elementor_mcp_escaped
								);
								// Highlight inline code: `text`
								$elementor_mcp_escaped = preg_replace(
									'/`([^`]+)`/',
									'<code>$1</code>',
									$elementor_mcp_escaped
								);
								echo wp_kses( $elementor_mcp_escaped, array(
									'strong' => array(),
									'code'   => array(),
								) );
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

</div>
