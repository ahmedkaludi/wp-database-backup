<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Destination file.
 *
 * @package wpdbbkp
 */

/**
 * Error checking.
 *
 * @param string $wpdbbkp_trouble - Trouble response.
 */
function wpdbbkp_preflight_problem( $wpdbbkp_trouble ) {
	$error_log = $wpdbbkp_trouble;
}

// set up variables.
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

if ( $wpdbbkp_host && is_admin() && current_user_can( 'manage_options' ) ) {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_conn = @ftp_connect( $wpdbbkp_host, $wpdbbkp_port, 90 );
	if ( ! $wpdbbkp_conn ) {
		$wpdbbkp_trouble = esc_html__( 'I could not connect to your FTP server..', 'wpdbbkp' ) . '<br />' . esc_html__( 'Please check your FTP Host settings and try again (leave FTP Host BLANK for local backups).', 'wpdbbkp' );
		wpdbbkp_preflight_problem( $wpdbbkp_trouble );
		return;
	}

	ftp_set_option( $wpdbbkp_conn, FTP_TIMEOUT_SEC, 90 );

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_result = @ftp_login( $wpdbbkp_conn, $wpdbbkp_user, $wpdbbkp_pass );
	if ( ! $wpdbbkp_result ) {
		ftp_close( $wpdbbkp_conn );
		$wpdbbkp_trouble = esc_html__( 'I could not log in to your FTP server.', 'wpdbbkp' ) . '<br />' . esc_html__( 'Please check your FTP Username and Password, then try again.', 'wpdbbkp' ) . '<br />' . esc_html__( 'For local backups, please leave the FTP Host option BLANK.', 'wpdbbkp' );
		wpdbbkp_preflight_problem( $wpdbbkp_trouble );
		return;
	}

	if ( defined( 'FTP_USEPASVADDRESS' ) ) {
		ftp_set_option( $wpdbbkp_conn, FTP_USEPASVADDRESS, false );
	}
	ftp_pasv( $wpdbbkp_conn, true );

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_success = @ftp_chdir( $wpdbbkp_conn, $wpdbbkp_subdir );
	if ( ! $wpdbbkp_success ) {
		ftp_close( $wpdbbkp_conn );
		$wpdbbkp_trouble = esc_html__( 'I cannot change into the FTP subdirectory you specified. Does it exist?', 'wpdbbkp' ) . '<br />' . esc_html__( 'You must create it first using an FTP client like FileZilla.', 'wpdbbkp' ) . '<br />' . esc_html__( 'Please check and try again.', 'wpdbbkp' );
		wpdbbkp_preflight_problem( $wpdbbkp_trouble );
		return;
	}

	ftp_close( $wpdbbkp_conn );
}
