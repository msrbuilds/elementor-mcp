<?php
/**
 * Sandbox screens data: widgets, blocks and PHP snippets as one kind of list
 * (spec 8.11 to 8.14). Access follows the stores: widgets and blocks need the
 * licence (blocks also the Pro store), snippet writes need unfiltered_html.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sandbox lists, overview and item detail.
 */
final class EMCP_Tools_Admin_Sandbox_Data {

	const TYPES      = array( 'widgets' => 'widget', 'blocks' => 'block', 'snippets' => 'snippet' );
	const POST_TYPES = array( 'widget' => 'emcp_widget', 'block' => 'emcp_block', 'snippet' => 'emcp_php_snippet' );
	const MAX_ROWS   = 500;
	const REVIEW_MAX = 20;
	const PAGE       = 'emcp-tools-widgets';

	/**
	 * Rows without Cloud state, per type, for this request.
	 *
	 * @var array<string,array>
	 */
	private $cache = array();

	/**
	 * Kind of a list type ('' when unknown).
	 *
	 * @param string $type widgets | blocks | snippets.
	 */
	public static function kind( string $type ): string {
		return self::TYPES[ $type ] ?? '';
	}

	/**
	 * List type of a kind ('' when unknown).
	 *
	 * @param string $kind widget | block | snippet.
	 */
	public static function type_of( string $kind ): string {
		$map = array_flip( self::TYPES );
		return $map[ $kind ] ?? '';
	}

	/**
	 * Sandbox URL, optionally a child view and an item to open.
	 *
	 * @param string $type   View ('' for the overview).
	 * @param int    $review Item whose code drawer opens.
	 */
	public static function view_url( string $type, int $review = 0 ): string {
		return admin_url( 'admin.php?page=' . self::PAGE . ( '' !== $type ? '&view=' . $type : '' ) . ( $review > 0 ? '&review=' . $review : '' ) );
	}

