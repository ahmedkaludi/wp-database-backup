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

		if(!empty($options) && is_array($options)){

			foreach ( $options as $option ) {
				if (isset($args[0]) && $option['filename'] === sanitize_text_field($args[0])) {
					$option['destination'] = wp_kses($args[4]);
					$option['log']         = wp_kses($args[2]);
					$newoptions[]          = $option;
				} else {
					$newoptions[] = $option;
				}
			}
		}		

		update_option( 'wp_db_backup_backups', $newoptions ,false);
	}

}