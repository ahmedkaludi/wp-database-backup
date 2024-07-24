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


add_action( 'wp_ajax_wpdbbkp_email_unsubscribe', 'wpdbbkp_unsubcribe_email_notification' );
add_action( 'wp_ajax_nopriv_wpdbbkp_email_unsubscribe', 'wpdbbkp_unsubcribe_email_notification' );

function wpdbbkp_unsubcribe_email_notification(){
	if(isset($_GET['unsubscribe_token'])){ // phpcs:ignore	WordPress.Security.NonceVerification.Recommended -- unsubscribe_token is doing the job of nonce.
		$saved_token=get_option('wpdbbkp_unsubscribe_token',false);
		if($saved_token && $saved_token==$_GET['unsubscribe_token']){ 		// phpcs:ignore	WordPress.Security.NonceVerification.Recommended -- unsubscribe_token is doing the job of nonce.
			$current_status = get_option( 'wp_db_backup_destination_Email',false);
			if($current_status == 1){
				update_option('wp_db_backup_destination_Email', 0 , false);
				delete_option('wpdbbkp_unsubscribe_token');
				echo '<h3 align="center">'.esc_html__( 'You have successfully unsubscribed from recieveing backup notification on Email', 'wpdbbkp' ).'</h3>';
			}else if($current_status == 0){
				echo '<h3 align="center">'.esc_html__( 'You have already unsubscribed.', 'wpdbbkp' ).'</h3>';
			}else{
				echo '<h3 align="center">'.esc_html__( 'Invalid Request', 'wpdbbkp' ).'</h3>';
			}
		}
		else{
			echo '<h3 align="center">'.esc_html__( 'Unauthorised Access', 'wpdbbkp' ).'</h3>';
		}
	}
	wp_die();
}
