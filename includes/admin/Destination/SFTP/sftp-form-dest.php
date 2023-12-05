<?php
/**
 * Destination file.
 *
 * @package wpdbbkp
 */

$wpdbbkp_sftp_enabled	=	get_option( 'wp_db_backup_destination_SFTP',false);
$wpdbbkp_sftp_details	=	get_option( 'wp_db_backup_sftp_details',null );
$wpdbbkp_sftp_status		=	'<label><b>Status</b>: Not Configured </label> ';

if($wpdbbkp_sftp_enabled==1 && !empty($wpdbbkp_sftp_details) && isset($wpdbbkp_sftp_details['host']) && isset($wpdbbkp_sftp_details['username']) && (isset($wpdbbkp_sftp_details['password']) ||(isset($wpdbbkp_sftp_details['sftp_key']) && isset($wpdbbkp_sftp_details['key_password']))))
{
	$wpdbbkp_sftp_status='<label><b>Status</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="Destination enabled"></span><span class="configured">Configured </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapsesftp">
				<h2><?php echo esc_html__('SSH/sFTP', 'wpdbbkp') ?> <?php echo $wpdbbkp_sftp_status;?> <span class="dashicons dashicons-admin-generic"></span></h2> 
			</a>
		</h4>
	</div>
	<div id="collapsesftp" class="panel-collapse collapse">
		<div class="panel-body">
			<p><?php echo esc_html__('SSH/sFTP Destination Define an sFTP destination connection. You can define destination which use sFTP.', 'wpdbbkp') ?></p>
			<?php
							/**
							 * Destination form.
							 *
							 * @package wpdbbkp
							 */

			require plugin_dir_path( __FILE__ ) . 'sftp-form.php'; // Include file.
			?>
		</div>		
	</div>
</div>
