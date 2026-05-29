<?php
/**
 * Skills tab view.
 *
 * Pro users (with skills folder bundled): download button + per-client setup guide.
 * Pro users on a build without the skills folder: graceful "not bundled" notice.
 * Free users: upgrade CTA.
 *
 * @package Elementor_MCP
 * @since   1.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$elementor_mcp_has_pro   = class_exists( 'Elementor_MCP_Pro_Skills' ) && Elementor_MCP_Pro_Skills::user_has_access();
$elementor_mcp_pro_only  = function_exists( 'emcp_pro_fs' ) && emcp_pro_fs()->can_use_premium_code();
$elementor_mcp_skills_dir_missing = $elementor_mcp_pro_only && ! Elementor_MCP_Pro_Skills::skills_dir_exists();
$elementor_mcp_download_url = $elementor_mcp_has_pro ? Elementor_MCP_Pro_Skills::download_url() : '';

$elementor_mcp_upgrade_url = function_exists( 'elementor_mcp_upgrade_url' )
	? elementor_mcp_upgrade_url()
	: 'https://emcp.msrbuilds.com/pricing';

/*
 * Bundled industry vertical packs. Read the labels straight from the shipped
 * verticals/ folder so this list never drifts from what's actually in the zip.
 * Only present on premium builds (the folder ships only there).
 */
