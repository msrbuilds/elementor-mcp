<?php
/**
 * Curated tool metadata for the Tools admin screen.
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
 * Curated tool metadata for the Tools admin screen.
 */
trait EMCP_Tools_Admin_Catalog_Trait {

	/**
	 * The curated admin tool catalog: every tool grouped by category with its
	 * label, description, and badges for the Tools admin screen. This is the
	 * source of the admin-UI metadata; get_all_tools() keeps it honest against
	 * the ability registry.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, tools: array<string, array{label: string, description: string, badges: string[]}>}> Grouped tools.
	 */
	private function get_tool_catalog(): array {
		$tools = array(
			'query'            => array(
				'platform' => 'elementor',
				'label' => __( 'Query & Discovery', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-widgets'         => array(
						'label'       => __( 'List Widgets', 'emcp-tools' ),
						'description' => __( 'Lists all available Elementor widget types and their names.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-widget-schema'    => array(
						'label'       => __( 'Get Widget Schema', 'emcp-tools' ),
						'description' => __( 'Returns the JSON schema for a specific widget type.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-page-structure'   => array(
						'label'       => __( 'Get Page Structure', 'emcp-tools' ),
						'description' => __( 'Returns the full Elementor element tree for a page.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-page-snapshot'    => array(
						'label'       => __( 'Get Page Snapshot', 'emcp-tools' ),
						'description' => __( 'One normalized page digest: structure, tokens-in-use, responsive overrides, content outline, SEO-lite (+ opt-in performance/a11y/seo).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-element-settings' => array(
						'label'       => __( 'Get Element Settings', 'emcp-tools' ),
						'description' => __( 'Returns the settings of a specific element by ID.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-pages'           => array(
						'label'       => __( 'List Pages', 'emcp-tools' ),
						'description' => __( 'Lists all pages/posts that use Elementor.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-templates'       => array(
						'label'       => __( 'List Templates', 'emcp-tools' ),
						'description' => __( 'Lists all saved Elementor templates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-global-settings'  => array(
						'label'       => __( 'Get Global Settings', 'emcp-tools' ),
						'description' => __( 'Returns global colors, typography, and theme settings.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'redirects'        => array(
				'platform' => 'modules',
				'label' => __( 'Redirects', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-redirects'    => array(
						'label'       => __( 'List Redirects', 'emcp-tools' ),
						'description' => __( 'Lists the site\'s managed 301/302 redirects (source → target, code, hits).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/find-broken-links' => array(
						'label'       => __( 'Find Broken Links', 'emcp-tools' ),
						'description' => __( 'Scans published content for internal links to dead or already-redirected URLs. Read-only.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-redirect'   => array(
						'label'       => __( 'Create Redirect', 'emcp-tools' ),
						'description' => __( 'Creates a 301/302 redirect from an old path to a target URL or post. Disabled by default.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-redirect'   => array(
						'label'       => __( 'Update Redirect', 'emcp-tools' ),
						'description' => __( 'Updates an existing redirect by id. Disabled by default.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-redirect'   => array(
						'label'       => __( 'Delete Redirect', 'emcp-tools' ),
						'description' => __( 'Deletes a redirect by id. Reversible from History. Disabled by default.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			),
			'migrate'          => array(
				'platform' => 'modules',
				'label' => __( 'Backup & Migrate', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/create-backup' => array(
						'label'       => __( 'Create Backup', 'emcp-tools' ),
						'description' => __( 'Creates a portable .emcp backup (full/database/files) and returns its id + size. Non-destructive.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-backups'  => array(
						'label'       => __( 'List Backups', 'emcp-tools' ),
						'description' => __( 'Lists this site\'s .emcp backups. Read-only.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/migrate-site'  => array(
						'label'       => __( 'Migrate Site to Live', 'emcp-tools' ),
						'description' => __( 'Pushes this whole site to a paired live target and restores it there. Destructive on the destination; requires confirm. Disabled by default.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/sync-to-live'  => array(
						'label'       => __( 'Sync to Live', 'emcp-tools' ),
						'description' => __( 'Pushes a full or selective scope (chosen tables/files) to a paired live target. Destructive for the pushed scope; requires confirm. Disabled by default.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/list-syncable-changes' => array(
						'label'       => __( 'List Syncable Changes', 'emcp-tools' ),
						'description' => __( 'Lists pages/posts/CPTs changed locally since they were last synced to a paired live target. Read-only.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/sync-content-item' => array(
						'label'       => __( 'Sync Content Item to Live', 'emcp-tools' ),
						'description' => __( 'Pushes one page/post/CPT (content + fields + attached media) to a paired live target, upserting it and remapping media. Overwrites only that item; requires confirm. Disabled by default.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/discard-sync-change' => array(
						'label'       => __( 'Discard Sync Change', 'emcp-tools' ),
						'description' => __( 'Dismisses an item from the changes-to-sync list until it changes again. Local only.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'gutenberg_blocks' => array(
				'platform' => 'gutenberg',
				'label' => __( 'Gutenberg Blocks', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-blocks'      => array(
						'label'       => __( 'List Blocks', 'emcp-tools' ),
						'description' => __( 'Lists registered block types (name, title, category).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-block-schema' => array(
						'label'       => __( 'Get Block Schema', 'emcp-tools' ),
						'description' => __( 'Returns a block\'s attributes, supports, and a markup example.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-post-blocks'  => array(
						'label'       => __( 'Get Post Blocks', 'emcp-tools' ),
						'description' => __( 'Returns a post\'s block tree with an index path per block.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-patterns'    => array(
						'label'       => __( 'List Patterns', 'emcp-tools' ),
						'description' => __( 'Lists registered block patterns.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/add-block'        => array(
						'label'       => __( 'Add Block', 'emcp-tools' ),
						'description' => __( 'Inserts block markup into a post at a position.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-block'     => array(
						'label'       => __( 'Update Block', 'emcp-tools' ),
						'description' => __( 'Replaces the block at an index path with new markup.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/remove-block'     => array(
						'label'       => __( 'Remove Block', 'emcp-tools' ),
						'description' => __( 'Deletes the block at an index path.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/move-block'       => array(
						'label'       => __( 'Move Block', 'emcp-tools' ),
						'description' => __( 'Moves a block to a new position.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/duplicate-block'  => array(
						'label'       => __( 'Duplicate Block', 'emcp-tools' ),
						'description' => __( 'Clones the block at a path and inserts the copy after it.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/insert-pattern'   => array(
						'label'       => __( 'Insert Pattern', 'emcp-tools' ),
						'description' => __( 'Inserts a registered block pattern into a post.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_nav_menus'     => array(
				'platform' => 'wordpress',
				'label' => __( 'Navigation Menus', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/menu-read'  => array(
						'label'       => __( 'Menu Read', 'emcp-tools' ),
						'description' => __( 'Read nav menus: list menus, get a menu\'s nested item tree, list theme locations, render a menu to HTML. Call with no operation to list read operations.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/menu-write' => array(
						'label'       => __( 'Menu Write', 'emcp-tools' ),
						'description' => __( 'Manage nav menus: create/rename/delete menus, assign theme locations, and add/update/delete/reorder items. Call with no operation to list write operations.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_content'       => array(
				'platform' => 'wordpress',
				'label' => __( 'WordPress Content', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-post-types' => array(
						'label'       => __( 'List Post Types', 'emcp-tools' ),
						'description' => __( 'Lists registered post types (posts, pages, CPTs).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-taxonomies' => array(
						'label'       => __( 'List Taxonomies', 'emcp-tools' ),
						'description' => __( 'Lists taxonomies and optionally their terms.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-post'     => array(
						'label'       => __( 'Create Post', 'emcp-tools' ),
						'description' => __( 'Creates a post/page/CPT with content, terms, meta, featured image.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/get-post'        => array(
						'label'       => __( 'Get Post', 'emcp-tools' ),
						'description' => __( 'Returns a post\'s content, terms, meta, and featured image.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-page-html'   => array(
						'label'       => __( 'Get Page HTML', 'emcp-tools' ),
						'description' => __( 'Fetches chunked public front-end response HTML from this site. JavaScript is not executed.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-post'     => array(
						'label'       => __( 'Update Post', 'emcp-tools' ),
						'description' => __( 'Partial update of a post/page/CPT.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-posts'      => array(
						'label'       => __( 'List Posts', 'emcp-tools' ),
						'description' => __( 'Lists/searches posts, pages, or any CPT (compact).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/delete-post'     => array(
						'label'       => __( 'Delete Post', 'emcp-tools' ),
						'description' => __( 'Trashes (or force-deletes) a post.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/set-post-terms'  => array(
						'label'       => __( 'Set Post Terms', 'emcp-tools' ),
						'description' => __( 'Assigns category/tag/custom terms to a post.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_settings'      => array(
				'platform' => 'wordpress',
				'label' => __( 'WordPress Settings', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/get-settings'    => array(
						'label'       => __( 'Get Settings', 'emcp-tools' ),
						'description' => __( 'Reads curated site settings (general, reading, writing, discussion, media, permalinks).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-settings' => array(
						'label'       => __( 'Update Settings', 'emcp-tools' ),
						'description' => __( 'Updates curated site settings; auto-flushes rewrite rules on permalink changes.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'performance'      => array(
				'platform' => 'wordpress',
				'label' => __( 'Performance & Security', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/analyze-performance' => array(
						'label'       => __( 'Analyze Performance', 'emcp-tools' ),
						'description' => __( 'Audits server config, WordPress internals, and a target page; returns a scored report with recommendations.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/scan-security' => array(
						'label'       => __( 'Scan Security', 'emcp-tools' ),
						'description' => __( 'Scans for malware heuristics, core file integrity, configuration hardening, and outdated/abandoned software; returns a scored report with recommendations.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
				),
			),
			'filesystem'       => array(
				'platform' => 'wordpress',
				'danger'   => true,
				'label' => __( 'Filesystem', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/read-file'      => array( 'label' => __( 'Read File', 'emcp-tools' ),      'description' => __( 'Read a file in the WordPress install.', 'emcp-tools' ),          'badges' => array( 'read-only' ) ),
					'emcp-tools/list-directory' => array( 'label' => __( 'List Directory', 'emcp-tools' ), 'description' => __( 'List a directory in the WordPress install.', 'emcp-tools' ),      'badges' => array( 'read-only' ) ),
					'emcp-tools/search-files'   => array( 'label' => __( 'Search Files', 'emcp-tools' ),   'description' => __( 'Search file contents across the install.', 'emcp-tools' ),        'badges' => array( 'read-only' ) ),
					'emcp-tools/write-file'     => array( 'label' => __( 'Write File', 'emcp-tools' ),     'description' => __( 'Create/overwrite a file (backs up first). Disabled by default.', 'emcp-tools' ), 'badges' => array() ),
					'emcp-tools/edit-file'      => array( 'label' => __( 'Edit File', 'emcp-tools' ),      'description' => __( 'Replace a string in a file (backs up first). Disabled by default.', 'emcp-tools' ),  'badges' => array() ),
					'emcp-tools/delete-file'    => array( 'label' => __( 'Delete File', 'emcp-tools' ),    'description' => __( 'Delete a file (backs up; needs confirm). Disabled by default.', 'emcp-tools' ),     'badges' => array() ),
				),
			),
			'database'         => array(
				'platform' => 'wordpress',
				'danger'   => true,
				'label' => __( 'Database', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-tables'    => array( 'label' => __( 'List Tables', 'emcp-tools' ),    'description' => __( 'List database tables with sizes.', 'emcp-tools' ),                'badges' => array( 'read-only' ) ),
					'emcp-tools/describe-table' => array( 'label' => __( 'Describe Table', 'emcp-tools' ), 'description' => __( 'Show a table\'s columns and keys.', 'emcp-tools' ),               'badges' => array( 'read-only' ) ),
					'emcp-tools/query'          => array( 'label' => __( 'Query (read-only)', 'emcp-tools' ), 'description' => __( 'Run a read-only SQL query (SELECT/SHOW/etc.).', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/insert-row'     => array( 'label' => __( 'Insert Row', 'emcp-tools' ),     'description' => __( 'Insert a row (parameterized). Disabled by default.', 'emcp-tools' ),   'badges' => array() ),
					'emcp-tools/update-rows'    => array( 'label' => __( 'Update Rows', 'emcp-tools' ),    'description' => __( 'Update rows matching a WHERE. Disabled by default.', 'emcp-tools' ),   'badges' => array() ),
					'emcp-tools/delete-rows'    => array( 'label' => __( 'Delete Rows', 'emcp-tools' ),    'description' => __( 'Delete rows matching a WHERE (confirm). Disabled by default.', 'emcp-tools' ), 'badges' => array() ),
				),
			),
			'wpcli'            => array(
				'platform' => 'wordpress',
				'danger'   => true,
				'label' => __( 'WP-CLI', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/run-wp-cli'       => array( 'label' => __( 'Run WP-CLI Command', 'emcp-tools' ), 'description' => __( 'Run a wp-cli command (blocklist-guarded: no eval/shell/raw-SQL/config-writes). Disabled by default.', 'emcp-tools' ), 'badges' => array() ),
					'emcp-tools/dispatch-wp-cli'  => array( 'label' => __( 'Dispatch WP-CLI Job', 'emcp-tools' ), 'description' => __( 'Run a wp-cli command as a detached background job (long migrations / bulk tasks). Disabled by default.', 'emcp-tools' ), 'badges' => array() ),
					'emcp-tools/get-wp-cli-job'   => array( 'label' => __( 'Get WP-CLI Job', 'emcp-tools' ), 'description' => __( 'Poll a background job\'s status, exit code, and output.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/list-wp-cli-jobs' => array( 'label' => __( 'List WP-CLI Jobs', 'emcp-tools' ), 'description' => __( 'List recent WP-CLI background jobs.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
				),
			),
			'transactions'     => array(
				'platform' => 'modules',
				'label' => __( 'Changes & Rollback', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-changes'    => array( 'label' => __( 'List Changes', 'emcp-tools' ),    'description' => __( 'List recent AI-made changes (Elementor/filesystem/database), newest first.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/get-change'      => array( 'label' => __( 'Get Change', 'emcp-tools' ),      'description' => __( 'Full detail of one change-ledger entry, including its rollback reference.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/rollback-change' => array( 'label' => __( 'Roll Back Change', 'emcp-tools' ), 'description' => __( 'Undo one recorded change by id (page/file/database). Only reverts changes EMCP recorded.', 'emcp-tools' ), 'badges' => array() ),
				),
			),
			'search'           => array(
				'platform' => 'modules',
				'label' => __( 'Content Search', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/search-content'  => array( 'label' => __( 'Search Content', 'emcp-tools' ),  'description' => __( 'Search the site\'s pages, templates, widgets, and global styles to reuse existing content.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
					'emcp-tools/reindex-search'  => array( 'label' => __( 'Reindex Search', 'emcp-tools' ),  'description' => __( 'Rebuild the content-search index (also updates on save).', 'emcp-tools' ), 'badges' => array() ),
				),
			),
			'content_mirror'   => array(
				'platform' => 'modules',
				'label' => __( 'Content Mirror (Git)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/export-content'        => array( 'label' => __( 'Export Content', 'emcp-tools' ),        'description' => __( 'Export page/template content to git-trackable JSON files.', 'emcp-tools' ), 'badges' => array() ),
					'emcp-tools/restore-content'       => array( 'label' => __( 'Restore Content', 'emcp-tools' ),       'description' => __( 'Restore a page/template from its mirror file (file-based undo).', 'emcp-tools' ), 'badges' => array() ),
					'emcp-tools/list-content-exports'  => array( 'label' => __( 'List Content Exports', 'emcp-tools' ), 'description' => __( 'List the mirror files on disk.', 'emcp-tools' ), 'badges' => array( 'read-only' ) ),
				),
			),
			'wp_packages'      => array(
				'platform' => 'wordpress',
				'label' => __( 'Plugins & Themes', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-plugins'      => array(
						'label'       => __( 'List Plugins', 'emcp-tools' ),
						'description' => __( 'Lists installed plugins, status, versions, and updates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/search-plugins'    => array(
						'label'       => __( 'Search Plugins', 'emcp-tools' ),
						'description' => __( 'Searches the wordpress.org plugin directory.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/install-plugin'    => array(
						'label'       => __( 'Install Plugin', 'emcp-tools' ),
						'description' => __( 'Installs a plugin from wordpress.org by slug or from a confirmed, hash-verified Media Library ZIP.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/activate-plugin'   => array(
						'label'       => __( 'Activate Plugin', 'emcp-tools' ),
						'description' => __( 'Activates an installed plugin.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/deactivate-plugin' => array(
						'label'       => __( 'Deactivate Plugin', 'emcp-tools' ),
						'description' => __( 'Deactivates a plugin (never EMCP Tools or Elementor).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-plugin'     => array(
						'label'       => __( 'Update Plugin', 'emcp-tools' ),
						'description' => __( 'Updates a plugin to the latest wordpress.org version.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-plugin'     => array(
						'label'       => __( 'Delete Plugin', 'emcp-tools' ),
						'description' => __( 'Permanently deletes an inactive plugin.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/list-themes'       => array(
						'label'       => __( 'List Themes', 'emcp-tools' ),
						'description' => __( 'Lists installed themes, active status, and updates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/search-themes'     => array(
						'label'       => __( 'Search Themes', 'emcp-tools' ),
						'description' => __( 'Searches the wordpress.org theme directory.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/install-theme'     => array(
						'label'       => __( 'Install Theme', 'emcp-tools' ),
						'description' => __( 'Installs a theme from wordpress.org by slug or from a confirmed, hash-verified Media Library ZIP.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/switch-theme'      => array(
						'label'       => __( 'Switch Theme', 'emcp-tools' ),
						'description' => __( 'Activates an installed theme.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-theme'      => array(
						'label'       => __( 'Update Theme', 'emcp-tools' ),
						'description' => __( 'Updates a theme to the latest wordpress.org version.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-theme'      => array(
						'label'       => __( 'Delete Theme', 'emcp-tools' ),
						'description' => __( 'Permanently deletes an inactive theme.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			),
			'wp_users'         => array(
				'platform' => 'wordpress',
				'label' => __( 'Users', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-users'   => array(
						'label'       => __( 'List Users', 'emcp-tools' ),
						'description' => __( 'Lists users (admin-only); filter by role/search.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-user'     => array(
						'label'       => __( 'Get User', 'emcp-tools' ),
						'description' => __( 'Returns one user\'s profile detail.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-user'  => array(
						'label'       => __( 'Create User', 'emcp-tools' ),
						'description' => __( 'Creates a non-admin user; auto-password + email.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-user'  => array(
						'label'       => __( 'Update User', 'emcp-tools' ),
						'description' => __( 'Edits a non-admin user\'s profile (no role/password; admins refused).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'wp_acf'           => array(
				'platform' => 'plugins',
				'group'    => 'dynamic',
				'label'    => __( 'ACF (Advanced Custom Fields)', 'emcp-tools' ),
				'note'     => __( 'Plugin integrations are exposed as two tools, one Read, one Write. The AI calls a tool with an operation name; each tool bundles the operations listed on its card. Toggle a tool to allow or block all of its operations at once. Post-type & taxonomy operations need ACF 6.1+.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/acf-read'  => array(
						'label'       => __( 'ACF Read', 'emcp-tools' ),
						'description' => __( 'Read Advanced Custom Fields data, field groups, field values, options pages, and (ACF 6.1+) ACF-managed post types and taxonomies.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array(
							'list-field-groups',
							'get-field-group',
							'list-options-pages',
							'get-fields',
							'list-post-types',
							'get-post-type',
							'list-taxonomies',
							'get-taxonomy',
						),
					),
					'emcp-tools/acf-write' => array(
						'label'       => __( 'ACF Write', 'emcp-tools' ),
						'description' => __( 'Write Advanced Custom Fields data, field values, field groups, and (ACF 6.1+) ACF-managed post types and taxonomies. No delete operations; slugs and field keys are immutable.', 'emcp-tools' ),
						'badges'      => array(),
						'operations'  => array(
							'update-fields',
							'validate-fields',
							'batch-update-fields',
							'create-field-group',
							'update-field-group',
							'create-post-type',
							'update-post-type',
							'create-taxonomy',
							'update-taxonomy',
						),
					),
				),
			),
			'wp_woo'           => array(
				'platform' => 'plugins',
				'group'    => 'ecommerce',
				'pro'      => true,
				'label'    => __( 'WooCommerce', 'emcp-tools' ),
				'note'     => __( 'WooCommerce is exposed as two tools, one Read and one Write, over wc/v3. Native WooCommerce Brands add list/get/create/update operations with validated existing image attachments and no-write dry runs; unsupported third-party brand taxonomies are refused. Use the active SEO integration term tools for brand SEO. Money/irreversible operations (refunds, deletes, batch) require confirm:true. Requires WooCommerce active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/woo-read'  => array(
						'label'            => __( 'WooCommerce Read', 'emcp-tools' ),
						'description'      => __( 'Read products, native brands, variations, orders, refunds, customers, coupons, reports, settings, shipping, taxes, webhooks, and system status. Call with no operation to list all read operations.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-products', 'list-brands', 'get-brand', 'get-order', 'list-orders', 'list-customers', 'list-coupons', 'report-sales', 'get-settings', 'list-webhooks', 'system-status', '…' ),
						'available'        => self::woo_available(),
						'requires'         => array( 'name' => 'WooCommerce', 'kind' => 'plugin' ),
					),
					'emcp-tools/woo-write' => array(
						'label'            => __( 'WooCommerce Write', 'emcp-tools' ),
						'description'      => __( 'Plan and safely upsert structured product imports by ID or exact SKU, create and update native brands, and manage WooCommerce data. Imports are hash-bound and never delete. Refunds/deletes/batch require confirm:true.', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'plan-product-import', 'upsert-products', 'create-product', 'create-brand', 'update-brand', 'update-order', 'create-refund', 'create-customer', 'delete-order', 'update-setting', '…' ),
						'available'        => self::woo_available(),
						'requires'         => array( 'name' => 'WooCommerce', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_funnelkit'     => array(
				'platform' => 'plugins',
				'group'    => 'ecommerce',
				'pro'      => true,
				'label'    => __( 'FunnelKit', 'emcp-tools' ),
				'note'     => __( 'Read and build FunnelKit Funnel Builder funnels through FunnelKit\'s own controllers: funnels and steps, checkout products and fields, order bumps, upsells, opt-in and thank-you pages, A/B tests and the store checkout. Writes ship disabled; deletes need confirm, creates and updates accept dry_run, and every write is recorded in History. Requires FunnelKit Funnel Builder active; bumps, upsells and A/B tests need Funnel Builder Pro.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/funnelkit-read'  => array(
						'label'       => __( 'FunnelKit Read', 'emcp-tools' ),
						'description' => __( 'List funnels and ordered steps; read decoded funnel pages, checkout products with effective prices, checkout fieldsets, order bumps, upsells, A/B tests and analytics; export funnels as JSON.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'list-funnels', 'get-funnel', 'get-funnel-page', 'get-checkout-products', 'get-checkout-fields', 'list-order-bumps', 'get-experiment', 'export-funnels', '…' ),
						'available'   => self::funnelkit_available(),
						'requires'    => array( 'name' => 'FunnelKit Funnel Builder', 'kind' => 'plugin' ),
					),
					'emcp-tools/funnelkit-write' => array(
						'label'       => __( 'FunnelKit Write', 'emcp-tools' ),
						'description' => __( 'Create and change funnels, steps, checkout products and fields, order bumps, upsells, opt-in and thank-you pages, A/B tests and the store checkout; import templates and funnels. Deletes need confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
						'operations'  => array( 'create-funnel', 'import-template', 'create-step', 'set-checkout-products', 'create-order-bump', 'create-offer', 'create-experiment', 'import-funnels', 'delete-funnel', '…' ),
						'available'   => self::funnelkit_available(),
						'requires'    => array( 'name' => 'FunnelKit Funnel Builder', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_funnelkit_automations' => array(
				'platform' => 'plugins',
				'group'    => 'ecommerce',
				'pro'      => true,
				'label'    => __( 'FunnelKit Automations', 'emcp-tools' ),
				'note'     => __( 'Read and manage FunnelKit Automations: automations and their steps, contacts (compact rows), tags, lists, fields, broadcasts, templates, form feeds, link triggers, audiences, bulk actions and allowlisted settings. Writes ship disabled; anything that deletes or sends needs confirm, and History says which writes cannot be undone. API keys, connectors and licences are never reachable. Requires FunnelKit Automations active; messaging needs Automations Pro.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/funnelkit-automations-read'  => array(
						'label'       => __( 'FunnelKit Automations Read', 'emcp-tools' ),
						'description' => __( 'Automations and stats, contacts as compact rows, tags, lists, fields, audiences, broadcasts, templates, carts, email reports and allowlisted settings.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'list-automations', 'get-automation', 'list-contacts', 'get-contact', 'list-tags', 'list-lists', 'list-broadcasts', 'list-carts', 'get-automations-settings', '…' ),
						'available'   => self::funnelkit_automations_available(),
						'requires'    => array( 'name' => 'FunnelKit Automations', 'kind' => 'plugin' ),
					),
					'emcp-tools/funnelkit-automations-write' => array(
						'label'       => __( 'FunnelKit Automations Write', 'emcp-tools' ),
						'description' => __( 'Create and change automations and steps, contacts, tags, lists, fields, broadcasts, templates, form feeds, link triggers, audiences, bulk actions and settings. Deletes and sends need confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
						'operations'  => array( 'create-automation', 'add-step', 'toggle-automation', 'create-contact', 'assign-tags', 'create-tags', 'create-broadcast', 'send-broadcast', 'update-automations-settings', '…' ),
						'available'   => self::funnelkit_automations_available(),
						'requires'    => array( 'name' => 'FunnelKit Automations', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_polylang'      => array(
				'platform' => 'plugins',
				'group'    => 'multilingual',
				'pro'      => true,
				'label'    => __( 'Polylang', 'emcp-tools' ),
				'note'     => __( 'Make a site multilingual with Polylang: languages and settings, menus per language, and translations of Elementor and Gutenberg pages. create-translation copies a page into another language and links it; get-page-text and apply-page-text move its text in and out in one call each. Writes ship disabled; deleting a language and URL changes need confirm, and every other write can be undone in History. Requires Polylang (free) active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/polylang-read'  => array(
						'label'       => __( 'Polylang Read', 'emcp-tools' ),
						'description' => __( 'Languages and settings, translation groups, posts missing a translation, the translatable text of a page as segments, and string translations.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'get-setup', 'list-translations', 'find-missing', 'get-page-text', 'list-strings' ),
						'available'   => self::polylang_available(),
						'requires'    => array( 'name' => 'Polylang', 'kind' => 'plugin' ),
					),
					'emcp-tools/polylang-write' => array(
						'label'       => __( 'Polylang Write', 'emcp-tools' ),
						'description' => __( 'Copy a page into another language and link it, write its translated text back, publish it, and manage languages, links, term translations, strings, menus and settings. delete-language and URL changes need confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
						'operations'  => array( 'create-translation', 'apply-page-text', 'set-status', 'add-language', 'assign-language', 'link-translations', 'create-term-translation', 'update-strings', 'set-menu-location', 'update-settings', '…' ),
						'available'   => self::polylang_available(),
						'requires'    => array( 'name' => 'Polylang', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_translatepress' => array(
				'platform' => 'plugins',
				'group'    => 'multilingual',
				'pro'      => true,
				'label'    => __( 'TranslatePress', 'emcp-tools' ),
				'note'     => __( 'Translate a site that uses TranslatePress without its visual editor: read every string of a page in the translation language with its sentence context, write the translations back in one all-or-nothing call, translate theme and plugin strings found by search, follow progress and set up the language. Translations are saved as machine translated until reviewed, a reviewed one is never overwritten without asking, and every write except removing a language can be undone in History. Writes ship disabled. Requires TranslatePress (free) active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/translatepress-read'  => array(
						'label'       => __( 'TranslatePress Read', 'emcp-tools' ),
						'description' => __( 'The language setup, every string of a page with its context and version, string search, gettext strings and translation progress.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'get-setup', 'get-page-strings', 'search-strings', 'get-progress' ),
						'available'   => self::translatepress_available(),
						'requires'    => array( 'name' => 'TranslatePress', 'kind' => 'plugin' ),
					),
					'emcp-tools/translatepress-write' => array(
						'label'       => __( 'TranslatePress Write', 'emcp-tools' ),
						'description' => __( 'Write and review translations of page and gettext strings, and add, update, remove or make default the translation language. remove-language and set-default-language need confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
						'operations'  => array( 'translate-strings', 'review-strings', 'add-language', 'update-language', 'remove-language', 'set-default-language' ),
						'available'   => self::translatepress_available(),
						'requires'    => array( 'name' => 'TranslatePress', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_tablepress'    => array(
				'platform' => 'plugins',
				'group'    => 'tables',
				'pro'      => true,
				'label'    => __( 'TablePress', 'emcp-tools' ),
				'note'     => __( 'Build and edit TablePress tables: create a table from data, change it whole or cell by cell, add and remove rows and columns, import a CSV, HTML, JSON, XLSX or ODS file from pasted data or a URL, export it, style tables with TablePress custom CSS, and put a table on an Elementor or Gutenberg page. Every change to an existing table names the version it read, so a newer change is never overwritten by accident, and every write can be undone in History. Writes ship disabled. Requires TablePress (free) active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/tablepress-read'  => array(
						'label'       => __( 'TablePress Read', 'emcp-tools' ),
						'description' => __( 'The list of tables, a table\'s cells, options and version, exports, the rendered HTML, the pages that show a table, and the custom CSS.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'get-setup', 'list-tables', 'get-table', 'export-table', 'render-table', 'find-usage', 'get-custom-css' ),
						'available'   => self::tablepress_available(),
						'requires'    => array( 'name' => 'TablePress', 'kind' => 'plugin' ),
					),
					'emcp-tools/tablepress-write' => array(
						'label'       => __( 'TablePress Write', 'emcp-tools' ),
						'description' => __( 'Create, edit, copy, rename, delete and import tables, set the custom CSS and place a table on a page. change-table-id and delete-table need confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
						'operations'  => array( 'create-table', 'update-table', 'edit-cells', 'insert-rows', 'delete-rows', 'insert-columns', 'delete-columns', 'copy-table', 'change-table-id', 'delete-table', 'import-table', 'update-custom-css', 'place-table' ),
						'available'   => self::tablepress_available(),
						'requires'    => array( 'name' => 'TablePress', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_tutor'         => array(
				'platform' => 'plugins',
				'group'    => 'lms',
				'pro'      => true,
				'label'    => __( 'Tutor LMS', 'emcp-tools' ),
				'note'     => __( 'Build Tutor LMS courses: create a course with its details, add topics, lessons (with video and attachments) and quizzes (with questions and answers), set the order of the curriculum, publish it or move it to the trash, enrol students in free courses and read their progress. A change names only the fields it sets; everything else stays as it is. Every change to an existing course names the version it read, and every write can be undone in History; quiz work students have done is never undone. Writes ship disabled. Requires Tutor LMS (free) active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/tutor-read'  => array(
						'label'       => __( 'Tutor LMS Read', 'emcp-tools' ),
						'description' => __( 'The list of courses, a course\'s fields and curriculum with its version, lessons, quizzes, the students of a course and one student\'s progress.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'get-setup', 'list-courses', 'get-course', 'get-lesson', 'get-quiz', 'list-enrolments', 'get-student-progress' ),
						'available'   => self::tutor_available(),
						'requires'    => array( 'name' => 'Tutor LMS', 'kind' => 'plugin' ),
					),
					'emcp-tools/tutor-write' => array(
						'label'       => __( 'Tutor LMS Write', 'emcp-tools' ),
						'description' => __( 'Create and change courses, topics, lessons and quizzes, set the curriculum order, publish or trash courses, and enrol or un-enrol students in free courses. trash-course, delete-topic, delete-lesson, delete-quiz and cancel-enrolment need confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
						'operations'  => array( 'create-course', 'update-course', 'set-course-status', 'trash-course', 'create-topic', 'update-topic', 'delete-topic', 'create-lesson', 'update-lesson', 'delete-lesson', 'save-quiz', 'delete-quiz', 'reorder-contents', 'enrol-student', 'cancel-enrolment' ),
						'available'   => self::tutor_available(),
						'requires'    => array( 'name' => 'Tutor LMS', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_metabox'       => array(
				'platform' => 'plugins',
				'group'    => 'dynamic',
				'label'    => __( 'Meta Box', 'emcp-tools' ),
				'note'     => __( 'Plugin integrations are exposed as two tools, one Read, one Write. The AI calls a tool with an operation name; each tool bundles the operations listed on its card. Toggle a tool to allow or block all of its operations at once.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/metabox-read'  => array(
						'label'       => __( 'Meta Box Read', 'emcp-tools' ),
						'description' => __( 'Read Meta Box data, field groups, field definitions, and field values for posts and other supported object types.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array(
							'list-field-groups',
							'get-field-group',
							'get-fields',
						),
					),
					'emcp-tools/metabox-write' => array(
						'label'       => __( 'Meta Box Write', 'emcp-tools' ),
						'description' => __( 'Write Meta Box field values. No delete operations; unknown fields are skipped, not created.', 'emcp-tools' ),
						'badges'      => array(),
						'operations'  => array(
							'update-fields',
						),
					),
				),
			),
			'wp_ea'            => array(
				'platform' => 'plugins',
				'group'    => 'addons',
				'pro'      => true,
				'label'    => __( 'Essential Addons for Elementor', 'emcp-tools' ),
				'note'     => __( 'Discovery for the Essential Addons widget pack. Its widgets are placed with the standard Add Free Widget tool, so this adds no widget-adding tool of its own, just the catalog and a readable schema (an addon widget can carry 400+ controls).', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/essential-addons-read' => array(
						'label'            => __( 'Essential Addons Read', 'emcp-tools' ),
						'description'      => __( 'List Essential Addons widgets registered on this site and inspect a widget\'s content controls.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-widgets', 'get-widget-schema' ),
						'available'        => self::essential_addons_available(),
						'requires'         => array( 'name' => 'Essential Addons for Elementor', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_premium'       => array(
				'platform' => 'plugins',
				'group'    => 'addons',
				'pro'      => true,
				'label'    => __( 'Premium Addons for Elementor', 'emcp-tools' ),
				'note'     => __( 'Discovery for the Premium Addons widget pack. As with Essential Addons, widgets are placed with the standard Add Free Widget tool; this supplies the catalog and a curated schema.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/premium-addons-read' => array(
						'label'            => __( 'Premium Addons Read', 'emcp-tools' ),
						'description'      => __( 'List Premium Addons widgets registered on this site and inspect a widget\'s content controls.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-widgets', 'get-widget-schema' ),
						'available'        => self::premium_addons_available(),
						'requires'         => array( 'name' => 'Premium Addons for Elementor', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_uae'           => array(
				'platform' => 'plugins',
				'group'    => 'addons',
				'pro'      => true,
				'label'    => __( 'Ultimate Addons for Elementor', 'emcp-tools' ),
				'note'     => __( 'Ultimate Addons for Elementor (UAE, formerly Header Footer Elementor) exposed as two tools, one Read, one Write. UAE is both a widget pack and a template plugin: reads discover its widgets and list header/footer templates with their display conditions; writes create, update, retarget and delete templates. Widgets are placed, and template content built, with the normal Elementor tools. Delete requires confirm:true.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/uae-read'  => array(
						'label'            => __( 'Ultimate Addons for Elementor Read', 'emcp-tools' ),
						'description'      => self::uae_templates_available()
							? __( 'Discover UAE widgets, and list its header/footer/block templates with their type, status and display conditions.', 'emcp-tools' )
							: __( 'Discover the UAE widgets registered on this site and inspect the content controls of a widget.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => self::uae_templates_available()
							? array( 'list-widgets', 'get-widget-schema', 'list-templates', 'get-template' )
							: array( 'list-widgets', 'get-widget-schema' ),
						'available'        => self::uae_available(),
						'requires'         => array( 'name' => 'Ultimate Addons for Elementor', 'kind' => 'plugin' ),
					),
					'emcp-tools/uae-write' => array(
						'label'            => __( 'Ultimate Addons for Elementor Write', 'emcp-tools' ),
						'description'      => __( 'Create, update, retarget and delete UAE templates. These render site-wide, so this tool is off by default and delete needs confirmation.', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'create-template', 'update-template', 'set-display-conditions', 'delete-template' ),
						'available'        => self::uae_templates_available(),
						// Recorded even though the note below is written by hand:
						// the badge needs the name, and without it this card was
						// the one unavailable tool on the screen with no badge.
						'requires'         => array( 'name' => 'Ultimate Addons for Elementor', 'kind' => 'plugin' ),
						'unavailable_note' => self::uae_pro_available()
							? __( 'UAE templates come from the free Ultimate Addons for Elementor plugin. UAE Pro on its own supplies widgets, which the Read tool already covers.', 'emcp-tools' )
							: __( 'Install & activate Ultimate Addons for Elementor to enable this tool.', 'emcp-tools' ),
					),
				),
			),
			'wp_cf7'           => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'label'    => __( 'Contact Form 7', 'emcp-tools' ),
				'note'     => __( 'Contact Form 7 exposed as two tools, one Read, one Write. Reads list forms, fields, mail templates and messages; writes update mail, messages, and settings. CF7 stores no submissions, so there are no entry operations.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/cf7-read'  => array(
						'label'            => __( 'Contact Form 7 Read', 'emcp-tools' ),
						'description'      => __( 'Read CF7 forms, fields, mail templates, messages, and settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-notifications', 'get-settings' ),
						'available'        => self::cf7_available(),
						'requires'         => array( 'name' => 'Contact Form 7', 'kind' => 'plugin' ),
					),
					'emcp-tools/cf7-write' => array(
						'label'            => __( 'Contact Form 7 Write', 'emcp-tools' ),
						'description'      => __( 'Update CF7 mail templates, messages, and additional settings.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-notification', 'update-messages', 'update-form-settings' ),
						'available'        => self::cf7_available(),
						'requires'         => array( 'name' => 'Contact Form 7', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_wpforms'       => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'WPForms', 'emcp-tools' ),
				'note'     => __( 'WPForms exposed as two tools, one Read, one Write. Reads cover forms, fields, notifications, and entries (entries require WPForms Pro); writes update settings/notifications and manage entries. Requires WPForms active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/wpforms-read'  => array(
						'label'            => __( 'WPForms Read', 'emcp-tools' ),
						'description'      => __( 'Read WPForms forms, fields, notifications, and entries (entries require WPForms Pro).', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-notifications', 'list-entries', 'get-entry', 'get-settings' ),
						'available'        => self::wpforms_available(),
						'requires'         => array( 'name' => 'WPForms', 'kind' => 'plugin' ),
					),
					'emcp-tools/wpforms-write' => array(
						'label'            => __( 'WPForms Write', 'emcp-tools' ),
						'description'      => __( 'Update WPForms notifications, set entry status, and delete entries (confirm:true). Entry operations require WPForms Pro.', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'update-notification', 'update-entry-status', 'delete-entry' ),
						'available'        => self::wpforms_available(),
						'requires'         => array( 'name' => 'WPForms', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_gravityforms'  => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'Gravity Forms', 'emcp-tools' ),
				'note'     => __( 'Gravity Forms exposed as two tools, one Read, one Write, over the GFAPI. Reads cover forms, fields, notifications, and entries; writes set entry status and delete entries. Requires Gravity Forms active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/gravityforms-read'  => array(
						'label'            => __( 'Gravity Forms Read', 'emcp-tools' ),
						'description'      => __( 'Read Gravity Forms forms, fields, notifications, and entries.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-notifications', 'list-entries', 'get-entry', 'get-settings' ),
						'available'        => self::gravityforms_available(),
						'requires'         => array( 'name' => 'Gravity Forms', 'kind' => 'plugin' ),
					),
					'emcp-tools/gravityforms-write' => array(
						'label'            => __( 'Gravity Forms Write', 'emcp-tools' ),
						'description'      => __( 'Set Gravity Forms entry status (active/spam/trash) and delete entries (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'update-entry-status', 'delete-entry' ),
						'available'        => self::gravityforms_available(),
						'requires'         => array( 'name' => 'Gravity Forms', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_fluentforms'   => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'Fluent Forms', 'emcp-tools' ),
				'note'     => __( 'Fluent Forms exposed as two tools, one Read, one Write. Reads cover forms, fields, and submissions; writes set submission status and delete submissions. Requires Fluent Forms active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/fluentforms-read'  => array(
						'label'            => __( 'Fluent Forms Read', 'emcp-tools' ),
						'description'      => __( 'Read Fluent Forms forms, fields, and submissions.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-entries', 'get-entry' ),
						'available'        => self::fluentforms_available(),
						'requires'         => array( 'name' => 'Fluent Forms', 'kind' => 'plugin' ),
					),
					'emcp-tools/fluentforms-write' => array(
						'label'            => __( 'Fluent Forms Write', 'emcp-tools' ),
						'description'      => __( 'Set Fluent Forms submission status and delete submissions (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'update-entry-status', 'delete-entry' ),
						'available'        => self::fluentforms_available(),
						'requires'         => array( 'name' => 'Fluent Forms', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_ninjaforms'    => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'Ninja Forms', 'emcp-tools' ),
				'note'     => __( 'Ninja Forms exposed as two tools, one Read, one Write. Reads cover forms, fields, and submissions; writes delete submissions. Requires Ninja Forms active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/ninjaforms-read'  => array(
						'label'            => __( 'Ninja Forms Read', 'emcp-tools' ),
						'description'      => __( 'Read Ninja Forms forms, fields, and submissions.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-entries', 'get-entry' ),
						'available'        => self::ninjaforms_available(),
						'requires'         => array( 'name' => 'Ninja Forms', 'kind' => 'plugin' ),
					),
					'emcp-tools/ninjaforms-write' => array(
						'label'            => __( 'Ninja Forms Write', 'emcp-tools' ),
						'description'      => __( 'Delete Ninja Forms submissions (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'delete-entry' ),
						'available'        => self::ninjaforms_available(),
						'requires'         => array( 'name' => 'Ninja Forms', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_formidable'    => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'Formidable Forms', 'emcp-tools' ),
				'note'     => __( 'Formidable Forms exposed as two tools, one Read, one Write. Reads cover forms, fields, notifications, and entries; writes update notifications and delete entries. Requires Formidable Forms active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/formidable-read'  => array(
						'label'            => __( 'Formidable Forms Read', 'emcp-tools' ),
						'description'      => __( 'Read Formidable forms, fields, notifications, and entries.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-notifications', 'list-entries', 'get-entry' ),
						'available'        => self::formidable_available(),
						'requires'         => array( 'name' => 'Formidable Forms', 'kind' => 'plugin' ),
					),
					'emcp-tools/formidable-write' => array(
						'label'            => __( 'Formidable Forms Write', 'emcp-tools' ),
						'description'      => __( 'Delete Formidable entries (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'delete-entry' ),
						'available'        => self::formidable_available(),
						'requires'         => array( 'name' => 'Formidable Forms', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_metform'       => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'MetForm', 'emcp-tools' ),
				'note'     => __( 'MetForm exposed as two tools, one Read, one Write. Reads cover forms, fields, and entries; writes delete entries. Requires MetForm (and Elementor) active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/metform-read'  => array(
						'label'            => __( 'MetForm Read', 'emcp-tools' ),
						'description'      => __( 'Read MetForm forms, fields, and entries.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-entries', 'get-entry' ),
						'available'        => self::metform_available(),
						'requires'         => array( 'name' => 'MetForm', 'kind' => 'plugin' ),
					),
					'emcp-tools/metform-write' => array(
						'label'            => __( 'MetForm Write', 'emcp-tools' ),
						'description'      => __( 'Delete MetForm entries (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'delete-entry' ),
						'available'        => self::metform_available(),
						'requires'         => array( 'name' => 'MetForm', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_sureforms'     => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'SureForms', 'emcp-tools' ),
				'note'     => __( 'SureForms exposed as two tools, one Read, one Write. Reads cover forms, fields, and entries; writes set entry status and delete entries. Requires SureForms active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/sureforms-read'  => array(
						'label'            => __( 'SureForms Read', 'emcp-tools' ),
						'description'      => __( 'Read SureForms forms, fields, and entries.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-entries', 'get-entry' ),
						'available'        => self::sureforms_available(),
						'requires'         => array( 'name' => 'SureForms', 'kind' => 'plugin' ),
					),
					'emcp-tools/sureforms-write' => array(
						'label'            => __( 'SureForms Write', 'emcp-tools' ),
						'description'      => __( 'Set SureForms entry status and delete entries (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'update-entry-status', 'delete-entry' ),
						'available'        => self::sureforms_available(),
						'requires'         => array( 'name' => 'SureForms', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_forminator'    => array(
				'platform' => 'plugins',
				'group'    => 'forms',
				'pro'      => true,
				'label'    => __( 'Forminator', 'emcp-tools' ),
				'note'     => __( 'Forminator exposed as two tools, one Read, one Write. Reads cover forms (id, name, shortcode, fields) and submissions; writes delete a submission. Requires Forminator active.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/forminator-read'  => array(
						'label'            => __( 'Forminator Read', 'emcp-tools' ),
						'description'      => __( 'Read Forminator forms, fields, shortcodes, and submissions.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-forms', 'get-form', 'list-entries', 'get-entry' ),
						'available'        => self::forminator_available(),
						'requires'         => array( 'name' => 'Forminator', 'kind' => 'plugin' ),
					),
					'emcp-tools/forminator-write' => array(
						'label'            => __( 'Forminator Write', 'emcp-tools' ),
						'description'      => __( 'Delete a Forminator submission (confirm:true).', 'emcp-tools' ),
						'badges'           => array( 'destructive' ),
						'operations'       => array( 'delete-entry' ),
						'available'        => self::forminator_available(),
						'requires'         => array( 'name' => 'Forminator', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_slimseo'       => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'label'    => __( 'Slim SEO', 'emcp-tools' ),
				'note'     => __( 'Slim SEO exposed as two tools, one Read, one Write. Read and write the SEO metadata (title, description, canonical, robots, social) Slim SEO stores for posts and terms, plus its site settings.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/slimseo-read'  => array(
						'label'            => __( 'Slim SEO Read', 'emcp-tools' ),
						'description'      => __( 'Read Slim SEO post/term SEO metadata and site settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-term-seo', 'get-settings' ),
						'available'        => self::slimseo_available(),
						'requires'         => array( 'name' => 'Slim SEO', 'kind' => 'plugin' ),
					),
					'emcp-tools/slimseo-write' => array(
						'label'            => __( 'Slim SEO Write', 'emcp-tools' ),
						'description'      => __( 'Update Slim SEO post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo', 'update-term-seo' ),
						'available'        => self::slimseo_available(),
						'requires'         => array( 'name' => 'Slim SEO', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_visibility'    => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'label'    => __( 'Visibility', 'emcp-tools' ),
				'note'     => __( 'Visibility (native-aeo-pack) exposed as two tools, one Read, one Write. Read and write the per-post SEO metadata it stores: title, description, canonical, robots flags, Open Graph fields, sitemap exclusion and the Schema.org type override. Community contribution (#141).', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/visibility-read'  => array(
						'label'            => __( 'Visibility Read', 'emcp-tools' ),
						'description'      => __( 'Read Visibility post SEO metadata.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo' ),
						'available'        => self::visibility_available(),
						'requires'         => array( 'name' => 'Visibility', 'kind' => 'plugin' ),
					),
					'emcp-tools/visibility-write' => array(
						'label'            => __( 'Visibility Write', 'emcp-tools' ),
						'description'      => __( 'Update Visibility post SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo' ),
						'available'        => self::visibility_available(),
						'requires'         => array( 'name' => 'Visibility', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_yoast'         => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'pro'      => true,
				'label'    => __( 'Yoast SEO', 'emcp-tools' ),
				'note'     => __( 'Yoast SEO exposed as two tools, one Read, one Write. Read and write the SEO metadata (title, description, canonical, robots, social, focus keyword) Yoast stores for posts and terms, plus site settings.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/yoast-read'  => array(
						'label'            => __( 'Yoast SEO Read', 'emcp-tools' ),
						'description'      => __( 'Read Yoast post/term SEO metadata and site settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-term-seo', 'get-settings' ),
						'available'        => self::yoast_available(),
						'requires'         => array( 'name' => 'Yoast SEO', 'kind' => 'plugin' ),
					),
					'emcp-tools/yoast-write' => array(
						'label'            => __( 'Yoast SEO Write', 'emcp-tools' ),
						'description'      => __( 'Update Yoast post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo', 'update-term-seo' ),
						'available'        => self::yoast_available(),
						'requires'         => array( 'name' => 'Yoast SEO', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_rankmath'      => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'pro'      => true,
				'label'    => __( 'Rank Math', 'emcp-tools' ),
				'note'     => __( 'Rank Math exposed as two tools, one Read, one Write. Read/write post & term SEO metadata and site settings; also read schema (structured data).', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/rankmath-read'  => array(
						'label'            => __( 'Rank Math Read', 'emcp-tools' ),
						'description'      => __( 'Read Rank Math post/term SEO metadata, schema, and site settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-term-seo', 'get-schema', 'get-settings' ),
						'available'        => self::rankmath_available(),
						'requires'         => array( 'name' => 'Rank Math', 'kind' => 'plugin' ),
					),
					'emcp-tools/rankmath-write' => array(
						'label'            => __( 'Rank Math Write', 'emcp-tools' ),
						'description'      => __( 'Update Rank Math post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo', 'update-term-seo' ),
						'available'        => self::rankmath_available(),
						'requires'         => array( 'name' => 'Rank Math', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_aioseo'        => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'pro'      => true,
				'label'    => __( 'All in One SEO', 'emcp-tools' ),
				'note'     => __( 'All in One SEO exposed as two tools, one Read, one Write. Read/write post SEO metadata and read schema (structured data) + site settings.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/aioseo-read'  => array(
						'label'            => __( 'All in One SEO Read', 'emcp-tools' ),
						'description'      => __( 'Read AIOSEO post SEO metadata, schema, and site settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-schema', 'get-settings' ),
						'available'        => self::aioseo_available(),
						'requires'         => array( 'name' => 'All in One SEO', 'kind' => 'plugin' ),
					),
					'emcp-tools/aioseo-write' => array(
						'label'            => __( 'All in One SEO Write', 'emcp-tools' ),
						'description'      => __( 'Update AIOSEO post SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo' ),
						'available'        => self::aioseo_available(),
						'requires'         => array( 'name' => 'All in One SEO', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_seopress'      => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'pro'      => true,
				'label'    => __( 'SEOPress', 'emcp-tools' ),
				'note'     => __( 'SEOPress exposed as two tools, one Read, one Write. Read/write post & term SEO metadata and site settings; also read schema (structured data).', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/seopress-read'  => array(
						'label'            => __( 'SEOPress Read', 'emcp-tools' ),
						'description'      => __( 'Read SEOPress post/term SEO metadata, schema, and site settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-term-seo', 'get-settings', 'get-schema' ),
						'available'        => self::seopress_available(),
						'requires'         => array( 'name' => 'SEOPress', 'kind' => 'plugin' ),
					),
					'emcp-tools/seopress-write' => array(
						'label'            => __( 'SEOPress Write', 'emcp-tools' ),
						'description'      => __( 'Update SEOPress post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo', 'update-term-seo' ),
						'available'        => self::seopress_available(),
						'requires'         => array( 'name' => 'SEOPress', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_seoframework'  => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'pro'      => true,
				'label'    => __( 'The SEO Framework', 'emcp-tools' ),
				'note'     => __( 'The SEO Framework exposed as two tools, one Read, one Write. Read and write the SEO metadata (title, description, canonical, robots, social) it stores for posts and terms.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/seoframework-read'  => array(
						'label'            => __( 'The SEO Framework Read', 'emcp-tools' ),
						'description'      => __( 'Read The SEO Framework post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-term-seo' ),
						'available'        => self::seoframework_available(),
						'requires'         => array( 'name' => 'The SEO Framework', 'kind' => 'plugin' ),
					),
					'emcp-tools/seoframework-write' => array(
						'label'            => __( 'The SEO Framework Write', 'emcp-tools' ),
						'description'      => __( 'Update The SEO Framework post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo', 'update-term-seo' ),
						'available'        => self::seoframework_available(),
						'requires'         => array( 'name' => 'The SEO Framework', 'kind' => 'plugin' ),
					),
				),
			),
			'wp_surerank'      => array(
				'platform' => 'plugins',
				'group'    => 'seo',
				'pro'      => true,
				'label'    => __( 'SureRank', 'emcp-tools' ),
				'note'     => __( 'SureRank exposed as two tools, one Read, one Write. Read and write the SEO metadata (title, description, canonical, robots, social) SureRank stores for posts and terms.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/surerank-read'  => array(
						'label'            => __( 'SureRank Read', 'emcp-tools' ),
						'description'      => __( 'Read SureRank post/term SEO metadata and site settings.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-post-seo', 'get-term-seo', 'get-settings' ),
						'available'        => self::surerank_available(),
						'requires'         => array( 'name' => 'SureRank', 'kind' => 'plugin' ),
					),
					'emcp-tools/surerank-write' => array(
						'label'            => __( 'SureRank Write', 'emcp-tools' ),
						'description'      => __( 'Update SureRank post/term SEO metadata.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-post-seo', 'update-term-seo' ),
						'available'        => self::surerank_available(),
						'requires'         => array( 'name' => 'SureRank', 'kind' => 'plugin' ),
					),
				),
			),
			'theme_active'     => array(
				'platform' => 'themes',
				'label'    => __( 'Active Theme', 'emcp-tools' ),
				'note'     => __( 'Theme integrations are exposed as two tools, one Read, one Write, that bundle internal operations. The AI calls a tool with an operation name; toggle a tool to allow or block all of its operations at once.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/theme-read'  => array(
						'label'       => __( 'Theme Read', 'emcp-tools' ),
						'description' => __( 'Read the active theme: context (framework, block-theme, supports, menu locations, child status) and theme_mod values.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
						'operations'  => array( 'get-theme-context', 'get-mods' ),
					),
					'emcp-tools/theme-write' => array(
						'label'       => __( 'Theme Write', 'emcp-tools' ),
						'description' => __( 'Set theme_mod values and create + activate a child theme so the agent can edit theme files (create-child-theme requires confirm:true).', 'emcp-tools' ),
						'badges'      => array(),
						'operations'  => array( 'set-mods', 'create-child-theme' ),
					),
				),
			),
			'theme_astra_spectra' => array(
				'platform' => 'themes',
				'label'    => __( 'Astra + Spectra', 'emcp-tools' ),
				'note'     => __( 'The Astra theme and its Spectra blocks companion, grouped as one pack. Astra tools manage the theme\'s settings (enabled only when Astra is the active theme); Spectra tools give the block catalog + insertion (enabled only when the Spectra plugin is active). Toggles for an inactive component are disabled until you install and activate it.', 'emcp-tools' ),
				'notice'   => self::spectra_file_generation_notice(),
				'tools'    => array(
					'emcp-tools/astra-read'    => array(
						'label'            => __( 'Astra Read', 'emcp-tools' ),
						'description'      => __( 'Read Astra settings (colors, typography, layout, header/footer) with value + type/label/group metadata.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-settings' ),
						'available'        => self::astra_available(),
						'requires'         => array( 'name' => 'Astra', 'kind' => 'theme' ),
					),
					'emcp-tools/astra-write'   => array(
						'label'            => __( 'Astra Write', 'emcp-tools' ),
						'description'      => __( 'Write Astra settings; non-allowlisted keys are reported in skipped[].', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-settings' ),
						'available'        => self::astra_available(),
						'requires'         => array( 'name' => 'Astra', 'kind' => 'theme' ),
					),
					'emcp-tools/spectra-read'  => array(
						'label'            => __( 'Spectra Read', 'emcp-tools' ),
						'description'      => __( 'Catalog of available Spectra blocks (list-blocks) and each block\'s real attributes + example markup (get-block-schema).', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-blocks', 'get-block-schema' ),
						'available'        => self::spectra_available(),
						'requires'         => array( 'name' => 'Spectra', 'kind' => 'plugin' ),
					),
					'emcp-tools/spectra-write' => array(
						'label'            => __( 'Spectra Write', 'emcp-tools' ),
						'description'      => __( 'Insert a Spectra block into a post with a generated block_id (add-block); Spectra applies its own defaults.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'add-block' ),
						'available'        => self::spectra_available(),
						'requires'         => array( 'name' => 'Spectra', 'kind' => 'plugin' ),
					),
				),
			),
			'theme_kadence'    => array(
				'platform' => 'themes',
				'label'    => __( 'Kadence + Kadence Blocks', 'emcp-tools' ),
				'note'     => __( 'The Kadence theme and its Kadence Blocks companion, grouped as one pack. Kadence tools manage the theme\'s settings (enabled only when Kadence is the active theme); Kadence Blocks tools give the block catalog + insertion (enabled only when the Kadence Blocks plugin is active). Toggles for an inactive component are disabled until you install and activate it.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/kadence-read'         => array(
						'label'            => __( 'Kadence Read', 'emcp-tools' ),
						'description'      => __( 'Read Kadence settings (palette, colors, typography, layout, buttons, header/footer) with value + type/label/group/shape metadata.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-settings' ),
						'available'        => self::kadence_available(),
						'requires'         => array( 'name' => 'Kadence', 'kind' => 'theme' ),
					),
					'emcp-tools/kadence-write'        => array(
						'label'            => __( 'Kadence Write', 'emcp-tools' ),
						'description'      => __( 'Write Kadence settings as theme_mods; non-allowlisted keys are reported in skipped[].', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-settings' ),
						'available'        => self::kadence_available(),
						'requires'         => array( 'name' => 'Kadence', 'kind' => 'theme' ),
					),
					'emcp-tools/kadence-blocks-read'  => array(
						'label'            => __( 'Kadence Blocks Read', 'emcp-tools' ),
						'description'      => __( 'Catalog of available Kadence blocks (list-blocks) and each block\'s real attributes + example markup (get-block-schema).', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'list-blocks', 'get-block-schema' ),
						'available'        => self::kadence_blocks_available(),
						'requires'         => array( 'name' => 'Kadence Blocks', 'kind' => 'plugin' ),
					),
					'emcp-tools/kadence-blocks-write' => array(
						'label'            => __( 'Kadence Blocks Write', 'emcp-tools' ),
						'description'      => __( 'Insert a Kadence block into a post with a generated uniqueID + scaffolded inner blocks (add-block).', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'add-block' ),
						'available'        => self::kadence_blocks_available(),
						'requires'         => array( 'name' => 'Kadence Blocks', 'kind' => 'plugin' ),
					),
				),
			),
			'theme_generatepress' => array(
				'platform' => 'themes',
				'label'    => __( 'GeneratePress + GenerateBlocks', 'emcp-tools' ),
				'note'     => __( 'The GeneratePress theme and its GenerateBlocks companion (Pro). GeneratePress tools manage the theme\'s settings (enabled only when GeneratePress is the active theme); GenerateBlocks tools give the block catalog + insertion (enabled only when the GenerateBlocks plugin is active). Toggles for an inactive component are disabled until you install and activate it.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/generatepress-read'   => array(
						'label'            => __( 'GeneratePress Read', 'emcp-tools' ),
						'description'      => __( 'Read GeneratePress settings (global palette, colors, layout, typography) with value + type/label/group/shape metadata.', 'emcp-tools' ),
						'badges'           => array( 'read-only', 'pro' ),
						'operations'       => array( 'get-settings' ),
						'available'        => self::generatepress_available(),
						'requires'         => array( 'name' => 'GeneratePress', 'kind' => 'theme' ),
					),
					'emcp-tools/generatepress-write'  => array(
						'label'            => __( 'GeneratePress Write', 'emcp-tools' ),
						'description'      => __( 'Write GeneratePress settings; non-allowlisted keys are reported in skipped[].', 'emcp-tools' ),
						'badges'           => array( 'pro' ),
						'operations'       => array( 'update-settings' ),
						'available'        => self::generatepress_available(),
						'requires'         => array( 'name' => 'GeneratePress', 'kind' => 'theme' ),
					),
					'emcp-tools/generateblocks-read'  => array(
						'label'            => __( 'GenerateBlocks Read', 'emcp-tools' ),
						'description'      => __( 'Catalog of the GenerateBlocks V2 blocks (list-blocks) and each block\'s attributes + styles model (get-block-schema).', 'emcp-tools' ),
						'badges'           => array( 'read-only', 'pro' ),
						'operations'       => array( 'list-blocks', 'get-block-schema' ),
						'available'        => self::generateblocks_available(),
						'requires'         => array( 'name' => 'GenerateBlocks', 'kind' => 'plugin' ),
					),
					'emcp-tools/generateblocks-write' => array(
						'label'            => __( 'GenerateBlocks Write', 'emcp-tools' ),
						'description'      => __( 'Insert a GenerateBlocks V2 block with a generated uniqueId, styles object + compiled css, and content (add-block).', 'emcp-tools' ),
						'badges'           => array( 'pro' ),
						'operations'       => array( 'add-block' ),
						'available'        => self::generateblocks_available(),
						'requires'         => array( 'name' => 'GenerateBlocks', 'kind' => 'plugin' ),
					),
				),
			),
			'theme_betheme'    => array(
				'platform' => 'bebuilder',
				'pro'      => true,
				'label'    => __( 'BeTheme + BeBuilder', 'emcp-tools' ),
				'note'     => __( 'BeTheme (Muffin Group) and its BeBuilder page builder, as one pack. Read and write a curated set of the theme\'s 830 settings, and build page content as BeBuilder sections. Enabled only when BeTheme is the active theme. BeTheme\'s own template system is not covered here: use EMCP Themer, or BeTheme\'s Templates screen directly.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/betheme-read'  => array(
						'label'            => __( 'BeTheme Read', 'emcp-tools' ),
						'description'      => __( 'Read theme context, curated settings (colors, typography, layout, header/footer, blog), the BeBuilder item catalog with per-item schemas, and a page\'s BeBuilder structure.', 'emcp-tools' ),
						'badges'           => array( 'read-only' ),
						'operations'       => array( 'get-context', 'get-settings', 'list-item-types', 'get-item-schema', 'get-page' ),
						'available'        => self::betheme_available(),
						'requires'         => array( 'name' => 'BeTheme', 'kind' => 'theme' ),
					),
					'emcp-tools/betheme-write' => array(
						'label'            => __( 'BeTheme Write', 'emcp-tools' ),
						'description'      => __( 'Write curated theme settings, replace a page\'s BeBuilder content, or append a section. Settings outside the curated list are reported in skipped[]; an unknown item type is refused rather than written.', 'emcp-tools' ),
						'badges'           => array(),
						'operations'       => array( 'update-settings', 'set-page', 'add-section' ),
						'available'        => self::betheme_available(),
						'requires'         => array( 'name' => 'BeTheme', 'kind' => 'theme' ),
					),
				),
			),
			'theme_blocksy'    => array(
				'platform' => 'themes',
				'label'    => __( 'Blocksy', 'emcp-tools' ),
				'note'     => __( 'Blocksy (Pro): its dynamic content blocks (query/tax-query loops, dynamic-data, about-me, socials, share-box, breadcrumbs, …) and its Blocksy Companion extensions (activate/deactivate). Enabled when Blocksy Companion is active. Theme settings are reachable via the free Active Theme tools (theme-read/theme-write).', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/blocksy-blocks-read'      => array(
						'label'            => __( 'Blocksy Blocks Read', 'emcp-tools' ),
						'description'      => __( 'Catalog of the Blocksy blocks (list-blocks) and each block\'s attributes (get-block-schema).', 'emcp-tools' ),
						'badges'           => array( 'read-only', 'pro' ),
						'operations'       => array( 'list-blocks', 'get-block-schema' ),
						'available'        => self::blocksy_blocks_available(),
						'requires'         => array( 'name' => 'Blocksy Companion', 'kind' => 'plugin' ),
					),
					'emcp-tools/blocksy-blocks-write'     => array(
						'label'            => __( 'Blocksy Blocks Write', 'emcp-tools' ),
						'description'      => __( 'Insert a Blocksy block into a post (add-block); query/tax-query get a scaffolded template child.', 'emcp-tools' ),
						'badges'           => array( 'pro' ),
						'operations'       => array( 'add-block' ),
						'available'        => self::blocksy_blocks_available(),
						'requires'         => array( 'name' => 'Blocksy Companion', 'kind' => 'plugin' ),
					),
					'emcp-tools/blocksy-extensions-read'  => array(
						'label'            => __( 'Blocksy Extensions Read', 'emcp-tools' ),
						'description'      => __( 'List Blocksy Companion extensions with name, description, pro flag, and active status (list-extensions).', 'emcp-tools' ),
						'badges'           => array( 'read-only', 'pro' ),
						'operations'       => array( 'list-extensions' ),
						'available'        => self::blocksy_extensions_available(),
						'requires'         => array( 'name' => 'Blocksy Companion', 'kind' => 'plugin' ),
					),
					'emcp-tools/blocksy-extensions-write' => array(
						'label'            => __( 'Blocksy Extensions Write', 'emcp-tools' ),
						'description'      => __( 'Activate or deactivate a Blocksy Companion extension by slug.', 'emcp-tools' ),
						'badges'           => array( 'pro' ),
						'operations'       => array( 'activate-extension', 'deactivate-extension' ),
						'available'        => self::blocksy_extensions_available(),
						'requires'         => array( 'name' => 'Blocksy Companion', 'kind' => 'plugin' ),
					),
				),
			),
			'page'             => array(
				'platform' => 'elementor',
				'label' => __( 'Page Management', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/create-page'          => array(
						'label'       => __( 'Create Page', 'emcp-tools' ),
						'description' => __( 'Creates a new WordPress page with Elementor enabled.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-page-settings' => array(
						'label'       => __( 'Update Page Settings', 'emcp-tools' ),
						'description' => __( 'Updates Elementor page-level settings (layout, canvas, etc).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-page-content'  => array(
						'label'       => __( 'Delete Page Content', 'emcp-tools' ),
						'description' => __( 'Removes all Elementor content from a page.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/import-template'      => array(
						'label'       => __( 'Import Template', 'emcp-tools' ),
						'description' => __( 'Imports an Elementor template JSON into a page.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/export-page'          => array(
						'label'       => __( 'Export Page', 'emcp-tools' ),
						'description' => __( 'Exports a page\'s Elementor data as JSON.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/regenerate-css' => array(
						'label' => __( 'Regenerate CSS & Data', 'emcp-tools' ),
						'description' => __( 'Clears Elementor CSS and render/asset caches for one page, or site-wide with administrator permission and confirmation.', 'emcp-tools' ),
						'badges' => array(),
					),
				),
			),
			'layout'           => array(
				'platform' => 'elementor',
				'label' => __( 'Layout & Structure', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-container'     => array(
						'label'       => __( 'Add Container', 'emcp-tools' ),
						'description' => __( 'Adds a new flexbox container to a page or inside another container.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/move-element'      => array(
						'label'       => __( 'Move Element', 'emcp-tools' ),
						'description' => __( 'Moves an element to a new parent or position.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/remove-element'    => array(
						'label'       => __( 'Remove Element', 'emcp-tools' ),
						'description' => __( 'Removes an element and all its children from the page.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/duplicate-element'    => array(
						'label'       => __( 'Duplicate Element', 'emcp-tools' ),
						'description' => __( 'Creates a deep copy of an element and inserts it after the original.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-container'     => array(
						'label'       => __( 'Update Container', 'emcp-tools' ),
						'description' => __( 'Updates settings on an existing container element.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/get-container-schema' => array(
						'label'       => __( 'Get Container Schema', 'emcp-tools' ),
						'description' => __( 'Returns the JSON schema for container settings.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/find-element'         => array(
						'label'       => __( 'Find Element', 'emcp-tools' ),
						'description' => __( 'Finds elements by type, settings, or CSS class within a page.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-element'       => array(
						'label'       => __( 'Update Element', 'emcp-tools' ),
						'description' => __( 'Updates settings on any element (widget or container) by ID. Also writes v4 atomic styles / editor_settings when included.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/batch-update'         => array(
						'label'       => __( 'Batch Update', 'emcp-tools' ),
						'description' => __( 'Applies multiple element updates in a single call.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/set-element-label'    => array(
						'label'       => __( 'Set Element Label', 'emcp-tools' ),
						'description' => __( 'Sets an element\'s Navigator label (editor_settings.title).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/reorder-elements'     => array(
						'label'       => __( 'Reorder Elements', 'emcp-tools' ),
						'description' => __( 'Reorders child elements within a container.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'widgets'          => array(
				'platform' => 'elementor',
				'label' => __( 'Widgets', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-free-widget' => array(
						'label'       => __( 'Add Widget', 'emcp-tools' ),
						'description' => __( 'Adds any free/core Elementor widget by type (discover with list-widgets / get-widget-schema).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-pro-widget'  => array(
						'label'       => __( 'Add Pro Widget', 'emcp-tools' ),
						'description' => __( 'Adds an Elementor Pro / WooCommerce widget by type. Registers only when Elementor Pro is active.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/update-widget'   => array(
						'label'       => __( 'Update Widget', 'emcp-tools' ),
						'description' => __( 'Updates settings on an existing widget (partial merge).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'template'         => array(
				'platform' => 'elementor',
				'label' => __( 'Templates', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/save-as-template' => array(
						'label'       => __( 'Save as Template', 'emcp-tools' ),
						'description' => __( 'Saves the current page content as a reusable template.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/apply-template'       => array(
						'label'       => __( 'Apply Template', 'emcp-tools' ),
						'description' => __( 'Applies a saved template to a target page.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/create-elementor-theme-template' => array(
						'label'       => __( 'Create Elementor Theme Template', 'emcp-tools' ),
						'description' => __( 'Creates a native Elementor Pro theme builder template (header, footer, single, archive, etc). For the builder-agnostic EMCP Themer, use create-theme-template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/set-elementor-template-conditions' => array(
						'label'       => __( 'Set Elementor Template Conditions', 'emcp-tools' ),
						'description' => __( 'Sets display conditions on a native Elementor Pro theme builder template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/list-dynamic-tags'    => array(
						'label'       => __( 'List Dynamic Tags', 'emcp-tools' ),
						'description' => __( 'Lists all available dynamic tags and their categories.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro', 'read-only' ),
					),
					'emcp-tools/set-dynamic-tag'      => array(
						'label'       => __( 'Set Dynamic Tag', 'emcp-tools' ),
						'description' => __( 'Sets a dynamic tag on a specific element setting.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/create-popup'         => array(
						'label'       => __( 'Create Popup', 'emcp-tools' ),
						'description' => __( 'Creates an Elementor popup template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/set-popup-settings'   => array(
						'label'       => __( 'Set Popup Settings', 'emcp-tools' ),
						'description' => __( 'Sets triggers, conditions, and timing on a popup template.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
				),
			),
			'global'           => array(
				'platform' => 'elementor',
				'label' => __( 'Global Settings', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/update-global-colors'     => array(
						'label'       => __( 'Update Global Colors', 'emcp-tools' ),
						'description' => __( 'Updates the site-wide Elementor color palette.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-global-typography' => array(
						'label'       => __( 'Update Global Typography', 'emcp-tools' ),
						'description' => __( 'Updates the site-wide Elementor typography presets.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'composite'        => array(
				'platform' => 'elementor',
				'label' => __( 'Composite', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/build-page' => array(
						'label'       => __( 'Build Page', 'emcp-tools' ),
						'description' => __( 'Creates a complete page from a declarative structure in one call.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'stock_images'     => array(
				'platform' => 'wordpress',
				'label' => __( 'Stock & Media Images', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-media'       => array(
						'label'       => __( 'List Media', 'emcp-tools' ),
						'description' => __( 'Lists and searches images already in the WordPress Media Library (the site\'s own uploads).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-media'        => array(
						'label'       => __( 'Get Media', 'emcp-tools' ),
						'description' => __( 'Full detail of one attachment (sizes, metadata, alt/caption).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/upload-media'     => array(
						'label'       => __( 'Upload Media', 'emcp-tools' ),
						'description' => __( 'Upload a LOCAL file from the client machine into the Media Library by passing its base64 bytes (companion to sideload-image, which fetches a server-reachable URL). Only WordPress-allowed file types.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-media'     => array(
						'label'       => __( 'Update Media', 'emcp-tools' ),
						'description' => __( 'Edit an attachment\'s alt text, title, caption, description.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-media'     => array(
						'label'       => __( 'Delete Media', 'emcp-tools' ),
						'description' => __( 'Delete an attachment (permanent; requires confirm).', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/search-images'    => array(
						'label'       => __( 'Search Images', 'emcp-tools' ),
						'description' => __( 'Searches a stock-photo provider (Unsplash, Pexels, or Pixabay) for images. Core WordPress tool, available without Elementor. Needs a free provider API key (Connection tab).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/sideload-image'   => array(
						'label'       => __( 'Sideload Image', 'emcp-tools' ),
						'description' => __( 'Downloads an external image URL into the WordPress Media Library. Core WordPress tool, available without Elementor.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-stock-image'  => array(
						'label'       => __( 'Add Stock Image', 'emcp-tools' ),
						'description' => __( 'Searches, downloads, and adds a stock image to the page in one call.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'svg_icons'        => array(
				'platform' => 'elementor',
				'label' => __( 'SVG Icons', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/upload-svg-icon'  => array(
						'label'       => __( 'Upload SVG Icon', 'emcp-tools' ),
						'description' => __( 'Uploads an SVG icon (from URL or raw markup) for use with icon/icon-box widgets.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			),
			'custom_code'      => array(
				'platform' => 'elementor',
				'label' => __( 'Custom Code', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-custom-css'     => array(
						'label'       => __( 'Add Custom CSS', 'emcp-tools' ),
						'description' => __( 'Adds custom CSS to a specific element or the entire page.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/add-custom-js'      => array(
						'label'       => __( 'Add Custom JavaScript', 'emcp-tools' ),
						'description' => __( 'Adds a JavaScript snippet to a page via an HTML widget.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-code-snippet'   => array(
						'label'       => __( 'Add Code Snippet', 'emcp-tools' ),
						'description' => __( 'Creates a site-wide Custom Code snippet for head/body injection.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro' ),
					),
					'emcp-tools/list-code-snippets' => array(
						'label'       => __( 'List Code Snippets', 'emcp-tools' ),
						'description' => __( 'Lists all existing Custom Code snippets.', 'emcp-tools' ),
						'badges'      => array( 'elementor-pro', 'read-only' ),
					),
				),
			),
		);

		// Atomic elements (Elementor 4.0+). The underlying abilities are only
		// registered when Elementor >= 4.0 is active, so we mirror that gate
		// here to avoid showing toggles for tools that don't exist.
		if ( class_exists( 'EMCP_Tools_Atomic_Props' ) && EMCP_Tools_Atomic_Props::is_atomic_supported() ) {
			$tools['atomic_layout'] = array(
				'platform' => 'elementor',
				'label' => __( 'Atomic Layout (Elementor 4.0+)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/detect-elementor-version' => array(
						'label'       => __( 'Detect Elementor Version', 'emcp-tools' ),
						'description' => __( 'Returns the Elementor version and whether atomic elements are supported.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-global-classes'      => array(
						'label'       => __( 'List Global Classes', 'emcp-tools' ),
						'description' => __( 'Resolves Class Manager "g-" class IDs to their names and CSS properties.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-global-class'      => array(
						'label'       => __( 'Create Global Class', 'emcp-tools' ),
						'description' => __( 'Create an Elementor v4 Global Class with a label + styles; returns the new g- id.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-global-class'      => array(
						'label'       => __( 'Update Global Class', 'emcp-tools' ),
						'description' => __( 'Update a Global Class label and/or its styles (per breakpoint/state).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-global-class'      => array(
						'label'       => __( 'Delete Global Class', 'emcp-tools' ),
						'description' => __( 'Delete a Global Class by g- id (also removes it from elements using it); requires confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/reorder-global-classes'   => array(
						'label'       => __( 'Reorder Global Classes', 'emcp-tools' ),
						'description' => __( 'Set the Class Manager order (= CSS source order / specificity) of the v4 Global Classes.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-variables'           => array(
						'label'       => __( 'List Global Variables', 'emcp-tools' ),
						'description' => __( 'List Elementor design tokens with stable ids, values, types, and watermark.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/create-variable'          => array(
						'label'       => __( 'Create Global Variable', 'emcp-tools' ),
						'description' => __( 'Create an Elementor Global Variable design token.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-variable'          => array(
						'label'       => __( 'Update Global Variable', 'emcp-tools' ),
						'description' => __( 'Update a Global Variable label, value, type, or order.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-variable'          => array(
						'label'       => __( 'Delete Global Variable', 'emcp-tools' ),
						'description' => __( 'Soft-delete a Global Variable; requires confirm:true.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/restore-variable'         => array(
						'label'       => __( 'Restore Global Variable', 'emcp-tools' ),
						'description' => __( 'Restore a soft-deleted Global Variable.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/batch-variables'           => array(
						'label'       => __( 'Batch Global Variables', 'emcp-tools' ),
						'description' => __( 'Atomically create, update, delete, or restore multiple design tokens.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/add-flexbox'              => array(
						'label'       => __( 'Add Flexbox', 'emcp-tools' ),
						'description' => __( 'Adds an atomic flexbox container (e-flexbox).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-div-block'            => array(
						'label'       => __( 'Add Div Block', 'emcp-tools' ),
						'description' => __( 'Adds an atomic div-block container (e-div-block).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);

			$tools['atomic_widgets'] = array(
				'platform' => 'elementor',
				'label' => __( 'Atomic Widgets (Elementor 4.0+)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/add-atomic-widget'    => array(
						'label'       => __( 'Add Atomic Widget', 'emcp-tools' ),
						'description' => __( 'Universal: adds any atomic widget by type with raw $$type settings.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/update-atomic-widget' => array(
						'label'       => __( 'Update Atomic Widget', 'emcp-tools' ),
						'description' => __( 'Universal: partial-merge update on an existing atomic widget.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-heading'   => array(
						'label'       => __( 'Add Atomic Heading', 'emcp-tools' ),
						'description' => __( 'Adds an atomic heading element (e-heading).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-paragraph' => array(
						'label'       => __( 'Add Atomic Paragraph', 'emcp-tools' ),
						'description' => __( 'Adds an atomic paragraph element (e-paragraph).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-button'    => array(
						'label'       => __( 'Add Atomic Button', 'emcp-tools' ),
						'description' => __( 'Adds an atomic button element (e-button).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-image'     => array(
						'label'       => __( 'Add Atomic Image', 'emcp-tools' ),
						'description' => __( 'Adds an atomic image element (e-image).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-svg'       => array(
						'label'       => __( 'Add Atomic SVG', 'emcp-tools' ),
						'description' => __( 'Adds an atomic SVG element (e-svg).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-youtube'   => array(
						'label'       => __( 'Add Atomic YouTube', 'emcp-tools' ),
						'description' => __( 'Adds an atomic YouTube embed (e-youtube).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-video'     => array(
						'label'       => __( 'Add Atomic Video', 'emcp-tools' ),
						'description' => __( 'Adds an atomic self-hosted video (e-self-hosted-video).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/add-atomic-divider'   => array(
						'label'       => __( 'Add Atomic Divider', 'emcp-tools' ),
						'description' => __( 'Adds an atomic divider element (e-divider).', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);
		}

		// Brand Kits (Pro). Only shown to licensed sites — the underlying
		// abilities register only for Pro, matching this gate. No 'pro' badge so
		// they are NOT auto-disabled by maybe_apply_default_disabled_tools (this
		// is a headline Pro feature, on by default for licensed users).
		if (
			class_exists( 'EMCP_Tools_Pro_Brand_Kits' )
			&& EMCP_Tools_Pro_Brand_Kits::user_has_access()
		) {
			$tools['brand_kits'] = array(
				'platform' => 'elementor',
				'label' => __( 'Brand Kits', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-brand-kits'           => array(
						'label'       => __( 'List Brand Kits', 'emcp-tools' ),
						'description' => __( 'Lists available premium brand kits from the cached library.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/apply-brand-kit'           => array(
						'label'       => __( 'Apply Brand Kit', 'emcp-tools' ),
						'description' => __( 'Applies a brand kit: replaces system colors + typography site-wide.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/replace-system-colors'     => array(
						'label'       => __( 'Replace System Colors', 'emcp-tools' ),
						'description' => __( 'Replaces the four Elementor system color slots atomically.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/replace-system-typography' => array(
						'label'       => __( 'Replace System Typography', 'emcp-tools' ),
						'description' => __( 'Replaces the four Elementor system typography slots atomically.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			);
		}

		// PHP Code Snippets (Sandbox) — free, but capability-gated and powerful,
		// so all six ship disabled-by-default (maybe_apply_default_disabled_tools
		// v4) and the admin re-enables them here. There is no "activate" tool: an
		// AI can only create drafts; a human admin activates them on the Sandbox tab.
		$tools['php_snippets'] = array(
			'platform' => 'modules',
			'label' => __( 'PHP Snippets (Sandbox)', 'emcp-tools' ),
			'tools' => array(
				'emcp-tools/validate-php-snippet' => array(
					'label'       => __( 'Validate PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Statically checks snippet code (parse + security scan) without storing or running it.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/create-php-snippet'   => array(
					'label'       => __( 'Create PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Creates an INACTIVE draft snippet (validated; an admin must activate it before it runs).', 'emcp-tools' ),
					'badges'      => array(),
				),
				'emcp-tools/update-php-snippet'   => array(
					'label'       => __( 'Update PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Updates a snippet\'s code/settings and re-validates.', 'emcp-tools' ),
					'badges'      => array(),
				),
				'emcp-tools/get-php-snippet'      => array(
					'label'       => __( 'Get PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Returns a snippet\'s code, status, shortcode, and validation report.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/list-php-snippets'    => array(
					'label'       => __( 'List PHP Snippets', 'emcp-tools' ),
					'description' => __( 'Lists PHP snippets with their status and run context.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/delete-php-snippet'   => array(
					'label'       => __( 'Delete PHP Snippet', 'emcp-tools' ),
					'description' => __( 'Permanently deletes a snippet and its sandbox file.', 'emcp-tools' ),
					'badges'      => array( 'destructive' ),
				),
			),
		);

		// Sandbox Cloud (export/import) — free, always-on. Lets a sandbox artifact
		// (custom widget/block/snippet) be exported as a portable bundle and
		// imported on another site, so authored sandbox code isn't stuck to one
		// install. Both read/write in nature but low-risk (data movement, not
		// arbitrary execution) — enabled-by-default, unlike the sandboxes themselves.
		$tools['sandbox_cloud'] = array(
			'platform' => 'modules',
			'label' => __( 'Sandbox Cloud (Export / Import)', 'emcp-tools' ),
			'tools' => array(
				'emcp-tools/export-sandbox-artifact' => array(
					'label'       => __( 'Export Sandbox Artifact', 'emcp-tools' ),
					'description' => __( 'Exports a custom widget/block/snippet as a portable bundle.', 'emcp-tools' ),
					'badges'      => array( 'read-only' ),
				),
				'emcp-tools/import-sandbox-artifact' => array(
					'label'       => __( 'Import Sandbox Artifact', 'emcp-tools' ),
					'description' => __( 'Imports a sandbox artifact bundle produced by export-sandbox-artifact.', 'emcp-tools' ),
					'badges'      => array(),
				),
			),
		);

		// Project Memory (Pro) — recall/remember/save-session-summary. Disabled by
		// default; the approved-guidance injection works with these off.
		$tools['memory'] = array(
			'platform' => 'modules',
			'pro'      => true,
			'label'    => __( 'Project Memory (Pro)', 'emcp-tools' ),
			'tools'    => array(
				'emcp-tools/recall' => array(
					'label'       => __( 'Recall Project Memory', 'emcp-tools' ),
					'description' => __( 'Read approved guidance + recent session summaries so the agent does not re-guess site context.', 'emcp-tools' ),
					'badges'      => array( 'pro', 'read-only' ),
				),
				'emcp-tools/remember' => array(
					'label'       => __( 'Remember Guidance', 'emcp-tools' ),
					'description' => __( 'Propose one guardrail/fact/convention/instruction. Stored pending until a human approves it.', 'emcp-tools' ),
					'badges'      => array( 'pro' ),
				),
				'emcp-tools/save-session-summary' => array(
					'label'       => __( 'Save Session Summary', 'emcp-tools' ),
					'description' => __( 'Record a session summary; the plugin attaches a factual digest of the actual changes.', 'emcp-tools' ),
					'badges'      => array( 'pro' ),
				),
			),
		);

		// EMCP Cloud — free module, but the tools only register once the site is
		// actually connected to a cloud account, so the whole section is gated on
		// that rather than showing toggles that control nothing.
		if ( class_exists( 'EMCP_Tools_Cloud_Module' ) && EMCP_Tools_Cloud_Module::is_enabled()
			&& class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected() ) {
			$tools['cloud'] = array(
				'platform' => 'modules',
				'label'    => __( 'EMCP Cloud', 'emcp-tools' ),
				'note'     => __( 'Back up and sync your sandbox artifacts and settings to your EMCP Cloud account. These tools appear once this site is connected.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/cloud-status'      => array(
						'label'       => __( 'Cloud Status', 'emcp-tools' ),
						'description' => __( 'Plan, limits, and usage for the connected account.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/cloud-list'        => array(
						'label'       => __( 'Cloud List', 'emcp-tools' ),
						'description' => __( 'List the artifacts backed up to your account, optionally by kind.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/cloud-backup'      => array(
						'label'       => __( 'Cloud Backup', 'emcp-tools' ),
						'description' => __( 'Back up a local sandbox artifact (block, widget, or PHP snippet) to your account.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/cloud-pull'        => array(
						'label'       => __( 'Cloud Pull', 'emcp-tools' ),
						'description' => __( 'Pull a cloud artifact into this site by UUID. It lands as a new inactive draft.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/cloud-config-sync' => array(
						'label'       => __( 'Cloud Config Sync', 'emcp-tools' ),
						'description' => __( 'Push or pull a config blob (settings, brand kit, tool toggles) to or from EMCP Cloud.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);

			$tools['marketplace'] = array(
				'platform' => 'modules',
				'label'    => __( 'Marketplace', 'emcp-tools' ),
				'note'     => __( 'Browse and install published EMCP Cloud marketplace listings. An install always lands as a new inactive draft for you to review before it runs.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/cloud-marketplace-list'    => array(
						'label'       => __( 'Marketplace List', 'emcp-tools' ),
						'description' => __( 'Browse published marketplace listings (blocks, widgets, snippets).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/cloud-marketplace-install' => array(
						'label'       => __( 'Marketplace Install', 'emcp-tools' ),
						'description' => __( 'Install a listing by slug. It is imported as a new inactive draft.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);
		}

		// Image Optimization — free, opt-in module (it mutates uploads). One tool;
		// the compression and WebP pipeline itself is settings, not MCP surface.
		if ( class_exists( 'EMCP_Tools_Image_Optimization_Module' ) && EMCP_Tools_Image_Optimization_Module::module_is_active() ) {
			$tools['image_optimization'] = array(
				'platform' => 'modules',
				'label'    => __( 'Image Optimization', 'emcp-tools' ),
				'note'     => __( 'Compression, WebP generation, and the bulk optimizer are configured on the Modules tab. This is the one operation exposed over MCP.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/resize-media' => array(
						'label'       => __( 'Resize Media', 'emcp-tools' ),
						'description' => __( 'Resize a Media Library image in place (scale to fit, or crop to exact size). The attachment id and URLs are unchanged, and the original is backed up.', 'emcp-tools' ),
						'badges'      => array(),
					),
				),
			);
		}

		// EMCP Themer — free, module-gated. The template CPT lives under its own
		// top-level menu, but the tools belong on this grid like every other
		// domain, so an admin can see and toggle them individually. All nine are
		// on by default; the module toggle on the Modules tab remains the single
		// kill switch for the whole feature.
		if ( class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled() ) {
			$tools['themer'] = array(
				'platform' => 'modules',
				'label'    => __( 'EMCP Themer (theme builder)', 'emcp-tools' ),
				'note'     => __( 'Build headers, footers, and single/archive/search/404 layouts with any page builder, then decide where each one applies. Template CONTENT is built with the Gutenberg or Elementor tools against the returned template_id; these tools create and route the templates.', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/create-theme-template'   => array(
						'label'       => __( 'Create Theme Template', 'emcp-tools' ),
						'description' => __( 'Create a typed theme template (header, footer, single, archive, search, 404).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-theme-templates'    => array(
						'label'       => __( 'List Theme Templates', 'emcp-tools' ),
						'description' => __( 'List theme templates, optionally filtered by type.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-theme-template'      => array(
						'label'       => __( 'Get Theme Template', 'emcp-tools' ),
						'description' => __( 'Full detail for one template: type, conditions, detected builder, content status.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-theme-template'   => array(
						'label'       => __( 'Update Theme Template', 'emcp-tools' ),
						'description' => __( "Update a template's title or type.", 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/set-template-conditions' => array(
						'label'       => __( 'Set Template Conditions', 'emcp-tools' ),
						'description' => __( 'Set where a template applies. Granular selectors, Exclude rules, and priority require Pro.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-theme-template'   => array(
						'label'       => __( 'Delete Theme Template', 'emcp-tools' ),
						'description' => __( 'Delete a theme template.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
					'emcp-tools/resolve-template'        => array(
						'label'       => __( 'Resolve Template', 'emcp-tools' ),
						'description' => __( 'Show which template wins each slot (header/body/footer) for a given post or context. Use it to debug conditions.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-condition-targets'  => array(
						'label'       => __( 'List Condition Targets', 'emcp-tools' ),
						'description' => __( 'Discovery: the selectors and objects a template can target, so conditions are built from real values.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/list-dynamic-sources'    => array(
						'label'       => __( 'List Dynamic Sources', 'emcp-tools' ),
						'description' => __( 'Discovery: the dynamic sources this site offers, what each produces, and where each can be used (widget, block, Elementor tag, block binding).', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
				),
			);
		}

		// Themer PHP Templates — free, capability-gated + master-switch-gated;
		// disabled by default. AI authors DRAFTS; a human attaches one in a
		// template metabox (the execution gate). Registered only when the Themer
		// module is active, alongside where the feature actually lives.
		if ( class_exists( 'EMCP_Tools_Themer_Module' ) && EMCP_Tools_Themer_Module::is_enabled() ) {
			$tools['themer_php'] = array(
				'platform' => 'modules',
				'label'    => __( 'Themer PHP Templates', 'emcp-tools' ),
				'tools'    => array(
					'emcp-tools/create-theme-php-template' => array(
						'label'       => __( 'Create Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Create a validated DRAFT PHP region template (never runs until a human attaches it).', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/list-theme-php-templates'  => array(
						'label'       => __( 'List Theme PHP Templates', 'emcp-tools' ),
						'description' => __( 'List draft PHP templates.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/get-theme-php-template'    => array(
						'label'       => __( 'Get Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Return one PHP template with its validation report.', 'emcp-tools' ),
						'badges'      => array( 'read-only' ),
					),
					'emcp-tools/update-theme-php-template' => array(
						'label'       => __( 'Update Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Update a PHP template and re-validate.', 'emcp-tools' ),
						'badges'      => array(),
					),
					'emcp-tools/delete-theme-php-template' => array(
						'label'       => __( 'Delete Theme PHP Template', 'emcp-tools' ),
						'description' => __( 'Delete a PHP template and its sandbox file.', 'emcp-tools' ),
						'badges'      => array( 'destructive' ),
					),
				),
			);
		}

		// SEO & Accessibility toolkit (Pro) + Widget Builder (Pro). ALWAYS added
		// to the catalog so free users see the (locked) Pro surface; get_all_tools()
		// flags each 'pro' category "Requires EMCP Pro" and disables its toggles on
		// free builds, and the abilities themselves stay license-gated. Bare block
		// keeps the two category assignments grouped.
		{
			$tools['seo'] = array(
				'platform' => 'wordpress',
				'pro'      => true,
				'label' => __( 'SEO', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/audit-page-seo'                => array(
						'label'       => __( 'Audit Page SEO', 'emcp-tools' ),
						'description' => __( 'Scored on-page SEO report (H1, title/meta, canonical, alts, links, word count).', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/extract-keywords-from-content' => array(
						'label'       => __( 'Extract Keywords', 'emcp-tools' ),
						'description' => __( 'Frequency keyword + phrase extraction from page content.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/generate-meta-tags'            => array(
						'label'       => __( 'Generate Meta Tags', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true writes to Yoast/Rank Math) an SEO title and meta description. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/generate-schema-markup'        => array(
						'label'       => __( 'Generate Schema Markup', 'emcp-tools' ),
						'description' => __( 'Generates (apply:true injects) JSON-LD structured data (Article, LocalBusiness, FAQPage, etc.). Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/set-social-image'              => array(
						'label'       => __( 'Set Social Image', 'emcp-tools' ),
						'description' => __( 'Sets the Open Graph + Twitter share image (Yoast / Rank Math) so link previews use the image you choose, not the first content image.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
				),
			);

			$tools['a11y'] = array(
				'platform' => 'elementor',
				'label' => __( 'Accessibility', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/audit-page-a11y'           => array(
						'label'       => __( 'Audit Page Accessibility', 'emcp-tools' ),
						'description' => __( 'WCAG-oriented report: contrast, alts, heading order, link text, form labels.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/fix-color-contrast'        => array(
						'label'       => __( 'Fix Color Contrast', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true to write) adjusted text colors so failing pairs meet WCAG AA. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
					'emcp-tools/add-alt-text-from-context' => array(
						'label'       => __( 'Add Alt Text from Context', 'emcp-tools' ),
						'description' => __( 'Proposes (apply:true to write) alt text for images lacking it, from filename/heading/title. Dry-run by default.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);

			$tools['widget_builder'] = array(
				'platform' => 'elementor',
				'pro'      => true,
				'label' => __( 'Widget Builder (Pro)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-control-types'   => array(
						'label'       => __( 'List Control Types', 'emcp-tools' ),
						'description' => __( 'Returns the control types and template syntax for building widget specs.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/validate-widget-spec' => array(
						'label'       => __( 'Validate Widget Spec', 'emcp-tools' ),
						'description' => __( 'Validates a widget spec and dry-runs the generator without saving.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/create-custom-widget' => array(
						'label'       => __( 'Create Custom Widget', 'emcp-tools' ),
						'description' => __( 'Generates a custom Elementor widget from a spec into an isolated sandbox as an inactive draft. You activate it in Sandbox, Widgets.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/update-custom-widget' => array(
						'label'       => __( 'Update Custom Widget', 'emcp-tools' ),
						'description' => __( 'Replaces a custom widget\'s spec and regenerates its code.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/get-custom-widget'    => array(
						'label'       => __( 'Get Custom Widget', 'emcp-tools' ),
						'description' => __( 'Returns a custom widget\'s spec, generated PHP, status, and last error.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/list-custom-widgets'  => array(
						'label'       => __( 'List Custom Widgets', 'emcp-tools' ),
						'description' => __( 'Lists all generated custom widgets with their status.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/set-widget-status'    => array(
						'label'       => __( 'Set Widget Status', 'emcp-tools' ),
						'description' => __( 'Returns a custom widget to draft. Activation is yours, in Sandbox, Widgets.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/delete-custom-widget' => array(
						'label'       => __( 'Delete Custom Widget', 'emcp-tools' ),
						'description' => __( 'Permanently deletes a custom widget and its sandbox file.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);

			$tools['block_builder'] = array(
				'platform' => 'gutenberg',
				'pro'      => true,
				'label' => __( 'Block Builder (Pro)', 'emcp-tools' ),
				'tools' => array(
					'emcp-tools/list-block-control-types' => array(
						'label'       => __( 'List Block Control Types', 'emcp-tools' ),
						'description' => __( 'Returns the attribute types and template syntax for building block specs.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/validate-block-spec'      => array(
						'label'       => __( 'Validate Block Spec', 'emcp-tools' ),
						'description' => __( 'Validates a block spec and dry-runs the generator without saving.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/create-custom-block'      => array(
						'label'       => __( 'Create Custom Block', 'emcp-tools' ),
						'description' => __( 'Generates a custom Gutenberg block from a spec into an isolated sandbox as an inactive draft. You activate it in Sandbox, Blocks.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/update-custom-block'      => array(
						'label'       => __( 'Update Custom Block', 'emcp-tools' ),
						'description' => __( 'Replaces a custom block\'s spec and regenerates its code.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/get-custom-block'          => array(
						'label'       => __( 'Get Custom Block', 'emcp-tools' ),
						'description' => __( 'Returns a custom block\'s spec, generated code, status, and last error.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/list-custom-blocks'        => array(
						'label'       => __( 'List Custom Blocks', 'emcp-tools' ),
						'description' => __( 'Lists all generated custom blocks with their status.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'read-only' ),
					),
					'emcp-tools/set-block-status'          => array(
						'label'       => __( 'Set Block Status', 'emcp-tools' ),
						'description' => __( 'Returns a custom block to draft. Activation is yours, in Sandbox, Blocks.', 'emcp-tools' ),
						'badges'      => array( 'pro' ),
					),
					'emcp-tools/delete-custom-block'       => array(
						'label'       => __( 'Delete Custom Block', 'emcp-tools' ),
						'description' => __( 'Permanently deletes a custom block and its sandbox file.', 'emcp-tools' ),
						'badges'      => array( 'pro', 'destructive' ),
					),
				),
			);
		}

		if ( class_exists( 'EMCP_Tools_Bricks_Integration' ) ) {
			$tools = array_merge( $tools, EMCP_Tools_Bricks_Integration::admin_categories() );
		}
		if (class_exists('EMCP_Tools_Beaver_Integration')) { $tools=array_merge($tools,EMCP_Tools_Beaver_Integration::admin_categories()); }
		if (class_exists('EMCP_Tools_Visual_Composer_Integration')) { $tools=array_merge($tools,EMCP_Tools_Visual_Composer_Integration::admin_categories()); }
		if (class_exists('EMCP_Tools_WPBakery_Integration')) { $tools=array_merge($tools,EMCP_Tools_WPBakery_Integration::admin_categories()); }
		if (class_exists('EMCP_Tools_Kirki_Integration')) { $tools=array_merge($tools,EMCP_Tools_Kirki_Integration::admin_categories()); }
		if (class_exists('EMCP_Tools_Oxygen_Integration')) { $tools=array_merge($tools,EMCP_Tools_Oxygen_Integration::admin_categories()); }
		if ( class_exists( 'EMCP_Tools_Breakdance_Integration' ) ) {
			$tools = array_merge( $tools, EMCP_Tools_Breakdance_Integration::admin_categories() );
		}
		if ( class_exists( 'EMCP_Tools_Avada_Integration' ) ) {
			$tools = array_merge( $tools, EMCP_Tools_Avada_Integration::admin_categories() );
		}
		if ( class_exists( 'EMCP_Tools_Thrive_Integration' ) ) { $tools = array_merge( $tools, EMCP_Tools_Thrive_Integration::admin_categories() ); }
		if ( class_exists( 'EMCP_Tools_Divi_Integration' ) ) {
			$tools = array_merge( $tools, EMCP_Tools_Divi_Integration::admin_categories() );
		}
		foreach ( array('theme_astra_spectra'=>array('spectra','Astra'), 'theme_kadence'=>array('kadence-blocks','Kadence'), 'theme_generatepress'=>array('generateblocks','GeneratePress')) as $category => $split ) {
			if (!isset($tools[$category])) { continue; }
			[$id,$label]=$split;
			$tools[$category]['label']=$label;
			$tools[$category]['note']='Theme settings only. The block plugin has its own integration and tools tab.';
			unset($tools[$category]['notice']);
			$compat=array();
			foreach (array('read','write') as $mode) {
				$slug='emcp-tools/'.$id.'-'.$mode;
				if(isset($tools[$category]['tools'][$slug])) { $compat[$slug]=$tools[$category]['tools'][$slug]; unset($tools[$category]['tools'][$slug]); }
			}
			$tools[$id.'_compatibility']=array('platform'=>$id,'label'=>'Compatibility','note'=>'Existing dispatcher names remain supported. Use individual tools for new workflows.','pro'=>$id==='generateblocks','tools'=>$compat);
		}
		// Preserve existing tool names and choices while separating their owners.
		$blocksy_legacy=$tools['theme_blocksy']['tools'];
		$tools['blocksy-blocks_compatibility']=array('platform'=>'blocksy-blocks','label'=>__('Compatibility','emcp-tools'),'pro'=>true,'tools'=>array_intersect_key($blocksy_legacy,array_flip(array('emcp-tools/blocksy-blocks-read','emcp-tools/blocksy-blocks-write'))));
		$tools['blocksy_companion_extensions']=array('platform'=>'plugins','group'=>'other','label'=>__('Blocksy Companion: Extensions','emcp-tools'),'pro'=>true,'tools'=>array_intersect_key($blocksy_legacy,array_flip(array('emcp-tools/blocksy-extensions-read','emcp-tools/blocksy-extensions-write'))));
		unset($tools['theme_blocksy']);
		foreach(array(
			'blocksy-theme'=>array('themes','Blocksy Theme','EMCP_Tools_Blocksy_Theme_Integration',array('get-context','get-settings','get-design-settings'),array('update-settings')),
			'blocksy-content'=>array('plugins','Blocksy Companion: Content Blocks','EMCP_Tools_Blocksy_Content_Integration',array('get-context','list-content-blocks','get-content-block','list-hooks'),array('create-content-block','update-content-block','publish-content-block','unpublish-content-block')),
		) as $id=>$spec) {
			$entries=array();
			foreach(array('read','write') as $mode) { $entries['emcp-tools/'.$id.'-'.$mode]=array('label'=>$spec[1].' '.ucfirst($mode),'description'=>$id==='blocksy-theme'?__('Native theme layout settings and read-only design configuration.','emcp-tools'):__('Native Pro templates, hooks and popups. Create disabled drafts, edit content and explicitly publish.','emcp-tools'),'operations'=>$spec[$mode==='read'?3:4],'badges'=>$mode==='read'?array('read-only','pro'):array('pro'),'available'=>class_exists($spec[2]) && (new $spec[2]())->is_available(),'requires'=>array('name'=>$id==='blocksy-theme'?'Blocksy':'Blocksy Companion Pro','kind'=>$id==='blocksy-theme'?'theme':'plugin')); }
			$tools[$id]=array('platform'=>$spec[0],'group'=>'other','label'=>$spec[1],'pro'=>true,'tools'=>$entries);
		}
		$tools=array_merge($tools,EMCP_Tools_Block_Pack_Integration::admin_categories());
		$tools['spectra_discovery']['notice']=self::spectra_file_generation_notice();
		return $tools;
	}
}
