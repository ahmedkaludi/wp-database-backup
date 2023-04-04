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
if ( true === isset( $_POST['wpdb_amazon_s3'] ) && 'Y' === $_POST['wpdb_amazon_s3'] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_amazon_setting'] ) ) {
		die( '<br><br>Invalid form data. form request came from the somewhere else not current site!' );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdbbackup_update_amazon_setting'] ) ), 'wpdbbackup-update-amazon-setting' ) ) {
		die( '<br><br>Invalid form data. form request came from the somewhere else not current site! ' );
	}

	// Save the posted value in the database.
	if ( true === isset( $_POST['wpdb_dest_amazon_s3_bucket'] ) ) {
		update_option( 'wpdb_dest_amazon_s3_bucket', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_amazon_s3_bucket'] ) ) ) );
	}
	if ( true === isset( $_POST['wpdb_dest_amazon_s3_bucket_key'] ) ) {
		update_option( 'wpdb_dest_amazon_s3_bucket_key', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_amazon_s3_bucket_key'] ) ) ) );
	}
	if ( true === isset( $_POST['wpdb_dest_amazon_s3_bucket_secret'] ) ) {
		update_option( 'wpdb_dest_amazon_s3_bucket_secret', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wpdb_dest_amazon_s3_bucket_secret'] ) ) ) );
	}
	if ( isset( $_POST['wp_db_backup_destination_s3'] ) ) {
		update_option( 'wp_db_backup_destination_s3', 1 );
	} else {
		update_option( 'wp_db_backup_destination_s3', 0 );
	}
	// Put a "settings updated" message on the screen.
	$update_msg = '<div class="updated"><p><strong>Your amazon s3 setting has been saved.</strong></p></div>';
}
$wp_db_backup_destination_s3 = get_option( 'wp_db_backup_destination_s3',0);
$wpdb_dest_amazon_s3_bucket = get_option( 'wpdb_dest_amazon_s3_bucket',null);
$wpdb_dest_amazon_s3_bucket_key = get_option( 'wpdb_dest_amazon_s3_bucket_key',null);
$wpdb_dest_amazon_s3_bucket_secret = get_option( 'wpdb_dest_amazon_s3_bucket_secret',null);

$wpdbbkp_amazon_s3_status			=	'<label><b>Status</b>: Not Configured </label> ';
if($wp_db_backup_destination_s3==1 && !empty($wpdb_dest_amazon_s3_bucket) && !empty($wpdb_dest_amazon_s3_bucket_key) && !empty($wpdb_dest_amazon_s3_bucket_secret))
{
	$wpdbbkp_amazon_s3_status='<label><b>Status</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="Destination enabled"></span><span class="configured">Configured </span> </label> ';
}

?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapseAmazon">
				<h2><?php echo esc_html__('Amazon S3', 'wpdbbkp') ?> <?php echo $wpdbbkp_amazon_s3_status;?> <span class="dashicons dashicons-admin-generic"></span></h2>

			</a>
		</h4>
	</div>
	<div id="collapseAmazon" class="panel-collapse collapse">
		<div class="panel-body">
			<?php
			echo esc_html( $update_msg );

			if ( get_option( 'wpdb_dest_amazon_s3_bucket_key' ) && get_option( 'wpdb_dest_amazon_s3_bucket_secret' ) ) {

				try {
					if ( ! class_exists( 'S3' ) ) {
						require_once 'S3.php';
					}

					// AWS access info.
					if ( ! defined( 'AWSACCESSKEY' ) ) {
						define( 'AWSACCESSKEY', get_option( 'wpdb_dest_amazon_s3_bucket_key' ) );
					}
					if ( ! defined( 'AWSSECRETKEY' ) ) {
						define( 'AWSSECRETKEY', get_option( 'wpdb_dest_amazon_s3_bucket_secret' ) );
					}

					// Check for CURL.
					if ( ! extension_loaded( 'curl' ) && ! @dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
						echo "ERROR: CURL extension not loaded\n\n";
					}

					$s3     = new S3( AWSACCESSKEY, AWSSECRETKEY );
					$result = $s3->listBuckets();
					if ( get_option( 'wpdb_dest_amazon_s3_bucket' ) ) {
						if (!empty($result) && false === in_array( get_option( 'wpdb_dest_amazon_s3_bucket' ), $result, true ) ) {
							echo '<span class="label label-warning">Invalid bucket name or AWS details</span>';
						}
					}
				} catch ( Exception $e ) {
					echo '<span class="label label-warning">Invalid AWS details</span>';
				}
			}
			?>
			<p> <?php echo esc_html__('Back up WordPress database to Amazon S3.', 'wpdbbkp') ?></p>
			<p><?php echo esc_html__('Enter your Amazon S3 details for your offsite backup. Leave these blank for local backups OR Disable Amazon S3 Destination', 'wpdbbkp') ?></p>
			<form  class="form-group" name="amazons3" method="post" action="">

				<div class="row form-group">
					<label class="col-sm-2" for="wp_db_backup_destination_s3"><?php echo esc_html__('Enable Amazon S3 Destination:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="checkbox" id="wp_db_backup_destination_s3" <?php echo ( true === isset( $wp_db_backup_destination_s3 ) && 1 === $wp_db_backup_destination_s3 ) ? 'checked' : ''; ?> name="wp_db_backup_destination_s3">
				</div>

				</div>
				<input type="hidden" name="wpdb_amazon_s3" value="Y">
				<input name="wpdbbackup_update_amazon_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-amazon-setting' ) ); ?>" />
				<?php wp_nonce_field( 'wp-database-backup' ); ?>
				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_amazon_s3_bucket"><?php echo esc_html__('Bucket Name:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">

						<input type="text" id="wpdb_dest_amazon_s3_bucket" class="form-control" name="wpdb_dest_amazon_s3_bucket" value="<?php echo esc_html( get_option( 'wpdb_dest_amazon_s3_bucket' ) ); ?>" size="25" placeholder="Buket name">
						<a href="http://docs.aws.amazon.com/AmazonS3/latest/gsg/CreatingABucket.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_amazon_s3_bucket_key"><?php echo esc_html__('Key:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_amazon_s3_bucket_key" class="form-control" name="wpdb_dest_amazon_s3_bucket_key" value="<?php echo esc_html( get_option( 'wpdb_dest_amazon_s3_bucket_key' ) ); ?>" size="25" placeholder="your access key id">
						<a href="http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/AWSCredentials.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<div class="row form-group">
					<label class="col-sm-2" for="wpdb_dest_amazon_s3_bucket_secret"><?php echo esc_html__('Secret:', 'wpdbbkp') ?></label>
					<div class="col-sm-6">
						<input type="text" id="wpdb_dest_amazon_s3_bucket_secret" class="form-control" name="wpdb_dest_amazon_s3_bucket_secret" value="<?php echo esc_html( get_option( 'wpdb_dest_amazon_s3_bucket_secret' ) ); ?>" size="25" placeholder="your secret access key">
						<a href="http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/AWSCredentials.html" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
					</div>
				</div>

				<p><input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save' ); ?>" />&nbsp;
				</p>
			</form>

		</div>
	</div>
</div>
