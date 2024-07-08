<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_db_backup_completed', array( 'WPDBBackupLocal', 'wp_db_backup_completed' ), 11 );

/**
 * WPDBBackupLocal Class.
 *
 * @class WPDBBackupLocal
 */
class WPDBBackupLocal {

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {
		$wp_db_local_backup      = get_option( 'wp_db_local_backup' );
		$wp_db_local_backup_path = get_option( 'wp_db_local_backup_path' );
		if ( true === isset( $wp_db_local_backup ) && 1 === (int) $wp_db_local_backup && false === empty( $wp_db_local_backup_path ) && true === file_exists( $wp_db_local_backup_path ) ) {
			update_option('wpdbbkp_backupcron_current','Processing Local Backup', false);
			$file                    = $args[1];
			$filename                = $args[0];
			$wp_db_local_backup_file = $wp_db_local_backup_path . '/' . $filename;
			$filesze                 = $args[3];

			if ( true === copy( $file, $wp_db_local_backup_file ) ) {
				$args[2] = $args[2] . ' <br>' . __( 'Upload Database Backup on ', 'wpdbbkp' ) . $wp_db_local_backup_path;
				$args[4] .= 'Local Path, ';
			}
		}
	}

}
