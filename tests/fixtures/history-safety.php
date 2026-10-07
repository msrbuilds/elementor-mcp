<?php
/** Standalone harness: no live WordPress, database, uploads, or Pro dependency. */
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
$options = $posts = $meta = $terms = $fail = array();
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
function current_user_can( $cap, ...$args ) { return true; }
function get_post_type_object( $type ) {
	return (object) array( 'cap' => (object) array( 'create_posts' => 'edit_pages', 'edit_posts' => 'edit_pages', 'publish_posts' => 'publish_pages', 'edit_others_posts' => 'edit_others_pages' ) );
}
function get_option( $k, $default = false ) { return $GLOBALS['options'][ $k ] ?? $default; }
function update_option( $k, $v, $autoload = null ) {
	if ( ! empty( $GLOBALS['fail']['option'] ) ) { return false; }
	if ( 'emcp_tools_changelog' === $k && ! empty( $GLOBALS['fail']['journal'] ) ) { return false; }
	$GLOBALS['options'][ $k ] = $v; return true;
}
function delete_option( $k ) { unset( $GLOBALS['options'][ $k ] ); return true; }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_the_title( $id ) { return get_post( $id )->post_title ?? ''; }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function wp_update_post( $p, $error = false ) {
	if ( ! empty( $GLOBALS['fail']['post'] ) ) { return $error ? new WP_Error( 'db_update_error' ) : 0; }
	$id = $p['ID']; $GLOBALS['posts'][ $id ] = (object) array_merge( (array) get_post( $id ), wp_unslash( $p ) ); return $id;
}
function wp_delete_post( $id, $force = false ) {
	if ( ! empty( $GLOBALS['fail']['delete'] ) ) { return false; }
	if ( ! empty( $GLOBALS['creation_delete_file'] ) && empty( $GLOBALS['fail']['files'] ) ) { unlink( $GLOBALS['creation_delete_file'] ); }
	$p = get_post( $id ); unset( $GLOBALS['posts'][ $id ] ); return $p;
}
function get_object_taxonomies( $type ) { return array( 'category' ); }
function get_attached_file( $id ) { return $GLOBALS['creation_delete_file'] ?? ''; }
function wp_get_attachment_metadata( $id ) { return array(); }
function wp_untrash_post( $id ) { return false; }
function get_userdata( $id ) { return $GLOBALS['users'][ $id ] ?? false; }
function wp_update_user( $input ) {
	if ( ! empty( $GLOBALS['fail']['user'] ) ) { return 0; }
	$id = $input['ID']; $GLOBALS['users'][ $id ] = (object) array_merge( (array) get_userdata( $id ), $input ); return $id;
}
function get_field( $key, $target = 0, $format = true ) { return $GLOBALS['acf'][ $target ][ $key ] ?? false; }
function update_field( $key, $value, $target = 0 ) {
	if ( ! empty( $GLOBALS['fail']['acf'] ) ) { return false; }
	$GLOBALS['acf'][ $target ][ $key ] = $value; return true;
}
function get_post_meta( $id, $key = '', $single = false ) {
	if ( '' === $key ) { return $GLOBALS['meta'][ $id ] ?? array(); }
	$rows = $GLOBALS['meta'][ $id ][ $key ] ?? array(); return $single ? ( $rows[0] ?? '' ) : $rows;
}
function metadata_exists( $type, $id, $key ) { return array_key_exists( $key, $GLOBALS['meta'][ $id ] ?? array() ); }
function update_post_meta( $id, $key, $value ) {
	if ( ! empty( $GLOBALS['fail']['meta'] ) ) { return false; }
	$GLOBALS['meta'][ $id ][ $key ] = array( wp_unslash( $value ) ); return true;
}
function delete_post_meta( $id, $key ) {
	if ( ! empty( $GLOBALS['fail']['meta'] ) ) { return false; }
	unset( $GLOBALS['meta'][ $id ][ $key ] ); return true;
}
function add_post_meta( $id, $key, $value ) {
	if ( ! empty( $GLOBALS['fail']['meta'] ) ) { return false; }
	$GLOBALS['meta'][ $id ][ $key ][] = wp_unslash( $value ); return 1;
}
function wp_get_object_terms( $id, $tax, $args = array() ) { return $GLOBALS['terms'][ $id ][ $tax ] ?? array(); }
function wp_set_object_terms( $id, $ids, $tax, $append = false ) { $GLOBALS['terms'][ $id ][ $tax ] = $ids; return $ids; }
function check( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
require ABSPATH . 'includes/class-lease.php';
require ABSPATH . 'includes/class-change-log.php';
require ABSPATH . 'tests/support/class-change-memory-storage.php';
EMCP_Tools_Change_Log::use_storage( new EMCP_Tools_Change_Memory_Storage(), EMCP_Tools_Change_Memory_Storage::lease() );
require ABSPATH . 'includes/class-change-recorder.php';
require ABSPATH . 'includes/class-elementor-data.php';
require ABSPATH . 'includes/abilities/class-transaction-abilities.php';
$wpdb = new class {
	public $calls = array();
	public function delete( $t, $w ) { $this->calls[] = 'delete'; return false; }
	public function update( $t, $d, $w ) { $this->calls[] = 'update'; return false; }
	public function insert( $t, $d ) { $this->calls[] = 'insert'; return false; }
};
$posts[42] = (object) array( 'ID' => 42, 'post_title' => 'Title', 'post_content' => 'Content', 'post_status' => 'draft', 'post_author' => 1 );
$case = $argv[1];
if ( str_starts_with( $case, 'page-create-' ) ) {
	function sanitize_text_field( $s ) { return $s; }
	function sanitize_key( $s ) { return $s; }
	function admin_url( $s ) { return $s; }
	function get_permalink( $id ) { return 'https://example.test/?p=' . $id; }
	function wp_insert_post( $input, $error = false ) {
		$GLOBALS['posts'][43] = (object) array_merge( $input, array( 'ID' => 43 ) );
		unset( $GLOBALS['posts'][43]->meta_input );
		foreach ( $input['meta_input'] as $key => $value ) { update_post_meta( 43, $key, $value ); }
		return 43;
	}
	class EMCP_Tools_Element_Factory {}
	require ABSPATH . 'includes/class-post-authorization.php';
	require ABSPATH . 'includes/abilities/class-page-abilities.php';
	$data = new class extends EMCP_Tools_Data {
		public function save_page_data( int $post_id, array $data ) {
			EMCP_Tools_Change_Recorder::record_elementor( $post_id, array(), 'Initialization' );
			if ( 'page-create-init-failure' === $GLOBALS['case'] ) { throw new RuntimeException( 'Initialization error' ); }
			update_post_meta( $post_id, '_elementor_data', '[]' );
			return true;
		}
	};
	if ( 'page-create-journal-failure' === $case ) { $fail['journal'] = true; }
	if ( 'page-create-outer-suppression' === $case ) { EMCP_Tools_Change_Log::$suppress = true; }
	$result = ( new EMCP_Tools_Page_Abilities( $data, new EMCP_Tools_Element_Factory() ) )->execute_create_page( array( 'title' => 'Created page' ) );
	$log = EMCP_Tools_Change_Log::all();
	if ( 'page-create-journal-failure' === $case ) {
		check( is_wp_error( $result ) && 'history_record_failed' === $result->get_error_code(), 'Created page with failed history reported success' );
	} elseif ( 'page-create-outer-suppression' === $case ) {
		check( EMCP_Tools_Change_Log::$suppress && array() === $log, 'Creation lost outer suppression' );
	} else {
		check( 1 === count( $log ) && 'create-page' === $log[0]['action'] && 'post-create' === $log[0]['rollback']['type'], 'Page initialization did not produce exactly one creation entry' );
		check( ! EMCP_Tools_Change_Log::$suppress, 'Page initialization leaked suppression' );
		if ( 'page-create-init-failure' === $case ) { check( is_wp_error( $result ), 'Initialization failure was swallowed' ); }
		else { check( $result['change_id'] === $log[0]['id'], 'Creation response omitted history ID' ); }
		check( ! is_wp_error( EMCP_Tools_Change_Log::rollback( $log[0]['id'] ) ) && ! get_post( 43 ), 'Page creation undo left the page' );
	}
	echo "PASS\n"; exit;
}
if ( str_starts_with( $case, 'created-' ) ) {
	$upload = str_starts_with( $case, 'created-upload' );
	$posts[42]->post_type = $upload ? 'attachment' : 'page';
	if ( in_array( $case, array( 'created-draft-clock-undo', 'created-date-conflict' ), true ) ) {
		$posts[42]->post_date = '2026-09-15 10:00:00';
		$posts[42]->post_date_gmt = 'created-date-conflict' === $case ? '2026-09-15 05:00:00' : '0000-00-00 00:00:00';
	}
	if ( $upload ) {
		$creation_delete_file = tempnam( sys_get_temp_dir(), 'emcp-creation-' );
		file_put_contents( $creation_delete_file, 'original bytes' );
	}
	try {
		if ( 'created-record-failure' === $case ) { $fail['journal'] = true; }
		if ( 'created-suppression' === $case ) { EMCP_Tools_Change_Log::$suppress = true; }
		$id = EMCP_Tools_Change_Recorder::record_resource_create( 42, $upload ? 'media' : 'elementor', $upload ? 'upload-media' : 'create-page' );
		if ( in_array( $case, array( 'created-record-failure', 'created-suppression' ), true ) ) {
			check( '' === $id && array() === EMCP_Tools_Change_Log::all(), 'Failed/suppressed creation returned a history entry' );
		} else {
			check( ! empty( EMCP_Tools_Change_Log::get( $id )['rollback']['after_hash'] ), 'Creation lacks a conflict guard' );
			if ( in_array( $case, array( 'created-draft-clock-undo', 'created-date-conflict' ), true ) ) { $posts[42]->post_date = '2026-09-15 10:01:00'; }
			if ( 'created-page-conflict' === $case ) { $meta[42]['new_human_field'] = array( 'newer' ); }
			if ( 'created-upload-conflict' === $case ) { file_put_contents( $creation_delete_file, 'newer bytes' ); }
			if ( in_array( $case, array( 'created-upload-delete-failure', 'created-upload-retry' ), true ) ) { $fail['files'] = true; }
			$result = EMCP_Tools_Change_Log::rollback( $id );
			if ( str_ends_with( $case, '-undo' ) ) {
				check( ! is_wp_error( $result ) && ! get_post( 42 ), 'Creation undo did not delete the resource' );
				if ( $upload ) { check( ! file_exists( $creation_delete_file ), 'Upload undo left the file' ); }
			} else {
				check( is_wp_error( $result ) && ! EMCP_Tools_Change_Log::get( $id )['rolled_back'], 'Failed/conflicting creation undo marked successful' );
				if ( str_ends_with( $case, '-conflict' ) ) { check( 'conflict' === $result->get_error_code() && get_post( 42 ), 'Newer creation state was deleted' ); }
				if ( 'created-upload-retry' === $case ) {
					$result = EMCP_Tools_Change_Log::rollback( $id, true );
					check( is_wp_error( $result ) && ! EMCP_Tools_Change_Log::get( $id )['rolled_back'], 'Retry hid an undeleted upload file' );
				}
			}
		}
	} finally {
		if ( $upload && file_exists( $creation_delete_file ) ) { unlink( $creation_delete_file ); }
	}
	echo "PASS\n"; exit;
}
if ( in_array( $case, array( 'generic-attachment-delete', 'legacy-generic-attachment-delete', 'blob-generic-attachment-delete' ), true ) ) {
	$snapshot = array( 'post' => array( 'ID' => 43, 'post_type' => 'attachment' ), 'meta' => array( '_wp_attached_file' => array( 'missing.png' ) ) );
	if ( 'generic-attachment-delete' === $case ) {
		$id = EMCP_Tools_Change_Recorder::record_post_delete( 43, $snapshot, true, 'Deleted attachment through generic post tool' );
	} else {
		$rb = array( 'type' => 'post-restore', 'mode' => 'reinsert', 'snapshot' => $snapshot );
		if ( 'blob-generic-attachment-delete' === $case ) {
			class EMCP_Tools_Change_Blobs {
				public static function get( $id ) { return array( 'snapshot' => $GLOBALS['snapshot'] ); }
			}
			unset( $rb['snapshot'] ); $rb['blob_id'] = 'attachment-snapshot';
		}
		$id = EMCP_Tools_Change_Log::record( array( 'domain' => 'content', 'action' => 'delete-post', 'rollback' => $rb ) );
	}
	if ( 'blob-generic-attachment-delete' !== $case ) {
		$list = ( new EMCP_Tools_Transaction_Abilities() )->execute_list( array() );
		check( false === $list['changes'][0]['reversible'], 'Attachment snapshot without files advertised as reversible' );
	}
	$result = EMCP_Tools_Change_Log::rollback( $id, true );
	check( is_wp_error( $result ) && 'snapshot_incomplete' === $result->get_error_code(), 'Generic attachment undo did not refuse the missing file snapshot' );
	check( ! get_post( 43 ) && ! EMCP_Tools_Change_Log::get( $id )['rolled_back'], 'Incomplete attachment restore changed target or completion state' );
	check( 1 === count( EMCP_Tools_Change_Log::all() ), 'Incomplete restore emitted a successful compensation' );
	echo "PASS\n"; exit;
}
if ( in_array( $case, array( 'journal-delete-failure', 'journal-clear-failure', 'journal-cap-failure' ), true ) ) {
	class EMCP_Tools_Change_Blobs {
		public static $deleted = array();
		public static function delete( $id ) { self::$deleted[] = $id; }
	}
	$id = EMCP_Tools_Change_Log::record( array( 'domain' => 'test', 'action' => 'test', 'rollback' => array( 'type' => 'post-fields', 'blob_id' => 'keep' ) ) );
	if ( 'journal-cap-failure' === $case ) {
		for ( $i = 1; $i < 500; ++$i ) { EMCP_Tools_Change_Log::record( array( 'domain' => 'test', 'action' => 'test' ) ); }
	}
	$fail['journal'] = true;
	if ( 'journal-delete-failure' === $case ) { check( false === EMCP_Tools_Change_Log::delete( $id ), 'Failed delete reported success' ); }
	elseif ( 'journal-clear-failure' === $case ) { check( 0 === EMCP_Tools_Change_Log::clear(), 'Failed clear reported success' ); }
	else { check( '' === EMCP_Tools_Change_Log::record( array( 'domain' => 'test', 'action' => 'test' ) ), 'Failed append reported success' ); }
	check( array() === EMCP_Tools_Change_Blobs::$deleted, 'Failed ledger persistence deleted a retained snapshot' );
	check( null !== EMCP_Tools_Change_Log::get( $id ), 'Existing entry was lost' );
	echo "PASS\n"; exit;
}
if ( 'journal-record-failure' === $case ) {
	$fail['journal'] = true;
	check( '' === EMCP_Tools_Change_Log::record( array( 'domain' => 'test', 'action' => 'test' ) ), 'Failed append returned a usable history ID' );
	echo "PASS\n"; exit;
}
$rb = null;
if ( str_starts_with( $case, 'legacy-db' ) ) {
	$rb = array( 'type' => 'db-before-image', 'op' => 'legacy-db-update' === $case ? 'update' : 'insert', 'table' => 'wp_demo', 'inserted_key' => array( 'name' => 'same' ), 'key_cols' => array( 'status' ), 'before_rows' => array( array( 'id' => 1, 'status' => 'draft' ) ) );
} elseif ( 'post-update-failure' === $case ) {
	$rb = array( 'type' => 'post-fields', 'post_id' => 42, 'before' => array( 'fields' => array( 'post_title' => 'Old' ) ) ); $fail['post'] = true;
} elseif ( 'post-meta-failure' === $case ) {
	$rb = array( 'type' => 'post-fields', 'post_id' => 42, 'before' => array( 'meta' => array( 'key' => 'old' ) ) ); $fail['meta'] = true;
} elseif ( 'option-failure' === $case ) {
	$rb = array( 'type' => 'option', 'values' => array( 'test' => 'old' ) ); $options['test'] = 'new';
} elseif ( 'journal-completion-failure' === $case ) {
	$rb = array( 'type' => 'option', 'values' => array( 'test' => 'old' ) ); $options['test'] = 'new';
} elseif ( 'post-delete-failure' === $case ) {
	$rb = array( 'type' => 'post-create', 'post_id' => 42 ); $fail['delete'] = true;
} elseif ( 'untrash-failure' === $case ) {
	$rb = array( 'type' => 'post-restore', 'mode' => 'untrash', 'post_id' => 42 );
} elseif ( 'missing-blob' === $case ) {
	$rb = array( 'type' => 'post-fields', 'post_id' => 42, 'blob_id' => 'missing' );
} elseif ( 'meta-handler-failure' === $case ) {
	$rb = array( 'type' => 'meta-before-image', 'object' => 'post', 'id' => 42, 'before' => array( 'key' => 'old' ) ); $fail['meta'] = true;
} elseif ( 'attachment-missing-backup' === $case ) {
	$rb = array( 'type' => 'attachment-delete', 'snapshot' => array( 'post' => array( 'ID' => 43 ), 'files' => array( array( 'orig' => ABSPATH . 'absent.jpg', 'trashed' => ABSPATH . 'absent-backup.jpg' ) ) ) );
} elseif ( 'attachment-id-collision' === $case || 'post-id-collision' === $case ) {
	$rb = array( 'type' => 'attachment-id-collision' === $case ? 'attachment-delete' : 'post-restore', 'snapshot' => array( 'post' => array( 'ID' => 42 ) ) );
} elseif ( 'legacy-post-scope' === $case ) {
	$rb = array( 'type' => 'post-fields', 'post_id' => 42, 'after_hash' => EMCP_Tools_Change_Recorder::hash_post( 42 ), 'before' => array( 'meta' => array( 'key' => 'old' ) ) );
}
if ( null !== $rb ) {
	$id = EMCP_Tools_Change_Log::record( array( 'domain' => 'test', 'action' => 'test', 'rollback' => $rb ) );
	if ( 'option-failure' === $case ) { $fail['option'] = true; }
	if ( 'journal-completion-failure' === $case ) { $fail['journal'] = true; }
	if ( 'legacy-db-availability' === $case ) {
		$list = ( new EMCP_Tools_Transaction_Abilities() )->execute_list( array() );
		check( false === $list['changes'][0]['reversible'], 'Unsafe DB entry advertised as reversible' );
	} else {
		$r = EMCP_Tools_Change_Log::rollback( $id, 'legacy-db-force' === $case );
		check( is_wp_error( $r ), 'Failed or unverifiable rollback reported success' );
		check( ! EMCP_Tools_Change_Log::get( $id )['rolled_back'], 'Failed rollback marked complete' );
		if ( str_starts_with( $case, 'legacy-db' ) ) { check( array() === $wpdb->calls, 'Unsafe DB inverse was executed' ); }
	}
} elseif ( in_array( $case, array( 'user-conflict', 'acf-conflict', 'user-failure', 'acf-failure' ), true ) ) {
	$users[7] = (object) array( 'ID' => 7, 'display_name' => 'New' ); $acf[7]['field_test'] = 'New';
	$user_case = str_starts_with( $case, 'user-' );
	$id = $user_case ? EMCP_Tools_Change_Recorder::record_user_fields( 7, array( 'display_name' => 'Old' ), 'Test' ) : EMCP_Tools_Change_Recorder::record_acf_fields( 7, array( 'field_test' => 'Old' ), 'Test' );
	if ( str_ends_with( $case, '-conflict' ) ) {
		if ( $user_case ) { $users[7]->display_name = 'Human'; } else { $acf[7]['field_test'] = 'Human'; }
	} else { $fail[ $user_case ? 'user' : 'acf' ] = true; }
	$r = EMCP_Tools_Change_Log::rollback( $id );
	check( is_wp_error( $r ), 'User/ACF rollback reported success' );
	if ( str_ends_with( $case, '-conflict' ) ) { check( 'conflict' === $r->get_error_code(), 'User/ACF conflict not detected' ); }
	check( ! EMCP_Tools_Change_Log::get( $id )['rolled_back'], 'User/ACF failure marked rolled back' );
} elseif ( in_array( $case, array( 'meta-conflict', 'untouched-field', 'term-conflict', 'field-conflict', 'suppression' ), true ) ) {
	$before = array( 'meta' => array( 'key' => 'old' ) ); $meta[42]['key'] = array( 'new' );
	if ( 'term-conflict' === $case ) { $before = array( 'terms' => array( 'category' => array( 1 ) ) ); $terms[42]['category'] = array( 2 ); }
	if ( 'field-conflict' === $case ) { $before = array( 'fields' => array( 'post_author' => 0 ) ); }
	$id = EMCP_Tools_Change_Recorder::record_post_fields( 42, $before, 'Test' );
	if ( 'meta-conflict' === $case ) { $meta[42]['key'] = array( 'human edit' ); }
	if ( 'term-conflict' === $case ) { $terms[42]['category'] = array( 3 ); }
	if ( 'field-conflict' === $case ) { $posts[42]->post_author = 2; }
	if ( 'untouched-field' === $case ) { $posts[42]->post_title = 'Human title'; }
	if ( 'suppression' === $case ) { EMCP_Tools_Change_Log::$suppress = true; }
	$r = EMCP_Tools_Change_Log::rollback( $id );
	if ( in_array( $case, array( 'meta-conflict', 'term-conflict', 'field-conflict' ), true ) ) {
		check( is_wp_error( $r ) && 'conflict' === $r->get_error_code(), 'Newer edit was not protected' );
	} else {
		check( ! is_wp_error( $r ), 'Unchanged touched state could not be restored' );
		if ( 'suppression' === $case ) { check( EMCP_Tools_Change_Log::$suppress, 'Outer suppression context lost' ); }
		if ( 'untouched-field' === $case ) { check( 'Human title' === $posts[42]->post_title, 'Unrelated edit was overwritten' ); }
	}
} else {
	$data = new class extends EMCP_Tools_Data {
		public function get_document( int $id ) { return new class { public function save( $args ) { return str_starts_with( $GLOBALS['case'], 'page-settings-native' ); } }; }
	};
	$original = array( array( 'custom_css' => 'a{color:blue}', 'nested' => array( 'path' => 'C:\\test' ) ) );
	if ( 'meta-absence' === $case ) { $original = array(); }
	if ( 'meta-empty' === $case ) { $original = array( array() ); }
	if ( 'meta-duplicates' === $case ) { $original[] = array( 'other' => 'row' ); }
	if ( 'meta-backslashes' === $case ) { $original = array( array( 'custom_css' => 'a::after{content:"\\2192"}' ) ); }
	if ( $original ) { $meta[42]['_elementor_page_settings'] = $original; }
	if ( 'page-settings-failure' === $case ) { $fail['meta'] = true; }
	if ( 'page-settings-journal-failure' === $case ) { $fail['journal'] = true; }
	$r = $data->save_page_settings( 42, array( 'custom_css' => 'page-settings-native-noop' === $case ? 'a{color:blue}' : 'a{color:red}' ) );
	if ( 'page-settings-journal-failure' === $case ) {
		check( is_wp_error( $r ) && 'history_record_failed' === $r->get_error_code(), 'Saved settings without history reported ordinary success' );
	} elseif ( 'page-settings-native-noop' === $case ) {
		check( true === $r && array() === EMCP_Tools_Change_Log::all(), 'Genuine no-op was rejected or recorded' );
	} elseif ( 'page-settings-failure' === $case || 'page-settings-native-drop' === $case ) {
		check( is_wp_error( $r ), 'Failed settings save reported success' );
		check( array() === EMCP_Tools_Change_Log::all(), 'Failed settings save recorded as applied' );
	} else {
		$log = EMCP_Tools_Change_Log::all(); check( 1 === count( $log ), 'Page settings save omitted history' );
		if ( 'page-settings-conflict' === $case ) { $meta[42]['_elementor_page_settings'] = array( array( 'custom_css' => 'human edit' ) ); }
		$r = EMCP_Tools_Change_Log::rollback( $log[0]['id'] );
		if ( 'page-settings-conflict' === $case ) { check( is_wp_error( $r ) && 'conflict' === $r->get_error_code(), 'Newer page settings not protected' ); }
		else { check( ! is_wp_error( $r ), 'Settings rollback failed' ); check( $original === get_post_meta( 42, '_elementor_page_settings', false ), 'Exact settings state was not restored' ); }
	}
}
echo "PASS\n";
