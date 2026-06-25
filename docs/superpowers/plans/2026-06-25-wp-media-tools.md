# WordPress Media Library Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add 3 MCP tools (`get-media`, `update-media`, `delete-media`) to the existing Media Library ability group so an agent can fetch attachment detail, edit metadata (alt/title/caption/description), and delete attachments — `get`/`update` enabled by default, `delete` disabled-by-default and gated behind an explicit `confirm:true`.

**Architecture:** Extend `EMCP_Tools_Media_Library_Abilities` (already holds `list-media`) with three `register_*` + `execute_*` methods and a `resolve_attachment()` helper, built on WP core attachment functions. No new registrar/bootstrap wiring (the group is already registered). Essentials gains get/update; admin catalog gains all 3; `delete-media` is seeded disabled-by-default (DEFAULTS_VERSION 6→7).

**Tech Stack:** PHP 8.2, WordPress core (`get_post`, `wp_get_attachment_metadata`, `wp_get_attachment_image_src`, `wp_get_attachment_url`, `wp_update_post`, `update_post_meta`, `wp_delete_attachment`), the `emcp_tools_register_ability()` shim, PHPUnit function-stub harness.

**Spec:** `docs/superpowers/specs/2026-06-25-wp-media-tools-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `includes/abilities/class-media-library-abilities.php` | MODIFY — +3 tools (`get-media`, `update-media`, `delete-media`) + `resolve_attachment()` helper. |
| `tests/bootstrap.php` | MODIFY — add attachment-function stubs (`wp_get_attachment_metadata`, `wp_get_attachment_image_src`, `wp_get_attachment_url`, `wp_delete_attachment`, `get_post_field`). |
| `tests/unit/media/MediaToolsTest.php` | NEW — execute-path tests. |
| `tests/unit/capabilities/MediaCapabilityTest.php` | NEW — per-tool capability + confirm gate. |
| `includes/class-plugin.php` | MODIFY — essentials += `get-media`, `update-media`. |
| `includes/admin/class-admin.php` | MODIFY — Media catalog entries; `DEFAULTS_VERSION` 6→7; `media_write_tool_slugs()`; v7 seed. |
| `phpunit.xml` | MODIFY — add a `Media` testsuite. |
| `CHANGELOG.md`, `README.md`, `readme.txt`, `CLAUDE.md` | MODIFY — fold into the single v3.0.0 entry. |

**Conventions:** match the existing class (tabs, `emcp_tools_register_ability()`, `'category' => 'emcp-tools'`, text domain `emcp-tools`, meta `annotations` + `show_in_rest`). The class's `get_ability_names()` is a hardcoded array — add the three slugs there and a `register_*` call each in `register()`.

---

## Task 1: Test harness — attachment-function stubs

**Files:** Modify `tests/bootstrap.php`

The harness already has `get_post` (reads `$GLOBALS['_wp_posts']`), `wp_update_post` (returns the ID), `update_post_meta`/`get_post_meta` (recording stubs), and `wp_get_attachment_image_url`. Add the remaining attachment functions.

- [ ] **Step 1: Check for pre-existing stubs**

Run: `grep -n "function wp_get_attachment_metadata\|function wp_delete_attachment\|function wp_get_attachment_image_src\|function get_post_field\|function wp_get_attachment_url" tests/bootstrap.php`
Expected: no matches.

- [ ] **Step 2: Add the stubs**

In `tests/bootstrap.php`, in the global `namespace { … }` block (near the other attachment stubs like `wp_get_attachment_image_url`), add:

```php
	if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
		function wp_get_attachment_metadata( $id = 0, $unfiltered = false ) {
			return $GLOBALS['_wp_attachment_meta'][ (int) $id ] ?? array();
		}
	}
	if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
		function wp_get_attachment_image_src( $id, $size = 'thumbnail', $icon = false ) {
			$map = $GLOBALS['_wp_attachment_src'][ (int) $id ][ is_string( $size ) ? $size : 'full' ] ?? null;
			return $map ? $map : false; // [ url, width, height, is_intermediate ]
		}
	}
	if ( ! function_exists( 'wp_get_attachment_url' ) ) {
		function wp_get_attachment_url( $id = 0 ) {
			return $GLOBALS['_wp_attachment_url'][ (int) $id ] ?? ( 'http://example.com/wp-content/uploads/file-' . (int) $id . '.jpg' );
		}
	}
	if ( ! function_exists( 'get_post_field' ) ) {
		function get_post_field( $field, $post = 0, $context = 'display' ) {
			$p = is_object( $post ) ? $post : get_post( $post );
			return $p && isset( $p->$field ) ? $p->$field : '';
		}
	}
	if ( ! function_exists( 'wp_delete_attachment' ) ) {
		function wp_delete_attachment( $id, $force_delete = false ) {
			$GLOBALS['_wp_deleted_attachments'][] = array( 'id' => (int) $id, 'force' => (bool) $force_delete );
			return get_post( $id ) ?: (object) array( 'ID' => (int) $id );
		}
	}
