<?php
/**
 * Destination ftp
 *
 * @package wpdbbkp
 */

// Set up variables.

require __DIR__ . '/vendor/autoload.php';
use phpseclib3\Net\SFTP;

$wp_upload_dir = wp_upload_dir();
$wp_upload_dir['basedir'] = str_replace( '\\', '/', $wp_upload_dir['basedir'] );
$remotefile               = $directory . '/' . $filename;
$localfile                = trailingslashit( $wp_upload_dir['basedir'] . '/db-backup' ) . $filename;
if ( $result ) {
	$success = $sftp->put($remotefile, $localfile, SFTP::SOURCE_LOCAL_FILE | SFTP::RESUME_START);
	if ( $success ) {
		$args[2] = $args[2] . '<br> '.esc_html__('Upload Database Backup on SFTP','wpdbbkp') . $host;
		$args[4] .= 'SFTP, ';
	}
}
// Close connection to host.
if($sftp){
	$sftp->disconnect();
}

