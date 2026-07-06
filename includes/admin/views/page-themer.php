<?php
/**
 * Themer admin tab: templates grouped by type + theme-adapter status.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$types     = EMCP_Tools_Themer_CPT::TYPES;
$adapter   = EMCP_Tools_Themer_Theme_Adapters::current();
$supported = null !== $adapter;
?>
<div class="wrap emcp-tools-themer">
	<h1><?php esc_html_e( 'EMCP Themer', 'emcp-tools' ); ?></h1>

	<div class="notice notice-<?php echo $supported ? 'success' : 'warning'; ?> inline">
		<p>
			<?php
			if ( $supported ) {
				printf(
					/* translators: %s: theme name */
					esc_html__( 'Your theme (%s) is directly supported — standalone headers & footers inject cleanly.', 'emcp-tools' ),
					esc_html( wp_get_theme()->get( 'Name' ) )
				);
			} else {
				esc_html_e( 'Your theme is not directly supported for standalone header/footer replacement. Body templates (single/archive/search/404) still work everywhere. For headers/footers, enable Force Render below or add emcp_themer_location() calls to your theme.', 'emcp-tools' );
			}
			?>
		</p>
	</div>

	<?php foreach ( $types as $type ) : ?>
		<?php
		$q = new WP_Query(
			array(
				'post_type'      => EMCP_Tools_Themer_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'meta_key'       => '_emcp_themer_type',
				'meta_value'     => $type,
				'no_found_rows'  => true,
			)
		);
		$create_url = admin_url( 'post-new.php?post_type=' . EMCP_Tools_Themer_CPT::POST_TYPE . '&emcp_themer_type=' . $type );
		?>
		<h2><?php echo esc_html( ucfirst( $type ) ); ?></h2>
		<?php if ( $q->have_posts() ) : ?>
			<ul>
				<?php foreach ( $q->posts as $p ) : ?>
					<li>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
						<span class="description">(<?php echo esc_html( $p->post_status ); ?>)</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'None yet.', 'emcp-tools' ); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( $create_url ); ?>">
				<?php
				/* translators: %s: template type */
				printf( esc_html__( 'Add %s', 'emcp-tools' ), esc_html( ucfirst( $type ) ) );
				?>
			</a>
		</p>
		<?php wp_reset_postdata(); ?>
	<?php endforeach; ?>
</div>
