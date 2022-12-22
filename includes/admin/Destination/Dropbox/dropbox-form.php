<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */
$wpdb_dropboxtoken = get_option( 'wpdb_dropboxtoken',null );
$wpdb_dropbbox_code = get_option( 'wpdb_dropbbox_code',null );
$wpdbbkp_dropbox_status			=	'<label><b>Status</b>: Not Configured </label> ';
if(!empty($wpdb_dropboxtoken) && !empty($wpdb_dropbbox_code))
{
	$wpdbbkp_dropbox_status='<label><b>Status</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="Destination enabled"></span><span class="configured">Configured </span></label> ';
}
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapseIII">
				<h2>Dropbox <?php echo $wpdbbkp_dropbox_status;?> <span class="dashicons dashicons-admin-generic"></span></h2>
			</a>
		</h4>
	</div>
	<div id="collapseIII" class="panel-collapse collapse">
		<div class="panel-body">
			<?php
				require plugin_dir_path( __FILE__ ) . 'dropboxupload.php';
			?>
		</div>		
	</div>
</div>
