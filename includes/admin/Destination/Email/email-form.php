<?php
/**
 * Destination dropboxs
 *
 * @package wpdbbkp
 */

$wpdbbkp_email_enabled	=	get_option( 'wp_db_backup_destination_Email',null );
$wpdbbkp_email_id		=	get_option( 'wp_db_backup_email_id',null );
$wpdbbkp_email_status		=	'<label><b>'.esc_html__('Status','wpdbbkp').'</b>: '.esc_html__('Not Configured ','wpdbbkp').'</label> ';
if($wpdbbkp_email_enabled==1 && !empty($wpdbbkp_email_id))
{
	$wpdbbkp_email_status='<label><b>'.esc_html__('Status','wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled','wpdbbkp').' "></span><span class="configured">'.esc_html__('Configured','wpdbbkp').' </span> </label> ';
}


// If user pressed this button, this hidden field will be set to 'Y'.
if ( isset( $_POST[ 'email_notification_submit' ] ) && 'Save Settings' === $_POST[ 'email_notification_submit' ] ) {

	// This is a hidden field used to validate the form.
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'wp-database-backup' ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		return;
	}
	if ( isset( $_POST['wp_db_backup_destination_Email'] ) ) {
		update_option( 'wp_db_backup_destination_Email', 1 , false);
	} else {
		update_option( 'wp_db_backup_destination_Email', 0 , false);
	}

	if ( isset( $_POST['wp_db_backup_email_attachment'] ) ) {
		update_option( 'wp_db_backup_email_attachment', sanitize_text_field(wp_unslash($_POST['wp_db_backup_email_attachment'])) , false);
	} 

	if ( isset( $_POST['wp_db_backup_email_id'] ) ) {
		update_option( 'wp_db_backup_email_id', sanitize_email( wp_unslash( $_POST['wp_db_backup_email_id']) ) , false);
	} 

} // end if.
?>
<div class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a data-toggle="collapse" data-parent="#accordion" href="#collapseII">
								<h2><?php echo esc_html__('Email Notification', 'wpdbbkp') ?> <?php echo wp_kses_post($wpdbbkp_email_status);?> <span class="dashicons dashicons-admin-generic"></span></h2>

							</a>
						</h4>
					</div>
					<div id="collapseII" class="panel-collapse collapse">
						<div class="panel-body">

							<?php
							echo '<form name="wp-email_form" method="post" action="" >';
							wp_nonce_field( 'wp-database-backup' );

							$wp_db_backup_email_id          = '';
							$wp_db_backup_email_id          = sanitize_email( get_option( 'wp_db_backup_email_id' ) );
							$wp_db_backup_email_attachment  = '';
							$wp_db_backup_email_attachment  = get_option( 'wp_db_backup_email_attachment' );
							$wp_db_backup_destination_email = get_option( 'wp_db_backup_destination_Email' );
							echo '<p>';
							echo '<span class="glyphicon glyphicon-envelope"></span> '.esc_html__('Send Email Notification', 'wpdbbkp').'</br></p>';
							$ischecked = ( true === isset( $wp_db_backup_destination_email ) && 1 === (int) $wp_db_backup_destination_email ) ? 'checked' : '';
							echo '<div class="row form-group">
                                <label class="col-sm-2" for="wp_db_backup_destination_Email">'.esc_html__('Enable Email Notification:', 'wpdbbkp').'</label>
                                <div class="col-sm-6">
                                    <input type="checkbox" ' . esc_attr( $ischecked ) . ' id="wp_db_backup_destination_Email" name="wp_db_backup_destination_Email">
                                </div>
                            </div>';
							echo '<div class="row form-group"><label class="col-sm-2" for="wp_db_backup_email_id">'.esc_html__('Email Id', 'wpdbbkp').'</label>';
							echo '<div class="col-sm-6"><input type="text" id="wp_db_backup_email_id" class="form-control" name="wp_db_backup_email_id" value="' . esc_attr( $wp_db_backup_email_id ) . '" placeholder="'.esc_attr__('Your Email Id', 'wpdbbkp').'"></div>';
							echo '<div class="col-sm-4">'.esc_html__('Leave blank if you don\'t want use this feature or Disable Email Notification', 'wpdbbkp').'</div></div>';
							echo '<div class="row form-group"><label class="col-sm-2" for="lead-theme">'.esc_html__('Attach backup file', 'wpdbbkp').' </label> ';
							$selected_option = get_option( 'wp_db_backup_email_attachment' );

							if ( 'yes' === $selected_option ) {
								$selected_yes = 'selected="selected"';
							} else {
								$selected_yes = '';
							}
							if ( 'no' === $selected_option ) {
								$selected_no = 'selected="selected"';
							} else {
								$selected_no = '';
							}
							echo '<div class="col-sm-2"><select id="lead-theme" class="form-control" name="wp_db_backup_email_attachment">';
							echo '<option value="none">'.esc_html__('Select', 'wpdbbkp').'</option>';

							echo '<option  value="yes"' . esc_attr( $selected_yes ) . '>'.esc_html__('Yes', 'wpdbbkp').'</option>';
							echo '<option  value="no" ' . esc_attr( $selected_no ) . '>'.esc_html__('No', 'wpdbbkp').'</option>';

							echo '</select></div>';

							echo '<div class="col-sm-8">'.esc_html__('If you want attache backup file to email then select "yes" (File attached only when backup file size <=25MB)', 'wpdbbkp').'</div>';

							echo '</div>';
							echo '<p class="submit">';
							echo '<input type="submit" name="email_notification_submit" class="btn btn-primary" value="'.esc_attr__('Save Settings', 'wpdbbkp').'" />';
							echo '</p>';
							echo '</form>';
							?>
						</div>		
					</div>
				</div>