```

- [ ] **Step 3: Verify the harness still loads**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist 2>&1 | tail -3`
Expected: `OK (556 tests, …)` unchanged.

- [ ] **Step 4: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: attachment-function stubs for the Media Library tools"
```

---

## Task 2: get-media

**Files:** Modify `includes/abilities/class-media-library-abilities.php`; Create `tests/unit/media/MediaToolsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/unit/media/MediaToolsTest.php`:

```php
<?php
/**
 * Execute-path tests for the Media Library write/detail tools.
 * @group media
 * @package EMCP_Tools\Tests\Media
 */
namespace EMCP_Tools\Tests\Media;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class MediaToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Media_Library_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_posts'] = array(
			77 => (object) array( 'ID' => 77, 'post_type' => 'attachment', 'post_title' => 'Sunset', 'post_name' => 'sunset', 'post_excerpt' => 'A caption', 'post_content' => 'A description', 'post_mime_type' => 'image/jpeg', 'post_date' => '2026-01-01 00:00:00', 'post_author' => 1, 'post_parent' => 0, 'post_status' => 'inherit' ),
			88 => (object) array( 'ID' => 88, 'post_type' => 'page', 'post_title' => 'Not media', 'post_status' => 'publish' ),
		);
		$GLOBALS['_wp_attachment_meta'] = array( 77 => array( 'width' => 1200, 'height' => 800, 'file' => '2026/01/sunset.jpg', 'filesize' => 24000, 'sizes' => array( 'thumbnail' => array( 'file' => 'sunset-150x150.jpg', 'width' => 150, 'height' => 150 ) ) ) );
		$GLOBALS['_wp_attachment_src']  = array( 77 => array( 'full' => array( 'http://x/sunset.jpg', 1200, 800, false ), 'thumbnail' => array( 'http://x/sunset-150x150.jpg', 150, 150, true ) ) );
		$GLOBALS['_wp_attachment_url']  = array( 77 => 'http://x/sunset.jpg' );
		$GLOBALS['_wp_deleted_attachments'] = array();
		$this->ability = new \EMCP_Tools_Media_Library_Abilities( $this->make_data_stub() );
		$this->ability->register();
	}

	/** @test */
	public function test_registers_four_tools(): void {
		$names = $this->ability->get_ability_names();
		foreach ( array( 'list-media', 'get-media', 'update-media', 'delete-media' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 4, $names );
	}

	/** @test */
	public function test_get_media_returns_detail_and_sizes(): void {
		$out = $this->ability->execute_get_media( array( 'id' => 77 ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 77, $out['id'] );
		$this->assertSame( 'Sunset', $out['title'] );
		$this->assertSame( 'A caption', $out['caption'] );
		$this->assertSame( 'A description', $out['description'] );
		$this->assertSame( 1200, $out['width'] );
		$this->assertArrayHasKey( 'thumbnail', $out['sizes'] );
		$this->assertSame( 150, $out['sizes']['thumbnail']['width'] );
	}

	/** @test */
	public function test_get_media_rejects_non_attachment(): void {
		$this->assertWPError( $this->ability->execute_get_media( array( 'id' => 88 ) ), 'not_an_attachment' );
	}

	/** @test */
	public function test_get_media_requires_id(): void {
		$this->assertWPError( $this->ability->execute_get_media( array() ), 'missing_params' );
	}
}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_get_media / count 4)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/media/MediaToolsTest.php`
Expected: FAIL.

- [ ] **Step 3: Add the slugs + register call + get-media implementation**

In `includes/abilities/class-media-library-abilities.php`:

(a) Update `get_ability_names()`:
```php
	public function get_ability_names(): array {
		return array(
			'emcp-tools/list-media',
			'emcp-tools/get-media',
			'emcp-tools/update-media',
			'emcp-tools/delete-media',
		);
	}
