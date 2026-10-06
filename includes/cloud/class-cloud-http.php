<?php
/**
 * EMCP Cloud HTTP transport (JSON + form POST), with a test-injectable seam.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Http {
	const TIMEOUT = 20;

	/** @var callable|null Test seam: fn($url,$args) => ['code'=>int,'json'=>array]|WP_Error. */
	private static $transport = null;

	/**
	 * @param callable|null $t Injected transport, or null to reset to wp_remote_post.
	 * @return void
	 */
	public static function set_transport( ?callable $t ): void {
		self::$transport = $t;
	}

	/**
	 * @param string $url  Endpoint.
	 * @param array  $args wp_remote_post args (headers, body, ...).
	 * @return array|\WP_Error ['code'=>int,'json'=>array] or WP_Error.
	 */
	private static function send( string $url, array $args ) {
		if ( null !== self::$transport ) {
			return ( self::$transport )( $url, $args );
		}
		$args['timeout']   = self::TIMEOUT;
		$args['sslverify'] = true;
		$method            = strtoupper( (string) ( $args['method'] ?? 'POST' ) );
		$res               = ( 'POST' === $method ) ? wp_remote_post( $url, $args ) : wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		return array(
			'code' => $code,
			'json' => is_array( $json ) ? $json : array(),
			'retry_after' => (string) wp_remote_retrieve_header( $res, 'retry-after' ),
		);
	}

	/**
	 * JSON POST (used for DCR).
	 *
	 * @param string $url     Endpoint.
	 * @param array  $body    Body to JSON-encode.
	 * @param array  $headers Extra headers.
	 * @return array|\WP_Error
	 */
	public static function post_json( string $url, array $body, array $headers = array() ) {
		return self::send(
			$url,
			array(
				'headers' => array_merge( array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * Form-encoded POST (used for token/refresh/revoke). An array body is
	 * auto-form-encoded by WP HTTP. Callers pass the Origin header.
	 *
	 * @param string $url     Endpoint.
	 * @param array  $fields  Form fields.
	 * @param array  $headers Extra headers (Origin).
	 * @return array|\WP_Error
	 */
	public static function post_form( string $url, array $fields, array $headers = array() ) {
		return self::send(
			$url,
			array(
				'headers' => array_merge( array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json' ), $headers ),
				'body'    => $fields,
			)
		);
	}

	/**
	 * Generic request with an explicit HTTP method (GET/PUT/DELETE). JSON body.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url      Endpoint.
	 * @param array  $args     wp_remote args (headers, body).
	 * @return array|\WP_Error
	 */
	public static function request( string $method, string $url, array $args ) {
		$args['method'] = strtoupper( $method );
		return self::send( $url, $args );
	}
}
