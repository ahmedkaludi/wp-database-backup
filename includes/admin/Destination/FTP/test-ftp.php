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
 * Test FTP connection.
 *
 * @return string
 */
function wpdbbkp_test_ftp() {

	$wpdbbkp_host   = get_option( 'backupbreeze_ftp_host' );
	$wpdbbkp_user   = get_option( 'backupbreeze_ftp_user' );
	$wpdbbkp_pass   = get_option( 'backupbreeze_ftp_pass' );
	$wpdbbkp_subdir = get_option( 'backupbreeze_ftp_subdir' );
	$wpdbbkp_port   = get_option( 'backupbreeze_ftp_port' );
	$wpdbbkp_port   = ! empty( $wpdbbkp_port ) ? (int) $wpdbbkp_port : 21;

	// Normalize host.
	$wpdbbkp_host = preg_replace( '#^(ftps?|sftp)://#i', '', trim( (string) $wpdbbkp_host ) );
	$wpdbbkp_host = untrailingslashit( $wpdbbkp_host );
	$wpdbbkp_host = preg_replace( '#/.*$#', '', $wpdbbkp_host );

	// Keep subdirectory relative for chrooted / custom FTP logins.
	if ( '' === (string) $wpdbbkp_subdir || '/' === $wpdbbkp_subdir ) {
		$wpdbbkp_subdir = '.';
	} else {
		$wpdbbkp_subdir = trim( str_replace( '\\', '/', $wpdbbkp_subdir ), '/' );
	}

	$wpdbbkp_conn = false;
	if ( is_admin() && current_user_can( 'manage_options' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$wpdbbkp_conn = @ftp_connect( $wpdbbkp_host, $wpdbbkp_port, 90 );
	}

	if ( ! $wpdbbkp_conn ) {
		return esc_html__( 'I could not connect to your FTP server.', 'wpdbbkp' ) . '<br />' . esc_html__( 'Please check your FTP Host and try again.', 'wpdbbkp' );
	}

	ftp_set_option( $wpdbbkp_conn, FTP_TIMEOUT_SEC, 90 );

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_result = @ftp_login( $wpdbbkp_conn, $wpdbbkp_user, $wpdbbkp_pass );
	if ( ! $wpdbbkp_result ) {
		ftp_close( $wpdbbkp_conn );
		return esc_html__( 'I could connect to the FTP server but I could not log in.', 'wpdbbkp' ) . ' ' . esc_html__( 'Please check your credentials and try again.', 'wpdbbkp' );
	}

	if ( defined( 'FTP_USEPASVADDRESS' ) ) {
		ftp_set_option( $wpdbbkp_conn, FTP_USEPASVADDRESS, false );
	}
	ftp_pasv( $wpdbbkp_conn, true );

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_success = @ftp_chdir( $wpdbbkp_conn, $wpdbbkp_subdir );
	if ( ! $wpdbbkp_success ) {
		ftp_close( $wpdbbkp_conn );
		return esc_html__( 'I can connect to the FTP server, but I cannot change into the FTP subdirectory you specified.', 'wpdbbkp' ) . '<br />' . esc_html__( 'Is the path correct? Does the directory exist? Is it writable?', 'wpdbbkp' ) . '<br />' . esc_html__( 'Please check using an FTP client like FileZilla.', 'wpdbbkp' );
	}

	ftp_close( $wpdbbkp_conn );
	return 'OK';
}