```

(b) Update `register()`:
```php
	public function register(): void {
		$this->register_list_media();
		$this->register_get_media();
		$this->register_update_media();
		$this->register_delete_media();
	}
```

(c) Add a write/edit permission helper next to `check_read_permission()`:
```php
	/**
	 * Edit permission for a specific attachment (attachments are posts).
	 *
	 * @since 3.0.0
	 * @param array|null $input Tool input; may carry an `id`.
	 * @return bool
	 */
	public function check_edit_permission( $input = null ): bool {
		$id = absint( $input['id'] ?? 0 );
		return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
	}

	/**
	 * Delete permission for a specific attachment.
	 *
	 * @since 3.0.0
	 * @param array|null $input Tool input; may carry an `id`.
	 * @return bool
	 */
	public function check_delete_permission( $input = null ): bool {
		$id = absint( $input['id'] ?? 0 );
		return $id ? current_user_can( 'delete_post', $id ) : current_user_can( 'delete_posts' );
	}

	/**
	 * Resolve an id to an attachment post, or a WP_Error.
	 *
	 * @since 3.0.0
	 * @param mixed $raw
	 * @return object|\WP_Error WP_Post-like on success.
	 */
	private function resolve_attachment( $raw ) {
		$id = absint( $raw );
		if ( ! $id ) {
			return new \WP_Error( 'missing_params', __( 'An attachment "id" is required.', 'emcp-tools' ) );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'attachment_not_found', __( 'Attachment not found.', 'emcp-tools' ) );
		}
		if ( 'attachment' !== ( $post->post_type ?? '' ) ) {
			return new \WP_Error( 'not_an_attachment', __( 'That ID is not a media attachment.', 'emcp-tools' ) );
		}
		return $post;
	}
