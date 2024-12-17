<?php
/**
 * Destination SSH/sFTP
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If user pressed this button, this hidden field will be set to 'Y'.
if ( isset( $_POST[ 'sftp_submit' ] ) && 'Save' === $_POST[ 'sftp_submit' ] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	$wpdbbackup_update_setting = isset( $_POST['wpdbbackup_update_setting'] ) ? sanitize_text_field( wp_unslash( $_POST['wpdbbackup_update_setting'] ) ) : '';
	if ( ! isset( $_POST['wpdbbackup_update_setting'] ) ) {
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	if ( ! wp_verify_nonce( wp_unslash($_POST['wpdbbackup_update_setting']) , 'wpdbbackup-update-setting' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	// Read their posted value.

	$option_to_save =array();
	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'host' ] ) ) {
		$option_to_save['host'] = sanitize_text_field(  wp_unslash($_POST['wp_db_backup_sftp_details'][ 'host' ] ) );
	}
	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'port' ] ) ) {
		$option_to_save['port'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['port']  ));
	}else{
		$option_to_save['port'] = 22;
	}

	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'username' ] ) ) {
		$option_to_save['username'] = sanitize_text_field(wp_unslash( $_POST['wp_db_backup_sftp_details']['username']  ));
	}

	$wpdbbkp_auth_type_ = isset($_POST['wp_db_backup_sftp_details'][ 'auth_type' ])?sanitize_text_field( wp_unslash($_POST['wp_db_backup_sftp_details'][ 'auth_type' ])):'password';

	$option_to_save['auth_type'] = $wpdbbkp_auth_type_;

	if($wpdbbkp_auth_type_ == 'password'){
		if ( isset( $_POST['wp_db_backup_sftp_details'][ 'password' ] ) ) {
			$option_to_save['password'] = sanitize_text_field(  wp_unslash($_POST['wp_db_backup_sftp_details']['password']  ));
		}
	}

	if($wpdbbkp_auth_type_ == 'key'){

		if ( isset( $_POST['wp_db_backup_sftp_details'][ 'sftp_key' ] )) {
			$option_to_save['sftp_key'] = sanitize_text_field( wp_unslash($_POST['wp_db_backup_sftp_details']['sftp_key']  ));
		}
		if ( isset( $_POST['wp_db_backup_sftp_details'][ 'key_password' ] ) ) {
			$option_to_save['key_password'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['key_password']  ));
		}
	}
	
	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'directory' ] ) ) {
		$option_to_save['directory'] = sanitize_text_field( wp_unslash($_POST['wp_db_backup_sftp_details']['directory'])   );
	}

	// Save the posted value in the database.
	update_option( 'wp_db_backup_sftp_details', wp_db_filter_data( $option_to_save ) ,false);

	if ( isset( $_POST['wp_db_backup_destination_SFTP'] ) ) {
		update_option( 'wp_db_backup_destination_SFTP', 1 , false);
	} else {
		update_option( 'wp_db_backup_destination_SFTP', 0 , false);
	}

	
	// Put a "settings updated" message on the screen.
	?>
	<div class="updated"><p><strong><?php esc_html_e( 'Your SFTP details have been saved.', 'wpdbbkp' ); ?></strong></p></div>
	<?php
} // end if.

// If user pressed this button, this hidden field will be set to 'Y'.
if ( isset( $_POST[ 'sftp_test'  ] ) && 'Test Connection' === $_POST[ 'sftp_test'  ] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_setting'] ) ) {
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	if ( ! wp_verify_nonce( wp_unslash( $_POST['wpdbbackup_update_setting'] ) , 'wpdbbackup-update-setting' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		wp_die( esc_html__('Invalid form data. form request came from the somewhere else not current site!','wpdbbkp') );
	}
	include plugin_dir_path( __FILE__ ) . 'test-sftp.php';
	// update all options while we're at it.
	$option_to_save =array();
	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'host' ] ) ) {
		$option_to_save['host'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details'][ 'host' ] ) );
	}
	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'port' ] ) ) {
		$option_to_save['port'] = sanitize_text_field( wp_unslash($_POST['wp_db_backup_sftp_details']['port'] ) );
	}else{
		$option_to_save['port'] = 22;
	}

	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'username' ] ) ) {
		$option_to_save['username'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['username'] ) );
	}

	$wpdbbkp_auth_type_ = isset($_POST['wp_db_backup_sftp_details'][ 'auth_type' ])?sanitize_text_field( wp_unslash($_POST['wp_db_backup_sftp_details'][ 'auth_type' ])):'password';

	$option_to_save['auth_type'] = $wpdbbkp_auth_type_;

	if($wpdbbkp_auth_type_ == 'password'){
		if ( isset( $_POST['wp_db_backup_sftp_details'][ 'password' ] ) ) {
			$option_to_save['password'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['password'] ) );
		}
	}

	if($wpdbbkp_auth_type_ == 'key'){

		if ( isset( $_POST['wp_db_backup_sftp_details'][ 'sftp_key' ] )) {
			$option_to_save['sftp_key'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['sftp_key'] ) );
		}

		if ( isset( $_POST['wp_db_backup_sftp_details'][ 'key_password' ] ) ) {
			$option_to_save['key_password'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['key_password'] ) );
		}
	}
	
	if ( isset( $_POST['wp_db_backup_sftp_details'][ 'directory' ] ) ) {
		$option_to_save['directory'] = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_sftp_details']['directory'] ) );
	}
	// Save the posted value in the database.
	update_option( 'wp_db_backup_sftp_details', wp_db_filter_data( $option_to_save ) ,false);

	$result = wpdbbkp_test_sftp();

	if ( 'OK' !== $result ) {
		?>
		<div class="error"><p><strong><?php echo esc_html__('connection has failed!', 'wpdbbkp') ?><br /></strong></p>
			<?php echo esc_html( $result ) . '<br /><br />'; ?>
		</div>
	<?php } else { ?>

		<div class="updated"><p><strong><?php echo esc_html__('Connected to ', 'wpdbbkp') ?><?php echo esc_html( $option_to_save['host']); ?>, <?php echo esc_html__('for user', 'wpdbbkp') ?> <?php echo esc_html( $option_to_save['username']); ?></strong></p></div>
		<?php
	} // end if.
} // end if.

// Read in existing option value from database
$wp_db_backup_destination_sftp = wp_db_filter_data( get_option( 'wp_db_backup_destination_SFTP',false) );
$wpdbbkp_sftp_details	=	get_option( 'wp_db_backup_sftp_details',array());
$wpdbbkp_sftp_authtype = isset($wpdbbkp_sftp_details['auth_type'])?$wpdbbkp_sftp_details['auth_type']:'password';
?>
<style>td, th {
		padding: 5px;
	}</style>
<p><?php echo esc_html__('Enter your SFTP details for your offsite backup repository. Leave these blank for local backups or Disable SFTP Destination.', 'wpdbbkp') ?></p>		
<form  class="form-group" name="form1" method="post" action="">
	<input type="hidden" name="<?php echo esc_attr( $hidden_field_name ); ?>" value="Y">
	<input name="wpdbbackup_update_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-setting' ) ); ?>" />
<?php wp_nonce_field( 'wp-database-backup' ); ?>

	<div class="row form-group">
		<label class="col-sm-2" for="wp_db_backup_destination_SFTP"><?php echo esc_html__('Enable SFTP Destination', 'wpdbbkp') ?></label>
		<div class="col-sm-6">
			<input type="checkbox" id="wp_db_backup_destination_SFTP" <?php echo ( isset( $wp_db_backup_destination_sftp ) && 1 === (int) $wp_db_backup_destination_sftp ) ? 'checked' : ''; ?> name="wp_db_backup_destination_SFTP">
		</div>
	</div>

	<div class="row form-group">
		<label class="col-sm-2" for="wpdbbkp_sftp_host"><?php echo esc_html__('SFTP Host', 'wpdbbkp') ?></label>
		<div class="col-sm-6">
			<input type="text" id="wpdbbkp_sftp_host" class="form-control" name="wp_db_backup_sftp_details[host]" value="<?php echo esc_attr( isset($wpdbbkp_sftp_details['host'])?$wpdbbkp_sftp_details['host']:''); ?>" size="25" placeholder="<?php esc_attr_e('e.g. sftp.yoursite.com','wpdbbkp');?>">
		</div>
	</div>

	<div class="row form-group">
		<label class="col-sm-2" for="wpdbbkp_sftp_port"><?php echo esc_html__('SFTP Port', 'wpdbbkp') ?></label>
		<div class="col-sm-2">
			<input type="text" id="wpdbbkp_sftp_port" class="form-control" name="wp_db_backup_sftp_details[port]" value="<?php echo esc_attr( isset($wpdbbkp_sftp_details['port'])?$wpdbbkp_sftp_details['port']:''); ?>" size="4">
		</div>
		<div class="col-sm-4">
			<em><?php echo esc_html__('defaults to 22 if left blank', 'wpdbbkp') ?> </em>
		</div>
	</div>

	<div class="row form-group">
		<label class="col-sm-2" for="wpdbbkp_sftp_user"><?php echo esc_html__('SFTP Username', 'wpdbbkp') ?></label>
		<div class="col-sm-6">
			<input type="text" id="wpdbbkp_sftp_user" class="form-control" name="wp_db_backup_sftp_details[username]" value="<?php echo esc_attr( isset($wpdbbkp_sftp_details['username'])?$wpdbbkp_sftp_details['username']:''); ?>" size="25">
		</div>
	</div>

	<div class="row form-group">
		<label class="col-sm-2" for="wpdbbkp_sftp_auth_select"><?php echo esc_html__('SFTP Auth Type ', 'wpdbbkp') ?></label>
		<div class="col-sm-6">
			<select  id="wpdbbkp_sftp_auth_select" class="form-control" name="wp_db_backup_sftp_details[auth_type]" >
				<option value="password" <?php if($wpdbbkp_sftp_authtype=='password'){ echo 'selected';}?> ><?php esc_html_e('Using Password', 'wpdbbkp') ?></option>
				<option value="key" <?php if($wpdbbkp_sftp_authtype=='key'){ echo 'selected';}?>><?php echo esc_html_e('Using Key', 'wpdbbkp') ?></option>
			</select>
		</div>
	</div>
	<div class="row form-group" <?php if( $wpdbbkp_sftp_authtype!='password'){ echo 'style="display:none"'; }?> >
		<label class="col-sm-2" for="wpdbbkp_sftp_password"><?php echo esc_html__('SFTP Password:', 'wpdbbkp') ?></label>
		<div class="col-sm-6">
			<input type="password" id="wpdbbkp_sftp_password" class="form-control" name="wp_db_backup_sftp_details[password]" value="<?php echo esc_attr( isset($wpdbbkp_sftp_details['password'])?$wpdbbkp_sftp_details['password']:''); ?>" size="25">
		</div>
	</div>

	
	
	<div class="row form-group" <?php if($wpdbbkp_sftp_authtype!='key'){ echo 'style="display:none"'; }?> >
		<label class="col-sm-2" for="wpdbbkp_sftp_sshkey"><?php echo esc_html__('SSH Key', 'wpdbbkp') ?></label>
		<div class="col-sm-4">
			<input type="file" id="wpdbbkp_sftp_sshkey" class="form-control"  size="25">
			<input type="hidden" name="wp_db_backup_sftp_details[sftp_key]" id="wp_db_backup_sftp_key" value="<?php echo isset($wpdbbkp_sftp_details['sftp_key'])?esc_attr($wpdbbkp_sftp_details['sftp_key']):'';?>">
		</div>
		<div class="col-sm-4">
		<em style="color:green"><?php if(!empty($wpdbbkp_sftp_details['sftp_key'])){ esc_html_e('(Key Already exists. To change please upload new key)', 'wpdbbkp'); } ?></em>
		<em> <?php esc_html_e('Please use OpenSSH standard keys', 'wpdbbkp');?></em>
		</div>
	</div>
	<div class="row form-group" <?php if($wpdbbkp_sftp_authtype!='key'){ echo 'style="display:none"'; }?> >
		<label class="col-sm-2" for="wpdbbkp_sftp_sshkey_password"><?php echo esc_html__('SSH Key Password', 'wpdbbkp') ?></label>
		<div class="col-sm-4">
			<input type="password" id="wpdbbkp_sftp_sshkey_password" class="form-control" name="wp_db_backup_sftp_details[key_password]" value="<?php echo esc_attr( isset($wpdbbkp_sftp_details['key_password'])?$wpdbbkp_sftp_details['key_password']:''); ?>" size="25">
		</div>
		<div class="col-sm-4">
			<?php esc_html_e('Enter password only if you have set the password','wpdbbkp');?>
		</div>
	</div>
	<div class="row form-group">
		<label class="col-sm-2" for="wpdbbkp_sftp_directory"><?php echo esc_html__('Subdirectory', 'wpdbbkp') ?></label>
		<div class="col-sm-4">
			<input type="text" id="wpdbbkp_sftp_directory" placeholder="<?php esc_attr_e('e.g. /httpdocs/backups','wpdbbkp');?>" class="form-control" name="wp_db_backup_sftp_details[directory]" value="<?php echo esc_attr( isset($wpdbbkp_sftp_details['directory'])?$wpdbbkp_sftp_details['directory']:''); ?>" size="25">
		</div>
		<div class="col-sm-4"> 
			<em><?php echo esc_html__('e.g. /httpdocs/backups or leave blank', 'wpdbbkp') ?></em> 
		</div>
	</div>

	<p><input type="submit" name="sftp_submit" class="btn btn-primary" value="<?php esc_attr_e( 'Save', 'wpdbbkp' ); ?>" />&nbsp;
		<input type="submit" name="sftp_test" class="btn btn-secondary" value="Test Connection" />

		<br />
	</p>
</form>
<hr />
<br />
