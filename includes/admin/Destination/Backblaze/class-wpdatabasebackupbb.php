<?php
/**
 * Destination BlackBlaze.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}




add_action( 'wp_db_backup_completed', array( 'WPDatabaseBackupBB', 'wp_db_backup_completed' ) );

/**
 * WPDatabaseBackupBB Class.
 *
 * @class WPDatabaseBackupBB
 */
class WPDatabaseBackupBB {

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {
		$destination_s3 = get_option( 'wp_db_backup_destination_bb' );
		if ( isset( $destination_s3 ) && 1 == $destination_s3 && get_option( 'wpdb_dest_bb_s3_bucket_host' ) && get_option( 'wpdb_dest_bb_s3_bucket' ) && get_option( 'wpdb_dest_bb_s3_bucket_key' ) && get_option( 'wpdb_dest_bb_s3_bucket_secret' ) ) {
			update_option('wpdbbkp_backupcron_current','Processing Backblaze S3 Backup', false);
			try {
		

				if ( ! function_exists( 'wpdbbkp_backblazeb2_s3_sendfile' ) ) {
					require_once 'BB.php';
				}

				$wpdb_dest_bb_s3_bucket_host = get_option( 'wpdb_dest_bb_s3_bucket_host',null);
				$wpdb_dest_bb_s3_bucket = get_option( 'wpdb_dest_bb_s3_bucket',null);
				$wpdb_dest_bb_s3_bucket_key = get_option( 'wpdb_dest_bb_s3_bucket_key',null);
				$wpdb_dest_bb_s3_bucket_secret = get_option( 'wpdb_dest_bb_s3_bucket_secret',null);

				// Check for CURL.
				if ( ! extension_loaded( 'curl' ) && ! dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
					$message_error = 'No Curl';
				}
				preg_match('/s3\.([a-zA-Z0-9-]+)\.backblazeb2\.com/', $wpdb_dest_bb_s3_bucket_host, $matches);
					if (isset($matches[1])) {
						$region = $matches[1];
					} 
				$ret = wpdbbkp_backblazeb2_s3_sendfile($wpdb_dest_bb_s3_bucket_host,$wpdb_dest_bb_s3_bucket,$region,$wpdb_dest_bb_s3_bucket_key,$wpdb_dest_bb_s3_bucket_secret,$args[1]);
				$args[2] = $args[2] .$ret;
				$args[4] = $args[4] .= 'Backblaze, ';
					
			} catch ( Exception $e ) {
				$args[2] = $args[2] . "<br>Failed to upload Database Backup on s3 bucket";
			}
		}
	}
	
}
