<?php
/**
 * Standalone harness for #182: a persistent object cache that keeps serving an
 * empty meta array for a new snippet post, no matter how often the store asks
 * for it to be dropped (W3 Total Cache on Valkey). The database holds the right
 * values; the snippet store must read them from there, not from the cache.
 *
 * Runs in its own process because it defines its own WordPress stubs and a
 * small `wpdb` double that answers the store's meta query from the rows.
 *
 * @package EMCP_Tools\Tests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/emcp-snippet-stale-' . getmypid() );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['_posts']   = array();  // post_id => post object.
$GLOBALS['_rows']    = array();  // The database: list of [meta_id, post_id, meta_key, meta_value].
$GLOBALS['_cache']   = array();  // The stale object cache: post_id => key => values.
$GLOBALS['_next_id'] = 900;
$GLOBALS['_meta_id'] = 0;
$GLOBALS['_queries'] = 0;
$GLOBALS['_cleaned'] = array();

class WP_Error {
	public function __construct( private string $code = '', private string $message = '', private $data = null ) {}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Post {
	public $ID; public $post_type; public $post_status; public $post_title; public $post_modified = '2026-11-13 10:00:00';
}
class WP_Query {
	public $posts = array();
	public function __construct( array $args ) {
		foreach ( $GLOBALS['_posts'] as $post ) {
			if ( $post->post_type !== $args['post_type'] ) {
				continue;
			}
			if ( 'any' !== $args['post_status'] && $post->post_status !== $args['post_status'] ) {
				continue;
			}
			$this->posts[] = ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) ? $post->ID : $post;
		}
	}
}

/** Answers the store's meta query from $GLOBALS['_rows']. */
class wpdb {
	public $postmeta = 'wp_postmeta';
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		return 'FAKE:' . json_encode( array( 'q' => $query, 'a' => $args ) );
	}
	public function get_results( $prepared, $output = OBJECT ) {
		$GLOBALS['_queries']++;
		$req = json_decode( substr( $prepared, 5 ), true );
		check( false !== strpos( $req['q'], 'wp_postmeta' ), 'query does not name the postmeta table' );
		check( false !== stripos( $req['q'], 'ORDER BY meta_id' ), 'query is not ordered by meta_id' );
		$ids  = array_values( array_filter( $req['a'], 'is_int' ) );
		$keys = array_values( array_filter( $req['a'], 'is_string' ) );
		$out  = array();
		foreach ( $GLOBALS['_rows'] as $row ) {
			if ( in_array( $row[1], $ids, true ) && in_array( $row[2], $keys, true ) ) {
				$r   = array( 'post_id' => (string) $row[1], 'meta_key' => $row[2], 'meta_value' => $row[3] );
				$out[] = ARRAY_A === $output ? $r : (object) $r;
			}
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new wpdb();

function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
function __( $s, $domain = '' ) { return $s; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : addslashes( (string) $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function maybe_unserialize( $v ) { return is_string( $v ) && ( 'b:0;' === $v || false !== @unserialize( $v ) ) ? unserialize( $v ) : $v; }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function current_user_can( $cap ) { return true; }
function get_current_user_id() { return 1; }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function apply_filters( $hook, $value, ...$rest ) { return $value; }
function add_filter( ...$a ) { return true; }
function wp_list_pluck( $list, $field ) { return array_map( static fn( $o ) => is_object( $o ) ? $o->$field : $o[ $field ], $list ); }

function wp_insert_post( array $data, $wp_error = false ) {
	$id                  = $GLOBALS['_next_id']++;
	$post                = new WP_Post();
	$post->ID            = $id;
	$post->post_type     = $data['post_type'];
	$post->post_status   = $data['post_status'];
	$post->post_title    = $data['post_title'];
	$GLOBALS['_posts'][ $id ] = $post;
	// The reporter's cache: primed empty right after the insert, and it stays.
	$GLOBALS['_cache'][ $id ] = array();
	return $id;
}
function wp_update_post( array $data ) {
	$post = $GLOBALS['_posts'][ $data['ID'] ] ?? null;
	if ( $post ) {
		foreach ( $data as $k => $v ) {
			if ( 'ID' !== $k ) {
				$post->$k = $v;
			}
		}
	}
	return $data['ID'];
}
function get_post( $id ) { return $GLOBALS['_posts'][ (int) $id ] ?? null; }
function get_post_status( $id ) { return isset( $GLOBALS['_posts'][ $id ] ) ? $GLOBALS['_posts'][ $id ]->post_status : false; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['_posts'][ $id ] ); return true; }
function clean_post_cache( $id ) { $GLOBALS['_cleaned'][] = is_object( $id ) ? (int) $id->ID : (int) $id; }

// Writes reach the database. The cache is never refreshed by them.
function update_post_meta( $post_id, $key, $value ) {
	$value = wp_unslash( $value );
	foreach ( $GLOBALS['_rows'] as $i => $row ) {
		if ( $row[1] === $post_id && $row[2] === $key ) {
			$GLOBALS['_rows'][ $i ][3] = (string) $value;
			return true;
		}
	}
	$GLOBALS['_rows'][] = array( ++$GLOBALS['_meta_id'], $post_id, $key, (string) $value );
	return true;
}
function delete_post_meta( $post_id, $key ) {
	foreach ( $GLOBALS['_rows'] as $i => $row ) {
		if ( $row[1] === $post_id && $row[2] === $key ) {
			unset( $GLOBALS['_rows'][ $i ] );
		}
	}
	return true;
}
// Reads come from the cache when it holds the post, as WordPress does.
function get_post_meta( $post_id, $key = '', $single = false ) {
	if ( array_key_exists( $post_id, $GLOBALS['_cache'] ) ) {
		$values = $GLOBALS['_cache'][ $post_id ][ $key ] ?? array();
	} else {
		$values = array();
		foreach ( $GLOBALS['_rows'] as $row ) {
			if ( $row[1] === $post_id && $row[2] === $key ) {
				$values[] = $row[3];
			}
		}
	}
	if ( $single ) {
		return $values ? maybe_unserialize( $values[0] ) : '';
	}
	return array_map( 'maybe_unserialize', $values );
}
// Deletes never reach the persistent cache.
function wp_cache_delete( ...$a ) { return true; }
function update_meta_cache( ...$a ) { return array(); }

require ABSPATH . 'includes/sandbox/class-sandbox-paths.php';
require ABSPATH . 'includes/class-php-snippet-validator.php';
require ABSPATH . 'includes/class-php-snippet-store.php';

// An older snippet, cached correctly long ago.
$old = wp_insert_post( array( 'post_type' => 'emcp_php_snippet', 'post_status' => 'draft', 'post_title' => 'Old' ) );
update_post_meta( $old, '_emcp_snippet_code', 'return;' );
update_post_meta( $old, '_emcp_snippet_context', 'hook' );
update_post_meta( $old, '_emcp_snippet_hook', 'wp_footer' );
update_post_meta( $old, '_emcp_snippet_priority', 5 );
$GLOBALS['_cache'][ $old ] = array(
	'_emcp_snippet_code'     => array( 'return;' ),
	'_emcp_snippet_context'  => array( 'hook' ),
	'_emcp_snippet_hook'     => array( 'wp_footer' ),
	'_emcp_snippet_priority' => array( '5' ),
);

// 1. Create answers the stored values.
$created = EMCP_Tools_PHP_Snippet_Store::create_draft(
	array( 'title' => 'New', 'code' => "return 'a\\\\b';", 'context' => 'hook', 'hook' => 'init', 'priority' => 10 )
);
check( is_array( $created ), 'create failed: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
$id = $created['snippet_id'];
check( 'hook' === $created['context'], 'create context: ' . $created['context'] );
check( 'init' === $created['hook'], 'create hook: ' . $created['hook'] );
check( 10 === $created['priority'], 'create priority: ' . var_export( $created['priority'], true ) );
check( "return 'a\\\\b';" === $created['code'], 'create code: ' . $created['code'] );
check( true === $created['validation']['valid'] && isset( $created['validation']['findings'] ), 'create validation: ' . json_encode( $created['validation'] ) );
check( in_array( $id, $GLOBALS['_cleaned'], true ), 'clean_post_cache was not called after the write' );

// 2. An update with only the priority keeps the stored code.
$updated = EMCP_Tools_PHP_Snippet_Store::update( $id, array( 'priority' => 20 ) );
check( is_array( $updated ), 'priority-only update failed: ' . ( is_wp_error( $updated ) ? $updated->get_error_message() : '' ) );
check( 20 === $updated['priority'], 'update priority: ' . var_export( $updated['priority'], true ) );
check( "return 'a\\\\b';" === $updated['code'], 'update lost the code: ' . $updated['code'] );
check( 'init' === $updated['hook'] && 'hook' === $updated['context'], 'update context/hook' );

// 3. A later list (separate call) shows both snippets with their stored values.
$listed = array();
foreach ( EMCP_Tools_PHP_Snippet_Store::list_snippets() as $row ) {
	$listed[ $row['snippet_id'] ] = $row;
}
check( isset( $listed[ $id ], $listed[ $old ] ), 'list is missing a snippet' );
check( 'hook' === $listed[ $id ]['context'] && 'init' === $listed[ $id ]['hook'] && 20 === $listed[ $id ]['priority'], 'list new: ' . json_encode( $listed[ $id ] ) );
check( 'wp_footer' === $listed[ $old ]['hook'] && 5 === $listed[ $old ]['priority'], 'list old: ' . json_encode( $listed[ $old ] ) );

$before = $GLOBALS['_queries'];
EMCP_Tools_PHP_Snippet_Store::list_snippets();
check( 1 === $GLOBALS['_queries'] - $before, 'list should read all snippet meta in one query, ran ' . ( $GLOBALS['_queries'] - $before ) );

// 4. get() on its own.
$got = EMCP_Tools_PHP_Snippet_Store::get( $id );
check( 20 === $got['priority'] && "return 'a\\\\b';" === $got['code'], 'get: ' . json_encode( $got ) );

// 5. A snippet whose code really is empty in the database still says so.
$empty = wp_insert_post( array( 'post_type' => 'emcp_php_snippet', 'post_status' => 'draft', 'post_title' => 'Empty' ) );
$res   = EMCP_Tools_PHP_Snippet_Store::update( $empty, array( 'priority' => 3 ) );
check( is_wp_error( $res ) && 'snippet_empty' === $res->get_error_code(), 'empty stored code: ' . ( is_wp_error( $res ) ? $res->get_error_code() : 'no error' ) );

echo "PASS\n";
