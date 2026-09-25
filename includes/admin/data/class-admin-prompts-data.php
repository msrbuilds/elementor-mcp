<?php
/**
 * Prompts screen data (spec 8.8): the bundled samples on free builds, the
 * synced Pro library when licensed (falling back to the samples when the
 * sync fails), paged 12 at a time with full content for Copy.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_Prompts_Data {

	const PER_PAGE = 12;

	/** Samples shown on free builds (file name => meta), as page-prompts.php. */
	private static function samples(): array {
		return array(
			'LOCAL_BUSINESS'          => array( __( 'Local Business', 'emcp-tools' ), __( 'General', 'emcp-tools' ), __( 'Multi-purpose small business landing page with hero, services, testimonials, and contact section.', 'emcp-tools' ) ),
			'DENTAL_CLINIC'           => array( __( 'Dental Clinic', 'emcp-tools' ), __( 'Health & Wellness', 'emcp-tools' ), __( 'Professional dental practice with services grid, team profiles, insurance info, and appointment booking.', 'emcp-tools' ) ),
			'WEB_DEVELOPER_PORTFOLIO' => array( __( 'Web Developer Portfolio', 'emcp-tools' ), __( 'Professional Services', 'emcp-tools' ), __( 'Developer portfolio with project showcase, tech stack, GitHub stats, and contact form.', 'emcp-tools' ) ),
			'HAIR_SALON'              => array( __( 'Hair Salon', 'emcp-tools' ), __( 'Lifestyle', 'emcp-tools' ), __( 'Stylish salon page with services menu, stylist profiles, gallery, and online booking.', 'emcp-tools' ) ),
			'CAR_WASH'                => array( __( 'Car Wash', 'emcp-tools' ), __( 'Lifestyle', 'emcp-tools' ), __( 'Car wash site with wash packages, add-on services, membership plans, and booking form.', 'emcp-tools' ) ),
		);
	}

	/** The free samples as a library bundle. */
	private static function free_bundle(): array {
		$categories = array();
		foreach ( self::samples() as $file => $meta ) {
			$path = EMCP_TOOLS_DIR . 'prompts/' . $file . '.md';
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$cat = sanitize_title( $meta[1] );
			if ( ! isset( $categories[ $cat ] ) ) {
				$categories[ $cat ] = array( 'slug' => $cat, 'label' => $meta[1], 'prompts' => array() );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin file.
			$categories[ $cat ]['prompts'][] = array( 'slug' => sanitize_title( $file ), 'title' => $meta[0], 'description' => $meta[2], 'content' => (string) file_get_contents( $path ), 'tier' => 'free' );
		}
		return array( 'categories' => array_values( $categories ) );
	}

	/** Load EMCP_Tools_Pro_Prompts (admin-only in the Pro loader) and report whether it is usable. */
	public static function pro_class(): bool {
		if ( ! class_exists( 'EMCP_Tools_Pro_Prompts' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			$file = EMCP_Tools_Pro_Loader::path( 'includes/admin/class-pro-prompts.php' );
			if ( '' !== $file && file_exists( $file ) ) {
				require_once $file;
			}
		}
		return class_exists( 'EMCP_Tools_Pro_Prompts' ) && EMCP_Tools_Pro_Prompts::user_has_access();
	}

	/** @return array{bundle: array, source: string, error: string} */
	private function library(): array {
		if ( self::pro_class() ) {
			$bundle = EMCP_Tools_Pro_Prompts::get_bundle();
			if ( ! is_wp_error( $bundle ) ) {
				foreach ( $bundle['categories'] as &$cat ) {
					foreach ( $cat['prompts'] as &$prompt ) {
						$prompt['tier'] = 'pro';
					}
					unset( $prompt );
				}
				unset( $cat );
				return array( 'bundle' => $bundle, 'source' => 'pro', 'error' => '' );
			}
			return array( 'bundle' => self::free_bundle(), 'source' => 'free', 'error' => $bundle->get_error_message() );
		}
		return array( 'bundle' => self::free_bundle(), 'source' => 'free', 'error' => '' );
	}

	public function find( string $category, string $slug ): ?array {
		foreach ( $this->library()['bundle']['categories'] as $cat ) {
			if ( ( $cat['slug'] ?? '' ) !== $category ) {
				continue;
			}
			foreach ( $cat['prompts'] ?? array() as $prompt ) {
				if ( ( $prompt['slug'] ?? '' ) === $slug ) {
					return $prompt;
				}
			}
		}
		return null;
	}

	private static function ai_chat_url(): string {
		$module = class_exists( 'EMCP_Tools_Modules_Registry' ) ? EMCP_Tools_Modules_Registry::instance()->get( 'ai-chat' ) : null;
		return ( $module && $module->is_active() && $module->is_available() ) ? admin_url( 'admin.php?page=emcp-tools-ai-chat' ) : '';
	}

	public function payload( array $query = array() ): array {
		$lib   = $this->library();
		$list  = EMCP_Tools_Admin_Library_List::build(
			$lib['bundle']['categories'] ?? array(),
			'prompts',
			static function ( array $p ): string {
				return ( $p['title'] ?? '' ) . ' ' . ( $p['description'] ?? '' );
			},
			$query,
			self::PER_PAGE
		);
		$items = array_map(
			static function ( array $p ): array {
				return array(
					'slug'          => (string) $p['slug'],
					'category'      => (string) $p['category'],
					'categoryLabel' => (string) $p['categoryLabel'],
					'title'         => (string) ( $p['title'] ?? '' ),
					'description'   => (string) ( $p['description'] ?? '' ),
					'content'       => (string) ( $p['content'] ?? '' ),
					'tier'          => (string) ( $p['tier'] ?? 'free' ),
				);
			},
			$list['items']
		);
		$pro = 'pro' === $lib['source'];
		return array_merge(
			$list,
			array(
				'items'           => $items,
				'source'          => $lib['source'],
				'syncedAt'        => $pro ? (int) ( $lib['bundle']['synced_at'] ?? $lib['bundle']['fetched_at'] ?? 0 ) : 0,
				'error'           => $lib['error'],
				'licensed'        => self::pro_class(),
				'noticeDismissed' => EMCP_Tools_Admin::prompts_notice_dismissed(),
				'v1Url'           => ( $pro && EMCP_Tools_Pro_Prompts::v1_zip_available() ) ? EMCP_Tools_Pro_Prompts::v1_download_url() : '',
				'aiChatUrl'       => self::pro_class() ? self::ai_chat_url() : '',
				'upgradeUrl'      => function_exists( 'emcp_tools_upgrade_url' ) ? emcp_tools_upgrade_url() : 'https://emcptools.com/pricing',
			)
		);
	}
}
