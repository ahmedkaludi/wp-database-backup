<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */
$wpdb_dropboxtoken = get_option( 'wpdb_dropboxtoken',null );
$wpdbbkp_dropbox_status_escaped			=	'<label><b>'.esc_html__('Status', 'wpdbbkp').'</b>: '.esc_html__('Not Configured', 'wpdbbkp').' </label> ';
if(!empty($wpdb_dropboxtoken))
{
	$wpdbbkp_dropbox_status_escaped='<label><b>'.esc_html__('Status', 'wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled', 'wpdbbkp').'"></span><span class="configured">'.esc_html__('Configured', 'wpdbbkp').' </span></label> ';
}
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapseIII">
				<h2><?php esc_html_e('Dropbox','wpdbbkp');?> <?php echo wp_kses_post($wpdbbkp_dropbox_status_escaped);?> <span class="dashicons dashicons-admin-generic"></span></h2>
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
