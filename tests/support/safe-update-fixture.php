<?php
/** Disposable local integration fixture. Never targets the source WordPress database for writes. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$_SERVER['HTTP_HOST'] = '127.0.0.1:18765'; $_SERVER['REQUEST_SCHEME'] = 'http'; $_SERVER['REQUEST_URI'] = '/';
$action = $argv[1] ?? '';
$root = str_replace( '\\', '/', $argv[2] ?? '' );
if ( ! preg_match( '#^E:/MSR Builds/Products/EMCP/website/output/safe-update-smoke-[a-z0-9-]+/$#D', $root ) ) { throw new RuntimeException( 'Dedicated fixture root required' ); }
$fixture_database = 'emcp_safe_test_' . substr( hash( 'sha256', $root ), 0, 12 );
if ( 'setup' !== $action ) {
	$config_check = file_get_contents( $root . 'wp-config.php' );
	if ( ! str_contains( $config_check, "define('DB_NAME','" . $fixture_database . "');" ) ) { throw new RuntimeException( 'Refusing a non-fixture database' ); }
}
if ( 'setup' === $action ) {
	// Read literal connection settings only. Never bootstrap or execute the source site's configuration.
	$source_root = 'F:/laragon/www/msrplugins/';
	$source_config = file_get_contents( $source_root . 'wp-config.php' );
	$credentials = array();
	foreach ( array( 'DB_USER', 'DB_PASSWORD', 'DB_HOST' ) as $key ) {
		if ( ! preg_match( "/define\\(\\s*'" . $key . "'\\s*,\\s*'((?:\\\\.|[^'\\\\])*)'\\s*\\)/", $source_config, $match ) ) { throw new RuntimeException( 'Literal local connection setting required' ); }
		$credentials[ $key ] = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $match[1] );
	}
	if ( is_dir( $root ) ) { throw new RuntimeException( 'Fixture must be fresh' ); }
	mkdir( $root, 0700, true );
	$copy = function ( $from, $to ) use ( &$copy ) {
		if ( ! is_dir( $to ) ) { mkdir( $to, 0755, true ); }
		foreach ( new DirectoryIterator( $from ) as $entry ) {
			if ( $entry->isDot() ) { continue; }
			if ( $entry->isDir() ) { $copy( $entry->getPathname(), $to . '/' . $entry->getFilename() ); }
			elseif ( ! copy( $entry->getPathname(), $to . '/' . $entry->getFilename() ) ) { throw new RuntimeException( 'copy_failed' ); }
		}
	};
	foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) { $copy( $source_root . $dir, $root . $dir ); }
	foreach ( glob( $source_root . '*.php' ) as $file ) { if ( 'wp-config.php' !== basename( $file ) ) { copy( $file, $root . basename( $file ) ); } }
	mkdir( $root . 'wp-content/plugins', 0755, true ); mkdir( $root . 'wp-content/themes/safe-test', 0755, true );
	file_put_contents( $root . 'wp-content/themes/safe-test/style.css', "/* Theme Name: Safe Test\nVersion: 1.0 */" );
	file_put_contents( $root . 'wp-content/themes/safe-test/index.php', '<?php header("Content-Type: text/html"); ?><!doctype html><html><title>Safe update fixture</title><body>' . str_repeat( 'Fixture content. ', 150 ) . '</body></html>' );
	$dbname = $fixture_database;
	$connection = new mysqli( $credentials['DB_HOST'], $credentials['DB_USER'], $credentials['DB_PASSWORD'] );
	$connection->query( 'CREATE DATABASE `' . $dbname . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
	$config = "<?php\n";
	foreach ( array_merge( $credentials, array( 'DB_NAME' => $dbname, 'DB_CHARSET' => 'utf8mb4', 'DB_COLLATE' => '', 'FS_METHOD' => 'direct', 'WP_DEBUG' => false, 'WP_CACHE' => false ) ) as $key => $value ) { $config .= 'define(' . var_export( $key, true ) . ',' . var_export( $value, true ) . ");\n"; }
	$config .= "\$table_prefix='wp_';\nif (!defined('ABSPATH')) define('ABSPATH',__DIR__.'/');\nrequire_once ABSPATH.'wp-settings.php';\n";
	$config .= "add_filter('http_request_host_is_external',static fn(\$external,\$host)=>\$host==='127.0.0.1' ? true : \$external,10,2);\nadd_filter('http_allowed_safe_ports',static fn(\$ports)=>array_merge(\$ports,array(18766)));\n";
	$config .= "add_filter('pre_http_request',static fn(\$pre,\$args,\$url)=>parse_url(\$url,PHP_URL_HOST)==='127.0.0.1' ? \$pre : new WP_Error('fixture_offline','External HTTP disabled in disposable fixture'),10,3);\n";
	file_put_contents( $root . 'wp-config.php', $config );
	echo "Fixture files and dedicated database created\n";
} elseif ( 'install' === $action ) {
	define( 'WP_INSTALLING', true ); require $root . 'wp-load.php'; require_once $root . 'wp-admin/includes/upgrade.php';
	wp_install( 'Safe update test', 'fixtureadmin', 'fixture@example.invalid', false, '', bin2hex( random_bytes( 24 ) ) );
	update_option( 'siteurl', 'http://127.0.0.1:18765' ); update_option( 'home', 'http://127.0.0.1:18765' ); switch_theme( 'safe-test' );
	echo "Fixture installed\n";
} elseif ( 'packages' === $action ) {
	$dest = dirname( rtrim( $root, '/' ) ) . '/safe-update-packages'; if ( ! is_dir( $dest ) ) { mkdir( $dest ); }
	$plugins = array( 'parent' => "<?php\n/* Plugin Name: Parent\nVersion: 2.0 */\nfunction safe_new_parent() { return true; }\n", 'child' => "<?php\n/* Plugin Name: Child\nVersion: 2.0 */\nsafe_new_parent();\n", 'bad' => "<?php\n/* Plugin Name: Bad\nVersion: 2.0 */\nupdate_option('emcp_safe_fixture','changed-by-bad-update');\nfile_put_contents(ABSPATH.'created-by-update.txt','fixture');\nglobal \$wpdb; \$wpdb->query('CREATE TABLE IF NOT EXISTS '.\$wpdb->prefix.'safe_update_new (id INT) ENGINE=MyISAM');\nthrow new Error('Intentional update fixture failure');\n" );
	foreach ( $plugins as $id => $source ) { $zip = new ZipArchive(); $zip->open( $dest . '/' . $id . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ); $zip->addFromString( $id . '/' . $id . '.php', $source ); $zip->close(); }
	$zip = new ZipArchive(); $zip->open( $dest . '/theme.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( 'safe-test/style.css', "/* Theme Name: Safe Test\nVersion: 2.0 */" );
	$zip->addFile( $root . 'wp-content/themes/safe-test/index.php', 'safe-test/index.php' ); $zip->close();
	$zip = new ZipArchive(); $zip->open( $dest . '/core.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) ) );
			if ( 'wp-includes/version.php' === $relative ) {
				$version = file_get_contents( $file->getPathname() );
				$version = preg_replace( "/\\\$wp_version\\s*=\\s*'[^']+';/", "\$wp_version = '99.0.0';", $version );
				$version = preg_replace_callback( '/\$wp_db_version\s*=\s*([0-9]+);/', static fn( $m ) => '$wp_db_version = ' . ( (int) $m[1] + 1 ) . ';', $version );
				$zip->addFromString( 'wordpress/' . $relative, $version );
			} else { $zip->addFile( $file->getPathname(), 'wordpress/' . $relative ); }
		}
	}
	foreach ( glob( $root . '*.php' ) as $file ) { if ( 'wp-config.php' !== basename( $file ) ) { $zip->addFile( $file, 'wordpress/' . basename( $file ) ); } }
	$zip->addFile( 'F:/laragon/www/msrplugins/readme.html', 'wordpress/readme.html' );
	$zip->addFile( 'F:/laragon/www/msrplugins/license.txt', 'wordpress/license.txt' );
	$zip->close();
	echo "Local update packages created\n";
} elseif ( 'prepare' === $action ) {
	require_once dirname( __DIR__, 2 ) . '/includes/cloud/safe-update-runtime.php';
	$spec = json_decode( file_get_contents( $argv[3] ), true, 512, JSON_THROW_ON_ERROR );
	define( 'WP_INSTALLING', true ); require $root . 'wp-load.php';
	$dir = $root . '.emcp-update-' . $spec['job']; mkdir( $dir, 0700 );
	$source = dirname( __DIR__, 2 ) . '/includes/cloud/';
	copy( $source . 'safe-update-runtime.php', $dir . '/runtime.php' ); copy( $source . 'safe-update-gate.php', $dir . '/gate.php' );
	$config = file_get_contents( $root . 'wp-config.php' );
	$state = array( 'job' => $spec['job'], 'token_hash' => hash( 'sha256', $spec['token'] ), 'root' => $root, 'dir' => $dir, 'phase' => 'prepared', 'group' => 0, 'groups' => $spec['groups'], 'paths' => array( '/', '/wp-login.php' ), 'base_path' => '', 'deadline' => time()+3600, 'db' => array( 'host' => DB_HOST, 'user' => DB_USER, 'password' => DB_PASSWORD, 'name' => DB_NAME, 'prefix' => 'wp_' ), 'config_hash' => hash( 'sha256', $config ) );
	EMCP_Update_Runtime::write( $dir . '/original-config.php', $config ); EMCP_Update_Runtime::write( $dir . '/state.php', $state );
	file_put_contents( $dir . '/runner.php', "<?php require __DIR__.'/runtime.php'; EMCP_Update_Runtime::serve(__DIR__);" );
	echo "Recovery fixture prepared\n";
} elseif ( 'seed' === $action ) {
	define( 'WP_INSTALLING', true ); require $root . 'wp-load.php';
	$plugins = array( 'parent' => "<?php\n/* Plugin Name: Parent\nVersion: 1.0 */\nfunction safe_old_parent() { return true; }\n", 'child' => "<?php\n/* Plugin Name: Child\nVersion: 1.0 */\nsafe_old_parent();\n", 'bad' => "<?php\n/* Plugin Name: Bad\nVersion: 1.0 */\n" );
	foreach ( $plugins as $id => $source ) { if ( ! is_dir( $root . 'wp-content/plugins/' . $id ) ) { mkdir( $root . 'wp-content/plugins/' . $id ); } file_put_contents( $root . 'wp-content/plugins/' . $id . '/' . $id . '.php', $source ); }
	update_option( 'active_plugins', array( 'parent/parent.php', 'child/child.php', 'bad/bad.php' ) );
	update_option( 'emcp_safe_fixture', 'before' );
	echo "Fixture plugins seeded\n";
} elseif ( 'fast-drain' === $action ) {
	// Test-only shortcut. Production always waits the full drain interval.
	require_once dirname( __DIR__, 2 ) . '/includes/cloud/safe-update-runtime.php';
	$path = $root . '.emcp-update-' . $argv[3] . '/state.php'; $state = EMCP_Update_Runtime::read( $path );
	if ( 'draining' !== $state['phase'] ) { throw new RuntimeException( 'Not draining' ); }
	$state['drain_until'] = 0; EMCP_Update_Runtime::write( $path, $state );
} elseif ( 'verify' === $action ) {
	require $root . 'wp-load.php';
	if ( 'before' !== get_option( 'emcp_safe_fixture' ) || ! function_exists( 'safe_new_parent' ) || '2.0' !== wp_get_theme()->get( 'Version' ) || '99.0.0' !== $GLOBALS['wp_version'] || (int) get_option( 'db_version' ) !== (int) $GLOBALS['wp_db_version'] ) { throw new RuntimeException( 'Restore did not preserve preceding successful groups' ); }
	if ( file_exists( $root . 'created-by-update.txt' ) || $GLOBALS['wpdb']->get_var( "SHOW TABLES LIKE 'wp_safe_update_new'" ) ) { throw new RuntimeException( 'Update-created objects were not removed' ); }
	if ( str_contains( file_get_contents( $root . 'wp-config.php' ), 'EMCP_UPDATE_GATE' ) ) { throw new RuntimeException( 'Maintenance was not released' ); }
	echo "Database, successful dependency group and maintenance cleanup verified\n";
} elseif ( 'cleanup-database' === $action ) {
	// No WordPress bootstrap: even a deliberately broken fixture can be cleaned safely.
	$credentials = array();
	foreach ( array( 'DB_USER', 'DB_PASSWORD', 'DB_HOST' ) as $key ) {
		if ( ! preg_match( "/define\\(\\s*'" . $key . "'\\s*,\\s*'((?:\\\\.|[^'\\\\])*)'\\s*\\)/", $config_check, $match ) ) { throw new RuntimeException( 'Literal fixture credentials required' ); }
		$credentials[ $key ] = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $match[1] );
	}
	if ( ! preg_match( '/^emcp_safe_test_[a-f0-9]{12}$/D', $fixture_database ) ) { throw new RuntimeException( 'Invalid fixture database' ); }
	$connection = new mysqli( $credentials['DB_HOST'], $credentials['DB_USER'], $credentials['DB_PASSWORD'] );
	$connection->query( 'DROP DATABASE `' . $fixture_database . '`' );
	echo "Disposable fixture database removed\n";
}
