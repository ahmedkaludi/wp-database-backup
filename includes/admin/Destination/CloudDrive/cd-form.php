<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$update_msg = '';
if ( true === isset( $_POST['wpdb_cd_s3'] ) && 'Y' === $_POST['wpdb_cd_s3'] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_cd_setting'] ) ) {
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	if ( ! wp_verify_nonce( wp_unslash( $_POST['wpdbbackup_update_cd_setting'] ), 'wpdbbackup-update-cd-setting' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}

	if ( true === isset( $_POST['wpdb_clouddrive_token'] ) ) {
		update_option( 'wpdb_clouddrive_token', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_clouddrive_token'] ) ) ), false );
	}
	
	if ( isset( $_POST['wp_db_backup_destination_cd'] ) ) {
		update_option( 'wp_db_backup_destination_cd', 1 , false);
	} else {
		update_option( 'wp_db_backup_destination_cd', 0 , false);
	}
	// Put a "settings updated" message on the screen.
	$update_msg = esc_html__('Your BackupforWP Cloud backup setting has been saved.' , 'wpdbbkp');
}
$wpdb_clouddrive_token = get_option( 'wpdb_clouddrive_token',null);

$wpdbbkp_bb_s3_status			=	'<label><b>'.esc_html__('Status', 'wpdbbkp').'</b>: '.esc_html__('Not Configured', 'wpdbbkp').' </label> ';

if(!empty($wpdb_clouddrive_token))
{
	$wpdbbkp_bb_s3_status ='<label><b>'.esc_html__('Status', 'wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled', 'wpdbbkp').'"></span><span class="configured">'.esc_html__('Configured', 'wpdbbkp').' </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapsebb">
				<h2><?php echo esc_html__('BackupforWP CloudDrive', 'wpdbbkp') ?> <?php echo wp_kses_post($wpdbbkp_bb_s3_status);?> <span class="dashicons dashicons-admin-generic"></span></h2>

			</a>
		</h4>
	</div>
	<div id="collapsebb" class="panel-collapse collapse">
		<div class="panel-body">
			<?php
			if($update_msg){
				echo '<div class="updated"><p><strong>'.esc_html( $update_msg ).'</strong></p></div>';
			}
			?>
			<p> <?php echo esc_html__('Back up WordPress database to Backup for WP Cloud Backup.', 'wpdbbkp') ?></p>
			<p><?php echo esc_html__('Enter your Cloud Backup token for your offsite backup. Leave these blank for local backups OR Disable Cloud Backup Destination', 'wpdbbkp') ?></p>
			<form  class="form-group" name="CloudDrive" method="post" action="">

				<input type="hidden" name="wpdb_cd_s3" value="Y">
				<input name="wpdbbackup_update_cd_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-cd-setting' ) ); ?>" />
				<?php wp_nonce_field( 'wp-database-backup' ); ?>
				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_clouddrive_token"><?php echo esc_html__('BackupforWP Cloud Backup API Token', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_clouddrive_token" class="form-control" name="wpdb_clouddrive_token" value="<?php echo esc_html( get_option( 'wpdb_clouddrive_token' ) ); ?>" size="25" placeholder="<?php esc_attr_e('26b18a624d2f5e01324bc81f90cfff63ba493bc15f00d790729fb437e90f54ea','wpdbbkp');?>">
						<a href="https://app.backupforwp.com/" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>
				
				<p><input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save' , 'wpdbbkp' ); ?>" />&nbsp;
				</p>
			</form>

		</div>
	</div>
</div>
