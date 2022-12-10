<?php
/**
 * Show header notification in dashboard
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
$notifier_file_url = NOTIFIER_XML_FILE_WPDB;
$changelog_msg     = '';
$notifier_data     = '';
$result            = array();

$change_message = "<strong><a href='https://www.wpseeds.com/product/wp-all-backup/' target='_blank'>WP All Backup Plugin.</a></strong> will backup and restore your entire site at will,
                        complete with FTP & S3 integration";
$coupon         = "Use Coupon code <strong>'WPDBSPECIAL40'</strong> and Get Flat 40% off on <strong><a href='https://www.wpseeds.com/product/wp-all-backup/' target='_blank'>WP All Backup Plugin.</a></strong>";

$url     = $notifier_file_url;
$request = new WP_Http();
$result  = (array) $request->request( $url );




// Load the remote XML data into a variable and return it.
$xml = simplexml_load_string( $notifier_data );
if ( ! empty( $xml ) ) {
	$changelog_msg = '';
	if ( true === isset( $xml->message ) && ! empty( $xml->message ) ) {
		$change_message = $xml->message;
	}
	if ( true === isset( $xml->coupon ) && ! empty( $xml->coupon ) ) {
		$coupon = $xml->coupon;
	}
	if ( true === isset( $xml->newrele ) && ! empty( $xml->newrele ) ) {
		$$changelog_msg .= "<li class='list-group-item' >" . $xml->newrelease . '<li>';
	}
	if ( WPDB_VERSION === $xml->latest ) {
		$alert     = '<strong>No Alert</strong><br/>';
		$changelog = '';
	} else {
		if ( true === isset( $xml->latest ) ) {
			$alert = '<strong><a href="https://www.wpseeds.com/blog/category/update/" title="Change Log" target="_blank">Plugin Updates</a></strong><br/>
                <strong>There is a new version of the <br/>WP Database Backup plugin available.</strong>
                 You have version ' . WPDB_VERSION . ' Update to version ' . $xml->latest . '.';
		}
		if ( true === isset( $xml->changelog ) ) {
			$changelog = $xml->changelog;
		}

		$changelog_msg .= '<li class="list-group-item" ><strong>New Version Availabel</strong></li>';

		echo '<style>.glyphicon.glyphicon-bell {
                    color: red !important;
                }</style>';
	}
} else {
	$alert     = '<strong>No Alert</strong><br/>';
	$changelog = '';
}
$changelog_msg .= "<li class='list-group-item'>" . $change_message . '<li>';
$changelog_msg .= "<li class='list-group-item'>" . $coupon . '<li>';
?>
		<?php if ( true === isset( $_GET['notification'] ) ) { ?>
	<div class="row">
		<div class="col-xs-12 col-sm-12 col-md-12">
		<div class="alert alert-success alert-dismissible fade in" role="alert">
	<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
			<?php
			if ( true === isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wp-database-backup' ) ) {
				if ( 'create' === $_GET['notification'] ) {
					$backup_list     = get_option( 'wp_db_backup_backups' );
					$download_backup = end( $backup_list );
					$backup_link     = '<a href="' . esc_url( $download_backup['url'] ) . '" style="color: #21759B;">' . __( 'Click Here to Download Backup.', 'wpdbbkp' ) . '</a>';
					esc_attr_e( 'Database Backup Created Successfully. ', 'wpdbbkp' );
					echo wp_kses_post( $backup_link );
				} elseif ( 'restore' === $_GET['notification'] ) {
					esc_attr_e( 'Database Backup Restore Successfully', 'wpdbbkp' );
				} elseif ( 'delete' === $_GET['notification'] ) {
					esc_attr_e( 'Database Backup deleted Successfully', 'wpdbbkp' );
				} elseif ( 'clear_temp_db_backup_file' === $_GET['notification'] ) {
					esc_attr_e( 'Clear all old/temp database backup files Successfully', 'wpdbbkp' );
				} elseif ( 'Invalid' === $_GET['notification'] ) {
					esc_attr_e( 'Invalid Access!!!!', 'wpdbbkp' );
				} elseif ( 'deleteauth' === $_GET['notification'] ) {
					esc_attr_e( 'Dropbox account unlink Successfully', 'wpdbbkp' );
				} elseif ( 'save' === $_GET['notification'] ) {
					esc_attr_e( 'Backup Setting Saved Successfully', 'wpdbbkp' );
				}
			}
			?>
		</div>
	</div>
</div>
<?php } ?>
<div class="row">
<div class="col-xs-8 col-sm-8 col-md-8">
	<img id="backup_process" style="display:none" width="50" height="50" src="<?php echo esc_url( WPDB_PLUGIN_URL ); ?>/assets/images/icon_loading.gif">
</div>
	<div class="col-xs-4 col-sm-4 col-md-4 text-right">

		<!-- Single button -->
		<div class="btn-group">
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<span class="glyphicon glyphicon-envelope" aria-hidden="true"></span> <span class="caret"></span>
			</button>
			<ul class="dropdown-menu pull-right">
				<?php echo wp_kses_post( $changelog_msg ); ?>
			</ul>
		</div>
		<!-- Single button -->
		<div class="btn-group">
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<span class="glyphicon glyphicon-bell" aria-hidden="true"></span> <span class="caret"></span>
			</button>
			<ul class="dropdown-menu pull-right list-group">
				<li  class="list-group-item "><?php echo wp_kses_post( $alert ); ?></li>
				<?php if ( ! empty( $changelog ) ) { ?>
					<li  class="list-group-item "><?php echo wp_kses_post( $changelog ); ?></li>
<?php } ?>
			</ul>
		</div>

		<!-- Single button Setting-->
		<div class="btn-group">
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<span class="glyphicon glyphicon-wrench" aria-hidden="true"></span> <span class="caret"></span>
			</button>
			<ul class="dropdown-menu pull-right">

				<li role="separator" class="divider"></li>
				<li>
					<a href="#" >
						<p><?php esc_attr_e( 'Schedule :', 'wpdbbkp' ); ?>
							<?php
							$settings = get_option( 'wp_db_backup_options' );
							if ( isset( $settings['enable_autobackups'] ) && '1' === $settings['enable_autobackups'] ) {
								esc_attr_e( 'Enabled -', 'wpdbbkp' );
								$autobackup_frequency = ucfirst( $settings['autobackup_frequency'] );
								echo esc_attr( $autobackup_frequency );
							} else {
								esc_attr_e( 'Disabled', 'wpdbbkp' );
							}
							?>
							</p>
					</a>
				</li>
				<li role="separator" class="divider"></li>
				<li >
					<a href="#" >
						<p><?php esc_attr_e( 'Exclude Tables :', 'wpdbbkp' ); ?></p>
						<?php
						$wp_db_exclude_table = array();
						$wp_db_exclude_table = (array) get_option( 'wp_db_exclude_table' );
						if ( false === empty( $wp_db_exclude_table ) ) {
							echo wp_kses_post( implode( ',<br> ', $wp_db_exclude_table ) );
						}
						?>
			</p>

					</a>
				</li>
				<li role="separator" class="divider"></li>
				<li><a href="#">
						<a href="#" >
							<p><?php esc_attr_e( 'Keep No of backup :', 'wpdbbkp' ); ?>
								<?php
								if ( 0 === (int) get_option( 'wp_local_db_backup_count' ) ) {
									esc_attr_e( 'Unlimited', 'wpdbbkp' );
								} else {
									echo esc_attr( get_option( 'wp_local_db_backup_count' ) );
								}
								?>
								</p>
						</a>
					</a>
				</li>
				<li role="separator" class="divider"></li>
				<li>
					<a href="#" >
						<p><?php esc_attr_e( 'Backup Log :', 'wpdbbkp' ); ?>
							<?php
							if ( 1 === (int) get_option( 'wp_db_log' ) ) {
								esc_attr_e( 'Enabled', 'wpdbbkp' );
							} else {
								esc_attr_e( 'Disabled', 'wpdbbkp' );
							}
							?>
							</p>
					</a>
				</li>
				<li role="separator" class="divider"></li>
				<li>
					<a href="#db_setting" data-toggle="tab" title="<?php esc_attr_e( 'Change Setting', 'wpdbbkp' ); ?>"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span> <?php esc_attr_e( 'Change Setting', 'wpdbbkp' ); ?></a>
				</li>
			</ul>
		</div>

		<!-- Single button Author-->
		<div class="btn-group">
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<span class="glyphicon glyphicon-user" aria-hidden="true"></span> <span class="caret"></span>
			</button>
			<ul class="dropdown-menu pull-right">
				<li>
					<a href="http://walkeprashant.in" target="_blank" >
						<h5 ><?php esc_attr_e( 'Plugin Author', 'wpdbbkp' ); ?></h5>
						<p><?php esc_attr_e( 'Prashant Walke', 'wpdbbkp' ); ?></p>
						<p><?php esc_attr_e( '(Sr. PHP Developer)', 'wpdbbkp' ); ?></p>
					</a>
				</li>
				<li role="separator" class="divider"></li>
				<li >
					<a href="http://www.wpseeds.com/wp-database-backup/" target="_blank" >
						<h5 ><?php esc_attr_e( 'Plugin URL', 'wpdbbkp' ); ?></h5>
					</a>
				</li>
				<li >
					<a href="http://www.wpseeds.com/blog/category/update/wp-database-backup/" target="_blank" >
						<h5 ><?php esc_attr_e( 'Change Log', 'wpdbbkp' ); ?> </h5>
					</a>
				</li>
				<li >
					<a href="https://www.wpseeds.com/documentation/docs/wp-database-backup/" target="_blank" >
						<h5 ><?php esc_attr_e( 'Documentation', 'wpdbbkp' ); ?></h5>
					</a>
				</li>
				<li >
					<a href="https://www.wpseeds.com/support/" target="_blank" >
						<h5 ><?php esc_attr_e( 'Support', 'wpdbbkp' ); ?></h5>
					</a>
				</li>
			</ul>
		</div>

	</div>
</div>
