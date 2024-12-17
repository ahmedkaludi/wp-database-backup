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
if ( true === isset( $_POST['wpdb_bb_s3'] ) && 'Y' === $_POST['wpdb_bb_s3'] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_bb_setting'] ) ) {
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	if ( ! wp_verify_nonce( wp_unslash( $_POST['wpdbbackup_update_bb_setting'] ) , 'wpdbbackup-update-bb-setting' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}

	// Save the posted value in the database.
	if ( true === isset( $_POST['wpdb_dest_bb_s3_bucket_host'] ) ) {
		update_option( 'wpdb_dest_bb_s3_bucket_host', wp_db_filter_data( sanitize_url( wp_unslash( $_POST['wpdb_dest_bb_s3_bucket_host'] ) ) ), false );
	}
	if ( true === isset( $_POST['wpdb_dest_bb_s3_bucket'] ) ) {
		update_option( 'wpdb_dest_bb_s3_bucket', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_bb_s3_bucket'] ) ) ), false );
	}
	if ( true === isset( $_POST['wpdb_dest_bb_s3_bucket_key'] ) ) {
		update_option( 'wpdb_dest_bb_s3_bucket_key', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_bb_s3_bucket_key'] ) ) ) , false);
	}
	if ( true === isset( $_POST['wpdb_dest_bb_s3_bucket_secret'] ) ) {
		update_option( 'wpdb_dest_bb_s3_bucket_secret', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_bb_s3_bucket_secret'] ) ) ), false );
	}
	if ( isset( $_POST['wp_db_backup_destination_bb'] ) ) {
		update_option( 'wp_db_backup_destination_bb', 1 , false);
	} else {
		update_option( 'wp_db_backup_destination_bb', 0 , false);
	}

	if ( isset( $_POST['wp_db_incremental_backup'] ) ) {
		update_option( 'wp_db_incremental_backup', 1 , false);
	} else {
		update_option( 'wp_db_incremental_backup', 0 , false);
	}
	// Put a "settings updated" message on the screen.
	$update_msg = esc_html__('Your Blackblaze S3 setting has been saved.' , 'wpdbbkp');
}
$wp_db_backup_destination_bb = get_option( 'wp_db_backup_destination_bb',0);
$wpdb_dest_bb_s3_bucket_host = get_option( 'wpdb_dest_bb_s3_bucket_host',null);
$wpdb_dest_bb_s3_bucket = get_option( 'wpdb_dest_bb_s3_bucket',null);
$wpdb_dest_bb_s3_bucket_key = get_option( 'wpdb_dest_bb_s3_bucket_key',null);
$wpdb_dest_bb_s3_bucket_secret = get_option( 'wpdb_dest_bb_s3_bucket_secret',null);
$incremental_backup = get_option( 'wp_db_incremental_backup', 0 );
if ( 1 === (int) $wp_db_incremental_backup ) {
	$incremental_backup = 'checked';
} else {
	$incremental_backup = '';
}

$wpdbbkp_bb_s3_status			=	'<label><b>'.esc_html__('Status', 'wpdbbkp').'</b>: '.esc_html__('Not Configured', 'wpdbbkp').' </label> ';

if($wp_db_backup_destination_bb == 1 && !empty($wpdb_dest_bb_s3_bucket) && !empty($wpdb_dest_bb_s3_bucket_key) && !empty($wpdb_dest_bb_s3_bucket_secret) && !empty($wpdb_dest_bb_s3_bucket_host))
{
	$wpdbbkp_bb_s3_status ='<label><b>'.esc_html__('Status', 'wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled', 'wpdbbkp').'"></span><span class="configured">'.esc_html__('Configured', 'wpdbbkp').' </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapsebb">
				<h2><?php echo esc_html__('Blackblaze', 'wpdbbkp') ?> <?php echo wp_kses_post($wpdbbkp_bb_s3_status);?> <span class="dashicons dashicons-admin-generic"></span></h2>

			</a>
		</h4>
	</div>
	<div id="collapsebb" class="panel-collapse collapse">
		<div class="panel-body">
			<?php
			if($update_msg){
				echo '<div class="updated"><p><strong>'.esc_html( $update_msg ).'</strong></p></div>';
			}
			

			if ( get_option( 'wpdb_dest_bb_s3_bucket_key' ) && get_option( 'wpdb_dest_bb_s3_bucket_secret' ) ) {

				try {
					
					

					// Check for CURL.
					if ( ! extension_loaded( 'curl' ) && ! @dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
						echo esc_html__("ERROR: CURL extension not loaded\n\n", 'wpdbbkp');
					}
			
					 
						$b2_authorize_url = "https://api.backblazeb2.com/b2api/v2/b2_authorize_account";
						$credentials = base64_encode($wpdb_dest_bb_s3_bucket_key . ":" . $wpdb_dest_bb_s3_bucket_secret);

						// Authorize account
						$response = wp_remote_get($b2_authorize_url, array(
							'headers' => array(
								'Authorization' => 'Basic ' . $credentials
							),
							'timeout' => 60 // Extend timeout
						));

						if(!is_wp_error($response)){
							$body = wp_remote_retrieve_body($response);
							$data = json_decode($body);
						}
					
						
					if(is_wp_error($response) || empty($data->authorizationToken)){
						echo '<span class="label label-warning">'.esc_html__( 'Invalid bucket name or Backblaze details' ,'wpdbbkp').'</span>';
					}
					
				} catch ( Exception $e ) {
					echo '<span class="label label-warning">'.esc_html__( 'Invalid  details' ,'wpdbbkp').'</span>';
				}
			}
			?>
			<p> <?php echo esc_html__('Back up WordPress to Blackblaze.', 'wpdbbkp') ?></p>
			<p><?php echo esc_html__('Enter your Blackblaze details for your offsite backup. Leave these blank for local backups OR Disable Blackblaze Destination', 'wpdbbkp') ?></p>
			<form  class="form-group" name="Blackblazes3" method="post" action="">

				<div class="row form-group">
					<label class="col-sm-2" for="wp_db_backup_destination_bb"><?php echo esc_html__('Enable Blackblaze Destination', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="checkbox" id="wp_db_backup_destination_bb" <?php echo (  1 == $wp_db_backup_destination_bb ) ? 'checked' : ''; ?> name="wp_db_backup_destination_bb">
				</div>

				</div>
				<input type="hidden" name="wpdb_bb_s3" value="Y">
				<input name="wpdbbackup_update_bb_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-bb-setting' ) ); ?>" />
				<?php wp_nonce_field( 'wp-database-backup' ); ?>
				<div class="row form-group conditional_fields">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket"><?php echo esc_html__('Bucket Endpoint', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_dest_bb_s3_bucket_host" class="form-control" name="wpdb_dest_bb_s3_bucket_host" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket_host' ) ); ?>" size="25" placeholder="<?php esc_attr_e('Endpoint : https://s3.us-west-002.backblazeb2.com','wpdbbkp');?>">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>
				<div class="row form-group conditional_fields">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket"><?php echo esc_html__('Bucket ID', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_dest_bb_s3_bucket" class="form-control" name="wpdb_dest_bb_s3_bucket" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket' ) ); ?>" size="25" placeholder="<?php esc_attr_e('Bucket ID', 'wpdbbkp');?>">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group conditional_fields">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket_key"><?php echo esc_html__('Key', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_bb_s3_bucket_key" class="form-control" name="wpdb_dest_bb_s3_bucket_key" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket_key' ) ); ?>" size="25" placeholder="<?php esc_attr_e('your access key id', 'wpdbbkp');?>">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group conditional_fields">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket_secret"><?php echo esc_html__('Secret', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_bb_s3_bucket_secret" class="form-control" name="wpdb_dest_bb_s3_bucket_secret" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket_secret' ) ); ?>" size="25" placeholder="<?php esc_attr_e('your secret access key', 'wpdbbkp');?>">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group conditional_fields">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket"><?php echo esc_html__('Enable Incremental backup', 'wpdbbkp') ?></label>
					<div class="col-sm-10">

					<input type="checkbox" <?php echo esc_attr( $incremental_backup ); ?> name="wp_db_incremental_backup"><br><?php echo esc_html__('Only updated files will be backedup after first backup is complete. This feature is currently available for  Blackblaze backup method', 'wpdbbkp') ?>
					</div>
					
				</div>

			

				<p><input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save' , 'wpdbbkp' ); ?>" />&nbsp;
				</p>
			</form>
			<script>
				jQuery(document).ready(function(){
					if(jQuery('#wp_db_backup_destination_bb').is(':checked')){
						jQuery('.conditional_fields').show();
					}else{
						jQuery('.conditional_fields').hide();
					}
					jQuery('#wp_db_backup_destination_bb').change(function(){
						if(jQuery(this).is(':checked')){
							jQuery('.conditional_fields').show();
						}else{
							jQuery('.conditional_fields').hide();
						}
					});
				});
			</script>

		</div>
	</div>
</div>
