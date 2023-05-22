<?php
/**
 * Show header notification in dashboard
 *
 * @package wpdbbkp
 */

if (!defined('ABSPATH')) {
	exit;
} // Exit if accessed directly
$loader_gif = esc_url(WPDB_PLUGIN_URL) . "/assets/images/icon_loading.gif";

if (true === isset($_GET['notification'])) { ?>
	<div class="row wpdbbkp_notification_row">
		<div class="col-xs-12 col-sm-12 col-md-12">
			<div class="alert alert-success alert-dismissible fade in" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
						aria-hidden="true">×</span></button>
				<div class="wpdbbkp_notification">
					<?php
					if (true === isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'wp-database-backup')) {
						if ('create' === $_GET['notification']) {
							$backup_list = get_option('wp_db_backup_backups');
							$download_backup = end($backup_list);
							$backup_link = '<a href="' . esc_url($download_backup['url']) . '" style="color: #21759B;">' . __('Click Here to Download Backup.', 'wpdbbkp') . '</a>';
							esc_attr_e('Database Backup Created Successfully. ', 'wpdbbkp');
							echo wp_kses_post($backup_link);
						} elseif ('restore' === $_GET['notification']) {
							esc_attr_e('Database Backup Restore Successfully', 'wpdbbkp');
						} elseif ('delete' === $_GET['notification']) {
							esc_attr_e('Database Backup deleted Successfully', 'wpdbbkp');
						} elseif ('clear_temp_db_backup_file' === $_GET['notification']) {
							esc_attr_e('Clear all old/temp database backup files Successfully', 'wpdbbkp');
						} elseif ('Invalid' === $_GET['notification']) {
							esc_attr_e('Invalid Access!!!!', 'wpdbbkp');
						} elseif ('deleteauth' === $_GET['notification']) {
							esc_attr_e('Dropbox account unlink Successfully', 'wpdbbkp');
						} elseif ('save' === $_GET['notification']) {
							esc_attr_e('Backup Setting Saved Successfully', 'wpdbbkp');
						}
					}
					?>
				</div>
			</div>
		</div>
	</div>
<?php } ?>


<div class="row">
	<div class="col-xs-8 col-sm-8 col-md-8">
		<img id="backup_process" style="display:none" width="50" height="50"
			src="<?php echo esc_url(WPDB_PLUGIN_URL); ?>/assets/images/icon_loading.gif">
	</div>
	<div class="col-xs-4 col-sm-4 col-md-4 text-right">

	</div>
</div>

<div id="wpdb-backup-process" style="display:none">
	<div class="text-center"><img width="50" height="50" src="<?php echo $loader_gif; ?>">
		<h5 class="text-success"><strong>Backup process started, it may take some time depending on size of your
				website. You can close this tab backup will continue in background</strong></h5>
		<div class="progress">
			<div id="wpdbbkp_progressbar" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
				style="width:0%">
				0%
			</div>
		</div>
		<h4 class="text-success" id="wpdbbkup_process_stats">Initializing...</h4>
	</div>
</div>