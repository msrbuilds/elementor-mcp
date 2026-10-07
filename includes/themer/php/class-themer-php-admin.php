<?php
/**
 * EMCP Themer PHP Templates — review + edit admin page (submenu under EMCP Themer).
 *
 * Lists draft PHP templates with type/compiled state/last error, and — when viewing
 * one — a full CodeMirror editor (the same `wp_enqueue_code_editor` machinery as the
 * core theme/plugin file editor) so an admin can edit the code, re-validate, and
 * recompile it. A delete action is also provided. Registered only when the feature
 * is enabled.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.1.0
 */
class EMCP_Tools_Themer_PHP_Admin {

	const PAGE = 'emcp-themer-php';

	/** Page hook suffix (set by add_submenu_page) — used to scope asset enqueues. */
	private $hook = '';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_emcp_themer_php_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_emcp_themer_php_delete', array( $this, 'handle_delete' ) );
	}

	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			'edit.php?post_type=' . EMCP_Tools_Themer_CPT::POST_TYPE,
			__( 'PHP Templates', 'emcp-tools' ),
			__( 'PHP Templates', 'emcp-tools' ),
			EMCP_Tools_Management_Access::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue the CodeMirror PHP editor on this page (when viewing a template).
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}
		// PHP mode with linting — same editor core WordPress uses for theme/plugin files.
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/x-php' ) );
		if ( false === $settings ) {
			return; // User disabled syntax highlighting, the plain textarea still works.
		}
		wp_add_inline_script(
			'code-editor',
			'jQuery(function($){ var el=document.getElementById("emcp-themer-php-code"); if(el && window.wp && wp.codeEditor){ wp.codeEditor.initialize(el, ' . wp_json_encode( $settings ) . '); } });'
		);
	}

	public function render(): void {
		if ( ! current_user_can( EMCP_Tools_Management_Access::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'emcp-tools' ) );
		}
		$templates = EMCP_Tools_Themer_PHP_Store::list_templates();
		$view      = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$detail    = $view ? EMCP_Tools_Themer_PHP_Store::get( $view ) : null;
		$notice    = get_transient( 'emcp_themer_php_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'emcp_themer_php_notice_' . get_current_user_id() );
		}
		include EMCP_TOOLS_DIR . 'includes/admin/views/page-themer-php.php';
	}

	/**
	 * Save an edited template's title + code (re-validates; recompiles if attached).
	 */
	public function handle_save(): void {
		$id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		check_admin_referer( 'emcp_themer_php_save_' . $id );

		$back = add_query_arg(
			array( 'post_type' => EMCP_Tools_Themer_CPT::POST_TYPE, 'page' => self::PAGE, 'view' => $id ),
			admin_url( 'edit.php' )
		);

		if ( ! current_user_can( EMCP_Tools_Management_Access::CAPABILITY ) || ! EMCP_Tools_Themer_PHP_Store::can_edit() ) {
			$this->notice( 'error', __( 'You do not have permission to edit PHP templates.', 'emcp-tools' ) );
			wp_safe_redirect( $back );
			exit;
		}

		$args = array(
			// wp_unslash: the store re-slashes for storage; validation must see raw code.
			'code'  => isset( $_POST['code'] ) ? (string) wp_unslash( $_POST['code'] ) : '',
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
		);
		if ( isset( $_POST['type'] ) ) {
			// The store's sanitize_type() validates this against the allowed set.
			$args['type'] = sanitize_text_field( wp_unslash( $_POST['type'] ) );
		}

		$result = EMCP_Tools_Themer_PHP_Store::update( $id, $args );
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
		} else {
			$recompiled = ! empty( $result['compiled'] );
			$this->notice(
				'success',
				$recompiled
					? __( 'Template saved and recompiled (it is attached to a template).', 'emcp-tools' )
					: __( 'Template saved.', 'emcp-tools' )
			);
		}
		wp_safe_redirect( $back );
		exit;
	}

	public function handle_delete(): void {
		$id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		check_admin_referer( 'emcp_themer_php_delete_' . $id );
		if ( current_user_can( EMCP_Tools_Management_Access::CAPABILITY ) && current_user_can( 'unfiltered_html' ) ) {
			EMCP_Tools_Themer_PHP_Store::delete( $id );
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => EMCP_Tools_Themer_CPT::POST_TYPE, 'page' => self::PAGE ), admin_url( 'edit.php' ) ) );
		exit;
	}

	/** Stash a one-shot admin notice for the current user. */
	private function notice( string $type, string $message ): void {
		set_transient(
			'emcp_themer_php_notice_' . get_current_user_id(),
			array( 'type' => $type, 'message' => $message ),
			60
		);
	}
}
