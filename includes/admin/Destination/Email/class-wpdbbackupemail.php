<?php
/**
 * Destination email.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_db_backup_completed', array( 'WPDBBackupEmail', 'wp_db_backup_completed' ), 11 );

/**
 * WPDBBackupEmail Class.
 *
 * @class WPDBBackupEmail
 */
class WPDBBackupEmail {

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {
		
		$destination_email = get_option( 'wp_db_backup_destination_Email' );
		if ( isset( $destination_email ) && 1 === (int) $destination_email && get_option( 'wp_db_backup_email_id' ) ) {
			update_option('wpdbbkp_backupcron_current','Processing Email Backup', false);
			$to                     = sanitize_email( get_option( 'wp_db_backup_email_id' ) );
			$subject                = 'Database Backup (' . get_bloginfo( 'name' ) . ')';
			$filename               = $args[0];
			$filesze                = $args[3];
			$site_url               = site_url();
			$log_message_attachment = '';
			$message                = '';

			include 'template-email-notification.php';

			$headers                            = array( 'Content-Type: text/html; charset=UTF-8' );
			$wp_db_backup_email_attachment_file = get_option( 'wp_db_backup_email_attachment' );
			if ( 'yes' === $wp_db_backup_email_attachment_file && $filesze <= 209700000 ) {
				$attachments            = $args[1];
				$log_message_attachment = ' with attached backup file.';
			} else {
				$attachments = '';
			}
			if ( wp_mail( $to, $subject, $message, $headers, $attachments ) ) {
				$args[4] .= 'Email, ';
			}
			$log_message               = '<b>Send Backup Mail to</b>:' . $to;
			$log_message              .= $log_message_attachment;
			$wp_db_remove_local_backup = get_option( 'wp_db_remove_local_backup' );
			if ( 1 === (int) $wp_db_remove_local_backup ) {
				$log_message .= ' Removed local backup file.';
			}
				$args[2] = $args[2] . ' <br>' . $log_message;
		}
	}

	/**
	 * Run after complete backup.
	 *
	 * @param bool $bytes - bytes details.
	 * @param int  $precision - precision details.
	 */
	public static function wp_db_backup_format_bytes( $bytes, $precision = 2 ) {
		$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes  = max( $bytes, 0 );
		$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow    = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

}
