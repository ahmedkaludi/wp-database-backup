<?php
/**
 * Destination ftp
 *
 * @package wpdbbkp
 */

// Set up variables.

require __DIR__ . '/vendor/autoload.php';
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;


$wpdbbkp_sftp_details	=	get_option( 'wp_db_backup_sftp_details',array());

$host   = isset($wpdbbkp_sftp_details['host'])?$wpdbbkp_sftp_details['host']:'';
$port   = isset($wpdbbkp_sftp_details['port'])?$wpdbbkp_sftp_details['port']:22;
$user   = isset($wpdbbkp_sftp_details['username'])?$wpdbbkp_sftp_details['username']:'';
$pass   = isset($wpdbbkp_sftp_details['password'])?$wpdbbkp_sftp_details['password']:'';
$pkey   = isset($wpdbbkp_sftp_details['sftp_key'])?base64_decode($wpdbbkp_sftp_details['sftp_key']):'';
$key_pass   = isset($wpdbbkp_sftp_details['key_password'])?$wpdbbkp_sftp_details['key_password']:false;
$directory = isset($wpdbbkp_sftp_details['directory'])?$wpdbbkp_sftp_details['directory']:'';
$wpdbbkp_auth_type_ = isset($wpdbbkp_sftp_details[ 'auth_type' ])?$wpdbbkp_sftp_details[ 'auth_type' ]:'password';
$wp_upload_dir = wp_upload_dir();

$wp_upload_dir['basedir'] = str_replace( '\\', '/', $wp_upload_dir['basedir'] );
$remotefile               = $directory . '/' . $filename;
$localfile                = trailingslashit( $wp_upload_dir['basedir'] . '/db-backup' ) . $filename;
if ( isset( $host ) && ! empty( $host ) && isset( $user ) && ! empty( $user ) && isset( $pass ) && ! empty( $pass ) ) {
	
	$sftp = new SFTP( $host , $port );
	if ( $sftp  ) {
		if($wpdbbkp_auth_type_=='key'){
			$key = PublicKeyLoader::load($pkey,$key_pass);
			$result = $sftp->login($user, $key);
		}else{
			$result = $sftp->login($user, $pass);
		}
		if ( $result ) {
			$success = $sftp->put($remotefile, $localfile, SFTP::SOURCE_LOCAL_FILE | SFTP::RESUME);
			if ( $success ) {
				$args[2] = $args[2] . '<br> Upload Database Backup on SFTP ' . $host;
				$args[4] = $args[4] .= 'SFTP, ';
			}
		}
	}
	// Close connection to host.
	if(!is_bool($conn)){
		$sftp->disconnect();
	}
	
}

