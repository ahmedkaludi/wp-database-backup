<?php
/**
 * Destination form.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
ob_start();

$update_msg = '';
if ( isset( $_POST['wpdb_google_drive'] ) && 'Y' === $_POST['wpdb_google_drive'] ) {
	// Validate that the contents of the form request came from the current site and not somewhere else added 21-08-15 V.3.4.
	if ( ! isset( $_POST['wpdbbackup_update_google_setting'] ) ) {
		die( '<br><br>Invalid form data. form request came from the somewhere else not current site!' );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdbbackup_update_google_setting'] ) ), 'wpdbbackup-update-google-setting' ) ) {
		die( '<br><br>Invalid form data. form request came from the somewhere else not current site! ' );
	}
	$client_id     = '';
	$client_secret = '';
	if ( isset( $_POST['Save'] ) && 'Save' === $_POST['Save'] ) {
		if ( true === isset( $_POST['wpdb_dest_google_client_key'] ) ) {
			$client_id = sanitize_text_field( wp_unslash( $_POST['wpdb_dest_google_client_key'] ) );
		}
		if ( true === isset( $_POST['wpdb_dest_google_secret_key'] ) ) {
			$client_secret = sanitize_text_field( wp_unslash( $_POST['wpdb_dest_google_secret_key'] ) );
		}
		update_option( 'wpdb_dest_google_client_key', wp_db_filter_data( $client_id ) );
		update_option( 'wpdb_dest_google_secret_key', wp_db_filter_data( $client_secret ) );
	} elseif ( isset( $_POST['Submit'] ) && 'Allow Access' === $_POST['Submit'] ) {
		// Save the posted value in the database.
		if ( true === isset( $_POST['wpdb_dest_google_client_key'] ) ) {
			$client_id = sanitize_text_field( wp_unslash( $_POST['wpdb_dest_google_client_key'] ) );
		}
		if ( true === isset( $_POST['wpdb_dest_google_secret_key'] ) ) {
			$client_secret = sanitize_text_field( wp_unslash( $_POST['wpdb_dest_google_secret_key'] ) );
		}
		update_option( 'wpdb_dest_google_client_key', wp_db_filter_data( $client_id ) );
		update_option( 'wpdb_dest_google_secret_key', wp_db_filter_data( $client_secret ) );

		require_once 'google-api-php-client/src/Google_Client.php';
		require_once 'google-api-php-client/src/contrib/Google_DriveService.php';

		$client = new Google_Client();
		// Get your credentials from the APIs Console.
		$client->setClientId( $client_id );
		$client->setClientSecret( $client_secret );
		$client->setRedirectUri( site_url() . '/wp-admin/tools.php?page=wp-database-backup&action=auth' );
		$client->setScopes( array( 'https://www.googleapis.com/auth/drive' ) );

		$service = new Google_DriveService( $client );

		$auth_url = $client->createAuthUrl();
		if ( isset( $_GET['code'] ) ) {
			update_option( 'wpdb_dest_google_authCode', wp_db_filter_data( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ) );
		} else {
			if ( isset( $_POST['wpdb_dest_google_client_key'] ) && ! empty( $_POST['wpdb_dest_google_client_key'] ) && isset( $_POST['wpdb_dest_google_secret_key'] ) && ! empty( $_POST['wpdb_dest_google_secret_key'] ) ) {
				wp_safe_redirect( filter_var( $auth_url, FILTER_SANITIZE_URL ) );
			}
		}
	} elseif ( isset( $_POST['reset'] ) && 'Reset Configure' === $_POST['reset'] ) {
		update_option( 'wpdb_dest_google_authCode', '' );
		wp_safe_redirect( esc_url( site_url() . '/wp-admin/tools.php?page=wp-database-backup' ) );
	}

	// Put a "settings updated" message on the screen.
	$update_msg = '<div class="updated"><p><strong>Your google drive setting has been saved.</strong></p></div>';
}
if ( isset( $_GET['code'] ) ) {
	update_option( 'wpdb_dest_google_authCode', wp_db_filter_data( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ) );
}
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title">
			<a data-toggle="collapse" data-parent="#accordion" href="#collapsegoogle">
				<h2>Google drive</h2>
			</a>
		</h4>
	</div>
	<div id="collapsegoogle" class="panel-collapse collapse in">
		<div class="panel-body">
			<?php echo esc_html( $update_msg ); ?>
			<form  class="form-group" name="googledrive" method="post" action="">
				<input type="hidden" name="wpdb_google_drive" value="Y">
				<input name="wpdbbackup_update_google_setting" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpdbbackup-update-google-setting' ) ); ?>" />
				<?php
				wp_nonce_field( 'wp-database-backup' );
				$wpdb_dest_google_auth_code  = get_option( 'wpdb_dest_google_authCode' );
				$wpdb_dest_google_client_key = get_option( 'wpdb_dest_google_client_key' );
				$wpdb_dest_google_secret_key = get_option( 'wpdb_dest_google_secret_key' );
				if ( ! empty( $wpdb_dest_google_auth_code ) && ! empty( $wpdb_dest_google_client_key ) && ! empty( $wpdb_dest_google_secret_key ) ) {
					?>
					<p class="text-success">Configuration to Google Drive Access has been done successfully</p>
					<p>By clicking reset, you can reconfigure Google Account</p>
					<p>For local backup click on Reset Configure</p>
					<p><input type="submit" name="reset" class="btn btn-primary" value="<?php esc_attr_e( 'Reset Configure' ); ?>" />&nbsp;
					</p>
				<?php } else { ?>

					<p><a href="http://www.wpseeds.com/wp-database-backup/#google" target="_blank"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a> Back up WordPress database to google drive.</p>
					<p>Configure google account, you need to create Client ID &amp; Client secret from the API section '<a href="https://code.google.com/apis/console/" target="_blank">Google API Console</a>' also use authorization redirecting url as <br>
						<strong> <?php echo esc_url( site_url() . '/wp-admin/tools.php?page=wp-database-backup&action=auth' ); ?></strong></p>
					<p>For local backup leave the setting as it is</p>

					<div class="row form-group">
						<label class="col-sm-2" for="wpdb_dest_google_client_key">Client ID</label>
						<div class="col-sm-6">
							<input type="text" id="wpdb_dest_google_client_key" class="form-control" name="wpdb_dest_google_client_key" value="<?php echo esc_html( get_option( 'wpdb_dest_google_client_key' ) ); ?>" size="25" placeholder="your client id">
						</div>
					</div>

					<div class="row form-group">
						<label class="col-sm-2" for="wpdb_dest_google_secret_key">Client secret:</label>
						<div class="col-sm-6">
							<input type="text" id="wpdb_dest_google_secret_key" class="form-control" name="wpdb_dest_google_secret_key" value="<?php echo esc_html( get_option( 'wpdb_dest_google_secret_key' ) ); ?>" size="25" placeholder="your client secret key">
						</div>
					</div>

					<p><input type="submit" name="Submit" class="btn btn-primary" value="<?php esc_attr_e( 'Allow Access' ); ?>" />&nbsp;
						<input type="submit" name="Save" class="btn btn-secondary" value="<?php esc_attr_e( 'Save' ); ?>" />&nbsp;
					</p>
				<?php } ?>
			</form>
		</div>
	</div>
</div>
