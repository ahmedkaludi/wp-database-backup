<?php
/**
 * Destination aws.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_db_backup_completed', array( 'WPDatabaseBackupS3', 'wp_db_backup_completed' ) );

/**
 * WPDatabaseBackupS3 Class.
 *
 * @class WPDatabaseBackupS3
 */
class WPDatabaseBackupS3 {

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {
		$destination_s3 = get_option( 'wp_db_backup_destination_s3' );
		if ( isset( $destination_s3 ) && 1 == $destination_s3 && get_option( 'wpdb_dest_amazon_s3_bucket' ) && get_option( 'wpdb_dest_amazon_s3_bucket_key' ) && get_option( 'wpdb_dest_amazon_s3_bucket_secret' ) ) {
			update_option('wpdbbkp_backupcron_current','Processing Amazon S3 Backup', false);
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
				if ( ! extension_loaded( 'curl' ) && ! dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
					$message_error = 'No Curl';
				}

				$s3          = new S3( AWSACCESSKEY, AWSSECRETKEY );
				$bucket_name = get_option( 'wpdb_dest_amazon_s3_bucket' );
				$result      = $s3->listBuckets();
				if ( get_option( 'wpdb_dest_amazon_s3_bucket' ) ) {
					if ( true === in_array( get_option( 'wpdb_dest_amazon_s3_bucket' ), $result ) ) { // phpcs:ignore
						if ( $s3->putObjectFile( $args[1], $bucket_name, baseName( $args[1] ), S3::ACL_PUBLIC_READ ) ) {
							$args[2] = $args[2] . '<br> '.esc_html__('Upload Database Backup on s3 bucket','wpdbbkp') . $bucket_name;
						} else {
							$args[2] = $args[2] . '<br>'.esc_html__('Failed to upload Database Backup on s3 bucket','wpdbbkp') . $bucket_name;
						}
					} else {
						$args[2] = $args[2] . '<br>'.esc_html__('Invalid bucket name or AWS details','wpdbbkp');
						$args[4] = $args[4] .= 'S3, ';
					}
				}
			} catch ( Exception $e ) {
				$error_msg = 'Error log.';
			}
		}
	}

}
