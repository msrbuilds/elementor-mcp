<?php
/**
 * Contact Form 7 integration (free) — two dispatcher tools (cf7-read /
 * cf7-write) over the WPCF7_ContactForm API.
 *
 * NOTE: Operation map is completed in Task 4 (verification-first — install CF7
 * and read its real API before wiring each operation). This file currently
 * ships the class shell + plugin detection so the base + registrar wiring is in
 * place; `operations()` returns an empty map until Task 4 fills it in.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.5.0
 */
class EMCP_Tools_CF7_Integration extends EMCP_Tools_Form_Integration {

	/**
	 * @return string
	 */
	public function id(): string {
		return 'cf7';
	}

	/**
	 * @return string
	 */
	public function label(): string {
		return 'Contact Form 7';
	}

	/**
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( 'WPCF7_ContactForm' ) || defined( 'WPCF7_VERSION' );
	}

	/**
	 * Operation map. Filled in Task 4 against the verified WPCF7 API.
	 *
	 * @return array<string,array>
	 */
	protected function operations(): array {
		return array();
	}
}
