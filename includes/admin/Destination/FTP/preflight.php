<?php
/**
 * Destination file.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Error checking.
 *
 * @param string $trouble - Trouble response.
 */
function backupbreeze_preflight_problem( $trouble ) {
	$error_log = $trouble;
}

// set up variables.
$host   = get_option( 'backupbreeze_ftp_host' );
$user   = get_option( 'backupbreeze_ftp_user' );
$pass   = get_option( 'backupbreeze_ftp_pass' );
$subdir = get_option( 'backupbreeze_ftp_subdir' );
if ( '' === $subdir ) {
	$subdir = '/';
}
//$remotefile = $subdir . '/' . $filename;

if ( $host ) {
	// If in WP Dashboard or Admin Panels.
	if ( is_admin() ) {
		// If user has WP manage options permissions.
		if ( current_user_can( 'manage_options' ) ) {
			// Connect to host ONLY if the 2 security conditions are valid / met.
			$conn = ftp_connect( $host );
			if ( ! $conn ) {
				$trouble = 'I could not connect to your FTP server.<br />Please check your FTP Host settings and try again (leave FTP Host BLANK for local backups).';
				backupbreeze_preflight_problem( $trouble );
			}
			$result = ftp_login( $conn, $user, $pass );
			if ( ! $result ) {
				$trouble = 'I could not log in to your FTP server.<br />Please check your FTP Username and Password, then try again.<br />For local backups, please leave the FTP Host option BLANK.';
				backupbreeze_preflight_problem( $trouble );
			}
			$success = ftp_chdir( $conn, $subdir );
			if ( ! $success ) {
				$trouble = 'I cannot change into the FTP subdirectory you specified. Does it exist?<br />You must create it first using an FTP client like FileZilla.<br />Please check and try again.';
				backupbreeze_preflight_problem( $trouble );
			}
		}
	}
}
