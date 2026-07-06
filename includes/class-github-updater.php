<?php
/**
 * GitHub-release updater for the FREE build.
 *
 * The free plugin is distributed via GitHub releases (not wordpress.org and not
 * Freemius — the auto-generated Freemius free build is kept unreleased). Without
 * this, free users would never see an in-dashboard "update available" prompt.
 * This class bridges GitHub releases into WordPress's native plugin-update flow
 * so free users update straight from Dashboard → Updates / the Plugins screen,
 * exactly like a wordpress.org plugin.
 *
 * Premium builds ship the `.emcp-pro` marker and let Freemius handle updates —
 * this updater self-disables there (see init()).
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves free-tier updates from the public GitHub repo's latest release.
 *
 * @since 3.2.0
 */
class EMCP_Tools_GitHub_Updater {

	/** GitHub `owner/repo` the free releases live on. */
	const REPO = 'msrbuilds/elementor-mcp';

	/** Transient caching the parsed latest-release payload (limits API hits). */
	const TRANSIENT = 'emcp_tools_github_release';

	/** How long to cache a successful release lookup. */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** Shorter cache after a failed/empty lookup so we recover quickly. */
	const CACHE_TTL_FAIL = 2 * HOUR_IN_SECONDS;

	/** Matches the free release ZIP asset (never the Pro zip, which isn't on GitHub). */
	const ASSET_PATTERN = '/^emcp-tools-[0-9].*\.zip$/i';

	/**
	 * The installed plugin folder slug (e.g. `emcp-tools` or `elementor-mcp`),
	 * used as the `plugins_api` slug and the target extraction directory.
	 *
	 * @var string
	 */
	private $slug;

	public function __construct() {
		$this->slug = dirname( EMCP_TOOLS_BASENAME );
	}

	/**
	 * Register the update hooks — but only for the FREE build. On premium builds
	 * (the `.emcp-pro` marker is present and Freemius reports is_premium) Freemius
	 * owns updates, so we stay out of the way to avoid a double-updater conflict.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->is_premium_build() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
	}

	/**
	 * Whether this is the premium build (Freemius handles those updates).
	 *
	 * @return bool
	 */
	private function is_premium_build(): bool {
		if ( file_exists( EMCP_TOOLS_DIR . '.emcp-pro' ) ) {
			return true;
		}
		return function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->is_premium();
	}

	/**
	 * Inject an update entry into the plugin-update transient when GitHub's latest
	 * release is newer than the installed version; otherwise record a no_update
	 * entry so WordPress shows the plugin as up to date.
	 *
	 * @param mixed $transient The `update_plugins` site transient (object or empty).
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$item = (object) array(
			'id'            => 'github.com/' . self::REPO,
			'slug'          => $this->slug,
			'plugin'        => EMCP_TOOLS_BASENAME,
			'new_version'   => $release['version'],
			'url'           => 'https://github.com/' . self::REPO,
			'package'       => $release['package'],
			'icons'         => $this->plugin_icons(),
			'banners'       => array(),
			'tested'        => $release['tested'],
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'compatibility' => new stdClass(),
		);

		if (
			'' !== $release['package']
			&& version_compare( $release['version'], EMCP_TOOLS_VERSION, '>' )
		) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ EMCP_TOOLS_BASENAME ] = $item;
			unset( $transient->no_update[ EMCP_TOOLS_BASENAME ] );
		} else {
			// Up to date — populate no_update so the Plugins screen reflects it.
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ EMCP_TOOLS_BASENAME ] = $item;
		}

		return $transient;
	}

	/**
	 * Supply the "View details" modal for our plugin from the GitHub release.
	 *
	 * @param mixed  $result The value being filtered (false by default).
	 * @param string $action The requested plugins_api action.
	 * @param object $args   Args, including the requested `slug`.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'EMCP Tools';
		$info->slug          = $this->slug;
		$info->version       = $release['version'];
		$info->author        = '<a href="https://msrbuilds.com">Mian Shahzad Raza</a>';
		$info->homepage      = 'https://github.com/' . self::REPO;
		$info->download_link = $release['package'];
		$info->trunk         = $release['package'];
		$info->requires      = $release['requires'];
		$info->requires_php  = $release['requires_php'];
		$info->tested        = $release['tested'];
		$info->last_updated  = $release['published'];
		$info->icons         = $this->plugin_icons();
		$info->sections      = array(
			'changelog' => $this->render_changelog( $release['changelog'], $release['html_url'] ),
		);

		return $info;
	}

	/**
	 * Ensure the extracted release folder is renamed to the installed plugin
	 * folder before WordPress moves it into place. The free ZIP unpacks to
	 * `emcp-tools/`; a site whose folder differs (e.g. a `git clone` named
	 * `elementor-mcp`) would otherwise get a second, orphaned plugin copy.
	 *
	 * @param string      $source        Extracted source directory.
	 * @param string      $remote_source Parent of $source.
	 * @param WP_Upgrader $upgrader      The upgrader instance.
	 * @param array       $args          Hook extras (includes `plugin` on plugin updates).
	 * @return string|WP_Error
	 */
	public function rename_source_dir( $source, $remote_source, $upgrader, $args = array() ) {
		global $wp_filesystem;

		// Only touch OUR update.
		if ( empty( $args['plugin'] ) || EMCP_TOOLS_BASENAME !== $args['plugin'] ) {
			return $source;
		}
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->slug . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}

