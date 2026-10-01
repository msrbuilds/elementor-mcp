<?php
/**
 * Standalone harness for the History seams Pro rollback types use (irreversible entries,
 * the current-hash and apply-rollback filters, FunnelKit kinds). Runs one scenario per process.
 *
 * @package EMCP_Tools\Tests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$options = array();
class WP_Error {
	public function __construct( private string $code, private string $message = '' ) {}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function __( $s, $domain = '' ) { return $s; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function maybe_serialize( $v ) { return is_array( $v ) || is_object( $v ) ? serialize( $v ) : (string) $v; }
function get_current_user_id() { return 1; }
function get_option( $k, $default = false ) { return $GLOBALS['options'][ $k ] ?? $default; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['options'][ $k ] ); return true; }
$GLOBALS['hooks'] = array();
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $cb, $args ); return true; }
function apply_filters( $hook, $value, ...$rest ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $h ) {
		$value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $rest ), 0, $h[1] ) );
	}
	return $value;
}
function do_action( ...$args ) {}
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
require ABSPATH . 'includes/class-lease.php';
require ABSPATH . 'includes/class-change-log.php';
require ABSPATH . 'tests/support/class-change-memory-storage.php';
EMCP_Tools_Change_Log::use_storage( new EMCP_Tools_Change_Memory_Storage(), EMCP_Tools_Change_Memory_Storage::lease() );

$hash  = new ReflectionMethod( EMCP_Tools_Change_Log::class, 'current_hash' );
$apply = new ReflectionMethod( EMCP_Tools_Change_Log::class, 'apply_rollback' );
$hash->setAccessible( true );
$apply->setAccessible( true );

switch ( $argv[1] ) {
	case 'irreversible-reason':
		$blocker = EMCP_Tools_Change_Log::rollback_blocker(
			array(
				'rolled_back' => false,
				'rollback'    => array( 'type' => 'irreversible', 'reason' => 'Sending a broadcast cannot be undone.' ),
			)
		);
		check( $blocker instanceof WP_Error, 'irreversible entry is not blocked' );
		check( 'irreversible' === $blocker->get_error_code(), 'code is ' . $blocker->get_error_code() );
		check( 'Sending a broadcast cannot be undone.' === $blocker->get_error_message(), 'reason lost: ' . $blocker->get_error_message() );
		break;

	case 'unknown-type-filters':
		$seen = array();
		add_filter( 'emcp_tools_change_current_hash', static function ( $h, $rb ) use ( &$seen ) {
			$seen[] = 'hash:' . $rb['type'];
			return 'abc';
		}, 10, 2 );
		add_filter( 'emcp_tools_change_apply_rollback', static function ( $result, $rb, $force ) use ( &$seen ) {
			$seen[] = 'apply:' . $rb['type'] . ':' . ( $force ? 'forced' : 'checked' );
			return true;
		}, 10, 3 );
		check( 'abc' === $hash->invoke( null, array( 'type' => 'funnelkit-snapshot' ) ), 'hash filter not used' );
		check( true === $apply->invoke( null, array( 'type' => 'funnelkit-snapshot' ), true ), 'apply filter not used' );
		check( array( 'hash:funnelkit-snapshot', 'apply:funnelkit-snapshot:forced' ) === $seen, 'seen: ' . json_encode( $seen ) );
		break;

	case 'unknown-type-unhandled':
		$result = $apply->invoke( null, array( 'type' => 'nobody-handles-this' ) );
		check( $result instanceof WP_Error && 'unknown_rollback' === $result->get_error_code(), 'unhandled type did not fail' );
		break;

	case 'funnelkit-kinds':
		check( 'content' === EMCP_Tools_Change_Log::kind_of( 'funnelkit' ), 'funnelkit kind' );
		check( 'content' === EMCP_Tools_Change_Log::kind_of( 'funnelkit-automations' ), 'funnelkit-automations kind' );
		break;

	default:
		check( false, 'unknown scenario' );
}
echo "PASS\n";
