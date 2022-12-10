<?php
/**
 * Backup Complete filter for generate log
 *
 * @package wpdbbkp
 */

add_action( 'wp_db_backup_completed', array( 'WPDBBackupLog', 'wp_db_backup_completed' ), 12 );

/**
 * WPDBBackupLog Class.
 *
 * @class WPDBBackupLog
 */
class WPDBBackupLog {

	/**
	 * Added log after backup completed.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {

		$options    = get_option( 'wp_db_backup_backups' );
		$newoptions = array();
		foreach ( $options as $option ) {
			if ( $option['filename'] === $args[0] ) {
				$option['destination'] = $args[4];
				$option['log']         = $args[2];
				$newoptions[]          = $option;
			} else {
				$newoptions[] = $option;
			}
		}

		update_option( 'wp_db_backup_backups', $newoptions );
	}

}