		return new WP_Error(
			'emcp_tools_rename_failed',
			__( 'Could not rename the downloaded EMCP Tools folder during the update.', 'emcp-tools' )
		);
	}

	/**
	 * Clear the cached release after our plugin finishes updating, so the next
	 * check reflects the freshly installed version.
	 *
	 * @param WP_Upgrader $upgrader The upgrader instance.
	 * @param array       $data     Process data (`action`, `type`, `plugins`).
	 * @return void
	 */
	public function clear_cache_after_update( $upgrader, $data ): void {
		if ( ! is_array( $data ) || ( $data['action'] ?? '' ) !== 'update' || ( $data['type'] ?? '' ) !== 'plugin' ) {
			return;
		}
		$plugins = (array) ( $data['plugins'] ?? array() );
		if ( in_array( EMCP_TOOLS_BASENAME, $plugins, true ) ) {
			delete_transient( self::TRANSIENT );
		}
	}

	/**
	 * Fetch + parse the latest GitHub release, cached in a transient to respect
	 * GitHub's unauthenticated rate limit (60/hr per IP). Returns null on any
	 * failure (network, non-200, no matching asset), caching the miss briefly.
	 *
	 * @return array{version:string,package:string,changelog:string,published:string,html_url:string,tested:string,requires:string,requires_php:string}|null
	 */
	private function get_latest_release(): ?array {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'EMCP-Tools-Updater/' . EMCP_TOOLS_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::TRANSIENT, array(), self::CACHE_TTL_FAIL );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_transient( self::TRANSIENT, array(), self::CACHE_TTL_FAIL );
			return null;
		}

		// Skip drafts / pre-releases — free users should only get stable builds.
		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			set_transient( self::TRANSIENT, array(), self::CACHE_TTL_FAIL );
			return null;
		}

		$version = ltrim( (string) $data['tag_name'], 'vV' );
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( ! empty( $asset['name'] ) && preg_match( self::ASSET_PATTERN, $asset['name'] ) ) {
				$package = (string) ( $asset['browser_download_url'] ?? '' );
				break;
			}
		}

		if ( '' === $version || '' === $package ) {
			set_transient( self::TRANSIENT, array(), self::CACHE_TTL_FAIL );
			return null;
		}

		$release = array(
			'version'      => $version,
			'package'      => $package,
			'changelog'    => (string) ( $data['body'] ?? '' ),
			'published'    => (string) ( $data['published_at'] ?? '' ),
			'html_url'     => (string) ( $data['html_url'] ?? ( 'https://github.com/' . self::REPO . '/releases' ) ),
			'tested'       => $this->plugin_header( 'Tested up to', '6.9' ),
			'requires'     => $this->plugin_header( 'Requires at least', '6.9' ),
			'requires_php' => $this->plugin_header( 'Requires PHP', '8.1' ),
		);

		set_transient( self::TRANSIENT, $release, self::CACHE_TTL );
		return $release;
	}

	/**
	 * Read a value from the installed plugin's header (cheap, cached by core).
	 *
	 * @param string $field    Header field label.
	 * @param string $fallback Default when unavailable.
	 * @return string
	 */
	private function plugin_header( string $field, string $fallback ): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( EMCP_TOOLS_DIR . basename( EMCP_TOOLS_BASENAME ), false, false );
		$map  = array(
			'Tested up to'     => 'Tested up to',
			'Requires at least' => 'RequiresWP',
			'Requires PHP'     => 'RequiresPHP',
		);
		$key  = $map[ $field ] ?? $field;
		$val  = isset( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
		return '' !== $val ? $val : $fallback;
	}

	/**
	 * The plugin's own bundled icons for the update UI.
	 *
	 * @return array<string,string>
	 */
	private function plugin_icons(): array {
		return array(
			'1x'      => EMCP_TOOLS_URL . 'assets/img/icon-sm.png',
			'default' => EMCP_TOOLS_URL . 'assets/img/icon-sm.png',
		);
	}

	/**
	 * Turn the release's markdown body into safe HTML for the details modal, with
	 * a link to the full notes on GitHub.
	 *
	 * @param string $markdown Release body.
	 * @param string $html_url GitHub release URL.
	 * @return string
	 */
	private function render_changelog( string $markdown, string $html_url ): string {
		$markdown = trim( $markdown );
		if ( '' === $markdown ) {
			$body = '<p>' . esc_html__( 'See the full release notes on GitHub.', 'emcp-tools' ) . '</p>';
		} else {
			// Light markdown → HTML: headings, list bullets, paragraphs. Escaped first.
			$lines = preg_split( '/\r\n|\r|\n/', $markdown );
			$out   = array();
			$in_ul = false;
			foreach ( $lines as $line ) {
				$line = rtrim( $line );
				if ( preg_match( '/^#{1,6}\s+(.*)$/', $line, $m ) ) {
					if ( $in_ul ) {
						$out[] = '</ul>';
						$in_ul = false;
					}
					$out[] = '<h4>' . esc_html( $m[1] ) . '</h4>';
				} elseif ( preg_match( '/^[\-\*]\s+(.*)$/', $line, $m ) ) {
					if ( ! $in_ul ) {
						$out[] = '<ul>';
						$in_ul = true;
					}
					$out[] = '<li>' . esc_html( $m[1] ) . '</li>';
				} elseif ( '' === trim( $line ) ) {
					if ( $in_ul ) {
						$out[] = '</ul>';
						$in_ul = false;
					}
				} else {
					if ( $in_ul ) {
						$out[] = '</ul>';
						$in_ul = false;
					}
					$out[] = '<p>' . esc_html( $line ) . '</p>';
				}
			}
			if ( $in_ul ) {
				$out[] = '</ul>';
			}
			$body = implode( "\n", $out );
		}

		$body .= '<p><a href="' . esc_url( $html_url ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'View full release notes on GitHub →', 'emcp-tools' ) . '</a></p>';

		return $body;
	}
}
