<?php
/**
 * Destination SFTP test.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! current_user_can( 'manage_options' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

require __DIR__ . '/vendor/autoload.php';
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
/**
 * Test app.
 */
function wpdbbkp_test_sftp() {

	// Now let's see if we can connect to the SFTP repo.

	$wpdbbkp_sftp_details	=	get_option( 'wp_db_backup_sftp_details',array());

	$host   = isset($wpdbbkp_sftp_details['host'])?$wpdbbkp_sftp_details['host']:'';
	$port   = isset($wpdbbkp_sftp_details['port'])?$wpdbbkp_sftp_details['port']:22;
	$user   = isset($wpdbbkp_sftp_details['username'])?$wpdbbkp_sftp_details['username']:'';
	$pass   = isset($wpdbbkp_sftp_details['password'])?$wpdbbkp_sftp_details['password']:'';
	$pkey   = isset($wpdbbkp_sftp_details['sftp_key'])?base64_decode($wpdbbkp_sftp_details['sftp_key']):'';
	$key_pass   = isset($wpdbbkp_sftp_details['key_password'])?$wpdbbkp_sftp_details['key_password']:false;
	$directory = isset($wpdbbkp_sftp_details['directory'])?$wpdbbkp_sftp_details['directory']:'';
	if ( '' === $directory ) {
		$directory = '/';
	}
	$wpdbbkp_auth_type_ = isset($wpdbbkp_sftp_details[ 'auth_type' ])?$wpdbbkp_sftp_details[ 'auth_type' ]:'password';
	if ( is_admin() ) {
		// If user has WP manage options permissions.
		if ( current_user_can( 'manage_options' ) ) {
			// Connect to host ONLY if the 2 security conditions are valid / met.
			$sftp = new SFTP( $host , $port );
		}
	}

	if ( ! $sftp ) {
		$trouble = esc_html__('Could not connect to  SFTP server.<br />Please check your SFTP Host and try again.', 'wpdbbkp');
		return $trouble;
	}

	if($wpdbbkp_auth_type_=='key'){
		$key = PublicKeyLoader::load($pkey,$key_pass);
		$result = $sftp->login($user, $key);
	}else{
		$result = $sftp->login($user, $pass);
	}
	if ( ! $result ) {
		$trouble = esc_html__('Connected to the SFTP server but could not log in.<br />Please check your credentials and try again.', 'wpdbbkp');
		return $trouble;
	}

	$trouble = 'OK';

	// Lose this connection.
	$sftp->disconnect();
	return $trouble;

}
