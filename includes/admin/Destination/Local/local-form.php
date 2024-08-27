<?php

$wpdbbkp_local_enabled	=	get_option( 'wp_db_local_backup',null );
$wpdbbkp_local_path		=	get_option( 'wp_db_local_backup_path',null );
$wpdbbkp_local_status		=	'<label><b>'.esc_html__('Status','wpdbbkp').'</b>: '.esc_html__('Not Configured','wpdbbkp').' </label> ';
if($wpdbbkp_local_enabled==1 && !empty($wpdbbkp_local_path))
{
	$wpdbbkp_local_status='<label><b>'.esc_html__('Status','wpdbbkp').'</b>: <span class="dashicons dashicons-yes-alt" style="color:green;font-size:16px" title="'.esc_attr__('Destination enabled','wpdbbkp').'"></span><span class="configured">'.esc_html__('Configured','wpdbbkp').' </span> </label> ';
}
?>
<div class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a data-toggle="collapse" data-parent="#accordion" href="#collapseLocal">
								<h2><?php echo esc_html__('Local Backup', 'wpdbbkp') ?> <?php echo wp_kses_post($wpdbbkp_local_status);?> <span class="dashicons dashicons-admin-generic"></span></h2>

							</a>
						</h4>
					</div>
					<div id="collapseLocal" class="panel-collapse collapse">
						<div class="panel-body">
							<?php
							/**
							 * Destination form.
							 *
							 * @package wpdbbkp
							 */

							echo '<form name="wp-local_form" method="post" action="" >';
							wp_nonce_field( 'wp-database-backup' );
							$wp_db_local_backup_path = get_option( 'wp_db_local_backup_path' );
							$wp_db_local_backup      = get_option( 'wp_db_local_backup' );
							echo '<p>';
							$ischecked = ( isset( $wp_db_local_backup ) && 1 === (int) $wp_db_local_backup ) ? 'checked' : '';
							echo '<div class="row form-group">
                                <label class="col-sm-2" for="wp_db_local_backup_path">'.esc_html__('Enable Local Backup','wpdbbkp').'</label>
                                <div class="col-sm-6">
                                    <input type="checkbox" ' . esc_attr( $ischecked ) . ' id="wp_db_local_backup_path" name="wp_db_local_backup">
                                </div>
                            </div>';
							echo '<div class="row form-group"><label class="col-sm-2" for="wp_db_backup_email_id">'.esc_html__('Local Backup Path','wpdbbkp').'</label>';
							echo '<div class="col-sm-6"><input type="text" id="wp_db_backup_email_id" class="form-control" name="wp_db_local_backup_path" value="' . esc_url( $wp_db_local_backup_path ) . '" placeholder="'.esc_attr__('Directory Path','wpdbbkp').'"></div>';
							echo '<div class="col-sm-4">'.esc_html__('Leave blank if you don\'t want use this feature or Disable Local Backup','wpdbbkp').'</div></div>';
							echo '<div class="row form-group">';
							echo '<div class="col-sm-12">';
							if ( false === empty( $wp_db_local_backup_path ) && false === file_exists( $wp_db_local_backup_path ) ) {
								echo '<div class="alert alert-warning alert-dismissible fade in" role="alert">
                                      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>';
								esc_html_e( 'Invalid Local Backup Path : ', 'wpdbbkp' );
								echo esc_url( $wp_db_local_backup_path );
								echo '</div>';
							}
							esc_html_e( 'Backups outside from the public_html directory or inside public_html directory but diffrent location (without using FTP).', 'wpdbbkp' );
							esc_html_e( 'Ex.: C:/xampp/htdocs', 'wpdbbkp' );
							echo '</div>';
							echo '<div class="col-sm-12 submit">';
							echo '<input type="submit" name="local_backup_submit" class="btn btn-primary" value="'.esc_attr__('Save Settings','wpdbbkp').'" />';
							echo '</div>';
							echo '</form>';
							?>
						</div>
					</div>
				</div>
						</div>
