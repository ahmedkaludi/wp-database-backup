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
 * Process the database backup and upload to Google Drive.
 *
 * @param array $args Arguments for the backup process.
 */
public static function wp_db_backup_completed( &$args ) {
    global $wp_filesystem;

    // Initialize the WordPress filesystem if it hasn't been initialized yet.
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();

    $auth_code     = get_option( 'wpdb_dest_google_authCode' );
    $client_id     = get_option( 'wpdb_dest_google_client_key' );
    $client_secret = get_option( 'wpdb_dest_google_secret_key' );

    if ( ! empty( $auth_code ) && ! empty( $client_id ) && ! empty( $client_secret ) ) {
        update_option( 'wpdbbkp_backupcron_current', 'Processing Google Backup', false );

        // Initialize the Google API client
        require_once 'google-api-php-client/src/Google_Client.php';
        require_once 'google-api-php-client/src/contrib/Google_DriveService.php';

        $client = new Google_Client();
        $client->setClientId( $client_id );
        $client->setClientSecret( $client_secret );
        $client->setRedirectUri( site_url() . '/wp-admin/admin.php?page=wp-database-backup&action=auth' );
        $client->setScopes( array( 'https://www.googleapis.com/auth/drive' ) );

        $service = new Google_DriveService( $client );

        // Exchange authorization code for access token
        if ( ! get_option( 'wpdb_google_drive_token' ) ) {
            $access_token = $client->authenticate( $auth_code );
            update_option( 'wpdb_google_drive_token', $access_token, false );
        } else {
            $access_token = get_option( 'wpdb_google_drive_token' );
        }
        $client->setAccessToken( $access_token );

        // Upload file to Google Drive
        $file = new Google_DriveFile();
        $file->setTitle( $args[0] );
        $file->setDescription( 'WP Database Backup : DB backup file - ' . site_url() );
        $file->setMimeType( 'application/gzip' );

        // Uploading chunked file so CPU and memory usage doesn't go 100%
        $chunk_size_bytes = 1 * 1024 * 1024;
        $media = new Google_MediaFileUpload( 'application/gzip', null, true, $chunk_size_bytes );
        $media->setFileSize( $wp_filesystem->size( $args[1] ) );

        $created_file = $service->files->insert(
            $file,
            array( 'mediaUpload' => $media )
        );

        $status = false;
        $file_handle = $wp_filesystem->get_contents( $args[1] );

        if ( $file_handle !== false ) {
            $offset = 0;
            while ( ! $status && $offset < strlen( $file_handle ) ) {
                $chunk = substr( $file_handle, $offset, $chunk_size_bytes );
                $status = $media->nextChunk( $created_file, $chunk );
                $offset += $chunk_size_bytes;
            }
        }

        $args[2] .= '<br>' . esc_html__( 'Upload Database Backup on Google Drive', 'wpdbbkp' );
        $args[4] .= 'Drive, ';
    }
}


}
