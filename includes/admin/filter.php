<?php
/**
 * Backup Filters
 *
 * @package wpdbbkp
 */

add_filter( 'upgrader_pre_install', 'wp_db_backup_upgrader_pre_install', 10, 2 );

/**
 * Filter for upgrade theme or plugin.
 *
 * @param bool  $response -  Installation response.
 * @param array $hook_extra - Extra arguments passed to hooked filters.
 * @return bool
 */
function wp_db_backup_upgrader_pre_install( $response, $hook_extra ) {
	$wp_db_backup_enable_auto_upgrade = get_option( 'wp_db_backup_enable_auto_upgrade' );
	if ( 1 === $wp_db_backup_enable_auto_upgrade ) {
		$before_update_backup_obj = new wpdb_Admin();
		$before_update_backup_obj->wp_db_backup_event_process();
	}
	return $response;
}

/**
 * Validating input data for sequrity.
 *
 * @param string $string -  Input data.
 * @return string
 */
function wp_db_filter_data( $string ) {
	$search  = array( 'animation-name', 'alert(', 'style=', 'onanimationstart' );
	$replace = array( '', '', '', '' );
	$result  = str_replace( $search, $replace, $string );
	return $result;
}
