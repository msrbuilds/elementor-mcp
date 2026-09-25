<?php
/**
 * REST endpoints the frame's shell calls.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Promo dismissal and notification read state.
 */
final class EMCP_Tools_Admin_REST_Frame extends EMCP_Tools_Admin_REST_Controller {

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		$this->route(
			'promo/dismiss',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'dismiss_promo' ),
				'args'     => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		$this->route(
			'notifications/read',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'read_notifications' ),
				'args'     => array(
					'ids' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Remember that the user dismissed an announcement.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_promo( $request ) {
		$id = sanitize_key( (string) $request->get_param( 'id' ) );
		if ( '' === $id ) {
			return new WP_Error( 'emcp_invalid_promo', __( 'Unknown announcement.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$user      = get_current_user_id();
		$dismissed = array_values( array_filter( (array) get_user_meta( $user, EMCP_Tools_Admin_Frame::PROMO_META, true ) ) );
		if ( ! in_array( $id, $dismissed, true ) ) {
			$dismissed[] = $id;
			update_user_meta( $user, EMCP_Tools_Admin_Frame::PROMO_META, $dismissed );
		}
		return new WP_REST_Response( array( 'dismissed' => $dismissed ) );
	}

	/**
	 * Mark notifications read and return the new unread count.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function read_notifications( $request ) {
		$ids  = array_map( 'strval', array_filter( (array) $request->get_param( 'ids' ), 'is_scalar' ) );
		$user = get_current_user_id();
		if ( class_exists( 'EMCP_Tools_Notifications' ) ) {
			EMCP_Tools_Notifications::mark_read( $user, $ids );
		}
		$unread = class_exists( 'EMCP_Tools_Notifications' ) ? EMCP_Tools_Notifications::unread_count( $user ) : 0;
		return new WP_REST_Response( array( 'unread' => (int) $unread ) );
	}
}
