<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */
$wpdb_dropboxtoken = get_option( 'wpdb_dropboxtoken',null );
$wpdb_dropbbox_code = get_option( 'wpdb_dropbbox_code',null );
$wpdbbkp_dropbox_status			=	'<span class="dashicons dashicons-warning" style="color:orange;font-size: 30px;" title="Destination not setup"></span> ';
if(!empty($wpdb_dropboxtoken) && !empty($wpdb_dropbbox_code))
{
	$wpdbbkp_dropbox_status='<span class="dashicons dashicons-yes-alt" style="color:green;font-size: 30px;" title="Destination enabled"></span>';
}
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapseIII">
				<h2>Dropbox <?php echo $wpdbbkp_dropbox_status;?></h2>
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
