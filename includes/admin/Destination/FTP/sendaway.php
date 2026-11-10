<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Destination ftp
 *
 * @package wpdbbkp
 */

// Set up variables.
$wpdbbkp_host       = get_option( 'backupbreeze_ftp_host' );
$wpdbbkp_user       = get_option( 'backupbreeze_ftp_user' );
$wpdbbkp_pass       = get_option( 'backupbreeze_ftp_pass' );
$wpdbbkp_subdir     = get_option( 'backupbreeze_ftp_subdir' );
$wpdbbkp_upload_dir = wp_upload_dir();
$wpdbbkp_upload_dir['basedir'] = str_replace( '\\', '/', $wpdbbkp_upload_dir['basedir'] );
$wpdbbkp_backup_dir = trailingslashit( $wpdbbkp_upload_dir['basedir'] . '/db-backup' );
$wpdbbkp_ftp_log    = $wpdbbkp_backup_dir . 'ftp-debug.log';

// Prefer absolute path from the backup process when available.
if ( ! empty( $wpdbbkp_args[1] ) ) {
	$wpdbbkp_localfile = $wpdbbkp_args[1];
} else {
	$wpdbbkp_localfile = $wpdbbkp_backup_dir . $wpdbbkp_filename;
}

// Keep original remote path style for chrooted / custom FTP logins.
$wpdbbkp_subdir     = is_string( $wpdbbkp_subdir ) ? trim( str_replace( '\\', '/', $wpdbbkp_subdir ), '/' ) : '';
$wpdbbkp_remotefile = ( '' !== $wpdbbkp_subdir )
	? $wpdbbkp_subdir . '/' . $wpdbbkp_filename
	: $wpdbbkp_filename;

$wpdbbkp_conn = false;

/**
 * Append a line to uploads/db-backup/ftp-debug.log
 *
 * @param string $log_file Log path.
 * @param string $message  Message.
 */
$wpdbbkp_ftp_write_log = static function ( $log_file, $message ) {
	$dir = dirname( $log_file );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $message . PHP_EOL;
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
};

$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, '===== FTP upload start =====' );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'destination_FTP option=' . get_option( 'wp_db_backup_destination_FTP' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'host(raw)=' . (string) get_option( 'backupbreeze_ftp_host' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'user=' . (string) $wpdbbkp_user );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'pass_set=' . ( ! empty( $wpdbbkp_pass ) ? 'yes' : 'no' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'subdir(raw)=' . (string) get_option( 'backupbreeze_ftp_subdir' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'subdir(normalized)=' . $wpdbbkp_subdir );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'filename=' . (string) $wpdbbkp_filename );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'localfile=' . (string) $wpdbbkp_localfile );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'remotefile=' . (string) $wpdbbkp_remotefile );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'local_exists=' . ( file_exists( $wpdbbkp_localfile ) ? 'yes' : 'no' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'local_readable=' . ( is_readable( $wpdbbkp_localfile ) ? 'yes' : 'no' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'local_size=' . ( file_exists( $wpdbbkp_localfile ) ? (string) filesize( $wpdbbkp_localfile ) : 'n/a' ) );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'ftp_connect_available=' . ( function_exists( 'ftp_connect' ) ? 'yes' : 'no' ) );

if ( empty( $wpdbbkp_host ) || empty( $wpdbbkp_user ) || empty( $wpdbbkp_pass ) ) {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'ABORT: missing host/user/pass' );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, '===== FTP upload end =====' );
	return;
}

if ( empty( $wpdbbkp_localfile ) || ! file_exists( $wpdbbkp_localfile ) ) {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'ABORT: local backup file missing' );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, '===== FTP upload end =====' );
	return;
}

// Normalize host (users often paste ftp://host).
$wpdbbkp_host = preg_replace( '#^(ftps?|sftp)://#i', '', trim( $wpdbbkp_host ) );
$wpdbbkp_host = untrailingslashit( $wpdbbkp_host );
$wpdbbkp_host = preg_replace( '#/.*$#', '', $wpdbbkp_host );

$wpdbbkp_port = get_option( 'backupbreeze_ftp_port' );
$wpdbbkp_port = ! empty( $wpdbbkp_port ) ? (int) $wpdbbkp_port : 21;

$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'host(normalized)=' . $wpdbbkp_host );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'port=' . $wpdbbkp_port );

// Capture FTP warnings into the log.
$wpdbbkp_ftp_warnings = array();
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
set_error_handler(
	static function ( $errno, $errstr ) use ( &$wpdbbkp_ftp_warnings ) {
		$wpdbbkp_ftp_warnings[] = $errstr;
		return true;
	}
);

