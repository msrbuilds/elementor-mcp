<?php
/** Local access policy; rendered with $policy, $actor and $users. @package EMCP_Tools */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="emcp-management-access">
	<h1><?php esc_html_e( 'Management access', 'emcp-tools' ); ?></h1>
	<p><?php esc_html_e( 'Choose which WordPress administrators can manage EMCP Tools, its settings and Cloud connection on this site.', 'emcp-tools' ); ?></p>
	<p><?php echo esc_html( sprintf( /* translators: 1: WordPress login, 2: user ID. */ __( 'You are managing this site as %1$s (WordPress user ID %2$d).', 'emcp-tools' ), $actor->user_login, $actor->ID ) ); ?></p>
	<p><?php esc_html_e( 'MCP and Gateway calls use the WordPress account that authorized their credentials. This local management policy does not change that account or its execution permissions.', 'emcp-tools' ); ?></p>
	<?php $locked = 'config' === ( $policy['source'] ?? '' ); ?>
	<?php if ( $locked ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'This policy is set by EMCP_TOOLS_MANAGEMENT_ADMINS in wp-config.php, which overrides the setting saved here. Change or remove the constant to edit it.', 'emcp-tools' ); ?></p></div>
		<?php
		$not_admins = array_values( array_filter( $policy['users'], static fn( $id ) => ! user_can( $id, 'manage_options' ) ) );
		if ( $not_admins ) :
			?>
			<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( /* translators: %s: user IDs. */ __( 'These IDs in wp-config.php are not current administrators and get no access: %s', 'emcp-tools' ), implode( ', ', $not_admins ) ) ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( '1' === ( $_GET['saved'] ?? '' ) ) : ?>
		<div class="notice notice-success inline" role="status"><p><?php esc_html_e( 'Management access saved.', 'emcp-tools' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( EMCP_Tools_Management_Access_Admin::ACTION ); ?>">
		<input type="hidden" name="version" value="<?php echo (int) $policy['version']; ?>">
		<?php wp_nonce_field( EMCP_Tools_Management_Access_Admin::ACTION ); ?>
		<fieldset <?php disabled( $locked ); ?>>
		<fieldset><legend><strong><?php esc_html_e( 'Who can manage EMCP Tools?', 'emcp-tools' ); ?></strong></legend>
			<p><label><input type="radio" name="mode" value="all_admins" <?php checked( $policy['mode'], 'all_admins' ); ?>> <?php esc_html_e( 'All current administrators (default)', 'emcp-tools' ); ?></label></p>
			<p><label><input type="radio" name="mode" value="allowlist" <?php checked( $policy['mode'], 'allowlist' ); ?>> <?php esc_html_e( 'Only the listed administrators', 'emcp-tools' ); ?></label></p>
		</fieldset>
		<p><label for="emcp-management-users"><strong><?php esc_html_e( 'Administrator user IDs', 'emcp-tools' ); ?></strong></label></p>
		<p><input id="emcp-management-users" name="users" type="text" class="regular-text" maxlength="2000" aria-describedby="emcp-management-users-help" value="<?php echo esc_attr( implode( ', ', $policy['users'] ?: array( (int) $actor->ID ) ) ); ?>"></p>
		<p id="emcp-management-users-help"><?php esc_html_e( 'Separate IDs with commas. Up to 100 managers. Keep your own ID in the list. Each account must retain administrator permissions. This field is ignored when all administrators are allowed.', 'emcp-tools' ); ?></p>
		<p><label><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I confirm this site-local management policy.', 'emcp-tools' ); ?></label></p>
		</fieldset>
		<?php if ( ! $locked ) { submit_button( __( 'Save management access', 'emcp-tools' ) ); } ?>
	</form>
	<h2><?php esc_html_e( 'Administrator directory', 'emcp-tools' ); ?></h2>
	<p><?php esc_html_e( 'The first 100 administrator accounts are shown below. Use WordPress Users to find other IDs. A Multisite super administrator must also be listed when the allowlist is active.', 'emcp-tools' ); ?></p>
	<table class="widefat striped"><caption class="screen-reader-text"><?php esc_html_e( 'Administrator account IDs', 'emcp-tools' ); ?></caption>
		<thead><tr><th scope="col"><?php esc_html_e( 'User ID', 'emcp-tools' ); ?></th><th scope="col"><?php esc_html_e( 'WordPress login', 'emcp-tools' ); ?></th></tr></thead>
		<tbody><?php foreach ( $users as $user ) : ?><tr><td><?php echo (int) $user->ID; ?></td><td><?php echo esc_html( $user->user_login ); ?></td></tr><?php endforeach; ?></tbody>
	</table>
	<h2><?php esc_html_e( 'Recovery and limits', 'emcp-tools' ); ?></h2>
	<p><?php esc_html_e( 'If all listed managers are deleted or demoted, a host operator can recover with WP-CLI using a current administrator account. A site or host owner who can install PHP or edit the database can change this policy.', 'emcp-tools' ); ?></p>
	<p><code>wp emcp management-access recover --user=ADMIN_ID --confirm</code></p>
	<p><?php esc_html_e( 'To set the list in wp-config.php instead, add one of these lines. While the constant is defined it overrides the setting above and cannot be changed from the dashboard or WP-CLI.', 'emcp-tools' ); ?></p>
	<p><code>define( 'EMCP_TOOLS_MANAGEMENT_ADMINS', '1,5' );</code> <code>define( 'EMCP_TOOLS_MANAGEMENT_ADMINS', 'all' );</code></p>
</section>
