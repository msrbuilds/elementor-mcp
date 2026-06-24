<?php
/**
 * Accessibility (A11y) toolkit MCP abilities (Pro).
 *
 * Ships the read-only audit in v1.8.0:
 *   - audit-page-a11y   (WCAG-oriented report: contrast, alts, heading
 *                        hierarchy, link-text quality, form-label coverage)
 *
 * The two auto-fixers (fix-color-contrast, add-alt-text-from-context) are
 * planned for v1.8.1 with a dry-run-by-default model.
 *
 * Pro-gated with the same defense-in-depth shape as the brand-kit / SEO
 * abilities. The report logic is a pure static helper (build_a11y_report) so it
 * unit-tests with fixtures and the execute callback stays thin.
 *
 * @package EMCP_Tools
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the accessibility toolkit abilities.
 *
 * @since 1.8.0
 */
class EMCP_Tools_A11y_Abilities {

	/**
	 * Data access layer.
	 *
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * Generic / non-descriptive link phrases flagged by the audit.
	 *
	 * @var string[]
	 */
	private static $generic_link_text = array(
		'click here', 'here', 'read more', 'learn more', 'more', 'this', 'link', 'this link', 'click', 'go', 'details',
	);

	/**
	 * Constructor.
	 *
	 * @param EMCP_Tools_Data $data Data access layer.
	 */
	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	/**
	 * Pro gate.
	 *
	 * @return bool
	 */
	private function has_access(): bool {
		return function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
	}

	/**
	 * Ability names — empty for non-Pro sites.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		if ( ! $this->has_access() ) {
			return array();
		}
		return array(
			'emcp-tools/audit-page-a11y',
			'emcp-tools/fix-color-contrast',
			'emcp-tools/add-alt-text-from-context',
		);
	}

	/**
	 * Registers the A11y abilities (Pro only).
	 */
	public function register(): void {
		if ( ! $this->has_access() ) {
			return;
		}
		$this->register_audit_page_a11y();
		$this->register_fix_color_contrast();
		$this->register_add_alt_text();
	}

	/**
	 * Read permission: Pro + edit_posts.
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return $this->has_access() && current_user_can( 'edit_posts' );
	}

	/**
	 * Edit permission for the fixers: Pro + edit_posts (per-post ownership is
	 * additionally enforced in the execute callback before any write).
	 *
	 * @return bool
	 */
	public function check_edit_permission(): bool {
		return $this->has_access() && current_user_can( 'edit_posts' );
	}

