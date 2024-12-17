<?php
/**
 * Show header notification in dashboard
 *
 * @package wpdbbkp
 */

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly
$wpdbbkp_bg_notify = get_option('wpdbbkp_dashboard_notify',false);
if (true === isset($_GET['notification']) && true === isset($_GET['_wpnonce']) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce']) , 'wp-database-backup') || $wpdbbkp_bg_notify) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce ?> 

	<div class="text-center wpdbbkp_notification"><img width="50" height="50" src="<?php echo esc_url(WPDB_PLUGIN_URL. "/assets/images/success.png"); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>">
		<h4 class="text-success"><?php if ((isset($_GET['notification']) && 'create' === $_GET['notification']) || $wpdbbkp_bg_notify=='create') {
							$backup_list = get_option('wp_db_backup_backups');
							if(!empty($backup_list) && is_array($backup_list)){
								$download_backup = end($backup_list);
								if($download_backup && !empty($download_backup) && isset($download_backup['url']))
								{ 
									$backup_link = '<a href="' . esc_url(admin_url('?wpdbbkp_download='.basename($download_backup['url']))) . '" style="color: #21759B;">' . __('Click Here to Download Backup.', 'wpdbbkp') . '</a>';
								}
							}
							
							update_option('wpdbbkp_dashboard_notify',false);
							esc_html_e('Backup Created Successfully. ', 'wpdbbkp');
						} elseif ('restore' === $_GET['notification']) {
							esc_html_e('Backup Restore Successfully', 'wpdbbkp');
						} elseif ('restore_limit' === $_GET['notification']) {
							esc_html_e('Restore Limit for Backup reached. Please update to PRO to remove this limit', 'wpdbbkp');
						}elseif ('delete' === $_GET['notification']) {
							esc_html_e('Backup deleted Successfully', 'wpdbbkp');
						}elseif ('deleteall' === $_GET['notification']) {
							esc_html_e('All Backup data is  deleted Successfully', 'wpdbbkp');
						} elseif ('clear_temp_db_backup_file' === $_GET['notification']) {
							esc_html_e('Clear all old/temp database backup files Successfully', 'wpdbbkp');
						} elseif ('Invalid' === $_GET['notification']) {
							esc_html_e('Invalid Access!!!!', 'wpdbbkp');
						} elseif ('deleteauth' === $_GET['notification']) {
							esc_html_e('Dropbox account unlink Successfully', 'wpdbbkp');
						} elseif ('save' === $_GET['notification']) {
							esc_html_e('Backup Setting Saved Successfully', 'wpdbbkp');
						}
			?></h4>
			<?php if (isset($_GET['notification']) && 'create' === $_GET['notification'] && isset($backup_link)) { ?>
		<h5 class="text-success"><strong><?php echo wp_kses_post($backup_link); ?> </strong></h5>
		<?php } ?>
	</div>
<?php } ?>

<div id="wpdb-backup-process" style="display:none">
	<div class="text-center"><img width="50" height="50" src="<?php echo esc_url(WPDB_PLUGIN_URL . "/assets/images/icon_loading.gif"); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>">
		<h5 class="text-success"><strong><?php echo esc_html__('Backup process is working in background, it may take some time depending on size of your
				website. You can close this tab if you want', 'wpdbbkp') ?></strong></h5>
		<div class="progress">
			<div id="wpdbbkp_progressbar" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
				style="width:0%">
				0%
			</div>
		</div>
		<h4 class="text-success" id="wpdbbkup_process_stats"><?php echo esc_html__('Processing...', 'wpdbbkp') ?></h4>
	</div>
</div>

<div id="backup_process" style="display:none">
	<div class="text-center"><img width="50" height="50" src="<?php echo esc_url(WPDB_PLUGIN_URL . "/assets/images/icon_loading.gif"); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>">
		<h4 class="text-success" id="wpdbbkup_process_stats"><?php echo esc_html__('Creating Database Backup...', 'wpdbbkp') ?></h4>
		<h5 class="text-success"><strong><?php echo esc_html__('It may take some time depending on size of your
				Database. Do not close this window.', 'wpdbbkp') ?></strong></h5>
	</div>
</div>