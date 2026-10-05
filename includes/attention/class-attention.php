<?php
/**
 * "Needs your attention" (spec 9.7): registered checks, cached ten minutes,
 * dismissed per user until the underlying state changes.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Attention {

	const CACHE = 'emcp_tools_attention';
	const TTL   = 600;
	const META  = 'emcp_tools_attention_dismissed';
	const ORDER = array(
		'danger'  => 0,
		'warning' => 1,
		'info'    => 2,
	);

	/** @return EMCP_Tools_Attention_Check[] */
	public static function default_checks(): array {
		return array(
			new EMCP_Tools_Attention_Server_Off(),
			new EMCP_Tools_Attention_History(),
			new EMCP_Tools_Attention_Sandbox(),
			new EMCP_Tools_Attention_Tools(),
			new EMCP_Tools_Attention_Updates(),
			new EMCP_Tools_Attention_Cloud(),
			new EMCP_Tools_Adapter_Attention(),
		);
	}

	/**
	 * The free checks plus whatever `emcp_tools_attention_checks` adds.
	 *
	 * @return EMCP_Tools_Attention_Check[]
	 */
	public static function checks(): array {
		$checks = apply_filters( 'emcp_tools_attention_checks', self::default_checks() );
		return array_values(
			array_filter(
				(array) $checks,
				static function ( $c ) {
					return $c instanceof EMCP_Tools_Attention_Check;
				}
			)
		);
	}

	/**
	 * Every applying check's item and state, most severe first. Cached for
	 * everyone; dismissals are applied per user afterwards.
	 */
	private static function current(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$out = array();
		foreach ( self::checks() as $check ) {
			try {
				if ( $check->applies() ) {
					$out[] = array(
						'item'  => $check->item(),
						'state' => $check->state(),
					);
				}
			} catch ( Throwable $e ) {
				continue; // One broken check never hides the others.
			}
		}
		usort(
			$out,
			static function ( array $a, array $b ): int {
				return ( self::ORDER[ $a['item']['severity'] ?? '' ] ?? 3 ) <=> ( self::ORDER[ $b['item']['severity'] ?? '' ] ?? 3 );
			}
		);
		set_transient( self::CACHE, $out, self::TTL );
		return $out;
	}

	/** Recompute when the conditions of the constant-state checks change. */
	public static function init(): void {
		foreach ( array( 'emcp_tools_server_enabled', 'emcp_tools_cloud_connection' ) as $option ) {
			add_action( 'update_option_' . $option, array( __CLASS__, 'flush' ) );
			add_action( 'add_option_' . $option, array( __CLASS__, 'flush' ) );
			add_action( 'delete_option_' . $option, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Items this user has not dismissed at their current state. A dismissal
	 * ends once its item stops showing, so a condition that comes back later
	 * shows again even when its state string is the same ("off").
	 *
	 * @param int $user_id User.
	 */
	public static function items( int $user_id ): array {
		$dismissed = get_user_meta( $user_id, self::META, true );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();
		$items     = array();
		$showing   = array();
		foreach ( self::current() as $row ) {
			$id             = (string) $row['item']['id'];
			$showing[ $id ] = true;
			if ( isset( $dismissed[ $id ] ) && (string) $dismissed[ $id ] === (string) $row['state'] ) {
				continue;
			}
			$items[] = $row['item'];
		}
		$kept = array_intersect_key( $dismissed, $showing );
		if ( count( $kept ) !== count( $dismissed ) ) {
			update_user_meta( $user_id, self::META, $kept );
		}
		return $items;
	}

	/**
	 * Hide an item for one user until its state changes.
	 *
	 * @param int    $user_id User.
	 * @param string $id      Check id.
	 * @return bool False when no such item shows now.
	 */
	public static function dismiss( int $user_id, string $id ): bool {
		foreach ( self::current() as $row ) {
			if ( (string) $row['item']['id'] === $id ) {
				$dismissed        = get_user_meta( $user_id, self::META, true );
				$dismissed        = is_array( $dismissed ) ? $dismissed : array();
				$dismissed[ $id ] = (string) $row['state'];
				update_user_meta( $user_id, self::META, $dismissed );
				return true;
			}
		}
		return false;
	}

	/** Recompute on the next read (after a save that changes a check). */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}
}