$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'STEP: ftp_connect...' );
// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdbbkp_conn = @ftp_connect( $wpdbbkp_host, $wpdbbkp_port, 90 );

if ( ! $wpdbbkp_conn ) {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'FAIL: ftp_connect failed' );
	if ( ! empty( $wpdbbkp_ftp_warnings ) ) {
		$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'warnings: ' . implode( ' | ', $wpdbbkp_ftp_warnings ) );
	}
	restore_error_handler();
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, '===== FTP upload end =====' );
	return;
}
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'OK: ftp_connect' );

ftp_set_option( $wpdbbkp_conn, FTP_TIMEOUT_SEC, 90 );

$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'STEP: ftp_login...' );
// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdbbkp_result = @ftp_login( $wpdbbkp_conn, $wpdbbkp_user, $wpdbbkp_pass );

if ( ! $wpdbbkp_result ) {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'FAIL: ftp_login failed' );
	if ( ! empty( $wpdbbkp_ftp_warnings ) ) {
		$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'warnings: ' . implode( ' | ', $wpdbbkp_ftp_warnings ) );
	}
	ftp_close( $wpdbbkp_conn );
	restore_error_handler();
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, '===== FTP upload end =====' );
	return;
}
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'OK: ftp_login' );

// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdbbkp_pwd = @ftp_pwd( $wpdbbkp_conn );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'pwd=' . ( false === $wpdbbkp_pwd ? 'unknown' : $wpdbbkp_pwd ) );

/*
 * Many FTP servers behind NAT return a private/unreachable IP in PASV.
 * Ignore that address and reuse the host from ftp_connect() instead.
 */
if ( defined( 'FTP_USEPASVADDRESS' ) ) {
	ftp_set_option( $wpdbbkp_conn, FTP_USEPASVADDRESS, false );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'FTP_USEPASVADDRESS set to false' );
} else {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'FTP_USEPASVADDRESS constant not defined' );
}

$wpdbbkp_ftp_warnings = array();
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'STEP: ftp_pasv(true)...' );
// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdbbkp_pasv = @ftp_pasv( $wpdbbkp_conn, true );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'ftp_pasv result=' . ( $wpdbbkp_pasv ? 'true' : 'false' ) );

$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'STEP: ftp_put (passive) remote=' . $wpdbbkp_remotefile );
// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
$wpdbbkp_success = @ftp_put( $wpdbbkp_conn, $wpdbbkp_remotefile, $wpdbbkp_localfile, FTP_BINARY );
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'ftp_put(passive) result=' . ( $wpdbbkp_success ? 'true' : 'false' ) );
if ( ! empty( $wpdbbkp_ftp_warnings ) ) {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'warnings: ' . implode( ' | ', $wpdbbkp_ftp_warnings ) );
	$wpdbbkp_ftp_warnings = array();
}

// Fallback to active mode if passive data connection failed.
if ( ! $wpdbbkp_success ) {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'STEP: ftp_pasv(false) active mode fallback...' );
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@ftp_pasv( $wpdbbkp_conn, false );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'STEP: ftp_put (active) remote=' . $wpdbbkp_remotefile );
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_success = @ftp_put( $wpdbbkp_conn, $wpdbbkp_remotefile, $wpdbbkp_localfile, FTP_BINARY );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'ftp_put(active) result=' . ( $wpdbbkp_success ? 'true' : 'false' ) );
	if ( ! empty( $wpdbbkp_ftp_warnings ) ) {
		$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'warnings: ' . implode( ' | ', $wpdbbkp_ftp_warnings ) );
		$wpdbbkp_ftp_warnings = array();
	}
}

if ( $wpdbbkp_success ) {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$wpdbbkp_remote_size = @ftp_size( $wpdbbkp_conn, $wpdbbkp_remotefile );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'SUCCESS: uploaded. remote_size=' . $wpdbbkp_remote_size . ' local_size=' . filesize( $wpdbbkp_localfile ) );
	$wpdbbkp_args[2] = $wpdbbkp_args[2] . '<br> ' . esc_html__( 'Upload Database Backup on FTP ', 'wpdbbkp' ) . $wpdbbkp_host;
	$wpdbbkp_args[4] .= 'FTP, ';
	$wpdbbkp_args['ftp_uploaded'] = true;
} else {
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'FAIL: upload did not succeed' );
}

if ( $wpdbbkp_conn && ! is_bool( $wpdbbkp_conn ) ) {
	ftp_close( $wpdbbkp_conn );
	$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, 'connection closed' );
}

restore_error_handler();
$wpdbbkp_ftp_write_log( $wpdbbkp_ftp_log, '===== FTP upload end =====' );
