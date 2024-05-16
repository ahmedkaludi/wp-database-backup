<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require 'vendor/autoload.php';
use Aws\S3\S3Client;

$update_msg = '';
if ( true === isset( $_POST['wpdb_bb_s3'] ) && 'Y' === $_POST['wpdb_bb_s3'] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_bb_setting'] ) ) {
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	if ( ! wp_verify_nonce( $_POST['wpdbbackup_update_bb_setting'] , 'wpdbbackup-update-bb-setting' ) ) {
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
	// Put a "settings updated" message on the screen.
	$update_msg = 'Your Blackblaze S3 setting has been saved.';
}
$wp_db_backup_destination_bb = get_option( 'wp_db_backup_destination_bb',0);
$wpdb_dest_bb_s3_bucket_host = get_option( 'wpdb_dest_bb_s3_bucket_host',null);
$wpdb_dest_bb_s3_bucket = get_option( 'wpdb_dest_bb_s3_bucket',null);
$wpdb_dest_bb_s3_bucket_key = get_option( 'wpdb_dest_bb_s3_bucket_key',null);
$wpdb_dest_bb_s3_bucket_secret = get_option( 'wpdb_dest_bb_s3_bucket_secret',null);

$wpdbbkp_bb_s3_status			=	'<label><b>Status</b>: Not Configured </label> ';

if($wp_db_backup_destination_bb == 1 && !empty($wpdb_dest_bb_s3_bucket) && !empty($wpdb_dest_bb_s3_bucket_key) && !empty($wpdb_dest_bb_s3_bucket_secret) && !empty($wpdb_dest_bb_s3_bucket_host))
{
	$wpdbbkp_bb_s3_status='<label><b>Status</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="Destination enabled"></span><span class="configured">Configured </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapsebb">
				<h2><?php echo esc_html__('Blackblaze S3', 'wpdbbkp') ?> <?php echo $wpdbbkp_bb_s3_status;?> <span class="dashicons dashicons-admin-generic"></span></h2>

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
						echo "ERROR: CURL extension not loaded\n\n";
					}
			
					preg_match('/s3\.([a-zA-Z0-9-]+)\.backblazeb2\.com/', $wpdb_dest_bb_s3_bucket_host, $matches);
					if (isset($matches[1])) {
						$region = $matches[1];
					} 
					$s3Client = new S3Client([
						'version' => 'latest',
						'region'  => $region,
						'endpoint' => $wpdb_dest_bb_s3_bucket_host, // Adjust based on your B2 endpoint
						'credentials' => [
							'key'    => $wpdb_dest_bb_s3_bucket_key,
							'secret' => $wpdb_dest_bb_s3_bucket_secret,
						],
					]);
					if(empty($s3Client)){
						echo '<span class="label label-warning">Invalid bucket name or AWS details</span>';
					}
					
				} catch ( Exception $e ) {
					echo '<span class="label label-warning">Invalid  details</span>';
				}
			}
			?>
			<p> <?php echo esc_html__('Back up WordPress database to Blackblaze S3.', 'wpdbbkp') ?></p>
			<p><?php echo esc_html__('Enter your Blackblaze S3 details for your offsite backup. Leave these blank for local backups OR Disable Blackblaze S3 Destination', 'wpdbbkp') ?></p>
			<form  class="form-group" name="Blackblazes3" method="post" action="">

				<div class="row form-group">
					<label class="col-sm-2" for="wp_db_backup_destination_bb"><?php echo esc_html__('Enable Blackblaze S3 Destination:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="checkbox" id="wp_db_backup_destination_bb" <?php echo (  1 == $wp_db_backup_destination_bb ) ? 'checked' : ''; ?> name="wp_db_backup_destination_bb">
				</div>

				</div>
				<input type="hidden" name="wpdb_bb_s3" value="Y">
				<input name="wpdbbackup_update_bb_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-bb-setting' ) ); ?>" />
				<?php wp_nonce_field( 'wp-database-backup' ); ?>
				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket"><?php echo esc_html__('Bucket Endpoint:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_dest_bb_s3_bucket_host" class="form-control" name="wpdb_dest_bb_s3_bucket_host" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket_host' ) ); ?>" size="25" placeholder="Endpoint : https://s3.us-west-002.backblazeb2.com">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>
				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket"><?php echo esc_html__('Bucket Name:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_dest_bb_s3_bucket" class="form-control" name="wpdb_dest_bb_s3_bucket" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket' ) ); ?>" size="25" placeholder="Bucket name">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket_key"><?php echo esc_html__('Key:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_bb_s3_bucket_key" class="form-control" name="wpdb_dest_bb_s3_bucket_key" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket_key' ) ); ?>" size="25" placeholder="your access key id">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_bb_s3_bucket_secret"><?php echo esc_html__('Secret:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_bb_s3_bucket_secret" class="form-control" name="wpdb_dest_bb_s3_bucket_secret" value="<?php echo esc_html( get_option( 'wpdb_dest_bb_s3_bucket_secret' ) ); ?>" size="25" placeholder="your secret access key">
						<a href="https://www.backblaze.com/apidocs/introduction-to-the-s3-compatible-api" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<p><input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save' , 'wpdbbkp' ); ?>" />&nbsp;
				</p>
			</form>

		</div>
	</div>
</div>