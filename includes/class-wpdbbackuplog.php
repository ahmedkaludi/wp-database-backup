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
				if(!is_array($option )){
					continue;
				}
				if (isset($args[0]) && $option['filename'] === $args[0]) {
					$option['destination'] = wp_kses_post($args[4]);
					$option['log']         = wp_kses_post($args[2]);
					$newoptions[]          = $option;
				} else {
					$newoptions[] = $option;
				}
			}
		}		
		$newoptions = wpdbbkp_filter_unique_filenames( $newoptions );
		update_option( 'wp_db_backup_backups', $newoptions ,false);
	}

}