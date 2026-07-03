<?php
/**
 * Security & Malware Scanner MCP ability (read-only).
 *
 * One tool — scan-security — that scans for malware, core-integrity, hardening,
 * and outdated-software issues and returns a scored report. Gated on
 * `manage_options`; enabled by default. Independent of Elementor version (it
 * audits the filesystem/WP/config, not Elementor internals), so it registers
 * unconditionally — the capability check is the guard.
 *
 * Ported from upstream msrbuilds/elementor-mcp (v3.0.0), adapted to this fork's
 * class/helper naming (the upstream rename to emcp-tools is not adopted),
 * including the sibling-prefix walk-confinement, regex, and false-positive
 * hardening fixes (the snippet-sandbox self-exclusion is dropped — the fork
 * ships no sandbox).
 *
 * @package Elementor_MCP
 * @since   1.12.0
 * @link    https://github.com/msrbuilds/elementor-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the Security & Malware Scanner ability.
 *
 * @since 1.12.0
 */
class Elementor_MCP_Security_Abilities {

	/** @var string[] */
	private $ability_names = array();

	/** @var Elementor_MCP_Security_Scanner */
	private $scanner;

	/**
	 * @param Elementor_MCP_Security_Scanner|null $scanner Optional scanner (injectable for tests).
	 */
	public function __construct( ?Elementor_MCP_Security_Scanner $scanner = null ) {
		$this->scanner = $scanner ?: new Elementor_MCP_Security_Scanner();
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers the Security & Malware Scanner abilities.
	 */
	public function register(): void {
		$this->register_scan_security();
	}

	/**
	 * Permission check — administrators only.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function register_scan_security(): void {
		$this->ability_names[] = 'elementor-mcp/scan-security';
		elementor_mcp_register_ability(
			'elementor-mcp/scan-security',
			array(
				'label'               => __( 'Scan Security', 'elementor-mcp' ),
				'description'         => __( 'Scans this WordPress site for security and malware problems across four areas: PHP malware heuristics (uploads + active plugins/themes; pass deep=true for the whole tree), WordPress core file integrity (vs official wordpress.org checksums), configuration hardening (file editor, debug output, admin username, XML-RPC, version disclosure, HTTPS, security headers), and outdated/abandoned software. Returns a scored report (0-100 + A-F grade) with severities and ranked, actionable recommendations. Read-only; self-contained; scans this site only.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_scan_security' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'checks'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => array( 'malware', 'integrity', 'hardening', 'software' ) ),
							'description' => __( 'Subset of audits to run. Omit to run all four.', 'elementor-mcp' ),
						),
						'deep'        => array( 'type' => 'boolean', 'description' => __( 'When true, the malware scan covers ALL plugins/themes and the wider tree (slower). Default false: uploads + active plugins/themes only.', 'elementor-mcp' ) ),
						'max_files'   => array( 'type' => 'integer', 'description' => __( 'Override the malware file-count cap (default 2000, ceiling 20000).', 'elementor-mcp' ) ),
						'max_seconds' => array( 'type' => 'integer', 'description' => __( 'Override the malware scan time budget in seconds (default 20, ceiling 120).', 'elementor-mcp' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'summary'             => array( 'type' => 'object' ),
						'sections'            => array( 'type' => 'object' ),
						'scan_meta'           => array( 'type' => 'object' ),
						'top_recommendations' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_scan_security( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		return $this->scanner->scan( $input );
	}
}
