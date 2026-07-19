<?php
/**
 * Elementor data access layer.
 *
 * Wraps Elementor internals to provide a clean API for reading and writing
 * Elementor page data, widget registrations, and element trees.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access layer wrapping Elementor's internal APIs.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Data {

	/**
	 * Gets the Elementor document for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return \Elementor\Core\Base\Document|\WP_Error The document instance or WP_Error.
	 */
	public function get_document( int $post_id ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( ! $document ) {
			return new \WP_Error(
				'document_not_found',
				sprintf(
					/* translators: %d: post ID */
					__( 'Elementor document not found for post ID %d.', 'emcp-tools' ),
					$post_id
				)
			);
		}

		return $document;
	}

	/**
	 * Gets the element tree for an Elementor page.
	 *
	 * Tries the Elementor document API first, falls back to reading raw
	 * post meta if the document returns empty data (common in CLI contexts).
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array|\WP_Error The elements data array or WP_Error.
	 */
	public function get_page_data( int $post_id ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$data = $document->get_elements_data();

		if ( is_array( $data ) && ! empty( $data ) ) {
			return $data;
		}

		// Fallback: read from raw post meta (handles CLI/proxy contexts).
		$raw = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! empty( $raw ) && is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Gets the page-level settings for an Elementor document.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array|\WP_Error The page settings array or WP_Error.
	 */
	public function get_page_settings( int $post_id ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return $document->get_settings();
	}

	/**
	 * Gets the document type for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string|\WP_Error The document type string or WP_Error.
	 */
	public function get_document_type( int $post_id ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return get_post_meta( $post_id, '_elementor_template_type', true );
	}

	/**
	 * Gets all registered Elementor widget types.
	 *
	 * @since 1.0.0
	 *
	 * @return \Elementor\Widget_Base[] Array of widget instances keyed by widget name.
	 */
	public function get_registered_widgets(): array {
		return \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
	}

	/**
	 * Gets the controls for a specific widget type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type The widget type name.
	 * @return array|\WP_Error The controls array or WP_Error if widget not found.
	 */
	public function get_widget_controls( string $widget_type ) {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );

		if ( ! $widget ) {
			return new \WP_Error(
				'widget_not_found',
				sprintf(
					/* translators: %s: widget type name */
					__( 'Widget type "%s" not found.', 'emcp-tools' ),
					$widget_type
				)
			);
		}

		return $widget->get_controls();
	}

	/**
	 * Recursively searches for an element by ID within an element tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data The element tree array.
	 * @param string $id   The element ID to find.
	 * @return array|null The element array if found, null otherwise.
	 */
	public function find_element_by_id( array $data, string $id ): ?array {
		foreach ( $data as $element ) {
			if ( isset( $element['id'] ) && $element['id'] === $id ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = $this->find_element_by_id( $element['elements'], $id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Saves page data using Elementor's native save mechanism.
	 *
	 * Tries document save() first (triggers CSS regeneration). If that fails
	 * (e.g. non-browser context like WP-CLI or REST API), falls back to direct
	 * meta update and manual CSS cache invalidation.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id The post ID.
	 * @param array $data    The elements data array.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function save_page_data( int $post_id, array $data ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		// Capture the prior Elementor data so the change ledger can offer a rollback.
		$emcp_before_raw = get_post_meta( $post_id, '_elementor_data', true );

		// Attempt native Elementor save (handles CSS regen, cache busting).
		// Elementor 4.0 atomic widgets THROW on invalid settings instead of
		// returning false, so catch it and return a clean error rather than
		// letting it fatal the whole request. The fallback meta-write below runs
		// ONLY for the no-exception falsy case (false OR null — valid data,
		// non-browser context), so invalid data is never written raw. Document::save()
		// can return null (not just false) in CLI/REST, hence `! $result`. (F-005, #36)
		try {
			$result = $document->save( array( 'elements' => $data ) );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'save_rejected',
				sprintf(
					/* translators: %s: error message from Elementor */
					__( 'Elementor rejected the element data: %s', 'emcp-tools' ),
					$e->getMessage()
				)
			);
		}

		// Verify the native save actually persisted our elements. Elementor's
		// Document::save() can return a truthy value in some 4.x / atomic / REST
		// contexts yet drop the elements so `_elementor_data` ends up empty —
		// a silent write failure the caller never sees (issue #98). Re-read and,
		// if we sent real elements but nothing landed, force the direct meta write
		// below rather than reporting a phantom success.
		$needs_fallback = ! $result;
		if ( ! $needs_fallback && ! empty( $data ) ) {
			$persisted_raw = get_post_meta( $post_id, '_elementor_data', true );
			$persisted     = ( is_string( $persisted_raw ) && '' !== $persisted_raw )
				? json_decode( $persisted_raw, true )
				: null;
			if ( empty( $persisted ) || ! is_array( $persisted ) ) {
				$needs_fallback = true;
			}
		}

		if ( $needs_fallback ) {
			// Fallback: direct meta write for non-browser contexts (CLI, REST proxy)
			// and for the silent-drop case above.
			$json = wp_json_encode( $data );

			if ( false === $json ) {
				return new \WP_Error(
					'json_encode_failed',
					__( 'Failed to encode element data as JSON.', 'emcp-tools' )
				);
			}

			update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );

			// Ensure Elementor meta flags are set.
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
			}

			// Invalidate Elementor CSS cache so it regenerates on next page view.
			delete_post_meta( $post_id, '_elementor_css' );

			$upload_dir = wp_get_upload_dir();
			$css_path   = $upload_dir['basedir'] . '/elementor/css/post-' . $post_id . '.css';
			if ( file_exists( $css_path ) ) {
				wp_delete_file( $css_path );
			}
		}

		// Record the edit to the unified change ledger (skipped during rollback).
		if ( class_exists( 'EMCP_Tools_Change_Log' ) && ! EMCP_Tools_Change_Log::$suppress ) {
			$emcp_before = array();
			if ( is_string( $emcp_before_raw ) && '' !== $emcp_before_raw ) {
				$emcp_decoded = json_decode( $emcp_before_raw, true );
				if ( is_array( $emcp_decoded ) ) {
					$emcp_before = $emcp_decoded;
				}
			}
			$emcp_title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
			EMCP_Tools_Change_Log::record( array(
				'domain'   => 'elementor',
				'action'   => 'page-edit',
				'target'   => trim( $emcp_title . ' (#' . $post_id . ')' ),
				'summary'  => sprintf( 'Edited Elementor page #%d', $post_id ),
				'rollback' => array( 'type' => 'elementor-data', 'post_id' => $post_id, 'before' => $emcp_before ),
			) );
		}

		return true;
	}

	/**
	 * Saves page-level settings.
	 *
	 * Tries native Elementor save first, falls back to direct meta for
	 * non-browser contexts (WP-CLI, REST API proxy).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id  The post ID.
	 * @param array $settings The page settings array.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function save_page_settings( int $post_id, array $settings ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$result = $document->save( array( 'settings' => $settings ) );

		if ( ! $result ) {
			// Fallback: merge settings into existing page settings meta.
			$existing = get_post_meta( $post_id, '_elementor_page_settings', true );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}

			$merged = array_merge( $existing, $settings );
			update_post_meta( $post_id, '_elementor_page_settings', $merged );

			// Invalidate CSS cache.
			delete_post_meta( $post_id, '_elementor_css' );
		}

		return true;
	}

	/**
	 * Inserts an element into the page data tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data      The element tree (passed by reference).
	 * @param string $parent_id The parent element ID. Empty string for top-level.
	 * @param array  $element   The element to insert.
	 * @param int    $position  The insertion position (-1 = append).
	 * @return bool True if inserted, false if parent not found.
	 */
	public function insert_element( array &$data, string $parent_id, array $element, int $position = -1 ): bool {
		// Top-level insertion.
		if ( empty( $parent_id ) ) {
			if ( $position < 0 || $position >= count( $data ) ) {
				$data[] = $element;
			} else {
				array_splice( $data, $position, 0, array( $element ) );
			}
			return true;
		}

		// Find parent and insert.
		foreach ( $data as &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $parent_id ) {
				if ( ! isset( $item['elements'] ) ) {
					$item['elements'] = array();
				}

				if ( $position < 0 || $position >= count( $item['elements'] ) ) {
					$item['elements'][] = $element;
				} else {
					array_splice( $item['elements'], $position, 0, array( $element ) );
				}

				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->insert_element( $item['elements'], $parent_id, $element, $position ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Removes an element from the page data tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data       The element tree (passed by reference).
	 * @param string $element_id The element ID to remove.
	 * @return bool True if removed, false if not found.
	 */
	public function remove_element( array &$data, string $element_id ): bool {
		foreach ( $data as $index => &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $element_id ) {
				array_splice( $data, $index, 1 );
				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->remove_element( $item['elements'], $element_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively reassigns fresh IDs to all elements in a tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array $elements The element tree.
	 * @return array The tree with new IDs.
	 */
	public function reassign_ids( array $elements ): array {
		foreach ( $elements as &$element ) {
			$element = $this->reassign_element_ids( $element );
		}
		unset( $element );

		return $elements;
	}

	/**
	 * Reassigns a fresh ID to a single element and all its children.
	 *
	 * @since 1.0.0
	 *
	 * @param array $element The element array.
	 * @return array The element with new IDs.
	 */
	public function reassign_element_ids( array $element ): array {
		$element['id'] = EMCP_Tools_Id_Generator::generate();

		// v4 atomic elements: local style classes are named `e-<id>-<hash>` and
		// belong to a single element. A fresh element id must get fresh local
		// classes, or the duplicate shares the source's local classes — causing
		// cross-element style bleed and Style Origin doubling (issue #97).
		if ( class_exists( 'EMCP_Tools_Atomic_Styles' ) ) {
			EMCP_Tools_Atomic_Styles::remap_local_classes( $element );
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = $this->reassign_ids( $element['elements'] );
		}

		return $element;
	}

	/**
	 * Recursively counts all elements in a tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array $elements The element tree.
	 * @return int Total count.
	 */
	public function count_elements( array $elements ): int {
		$count = count( $elements );

		foreach ( $elements as $element ) {
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$count += $this->count_elements( $element['elements'] );
			}
		}

		return $count;
	}

	/**
	 * Updates settings for a specific element in the tree.
	 *
	 * Modifies `$data` by reference. Returns true if element was found
	 * and updated, false if the element ID was not found.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data       The element tree (passed by reference).
	 * @param string $element_id The element ID to update.
	 * @param array  $settings   The settings to merge.
	 * @return bool True if updated, false if not found.
	 */
	public function update_element_settings( array &$data, string $element_id, array $settings ): bool {
		foreach ( $data as &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $element_id ) {
				if ( ! isset( $item['settings'] ) ) {
					$item['settings'] = array();
				}

				// Sibling-root keys: on v4 atomic elements the local `styles`
				// map and `editor_settings` (Navigator label = editor_settings.
				// title) live at the element ROOT, as siblings of `settings`.
				// An agent naturally nests them under `settings`; hoist them out
				// and deep-merge into the root so they actually persist instead
				// of being written to a dead `settings.styles` key (#72, #73).
				$touched_styles = false;
				foreach ( array( 'styles', 'editor_settings' ) as $root_key ) {
					if ( ! array_key_exists( $root_key, $settings ) ) {
						continue;
					}

					if ( 'styles' === $root_key ) {
						$touched_styles = true;
					}

					$incoming = $settings[ $root_key ];
					unset( $settings[ $root_key ] );

					if ( is_array( $incoming ) ) {
						$existing = isset( $item[ $root_key ] ) && is_array( $item[ $root_key ] ) ? $item[ $root_key ] : array();
						$item[ $root_key ] = self::deep_merge( $existing, $incoming );
					} else {
						$item[ $root_key ] = $incoming;
					}
				}

				// Containers: rewrite MCP shorthand keys (`justify_content`,
				// `align_items`, `align_content`) to Elementor's prefixed flex
				// keys before merging. Without this, the values are saved
				// but never read by Elementor's CSS generator (issue #32).
				if ( 'container' === ( $item['elType'] ?? '' ) ) {
					$settings = EMCP_Tools_Element_Factory::normalize_container_settings( $settings );
				}

				$item['settings'] = array_merge( $item['settings'], $settings );

				// v4 atomic: a local style class only renders when the element's
				// `classes` prop references it. An agent that writes a `styles`
				// map but forgets to add the class id to settings.classes gets a
				// silent no-op — the styles persist but never apply (#92). Wire
				// every local class id from the styles map into settings.classes.
				if ( $touched_styles ) {
					self::sync_local_class_refs( $item );
				}

				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->update_element_settings( $item['elements'], $element_id, $settings ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively merges $incoming into $existing. Associative arrays are merged
	 * key-by-key; lists (numeric-keyed arrays, e.g. a `variants` array) and
	 * scalars are replaced wholesale by the incoming value. This lets a partial
	 * `styles`/`editor_settings` update touch one class or key without dropping
	 * the siblings, while still replacing a variants list the caller supplied in
	 * full.
	 *
	 * @since 3.2.1
	 *
	 * @param array $existing The current value.
	 * @param array $incoming The value to merge in (wins on conflicts).
	 * @return array The merged array.
	 */
	private static function deep_merge( array $existing, array $incoming ): array {
		foreach ( $incoming as $key => $value ) {
			if (
				is_string( $key )
				&& isset( $existing[ $key ] )
				&& is_array( $existing[ $key ] )
				&& is_array( $value )
				&& self::is_assoc( $existing[ $key ] )
				&& self::is_assoc( $value )
			) {
				$existing[ $key ] = self::deep_merge( $existing[ $key ], $value );
			} else {
				$existing[ $key ] = $value;
			}
		}

		return $existing;
	}

	/**
	 * Whether an array is associative (has any string key / non-sequential
	 * integer keys). An empty array is treated as a list (not associative).
	 *
	 * @since 3.2.1
	 *
	 * @param array $arr The array to test.
	 * @return bool
	 */
	private static function is_assoc( array $arr ): bool {
		if ( array() === $arr ) {
			return false;
		}

		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Ensures every local style class in an atomic element's `styles` map is
	 * referenced by its `settings.classes` prop, so Elementor actually applies
	 * the styles at render time.
	 *
	 * In Elementor 4.0 (atomic), a per-element local class lives in the element
	 * root `styles` map keyed by an `e-<id>-<hash>` class id, with a sibling
	 * `settings.classes = { $$type:'classes', value:[ ...ids ] }` that lists the
	 * classes actually applied to the element. Writing a `styles` entry alone
	 * persists the definition but renders nothing until the id is also in
	 * `classes.value`. This wires up any missing references (idempotent) so a
	 * `styles` write is self-contained (#92).
	 *
	 * @since 3.4.1
	 *
	 * @param array $item Element structure (by reference).
	 */
	private static function sync_local_class_refs( array &$item ): void {
		if ( empty( $item['styles'] ) || ! is_array( $item['styles'] ) ) {
			return;
		}

		// Collect local class ids from the styles map (type:'class' only).
		$ids = array();
		foreach ( $item['styles'] as $key => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			if ( isset( $def['type'] ) && 'class' !== $def['type'] ) {
				continue;
			}
			$id = ( isset( $def['id'] ) && is_string( $def['id'] ) && '' !== $def['id'] )
				? $def['id']
				: ( is_string( $key ) ? $key : '' );
			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}

		if ( empty( $ids ) ) {
			return;
		}

		if ( ! isset( $item['settings'] ) || ! is_array( $item['settings'] ) ) {
			$item['settings'] = array();
		}
		$classes = ( isset( $item['settings']['classes'] ) && is_array( $item['settings']['classes'] ) )
			? $item['settings']['classes']
			: array();

		// Normalize to the atomic classes wrapper { $$type:'classes', value:[] }.
		if ( ! isset( $classes['$$type'] ) ) {
			$classes['$$type'] = 'classes';
		}
		if ( ! isset( $classes['value'] ) || ! is_array( $classes['value'] ) ) {
			$classes['value'] = array();
		}

		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $classes['value'], true ) ) {
				$classes['value'][] = $id;
			}
		}

		$item['settings']['classes'] = $classes;
	}
}
