<?php
/**
 * Backup Complete filter for dropbox
 *
 * @package wpdbbkp
 */

add_action( 'wp_db_backup_completed', array( 'WPDBBackupDropbox', 'wp_db_backup_completed' ) );

/**
 * Class for communicating with Dropbox API V2.
 *
 * @package wpdbbkp
 */
class WPDBBackupDropbox {
	/**
	 * Added log after backup completed.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {
		
		include plugin_dir_path( __FILE__ ) . 'class-wpdbbackup-destination-dropbox-api.php';
		$dropbox           = new WPDBBackup_Destination_Dropbox_API( 'dropbox' );
		$wpdb_dropboxtoken = get_option( 'wpdb_dropboxtoken' );
		$dropboxtoken      = ( ! empty( $wpdb_dropboxtoken ) ) ? maybe_unserialize( $wpdb_dropboxtoken ) : array();
		if ( isset( $dropboxtoken['access_token'] ) && ! empty( $dropboxtoken['access_token'] ) ) {
			update_option('wpdbbkp_backupcron_current','Processing Dropbox Backup',false);
			$dropbox->setOAuthTokens( $dropboxtoken );
			$wpdb_dropbbox_dir = get_option( 'wpdb_dropbbox_dir' );
			$wpdb_dropbbox_dir = ! empty( $wpdb_dropbbox_dir ) ? '/' . get_option( 'wpdb_dropbbox_dir' ) . '/' : '';
			$response          = $dropbox->upload( $args[1], $wpdb_dropbbox_dir . apply_filters( 'wp_db_backup_dropbox_file_name', $args[0] ) );
			if ( $response ) {
				$args[2] = $args[2] . '<br> '.esc_html__('Upload Database Backup on Dropbox', 'wpdbbkp').'';
				$args[4] .= 'DropBox, ';
				
			}
		}

	}

}
