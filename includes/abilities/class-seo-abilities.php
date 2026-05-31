<?php
/**
 * SEO toolkit MCP abilities (Pro).
 *
 * Four read-only / proposal tools that operate on a page's real Elementor
 * structure + the site's SEO-plugin meta — no external API, no inference cost:
 *
 *   - audit-page-seo                  (scored on-page SEO report)
 *   - extract-keywords-from-content   (frequency keyword extraction)
 *   - generate-meta-tags              (proposed title + description)
 *   - generate-schema-markup          (JSON-LD for the page)
 *
 * Pro-gated with the same defense-in-depth shape as the brand-kit abilities:
 * register() / get_ability_names() are no-ops without a license, each
 * permission_callback re-checks, and each execute re-checks. The analysis logic
 * lives in pure static helpers (build_seo_report / rank_keywords / propose_meta
 * / build_jsonld) so it unit-tests with fixtures and the execute callbacks stay
 * thin.
 *
 * @package Elementor_MCP
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the SEO toolkit abilities.
 *
 * @since 1.8.0
 */
class Elementor_MCP_Seo_Abilities {

	/**
	 * Data access layer.
	 *
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param Elementor_MCP_Data $data Data access layer.
	 */
	public function __construct( Elementor_MCP_Data $data ) {
		$this->data = $data;
	}

	// -------------------------------------------------------------------------
	// Gate + registration
	// -------------------------------------------------------------------------

	/**
	 * Whether the SEO tools should register/run on this site (Pro only).
	 *
	 * @return bool
	 */
	private function has_access(): bool {
		return function_exists( 'emcp_pro_fs' ) && emcp_pro_fs()->can_use_premium_code();
	}

	/**
	 * Ability names — empty for non-Pro sites so they never enter the MCP
	 * surface or count against client tool caps.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		if ( ! $this->has_access() ) {
			return array();
		}
		return array(
			'elementor-mcp/audit-page-seo',
			'elementor-mcp/extract-keywords-from-content',
			'elementor-mcp/generate-meta-tags',
			'elementor-mcp/generate-schema-markup',
		);
	}

	/**
	 * Registers the SEO abilities (Pro only).
	 */
	public function register(): void {
		if ( ! $this->has_access() ) {
			return;
		}
		$this->register_audit_page_seo();
		$this->register_extract_keywords();
		$this->register_generate_meta_tags();
		$this->register_generate_schema_markup();
	}

