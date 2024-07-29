<?php
/**
 * Include destination files.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wp_db_incremental_backup = get_option( 'wp_db_incremental_backup', false );

if ( !$wp_db_incremental_backup ) {
	require plugin_dir_path( __FILE__ ) . '/FTP/ftp-form-dest.php';
	require plugin_dir_path( __FILE__ ) . '/SFTP/sftp-form-dest.php';
	require plugin_dir_path( __FILE__ ) . '/Local/local-form.php';
	require plugin_dir_path( __FILE__ ) . '/Email/email-form.php';
	require plugin_dir_path( __FILE__ ) . '/Google/google-form.php';
	require plugin_dir_path( __FILE__ ) . '/S3/s3-form.php';
	require plugin_dir_path( __FILE__ ) . '/Dropbox/dropbox-form.php';
} else { ?>

	<div class="alert alert-warning " role="alert">
		&nbsp;<span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> 
		<?php echo esc_html__('Only incremental backup supporting destinations are shown here.To show all disable incremental backup  in settings tab', 'wpdbbkp');?>
	</div>

<?php
}

require plugin_dir_path( __FILE__ ) . '/Backblaze/bb-form.php';

