<?php
/**
 * Local record of Marketplace installs (spec 8.18): the Cloud API has no
 * per-site installs list, so the site remembers which listing became which
 * draft, for the "Installed" state and "My installs".
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Marketplace_Installs {

	const OPTION = 'emcp_tools_marketplace_installs';

	/** Sandbox screen for each artifact post type. */
	const VIEWS = array(
		'emcp_widget'      => 'widgets',
		'emcp_block'       => 'blocks',
		'emcp_php_snippet' => 'snippets',
	);

	public static function record( string $slug, string $kind, int $id, string $title ): void {
		if ( '' === $slug || $id <= 0 ) {
			return;
		}
		$all          = self::raw();
		$all[ $slug ] = array( 'kind' => $kind, 'id' => $id, 'title' => $title, 'at' => time() );
		update_option( self::OPTION, $all, false );
	}

	public static function review_url( int $id ): string {
		$view = self::VIEWS[ (string) get_post_type( $id ) ] ?? '';
		return '' === $view ? '' : admin_url( 'admin.php?page=emcp-tools-widgets&view=' . $view . '&review=' . $id );
	}

	/** @return array{id:int, reviewUrl:string}|null */
	public static function installed( string $slug ): ?array {
		$rec    = self::raw()[ $slug ] ?? null;
		$status = is_array( $rec ) ? get_post_status( (int) $rec['id'] ) : false;
		// A trashed draft is not installed: the listing can be installed again.
		if ( false === $status || 'trash' === $status ) {
			return null;
		}
		return array( 'id' => (int) $rec['id'], 'reviewUrl' => self::review_url( (int) $rec['id'] ) );
	}

	/** Installs whose draft still exists and is not trashed, newest first; records of deleted posts are dropped. */
	public static function all(): array {
		$all  = self::raw();
		$out  = array();
		$kept = array();
		foreach ( $all as $slug => $rec ) {
			$status = is_array( $rec ) ? get_post_status( (int) $rec['id'] ) : false;
			if ( false === $status ) {
				continue;
			}
			$kept[ $slug ] = $rec;
			if ( 'trash' === $status ) {
				continue;
			}
			$out[] = array(
				'slug'      => (string) $slug,
				'kind'      => (string) $rec['kind'],
				'id'        => (int) $rec['id'],
				'title'     => (string) $rec['title'],
				'at'        => (int) $rec['at'],
				'status'    => (string) $status,
				'reviewUrl' => self::review_url( (int) $rec['id'] ),
			);
		}
		if ( count( $kept ) !== count( $all ) ) {
			update_option( self::OPTION, $kept, false );
		}
		usort(
			$out,
			static function ( array $a, array $b ): int {
				return $b['at'] <=> $a['at'];
			}
		);
		return $out;
	}

	private static function raw(): array {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}
}
