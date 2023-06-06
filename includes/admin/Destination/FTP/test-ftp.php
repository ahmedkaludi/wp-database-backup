<?php
/**
 * Destination test.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! current_user_can( 'manage_options' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * Test app.
 */
function backupbreeze_test_ftp() {

	// Now let's see if we can connect to the FTP repo.
	$host   = get_option( 'backupbreeze_ftp_host' );
	$user   = get_option( 'backupbreeze_ftp_user' );
	$pass   = get_option( 'backupbreeze_ftp_pass' );
	$subdir = get_option( 'backupbreeze_ftp_subdir' );
	if ( '' === $subdir ) {
		$subdir = '/';
	}

	if ( is_admin() ) {
		// If user has WP manage options permissions.
		if ( current_user_can( 'manage_options' ) ) {
			// Connect to host ONLY if the 2 security conditions are valid / met.
			$conn = ftp_connect( $host );
		}
	}

	if ( ! $conn ) {
		$trouble = 'I could not connect to your FTP server.<br />Please check your FTP Host and try again.';
		return $trouble;
	}

	$result = ftp_login( $conn, $user, $pass );
	if ( ! $result ) {
		$trouble = 'I could connect to the FTP server but I could not log in.<br />Please check your credentials and try again.';
		return $trouble;
	}

	$success = ftp_chdir( $conn, $subdir );
	if ( ! $success ) {
		$trouble = 'I can connect to the FTP server, but I cannot change into the FTP subdirectory you specified. <br />Is the path correct? Does the directory exist? Is it wrritable?<br />Please check using an FTP client like FileZilla.';
		return $trouble;
	}

	$trouble = 'OK';

	// Lose this connection.
	ftp_close( $conn );
	return $trouble;

}
