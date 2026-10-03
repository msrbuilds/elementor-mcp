<?php
/**
 * Standalone harness for transactional undo in the change log (rollback refs carrying
 * transactional => true, transaction(), in_transaction(), after_commit()). One scenario per process.
 *
 * @package EMCP_Tools\Tests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$options = array();
class WP_Error {
	public function __construct( private string $code = '', private string $message = '', private $data = null ) {}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
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
require ABSPATH . 'tests/support/class-change-memory-transaction.php';

/** A target the handler writes, rolled back with the transaction. */
final class Target {
	public $value = 'after';
	public function snapshot() { return $this->value; }
	public function restore( $v ) { $this->value = $v; }
}

$storage = new EMCP_Tools_Change_Memory_Storage();
$storage->exists = true;
$storage->set_flag( 'table' );
EMCP_Tools_Change_Log::use_storage( $storage, EMCP_Tools_Change_Memory_Storage::lease() );
$tx     = new EMCP_Tools_Change_Memory_Transaction( $storage );
$target = new Target();
$tx->participants[] = $target;
EMCP_Tools_Change_Log::use_transaction( $tx );

$GLOBALS['handler'] = static function () use ( $target ) {
	$target->value = 'before';
	return true;
};
$GLOBALS['seen_in_tx'] = null;
add_filter(
	'emcp_tools_change_apply_rollback',
	static function ( $result, $rb ) {
		if ( 'test-tx' !== ( $rb['type'] ?? '' ) ) {
			return $result;
		}
		$GLOBALS['seen_in_tx'] = EMCP_Tools_Change_Log::in_transaction();
		return ( $GLOBALS['handler'] )();
	},
	10,
	2
);

$record = static function ( bool $transactional = true ): string {
	$rb = array( 'type' => 'test-tx' );
	if ( $transactional ) {
		$rb['transactional'] = true;
	}
	return EMCP_Tools_Change_Log::record( array( 'domain' => 'test', 'action' => 'write', 'target' => 't', 'summary' => 'A write', 'rollback' => $rb ) );
};
$unchanged = static function ( string $id ) use ( $storage, $target ) {
	$entry = EMCP_Tools_Change_Log::get( $id );
	check( is_array( $entry ) && empty( $entry['rolled_back'] ), 'entry was marked rolled back' );
	check( 1 === count( $storage->rows ), 'an audit row was kept: ' . count( $storage->rows ) );
	check( 'after' === $target->value, 'target was not put back: ' . $target->value );
};

