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
 * Notification read state.
 */
final class EMCP_Tools_Admin_REST_Frame extends EMCP_Tools_Admin_REST_Controller {

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
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
