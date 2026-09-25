<?php
/**
 * Feature cards for the locked-Pro screen (spec 8.25). Static text only, so
 * the free tree carries no Pro code.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locked-Pro screen data.
 */
final class EMCP_Tools_Admin_Locked {

	/**
	 * Register the `locked` React screen.
	 */
	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'locked',
			array(
				'script' => 'screen-locked',
				'boot'   => static function ( array $context ): array {
					return self::feature( (string) ( $context['tab'] ?? '' ) );
				},
			)
		);
	}

	/**
	 * The feature card for a Pro-only tab.
	 *
	 * @param string $tab Tab id.
	 * @return array{title:string, description:string, bullets:string[], upgrade_url:string, compare_url:string}
	 */
	public static function feature( string $tab ): array {
		$cards = array(
			'ai-chat' => array(
				'title'       => __( 'AI Chat', 'emcp-tools' ),
				'description' => __( 'Chat with any AI model inside WordPress and the page editors to build and edit pages.', 'emcp-tools' ),
				'bullets'     => array(
					__( 'Describe a page and watch it being built', 'emcp-tools' ),
					__( 'Edit the page you are on from the Elementor or block editor', 'emcp-tools' ),
					__( 'Bring your own API key from Anthropic, OpenAI, OpenRouter or Gemini', 'emcp-tools' ),
				),
			),
			'skills'  => array(
				'title'       => __( 'Skills', 'emcp-tools' ),
				'description' => __( 'Curated playbooks that teach connected agents how to work on your site.', 'emcp-tools' ),
				'bullets'     => array(
					__( 'Agents load the right playbook for the task at runtime', 'emcp-tools' ),
					__( 'Install the skills in Claude Desktop or Claude Code', 'emcp-tools' ),
					__( 'Playbooks for every supported builder and theme', 'emcp-tools' ),
				),
			),
			'memory'  => array(
				'title'       => __( 'Project Memory', 'emcp-tools' ),
				'description' => __( 'Remember your site across agent sessions so agents stop guessing.', 'emcp-tools' ),
				'bullets'     => array(
					__( 'Guardrails, site facts and conventions you approve', 'emcp-tools' ),
					__( 'Agents propose memory; nothing is used until you approve it', 'emcp-tools' ),
					__( 'Session summaries you can review', 'emcp-tools' ),
				),
			),
			'migrate' => array(
				'title'       => __( 'Backup & Migrate', 'emcp-tools' ),
				'description' => __( 'Back up the whole site, restore it, and move it to another server.', 'emcp-tools' ),
				'bullets'     => array(
					__( 'Full, database, files or content backups', 'emcp-tools' ),
					__( 'Push a local site to live with a small connector plugin', 'emcp-tools' ),
					__( 'One-way sync of chosen tables and folders', 'emcp-tools' ),
				),
			),
		);
		$card  = $cards[ $tab ] ?? array(
			'title'       => __( 'EMCP Pro', 'emcp-tools' ),
			'description' => __( 'This feature is part of EMCP Pro.', 'emcp-tools' ),
			'bullets'     => array(
				__( 'Every Pro tool and module', 'emcp-tools' ),
				__( 'Premium templates, prompts and brand kits', 'emcp-tools' ),
				__( 'Priority support', 'emcp-tools' ),
			),
		);
		return array_merge(
			$card,
			array(
				'upgrade_url' => function_exists( 'emcp_tools_upgrade_url' ) ? (string) emcp_tools_upgrade_url() : 'https://emcptools.com/pricing',
				'compare_url' => 'https://emcptools.com/pricing',
			)
		);
	}
}
