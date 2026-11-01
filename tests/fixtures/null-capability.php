<?php
// Isolated harness for #174: core's map_meta_cap can pass a null capability.
define( 'ABSPATH', __DIR__ ); define( 'EMCP_TOOLS_DIR', dirname( __DIR__, 2 ) . '/' );
set_error_handler( static function( $severity, $message ) { throw new RuntimeException( $message ); } );
require EMCP_TOOLS_DIR . 'includes/class-management-policy.php';
require EMCP_TOOLS_DIR . 'includes/class-management-access.php';
require EMCP_TOOLS_DIR . 'includes/class-post-authorization.php';
require EMCP_TOOLS_DIR . 'includes/themer/class-themer-cpt.php';
$callbacks = array(
	array( 'EMCP_Tools_Management_Access', 'map_capability' ),
	array( 'EMCP_Tools_Post_Authorization', 'protect_sitewide_elementor_template' ),
	array( 'EMCP_Tools_Themer_CPT', 'map_template_caps' ),
	array( 'EMCP_Tools_Themer_CPT', 'protect_php_backed_template' ),
);
foreach ( $callbacks as $callback ) {
	foreach ( array( null, 0, false ) as $cap ) {
		// The way WP_Hook::apply_filters() invokes a map_meta_cap callback.
		$caps = call_user_func_array( $callback, array( array( null ), $cap, 7, array() ) );
		if ( array( null ) !== $caps ) {
			fwrite( STDERR, implode( '::', $callback ) . ' changed the caps for ' . var_export( $cap, true ) . "\n" );
			exit( 1 );
		}
	}
}
echo "PASS\n";
