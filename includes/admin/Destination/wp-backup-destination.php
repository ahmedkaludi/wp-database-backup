<?php
/**
 * Include destination files.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


	require plugin_dir_path( __FILE__ ) . '/FTP/ftp-form-dest.php';
	require plugin_dir_path( __FILE__ ) . '/SFTP/sftp-form-dest.php';
	require plugin_dir_path( __FILE__ ) . '/Local/local-form.php';
	require plugin_dir_path( __FILE__ ) . '/Email/email-form.php';
	require plugin_dir_path( __FILE__ ) . '/Google/google-form.php';
	require plugin_dir_path( __FILE__ ) . '/S3/s3-form.php';
	require plugin_dir_path( __FILE__ ) . '/Dropbox/dropbox-form.php';
	require plugin_dir_path( __FILE__ ) . '/Backblaze/bb-form.php';
	

