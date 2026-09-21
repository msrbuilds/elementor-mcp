<?php
/**
 * Sandbox widget, block, and PHP snippet actions and portable bundles.
 *
 * Internal implementation of EMCP_Tools_Admin; loaded by class-admin.php.
 * Methods retain the admin class scope for existing callbacks and callers.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sandbox widget, block, and PHP snippet actions and portable bundles.
 */
trait EMCP_Tools_Admin_Sandbox_Trait {

	/**
	 * Which internal Sandbox pillar to render. The Sandbox parent page
	 * (?page=emcp-tools-widgets) is a 3-card overview; each pillar's full
	 * management UI lives at ?page=emcp-tools-widgets&view=<pillar> — a route
	 * deliberately not exposed as its own wp-admin menu entry.
	 *
	 * @since 3.7.0
	 *
	 * @return string One of 'overview' | 'blocks' | 'widgets' | 'snippets' | 'export'.
	 */
	public static function sandbox_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch, no state change.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'overview';
		return in_array( $view, array( 'overview', 'blocks', 'widgets', 'snippets', 'export' ), true ) ? $view : 'overview';
	}

	/**
	 * AJAX: activate/deactivate a generated widget from the Widget Builder tab.
	 *
	 * @since 1.9.0
	 */
	public function ajax_toggle_widget(): void {
		check_ajax_referer( 'emcp_tools_widgets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Widget_Store' ) || ! EMCP_Tools_Widget_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$widget_id = isset( $_POST['widget_id'] ) ? absint( wp_unslash( $_POST['widget_id'] ) ) : 0;
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $widget_id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_Widget_Store::set_status( $widget_id, $status );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: delete a generated widget from the Widget Builder tab.
	 *
	 * @since 1.9.0
	 */
	public function ajax_delete_widget(): void {
		check_ajax_referer( 'emcp_tools_widgets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Widget_Store' ) || ! EMCP_Tools_Widget_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$widget_id = isset( $_POST['widget_id'] ) ? absint( wp_unslash( $_POST['widget_id'] ) ) : 0;
		if ( ! $widget_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_Widget_Store::delete( $widget_id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: activate/deactivate a generated Gutenberg block from the Blocks tab.
	 *
	 * @since 3.7.0
	 */
	public function ajax_toggle_block(): void {
		check_ajax_referer( 'emcp_tools_blocks', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Block_Store' ) || ! EMCP_Tools_Block_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$block_id = isset( $_POST['block_id'] ) ? absint( wp_unslash( $_POST['block_id'] ) ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $block_id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_Block_Store::instance()->set_status( $block_id, $status );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: delete a generated Gutenberg block from the Blocks tab.
	 *
	 * @since 3.7.0
	 */
	public function ajax_delete_block(): void {
		check_ajax_referer( 'emcp_tools_blocks', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_Block_Store' ) || ! EMCP_Tools_Block_Store::user_has_access() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$block_id = isset( $_POST['block_id'] ) ? absint( wp_unslash( $_POST['block_id'] ) ) : 0;
		if ( ! $block_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_Block_Store::instance()->delete( $block_id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: create or update a PHP snippet draft from the Sandbox tab. Validates
	 * and refuses critical findings (returning them so the form can show why).
	 *
	 * @since 2.1.0
	 */
	public function ajax_save_php_snippet(): void {
		check_ajax_referer( 'emcp_tools_php_snippets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) || ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage PHP snippets (requires manage_options and unfiltered_html).', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['snippet_id'] ) ? absint( wp_unslash( $_POST['snippet_id'] ) ) : 0;
		// Code is raw PHP: keep it verbatim (unslash only). It is never executed
		// here — it is validated and stored; execution requires later activation.
		$args = array(
			'title'    => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'code'     => isset( $_POST['code'] ) ? wp_unslash( (string) $_POST['code'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw PHP source, validated by the snippet validator, never executed here.
			'context'  => isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : 'shortcode',
			'hook'     => isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '',
			'priority' => isset( $_POST['priority'] ) ? absint( wp_unslash( $_POST['priority'] ) ) : 10,
		);

		$res = $id
			? EMCP_Tools_PHP_Snippet_Store::update( $id, $args )
			: EMCP_Tools_PHP_Snippet_Store::create_draft( $args );

		if ( is_wp_error( $res ) ) {
			$data    = $res->get_error_data();
			$payload = array( 'message' => $res->get_error_message() );
			if ( is_array( $data ) && isset( $data['validation'] ) ) {
				$payload['validation'] = $data['validation'];
				// Summarised here rather than in the browser, so the report reads
				// the same wherever it is shown.
				$payload['summary'] = EMCP_Tools_PHP_Snippet_Validator::summary( (array) $data['validation'] );
			}
			wp_send_json_error( $payload, 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: activate/deactivate a PHP snippet (the human approval gate).
	 * Activation re-validates and writes the executable file.
	 *
	 * @since 2.1.0
	 */
	public function ajax_toggle_php_snippet(): void {
		check_ajax_referer( 'emcp_tools_php_snippets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) || ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$id     = isset( $_POST['snippet_id'] ) ? absint( wp_unslash( $_POST['snippet_id'] ) ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_PHP_Snippet_Store::set_status( $id, $status );
		if ( is_wp_error( $res ) ) {
			$data    = $res->get_error_data();
			$payload = array( 'message' => $res->get_error_message() );
			if ( is_array( $data ) && isset( $data['validation'] ) ) {
				$payload['validation'] = $data['validation'];
				// Summarised here rather than in the browser, so the report reads
				// the same wherever it is shown.
				$payload['summary'] = EMCP_Tools_PHP_Snippet_Validator::summary( (array) $data['validation'] );
			}
			wp_send_json_error( $payload, 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * AJAX: delete a PHP snippet from the Sandbox tab.
	 *
	 * @since 2.1.0
	 */
	public function ajax_delete_php_snippet(): void {
		check_ajax_referer( 'emcp_tools_php_snippets', 'nonce' );
		if ( ! class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) || ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'emcp-tools' ) ), 403 );
		}
		$id = isset( $_POST['snippet_id'] ) ? absint( wp_unslash( $_POST['snippet_id'] ) ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'emcp-tools' ) ), 400 );
		}
		$res = EMCP_Tools_PHP_Snippet_Store::delete( $id );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 400 );
		}
		wp_send_json_success( $res );
	}

	/**
	 * Nonce'd admin-post URL that exports one sandbox artifact (block/widget/
	 * snippet) as a portable JSON bundle download. Mirrors delete_change_url().
	 *
	 * @since 3.7.0
	 *
	 * @param string $kind One of 'block' | 'widget' | 'snippet'.
	 * @param int    $id   The artifact's local post ID.
	 * @return string
	 */
	public static function sandbox_export_url( string $kind, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_EXPORT_ARTIFACT,
					'kind'   => $kind,
					'id'     => $id,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_SANDBOX_BUNDLE
		);
	}

	/**
	 * admin-post.php callback: stream one sandbox artifact (custom block,
	 * custom widget, or PHP snippet) as a portable, checksum-verified JSON
	 * bundle download. GET: kind, id, _wpnonce. Halts execution at the end.
	 *
	 * Reuses EMCP_Tools_Sandbox_Cloud_Abilities::resolve_artifact() — the same
	 * resolver the MCP export-sandbox-artifact tool uses — so a block export
	 * cleanly fails here (no fatal) on a site without the Pro overlay.
	 *
	 * @since 3.7.0
	 */
	public function handle_export_artifact(): void {
		check_admin_referer( self::NONCE_SANDBOX_BUNDLE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above via check_admin_referer().
		$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above via check_admin_referer().
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		if ( ! in_array( $kind, EMCP_Tools_Sandbox_Bundle::KINDS, true ) ) {
			wp_die( esc_html__( 'Unsupported sandbox artifact kind.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}

		$artifact = ( new EMCP_Tools_Sandbox_Cloud_Abilities() )->resolve_artifact( $kind );
		if ( null === $artifact ) {
			wp_die( esc_html__( 'That artifact kind is unavailable on this site (it may require EMCP Tools Pro).', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}

		$bundle = $artifact->to_bundle( $id );
		if ( is_wp_error( $bundle ) ) {
			wp_die( esc_html( $bundle->get_error_message() ), '', array( 'response' => 400 ) );
		}

		$filename = sanitize_file_name( 'emcp-' . $kind . '-' . $id . '.json' );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		echo wp_json_encode( $bundle, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- streamed JSON download body, not HTML.
		exit;
	}

	/**
	 * admin-post.php callback: import an uploaded sandbox artifact bundle
	 * (custom block, custom widget, or PHP snippet) as a new local draft.
	 * POST (multipart): the `bundle` file upload, `_wpnonce`. Redirects back
	 * to the pillar view for the imported kind with a minimal notice query
	 * arg. Halts execution at the end.
	 *
	 * Validates the upload (present, no error, size-capped, .json extension,
	 * decodes to an array) then defers to EMCP_Tools_Sandbox_Bundle::validate()
	 * (schema version, kind, checksum) before resolving the artifact and
	 * calling apply_bundle() — a block import on a non-Pro site resolves to
	 * null and redirects with a clean notice, never a fatal.
	 *
	 * @since 3.7.0
	 */
	public function handle_import_artifact(): void {
		check_admin_referer( self::NONCE_SANDBOX_BUNDLE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}

		$back = menu_page_url( 'emcp-tools-widgets', false );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES superglobal; every field is validated below before use.
		$file = isset( $_FILES['bundle'] ) && is_array( $_FILES['bundle'] ) ? $_FILES['bundle'] : array();

		if ( empty( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'No bundle file was uploaded, or the upload failed.', 'emcp-tools' ) ), $back ) );
			exit;
		}

		$max_bytes = 2 * MB_IN_BYTES;
		if ( ! isset( $file['size'] ) || $file['size'] <= 0 || $file['size'] > $max_bytes ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'The bundle file is empty or larger than 2 MB.', 'emcp-tools' ) ), $back ) );
			exit;
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( '.json' !== strtolower( substr( $name, -5 ) ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'The bundle must be a .json file.', 'emcp-tools' ) ), $back ) );
			exit;
		}

		$tmp_name = isset( $file['tmp_name'] ) ? wp_unslash( $file['tmp_name'] ) : '';
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'The upload could not be read.', 'emcp-tools' ) ), $back ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a validated PHP-upload tmp file (is_uploaded_file() checked above), not a remote URL.
		$contents = file_get_contents( $tmp_name );
		$data     = ( false !== $contents ) ? json_decode( $contents, true ) : null;

		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( __( 'The bundle is not valid JSON.', 'emcp-tools' ) ), $back ) );
			exit;
		}

		$valid = EMCP_Tools_Sandbox_Bundle::validate( $data );
		if ( is_wp_error( $valid ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( $valid->get_error_message() ), $back ) );
			exit;
		}

		$kind     = (string) $data['kind'];
		$artifact = ( new EMCP_Tools_Sandbox_Cloud_Abilities() )->resolve_artifact( $kind );
		if ( null === $artifact ) {
			wp_safe_redirect(
				add_query_arg(
					'import_error',
					rawurlencode(
						sprintf(
							/* translators: %s: artifact kind (e.g. "block") */
							__( 'The "%s" artifact kind requires EMCP Tools Pro.', 'emcp-tools' ),
							$kind
						)
					),
					$back
				)
			);
			exit;
		}

		$new_id = $artifact->apply_bundle( $data );
		if ( is_wp_error( $new_id ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', rawurlencode( $new_id->get_error_message() ), $back ) );
			exit;
		}

		$view_by_kind = array(
			'block'   => 'blocks',
			'widget'  => 'widgets',
			'snippet' => 'snippets',
		);
		$view         = isset( $view_by_kind[ $kind ] ) ? $view_by_kind[ $kind ] : 'overview';

		wp_safe_redirect(
			add_query_arg(
				array(
					'view'     => $view,
					'imported' => '1',
				),
				$back
			)
		);
		exit;
	}
}
