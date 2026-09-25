<?php
/**
 * Prompts screen REST (spec 8.8).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Admin_REST_Prompts extends EMCP_Tools_Admin_REST_Controller {

	public function register_routes(): void {
		$this->route(
			'prompts',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'get' ),
				'args'     => array(
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'category' => array( 'type' => 'string', 'default' => '' ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				),
			)
		);
		$this->route( 'prompts/sync', array( 'methods' => 'POST', 'callback' => array( $this, 'sync' ) ) );
		$this->route( 'prompts/notice/dismiss', array( 'methods' => 'POST', 'callback' => array( $this, 'dismiss_notice' ) ) );
		$this->route( 'prompts/(?P<category>[a-z0-9-]+)/(?P<slug>[a-z0-9-]+)/copied', array( 'methods' => 'POST', 'callback' => array( $this, 'copied' ) ) );
	}

	public static function register_screen(): void {
		EMCP_Tools_Admin_Screens::register(
			'prompts',
			array(
				'script' => 'screen-prompts',
				'tabs'   => array( 'prompts' ),
				'boot'   => static function (): array {
					return ( new EMCP_Tools_Admin_Prompts_Data() )->payload();
				},
			)
		);
	}

	private static function query( $request ): array {
		return array(
			'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'category' => sanitize_key( (string) $request->get_param( 'category' ) ),
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
		);
	}

	public function get( $request ) {
		return new WP_REST_Response( ( new EMCP_Tools_Admin_Prompts_Data() )->payload( self::query( $request ) ) );
	}

	public function sync( $request ) {
		if ( ! EMCP_Tools_Admin_Prompts_Data::pro_class() ) {
			return new WP_Error( 'emcp_no_license', __( 'Syncing the prompt library needs an active EMCP Pro licence.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		$bundle = EMCP_Tools_Pro_Prompts::get_bundle( true );
		if ( is_wp_error( $bundle ) ) {
			return new WP_Error( 'emcp_sync_failed', $bundle->get_error_message(), array( 'status' => 400 ) );
		}
		delete_transient( 'emcp_tools_nav_counts' );
		$payload = ( new EMCP_Tools_Admin_Prompts_Data() )->payload();
		/* translators: 1: prompt count, 2: category count. */
		$payload['message'] = sprintf( __( 'Synced %1$d prompts across %2$d categories.', 'emcp-tools' ), $payload['total'], count( $payload['categories'] ) );
		return new WP_REST_Response( $payload );
	}

	public function dismiss_notice( $request ) {
		update_user_meta( get_current_user_id(), EMCP_Tools_Admin::META_PROMPTS_NOTICE_DISMISSED, '1' );
		return new WP_REST_Response( array( 'dismissed' => true ) );
	}

	public function copied( $request ) {
		$category = sanitize_key( (string) $request->get_param( 'category' ) );
		$slug     = sanitize_key( (string) $request->get_param( 'slug' ) );
		$prompt   = ( new EMCP_Tools_Admin_Prompts_Data() )->find( $category, $slug );
		if ( null === $prompt ) {
			return new WP_Error( 'emcp_unknown_prompt', __( 'That prompt is not in the library.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		$recorded = false;
		if ( 'pro' === ( $prompt['tier'] ?? '' ) && class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
			if ( ! class_exists( 'EMCP_Tools_Pro_Usage' ) ) {
				$file = EMCP_Tools_Pro_Loader::path( 'includes/admin/class-pro-usage.php' );
				if ( '' !== $file && file_exists( $file ) ) {
					require_once $file;
				}
			}
			if ( class_exists( 'EMCP_Tools_Pro_Usage' ) ) {
				EMCP_Tools_Pro_Usage::record( 'prompt', $slug, $category );
				$recorded = true;
			}
		}
		return new WP_REST_Response( array( 'recorded' => $recorded ) );
	}
}