	/**
	 * Whether the current user may read (or change) a list.
	 *
	 * @param string $type  widgets | blocks | snippets.
	 * @param bool   $write A change (snippet changes need unfiltered_html).
	 * @return true|WP_Error
	 */
	public static function can( string $type, bool $write = false ) {
		switch ( $type ) {
			case 'widgets':
				$ok = class_exists( 'EMCP_Tools_Widget_Store' ) && EMCP_Tools_Widget_Store::user_has_access();
				break;
			case 'blocks':
				$ok = class_exists( 'EMCP_Tools_Block_Store' ) && EMCP_Tools_Block_Store::user_has_access();
				break;
			case 'snippets':
				if ( $write && class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) && ! EMCP_Tools_PHP_Snippet_Store::can_edit() ) {
					return new WP_Error( 'emcp_sandbox_snippet_caps', __( 'Managing PHP snippets requires the manage_options and unfiltered_html capabilities.', 'emcp-tools' ), array( 'status' => 403 ) );
				}
				$ok = class_exists( 'EMCP_Tools_PHP_Snippet_Store' ) && EMCP_Tools_PHP_Snippet_Store::can_read();
				break;
			default:
				return new WP_Error( 'emcp_sandbox_unknown', __( 'Unknown Sandbox list.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return $ok ? true : new WP_Error( 'emcp_sandbox_pro_required', __( 'This part of the Sandbox needs EMCP Pro.', 'emcp-tools' ), array( 'status' => 403 ) );
	}

	/**
	 * Store summary of one artifact.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Id.
	 * @return array|WP_Error
	 */
	private static function summary( string $kind, int $id ) {
		if ( 'widget' === $kind ) {
			return EMCP_Tools_Widget_Store::summary( $id );
		}
		if ( 'block' === $kind ) {
			return EMCP_Tools_Block_Store::instance()->summary( $id );
		}
		return EMCP_Tools_PHP_Snippet_Store::summary( $id );
	}

	/**
	 * Every artifact id of a kind (capped).
	 *
	 * @param string $kind Kind.
	 * @return int[]
	 */
	private static function ids( string $kind ): array {
		return array_map(
			'intval',
			(array) get_posts(
				array(
					'post_type'      => self::POST_TYPES[ $kind ],
					'post_status'    => 'any',
					'posts_per_page' => self::MAX_ROWS,
					'fields'         => 'ids',
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			)
		);
	}

	private static function cloud_connected(): bool {
		return class_exists( 'EMCP_Tools_Cloud' ) && EMCP_Tools_Cloud::is_connected();
	}

	/**
	 * One list row, no source. Null when the id is not an artifact of this kind.
	 *
	 * @param string $kind  Kind.
	 * @param int    $id    Id.
	 * @param bool   $cloud Include Cloud and Marketplace state.
	 */
	public static function row( string $kind, int $id, bool $cloud ): ?array {
		$s = self::summary( $kind, $id );
		if ( is_wp_error( $s ) ) {
			return null;
		}
		$post = get_post( $id );
		$date = $post ? (string) ( $post->post_modified_gmt ?? $post->post_modified ?? '' ) : '';
		if ( 'widget' === $kind ) {
			$ident = (string) $s['widget_name'];
		} elseif ( 'block' === $kind ) {
			$ident = (string) $s['block_name'];
		} else {
			$ident = (string) $s['shortcode'];
		}
		$row = array(
			'id'          => $id,
			'kind'        => $kind,
			'title'       => (string) $s['title'],
			'ident'       => $ident,
			'active'      => 'active' === $s['status'],
			'lastError'   => (string) ( $s['last_error'] ?? '' ),
			'updatedTs'   => '' !== $date ? (int) strtotime( $date . ' UTC' ) : 0,
			'review'      => self::review( $kind, $id, $s ),
			'cloud'       => $cloud ? EMCP_Tools_Sandbox_Cloud_State::column( $kind, $id ) : 'none',
			'marketplace' => $cloud ? EMCP_Tools_Sandbox_Cloud_State::marketplace( $kind, $id ) : null,
		);
		if ( 'snippet' === $kind ) {
			$row['context']  = (string) $s['context'];
			$row['hook']     = (string) $s['hook'];
			$row['priority'] = (int) $s['priority'];
			$row['runsOn']   = 'shortcode' === $s['context'] ? 'shortcode' : (string) $s['hook'];
		}
		return $row;
	}

	/**
	 * Review summary: snippets from the validator, every kind from a recorded crash.
	 *
	 * @param string $kind Kind.
	 * @param int    $id   Id.
	 * @param array  $s    Store summary.
	 */
	private static function review( string $kind, int $id, array $s ): array {
		if ( '' !== (string) ( $s['last_error'] ?? '' ) ) {
			return array( 'level' => 'error', 'text' => __( 'Switched off after an error', 'emcp-tools' ) );
		}
		if ( 'snippet' !== $kind ) {
			return array( 'level' => 'none', 'text' => __( 'Nothing flagged', 'emcp-tools' ) );
		}
		$sum = EMCP_Tools_PHP_Snippet_Validator::summary( EMCP_Tools_PHP_Snippet_Store::get_validation( $id ) );
		$c   = $sum['counts'];
		if ( $sum['blocked'] ) {
			return array( 'level' => 'critical', 'text' => (string) $sum['headline'] );
		}
		if ( $c['warning'] > 0 ) {
			/* translators: %d: number of findings worth reading. */
			return array( 'level' => 'warning', 'text' => sprintf( _n( '%d thing worth reading', '%d things worth reading', $c['warning'], 'emcp-tools' ), $c['warning'] ) );
		}
		if ( $c['notice'] > 0 ) {
			/* translators: %d: number of ordinary notes. */
			return array( 'level' => 'notice', 'text' => sprintf( _n( '%d ordinary note', '%d ordinary notes', $c['notice'], 'emcp-tools' ), $c['notice'] ) );
		}
		return array( 'level' => 'none', 'text' => __( 'Nothing flagged', 'emcp-tools' ) );
	}

	/**
	 * Every row of a type the viewer can read, without Cloud state (cached per request).
	 *
	 * @param string $type Type.
	 */
	private function all_rows( string $type ): array {
		if ( ! isset( $this->cache[ $type ] ) ) {
			$this->cache[ $type ] = array();
			if ( true === self::can( $type ) ) {
				$kind = self::kind( $type );
				foreach ( self::ids( $kind ) as $id ) {
					$row = self::row( $kind, $id, false );
					if ( $row ) {
						$this->cache[ $type ][] = $row;
					}
				}
			}
		}
		return $this->cache[ $type ];
	}

	private static function ai_chat_url(): string {
		$url = admin_url( 'admin.php?page=emcp-tools-ai-chat' );
		if ( ! class_exists( 'EMCP_Tools_AI_Chat_Module' ) ) {
			return $url; // Free build: the tab shows the locked screen.
		}
		return in_array( 'ai-chat', (array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() ), true ) ? $url : '';
	}

	/**
	 * One page of a list.
	 *
	 * @param string $type  widgets | blocks | snippets.
	 * @param array  $query { status, search, page }.
	 * @return array|WP_Error
	 */
	public function list( string $type, array $query ) {
		$ok = self::can( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind  = self::kind( $type );
		$cloud = self::cloud_connected();
		$rows  = array();
		foreach ( self::ids( $kind ) as $id ) {
			$row = self::row( $kind, $id, $cloud );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		return array_merge(
			array(
				'type' => $type,
				'kind' => $kind,
			),
			EMCP_Tools_Admin_Sandbox_List::build( $rows, $query ),
			array(
				'cloud'     => array(
					'connected'  => $cloud,
					'connectUrl' => admin_url( 'admin.php?page=emcp-tools-connection' ),
				),
				'elementor' => 'widgets' !== $type || ( class_exists( 'EMCP_Tools_Bootstrap' ) && EMCP_Tools_Bootstrap::elementor_active() ),
				'aiChatUrl' => self::ai_chat_url(),
				'canEdit'   => 'snippets' !== $type || EMCP_Tools_PHP_Snippet_Store::can_edit(),
				'backUrl'   => self::view_url( '' ),
			)
		);
	}

	/** Overview: cards, the review queue and the Export button state. */
	public function overview(): array {
		$cards  = array();
		$review = array();
		foreach ( array( 'widgets', 'snippets', 'blocks' ) as $type ) {
			$rows    = $this->all_rows( $type );
			$active  = count( array_filter( array_column( $rows, 'active' ) ) );
			$cards[] = array(
				'type'      => $type,
				'available' => true === self::can( $type ),
				'active'    => $active,
				'inactive'  => count( $rows ) - $active,
				'url'       => self::view_url( $type ),
			);
			foreach ( $rows as $r ) {
				if ( $r['active'] ) {
					continue;
				}
				$post     = get_post( $r['id'] );
				$author   = $post ? get_userdata( (int) ( $post->post_author ?? 0 ) ) : false;
				$review[] = array(
					'kind'      => $r['kind'],
					'type'      => $type,
					'id'        => $r['id'],
					'title'     => $r['title'],
					'review'    => $r['review'],
					'author'    => $author ? (string) $author->display_name : '',
					'updatedTs' => $r['updatedTs'],
					'reviewUrl' => self::view_url( $type, $r['id'] ),
				);
			}
		}
		usort(
			$review,
			static function ( array $a, array $b ): int {
				return array( $b['updatedTs'], $b['id'] ) <=> array( $a['updatedTs'], $a['id'] );
			}
		);
		return array(
			'cards'  => $cards,
			'review' => array_slice( $review, 0, self::REVIEW_MAX ),
			'export' => self::export_state(),
		);
	}

	private static function export_state(): array {
		$pro     = class_exists( 'EMCP_Tools_Plugin_Export_Module' );
		$premium = function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code();
		$locked  = ! $pro || ! $premium;
		return array(
			'show'   => $locked || EMCP_Tools_Plugin_Export_Module::is_enabled(),
			'locked' => $locked,
			'url'    => self::view_url( 'export' ),
		);
	}

	/** Sidebar badge: review-queue items that want a human's attention. */
	public static function flagged_count(): int {
		$data  = new self();
		$count = 0;
		foreach ( array_keys( self::TYPES ) as $type ) {
			foreach ( $data->all_rows( $type ) as $r ) {
				if ( ! $r['active'] && in_array( $r['review']['level'], array( 'warning', 'critical', 'error' ), true ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/** Drop the cached sidebar counts after a Sandbox change. */
	public static function flush_nav(): void {
		delete_transient( 'emcp_tools_nav_counts' );
	}

	/**
	 * The code views of one item, fetched on demand.
	 *
	 * @param string $type Type.
	 * @param int    $id   Id.
	 * @return array|WP_Error
	 */
	public function detail( string $type, int $id ) {
		$ok = self::can( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind = self::kind( $type );
		$row  = self::row( $kind, $id, false );
		if ( null === $row ) {
			return new WP_Error( 'emcp_sandbox_not_found', __( 'That item no longer exists.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		$json  = static function ( $value ): string {
			return (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		};
		$tab   = static function ( string $tab_id, string $label, string $language, string $value ): array {
			return array(
				'id'       => $tab_id,
				'label'    => $label,
				'language' => $language,
				'value'    => $value,
			);
		};
		$tabs  = array();
		$extra = array();
		if ( 'widget' === $kind ) {
			$tabs[] = $tab( 'spec', __( 'Spec', 'emcp-tools' ), 'json', $json( EMCP_Tools_Widget_Store::get_spec( $id ) ?? array() ) );
			$tabs[] = $tab( 'php', $row['ident'] . '.php', 'php', EMCP_Tools_Widget_Store::get_php( $id ) );
			$css    = EMCP_Tools_Widget_Store::get_css( $id );
			$js     = EMCP_Tools_Widget_Store::get_js( $id );
			if ( '' !== $css ) {
				$tabs[] = $tab( 'css', 'style.css', 'css', $css );
			}
			if ( '' !== $js ) {
				$tabs[] = $tab( 'js', 'script.js', 'js', $js );
			}
		} elseif ( 'block' === $kind ) {
			$store  = EMCP_Tools_Block_Store::instance();
			$tabs[] = $tab( 'spec', __( 'Spec', 'emcp-tools' ), 'json', $json( $store->get_spec( $id ) ?? array() ) );
			foreach ( EMCP_Tools_Block_Store::ASSET_FILES as $file ) {
				$value = $store->get_asset( $id, $file );
				if ( '' !== $value ) {
					$tabs[] = $tab( sanitize_key( $file ), $file, (string) pathinfo( $file, PATHINFO_EXTENSION ), $value );
				}
			}
		} else {
			$rec    = EMCP_Tools_PHP_Snippet_Store::get( $id );
			$tabs[] = $tab( 'code', 'snippet-' . $id . '.php', 'php', (string) $rec['code'] );
			$extra  = array(
				'snippet'    => array(
					'title'    => (string) $rec['title'],
					'code'     => (string) $rec['code'],
					'context'  => (string) $rec['context'],
					'hook'     => (string) $rec['hook'],
					'priority' => (int) $rec['priority'],
				),
				'validation' => (array) $rec['validation'],
				'summary'    => EMCP_Tools_PHP_Snippet_Validator::summary( (array) $rec['validation'] ),
			);
		}
		return array_merge(
			array(
				'row'  => $row,
				'tabs' => $tabs,
			),
			$extra
		);
	}

	/**
	 * Switch an artifact on or off. Activation stays human-only: the route is
	 * cookie-only, and the stores re-validate (a critical snippet is refused).
	 *
	 * @param string $type   Type.
	 * @param int    $id     Id.
	 * @param bool   $active On or off.
	 * @param array  $query  The list query to answer with.
	 * @return array|WP_Error The list for $query, plus message.
	 */
	public function set_status( string $type, int $id, bool $active, array $query ) {
		$ok = self::can( $type, true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$status = $active ? 'active' : 'draft';
		$kind   = self::kind( $type );
		if ( 'widget' === $kind ) {
			$res = EMCP_Tools_Widget_Store::set_status( $id, $status );
		} elseif ( 'block' === $kind ) {
			$res = EMCP_Tools_Block_Store::instance()->set_status( $id, $status );
		} else {
			$res = EMCP_Tools_PHP_Snippet_Store::set_status( $id, $status );
		}
		if ( is_wp_error( $res ) ) {
			return self::as_rest_error( $res );
		}
		return $this->after_write( $type, $query, $active ? __( 'Switched on.', 'emcp-tools' ) : __( 'Switched off.', 'emcp-tools' ) );
	}

	/**
	 * Delete an artifact.
	 *
	 * @param string $type  Type.
	 * @param int    $id    Id.
	 * @param array  $query The list query to answer with.
	 * @return array|WP_Error
	 */
	public function delete( string $type, int $id, array $query ) {
		$ok = self::can( $type, true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind = self::kind( $type );
		if ( 'widget' === $kind ) {
			$res = EMCP_Tools_Widget_Store::delete( $id );
		} elseif ( 'block' === $kind ) {
			$res = EMCP_Tools_Block_Store::instance()->delete( $id );
		} else {
			$res = EMCP_Tools_PHP_Snippet_Store::delete( $id );
		}
		if ( is_wp_error( $res ) ) {
			return self::as_rest_error( $res );
		}
		return $this->after_write( $type, $query, __( 'Deleted.', 'emcp-tools' ) );
	}

	/**
	 * Create (id 0) or edit a snippet as a human. It is validated and stored as
	 * written; a new one starts inactive like an agent's draft.
	 *
	 * @param int   $id    Snippet id, 0 for a new one.
	 * @param array $args  { title?, code?, context?, hook?, priority? } straight from REST (not slashed).
	 * @param array $query The list query to answer with.
	 * @return array|WP_Error
	 */
	public function save_snippet( int $id, array $args, array $query ) {
		$ok = self::can( 'snippets', true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$clean = array();
		if ( isset( $args['title'] ) ) {
			$clean['title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( isset( $args['code'] ) ) {
			$clean['code'] = (string) $args['code']; // Raw PHP source: validated by the store, never run here.
		}
		if ( isset( $args['context'] ) ) {
			$clean['context'] = in_array( $args['context'], EMCP_Tools_PHP_Snippet_Store::CONTEXTS, true ) ? (string) $args['context'] : 'shortcode';
		}
		if ( isset( $args['hook'] ) ) {
			$clean['hook'] = sanitize_text_field( (string) $args['hook'] );
		}
		if ( isset( $args['priority'] ) ) {
			$clean['priority'] = absint( $args['priority'] );
		}
		$res = $id > 0 ? EMCP_Tools_PHP_Snippet_Store::update( $id, $clean ) : EMCP_Tools_PHP_Snippet_Store::create_draft( $clean );
		if ( is_wp_error( $res ) ) {
			return self::as_rest_error( $res );
		}
		$out           = $this->after_write( 'snippets', $query, $id > 0 ? __( 'Snippet updated.', 'emcp-tools' ) : __( 'Saved as an inactive draft.', 'emcp-tools' ) );
		$out['itemId'] = (int) $res['snippet_id'];
		return $out;
	}

	/**
	 * A portable bundle for download.
	 *
	 * @param string $type Type.
	 * @param int    $id   Id.
	 * @return array{filename:string,bundle:array}|WP_Error
	 */
	public function export_bundle( string $type, int $id ) {
		$ok = self::can( $type );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$kind = self::kind( $type );
		$art  = EMCP_Tools_Sandbox_Cloud_State::artifact( $kind );
		if ( null === $art ) {
			return new WP_Error( 'emcp_sandbox_pro_required', __( 'This part of the Sandbox needs EMCP Pro.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		$bundle = $art->to_bundle( $id );
		if ( is_wp_error( $bundle ) ) {
			return self::as_rest_error( $bundle );
		}
		return array(
			'filename' => sanitize_file_name( 'emcp-' . $kind . '-' . $id . '.json' ),
			'bundle'   => $bundle,
		);
	}

	/**
	 * Import a portable bundle. Always a new inactive draft (the artifacts' own
	 * apply_bundle() guarantees it).
	 *
	 * @param string $json Bundle JSON.
	 * @return array|WP_Error
	 */
	public function import_json( string $json ) {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || array() === $data ) {
			return new WP_Error( 'emcp_sandbox_bad_bundle', __( 'The bundle is not valid JSON.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$valid = EMCP_Tools_Sandbox_Bundle::validate( $data );
		if ( is_wp_error( $valid ) ) {
			return new WP_Error( 'emcp_sandbox_bad_bundle', $valid->get_error_message(), array( 'status' => 400 ) );
		}
		$kind = (string) $data['kind'];
		$type = self::type_of( $kind );
		$ok   = self::can( $type, true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$art = EMCP_Tools_Sandbox_Cloud_State::artifact( $kind );
		if ( null === $art ) {
			return new WP_Error( 'emcp_sandbox_pro_required', __( 'This part of the Sandbox needs EMCP Pro.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		$id = $art->apply_bundle( $data );
		if ( is_wp_error( $id ) ) {
			return self::as_rest_error( $id );
		}
		self::flush_nav();
		return array(
			'kind'      => $kind,
			'type'      => $type,
			'id'        => (int) $id,
			'message'   => __( 'Imported as a new inactive draft.', 'emcp-tools' ),
			'reviewUrl' => self::view_url( $type, (int) $id ),
		);
	}

	/**
	 * Server-side render of a block with its defaults (spec 8.13).
	 *
	 * @param int $id Block id.
	 * @return array|WP_Error
	 */
	public function block_preview( int $id ) {
		$ok = self::can( 'blocks' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$res = EMCP_Tools_Block_Store::instance()->preview( $id );
		return is_wp_error( $res ) ? self::as_rest_error( $res ) : $res;
	}

	/**
	 * The fresh list after a change, with a message.
	 *
	 * @param string $type    Type.
	 * @param array  $query   List query.
	 * @param string $message Toast text.
	 */
	private function after_write( string $type, array $query, string $message ): array {
		self::flush_nav();
		$this->cache = array();
		$list        = $this->list( $type, $query );
		return array_merge( is_wp_error( $list ) ? array() : $list, array( 'message' => $message ) );
	}

	/**
	 * Store errors carry no HTTP status: not_found is 404, forbidden 403, the rest
	 * 400. A validation report travels with the error, summarised here so every
	 * screen words it the same way.
	 *
	 * @param WP_Error $e Store error.
	 */
	public static function as_rest_error( WP_Error $e ): WP_Error {
		$code = $e->get_error_code();
		if ( 'not_found' === $code ) {
			$status = 404;
		} elseif ( 'forbidden' === $code ) {
			$status = 403;
		} else {
			$status = 400;
		}
		$data = array( 'status' => $status );
		$orig = $e->get_error_data();
		if ( is_array( $orig ) && isset( $orig['validation'] ) ) {
			$data['validation'] = $orig['validation'];
			$data['summary']    = EMCP_Tools_PHP_Snippet_Validator::summary( (array) $orig['validation'] );
		}
		return new WP_Error( $code, $e->get_error_message(), $data );
	}
}
