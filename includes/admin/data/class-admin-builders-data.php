<?php
/**
 * Page Builders screen data (spec 8.5).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Builders_Data {

	/** The screen payload. */
	public function payload(): array {
		$builders = array();
		foreach ( EMCP_Tools_Page_Builders::catalog() as $id => $builder ) {
			$builders[] = array(
				'id'          => (string) $id,
				'label'       => (string) $builder['label'],
				'description' => (string) ( $builder['description'] ?? '' ),
				'requirement' => (string) ( $builder['requirement'] ?? '' ),
				'available'   => EMCP_Tools_Page_Builders::available( $id ),
			);
		}
		$packs = array();
		foreach ( EMCP_Tools_Page_Builders::block_packs() as $id => $pack ) {
			$packs[] = array(
				'id'          => (string) $id,
				'label'       => (string) $pack['label'],
				'enabled'     => EMCP_Tools_Page_Builders::enabled( $id ),
				'available'   => EMCP_Tools_Page_Builders::available( $id ),
				'requirement' => self::pack_requirement( (string) $id, $pack ),
			);
		}
		return array(
			'selected'  => EMCP_Tools_Page_Builders::selected(),
			'gutenberg' => self::gutenberg(),
			'builders'  => $builders,
			'packs'     => $packs,
		);
	}

	/**
	 * Whether the block editor is there to build with. It always is, unless
	 * the Classic Editor plugin is active and switches it off.
	 *
	 * @return array{available:bool,reason:string}
	 */
	public static function gutenberg(): array {
		$available = (bool) apply_filters( 'emcp_tools_block_editor_available', ! class_exists( 'Classic_Editor' ) );
		return array(
			'available' => $available,
			'reason'    => $available ? '' : __( 'The Classic Editor plugin is active, so the block editor is switched off. Deactivate Classic Editor to build with Gutenberg.', 'emcp-tools' ),
		);
	}

	/**
	 * Requirement line for a block pack.
	 *
	 * @param string $id   Pack id.
	 * @param array  $pack Pack entry.
	 */
	public static function pack_requirement( string $id, array $pack ): string {
		if ( ! empty( $pack['requirement'] ) ) {
			return (string) $pack['requirement'];
		}
		return 'generateblocks' === $id
			/* translators: %s: plugin name. */
			? sprintf( __( 'Requires the active %s plugin and EMCP Pro.', 'emcp-tools' ), $pack['label'] )
			/* translators: %s: plugin name. */
			: sprintf( __( 'Requires the active %s plugin.', 'emcp-tools' ), $pack['label'] );
	}

	/**
	 * Apply a pack diff to the enabled list.
	 *
	 * @param string[] $current   Enabled packs.
	 * @param string[] $enable    Packs to enable.
	 * @param string[] $disable   Packs to disable.
	 * @param string[] $known     Every pack id.
	 * @param string[] $available Packs whose plugin is active.
	 * @return string[]
	 */
	public static function apply_packs( array $current, array $enable, array $disable, array $known, array $available ): array {
		$out = array_values( array_intersect( $current, $known ) );
		foreach ( $enable as $id ) {
			if ( in_array( $id, $available, true ) && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return array_values( array_diff( $out, array_intersect( $disable, $known ) ) );
	}
}
