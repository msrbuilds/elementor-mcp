<?php
/**
 * Builds a Claude Desktop .mcpb bundle that installs the EMCP MCP server.
 *
 * The bundle contains two files:
 *   manifest.json      — MCPB 0.3 manifest
 *   server/index.js    — self-contained CJS proxy (credentials embedded,
 *                        ESM imports converted, no spawn, no npx, no PATH)
 *
 * server/index.js IS the MCP server — it reads JSON-RPC from stdin, forwards
 * it to the WordPress REST endpoint, and writes responses to stdout. Running
 * it requires nothing beyond the Node.js binary Claude Desktop already has.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Mcpb_Builder {

	const MANIFEST_VERSION = '0.3';

	/** Relative path of the entry-point inside the bundle. */
	const ENTRY_POINT = 'server/index.js';

	/**
	 * Build the MCPB manifest array. Pure — no I/O.
	 *
	 * @param string $site_url     home_url() of the WordPress site.
	 * @param string $username     WordPress login.
	 * @param string $app_password Application Password (baked into env).
	 * @return array
	 */
	public static function build_manifest( string $site_url, string $username, string $app_password ): array {
		$host    = (string) wp_parse_url( $site_url, PHP_URL_HOST );
		$slug    = self::host_slug( $host );
		$version = defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '0.0.0';

		return array(
			'manifest_version' => self::MANIFEST_VERSION,
			// Unique per site: Claude Desktop identifies extensions by the
			// manifest `name`, so a fixed name makes each install silently
			// overwrite the previous site's bundle. See #86.
			'name'             => '' !== $slug ? 'emcp-tools-' . $slug : 'emcp-tools',
			'display_name'     => sprintf( 'EMCP Tools — %s', $host ),
			'version'          => $version,
			'description'      => sprintf( 'Connect Claude Desktop to %s for Elementor and WordPress management via MCP.', $host ),
			'author'           => array( 'name' => 'MSR Builds' ),
			'server'           => array(
				'type'        => 'node',
				'entry_point' => self::ENTRY_POINT,
				// mcp_config mirrors the entry-point command so the manifest
				// is valid regardless of which path Claude Desktop takes.
				// args MUST use ${__dirname} — Claude Desktop does not cd into
				// the extracted bundle dir before running `node`, so a relative
				// path resolves against the wrong CWD and Node throws "Cannot
				// find module" → instant "Server disconnected". ${__dirname} is
				// the MCPB substitution variable for the bundle's install path.
				'mcp_config'  => array(
					'command' => 'node',
					'args'    => array( '${__dirname}/' . self::ENTRY_POINT ),
					'env'     => array(
						'WP_URL'               => $site_url,
						'WP_USERNAME'          => $username,
						'WP_APP_PASSWORD'      => $app_password,
						'MCP_PROTOCOL_VERSION' => '2024-11-05',
					),
				),
			),
		);
	}

	/**
	 * Derive a Claude-Desktop-safe, per-site suffix from the site host.
	 *
	 * Claude Desktop keys installed extensions by the manifest `name` (not the
	 * filename or `display_name`), so the name must be unique per site or a
	 * second install replaces the first. e.g. "staging.example.co.uk" →
	 * "staging-example-co-uk".
	 *
	 * @param string $host wp_parse_url() host component.
	 * @return string Lower-case slug, or '' when the host yields no slug.
	 */
	private static function host_slug( string $host ): string {
		$slug = preg_replace( '/[^a-z0-9]+/', '-', strtolower( $host ) );
		return trim( (string) $slug, '-' );
	}

	/**
	 * Write the manifest + self-contained proxy into a temp .mcpb (zip) file.
	 *
	 * @param array $manifest
	 * @return string|\WP_Error Temp file path.
	 */
	public static function build_zip( array $manifest ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'no_zip', __( 'The ZipArchive PHP extension is required to build the bundle.', 'emcp-tools' ) );
		}

		// Convert the ESM proxy source to a self-contained CJS server file.
		$proxy_source_path = EMCP_TOOLS_DIR . 'bin/mcp-proxy.mjs';
		$proxy_source      = @file_get_contents( $proxy_source_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( false === $proxy_source ) {
			return new \WP_Error( 'no_proxy', __( 'Could not read the bundled proxy file (bin/mcp-proxy.mjs). Please reinstall the plugin.', 'emcp-tools' ) );
		}

		$env           = $manifest['server']['mcp_config']['env'] ?? array();
		$server_js     = self::build_server_js( $proxy_source, $env );
		$server_js_err = self::validate_server_js( $server_js );
		if ( null !== $server_js_err ) {
			return new \WP_Error( 'bad_server_js', $server_js_err );
		}

		$tmp = wp_tempnam( 'emcp-tools.mcpb' );
		if ( ! $tmp ) {
			return new \WP_Error( 'no_tmp', __( 'Could not create a temporary file for the bundle.', 'emcp-tools' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new \WP_Error( 'no_open', __( 'Could not open the bundle archive for writing.', 'emcp-tools' ) );
		}

		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->addFromString( self::ENTRY_POINT, $server_js );

		$zip->close();
		return $tmp;
	}

	/**
	 * Converts the ESM proxy source into a self-contained CJS server file with
	 * credentials embedded. No spawn, no npx, no PATH — just Node.js built-ins.
	 *
	 * The conversion is minimal: replace the four ESM import lines with their
	 * CJS require() equivalents, remove the shebang, and prepend a credential
	 * override block so the file works even when the host does not inject env vars.
	 *
	 * @param string $esm_source The raw content of bin/mcp-proxy.mjs.
	 * @param array  $env        Credential env vars to embed (key → value).
	 * @return string CJS source ready to run with `node`.
	 */
	public static function build_server_js( string $esm_source, array $env = array() ): string {
		// 1. Remove the shebang line if present.
		$source = preg_replace( '/^#![^\n]*\n/', '', $esm_source );

		// 2. Replace the four ESM import lines with CJS require() equivalents.
		//    The proxy only imports from Node built-ins so this is exhaustive.
		$esm_to_cjs = array(
			"/import\s*\{\s*createInterface\s*\}\s*from\s*'node:readline'\s*;/"
				=> "const { createInterface } = require('readline');",
			"/import\s*\{\s*request\s*as\s*httpRequest\s*\}\s*from\s*'node:http'\s*;/"
				=> "const { request: httpRequest } = require('http');",
			"/import\s*\{\s*request\s*as\s*httpsRequest\s*\}\s*from\s*'node:https'\s*;/"
				=> "const { request: httpsRequest } = require('https');",
			"/import\s*\{\s*appendFileSync\s*\}\s*from\s*'node:fs'\s*;/"
				=> "const { appendFileSync } = require('fs');",
		);
		foreach ( $esm_to_cjs as $pattern => $replacement ) {
			$source = (string) preg_replace( $pattern, $replacement, $source );
		}

		// 3. Build the credential preamble: overrides process.env BEFORE any
		//    code reads from it, so the file works whether or not the host
		//    injects the mcp_config.env values.
		$lines = array(
			"'use strict';",
			'// EMCP Tools — self-contained MCP proxy (credentials embedded).',
			'// Credentials are set here so this works even when the host does',
			'// not inject mcp_config.env into the process environment.',
		);
		foreach ( $env as $key => $value ) {
			$lines[] = sprintf(
				"process.env[%s] = process.env[%s] || %s;",
				wp_json_encode( (string) $key ),
				wp_json_encode( (string) $key ),
				wp_json_encode( (string) $value )
			);
		}
		$preamble = implode( "\n", $lines ) . "\n\n";

		return $preamble . ltrim( $source );
	}

	/**
	 * Basic sanity check: ensure the key CJS require() lines are present
	 * and no ESM import statements remain.
	 *
	 * @param string $source
	 * @return string|null Error message, or null if valid.
	 */
	private static function validate_server_js( string $source ): ?string {
		$required = array(
			"require('readline')",
			"require('http')",
			"require('https')",
			"require('fs')",
		);
		foreach ( $required as $needle ) {
			if ( false === strpos( $source, $needle ) ) {
				return "Built server/index.js is missing expected require: {$needle}";
			}
		}
		if ( preg_match( "/\bimport\s+[{*]/", $source ) ) {
			return 'Built server/index.js still contains ESM import statements.';
		}
		return null;
	}
}
