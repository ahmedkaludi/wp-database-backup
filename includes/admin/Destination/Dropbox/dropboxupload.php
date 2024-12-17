<?php
/**
 * Destination dropboxs
 *
 * @package wpdbbkp
 */

?>
<?php

require plugin_dir_path( __FILE__ ) . 'class-wpdbbackup-destination-dropbox-api.php';
if ( isset( $_GET['action'] ) && 'deleteauth' === $_GET['action'] ) {
	// disable token on dropbox.
	try {
		$dropbox = new WPDBBackup_Destination_Dropbox_API();
		$dropbox->setOAuthTokens( maybe_unserialize( get_option( 'wpdb_dropboxtoken' ) ) );
		$dropbox->authTokenRevoke();
	} catch ( Exception $e ) {
		echo '<div id="message" class="error"><p> Dropbox API: ' . esc_attr( $e->getMessage() ) . ' </p></div>';
	}
	update_option( 'wpdb_dropboxtoken', '' , false);
	wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=deleteauth' );

}

$dropbox          = new WPDBBackup_Destination_Dropbox_API( 'dropbox' );
$dropbox_auth_url = $dropbox->oAuthAuthorize();
if ( true === isset( $_POST['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ) , 'wp-database-backup' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
	if ( isset( $_POST['wpdb_dropbbox_code'] ) && ! empty( $_POST['wpdb_dropbbox_code'] ) ) {
		$dropboxtoken = $dropbox->oAuthToken( sanitize_text_field( wp_unslash( $_POST['wpdb_dropbbox_code'] ) ) );
		$dropboxtoken = update_option( 'wpdb_dropboxtoken', maybe_serialize( $dropboxtoken ) , false);
	}

	if ( isset( $_POST['wpdb_dropbbox_dir'] ) ) {
		$dropboxtoken = update_option( 'wpdb_dropbbox_dir', sanitize_text_field( wp_unslash( $_POST['wpdb_dropbbox_dir'] ) ), false );
	}
}

$wpdb_dropboxtoken = get_option( 'wpdb_dropboxtoken' );
$dropboxtoken      = ! empty( $wpdb_dropboxtoken ) ? maybe_unserialize( $wpdb_dropboxtoken ) : array();


?>
<form class="form-group" name="form2" method="post" action="">

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Authentication', 'wpdbbkp' ); ?></th>
			<td><?php if ( empty( $dropboxtoken['access_token'] ) ) { ?>
					<span style="color:red;"><?php esc_html_e( 'Not authenticated!', 'wpdbbkp' ); ?></span><br/>&nbsp;
					<br/>
					<a class="button secondary" href="http://db.tt/8irM1vQ0" target="_blank"><?php esc_html_e( 'Create Account', 'wpdbbkp' ); ?></a><br/><br/>
				<?php } else { ?>
					<span style="color:green;"><?php esc_html_e( 'Authenticated!', 'wpdbbkp' ); ?></span>
					<?php
					$dropbox->setOAuthTokens( $dropboxtoken );
					$info = $dropbox->usersGetCurrentAccount();
					if ( ! empty( $info['account_id'] ) ) {

						$user = $info['name']['display_name'];

						esc_attr_e( ' with Dropbox of user ', 'wpdbbkp' );
						echo esc_attr( $user ) . '<br/>';
						// Quota.
						$quota            = $dropbox->usersGetSpaceUsage();
						$dropboxfreespase = $quota['allocation']['allocated'] - $quota['used'];
						echo esc_attr( size_format( $dropboxfreespase, 2 ) );
						esc_attr_e( ' available on your Dropbox', 'wpdbbkp' );

					}
					?>
					<br><br>
					<a class="button secondary" href="<?php echo esc_url( site_url() . '/wp-admin/admin.php?page=wp-database-backup&action=deleteauth&_wpnonce=' . $nonce ); ?> " title="<?php esc_html_e( 'Unlink Dropbox Account', 'wpdbbkp' ); ?>"><?php esc_html_e( 'Unlink Dropbox Account', 'wpdbbkp' ); ?></a>
					<p><?php echo esc_html__('Unlink Dropbox Account for local backups.', 'wpdbbkp'); ?></p>
				<?php } ?>
			</td>
		</tr>

		<?php if ( empty( $dropboxtoken['access_token'] ) ) { ?>
			<tr>
				<th scope="row"><label for="id_dropbbox_code"><?php esc_html_e( 'Access to Dropbox', 'wpdbbkp' ); ?></label></th>
				<td>
					<input id="id_dropbbox_code" name="wpdb_dropbbox_code" type="text" value="" class="regular-text code"/>&nbsp;
					<a class="button secondary" href="<?php echo esc_attr( $dropbox_auth_url ); ?>" target="_blank"><?php esc_html_e( 'Get Dropbox auth code ', 'wpdbbkp' ); ?></a>
					<p><?php echo esc_html__('In order to use Dropbox destination you will need to Get Dropbox auth code with your Dropbox account on click', 'wpdbbkp'); ?> <strong><?php echo esc_html__('Get Dropbox auth code', 'wpdbbkp'); ?></strong> <?php echo esc_html__('button', 'wpdbbkp'); ?></p>
					<p><?php echo esc_html__('Enter Dropbox auth code in text box and save changes', 'wpdbbkp'); ?></p>
					<p><?php echo esc_html__('For local backup leave the setting as it is', 'wpdbbkp'); ?></p>
				</td>
			</tr>
		<?php } ?>
	</table>

	<p></p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="iddropboxdir"><?php esc_html_e( 'Destination Folder', 'wpdbbkp' ); ?></label></th>
			<td>
				<input id="wpdb_dropbbox_dir" name="wpdb_dropbbox_dir" type="text" value="<?php echo esc_attr( get_option( 'wpdb_dropbbox_dir' ) ); ?>" class="regular-text"/>
				<p class="description">
					<?php esc_html_e( 'Specify a subfolder where your backup archives will be stored. It will be created at the Apps › WP-Database-Backup of your Dropbox. Already exisiting folders with the same name will not be overriden.', 'wpdbbkp' ); ?>

				</p>
				<p><?php echo esc_html__('E.g. backup', 'wpdbbkp'); ?></p>
			</td>
		</tr>
	</table>
	<input type="hidden" name="<?php echo esc_attr( $hidden_field_name ); ?>" value="Y">
	<input name="wpdbbackup_update_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-setting' ) ); ?>"/>
	<?php wp_nonce_field( 'wp-database-backup' ); ?>

	<input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save' , 'wpdbbkp' ); ?>"/>&nbsp;
</form>