	/**
	 * Read permission: Pro + edit_posts.
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return $this->has_access() && current_user_can( 'edit_posts' );
	}

	// -------------------------------------------------------------------------
	// Shared input helpers
	// -------------------------------------------------------------------------

	/**
	 * Site host (e.g. "example.com") for internal/external link classification.
	 *
	 * @return string
	 */
	private function site_host(): string {
		if ( ! function_exists( 'home_url' ) ) {
			return '';
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	/**
	 * Loads + extracts a page's normalized content, or a WP_Error.
	 *
	 * @param int $post_id Post ID.
	 * @return array|\WP_Error
	 */
	private function extracted( int $post_id ) {
		$page = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_array( $page ) ) {
			return new \WP_Error( 'no_data', __( 'No Elementor data found for this page.', 'elementor-mcp' ) );
		}
		return Elementor_MCP_Content_Extractor::extract( $page, $this->site_host() );
	}

	// =========================================================================
	// audit-page-seo
	// =========================================================================

	private function register_audit_page_seo(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/audit-page-seo',
			array(
				'label'               => __( 'Audit Page SEO', 'elementor-mcp' ),
				'description'         => __( 'Audits on-page SEO for an Elementor page (H1, title/meta length, canonical, heading hierarchy, image alts, internal links, word count, optional target-keyword usage). Read-only; returns a scored report.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_audit_page_seo' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array( 'type' => 'integer', 'description' => __( 'The page/post ID to audit.', 'elementor-mcp' ) ),
						'target_keyword' => array( 'type' => 'string', 'description' => __( 'Optional focus keyword to check usage of.', 'elementor-mcp' ) ),
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
	public function execute_audit_page_seo( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'elementor-mcp' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'elementor-mcp' ) );
		}
		$extracted = $this->extracted( $post_id );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$seo    = Elementor_MCP_Seo_Meta::get( $post_id );
		$target = isset( $input['target_keyword'] ) ? sanitize_text_field( (string) $input['target_keyword'] ) : '';
		return self::build_seo_report( $extracted, $seo, $target );
	}

	// =========================================================================
	// extract-keywords-from-content
	// =========================================================================

	private function register_extract_keywords(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/extract-keywords-from-content',
			array(
				'label'               => __( 'Extract Keywords from Content', 'elementor-mcp' ),
				'description'         => __( 'Extracts the most frequent meaningful keywords and two-word phrases from a page\'s text (stop-word filtered). No external service. Useful for choosing a target keyword.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_extract_keywords' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'limit'   => array( 'type' => 'integer', 'description' => __( 'Max keywords to return (default 20).', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'keywords'    => array( 'type' => 'array' ),
						'bigrams'     => array( 'type' => 'array' ),
						'total_words' => array( 'type' => 'integer' ),
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
	public function execute_extract_keywords( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'elementor-mcp' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'elementor-mcp' ) );
		}
		$extracted = $this->extracted( $post_id );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$limit = isset( $input['limit'] ) ? max( 1, min( 100, absint( $input['limit'] ) ) ) : 20;
		return self::rank_keywords( $extracted, $limit );
	}

	// =========================================================================
	// generate-meta-tags
	// =========================================================================

	private function register_generate_meta_tags(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/generate-meta-tags',
			array(
				'label'               => __( 'Generate Meta Tags', 'elementor-mcp' ),
				'description'         => __( 'Proposes an SEO title (<=60 chars) and meta description (<=155 chars) from the page content, keyword-front-loaded when a target keyword is given. Dry-run by default; with apply:true writes them to the active SEO plugin (Yoast / Rank Math).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_generate_meta_tags' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array( 'type' => 'integer' ),
						'target_keyword' => array( 'type' => 'string' ),
						'apply'          => array( 'type' => 'boolean', 'description' => __( 'Write the proposed meta to the active SEO plugin. Defaults to false (dry-run).', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'proposed_title'       => array( 'type' => 'string' ),
						'proposed_description' => array( 'type' => 'string' ),
						'title_length'         => array( 'type' => 'integer' ),
						'description_length'   => array( 'type' => 'integer' ),
						'applied'              => array( 'type' => 'boolean' ),
						'write_source'         => array( 'type' => 'string' ),
						'written_fields'       => array( 'type' => 'array' ),
						'notes'                => array( 'type' => 'array' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_generate_meta_tags( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'elementor-mcp' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'elementor-mcp' ) );
		}
		$extracted = $this->extracted( $post_id );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$seo       = Elementor_MCP_Seo_Meta::get( $post_id );
		$target    = isset( $input['target_keyword'] ) ? sanitize_text_field( (string) $input['target_keyword'] ) : '';
		$site_name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$proposal  = self::propose_meta( $extracted, $seo, $target, $site_name );

		$proposal['applied']        = false;
		$proposal['write_source']   = '';
		$proposal['written_fields'] = array();

		if ( ! empty( $input['apply'] ) ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this page.', 'elementor-mcp' ) );
			}
			$w                          = Elementor_MCP_Seo_Meta::write( $post_id, $proposal['proposed_title'], $proposal['proposed_description'] );
			$proposal['applied']        = $w['written'];
			$proposal['write_source']   = $w['source'];
			$proposal['written_fields'] = $w['fields'];
			if ( ! $w['written'] && 'none' === $w['source'] ) {
				$proposal['notes'][] = __( 'No SEO plugin (Yoast / Rank Math) detected — meta was not persisted. Install one, or add the tags via generate-schema-markup / a head snippet.', 'elementor-mcp' );
			}
		}

		return $proposal;
	}

	// =========================================================================
	// generate-schema-markup
	// =========================================================================

	private function register_generate_schema_markup(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/generate-schema-markup',
			array(
				'label'               => __( 'Generate Schema Markup', 'elementor-mcp' ),
				'description'         => __( 'Generates JSON-LD structured data for the page (Article, LocalBusiness, FAQPage, Service, or Product). LocalBusiness requires a business object (name/address/phone). FAQPage uses a provided faqs array. Dry-run by default; with apply:true injects it into the page via a managed HTML widget (replaced in place on re-apply).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_generate_schema_markup' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array( 'type' => 'integer' ),
						'schema_type' => array(
							'type'        => 'string',
							'enum'        => array( 'auto', 'Article', 'LocalBusiness', 'FAQPage', 'Service', 'Product' ),
							'description' => __( 'Schema type, or "auto" to infer.', 'elementor-mcp' ),
						),
						'business'    => array(
							'type'        => 'object',
							'description' => __( 'NAP for LocalBusiness: { name, street, locality, region, postal_code, country, phone, url, price_range }.', 'elementor-mcp' ),
						),
						'faqs'        => array(
							'type'        => 'array',
							'description' => __( 'For FAQPage: array of { question, answer }.', 'elementor-mcp' ),
						),
						'apply'       => array( 'type' => 'boolean', 'description' => __( 'Inject the JSON-LD into the page. Defaults to false (dry-run).', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'detected_type' => array( 'type' => 'string' ),
						'jsonld'        => array( 'type' => 'string' ),
						'insert_hint'   => array( 'type' => 'string' ),
						'applied'        => array( 'type' => 'boolean' ),
						'element_id'     => array( 'type' => 'string' ),
						'notes'         => array( 'type' => 'array' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_generate_schema_markup( $input ) {
		if ( ! $this->has_access() ) {
			return new \WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required.', 'elementor-mcp' ) );
		}
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'missing_post_id', __( 'A valid post_id is required.', 'elementor-mcp' ) );
		}
		$extracted = $this->extracted( $post_id );
		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}
		$seo      = Elementor_MCP_Seo_Meta::get( $post_id );
		$type     = isset( $input['schema_type'] ) ? sanitize_text_field( (string) $input['schema_type'] ) : 'auto';
		$business = isset( $input['business'] ) && is_array( $input['business'] ) ? $input['business'] : array();
		$faqs     = isset( $input['faqs'] ) && is_array( $input['faqs'] ) ? $input['faqs'] : array();
		$url      = $seo['canonical'];
		$result   = self::build_jsonld( $type, $extracted, $seo, $business, $faqs, $url );

		$result['applied']    = false;
		$result['element_id'] = '';

		if ( ! empty( $input['apply'] ) ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this page.', 'elementor-mcp' ) );
			}
			if ( '' === $result['jsonld'] ) {
				$result['notes'][] = __( 'No JSON-LD was generated, so nothing was injected.', 'elementor-mcp' );
				return $result;
			}
			$injected = $this->inject_schema( $post_id, $result['jsonld'] );
			if ( is_wp_error( $injected ) ) {
				return $injected;
			}
			$result['applied']    = true;
			$result['element_id'] = $injected;
		}

		return $result;
	}

	/**
	 * Injects (or replaces in place) the page's managed JSON-LD HTML widget.
	 *
	 * Idempotent: the widget's element id is stored in `_emcp_schema_element_id`
	 * post meta, so re-applying updates the same widget instead of stacking
	 * duplicate schema. A fresh injection appends a full-width container holding
	 * the script at the end of the page.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $jsonld  The JSON-LD string.
	 * @return string|\WP_Error The HTML-widget element id, or WP_Error.
	 */
	private function inject_schema( int $post_id, string $jsonld ) {
		$page = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		if ( ! is_array( $page ) ) {
			$page = array();
		}

		$script = '<script type="application/ld+json">' . "\n" . $jsonld . "\n" . '</script>';

		$stored = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, '_emcp_schema_element_id', true ) : '';
		if ( '' !== $stored && null !== $this->data->find_element_by_id( $page, $stored ) ) {
			// Replace in place.
			if ( ! $this->data->update_element_settings( $page, $stored, array( 'html' => $script ) ) ) {
				return new \WP_Error( 'inject_failed', __( 'Could not update the existing schema widget.', 'elementor-mcp' ) );
			}
			$element_id = $stored;
		} else {
			// Fresh injection: append a full-width container with an HTML widget.
			$element_id = Elementor_MCP_Id_Generator::generate();
			$container  = array(
				'id'         => Elementor_MCP_Id_Generator::generate(),
				'elType'     => 'container',
				'widgetType' => null,
				'settings'   => array( 'content_width' => 'full' ),
				'elements'   => array(
					array(
						'id'         => $element_id,
						'elType'     => 'widget',
						'widgetType' => 'html',
						'settings'   => array( 'html' => $script ),
						'elements'   => array(),
					),
				),
			);
			$page[] = $container;
			if ( function_exists( 'update_post_meta' ) ) {
				update_post_meta( $post_id, '_emcp_schema_element_id', $element_id );
			}
		}

		$save = $this->data->save_page_data( $post_id, $page );
		if ( is_wp_error( $save ) ) {
			return $save;
		}
		return $element_id;
	}

	// =========================================================================
	// Pure analysis helpers (no WordPress — unit-testable with fixtures)
	// =========================================================================

	/**
	 * Builds a scored SEO report from extracted content + resolved SEO meta.
	 *
	 * @param array  $ex     Content_Extractor output.
	 * @param array  $seo    Seo_Meta output.
	 * @param string $target Optional target keyword.
	 * @return array
	 */
	public static function build_seo_report( array $ex, array $seo, string $target = '' ): array {
		$checks = array();

		// H1 presence.
		$h1 = 0;
		foreach ( $ex['headings'] as $h ) {
			if ( 1 === (int) $h['level'] ) {
				$h1++;
			}
		}
		$checks[] = self::check(
			'h1_present',
			__( 'Single H1', 'elementor-mcp' ),
			( 1 === $h1 ) ? 'pass' : ( 0 === $h1 ? 'fail' : 'warn' ),
			sprintf( /* translators: %d: count */ __( '%d H1 heading(s) found.', 'elementor-mcp' ), $h1 ),
			( 1 === $h1 ) ? '' : __( 'A page should have exactly one H1.', 'elementor-mcp' )
		);

		// Heading hierarchy (no skipped levels).
		$levels = array_map( static function ( $h ) {
			return (int) $h['level'];
		}, $ex['headings'] );
		$skip = false;
		$prev = 0;
		foreach ( $levels as $lvl ) {
			if ( $prev > 0 && $lvl > $prev + 1 ) {
				$skip = true;
				break;
			}
			$prev = $lvl;
		}
		$checks[] = self::check(
			'heading_hierarchy',
			__( 'Heading hierarchy', 'elementor-mcp' ),
			$skip ? 'warn' : 'pass',
			$skip ? __( 'A heading level is skipped (e.g. H1 → H3).', 'elementor-mcp' ) : __( 'No skipped heading levels.', 'elementor-mcp' ),
			$skip ? __( 'Avoid jumping heading levels; keep them sequential.', 'elementor-mcp' ) : ''
		);

		// Title length.
		$title_len = self::mb_len( $seo['title'] ?? '' );
		$tpl_note  = ! empty( $seo['title_is_template'] ) ? __( ' (contains SEO-plugin template tokens — length is approximate)', 'elementor-mcp' ) : '';
		$checks[]  = self::check(
			'title_length',
			__( 'Title length', 'elementor-mcp' ),
			( 0 === $title_len ) ? 'fail' : ( ( $title_len >= 30 && $title_len <= 60 ) ? 'pass' : 'warn' ),
			sprintf( /* translators: 1: length, 2: note */ __( 'SEO title is %1$d characters%2$s.', 'elementor-mcp' ), $title_len, $tpl_note ),
			( $title_len >= 30 && $title_len <= 60 ) ? '' : __( 'Aim for a 30–60 character title.', 'elementor-mcp' )
		);

		// Meta description.
		$desc_len = self::mb_len( $seo['description'] ?? '' );
		$checks[] = self::check(
			'meta_description',
			__( 'Meta description', 'elementor-mcp' ),
			( 0 === $desc_len ) ? 'fail' : ( ( $desc_len >= 120 && $desc_len <= 160 ) ? 'pass' : 'warn' ),
			( 0 === $desc_len ) ? __( 'No meta description set.', 'elementor-mcp' ) : sprintf( /* translators: %d: length */ __( 'Meta description is %d characters.', 'elementor-mcp' ), $desc_len ),
			( $desc_len >= 120 && $desc_len <= 160 ) ? '' : __( 'Aim for a 120–160 character meta description.', 'elementor-mcp' )
		);

		// Canonical.
		$has_canonical = '' !== trim( (string) ( $seo['canonical'] ?? '' ) );
		$checks[]      = self::check(
			'canonical',
			__( 'Canonical URL', 'elementor-mcp' ),
			$has_canonical ? 'pass' : 'warn',
			$has_canonical ? __( 'Canonical URL is present.', 'elementor-mcp' ) : __( 'No canonical URL resolved.', 'elementor-mcp' ),
			$has_canonical ? '' : __( 'Set a canonical URL (your SEO plugin usually does this automatically).', 'elementor-mcp' )
		);

		// Image alts.
		$missing_alt = 0;
		foreach ( $ex['images'] as $img ) {
			if ( '' === trim( (string) $img['alt'] ) ) {
				$missing_alt++;
			}
		}
		$total_img = count( $ex['images'] );
		$checks[]  = self::check(
			'image_alts',
			__( 'Image alt text', 'elementor-mcp' ),
			( 0 === $missing_alt ) ? 'pass' : 'fail',
			sprintf( /* translators: 1: missing, 2: total */ __( '%1$d of %2$d images are missing alt text.', 'elementor-mcp' ), $missing_alt, $total_img ),
			( 0 === $missing_alt ) ? '' : __( 'Add descriptive alt text to every meaningful image.', 'elementor-mcp' )
		);

		// Internal links.
		$internal = 0;
		foreach ( $ex['links'] as $l ) {
			if ( ! empty( $l['internal'] ) ) {
				$internal++;
			}
		}
		$checks[] = self::check(
			'internal_links',
			__( 'Internal links', 'elementor-mcp' ),
			( $internal >= 1 ) ? 'pass' : 'warn',
			sprintf( /* translators: %d: count */ __( '%d internal link(s).', 'elementor-mcp' ), $internal ),
			( $internal >= 1 ) ? '' : __( 'Add at least one internal link to related content.', 'elementor-mcp' )
		);

		// Word count.
		$wc       = (int) $ex['word_count'];
		$checks[] = self::check(
			'word_count',
			__( 'Content length', 'elementor-mcp' ),
			( $wc >= 300 ) ? 'pass' : ( $wc >= 150 ? 'warn' : 'fail' ),
			sprintf( /* translators: %d: words */ __( '%d words of content.', 'elementor-mcp' ), $wc ),
			( $wc >= 300 ) ? '' : __( 'Thin content — aim for 300+ words where appropriate.', 'elementor-mcp' )
		);

		// Target keyword usage.
		if ( '' !== $target ) {
			$tk          = self::mb_lower( $target );
			$title_has   = false !== mb_strpos( self::mb_lower( (string) ( $seo['title'] ?? '' ) ), $tk );
			$desc_has    = false !== mb_strpos( self::mb_lower( (string) ( $seo['description'] ?? '' ) ), $tk );
			$h1_text     = '';
			foreach ( $ex['headings'] as $h ) {
				if ( 1 === (int) $h['level'] ) {
					$h1_text = $h['text'];
					break;
				}
			}
			$h1_has   = false !== mb_strpos( self::mb_lower( $h1_text ), $tk );
			$hits     = ( $title_has ? 1 : 0 ) + ( $desc_has ? 1 : 0 ) + ( $h1_has ? 1 : 0 );
			$checks[] = self::check(
				'keyword_usage',
				__( 'Target keyword usage', 'elementor-mcp' ),
				( $hits >= 2 ) ? 'pass' : ( $hits >= 1 ? 'warn' : 'fail' ),
				sprintf(
					/* translators: 1: keyword, 2: in title, 3: in h1, 4: in description */
					__( '"%1$s" — title: %2$s, H1: %3$s, meta: %4$s.', 'elementor-mcp' ),
					$target,
					$title_has ? '✓' : '✗',
					$h1_has ? '✓' : '✗',
					$desc_has ? '✓' : '✗'
				),
				( $hits >= 2 ) ? '' : __( 'Use the target keyword in the title, H1, and meta description.', 'elementor-mcp' )
			);
		}

		return array(
			'score'   => self::score( $checks ),
			'checks'  => $checks,
			'summary' => self::summary( $checks ),
		);
	}

	/**
	 * Frequency keyword + bigram extraction.
	 *
	 * @param array $ex    Content_Extractor output.
	 * @param int   $limit Max keywords.
	 * @return array
	 */
	public static function rank_keywords( array $ex, int $limit = 20 ): array {
		$text = '';
		foreach ( $ex['headings'] as $h ) {
			$text .= ' ' . $h['text'];
		}
		foreach ( $ex['text_blocks'] as $t ) {
			$text .= ' ' . $t['text'];
		}

		$tokens = self::tokenize( $text );
		$total  = count( $tokens );

		// Unigrams (stop-word filtered, length >= 3).
		$freq = array();
		foreach ( $tokens as $w ) {
			if ( mb_strlen( $w ) < 3 || self::is_stopword( $w ) ) {
				continue;
			}
			$freq[ $w ] = ( $freq[ $w ] ?? 0 ) + 1;
		}
		arsort( $freq );

		$keywords = array();
		foreach ( array_slice( $freq, 0, $limit, true ) as $term => $count ) {
			$keywords[] = array(
				'term'  => $term,
				'count' => $count,
				'score' => $total > 0 ? round( $count / $total, 4 ) : 0,
			);
		}

		// Bigrams (neither word a stop-word).
		$bg = array();
		for ( $i = 0, $n = count( $tokens ) - 1; $i < $n; $i++ ) {
			$a = $tokens[ $i ];
			$b = $tokens[ $i + 1 ];
			if ( mb_strlen( $a ) < 3 || mb_strlen( $b ) < 3 || self::is_stopword( $a ) || self::is_stopword( $b ) ) {
				continue;
			}
			$key        = $a . ' ' . $b;
			$bg[ $key ] = ( $bg[ $key ] ?? 0 ) + 1;
		}
		arsort( $bg );
		$bigrams = array();
		foreach ( array_slice( $bg, 0, $limit, true ) as $term => $count ) {
			if ( $count < 2 ) {
				continue; // Only surface phrases that recur.
			}
			$bigrams[] = array( 'term' => $term, 'count' => $count );
		}

		return array(
			'keywords'    => $keywords,
			'bigrams'     => $bigrams,
			'total_words' => $total,
		);
	}

	/**
	 * Proposes an SEO title + meta description from page content.
	 *
	 * @param array  $ex        Content_Extractor output.
	 * @param array  $seo       Seo_Meta output.
	 * @param string $target    Optional target keyword.
	 * @param string $site_name Optional site name to append to the title.
	 * @return array
	 */
	public static function propose_meta( array $ex, array $seo, string $target = '', string $site_name = '' ): array {
		$notes = array();

		// Base title: first H1, else existing SEO title, else first heading.
		$base = '';
		foreach ( $ex['headings'] as $h ) {
			if ( 1 === (int) $h['level'] ) {
				$base = $h['text'];
				break;
			}
		}
		if ( '' === $base ) {
			$base = trim( (string) ( $seo['title'] ?? '' ) );
		}
		if ( '' === $base && ! empty( $ex['headings'] ) ) {
			$base = $ex['headings'][0]['text'];
		}
		if ( '' === $base ) {
			$base = __( 'Untitled', 'elementor-mcp' );
			$notes[] = __( 'No heading found — title falls back to a placeholder.', 'elementor-mcp' );
		}

		$title = $base;
		if ( '' !== $site_name && ( self::mb_len( $base ) + 3 + self::mb_len( $site_name ) ) <= 60 ) {
			$title = $base . ' | ' . $site_name;
		}
		$title = self::truncate( $title, 60 );

		// Description: first substantial text block, keyword-front-loaded.
		$body = '';
		foreach ( $ex['text_blocks'] as $t ) {
			if ( self::mb_len( $t['text'] ) >= 40 ) {
				$body = $t['text'];
				break;
			}
		}
		if ( '' === $body && ! empty( $ex['text_blocks'] ) ) {
			$body = $ex['text_blocks'][0]['text'];
		}

		$desc = $body;
		if ( '' !== $target && false === mb_strpos( self::mb_lower( $desc ), self::mb_lower( $target ) ) ) {
			$desc = rtrim( ucfirst( $target ), '.' ) . ': ' . $desc;
			$notes[] = __( 'Target keyword front-loaded into the description.', 'elementor-mcp' );
		}
		$desc = self::truncate( $desc, 155 );
		if ( '' === $desc ) {
			$notes[] = __( 'No body text found to build a description from.', 'elementor-mcp' );
		}

		return array(
			'proposed_title'       => $title,
			'proposed_description' => $desc,
			'title_length'         => self::mb_len( $title ),
			'description_length'   => self::mb_len( $desc ),
			'notes'                => $notes,
		);
	}

	/**
	 * Builds JSON-LD structured data.
	 *
	 * @param string $type     Requested type or 'auto'.
	 * @param array  $ex       Content_Extractor output.
	 * @param array  $seo      Seo_Meta output.
	 * @param array  $business Business NAP object.
	 * @param array  $faqs     FAQ pairs.
	 * @param string $url      Page URL.
	 * @return array
	 */
	public static function build_jsonld( string $type, array $ex, array $seo, array $business, array $faqs, string $url ): array {
		$notes = array();
		$name  = '';
		foreach ( $ex['headings'] as $h ) {
			if ( 1 === (int) $h['level'] ) {
				$name = $h['text'];
				break;
			}
		}
		if ( '' === $name ) {
			$name = trim( (string) ( $seo['title'] ?? '' ) );
		}

		// Resolve 'auto'.
		if ( '' === $type || 'auto' === $type ) {
			if ( ! empty( $business ) ) {
				$type = 'LocalBusiness';
			} elseif ( ! empty( $faqs ) ) {
				$type = 'FAQPage';
			} else {
				$type = 'Article';
			}
			$notes[] = sprintf( /* translators: %s: type */ __( 'Auto-detected schema type: %s.', 'elementor-mcp' ), $type );
		}

		switch ( $type ) {
			case 'LocalBusiness':
				if ( empty( $business ) ) {
					$notes[] = __( 'LocalBusiness needs a business object (name/address/phone) — emitting a minimal stub.', 'elementor-mcp' );
				}
				$schema = array(
					'@context' => 'https://schema.org',
					'@type'    => 'LocalBusiness',
					'name'     => $business['name'] ?? $name,
				);
				if ( ! empty( $business['phone'] ) ) {
					$schema['telephone'] = (string) $business['phone'];
				}
				if ( ! empty( $business['url'] ) || '' !== $url ) {
					$schema['url'] = (string) ( $business['url'] ?? $url );
				}
				if ( ! empty( $business['price_range'] ) ) {
					$schema['priceRange'] = (string) $business['price_range'];
				}
				$address = array_filter( array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $business['street'] ?? '',
					'addressLocality' => $business['locality'] ?? '',
					'addressRegion'   => $business['region'] ?? '',
					'postalCode'      => $business['postal_code'] ?? '',
					'addressCountry'  => $business['country'] ?? '',
				) );
				if ( count( $address ) > 1 ) {
					$schema['address'] = $address;
				}
				break;

			case 'FAQPage':
				$entities = array();
				foreach ( $faqs as $f ) {
					if ( ! is_array( $f ) || empty( $f['question'] ) || empty( $f['answer'] ) ) {
						continue;
					}
					$entities[] = array(
						'@type'          => 'Question',
						'name'           => (string) $f['question'],
						'acceptedAnswer' => array( '@type' => 'Answer', 'text' => (string) $f['answer'] ),
					);
				}
				if ( empty( $entities ) ) {
					$notes[] = __( 'FAQPage needs a faqs array of {question, answer} — none provided.', 'elementor-mcp' );
				}
				$schema = array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => $entities,
				);
				break;

			case 'Product':
				$schema = array(
					'@context'    => 'https://schema.org',
					'@type'       => 'Product',
					'name'        => $name,
					'description' => (string) ( $seo['description'] ?? '' ),
				);
				break;

			case 'Service':
				$schema = array(
					'@context'    => 'https://schema.org',
					'@type'       => 'Service',
					'name'        => $name,
					'description' => (string) ( $seo['description'] ?? '' ),
				);
				break;

			case 'Article':
			default:
				$type   = 'Article';
				$schema = array(
					'@context'         => 'https://schema.org',
					'@type'            => 'Article',
					'headline'         => $name,
					'description'      => (string) ( $seo['description'] ?? '' ),
				);
				if ( '' !== $url ) {
					$schema['mainEntityOfPage'] = $url;
				}
				break;
		}

		$jsonld = function_exists( 'wp_json_encode' )
			? wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			: json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		return array(
			'detected_type' => $type,
			'jsonld'        => is_string( $jsonld ) ? $jsonld : '',
			'insert_hint'   => __( 'Insert inside a <script type="application/ld+json"> tag in the page head or via your SEO plugin.', 'elementor-mcp' ),
			'notes'         => $notes,
		);
	}

	// -------------------------------------------------------------------------
	// Internal scoring/text utilities
	// -------------------------------------------------------------------------

	/**
	 * Builds one check entry.
	 *
	 * @param string $id      Check id.
	 * @param string $label   Human label.
	 * @param string $status  pass|warn|fail|inconclusive.
	 * @param string $detail  Detail line.
	 * @param string $rec     Recommendation (empty when passing).
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
	 * Computes a 0-100 score (pass=1, warn=0.5, fail/inconclusive=0).
	 *
	 * @param array $checks Checks.
	 * @return int
	 */
	private static function score( array $checks ): int {
		if ( empty( $checks ) ) {
			return 0;
		}
		$sum = 0.0;
		foreach ( $checks as $c ) {
			$sum += ( 'pass' === $c['status'] ) ? 1.0 : ( 'warn' === $c['status'] ? 0.5 : 0.0 );
		}
		return (int) round( 100 * $sum / count( $checks ) );
	}

	/**
	 * Tallies pass/warn/fail counts.
	 *
	 * @param array $checks Checks.
	 * @return array
	 */
	private static function summary( array $checks ): array {
		$s = array( 'passes' => 0, 'warnings' => 0, 'failures' => 0 );
		foreach ( $checks as $c ) {
			if ( 'pass' === $c['status'] ) {
				$s['passes']++;
			} elseif ( 'warn' === $c['status'] ) {
				$s['warnings']++;
			} else {
				$s['failures']++;
			}
		}
		return $s;
	}

	/**
	 * Lowercases a string (mbstring-aware).
	 *
	 * @param string $s Input.
	 * @return string
	 */
	private static function mb_lower( string $s ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/**
	 * String length (mbstring-aware).
	 *
	 * @param string $s Input.
	 * @return int
	 */
	private static function mb_len( string $s ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s );
	}

	/**
	 * Truncates to a max length on a word boundary, no trailing partial word.
	 *
	 * @param string $s   Input.
	 * @param int    $max Max length.
	 * @return string
	 */
	private static function truncate( string $s, int $max ): string {
		$s = trim( preg_replace( '/\s+/u', ' ', $s ) );
		if ( self::mb_len( $s ) <= $max ) {
			return $s;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max, 'UTF-8' ) : substr( $s, 0, $max );
		$sp  = mb_strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > 0 ) {
			$cut = mb_substr( $cut, 0, $sp, 'UTF-8' );
		}
		return rtrim( $cut, " ,.;:" );
	}

	/**
	 * Tokenizes text into lowercase word tokens.
	 *
	 * @param string $text Input.
	 * @return string[]
	 */
	private static function tokenize( string $text ): array {
		$text   = self::mb_lower( $text );
		$text   = preg_replace( '/[^\p{L}\p{N}\s\-]+/u', ' ', $text );
		$tokens = preg_split( '/\s+/u', trim( (string) $text ) );
		return array_values( array_filter( (array) $tokens, static function ( $t ) {
			return '' !== $t;
		} ) );
	}

	/**
	 * Whether a word is a common English stop-word.
	 *
	 * @param string $w Word (lowercase).
	 * @return bool
	 */
	private static function is_stopword( string $w ): bool {
		static $stop = null;
		if ( null === $stop ) {
			$stop = array_flip( array(
				'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'her', 'was', 'one', 'our',
				'out', 'his', 'has', 'had', 'how', 'its', 'who', 'get', 'use', 'your', 'with', 'this', 'that', 'from',
				'they', 'will', 'have', 'what', 'when', 'were', 'them', 'then', 'than', 'into', 'more', 'some', 'such',
				'only', 'just', 'also', 'over', 'most', 'been', 'here', 'their', 'there', 'about', 'would', 'these',
				'which', 'while', 'where', 'every', 'other', 'could', 'should', 'after', 'before', 'because',
			) );
		}
		return isset( $stop[ $w ] );
	}
}
