<?php
/**
 * Destination file.
 *
 * @package wpdbbkp
 */

$wpdbbkp_ftp_enabled	=	get_option( 'wp_db_backup_destination_FTP',null );
$wpdbbkp_ftp_host		=	get_option( 'backupbreeze_ftp_host',null );
$wpdbbkp_ftp_user		=	get_option( 'backupbreeze_ftp_user',null );
$wpdbbkp_ftp_pass		=	get_option( 'backupbreeze_ftp_pass',null );
$wpdbbkp_ftp_status		=	'<label><b>'.esc_html__('Status','wpdbbkp').'</b>: '.esc_html__('Not Configured','wpdbbkp').' </label> ';
if($wpdbbkp_ftp_enabled==1 && !empty($wpdbbkp_ftp_host) && !empty($wpdbbkp_ftp_user) && !empty($wpdbbkp_ftp_pass))
{
	$wpdbbkp_ftp_status='<label><b>'.esc_html__('Status','wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled','wpdbbkp').'"></span><span class="configured">'.esc_html__('Configured','wpdbbkp').' </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapseI">
				<h2><?php echo esc_html__('FTP/FTPS', 'wpdbbkp') ?> <?php echo wp_kses_post($wpdbbkp_ftp_status);?> <span class="dashicons dashicons-admin-generic"></span></h2> 
			</a>
		</h4>
	</div>
	<div id="collapseI" class="panel-collapse collapse">
		<div class="panel-body">
			<p><?php echo esc_html__('FTP/FTPS Destination Define an FTP destination connection. You can define destination which use FTP.', 'wpdbbkp') ?></p>
			<?php
							/**
							 * Destination form.
							 *
							 * @package wpdbbkp
							 */

			require plugin_dir_path( __FILE__ ) . 'ftp-form.php'; // Include file.
			?>
		</div>		
	</div>
</div>
