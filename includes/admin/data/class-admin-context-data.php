<?php
/**
 * Context screen data (spec 8.7).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Context_Data {

	/** A fixed guide for start-of-session context, never enforced (spec 9.4). */
	const BUDGET = 8000;

	public static function tokens( string $text ): int {
		return (int) ceil( strlen( $text ) / 4 );
	}

	private static function sections( bool $refresh = false ): array {
		$defs      = EMCP_Tools_Context_Sections::definitions();
		$on        = EMCP_Tools_Context_Sections::enabled();
		$summaries = EMCP_Tools_Context_Sections::summaries( $refresh );
		$out       = array();
		foreach ( $summaries as $id => $summary ) {
			$out[] = array(
				'id'      => $id,
				'label'   => $defs[ $id ]['label'],
				'icon'    => $defs[ $id ]['icon'],
				'summary' => $summary,
				'enabled' => $on[ $id ],
			);
		}
		return $out;
	}

	public function payload( bool $refresh = false ): array {
		$sections = self::sections( $refresh );
		$text     = EMCP_Tools_Site_Context::server_instructions();
		return array(
			'profile'      => EMCP_Tools_Context_Sections::profile(),
			'industries'   => EMCP_Tools_Context_Sections::industries(),
			'voices'       => EMCP_Tools_Context_Sections::voices(),
			'sections'     => $sections,
			'refreshedAt'  => (int) ( EMCP_Tools_Context_Sections::detected()['refreshedAt'] ?? 0 ),
			'instructions' => EMCP_Tools_Site_Context::get_context(),
			'enabled'      => EMCP_Tools_Site_Context::is_enabled(),
			'maxChars'     => EMCP_Tools_Site_Context::MAX_CHARS,
			'preview'      => $text,
			'tokens'       => self::tokens( $text ),
			'budget'       => self::BUDGET,
			'memoryUrl'    => admin_url( 'admin.php?page=emcp-tools-memory' ),
		);
	}

	/**
	 * Apply a diff and return the fresh payload.
	 *
	 * @param array $diff { profile?, sections?, instructions?, enabled? }.
	 */
	public function save( array $diff ): array {
		if ( array_key_exists( 'profile', $diff ) ) {
			update_option( EMCP_Tools_Context_Sections::PROFILE_OPTION, EMCP_Tools_Context_Sections::sanitize_profile( $diff['profile'] ), false );
		}
		if ( isset( $diff['sections'] ) && is_array( $diff['sections'] ) ) {
			$stored = get_option( EMCP_Tools_Context_Sections::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();
			$known  = EMCP_Tools_Context_Sections::definitions();
			foreach ( $diff['sections'] as $id => $on ) {
				if ( isset( $known[ $id ] ) ) {
					$stored[ $id ] = rest_sanitize_boolean( $on );
				}
			}
			update_option( EMCP_Tools_Context_Sections::OPTION, $stored, false );
		}
		if ( array_key_exists( 'instructions', $diff ) ) {
			update_option( EMCP_Tools_Site_Context::OPTION_CONTEXT, self::clip( $diff['instructions'] ) );
		}
		if ( array_key_exists( 'enabled', $diff ) ) {
			update_option( EMCP_Tools_Site_Context::OPTION_ENABLED, rest_sanitize_boolean( $diff['enabled'] ) ? '1' : '0' );
		}
		return $this->payload();
	}

	/**
	 * The exact server instructions for an unsaved draft. Writes nothing.
	 *
	 * @param array $draft { profile?, sections?, instructions?, enabled? }.
	 * @return array{text:string, tokens:int, budget:int}
	 */
	public function preview( array $draft ): array {
		$clean = array();
		if ( array_key_exists( 'profile', $draft ) ) {
			$clean['profile'] = EMCP_Tools_Context_Sections::sanitize_profile( $draft['profile'] );
		}
		if ( isset( $draft['sections'] ) && is_array( $draft['sections'] ) ) {
			$known = EMCP_Tools_Context_Sections::definitions();
			$map   = EMCP_Tools_Context_Sections::enabled();
			foreach ( $draft['sections'] as $id => $on ) {
				if ( isset( $known[ $id ] ) ) {
					$map[ $id ] = rest_sanitize_boolean( $on );
				}
			}
			$clean['sections'] = $map;
		}
		if ( array_key_exists( 'instructions', $draft ) ) {
			$clean['instructions'] = self::clip( $draft['instructions'] );
		}
		if ( array_key_exists( 'enabled', $draft ) ) {
			$clean['enabled'] = rest_sanitize_boolean( $draft['enabled'] );
		}
		$text = EMCP_Tools_Site_Context::server_instructions( $clean );
		return array( 'text' => $text, 'tokens' => self::tokens( $text ), 'budget' => self::BUDGET );
	}

	/** @param mixed $text */
	private static function clip( $text ): string {
		return mb_substr( sanitize_textarea_field( (string) $text ), 0, EMCP_Tools_Site_Context::MAX_CHARS );
	}
}
