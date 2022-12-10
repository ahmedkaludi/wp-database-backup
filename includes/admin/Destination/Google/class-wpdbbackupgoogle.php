<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_db_backup_completed', array( 'WPDBBackupGoogle', 'wp_db_backup_completed' ) );

/**
 * WPDBBackupGoogle Class.
 *
 * @class WPDBBackupGoogle
 */
class WPDBBackupGoogle {

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {

		$auth_code     = get_option( 'wpdb_dest_google_authCode' );
		$client_id     = get_option( 'wpdb_dest_google_client_key' );
		$client_secret = get_option( 'wpdb_dest_google_secret_key' );

		if ( ! empty( $auth_code ) && ! empty( $client_id ) && ! empty( $client_secret ) ) {
			set_time_limit( 0 );
			require_once 'google-api-php-client/src/Google_Client.php';
			require_once 'google-api-php-client/src/contrib/Google_DriveService.php';
			$client = new Google_Client();
			// Get your credentials from the APIs Console.
			$client->setClientId( $client_id );
			$client->setClientSecret( $client_secret );
			$client->setRedirectUri( site_url() . '/wp-admin/tools.php?page=wp-database-backup&action=auth' );
			$client->setScopes( array( 'https://www.googleapis.com/auth/drive' ) );
			$service = new Google_DriveService( $client );
			// Exchange authorisation code for access token.
			if ( ! get_option( 'wpdb_google_drive_token' ) ) {
				// Save token for future use.
				$access_token = $client->authenticate( $auth_code );
				update_option( 'wpdb_google_drive_token', $access_token );
			} else {
				$access_token = get_option( 'wpdb_google_drive_token' );
			}
			$client->setAccessToken( $access_token );
			// Upload file to Google Drive.
			$file = new Google_DriveFile();
			$file->setTitle( $args[0] );
			$file->setDescription( 'WP Database Backup : database backup file-' . site_url() );
			$file->setMimeType( 'application/gzip' );
			$data         = $wp_filesystem->get_contents( $args[1] );
			$created_file = $service->files->insert(
				$file,
				array(
					'data'     => $data,
					'mimeType' => 'application/gzip',
				)
			);
			$args[2]      = $args[2] . '<br> Upload Database Backup on google drive';
			$args[4]      = $args[4] .= 'Drive, ';
			// Process response here.
		}
	}

}
