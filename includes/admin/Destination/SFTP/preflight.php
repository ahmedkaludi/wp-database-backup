<?php
/**
 * Destination file.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Error checking.
 *
 * @param string $trouble - Trouble response.
 */

require __DIR__ . '/vendor/autoload.php';
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

function wpdbbkp_preflight_problem( $trouble ) {
	$error_log = $trouble;
}
$wpdbbkp_sftp_details	=	get_option( 'wp_db_backup_sftp_details',array());

$host   = isset($wpdbbkp_sftp_details['host'])?$wpdbbkp_sftp_details['host']:'';
$port   = isset($wpdbbkp_sftp_details['port'])?$wpdbbkp_sftp_details['port']:22;
$user   = isset($wpdbbkp_sftp_details['username'])?$wpdbbkp_sftp_details['username']:'';
$pass   = isset($wpdbbkp_sftp_details['password'])?$wpdbbkp_sftp_details['password']:'';
$pkey   = isset($wpdbbkp_sftp_details['sftp_key'])?base64_decode($wpdbbkp_sftp_details['sftp_key']):'';
$key_pass   = isset($wpdbbkp_sftp_details['key_password'])?$wpdbbkp_sftp_details['key_password']:false;
$directory = isset($wpdbbkp_sftp_details['directory'])?$wpdbbkp_sftp_details['directory']:'';
$wpdbbkp_auth_type_ = isset($wpdbbkp_sftp_details[ 'auth_type' ])?$wpdbbkp_sftp_details[ 'auth_type' ]:'password';
$sftp = false;
if ( '' === $directory ) {
	$directory = '/';
}
if ( $host ) {
	// If in WP Dashboard or Admin Panels.
	if ( is_admin() ) {
		// If user has WP manage options permissions.
		if ( current_user_can( 'manage_options' ) ) {
			// Connect to host ONLY if the 2 security conditions are valid / met.
			$sftp = new SFTP( $host , $port );
			if ( ! $sftp ) {
				return esc_html__('Could not connect to your SFTP server.','wpdbbkp').'<br />'.esc_html__('Please check your SFTP Host settings and try again (leave FTP Host BLANK for local backups).','wpdbbkp');
			}
			if($wpdbbkp_auth_type_=='key'){
				$key = PublicKeyLoader::load($pkey,$key_pass);
				$result = $sftp->login($user, $key);
			}else{
				$result = $sftp->login($user, $pass);
			}
			if ( ! $result ) {
				return esc_html__('Could not log in to your FTP server.','wpdbbkp').'<br />'.esc_html__('Please check your SFTP Username and Password, then try again.','wpdbbkp').'<br />'.esc_html__('For local backups, please leave the FTP Host option BLANK.','wpdbbkp');
			}
		}
	}
}
