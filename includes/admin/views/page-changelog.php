<?php
/**
 * Changelog tab view for the MCP Tools for Elementor admin settings page.
 *
 * Reads CHANGELOG.md and displays version entries as styled cards.
 *
 * @package EMCP_Tools
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_tools_changelog_file = EMCP_TOOLS_DIR . 'CHANGELOG.md';

if ( ! file_exists( $emcp_tools_changelog_file ) ) {
	echo '<p>' . esc_html__( 'Changelog file not found.', 'emcp-tools' ) . '</p>';
	return;
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
$emcp_tools_changelog_raw = file_get_contents( $emcp_tools_changelog_file );

/**
 * Parse the markdown changelog into version blocks.
 * Each block: version string + array of change lines.
 */
$emcp_tools_versions      = array();
$emcp_tools_current_ver   = null;
$emcp_tools_current_items = array();

foreach ( explode( "\n", $emcp_tools_changelog_raw ) as $emcp_tools_line ) {
	// Match version headers: ## [x.x.x]
	if ( preg_match( '/^## \[([^\]]+)\]/', $emcp_tools_line, $emcp_tools_matches ) ) {
		// Save previous version block.
		if ( null !== $emcp_tools_current_ver ) {
			$emcp_tools_versions[] = array(
				'version' => $emcp_tools_current_ver,
				'items'   => $emcp_tools_current_items,
			);
		}
		$emcp_tools_current_ver   = $emcp_tools_matches[1];
		$emcp_tools_current_items = array();
		continue;
	}

	// Match list items: - text
	if ( preg_match( '/^- (.+)/', $emcp_tools_line, $emcp_tools_matches ) ) {
		$emcp_tools_current_items[] = $emcp_tools_matches[1];
	}
}

// Save last version block.
if ( null !== $emcp_tools_current_ver ) {
	$emcp_tools_versions[] = array(
		'version' => $emcp_tools_current_ver,
		'items'   => $emcp_tools_current_items,
	);
}
?>

<div class="elementor-mcp-changelog">

	<div class="elementor-mcp-changelog-intro">
		<h2><?php esc_html_e( 'Changelog', 'emcp-tools' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Version history for MCP Tools for Elementor. See what changed in each release.', 'emcp-tools' ); ?>
		</p>
	</div>

	<div class="elementor-mcp-changelog-list">
		<?php foreach ( $emcp_tools_versions as $emcp_tools_entry ) : ?>
			<div class="elementor-mcp-changelog-version <?php echo ( $emcp_tools_entry === reset( $emcp_tools_versions ) ) ? 'is-latest' : ''; ?>">
				<div class="elementor-mcp-changelog-version-header">
					<h3>
						<?php
						/* translators: %s: version number */
						printf( esc_html__( 'Version %s', 'emcp-tools' ), esc_html( $emcp_tools_entry['version'] ) );
						?>
					</h3>
					<?php if ( $emcp_tools_entry === reset( $emcp_tools_versions ) ) : ?>
						<span class="elementor-mcp-changelog-badge"><?php esc_html_e( 'Latest', 'emcp-tools' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $emcp_tools_entry['items'] ) ) : ?>
					<ul class="elementor-mcp-changelog-items">
						<?php foreach ( $emcp_tools_entry['items'] as $emcp_tools_item ) : ?>
							<li>
								<?php
								$emcp_tools_escaped = esc_html( $emcp_tools_item );
								// Highlight prefixes: New:, Fix:, Improved:
								$emcp_tools_escaped = preg_replace(
									'/^(New:|Fix:|Improved:|Total)/',
									'<strong>$1</strong>',
									$emcp_tools_escaped
								);
								// Highlight inline code: `text`
								$emcp_tools_escaped = preg_replace(
									'/`([^`]+)`/',
									'<code>$1</code>',
									$emcp_tools_escaped
								);
								echo wp_kses( $emcp_tools_escaped, array(
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