$elementor_mcp_verticals = array();
if ( $elementor_mcp_has_pro && defined( 'ELEMENTOR_MCP_DIR' ) ) {
	$elementor_mcp_verticals_dir = ELEMENTOR_MCP_DIR . 'skills/emcp-skills/verticals';
	if ( is_dir( $elementor_mcp_verticals_dir ) ) {
		foreach ( (array) glob( $elementor_mcp_verticals_dir . '/*.md' ) as $elementor_mcp_vfile ) {
			if ( 'readme.md' === strtolower( basename( $elementor_mcp_vfile ) ) ) {
				continue;
			}
			$elementor_mcp_label = '';
			$elementor_mcp_lines  = (array) @file( $elementor_mcp_vfile, FILE_IGNORE_NEW_LINES ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			foreach ( array_slice( $elementor_mcp_lines, 0, 20 ) as $elementor_mcp_line ) {
				if ( preg_match( '/^label:\s*(.+?)\s*$/', $elementor_mcp_line, $elementor_mcp_m ) ) {
					$elementor_mcp_label = $elementor_mcp_m[1];
					break;
				}
			}
			if ( '' === $elementor_mcp_label ) {
				$elementor_mcp_label = ucwords( str_replace( '-', ' ', basename( $elementor_mcp_vfile, '.md' ) ) );
			}
			$elementor_mcp_verticals[] = $elementor_mcp_label;
		}
		sort( $elementor_mcp_verticals );
	}
}
?>

<div class="elementor-mcp-skills">

	<div class="elementor-mcp-pro-prompts-header">
		<div class="elementor-mcp-pro-prompts-heading">
			<h2>
				<?php esc_html_e( 'EMCP Skill for AI Agents', 'elementor-mcp' ); ?>
				<span class="elementor-mcp-badge elementor-mcp-badge--pro">PRO</span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'A pre-written Agent Skill that teaches Claude (and any compatible AI client) exactly how to build, edit, and style Elementor pages through the MCP tools — now with industry skill packs that tailor the build to the site\'s trade. Install once per machine — every future session that loads this skill knows your workflow.', 'elementor-mcp' ); ?>
			</p>
		</div>
		<?php if ( $elementor_mcp_has_pro ) : ?>
			<a class="elementor-mcp-skills-download" href="<?php echo esc_url( $elementor_mcp_download_url ); ?>">
				<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
					<path fill="currentColor" d="M10 3a1 1 0 011 1v7.59l2.3-2.3a1 1 0 111.4 1.42l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.42L9 11.6V4a1 1 0 011-1zM4 15a1 1 0 011 1v1h10v-1a1 1 0 112 0v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2a1 1 0 011-1z"/>
				</svg>
				<?php esc_html_e( 'Download emcp-skills.zip', 'elementor-mcp' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( $elementor_mcp_has_pro ) : ?>

		<div class="elementor-mcp-coming-soon" role="status">
			<span class="elementor-mcp-coming-soon__icon" aria-hidden="true">
				<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-12.75a.75.75 0 00-1.5 0v5.5a.75.75 0 00.34.628l3.75 2.5a.75.75 0 10.82-1.255L10.75 10.4V5.25z"/></svg>
			</span>
			<div class="elementor-mcp-coming-soon__text">
				<strong><?php esc_html_e( 'Quick install:', 'elementor-mcp' ); ?></strong>
				<?php esc_html_e( 'Click the download button above, then follow the guide for your AI client below. The skill folder goes into your client\'s skills/rules directory — paths listed per platform.', 'elementor-mcp' ); ?>
			</div>
		</div>

		<?php if ( ! empty( $elementor_mcp_verticals ) ) : ?>
			<div class="elementor-mcp-skills-packs">
				<h3 class="elementor-mcp-skills-packs__title">
					<?php
					/* translators: %d: number of bundled industry packs. */
					echo esc_html( sprintf( _n( 'Includes %d industry skill pack', 'Includes %d industry skill packs', count( $elementor_mcp_verticals ), 'elementor-mcp' ), count( $elementor_mcp_verticals ) ) );
					?>
					<span class="elementor-mcp-badge elementor-mcp-badge--pro">NEW</span>
				</h3>
				<p class="description">
					<?php esc_html_e( 'When your AI agent recognizes the site\'s industry, it reads the matching pack before building — applying that trade\'s brand voice, SEO keywords, page structure, conversion patterns, and compliance notes, plus the exact Brand Kit + prompt + template combo to use. Nothing to configure: just tell your client what kind of site you\'re building (e.g. "build a dental clinic site") and the skill routes itself.', 'elementor-mcp' ); ?>
				</p>
				<ul class="elementor-mcp-skills-packs__grid">
					<?php foreach ( $elementor_mcp_verticals as $elementor_mcp_v ) : ?>
						<li class="elementor-mcp-skills-pack"><?php echo esc_html( $elementor_mcp_v ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="elementor-mcp-skills-guides">

			<!-- Claude Code -->
			<details class="elementor-mcp-skills-guide" open>
				<summary>
					<strong><?php esc_html_e( 'Claude Code (terminal)', 'elementor-mcp' ); ?></strong>
					<span class="elementor-mcp-skills-guide__hint"><?php esc_html_e( 'Native skills support', 'elementor-mcp' ); ?></span>
				</summary>
				<div class="elementor-mcp-skills-guide__body">
					<p><?php esc_html_e( 'Claude Code reads skills from two locations — project-scoped or user-global. Use whichever fits your workflow:', 'elementor-mcp' ); ?></p>
					<ol>
						<li>
							<?php esc_html_e( 'Extract emcp-skills.zip — you\'ll get a folder named "emcp-skills/".', 'elementor-mcp' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Move the folder to one of these locations:', 'elementor-mcp' ); ?>
							<ul>
								<li><strong><?php esc_html_e( 'Per-project (recommended):', 'elementor-mcp' ); ?></strong> <code>&lt;project-root&gt;/.claude/skills/emcp-skills/</code></li>
								<li><strong><?php esc_html_e( 'Global (all projects):', 'elementor-mcp' ); ?></strong>
									<ul>
										<li>macOS / Linux: <code>~/.claude/skills/emcp-skills/</code></li>
										<li>Windows: <code>%USERPROFILE%\.claude\skills\emcp-skills\</code></li>
									</ul>
								</li>
							</ul>
						</li>
						<li><?php esc_html_e( 'Restart Claude Code. The skill activates automatically when you start any session that has the EMCP Tools MCP server connected.', 'elementor-mcp' ); ?></li>
					</ol>
				</div>
			</details>

			<!-- Claude Desktop -->
			<details class="elementor-mcp-skills-guide">
				<summary>
					<strong><?php esc_html_e( 'Claude Desktop', 'elementor-mcp' ); ?></strong>
					<span class="elementor-mcp-skills-guide__hint"><?php esc_html_e( 'Skills folder support', 'elementor-mcp' ); ?></span>
				</summary>
				<div class="elementor-mcp-skills-guide__body">
					<p><?php esc_html_e( 'Drop the folder into Claude Desktop\'s skills directory:', 'elementor-mcp' ); ?></p>
					<ul>
						<li><strong>macOS:</strong> <code>~/Library/Application Support/Claude/Skills/emcp-skills/</code></li>
						<li><strong>Windows:</strong> <code>%APPDATA%\Claude\Skills\emcp-skills\</code></li>
						<li><strong>Linux:</strong> <code>~/.config/Claude/Skills/emcp-skills/</code></li>
					</ul>
					<p><?php esc_html_e( 'Restart Claude Desktop. New conversations connected to the EMCP MCP server will pick up the skill.', 'elementor-mcp' ); ?></p>
				</div>
			</details>

			<!-- Cursor -->
			<details class="elementor-mcp-skills-guide">
				<summary>
					<strong><?php esc_html_e( 'Cursor', 'elementor-mcp' ); ?></strong>
					<span class="elementor-mcp-skills-guide__hint"><?php esc_html_e( 'Adapt as project rules', 'elementor-mcp' ); ?></span>
				</summary>
				<div class="elementor-mcp-skills-guide__body">
					<p><?php esc_html_e( 'Cursor uses Project Rules rather than skills folders. Wire the EMCP skill in via:', 'elementor-mcp' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Extract emcp-skills.zip on your machine.', 'elementor-mcp' ); ?></li>
						<li><?php esc_html_e( 'Copy emcp-skills/ to your project at:', 'elementor-mcp' ); ?> <code>&lt;project-root&gt;/.cursor/rules/emcp-skills/</code></li>
						<li><?php esc_html_e( 'Open Cursor → Settings → Rules → Project Rules. Add a new rule referencing the SKILL.md file. Set the rule type to "Agent Requested" so it loads when your agent uses MCP tools.', 'elementor-mcp' ); ?></li>
					</ol>
				</div>
			</details>

			<!-- Windsurf -->
			<details class="elementor-mcp-skills-guide">
				<summary>
					<strong><?php esc_html_e( 'Windsurf', 'elementor-mcp' ); ?></strong>
					<span class="elementor-mcp-skills-guide__hint"><?php esc_html_e( 'Cascade rules', 'elementor-mcp' ); ?></span>
				</summary>
				<div class="elementor-mcp-skills-guide__body">
					<p><?php esc_html_e( 'Windsurf\'s Cascade supports custom rules per workspace:', 'elementor-mcp' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Extract emcp-skills.zip.', 'elementor-mcp' ); ?></li>
						<li><?php esc_html_e( 'Copy the folder to your workspace at:', 'elementor-mcp' ); ?> <code>&lt;workspace&gt;/.windsurf/rules/emcp-skills/</code></li>
						<li><?php esc_html_e( 'In Windsurf, open Cascade → Customize → Rules. Enable the new rule and set it to load when MCP tools matching elementor-mcp-* are present.', 'elementor-mcp' ); ?></li>
					</ol>
				</div>
			</details>

			<!-- Antigravity -->
			<details class="elementor-mcp-skills-guide">
				<summary>
					<strong><?php esc_html_e( 'Antigravity', 'elementor-mcp' ); ?></strong>
					<span class="elementor-mcp-skills-guide__hint"><?php esc_html_e( 'Knowledge Manager', 'elementor-mcp' ); ?></span>
				</summary>
				<div class="elementor-mcp-skills-guide__body">
					<p><?php esc_html_e( 'Antigravity surfaces custom knowledge through its Knowledge Manager:', 'elementor-mcp' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Extract emcp-skills.zip and locate the SKILL.md file inside.', 'elementor-mcp' ); ?></li>
						<li><?php esc_html_e( 'In Antigravity, open Knowledge Manager and create a new knowledge source. Point it at the extracted emcp-skills folder, or paste the SKILL.md contents directly.', 'elementor-mcp' ); ?></li>
						<li><?php esc_html_e( 'Tag the knowledge with "elementor-mcp" so it auto-loads on sessions where the EMCP MCP server is connected.', 'elementor-mcp' ); ?></li>
					</ol>
				</div>
			</details>

			<!-- Any other client -->
			<details class="elementor-mcp-skills-guide">
				<summary>
					<strong><?php esc_html_e( 'Any other MCP client', 'elementor-mcp' ); ?></strong>
					<span class="elementor-mcp-skills-guide__hint"><?php esc_html_e( 'Universal fallback', 'elementor-mcp' ); ?></span>
				</summary>
				<div class="elementor-mcp-skills-guide__body">
					<p><?php esc_html_e( 'If your client doesn\'t support skills natively:', 'elementor-mcp' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Extract emcp-skills.zip.', 'elementor-mcp' ); ?></li>
						<li><?php esc_html_e( 'Open SKILL.md in any text editor.', 'elementor-mcp' ); ?></li>
						<li><?php esc_html_e( 'Copy the entire contents and paste it into your client\'s "system prompt", "custom instructions", or equivalent persistent-context input. The skill is plain markdown — any LLM can read it directly.', 'elementor-mcp' ); ?></li>
					</ol>
				</div>
			</details>

		</div>

	<?php elseif ( $elementor_mcp_skills_dir_missing ) : ?>

		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Skills are not bundled in this build. If you\'re running the premium plugin and seeing this message, try re-downloading the latest premium zip from your Freemius account.', 'elementor-mcp' ); ?></p>
		</div>

	<?php else : ?>

		<div class="elementor-mcp-prompts-cta">
			<div class="elementor-mcp-prompts-cta-content">
				<h3><?php esc_html_e( 'Unlock the EMCP Agent Skill', 'elementor-mcp' ); ?></h3>
				<p><?php esc_html_e( 'Pre-written Agent Skill that teaches Claude and other compatible AI clients exactly how to build Elementor pages through the MCP tools — design rules, kit-first workflow, responsive controls, the full review loop. One install per machine.', 'elementor-mcp' ); ?></p>
				<a href="<?php echo esc_url( $elementor_mcp_upgrade_url ); ?>" class="button button-primary elementor-mcp-prompts-cta-btn" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 20 20" width="16" height="16" xmlns="http://www.w3.org/2000/svg"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
					<?php esc_html_e( 'Upgrade to Pro', 'elementor-mcp' ); ?>
				</a>
			</div>
		</div>

	<?php endif; ?>

</div>
