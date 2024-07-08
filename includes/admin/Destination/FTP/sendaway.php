<?php
/**
 * Destination ftp
 *
 * @package wpdbbkp
 */

// Set up variables.
$host          = get_option( 'backupbreeze_ftp_host' );
$user          = get_option( 'backupbreeze_ftp_user' );
$pass          = get_option( 'backupbreeze_ftp_pass' );
$subdir        = get_option( 'backupbreeze_ftp_subdir' );
$wp_upload_dir = wp_upload_dir();

$wp_upload_dir['basedir'] = str_replace( '\\', '/', $wp_upload_dir['basedir'] );
$remotefile               = $subdir . '/' . $filename;
$localfile                = trailingslashit( $wp_upload_dir['basedir'] . '/db-backup' ) . $filename;
if ( isset( $host ) && ! empty( $host ) && isset( $user ) && ! empty( $user ) && isset( $pass ) && ! empty( $pass ) ) {
	// See if port option is blank and set it to 21 if it isn't.
	if ( ! get_option( 'backupbreeze_ftp_port' ) ) {
		$port = '21';
	} else {
		$port = get_option( 'backupbreeze_ftp_port' );
	}
	$conn = ftp_connect( $host, $port );
	if ( $conn ) {
		$result = ftp_login( $conn, $user, $pass );
		if ( $result ) {
			// Switch to passive mode.
			ftp_pasv( $conn, true );
			// Upload file.
			$success = ftp_put( $conn, $remotefile, $localfile, FTP_BINARY );
			if ( $success ) {
				$args[2] = $args[2] . '<br> '.esc_html__('Upload Database Backup on FTP ','wpdbbkp'). $host;
				$args[4] .= 'FTP, ';
			}
		}
	}
	// Close connection to host.
	if(!is_bool($conn)){
		ftp_quit( $conn );
	}
	
}

