<?php
/**
 * Brand Kits screen data (spec 8.17): the bundled free kits, or the synced
 * Pro library when licensed (falling back to the free kits when the sync
 * fails), plus apply and restore on free code and the current-kit record.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Brand_Kits_Data {

	const PER_PAGE       = 12;
	const OPTION_CURRENT = 'emcp_tools_current_brand_kit';
	/** Backup post meta: the current-kit record from before that backup's apply. */
	const META_PREV_CURRENT = '_emcp_prev_current_kit';

	private static function elementor_active(): bool {
		if ( defined( 'EMCP_TOOLS_TESTING' ) && isset( $GLOBALS['_emcp_elementor_active'] ) ) {
			return (bool) $GLOBALS['_emcp_elementor_active'];
		}
		return class_exists( 'EMCP_Tools_Bootstrap' ) && EMCP_Tools_Bootstrap::elementor_active();
	}

	private static function licensed(): bool {
		return class_exists( 'EMCP_Tools_Pro_Brand_Kits' ) && EMCP_Tools_Pro_Brand_Kits::user_has_access();
	}

	/** @return array{bundle: array, source: string, error: string} */
	private function library(): array {
		if ( self::licensed() ) {
			$bundle = EMCP_Tools_Pro_Brand_Kits::get_bundle();
			if ( ! is_wp_error( $bundle ) && ! empty( $bundle['categories'] ) ) {
				return array( 'bundle' => $bundle, 'source' => 'pro', 'error' => '' );
			}
			$error = is_wp_error( $bundle ) ? $bundle->get_error_message() : __( 'The brand kit library is empty.', 'emcp-tools' );
			return array( 'bundle' => EMCP_Tools_Free_Brand_Kits::get_bundle(), 'source' => 'free', 'error' => $error );
		}
		return array( 'bundle' => EMCP_Tools_Free_Brand_Kits::get_bundle(), 'source' => 'free', 'error' => '' );
	}

	/** @return string[] Four colours: primary, secondary, text, accent. */
	public static function swatches( array $kit ): array {
		$preview = $kit['preview']['swatches'] ?? null;
		if ( is_array( $preview ) && count( $preview ) >= 4 ) {
			return array_map( 'strval', array_slice( array_values( $preview ), 0, 4 ) );
		}
		$out = array();
		foreach ( array( 'primary', 'secondary', 'text', 'accent' ) as $slot ) {
			$out[] = (string) ( $kit['colors'][ $slot ]['color'] ?? '#cccccc' );
		}
		return $out;
	}

	public function find( string $category, string $slug ): ?array {
		foreach ( $this->library()['bundle']['categories'] ?? array() as $cat ) {
			if ( ( $cat['slug'] ?? '' ) !== $category ) {
				continue;
			}
			foreach ( $cat['kits'] ?? array() as $kit ) {
				if ( ( $kit['slug'] ?? '' ) === $slug ) {
					return $kit;
				}
			}
		}
		return null;
	}

	private static function current(): ?array {
		$c = get_option( self::OPTION_CURRENT, null );
		return is_array( $c ) && ! empty( $c['slug'] ) ? $c : null;
	}

	/**
	 * @param string $category Category slug.
	 * @param string $slug     Kit slug.
	 * @param bool   $backup   Save a restore point first.
	 * @return array|WP_Error
	 */
	public function apply( string $category, string $slug, bool $backup ) {
		if ( ! self::elementor_active() ) {
			return new WP_Error( 'emcp_no_elementor', __( 'Activate Elementor to apply a brand kit: kits write to the Elementor site settings.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		$kit = $this->find( $category, $slug );
		if ( null === $kit ) {
			return new WP_Error( 'emcp_unknown_kit', __( 'That brand kit is not in the library.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		$backup_id = null;
		if ( $backup ) {
			$created = EMCP_Tools_Kit_Backup_Store::create( (string) ( $kit['title'] ?? $slug ) );
			if ( is_wp_error( $created ) ) {
				return new WP_Error( $created->get_error_code(), $created->get_error_message(), array( 'status' => 400 ) );
			}
			$backup_id = (int) $created;
			update_post_meta( $backup_id, self::META_PREV_CURRENT, wp_slash( (string) wp_json_encode( self::current() ) ) );
		}
		$result = EMCP_Tools_System_Kit_Writer::apply_kit( $kit );
		if ( is_wp_error( $result ) ) {
			// The writer can fail after writing the colours: put everything back.
			if ( null !== $backup_id && ! is_wp_error( EMCP_Tools_Kit_Backup_Store::restore( $backup_id, true ) ) ) {
				wp_trash_post( $backup_id );
				/* translators: %s: the reason the kit could not be applied. */
				return new WP_Error( $result->get_error_code(), sprintf( __( 'The kit could not be applied. Your previous colors and fonts were put back. %s', 'emcp-tools' ), $result->get_error_message() ), array( 'status' => 400 ) );
			}
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}
		update_option(
			self::OPTION_CURRENT,
			array(
				'slug'      => $slug,
				'category'  => $category,
				'title'     => (string) ( $kit['title'] ?? $slug ),
				'swatches'  => self::swatches( $kit ),
				'appliedAt' => time(),
				'backupId'  => $backup_id,
			),
			false
		);
		return array_merge( $result, array( 'backup_id' => $backup_id ) );
	}

	/**
	 * Restore the newest restore point, then retire it, so each Restore goes
	 * one kit further back and the current-kit record follows.
	 *
	 * @return array|WP_Error
	 */
	public function restore( bool $full_clobber ) {
		if ( ! self::elementor_active() ) {
			return new WP_Error( 'emcp_no_elementor', __( 'Activate Elementor to restore a brand kit.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		$backups = EMCP_Tools_Kit_Backup_Store::list_backups( 1 );
		if ( ! $backups ) {
			return new WP_Error( 'emcp_no_backup', __( 'There is no restore point yet. One is saved each time you apply a kit.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		$backup_id = (int) $backups[0]['id'];
		$result    = EMCP_Tools_Kit_Backup_Store::restore( $backup_id, $full_clobber );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}
		$previous = json_decode( (string) get_post_meta( $backup_id, self::META_PREV_CURRENT, true ), true );
		if ( is_array( $previous ) && ! empty( $previous['slug'] ) ) {
			update_option( self::OPTION_CURRENT, $previous, false );
		} else {
			delete_option( self::OPTION_CURRENT );
		}
		wp_trash_post( $backup_id );
		return array( 'restored' => $backups[0]['title'] );
	}

	public function payload( array $query = array() ): array {
		$lib     = $this->library();
		$current = self::current();
		$list    = EMCP_Tools_Admin_Library_List::build(
			$lib['bundle']['categories'] ?? array(),
			'kits',
			static function ( array $k ): string {
				return ( $k['title'] ?? '' ) . ' ' . ( $k['description'] ?? '' ) . ' ' . implode( ' ', (array) ( $k['tags'] ?? array() ) );
			},
			$query,
			self::PER_PAGE
		);
		$list['items'] = array_map(
			static function ( array $k ) use ( $current ): array {
				return array(
					'slug'          => (string) $k['slug'],
					'category'      => (string) $k['category'],
					'categoryLabel' => (string) $k['categoryLabel'],
					'title'         => (string) ( $k['title'] ?? '' ),
					'description'   => (string) ( $k['description'] ?? '' ),
					'swatches'      => self::swatches( $k ),
					'headingFont'   => (string) ( $k['typography']['primary']['font_family'] ?? '' ),
					'bodyFont'      => (string) ( $k['typography']['text']['font_family'] ?? '' ),
					'headingColor'  => (string) ( $k['colors']['primary']['color'] ?? '#111111' ),
					'accentColor'   => (string) ( $k['colors']['accent']['color'] ?? '#111111' ),
					'thumbnail'     => (string) ( $k['thumbnail_url'] ?? '' ),
					'applied'       => null !== $current && $current['slug'] === $k['slug'] && $current['category'] === $k['category'],
				);
			},
			$list['items']
		);
		return array_merge(
			$list,
			array(
				'current'         => $current,
				'restorable'      => (bool) EMCP_Tools_Kit_Backup_Store::list_backups( 1 ),
				'source'          => $lib['source'],
				'syncedAt'        => 'pro' === $lib['source'] ? (int) ( $lib['bundle']['fetched_at'] ?? 0 ) : 0,
				'error'           => $lib['error'],
				'licensed'        => self::licensed(),
				'elementorActive' => self::elementor_active(),
				'upgradeUrl'      => function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing',
			)
		);
	}
}