```

(d) Add the `get-media` registration + execute (before the final class `}`):
```php
	// -------------------------------------------------------------------------
	// get-media
	// -------------------------------------------------------------------------

	private function register_get_media(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-media',
			array(
				'label'               => __( 'Get Media', 'emcp-tools' ),
				'description'         => __( 'Returns full detail for one Media Library attachment: title, URL, every registered image size (url + dimensions), mime type, filesize, alt text, caption, description, and raw attachment metadata. The single-item complement to list-media.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_media' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer', 'description' => __( 'Attachment ID.', 'emcp-tools' ) ) ),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ),
					'url' => array( 'type' => 'string' ), 'mime_type' => array( 'type' => 'string' ),
					'alt' => array( 'type' => 'string' ), 'caption' => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ), 'width' => array( 'type' => 'integer' ),
					'height' => array( 'type' => 'integer' ), 'sizes' => array( 'type' => 'object' ),
					'metadata' => array( 'type' => 'object' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_get_media( $input ) {
		$post = $this->resolve_attachment( $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id   = (int) $post->ID;
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();

		$sizes = array();
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( array_keys( $meta['sizes'] ) as $size ) {
				$src = wp_get_attachment_image_src( $id, $size );
				if ( is_array( $src ) ) {
					$sizes[ $size ] = array( 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
				}
			}
		}

		$author_id  = (int) ( $post->post_author ?? 0 );
		$author_obj = $author_id && function_exists( 'get_userdata' ) ? get_userdata( $author_id ) : null;

		$filesize = 0;
		if ( isset( $meta['filesize'] ) ) {
			$filesize = (int) $meta['filesize'];
		}

		return array(
			'id'          => $id,
			'title'       => (string) $post->post_title,
			'slug'        => (string) $post->post_name,
			'url'         => (string) wp_get_attachment_url( $id ),
			'mime_type'   => (string) ( $post->post_mime_type ?? '' ),
			'filesize'    => $filesize,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'date'        => (string) ( $post->post_date ?? '' ),
			'author'      => array( 'id' => $author_id, 'name' => $author_obj ? (string) $author_obj->display_name : '' ),
			'post_parent' => (int) ( $post->post_parent ?? 0 ),
			'width'       => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'      => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'sizes'       => $sizes,
			'metadata'    => $meta,
		);
	}
```

- [ ] **Step 4: Run — expect PASS (4 tests: registers-four + 3 get-media)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/media/MediaToolsTest.php`

> Note: at this point `register_update_media()` and `register_delete_media()` are referenced by `register()` but not yet defined → fatal. To keep the task self-contained, add EMPTY stub methods for those two now (just `private function register_update_media(): void {}` and `private function register_delete_media(): void {}`), and TEMPORARILY change `test_registers_four_tools` to assert `list-media` + `get-media` are present (drop the count-4). Tasks 3 & 4 replace the stubs with real registrations and Task 4 restores the count assertion.

Expected after the temporary adjustments: PASS.

- [ ] **Step 5: Add autoloader entry (if needed) + commit**

The harness autoloads via the map in `tests/bootstrap.php`. `EMCP_Tools_Media_Library_Abilities` is likely already registered (it's an existing class) — confirm with `grep -n "Media_Library_Abilities" tests/bootstrap.php`; add the map entry only if missing.

```bash
git add includes/abilities/class-media-library-abilities.php tests/unit/media/MediaToolsTest.php tests/bootstrap.php
git commit -m "feat(media): get-media — full attachment detail with size map"
```

---

## Task 3: update-media

**Files:** Modify `includes/abilities/class-media-library-abilities.php`; append to `tests/unit/media/MediaToolsTest.php`

- [ ] **Step 1: Append failing tests**

```php
	/** @test */
	public function test_update_media_writes_only_passed_fields(): void {
		$out = $this->ability->execute_update_media( array( 'id' => 77, 'alt' => 'Sunset over the sea', 'title' => 'Sunset HQ' ) );
		$this->assertNotWPError( $out );
		$this->assertContains( 'alt', $out['updated'] );
		$this->assertContains( 'title', $out['updated'] );
		$this->assertNotContains( 'caption', $out['updated'] );
		// alt written via _wp_attachment_image_alt meta.
		$altCalls = array_filter( $GLOBALS['_wp_meta_calls'], fn( $c ) => ( $c['meta_key'] ?? '' ) === '_wp_attachment_image_alt' && ( $c['post_id'] ?? 0 ) === 77 );
		$this->assertNotEmpty( $altCalls );
	}

	/** @test */
	public function test_update_media_rejects_non_attachment(): void {
		$this->assertWPError( $this->ability->execute_update_media( array( 'id' => 88, 'alt' => 'x' ) ), 'not_an_attachment' );
	}

	/** @test */
	public function test_update_media_requires_id(): void {
		$this->assertWPError( $this->ability->execute_update_media( array( 'alt' => 'x' ) ), 'missing_params' );
	}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_update_media)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/media/MediaToolsTest.php`

- [ ] **Step 3: Replace the empty `register_update_media()` stub with the real registration + execute**

```php
	// -------------------------------------------------------------------------
	// update-media
	// -------------------------------------------------------------------------

	private function register_update_media(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-media',
			array(
				'label'               => __( 'Update Media', 'emcp-tools' ),
				'description'         => __( 'Updates an existing attachment\'s metadata: title, alt text, caption, and/or description. Only the fields you pass change. Great for fixing missing alt text (accessibility/SEO) on images already in the library.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_media' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer', 'description' => __( 'Attachment ID.', 'emcp-tools' ) ),
						'title'       => array( 'type' => 'string' ),
						'alt'         => array( 'type' => 'string', 'description' => __( 'Alt text (accessibility).', 'emcp-tools' ) ),
						'caption'     => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'id' => array( 'type' => 'integer' ), 'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'alt' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ),
					'caption' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_update_media( $input ) {
		$post = $this->resolve_attachment( $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id      = (int) $post->ID;
		$updated = array();

		$postarr = array( 'ID' => $id );
		if ( array_key_exists( 'title', $input ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $input['title'] );
			$updated[]             = 'title';
		}
		if ( array_key_exists( 'caption', $input ) ) {
			$postarr['post_excerpt'] = sanitize_text_field( (string) $input['caption'] );
			$updated[]               = 'caption';
		}
		if ( array_key_exists( 'description', $input ) ) {
			$postarr['post_content'] = (string) $input['description'];
			$updated[]               = 'description';
		}
		if ( count( $postarr ) > 1 ) {
			$res = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		if ( array_key_exists( 'alt', $input ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $input['alt'] ) );
			$updated[] = 'alt';
		}

		$fresh = get_post( $id );
		return array(
			'id'          => $id,
			'updated'     => $updated,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'title'       => (string) ( $fresh->post_title ?? $post->post_title ),
			'caption'     => (string) ( $fresh->post_excerpt ?? $post->post_excerpt ),
			'description' => (string) ( $fresh->post_content ?? $post->post_content ),
		);
	}
```

- [ ] **Step 4: Run — expect PASS**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/media/MediaToolsTest.php`

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-media-library-abilities.php tests/unit/media/MediaToolsTest.php
git commit -m "feat(media): update-media — edit alt/title/caption/description on an attachment"
```

---

## Task 4: delete-media (confirm-gated, disabled-by-default)

**Files:** Modify `includes/abilities/class-media-library-abilities.php`; append to `tests/unit/media/MediaToolsTest.php`

- [ ] **Step 1: Restore the count assertion + append failing tests**

Restore `test_registers_four_tools` to its full form (4-slug loop + `assertCount( 4, $names )`). Append:

```php
	/** @test */
	public function test_delete_media_requires_confirm(): void {
		$out = $this->ability->execute_delete_media( array( 'id' => 77 ) );
		$this->assertWPError( $out, 'confirmation_required' );
		$this->assertSame( array(), $GLOBALS['_wp_deleted_attachments'] );
	}

	/** @test */
	public function test_delete_media_deletes_with_confirm_and_force(): void {
		$out = $this->ability->execute_delete_media( array( 'id' => 77, 'confirm' => true, 'force' => true ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['success'] );
		$this->assertSame( 'deleted', $out['deleted'] );
		$this->assertSame( array( array( 'id' => 77, 'force' => true ) ), $GLOBALS['_wp_deleted_attachments'] );
	}

	/** @test */
	public function test_delete_media_rejects_non_attachment(): void {
		$this->assertWPError( $this->ability->execute_delete_media( array( 'id' => 88, 'confirm' => true ) ), 'not_an_attachment' );
	}

	/** @test */
	public function test_delete_media_requires_id(): void {
		$this->assertWPError( $this->ability->execute_delete_media( array( 'confirm' => true ) ), 'missing_params' );
	}
```

- [ ] **Step 2: Run — expect FAIL (undefined execute_delete_media)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/media/MediaToolsTest.php`

- [ ] **Step 3: Replace the empty `register_delete_media()` stub with the real registration + execute**

```php
	// -------------------------------------------------------------------------
	// delete-media
	// -------------------------------------------------------------------------

	private function register_delete_media(): void {
		emcp_tools_register_ability(
			'emcp-tools/delete-media',
			array(
				'label'               => __( 'Delete Media', 'emcp-tools' ),
				'description'         => __( 'Deletes a Media Library attachment. DESTRUCTIVE and effectively permanent — WordPress bypasses Trash for media unless MEDIA_TRASH is defined. Requires confirm:true. Pass force:true to skip Trash even when MEDIA_TRASH is on.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_media' ),
				'permission_callback' => array( $this, 'check_delete_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer', 'description' => __( 'Attachment ID.', 'emcp-tools' ) ),
						'confirm' => array( 'type' => 'boolean', 'description' => __( 'Must be true to proceed (acknowledges permanent deletion).', 'emcp-tools' ) ),
						'force'   => array( 'type' => 'boolean', 'description' => __( 'Skip Trash even when MEDIA_TRASH is defined. Default: false.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id', 'confirm' ),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array(
					'success' => array( 'type' => 'boolean' ), 'id' => array( 'type' => 'integer' ),
					'deleted' => array( 'type' => 'string' ),
				) ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public function execute_delete_media( $input ) {
		$post = $this->resolve_attachment( $input['id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( true !== ( $input['confirm'] ?? null ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Deleting media is permanent on most sites (WordPress bypasses Trash unless MEDIA_TRASH is defined). Pass confirm:true to proceed.', 'emcp-tools' ) );
		}
		$id      = (int) $post->ID;
		$force   = ! empty( $input['force'] );
		$trashed = ! $force && defined( 'MEDIA_TRASH' ) && MEDIA_TRASH;
		$res     = wp_delete_attachment( $id, $force );
		return array(
			'success' => (bool) $res,
			'id'      => $id,
			'deleted' => $trashed ? 'trashed' : 'deleted',
		);
	}
```

- [ ] **Step 4: Run — expect PASS (all MediaToolsTest)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/media/MediaToolsTest.php`
Expected: PASS (11 tests). Full suite green.

- [ ] **Step 5: Commit**

```bash
git add includes/abilities/class-media-library-abilities.php tests/unit/media/MediaToolsTest.php
git commit -m "feat(media): delete-media — confirm-gated, destructive, disabled-by-default"
```

---

## Task 5: Capability tests

**Files:** Create `tests/unit/capabilities/MediaCapabilityTest.php`

- [ ] **Step 1: Write the test**

```php
<?php
/**
 * Capability gating for the Media Library write/detail tools.
 * @group capabilities
 * @group media
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class MediaCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Media_Library_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_Media_Library_Abilities( $this->make_data_stub() );
		$this->a->register();
	}

	/** @test */
	public function test_read_requires_edit_posts(): void {
		$this->deny_all_caps();
		$this->assertFalse( $this->a->check_read_permission() );
		$this->allow_caps( 'edit_posts' );
		$this->assertTrue( $this->a->check_read_permission() );
	}

	/** @test */
	public function test_edit_requires_edit_post_on_id(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->check_edit_permission( array( 'id' => 77 ) ) );
		$this->allow_caps( 'edit_posts', 'edit_post' );
		$this->assertTrue( $this->a->check_edit_permission( array( 'id' => 77 ) ) );
	}

	/** @test */
	public function test_delete_requires_delete_post_on_id(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->check_delete_permission( array( 'id' => 77 ) ) );
		$this->allow_caps( 'delete_post' );
		$this->assertTrue( $this->a->check_delete_permission( array( 'id' => 77 ) ) );
	}
}
```

- [ ] **Step 2: Run — expect PASS (3 tests)**

Run: `/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist tests/unit/capabilities/MediaCapabilityTest.php`

- [ ] **Step 3: Commit**

```bash
git add tests/unit/capabilities/MediaCapabilityTest.php
git commit -m "test(media): per-tool capability gating + edit/delete-on-id"
```

---

## Task 6: Wiring — essentials, admin catalog, defaults v7, phpunit suite

**Files:** Modify `includes/class-plugin.php`, `includes/admin/class-admin.php`, `phpunit.xml`

- [ ] **Step 1: Essentials**

In `includes/class-plugin.php` `get_essential_tool_slugs()`, after the plugins/themes block (`'emcp-tools/list-themes',`), add:
```php

			// WordPress media (2 — detail read + metadata edit; delete is opt-in).
			'emcp-tools/get-media',
			'emcp-tools/update-media',
```

- [ ] **Step 2: Admin catalog — add the 3 to the Media category**

In `includes/admin/class-admin.php` `get_tool_catalog()`, find the category that contains `'emcp-tools/list-media'` (the Media / Stock category). Add these three entries inside that category's `'tools'` array, after `list-media`:
```php
					'emcp-tools/get-media'    => array(
						'label'       => __( 'Get Media', 'emcp-tools' ),
						'description' => __( 'Full detail of one attachment (sizes, metadata, alt/caption).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-media' => array(
						'label'       => __( 'Update Media', 'emcp-tools' ),
						'description' => __( 'Edit an attachment\'s alt text, title, caption, description.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-media' => array(
						'label'       => __( 'Delete Media', 'emcp-tools' ),
						'description' => __( 'Delete an attachment (permanent; requires confirm).', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
```

> If `list-media` is NOT in the curated catalog (it may be excluded as a non-essential tool), instead add a new `'wp_media'` category as a sibling of `'wp_packages'`:
> ```php
> 			'wp_media'         => array(
> 				'label' => __( 'Media Library', 'emcp-tools' ),
> 				'tools' => array(
> 					'emcp-tools/get-media'    => array( 'label' => __( 'Get Media', 'emcp-tools' ), 'description' => __( 'Full detail of one attachment.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
> 					'emcp-tools/update-media' => array( 'label' => __( 'Update Media', 'emcp-tools' ), 'description' => __( 'Edit alt/title/caption/description.', 'emcp-tools' ), 'badges' => array() ),
> 					'emcp-tools/delete-media' => array( 'label' => __( 'Delete Media', 'emcp-tools' ), 'description' => __( 'Delete an attachment (permanent; requires confirm).', 'emcp-tools' ), 'badges' => array( 'destructive' ) ),
> 				),
> 			),
> ```
> Decide by grepping: `grep -n "list-media" includes/admin/class-admin.php`. If present in `get_tool_catalog()`, extend that category; else add `wp_media`. The admin catalog↔registry drift test requires every catalog slug to be a registered ability and (for curated coverage) is tolerant of registry tools absent from the catalog — match whichever pattern `list-media` follows.

- [ ] **Step 3: Defaults v7 — seed delete-media disabled**

In `includes/admin/class-admin.php`: change `const DEFAULTS_VERSION = 6;` to `7`. Add a slugs method next to `package_write_tool_slugs()`:
```php
	/**
	 * Media tool slugs that ship disabled-by-default. Only delete-media (the
	 * destructive, effectively-permanent op); get-media / update-media stay on.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function media_write_tool_slugs(): array {
		return array( 'emcp-tools/delete-media' );
	}
```
In `maybe_apply_default_disabled_tools()`, after the `if ( $applied < 6 ) { … }` block, add:
```php
			// v7 — delete-media ships disabled-by-default (permanent deletion).
			if ( $applied < 7 ) {
				$add = array_merge( $add, self::media_write_tool_slugs() );
			}
```

- [ ] **Step 4: phpunit.xml — add Media suite**

After the `<testsuite name="Packages">` block, add:
```xml
        <testsuite name="Media">
            <directory>tests/unit/media</directory>
        </testsuite>
```

- [ ] **Step 5: Lint + run both configs**

Run:
```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/class-plugin.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe -l includes/admin/class-admin.php
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: no syntax errors; both suites green (the admin catalog↔registry drift test still passes).

- [ ] **Step 6: Commit**

```bash
git add includes/class-plugin.php includes/admin/class-admin.php phpunit.xml
git commit -m "feat(media): essentials + admin category + delete-media disabled-by-default (defaults v7)"
```

---

## Task 7: Documentation (fold into the single v3.0.0 entry)

**Files:** `CHANGELOG.md`, `readme.txt`, `README.md`, `CLAUDE.md`

This domain is NOT a new version — it folds into the EXISTING `[3.0.0]` / `= 3.0.0 =` entries. Do not bump any version.

- [ ] **Step 1: CHANGELOG.md** — inside the existing `## [3.0.0]` → `### Added`, add:

```markdown
- **WordPress Media Library tools — beyond Elementor, domain 4.** Three MCP tools to manage existing attachments: `get-media` (full detail of one attachment — every registered size, dimensions, metadata, alt/caption/description), `update-media` (edit title, alt text, caption, description — a one-call accessibility/SEO fix for library images), and `delete-media` (delete an attachment; **destructive and effectively permanent** — WordPress bypasses Trash for media unless `MEDIA_TRASH` is defined). `get-media`/`update-media` are enabled by default; `delete-media` ships **disabled-by-default** and additionally requires an explicit `confirm:true`. URL uploads continue to use the existing `sideload-image`.
```

- [ ] **Step 2: readme.txt** — inside the existing `= 3.0.0 =` block add a `* Added: WordPress Media Library tools …` line (mirror the CHANGELOG summary); append a clause to the description paragraph noting media-attachment management; add a Key Features bullet after the Plugins & Themes one:
```
* **Media Library (beyond Elementor)** — Fetch full attachment detail, edit metadata (alt text, title, caption, description), and delete attachments over MCP. get/update are enabled by default; delete is disabled-by-default and requires explicit confirmation. (v3.0.0)
```

- [ ] **Step 3: README.md** — add a Media Library feature bullet alongside the Plugins & Themes one, and a short tool-table section (3 tools) near the Plugins & Themes section. Keep versions at v3.0.0.

- [ ] **Step 4: CLAUDE.md** — append a domain-4 clause to the v3.0.0 overview sentence; update the "Current status" line to include the Media Library domain; update the count narrative (beyond-Elementor surface now also adds 3 Media tools, of which 2 enabled-by-default and 1 disabled-by-default); add a "WordPress Media Library — domain 4 (3 tools)" section with the tool table + the three-way delete gate (disabled-by-default + `delete_post` + `confirm:true`).

- [ ] **Step 5: Confirm + commit**

Run `grep -rn "3\.1\.0\|3\.2\.0" CHANGELOG.md readme.txt README.md CLAUDE.md` → expect no matches.
```bash
git add CHANGELOG.md readme.txt README.md CLAUDE.md
git commit -m "docs: document WordPress Media Library tools (folded into v3.0.0)"
```

---

## Task 8: Thorough verification (PHP + live MCP + browser)

Verification only — no new production code unless a fix is needed.

- [ ] **Step 1: Full PHPUnit, both configs**

```
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml.dist
/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe vendor/bin/phpunit --configuration phpunit.xml
```
Expected: both green, including the new Media + capability tests.

- [ ] **Step 2: Live MCP `tools/list`** — pipe initialize + notifications/initialized + tools/list into the WP-CLI stdio server (`--server=emcp-tools-server --user=admin --path=f:/laragon/www/msrplugins`, output to a Windows-absolute path). Confirm `emcp-tools-get-media` + `emcp-tools-update-media` appear; `emcp-tools-delete-media` appears only if the v7 default seeding has not yet run on this site (it's disabled-by-default once an admin loads the Tools page).

- [ ] **Step 3: Live MCP `tools/call`** — pick a real attachment id from `wp post list --post_type=attachment --field=ID` (or `sideload-image` a throwaway): `get-media{id}` returns detail; `update-media{id, alt:"…"}` → verify with `wp post meta get <id> _wp_attachment_image_alt`, then restore the original alt; `delete-media{id}` (no confirm) → `confirmation_required` refusal; if you sideloaded a throwaway, `delete-media{id, confirm:true, force:true}` to clean it up and confirm with `wp post list`.

- [ ] **Step 4: Browser** — trigger the v7 seeding (load EMCP Tools → Tools); confirm the Media tools render: `get-media`/`update-media` enabled, `delete-media` present but off with the destructive badge; no PHP errors. Verify the disabled-by-default seeding via `wp option get emcp_tools_disabled_tools` containing `emcp-tools/delete-media` and `emcp_tools_defaults_applied` = 7.

- [ ] **Step 5: Report** — PHPUnit counts (both configs), live `tools/list` + `tools/call` outcomes (with any test attachment removed and alt restored), browser observation. Commit any fix with a clear message.