switch ( $argv[1] ) {
	case 'commit':
		$id  = $record();
		$out = EMCP_Tools_Change_Log::rollback( $id );
		check( is_array( $out ) && $out['rolled_back'] === $id && '' !== $out['compensating'], 'no success: ' . json_encode( $out ) );
		check( array( 'begin', 'commit' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		check( true === $GLOBALS['seen_in_tx'], 'handler did not run inside the transaction' );
		check( false === EMCP_Tools_Change_Log::in_transaction(), 'still in a transaction afterwards' );
		check( ! empty( EMCP_Tools_Change_Log::get( $id )['rolled_back'] ), 'entry not marked' );
		check( 2 === count( $storage->rows ), 'no audit row' );
		check( 'before' === $target->value, 'target not restored' );
		break;

	case 'handler-error':
		$id                 = $record();
		$GLOBALS['handler'] = static function () use ( $target ) {
			$target->value = 'half';
			return new WP_Error( 'conflict', 'changed' );
		};
		$out = EMCP_Tools_Change_Log::rollback( $id );
		check( is_wp_error( $out ) && 'conflict' === $out->get_error_code(), 'not the handler error' );
		check( array( 'begin', 'rollback' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		$unchanged( $id );
		break;

	case 'handler-throws':
		$id                 = $record();
		$GLOBALS['handler'] = static function () use ( $target ) {
			$target->value = 'half';
			throw new RuntimeException( 'boom' );
		};
		$out = EMCP_Tools_Change_Log::rollback( $id );
		check( is_wp_error( $out ) && 'rollback_failed' === $out->get_error_code(), 'not rollback_failed' );
		check( array( 'begin', 'rollback' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		$unchanged( $id );
		break;

	case 'mark-fails':
		$id                     = $record();
		$storage->refuse_update = true;
		$out                    = EMCP_Tools_Change_Log::rollback( $id );
		$storage->refuse_update = false;
		check( is_wp_error( $out ) && 'rollback_state_failed' === $out->get_error_code(), 'not rollback_state_failed: ' . json_encode( $out ) );
		check( array( 'begin', 'rollback' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		$unchanged( $id );
		$retry = EMCP_Tools_Change_Log::rollback( $id );
		check( is_array( $retry ), 'retry failed' );
		check( 'before' === $target->value, 'retry did not restore' );
		break;

	case 'audit-fails':
		$id                     = $record();
		$storage->refuse_insert = true;
		$out                    = EMCP_Tools_Change_Log::rollback( $id );
		$storage->refuse_insert = false;
		check( is_wp_error( $out ) && 'rollback_audit_failed' === $out->get_error_code(), 'not rollback_audit_failed' );
		check( array( 'begin', 'rollback' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		$unchanged( $id );
		check( null === get_option( EMCP_Tools_Change_Names::unrecorded(), null ), 'a refused record inside the transaction was counted as not recorded' );
		break;

	case 'commit-fails':
		$id              = $record();
		$tx->fail_commit = true;
		$out             = EMCP_Tools_Change_Log::rollback( $id );
		check( is_wp_error( $out ) && 'commit_failed' === $out->get_error_code(), 'not commit_failed' );
		check( array( 'begin', 'commit', 'rollback' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		$unchanged( $id );
		break;

	case 'after-commit':
		$id   = $record();
		$runs = array();
		$GLOBALS['handler'] = static function () use ( $target, &$runs ) {
			$target->value = 'before';
			EMCP_Tools_Change_Log::after_commit( static function () use ( &$runs ) {
				$runs[] = 'first';
			} );
			EMCP_Tools_Change_Log::after_commit( static function () {
				throw new RuntimeException( 'hook broke' );
			} );
			return true;
		};
		$out = EMCP_Tools_Change_Log::rollback( $id );
		check( is_array( $out ) && $out['rolled_back'] === $id, 'not a success' );
		check( array( 'first' ) === $runs, 'callbacks: ' . json_encode( $runs ) );
		check( isset( $out['hook_error']['message'] ) && 'hook broke' === $out['hook_error']['message'] && 'RuntimeException' === $out['hook_error']['class'], 'hook_error: ' . json_encode( $out ) );
		check( ! empty( EMCP_Tools_Change_Log::get( $id )['rolled_back'] ), 'the throwing hook undid the commit' );
		break;

	case 'after-commit-discarded':
		$id   = $record();
		$runs = 0;
		$GLOBALS['handler'] = static function () use ( &$runs ) {
			EMCP_Tools_Change_Log::after_commit( static function () use ( &$runs ) {
				++$runs;
			} );
			return new WP_Error( 'conflict', 'changed' );
		};
		EMCP_Tools_Change_Log::rollback( $id );
		check( 0 === $runs, 'a callback ran after a rollback' );
		$GLOBALS['handler'] = static function () {
			return true;
		};
		EMCP_Tools_Change_Log::rollback( $id );
		check( 0 === $runs, 'a discarded callback ran on the next commit' );
		break;

	case 'not-transactional-ref':
		$id  = $record( false );
		$out = EMCP_Tools_Change_Log::rollback( $id );
		check( is_array( $out ) && '' !== $out['compensating'], 'not a success' );
		check( array() === $tx->statements, 'a plain ref started a transaction: ' . json_encode( $tx->statements ) );
		check( false === $GLOBALS['seen_in_tx'], 'a plain ref ran inside a transaction' );
		break;

	case 'not-supported':
		$id            = $record();
		$tx->supported = false;
		$out           = EMCP_Tools_Change_Log::rollback( $id );
		check( is_wp_error( $out ) && 'not_transactional' === $out->get_error_code(), 'not not_transactional' );
		check( array() === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		check( null === $GLOBALS['seen_in_tx'], 'the handler ran' );
		$unchanged( $id );
		break;

	case 'begin-fails':
		$id             = $record();
		$tx->fail_begin = true;
		$out            = EMCP_Tools_Change_Log::rollback( $id );
		check( is_wp_error( $out ) && 'not_transactional' === $out->get_error_code(), 'not not_transactional: ' . json_encode( $out ) );
		check( null === $GLOBALS['seen_in_tx'], 'the handler ran' );
		$unchanged( $id );
		break;

	case 'helper':
		$value = EMCP_Tools_Change_Log::transaction( static function () use ( $target ) {
			$target->value = 'written';
			check( EMCP_Tools_Change_Log::in_transaction(), 'not in a transaction' );
			$nested = EMCP_Tools_Change_Log::transaction( static function () {
				return 1;
			} );
			check( is_wp_error( $nested ) && 'transaction_active' === $nested->get_error_code(), 'nested allowed' );
			return array( 'ok' => true );
		} );
		check( array( 'ok' => true ) === $value, 'value: ' . json_encode( $value ) );
		check( 'written' === $target->value, 'not committed' );
		$tx->statements = array();
		$dry = EMCP_Tools_Change_Log::transaction( static function () use ( $target ) {
			$target->value = 'dry';
			return array( 'dry_run' => true );
		}, true );
		check( array( 'dry_run' => true ) === $dry, 'dry value' );
		check( array( 'begin', 'rollback' ) === $tx->statements, 'dry statements: ' . json_encode( $tx->statements ) );
		check( 'written' === $target->value, 'dry run kept its write' );
		break;

	case 'record-inside':
		$id = '';
		$tx->statements = array();
		$out = EMCP_Tools_Change_Log::transaction( static function () use ( $record, &$id ) {
			$id = $record();
			return '' === $id ? new WP_Error( 'history_unavailable', 'no' ) : array( 'id' => $id );
		} );
		check( is_array( $out ) && $id === $out['id'], 'record inside a transaction failed' );
		check( array( 'begin', 'commit' ) === $tx->statements, 'statements: ' . json_encode( $tx->statements ) );
		break;

	default:
		check( false, 'unknown scenario' );
}
echo "PASS\n";
