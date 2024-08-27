<?php
/**
 * Include destination files.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


	require plugin_dir_path( __FILE__ ) . '/FTP/class-wpdbbackupftp.php';
	require plugin_dir_path( __FILE__ ) . '/SFTP/class-wpdbbackupsftp.php';
	require plugin_dir_path( __FILE__ ) . '/Local/class-wpdbbackuplocal.php';
	require plugin_dir_path( __FILE__ ) . '/Email/class-wpdbbackupemail.php';
	require plugin_dir_path( __FILE__ ) . '/Google/class-wpdbbackupgoogle.php';
	require plugin_dir_path( __FILE__ ) . '/S3/class-wpdatabasebackups3.php';
	require plugin_dir_path( __FILE__ ) . '/Dropbox/class-wpdbbackupdropbox.php';
	require plugin_dir_path( __FILE__ ) . '/CloudDrive/class-wpdatabasebackupcd.php';
	require plugin_dir_path( __FILE__ ) . '/Backblaze/class-wpdatabasebackupbb.php';
