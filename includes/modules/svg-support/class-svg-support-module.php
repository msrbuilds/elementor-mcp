<?php
/**
 * SVG Uploads module — safe, self-contained SVG support.
 *
 * WordPress blocks SVG uploads by default (SVG is XML and can carry scripts).
 * Elementor enables SVG for authorized users via its own unfiltered-upload
 * handling, so this module is aimed at sites **without** Elementor (or where the
 * `svg` mime isn't otherwise registered). When active it:
 *   1. adds the `svg` mime type (for users who can `upload_files`),
 *   2. fixes `wp_check_filetype_and_ext()` so the real-content MIME check doesn't
 *      reject SVGs on the REST/sideload path (the piece most SVG plugins miss),
 *   3. sanitizes every uploaded SVG with the bundled enshrined/svg-sanitize
 *      library — fail-closed: an SVG that can't be cleaned is rejected.
 *
 * Free tier; opt-in (off by default) given the security surface.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG Uploads module.
 *
 * @since 3.4.0
 */
class EMCP_Tools_SVG_Support_Module extends EMCP_Tools_Module {

	const ID     = 'svg-support';
	const PREFIX = 'emcp_tools_module_svg_support_';

	public function id(): string {
		return self::ID;
	}

	public function title(): string {
		return __( 'SVG Uploads', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Safely allow SVG uploads to the Media Library. Every SVG is sanitized (scripts, event handlers and external references stripped) before it is saved. Mainly for sites without Elementor — Elementor already allows SVG uploads for authorized users.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	/** Opt-in: SVG uploads are a security surface, so off until an admin enables it. */
	public function default_active(): bool {
		return false;
	}

	/** Needs the sanitizer library present (bundled). Without it we must not allow SVGs. */
	public function is_available(): bool {
		return EMCP_Tools_SVG_Sanitizer::library_available();
	}

	/** Whether some other plugin/theme (e.g. Elementor) already allows the svg mime. */
	public static function svg_already_supported(): bool {
		$mimes = get_allowed_mime_types();
		return isset( $mimes['svg'] ) || isset( $mimes['svg|svgz'] );
	}

	/**
	 * One option: restrict SVG upload to administrators only.
	 *
	 * @return array<string,array>
	 */
	public function settings_fields(): array {
		$bool = static function ( $v ) {
			return '1' === (string) $v ? '1' : '0';
		};
		return array(
			self::PREFIX . 'admin_only' => array(
				'type'              => 'string',
				'default'           => '0',
				'sanitize_callback' => $bool,
			),
		);
	}

	/** The capability required to upload an SVG, honoring the admin-only toggle + a filter. */
	public function required_capability(): string {
		$admin_only = '1' === (string) get_option( self::PREFIX . 'admin_only', '0' );
		$cap        = $admin_only ? 'manage_options' : 'upload_files';
		/**
		 * Filter the capability required to upload an SVG.
		 *
		 * @param string $cap Capability slug.
		 */
		return (string) apply_filters( 'emcp_tools_svg_upload_capability', $cap );
	}

	/** Wire the module's runtime hooks. Called only when active + available. */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'allow_svg_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_filetype' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_upload' ) );
		if ( is_admin() ) {
			add_action( 'admin_head', array( $this, 'media_thumbnail_css' ) );
		}
	}

	/**
	 * Add the svg mime for users allowed to upload it.
	 *
	 * @param array<string,string> $mimes Allowed mimes.
	 * @return array<string,string>
	 */
	public function allow_svg_mime( array $mimes ): array {
		if ( current_user_can( $this->required_capability() ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Resolve the ext/type for .svg files so the real-content MIME check
	 * (finfo often reports text/plain or image/svg for SVG) doesn't reject the
	 * upload — this is what makes REST/sideload uploads work.
	 *
	 * @param array  $data     Values for ext/type/proper_filename.
	 * @param string $file     Full path to the file.
	 * @param string $filename The name of the file.
	 * @param array  $mimes    Allowed mimes.
	 * @return array
	 */
	public function fix_svg_filetype( $data, $file, $filename, $mimes ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext && current_user_can( $this->required_capability() ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	/**
	 * Sanitize an SVG upload in place; reject it if it can't be cleaned.
	 *
	 * @param array $file $_FILES-style entry ({name,type,tmp_name,error,size}).
	 * @return array
	 */
	public function sanitize_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return $file;
		}
		if ( 'svg' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			return $file;
		}
		if ( ! current_user_can( $this->required_capability() ) ) {
			$file['error'] = __( 'You are not allowed to upload SVG files.', 'emcp-tools' );
			return $file;
		}
		if ( ! ( new EMCP_Tools_SVG_Sanitizer() )->sanitize_file( $file['tmp_name'] ) ) {
			$file['error'] = __( 'This SVG could not be sanitized and was rejected for security. Its markup may contain scripts or unsupported content.', 'emcp-tools' );
		}
		return $file;
	}

	/** Make SVG thumbnails render in the Media Library grid/list. */
	public function media_thumbnail_css(): void {
		echo '<style>.attachment .thumbnail img[src$=".svg"],.media-icon img[src$=".svg"],td.column-title img[src$=".svg"]{width:100%;height:auto;}</style>';
	}

	/** Render the card knobs. */
	public function render_settings(): void {
		$admin_only = '1' === (string) get_option( self::PREFIX . 'admin_only', '0' );
		if ( self::svg_already_supported() ) {
			echo '<p class="description">' . esc_html__( 'SVG uploads are already enabled on this site (e.g. by Elementor or another plugin). This module will still sanitize SVGs when active.', 'emcp-tools' ) . '</p>';
		}
		echo '<label><input type="checkbox" name="' . esc_attr( self::PREFIX . 'admin_only' ) . '" value="1" ' . checked( $admin_only, true, false ) . ' /> ';
		echo esc_html__( 'Restrict SVG uploads to administrators only', 'emcp-tools' ) . '</label>';
	}
}
