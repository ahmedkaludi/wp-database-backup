<?php
/**
 * Destination Generic S3 form.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$update_msg = '';
if ( true === isset( $_POST['wpdb_generics3'] ) && 'Y' === $_POST['wpdb_generics3'] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_generics3_setting'] ) ) {
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	if ( ! wp_verify_nonce( wp_unslash( $_POST['wpdbbackup_update_generics3_setting']) , 'wpdbbackup-update-generics3-setting' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}

	// Save the posted value in the database.
	if ( true === isset( $_POST['wpdb_dest_generics3_endpoint'] ) ) {
		update_option( 'wpdb_dest_generics3_endpoint', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_generics3_endpoint'] ) ) ), false );
	}
	if ( true === isset( $_POST['wpdb_dest_generics3_bucket'] ) ) {
		update_option( 'wpdb_dest_generics3_bucket', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_generics3_bucket'] ) ) ), false );
	}
	if ( true === isset( $_POST['wpdb_dest_generics3_bucket_key'] ) ) {
		update_option( 'wpdb_dest_generics3_bucket_key', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_generics3_bucket_key'] ) ) ) , false);
	}
	if ( true === isset( $_POST['wpdb_dest_generics3_bucket_secret'] ) ) {
		update_option( 'wpdb_dest_generics3_bucket_secret', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_generics3_bucket_secret'] ) ) ), false );
	}
	if ( isset( $_POST['wp_db_backup_destination_generics3'] ) ) {
		update_option( 'wp_db_backup_destination_generics3', 1 , false);
	} else {
		update_option( 'wp_db_backup_destination_generics3', 0 , false);
	}
	// Put a "settings updated" message on the screen.
	$update_msg = '<div class="updated"><p><strong>Your Generic S3 setting has been saved.</strong></p></div>';
}

$wp_db_backup_destination_generics3 = get_option( 'wp_db_backup_destination_generics3',0);
$wpdb_dest_generics3_endpoint = get_option( 'wpdb_dest_generics3_endpoint',null);
$wpdb_dest_generics3_bucket = get_option( 'wpdb_dest_generics3_bucket',null);
$wpdb_dest_generics3_bucket_key = get_option( 'wpdb_dest_generics3_bucket_key',null);
$wpdb_dest_generics3_bucket_secret = get_option( 'wpdb_dest_generics3_bucket_secret',null);

$wpdbbkp_generics3_status = '<label><b>'.esc_html__('Status','wpdbbkp').'</b>: '.esc_html__('Not Configured','wpdbbkp').' </label> ';
if($wp_db_backup_destination_generics3==1 && !empty($wpdb_dest_generics3_endpoint) && !empty($wpdb_dest_generics3_bucket) && !empty($wpdb_dest_generics3_bucket_key) && !empty($wpdb_dest_generics3_bucket_secret))
{
	$wpdbbkp_generics3_status='<label><b>'.esc_html__('Status','wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled','wpdbbkp').'"></span><span class="configured">'.esc_html__('Configured','wpdbbkp').' </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapseGenericS3">
				<h2><?php echo esc_html__('Generic S3 Compatible', 'wpdbbkp') ?> <?php echo wp_kses_post($wpdbbkp_generics3_status);?></h2>

			</a>
		</h4>
	</div>
	<div id="collapseGenericS3" class="panel-collapse collapse">
		<div class="panel-body">
			<?php
			echo esc_html( $update_msg );

			if ( get_option( 'wpdb_dest_generics3_bucket_key' ) && get_option( 'wpdb_dest_generics3_bucket_secret' ) && get_option( 'wpdb_dest_generics3_endpoint' ) ) {

				try {
					if ( ! class_exists( 'WPDatabaseBackupGenericS3Client' ) ) {
						require_once plugin_dir_path( __FILE__ ) . 'class-wpdatabasebackupgenerics3.php';
					}

					// Generic S3 access info.
					if ( ! defined( 'GENERICS3ACCESSKEY' ) ) {
						define( 'GENERICS3ACCESSKEY', get_option( 'wpdb_dest_generics3_bucket_key' ) );
					}
					if ( ! defined( 'GENERICS3SECRETKEY' ) ) {
						define( 'GENERICS3SECRETKEY', get_option( 'wpdb_dest_generics3_bucket_secret' ) );
					}
					if ( ! defined( 'GENERICS3ENDPOINT' ) ) {
						define( 'GENERICS3ENDPOINT', get_option( 'wpdb_dest_generics3_endpoint' ) );
					}

					// Check for CURL.
					if ( ! extension_loaded( 'curl' ) && ! @dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
						echo "ERROR: CURL extension not loaded\n\n";
					}

					$s3 = new WPDatabaseBackupGenericS3Client( GENERICS3ACCESSKEY, GENERICS3SECRETKEY, GENERICS3ENDPOINT );
					$result = $s3->listBuckets();

					if ( ! empty( $result ) && get_option( 'wpdb_dest_generics3_bucket' ) ) {
						$bucket_found = false;
						foreach ( $result as $bucket ) {
							if ( $bucket['name'] === get_option( 'wpdb_dest_generics3_bucket' ) ) {
								$bucket_found = true;
								break;
							}
						}
						if ( ! $bucket_found ) {
							echo '<span class="label label-warning">'.esc_html__('Bucket not found or access denied','wpdbbkp').'</span>';
						}
					}
				} catch ( Exception $e ) {
					echo '<span class="label label-warning">'.esc_html__('Invalid Generic S3 details','wpdbbkp').'</span>';
				}
			}
			?>
			<p> <?php echo esc_html__('Back up WordPress database to any S3-compatible storage provider.', 'wpdbbkp') ?></p>
			<p><?php echo esc_html__('Enter your S3-compatible storage details for your offsite backup. This works with AWS S3, DigitalOcean Spaces, Linode Object Storage, Backblaze B2, MinIO, and other S3-compatible services. Leave these blank for local backups OR Disable Generic S3 Destination', 'wpdbbkp') ?></p>
			<form  class="form-group" name="generics3" method="post" action="">

				<div class="row form-group">
					<label class="col-sm-2" for="wp_db_backup_destination_generics3"><?php echo esc_html__('Enable Generic S3 Destination', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="checkbox" id="wp_db_backup_destination_generics3" <?php echo ( true == isset( $wp_db_backup_destination_generics3 ) && 1 == $wp_db_backup_destination_generics3 ) ? 'checked' : ''; ?> name="wp_db_backup_destination_generics3">
				</div>

				</div>
				<input type="hidden" name="wpdb_generics3" value="Y">
				<input name="wpdbbackup_update_generics3_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-generics3-setting' ) ); ?>" />
				<?php wp_nonce_field( 'wp-database-backup' ); ?>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_generics3_endpoint"><?php echo esc_html__('Endpoint URL', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_generics3_endpoint" class="form-control" name="wpdb_dest_generics3_endpoint" value="<?php echo esc_html( get_option( 'wpdb_dest_generics3_endpoint' ) ); ?>" size="25" placeholder="<?php esc_attr_e('https://s3.region.amazonaws.com or https://region.digitaloceanspaces.com','wpdbbkp');?>">
						<a href="https://docs.aws.amazon.com/general/latest/gr/s3.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
						<p class="description"><?php echo esc_html__('Enter the endpoint URL for your S3-compatible storage. For AWS S3: https://s3.region.amazonaws.com', 'wpdbbkp') ?></p>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_generics3_bucket"><?php echo esc_html__('Bucket Name', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_dest_generics3_bucket" class="form-control" name="wpdb_dest_generics3_bucket" value="<?php echo esc_html( get_option( 'wpdb_dest_generics3_bucket' ) ); ?>" size="25" placeholder="<?php esc_attr_e('your-bucket-name','wpdbbkp');?>">
						<a href="https://docs.aws.amazon.com/AmazonS3/latest/gsg/CreatingABucket.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_generics3_bucket_key"><?php echo esc_html__('Access Key', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_generics3_bucket_key" class="form-control" name="wpdb_dest_generics3_bucket_key" value="<?php echo esc_html( get_option( 'wpdb_dest_generics3_bucket_key' ) ); ?>" size="25" placeholder="<?php esc_attr_e('your access key id','wpdbbkp');?>">
						<a href="https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_generics3_bucket_secret"><?php echo esc_html__('Secret Key', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_generics3_bucket_secret" class="form-control" name="wpdb_dest_generics3_bucket_secret" value="<?php echo esc_html( get_option( 'wpdb_dest_generics3_bucket_secret' ) ); ?>" size="25" placeholder="<?php esc_attr_e('your secret access key','wpdbbkp');?>">
						<a href="https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<p><input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save' , 'wpdbbkp' ); ?>" />&nbsp;
				<input type="button" id="test-generics3-connection" class="btn btn-info" value="<?php esc_attr_e( 'Test Connection' , 'wpdbbkp' ); ?>" />
				</p>
				<div id="generics3-test-results" style="display: none; margin-top: 10px;"></div>
			</form>

			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#test-generics3-connection').on('click', function() {
					var $button = $(this);
					var $results = $('#generics3-test-results');

					$button.prop('disabled', true).val('Testing...');
					$results.hide().html('<div class="alert alert-info">Testing connection...</div>').show();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'test_generics3_connection',
							nonce: '<?php echo esc_js( wp_create_nonce( 'test_generics3_connection' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								var messages = response.data.messages.join('<br>');
								$results.html('<div class="alert alert-success"><strong>Connection Test Results:</strong><br>' + messages + '</div>');
							} else {
								var messages = response.data.messages ? response.data.messages.join('<br>') : 'Unknown error';
								$results.html('<div class="alert alert-danger"><strong>Connection Test Failed:</strong><br>' + messages + '</div>');
							}
						},
						error: function() {
							$results.html('<div class="alert alert-danger">Failed to test connection. Please check your settings and try again.</div>');
						},
						complete: function() {
							$button.prop('disabled', false).val('Test Connection');
						}
					});
				});
			});
			</script>

		</div>
	</div>
</div>