	private function register_audit_page_a11y(): void {
		emcp_tools_register_ability(
			'emcp-tools/audit-page-a11y',
			array(
				'label'               => __( 'Audit Page Accessibility', 'emcp-tools' ),
				'description'         => __( 'Audits a page for accessibility issues: color contrast (best-effort), missing image alt text, heading hierarchy, generic link text, and form-label coverage. Read-only; returns a scored WCAG-oriented report.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_audit_page_a11y' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => __( 'The page/post ID to audit.', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'score'   => array( 'type' => 'integer' ),
						'checks'  => array( 'type' => 'array' ),
						'summary' => array( 'type' => 'object' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_audit_page_a11y( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'emcp-tools' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'emcp-tools' ) );
		}
		$page = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_array( $page ) ) {
			return new \WP_Error( 'no_data', __( 'No Elementor data found for this page.', 'emcp-tools' ) );
		}
		$host = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '';
		$ex   = EMCP_Tools_Content_Extractor::extract( $page, $host );
		return self::build_a11y_report( $ex );
	}

	// =========================================================================
	// fix-color-contrast  (dry-run by default; apply:true to write)
	// =========================================================================

	private function register_fix_color_contrast(): void {
		emcp_tools_register_ability(
			'emcp-tools/fix-color-contrast',
			array(
				'label'               => __( 'Fix Color Contrast', 'emcp-tools' ),
				'description'         => __( 'Proposes (and, with apply:true, writes) adjusted text colors so failing text/background pairs meet WCAG AA. Dry-run by default — returns the proposed changes without modifying the page unless apply is true. Reversible via Elementor revisions.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_fix_color_contrast' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array( 'type' => 'integer' ),
						'element_id'   => array( 'type' => 'string', 'description' => __( 'Optional: only fix this element.', 'emcp-tools' ) ),
						'target_ratio' => array( 'type' => 'number', 'description' => __( 'Target contrast ratio (default 4.5).', 'emcp-tools' ) ),
						'apply'        => array( 'type' => 'boolean', 'description' => __( 'Write the changes. Defaults to false (dry-run preview).', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'applied'  => array( 'type' => 'boolean' ),
						'count'    => array( 'type' => 'integer' ),
						'proposed' => array( 'type' => 'array' ),
						'changes'  => array( 'type' => 'array' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_fix_color_contrast( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'emcp-tools' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'emcp-tools' ) );
		}
		$page = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_array( $page ) ) {
			return new \WP_Error( 'no_data', __( 'No Elementor data found for this page.', 'emcp-tools' ) );
		}

		$host       = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '';
		$ex         = EMCP_Tools_Content_Extractor::extract( $page, $host );
		$element_id = isset( $input['element_id'] ) ? sanitize_text_field( (string) $input['element_id'] ) : '';
		$target     = isset( $input['target_ratio'] ) ? (float) $input['target_ratio'] : EMCP_Tools_Color_Contrast::AA_NORMAL;
		$fixes      = self::propose_contrast_fixes( $ex, ( '' !== $element_id ) ? $element_id : null, $target );

		if ( empty( $input['apply'] ) ) {
			return array( 'applied' => false, 'count' => count( $fixes ), 'proposed' => $fixes );
		}

		// Writes require per-post ownership.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this page.', 'emcp-tools' ) );
		}

		$applied = 0;
		foreach ( $fixes as $f ) {
			if ( '' === $f['color_key'] ) {
				continue;
			}
			if ( $this->data->update_element_settings( $page, $f['element_id'], array( $f['color_key'] => $f['to'] ) ) ) {
				$applied++;
			}
		}
		if ( $applied > 0 ) {
			$save = $this->data->save_page_data( $post_id, $page );
			if ( is_wp_error( $save ) ) {
				return $save;
			}
		}
		return array( 'applied' => true, 'count' => $applied, 'changes' => $fixes );
	}

	// =========================================================================
	// add-alt-text-from-context  (dry-run by default; apply:true to write)
	// =========================================================================

	private function register_add_alt_text(): void {
		emcp_tools_register_ability(
			'emcp-tools/add-alt-text-from-context',
			array(
				'label'               => __( 'Add Alt Text from Context', 'emcp-tools' ),
				'description'         => __( 'Proposes (and, with apply:true, writes) alt text for images that lack it, derived from the image filename, the nearest heading, or the page title. No AI call. Dry-run by default; writes to the media library alt + the image widget when applied.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_alt_text' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'apply'   => array( 'type' => 'boolean', 'description' => __( 'Write the alt text. Defaults to false (dry-run preview).', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'applied'  => array( 'type' => 'boolean' ),
						'count'    => array( 'type' => 'integer' ),
						'proposed' => array( 'type' => 'array' ),
						'skipped'  => array( 'type' => 'array' ),
						'changes'  => array( 'type' => 'array' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_add_alt_text( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'emcp-tools' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'emcp-tools' ) );
		}
		$page = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_array( $page ) ) {
			return new \WP_Error( 'no_data', __( 'No Elementor data found for this page.', 'emcp-tools' ) );
		}

		$host      = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '';
		$ex        = EMCP_Tools_Content_Extractor::extract( $page, $host );
		$title     = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
		$proposals = self::propose_alt_texts( $ex, $title );

		if ( empty( $input['apply'] ) ) {
			return array( 'applied' => false, 'count' => count( $proposals ), 'proposed' => $proposals );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this page.', 'emcp-tools' ) );
		}

		$applied = 0;
		$skipped = array();
		$dirty   = false;
		foreach ( $proposals as $p ) {
			if ( (int) $p['attachment_id'] > 0 ) {
				if ( function_exists( 'update_post_meta' ) ) {
					update_post_meta( (int) $p['attachment_id'], '_wp_attachment_image_alt', $p['proposed_alt'] );
				}
				// Also set the widget-level alt so this specific image renders it.
				$el    = $this->data->find_element_by_id( $page, $p['element_id'] );
				$image = ( is_array( $el ) && isset( $el['settings']['image'] ) && is_array( $el['settings']['image'] ) ) ? $el['settings']['image'] : null;
				if ( null !== $image ) {
					$image['alt'] = $p['proposed_alt'];
					if ( $this->data->update_element_settings( $page, $p['element_id'], array( 'image' => $image ) ) ) {
						$dirty = true;
					}
				}
				$applied++;
			} else {
				// Raw <img> inside an HTML widget — can't be safely auto-written.
				$skipped[] = $p['element_id'];
			}
		}
		if ( $dirty ) {
			$save = $this->data->save_page_data( $post_id, $page );
			if ( is_wp_error( $save ) ) {
				return $save;
			}
		}
		return array( 'applied' => true, 'count' => $applied, 'skipped' => $skipped, 'changes' => $proposals );
	}

	// =========================================================================
	// Pure analysis helper (no WordPress — unit-testable with fixtures)
	// =========================================================================

	/**
	 * Builds a scored accessibility report from extracted content.
	 *
	 * Contrast resolution is best-effort: pairs whose background can't be
	 * resolved are reported as inconclusive (never asserted as failures).
	 *
	 * @param array $ex Content_Extractor output.
	 * @return array
	 */
	public static function build_a11y_report( array $ex ): array {
		$checks = array();

		// --- Color contrast --------------------------------------------------
		$pass = 0;
		$fail = 0;
		$inconclusive = 0;
		$worst = array();
		foreach ( $ex['text_style_contexts'] as $ctx ) {
			$bg = $ctx['background'] ?? null;
			if ( null === $bg || '' === $bg ) {
				$inconclusive++;
				continue;
			}
			$ratio = EMCP_Tools_Color_Contrast::contrast_ratio( (string) $ctx['color'], (string) $bg );
			if ( null === $ratio ) {
				$inconclusive++;
				continue;
			}
			if ( EMCP_Tools_Color_Contrast::passes( $ratio ) ) {
				$pass++;
			} else {
				$fail++;
				$worst[] = sprintf( '%s (%.2f:1)', $ctx['element_id'], $ratio );
			}
		}
		$total_ctx = $pass + $fail + $inconclusive;
		if ( 0 === $total_ctx ) {
			$contrast_status = 'inconclusive';
			$contrast_detail = __( 'No resolvable text/background color pairs found (colors may use globals or theme defaults).', 'emcp-tools' );
		} elseif ( $fail > 0 ) {
			$contrast_status = 'fail';
			$contrast_detail = sprintf(
				/* translators: 1: fail count, 2: worst list, 3: inconclusive count */
				__( '%1$d text/background pair(s) below 4.5:1 — %2$s. %3$d pair(s) inconclusive.', 'emcp-tools' ),
				$fail,
				implode( ', ', array_slice( $worst, 0, 5 ) ),
				$inconclusive
			);
		} elseif ( $pass > 0 ) {
			$contrast_status = ( $inconclusive > 0 ) ? 'warn' : 'pass';
			$contrast_detail = sprintf(
				/* translators: 1: pass count, 2: inconclusive count */
				__( '%1$d pair(s) meet 4.5:1; %2$d inconclusive (couldn\'t resolve background).', 'emcp-tools' ),
				$pass,
				$inconclusive
			);
		} else {
			$contrast_status = 'inconclusive';
			$contrast_detail = sprintf( /* translators: %d: count */ __( '%d color pair(s) inconclusive — background could not be resolved.', 'emcp-tools' ), $inconclusive );
		}
		$checks[] = self::check(
			'color_contrast',
			__( 'Color contrast (WCAG AA)', 'emcp-tools' ),
			$contrast_status,
			$contrast_detail,
			( 'fail' === $contrast_status ) ? __( 'Increase text/background contrast to at least 4.5:1 (3:1 for large text).', 'emcp-tools' ) : ''
		);

		// --- Image alt text --------------------------------------------------
		$missing = 0;
		foreach ( $ex['images'] as $img ) {
			if ( '' === trim( (string) $img['alt'] ) ) {
				$missing++;
			}
		}
		$checks[] = self::check(
			'image_alts',
			__( 'Image alt text', 'emcp-tools' ),
			( 0 === $missing ) ? 'pass' : 'fail',
			sprintf( /* translators: 1: missing, 2: total */ __( '%1$d of %2$d images are missing alt text.', 'emcp-tools' ), $missing, count( $ex['images'] ) ),
			( 0 === $missing ) ? '' : __( 'Add descriptive alt text (or empty alt="" for purely decorative images).', 'emcp-tools' )
		);

		// --- Heading hierarchy ----------------------------------------------
		$skip = false;
		$prev = 0;
		foreach ( $ex['headings'] as $h ) {
			$lvl = (int) $h['level'];
			if ( $prev > 0 && $lvl > $prev + 1 ) {
				$skip = true;
				break;
			}
			$prev = $lvl;
		}
		$checks[] = self::check(
			'heading_hierarchy',
			__( 'Heading hierarchy', 'emcp-tools' ),
			$skip ? 'warn' : 'pass',
			$skip ? __( 'A heading level is skipped, which disorients screen-reader users.', 'emcp-tools' ) : __( 'Headings are sequential.', 'emcp-tools' ),
			$skip ? __( 'Use heading levels in order (don\'t jump from H1 to H3).', 'emcp-tools' ) : ''
		);

		// --- Link text quality ----------------------------------------------
		$generic = 0;
		$empty   = 0;
		foreach ( $ex['links'] as $l ) {
			$text = trim( (string) $l['text'] );
			if ( '' === $text ) {
				$empty++;
			} elseif ( in_array( self::lower( $text ), self::$generic_link_text, true ) ) {
				$generic++;
			}
		}
		$bad     = $generic + $empty;
		$checks[] = self::check(
			'link_text_quality',
			__( 'Link text quality', 'emcp-tools' ),
			( 0 === $bad ) ? 'pass' : 'warn',
			sprintf(
				/* translators: 1: generic count, 2: empty count */
				__( '%1$d generic ("click here"-style) and %2$d empty link text(s).', 'emcp-tools' ),
				$generic,
				$empty
			),
			( 0 === $bad ) ? '' : __( 'Use descriptive link text that makes sense out of context.', 'emcp-tools' )
		);

		// --- Form label coverage --------------------------------------------
		$unlabeled = 0;
		foreach ( $ex['form_fields'] as $f ) {
			if ( '' === trim( (string) $f['label'] ) ) {
				$unlabeled++;
			}
		}
		if ( ! empty( $ex['form_fields'] ) ) {
			$checks[] = self::check(
				'form_label_coverage',
				__( 'Form label coverage', 'emcp-tools' ),
				( 0 === $unlabeled ) ? 'pass' : 'fail',
				sprintf( /* translators: 1: unlabeled, 2: total */ __( '%1$d of %2$d form fields have no label.', 'emcp-tools' ), $unlabeled, count( $ex['form_fields'] ) ),
				( 0 === $unlabeled ) ? '' : __( 'Give every form field a visible label.', 'emcp-tools' )
			);
		}

		return array(
			'score'   => self::score( $checks ),
			'checks'  => $checks,
			'summary' => self::summary( $checks ),
		);
	}

	/**
	 * Proposes adjusted text colors for failing contrast pairs.
	 *
	 * Only resolvable pairs (background known) that currently fail are returned;
	 * inconclusive pairs are left alone (we never "fix" what we can't measure).
	 *
	 * @param array       $ex         Content_Extractor output.
	 * @param string|null $element_id Optional element filter.
	 * @param float       $target     Target contrast ratio.
	 * @return array[] Each: { element_id, color_key, background, from, to, old_ratio, new_ratio }.
	 */
	public static function propose_contrast_fixes( array $ex, ?string $element_id, float $target = EMCP_Tools_Color_Contrast::AA_NORMAL ): array {
		$fixes = array();
		foreach ( $ex['text_style_contexts'] as $ctx ) {
			if ( null !== $element_id && ( $ctx['element_id'] ?? '' ) !== $element_id ) {
				continue;
			}
			$bg = $ctx['background'] ?? null;
			if ( null === $bg || '' === $bg ) {
				continue; // Can't fix what we can't measure.
			}
			$ratio = EMCP_Tools_Color_Contrast::contrast_ratio( (string) $ctx['color'], (string) $bg );
			if ( null === $ratio || EMCP_Tools_Color_Contrast::passes( $ratio ) ) {
				continue;
			}
			$suggest = EMCP_Tools_Color_Contrast::suggest_adjusted( (string) $ctx['color'], (string) $bg, $target );
			if ( null === $suggest ) {
				continue;
			}
			$new_ratio = EMCP_Tools_Color_Contrast::contrast_ratio( $suggest, (string) $bg );
			$fixes[]   = array(
				'element_id' => (string) $ctx['element_id'],
				'color_key'  => (string) ( $ctx['color_key'] ?? '' ),
				'background' => (string) $bg,
				'from'       => (string) $ctx['color'],
				'to'         => $suggest,
				'old_ratio'  => round( (float) $ratio, 2 ),
				'new_ratio'  => null !== $new_ratio ? round( (float) $new_ratio, 2 ) : null,
			);
		}
		return $fixes;
	}

	/**
	 * Proposes alt text for images that lack it, from filename → nearest heading
	 * → page title (no AI call).
	 *
	 * @param array  $ex         Content_Extractor output.
	 * @param string $page_title Page title fallback.
	 * @return array[] Each: { element_id, attachment_id, url, proposed_alt, source, writable }.
	 */
	public static function propose_alt_texts( array $ex, string $page_title ): array {
		$out = array();
		foreach ( $ex['images'] as $img ) {
			if ( '' !== trim( (string) $img['alt'] ) ) {
				continue; // Already has alt.
			}
			$source = 'filename';
			$alt    = self::alt_from_filename( (string) $img['url'] );
			if ( '' === $alt ) {
				$alt    = trim( (string) ( $img['context_heading'] ?? '' ) );
				$source = 'heading';
			}
			if ( '' === $alt ) {
				$alt    = trim( $page_title );
				$source = 'page_title';
			}
			if ( '' === $alt ) {
				continue; // Nothing to propose.
			}
			$out[] = array(
				'element_id'    => (string) $img['element_id'],
				'attachment_id' => (int) $img['attachment_id'],
				'url'           => (string) $img['url'],
				'proposed_alt'  => $alt,
				'source'        => $source,
				'writable'      => ( (int) $img['attachment_id'] > 0 ),
			);
		}
		return $out;
	}

	/**
	 * Derives a descriptive phrase from an image filename, or '' if the filename
	 * is non-descriptive (camera codes like IMG_1234, pure numbers, dimensions).
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	public static function alt_from_filename( string $url ): string {
		$path = parse_url( $url, PHP_URL_PATH );
		$base = is_string( $path ) && '' !== $path ? basename( $path ) : basename( $url );
		$base = preg_replace( '/\.[a-z0-9]+$/i', '', (string) $base ); // strip extension
		$base = preg_replace( '/[-_]+/', ' ', (string) $base );
		// Strip WordPress size/scale suffixes.
		$base = preg_replace( '/\b\d{2,4}x\d{2,4}\b/i', ' ', (string) $base );
		$base = preg_replace( '/\bscaled\b/i', ' ', (string) $base );
		$base = preg_replace( '/\be\d{8,}\b/i', ' ', (string) $base );

		$tokens = preg_split( '/\s+/', trim( (string) $base ) );
		$words  = array();
		foreach ( (array) $tokens as $t ) {
			$t = trim( (string) $t );
			if ( '' === $t || preg_match( '/^\d+$/', $t ) ) {
				continue;
			}
			if ( preg_match( '/^(img|image|dsc|dscn|pxl|pic|photo|screenshot|untitled|final|copy|v\d+)$/i', $t ) ) {
				continue;
			}
			$words[] = strtolower( $t );
		}
		if ( count( $words ) < 2 ) {
			return ''; // Too sparse to be a real description.
		}
		return ucfirst( implode( ' ', $words ) );
	}

	// -------------------------------------------------------------------------
	// Internal utilities
	// -------------------------------------------------------------------------

	/**
	 * Builds one check entry.
	 *
	 * @param string $id     Check id.
	 * @param string $label  Label.
	 * @param string $status pass|warn|fail|inconclusive.
	 * @param string $detail Detail.
	 * @param string $rec    Recommendation.
	 * @return array
	 */
	private static function check( string $id, string $label, string $status, string $detail, string $rec ): array {
		return array(
			'id'             => $id,
			'label'          => $label,
			'status'         => $status,
			'detail'         => $detail,
			'recommendation' => $rec,
		);
	}

	/**
	 * 0-100 score. inconclusive is neutral (excluded from the denominator) so a
	 * page isn't penalized for contrast we honestly couldn't resolve.
	 *
	 * @param array $checks Checks.
	 * @return int
	 */
	private static function score( array $checks ): int {
		$sum   = 0.0;
		$count = 0;
		foreach ( $checks as $c ) {
			if ( 'inconclusive' === $c['status'] ) {
				continue;
			}
			$count++;
			$sum += ( 'pass' === $c['status'] ) ? 1.0 : ( 'warn' === $c['status'] ? 0.5 : 0.0 );
		}
		return ( 0 === $count ) ? 0 : (int) round( 100 * $sum / $count );
	}

	/**
	 * Tallies pass/warn/fail/inconclusive counts.
	 *
	 * @param array $checks Checks.
	 * @return array
	 */
	private static function summary( array $checks ): array {
		$s = array( 'passes' => 0, 'warnings' => 0, 'failures' => 0, 'inconclusive' => 0 );
		foreach ( $checks as $c ) {
			switch ( $c['status'] ) {
				case 'pass':
					$s['passes']++;
					break;
				case 'warn':
					$s['warnings']++;
					break;
				case 'inconclusive':
					$s['inconclusive']++;
					break;
				default:
					$s['failures']++;
			}
		}
		return $s;
	}

	/**
	 * Lowercases (mbstring-aware).
	 *
	 * @param string $s Input.
	 * @return string
	 */
	private static function lower( string $s ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}
}
