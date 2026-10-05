<?php
/**
 * Standalone harness for EMCP_Tools_Change_Log::redact() over both stores (the option and the
 * table half of the in-memory storage) with an in-memory blob table. One scenario per process:
 * argv[1] is "{option|table}:{case}".
 *
 * @package EMCP_Tools\Tests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['_wp_options'] = array();
class WP_Error {
	public function __construct( private string $code, private string $message = '' ) {}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function __( $s, $domain = '' ) { return $s; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function maybe_serialize( $v ) { return is_array( $v ) || is_object( $v ) ? serialize( $v ) : (string) $v; }
function maybe_unserialize( $v ) { return is_string( $v ) && ( 'b:0;' === $v || false !== @unserialize( $v ) ) ? unserialize( $v ) : $v; }
function get_current_user_id() { return 1; }
function get_option( $k, $default = false ) { return array_key_exists( $k, $GLOBALS['_wp_options'] ) ? $GLOBALS['_wp_options'][ $k ] : $default; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['_wp_options'][ $k ] = $v; return true; }
function add_option( $k, $v, $d = '', $autoload = null ) { $GLOBALS['_wp_options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['_wp_options'][ $k ] ); return true; }
function wp_cache_delete( ...$a ) { return true; }
function wp_rand( $a = 0, $b = 0 ) { return $a; }
function add_filter( ...$a ) { return true; }
function apply_filters( $hook, $value, ...$rest ) { return $value; }
function do_action( ...$args ) {}
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }

/** The blob table in memory. */
$GLOBALS['wpdb'] = new class() {
	public $prefix = 'wp_';
	public $blobs  = array();
	public function prepare( $q, ...$a ) { return array( $q, is_array( $a[0] ?? null ) ? $a[0] : $a ); }
	public function insert( $t, $d, $f = null ) { $this->blobs[ $d['blob_id'] ] = $d['data']; return 1; }
	public function delete( $t, $w, $f = null ) { unset( $this->blobs[ $w['blob_id'] ] ); return 1; }
	public $reads = array();
	public function get_var( $q ) { $this->reads[] = $q[1][0]; return $this->blobs[ $q[1][0] ] ?? null; }
	public function query( $q ) { return 1; }
	public function get_charset_collate() { return ''; }
};

require ABSPATH . 'includes/class-lease.php';
require ABSPATH . 'includes/class-change-log.php';
require ABSPATH . 'includes/class-change-blobs.php';
require ABSPATH . 'tests/support/class-change-memory-storage.php';
$storage = new EMCP_Tools_Change_Memory_Storage();
EMCP_Tools_Change_Log::use_storage( $storage, EMCP_Tools_Change_Memory_Storage::lease() );

list( $store, $case ) = explode( ':', $argv[1] );
if ( 'table' === $store ) {
	$storage->exists = true;
	$storage->set_flag( 'table' );
}

/** Every stored rollback, encoded, plus every blob. */
function everything(): string {
	$rows = EMCP_Tools_Change_Log::query( array( 'limit' => 100000 ) );
	return json_encode( $rows ) . json_encode( $GLOBALS['wpdb']->blobs );
}

function entry( string $domain, array $rb ): string {
	$id = EMCP_Tools_Change_Log::record( array( 'domain' => $domain, 'action' => 'test', 'target' => $domain . ':1', 'summary' => 'Changed #1', 'rollback' => $rb ) );
	check( '' !== $id, 'record failed' );
	return $id;
}

function blob_entry( string $domain, array $rb, array $heavy ): string {
	$bid           = EMCP_Tools_Change_Blobs::put( $heavy );
	$rb['blob_id'] = $bid;
	return entry( $domain, $rb );
}

$keep = static fn( array $rb, ?array $heavy ) => array( 'keep' );

