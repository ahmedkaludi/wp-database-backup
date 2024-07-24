<?php
/**
 * Destination ftp
 *
 * @package wpdbbkp
 */

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

?>
<p><strong><?php esc_html_e('Here\'s a list of BackupBreeze in your repository:' , 'wpdbbkp');?> </strong></p>
<?php
/**
 * Set up variables
 *
 * @package wpdbbkp
 */
$host   = get_option( 'snapshot_ftp_host' );
$user   = get_option( 'snapshot_ftp_user' );
$pass   = get_option( 'snapshot_ftp_pass' );
$subdir = get_option( 'snapshot_ftp_subdir' );
if ( '' === $subdir ) {
	$subdir = '/';
}

// If in WP Dashboard or Admin Panels.
if ( is_admin() ) {
	// If user has WP manage options permissions.
	if ( current_user_can( 'manage_options' ) ) {
		$conn_id = ftp_connect( $host );
	}
}

// Login with username and password.
$login_result = ftp_login( $conn_id, $user, $pass );

// Get contents of the current directory.
$contents = ftp_nlist( $conn_id, "$subdir/*.tar" );

?>
<ol></em>

<?php
if(!empty($contents)){
	foreach ( $contents as $key => $value ) {
		echo '<li>' . esc_attr( substr( $value, ( strlen( $subdir ) ) ) ) . '</li>';
	}
}

?>
</ol>
<p><br />
<em><?php echo esc_html__('This section shows a list of Backup in your repository. ', 'wpdbbkp') ?></em></p>
<p><em><?php echo esc_html__("If you're using the Auto-Delete option under Automation: ", 'wpdbbkp') ?> <br />
</em><em><?php echo esc_html__('the files at the bottom of this list will be deleted, the ones at the top will stay in place. ', 'wpdbbkp') ?></em>
<?php
	ftp_close( $conn_id );
?>
</p>
