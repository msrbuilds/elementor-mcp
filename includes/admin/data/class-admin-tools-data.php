<?php
/**
 * Tools screen data (spec 8.3): the payload the React screen boots with, and
 * the diff applier its save uses.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Tools_Data {

	/** Tabs that follow the standalone builders, in this order (spec 8.3). */
	const CORE_TABS = array( 'wordpress', 'plugins', 'themes', 'gutenberg' );

	/** @var EMCP_Tools_Admin */
	private $admin;

	public function __construct( EMCP_Tools_Admin $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Risk from a tool's badges.
	 *
	 * @param string[] $badges Badges.
	 */
	public static function risk( array $badges ): string {
		if ( in_array( 'destructive', $badges, true ) ) {
			return 'destructive';
		}
		return in_array( 'read-only', $badges, true ) ? 'read-only' : 'writes';
	}

	/**
	 * Apply an enable / disable diff to the stored disabled list. Only named
	 * slugs change: tools on hidden tabs and seeded defaults are untouched.
	 *
	 * @param string[] $disabled   Stored disabled slugs.
	 * @param string[] $enable     Slugs to enable.
	 * @param string[] $disable    Slugs to disable.
	 * @param string[] $known      Every catalog slug.
	 * @param string[] $enableable Visible, available slugs.
	 * @return array{disabled: string[], ignored: string[]}
	 */
	public static function apply( array $disabled, array $enable, array $disable, array $known, array $enableable ): array {
		$ignored = array();
		foreach ( $enable as $slug ) {
			if ( in_array( $slug, $enableable, true ) ) {
				$disabled = array_values( array_diff( $disabled, array( $slug ) ) );
			} else {
				$ignored[] = $slug;
			}
		}
		foreach ( $disable as $slug ) {
			if ( in_array( $slug, $known, true ) ) {
				$disabled[] = $slug;
			} else {
				$ignored[] = $slug;
			}
		}
		return array(
			'disabled' => array_values( array_unique( $disabled ) ),
			'ignored'  => array_values( array_unique( $ignored ) ),
		);
	}

	/**
	 * Standalone builders first, then WordPress, Plugins, Themes, Gutenberg,
	 * then block packs, then EMCP Modules.
	 *
	 * @param string[] $tab_ids Tabs present.
	 * @return string[]
	 */
	public static function tab_order( array $tab_ids ): array {
		$builders = array_keys( EMCP_Tools_Page_Builders::catalog() );
		$packs    = array_keys( EMCP_Tools_Page_Builders::block_packs() );
		$rank     = static function ( string $id ) use ( $builders, $packs ): int {
			if ( in_array( $id, $builders, true ) ) {
				return 0;
			}
			$core = array_search( $id, self::CORE_TABS, true );
			if ( false !== $core ) {
				return 10 + (int) $core;
			}
			if ( in_array( $id, $packs, true ) ) {
				return 20;
			}
			return 'modules' === $id ? 30 : 25;
		};
		$ids = array_values( $tab_ids );
		usort(
			$ids,
			static function ( $a, $b ) use ( $rank, $ids ) {
				return array( $rank( $a ), array_search( $a, $ids, true ) ) <=> array( $rank( $b ), array_search( $b, $ids, true ) );
			}
		);
		return $ids;
	}

	/** The screen payload. */
	public function payload(): array {
		$licensed  = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		$elementor = class_exists( 'EMCP_Tools_Bootstrap' ) ? EMCP_Tools_Bootstrap::elementor_active() : false;
		$disabled  = (array) get_option( EMCP_Tools_Admin::OPTION_DISABLED_TOOLS, array() );
		$labels    = EMCP_Tools_Admin::platform_tabs();
		$groups    = EMCP_Tools_Admin::plugin_groups();
		$visible   = array_keys( EMCP_Tools_Admin::visible_platform_tabs() );

		$categories = array();
		$enabled    = array();
		$present    = array();
		foreach ( EMCP_Tools_Page_Builders::visible_categories( $this->admin->get_all_tools() ) as $id => $category ) {
			$platform = (string) ( $category['platform'] ?? 'elementor' );
			if ( ! isset( $labels[ $platform ] ) || ! in_array( $platform, $visible, true ) ) {
				continue;
			}
			$pro_locked         = ! empty( $category['pro'] ) && ! $licensed;
			$elementor_category = EMCP_Tools_Admin::is_elementor_category( $category );
			$tools              = array();
			foreach ( (array) ( $category['tools'] ?? array() ) as $slug => $tool ) {
				$available = ! ( $elementor_category && ! $elementor ) && false !== ( $tool['available'] ?? true ) && ! $pro_locked;
				$tools[]   = array(
					'slug'            => (string) $slug,
					'name'            => (string) ( $tool['label'] ?? $slug ),
					'description'     => (string) ( $tool['description'] ?? '' ),
					'risk'            => self::risk( (array) ( $tool['badges'] ?? array() ) ),
					'available'       => $available,
					'requirement'     => $available ? '' : EMCP_Tools_Admin::requirement_badge( $tool ),
					'requirementNote' => $available ? '' : EMCP_Tools_Admin::requirement_note( $tool ),
					'operations'      => array_values( array_map( 'strval', (array) ( $tool['operations'] ?? array() ) ) ),
				);
				$enabled[ (string) $slug ] = ! in_array( $slug, $disabled, true );
			}
			if ( ! $tools ) {
				continue;
			}
			$group             = (string) ( $category['group'] ?? '' );
			$present[]         = $platform;
			$categories[]      = array(
				'id'         => (string) $id,
				'label'      => (string) ( $category['label'] ?? $id ),
				'platform'   => $platform,
				'group'      => $group,
				'groupLabel' => (string) ( $groups[ $group ]['label'] ?? '' ),
				'note'       => (string) ( $category['note'] ?? '' ),
				'notice'     => is_array( $category['notice'] ?? null ) ? $category['notice'] : null,
				'danger'     => ! empty( $category['danger'] ),
				'proLocked'  => $pro_locked,
				'tools'      => $tools,
			);
		}

		$tabs = array();
		foreach ( self::tab_order( array_values( array_unique( $present ) ) ) as $tab ) {
			$tabs[] = array(
				'id'    => $tab,
				'label' => (string) $labels[ $tab ],
			);
		}

		$themer_on = class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled();
		return array(
			'tabs'            => $tabs,
			'categories'      => $categories,
			'enabled'         => $enabled,
			'dispatcher'      => '1' === (string) get_option( 'emcp_tools_dispatcher_mode', '0' ),
			'themerPhp'       => $themer_on ? '1' === (string) get_option( 'emcp_tools_themer_php_enabled', '0' ) : null,
			'defaults'        => array_values( array_intersect( $this->admin->default_disabled_tool_slugs(), array_keys( $enabled ) ) ),
			'elementorActive' => $elementor,
			'licensed'        => $licensed,
			'upgradeUrl'      => function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing',
		);
	}

	/**
	 * Visible, available slugs: the only ones a request may enable.
	 *
	 * @return string[]
	 */
	public function enableable_slugs(): array {
		$out = array();
		foreach ( $this->payload()['categories'] as $category ) {
			foreach ( $category['tools'] as $tool ) {
				if ( $tool['available'] ) {
					$out[] = $tool['slug'];
				}
			}
		}
		return $out;
	}
}