switch ( $case ) {
	case 'walk':
		$n     = 'table' === $store ? 1203 : 40;
		$other = entry( 'other', array( 'type' => 'post-fields', 'secret' => 'other' ) );
		for ( $i = 0; $i < $n; $i++ ) {
			entry( 'crm', array( 'type' => 'crm-thing', 'i' => $i ) );
		}
		$seen = array();
		$res  = EMCP_Tools_Change_Log::redact(
			'crm',
			static function ( array $rb, ?array $heavy ) use ( &$seen ) {
				$seen[] = $rb['i'];
				return array( 'keep' );
			},
			'erased'
		);
		check( 0 === $res, 'keep changed ' . $res );
		check( range( 0, $n - 1 ) === $seen, 'walked ' . count( $seen ) . ' of ' . $n );
		check( 'other' === EMCP_Tools_Change_Log::get( $other )['rollback']['secret'], 'other domain touched' );
		break;

	case 'strip':
		$inline = entry( 'crm', array( 'type' => 'crm-thing', 'before' => array( 'email' => 'jane@example.com' ) ) );
		$heavy  = blob_entry( 'crm', array( 'type' => 'crm-thing' ), array( 'before' => array( 'email' => 'jane@example.com' ) ) );
		$bid    = EMCP_Tools_Change_Log::get( $heavy )['rollback']['blob_id'];
		$other  = entry( 'other', array( 'type' => 'post-fields', 'email' => 'kept@example.com' ) );
		$res    = EMCP_Tools_Change_Log::redact( 'crm', static fn( array $rb, ?array $h ) => array( 'strip' ), 'The contact was erased.' );
		check( 2 === $res, 'strip count ' . $res );
		check( ! isset( $GLOBALS['wpdb']->blobs[ $bid ] ), 'blob kept' );
		check( false === strpos( everything(), 'jane@' ), 'email left: ' . everything() );
		check( false !== strpos( everything(), 'kept@' ), 'other domain stripped' );
		foreach ( array( $inline, $heavy ) as $id ) {
			$e = EMCP_Tools_Change_Log::get( $id );
			check( 'Changed #1' === $e['summary'], 'summary lost' );
			$r = EMCP_Tools_Change_Log::rollback( $id, true );
			check( $r instanceof WP_Error && 'redacted' === $r->get_error_code(), 'rollback answered ' . ( $r instanceof WP_Error ? $r->get_error_code() : 'ok' ) );
			check( 'The contact was erased.' === $r->get_error_message(), 'reason lost' );
		}
		check( 0 === EMCP_Tools_Change_Log::redact( 'crm', static fn( array $rb, ?array $h ) => array( 'strip' ), 'again' ), 'not idempotent' );
		break;

	case 'rewrite':
		$id  = blob_entry( 'crm', array( 'type' => 'crm-thing', 'contacts' => array( 1, 2 ) ), array( 'before' => array( 1 => 'jane@example.com', 2 => 'bob@example.com' ) ) );
		$old = EMCP_Tools_Change_Log::get( $id )['rollback']['blob_id'];
		$in  = entry( 'crm', array( 'type' => 'crm-thing', 'contacts' => array( 1, 3 ), 'before' => array( 1 => 'jane@example.com', 3 => 'cy@example.com' ) ) );
		$res = EMCP_Tools_Change_Log::redact(
			'crm',
			static function ( array $rb, ?array $heavy ) {
				$rb['contacts'] = array_values( array_diff( $rb['contacts'], array( 1 ) ) );
				if ( null !== $heavy ) {
					unset( $heavy['before'][1] );
					return array( 'rewrite', $rb, $heavy );
				}
				unset( $rb['before'][1] );
				return array( 'rewrite', $rb, null );
			},
			'erased'
		);
		check( 2 === $res, 'rewrite count ' . $res );
		$rb = EMCP_Tools_Change_Log::get( $id )['rollback'];
		check( array( 2 ) === $rb['contacts'], 'contacts ' . json_encode( $rb['contacts'] ) );
		check( ! empty( $rb['blob_id'] ) && $old !== $rb['blob_id'], 'blob not replaced' );
		check( ! isset( $GLOBALS['wpdb']->blobs[ $old ] ), 'old blob kept' );
		check( array( 'before' => array( 2 => 'bob@example.com' ) ) === EMCP_Tools_Change_Blobs::get( $rb['blob_id'] ), 'new blob ' . json_encode( EMCP_Tools_Change_Blobs::get( $rb['blob_id'] ) ) );
		check( array( 3 => 'cy@example.com' ) === EMCP_Tools_Change_Log::get( $in )['rollback']['before'], 'inline rewrite' );
		check( false === strpos( everything(), 'jane@' ), 'email left' );
		break;

	case 'keep':
		$id     = blob_entry( 'crm', array( 'type' => 'crm-thing' ), array( 'before' => 'x' ) );
		$before = everything();
		check( 0 === EMCP_Tools_Change_Log::redact( 'crm', $keep, 'erased' ), 'keep counted' );
		check( $before === everything(), 'keep changed something' );
		check( 0 === EMCP_Tools_Change_Log::redact( 'nothing-here', static fn() => array( 'strip' ), 'erased' ), 'empty domain counted' );
		break;

	case 'race':
		$id  = entry( 'crm', array( 'type' => 'crm-thing', 'before' => 'x' ) );
		$res = EMCP_Tools_Change_Log::redact(
			'crm',
			static function ( array $rb, ?array $heavy ) use ( $id ) {
				// Another process rewrites the entry between the read and the write.
				EMCP_Tools_Change_Log::store()->replace_rollback( $id, $rb, array( 'type' => 'crm-thing', 'before' => 'y' ) );
				return array( 'strip' );
			},
			'erased'
		);
		check( 0 === $res, 'stale write counted ' . $res );
		check( 'y' === EMCP_Tools_Change_Log::get( $id )['rollback']['before'], 'stale write overwrote the newer rollback' );
		break;

	case 'match':
		$one   = blob_entry( 'crm', array( 'type' => 'crm-thing', 'contacts' => array( 1 ) ), array( 'before' => 'one' ) );
		$two   = blob_entry( 'crm', array( 'type' => 'crm-thing', 'contacts' => array( 2 ) ), array( 'before' => 'two' ) );
		$GLOBALS['wpdb']->reads = array();
		$seen  = array();
		$res   = EMCP_Tools_Change_Log::redact(
			'crm',
			static function ( array $rb, ?array $heavy ) use ( &$seen ) {
				$seen[] = $heavy['before'] ?? null;
				return array( 'strip' );
			},
			'erased',
			static fn( array $rb ) => in_array( 1, (array) ( $rb['contacts'] ?? array() ), true )
		);
		check( 1 === $res && array( 'one' ) === $seen, 'match: only the matching entry is rewritten', json_encode( $seen ) );
		check( 1 === count( $GLOBALS['wpdb']->reads ), 'match: only one blob is read, the matching one', json_encode( $GLOBALS['wpdb']->reads ) );
		check( 'crm-thing' === EMCP_Tools_Change_Log::get( $two )['rollback']['type'], 'match: the other entry is untouched' );
		break;

	default:
		check( false, 'unknown case ' . $case );
}
echo "PASS\n";
