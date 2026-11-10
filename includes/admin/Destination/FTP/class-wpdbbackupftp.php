<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} 
/**
 * Backup Filters
 *
 * @package wpdbbkp
 */

add_action( 'wpdbbkp_db_backup_completed', array( 'WPDBBackupFTP', 'wp_db_backup_completed' ), 10 );

/**
 * WPDBBackupFTP Class.
 *
 * @class WPDBBackupFTP
 */
class WPDBBackupFTP {

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$wpdbbkp_args ) {
		$destination_ftp = get_option( 'wp_db_backup_destination_FTP' );
		$upload_dir      = wp_upload_dir();
		$log_file        = trailingslashit( str_replace( '\\', '/', $upload_dir['basedir'] ) . '/db-backup' ) . 'ftp-debug.log';
		$log_dir         = dirname( $log_file );
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] hook fired; destination_FTP=' . (string) $destination_ftp . '; file=' . ( isset( $wpdbbkp_args[0] ) ? $wpdbbkp_args[0] : '' ) . PHP_EOL;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );

		if ( isset( $destination_ftp ) && 1 === (int) $destination_ftp ) {
			update_option( 'wpdbbkp_backupcron_current', 'Processing FTP Backup', false );
			$wpdbbkp_filename = $wpdbbkp_args[0];
			include plugin_dir_path( __FILE__ ) . 'sendaway.php';
		} else {
			$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] SKIP: FTP destination not enabled' . PHP_EOL;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
		}
	}
}
