<?php
add_action( 'wpdbbkp_backup_completed', array( 'WPDBFullBackupLog', 'wpdbbkp_backup_completed' ), 11 );

/**
 * Class WPDBFullBackupLog
 *
 * Handles logging and updating backup completion details.
 */
class WPDBFullBackupLog {

    /**
     * Processes backup completion details and updates options.
     *
     * @param array $args Backup completion arguments.
     */
    public static function wpdbbkp_backup_completed( &$args ) {
        global $wp_filesystem;

        // Ensure WP Filesystem is loaded
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        // Retrieve existing options
        $options = get_option( 'wp_db_backup_backups' );
        $new_options = array();

        if ( ! empty( $options ) && is_array( $options ) ) {
            foreach ( $options as $option ) {
                if ( ! is_array( $option ) ) {
                    continue;
                }
                if ( $option['filename'] === $args[0] ) {
                    $option['destination'] = wp_kses( $args[4], wp_kses_allowed_html( 'post' ) );
                }
                $new_options[] = $option;
            }
        }

        $new_options = wpdbbkp_filter_unique_filenames( $new_options );

        // Update the options
        update_option( 'wp_db_backup_backups', $new_options, false );

        // Log to file if logging is enabled
        if ( get_option( 'wp_db_log' ) == 1 ) {
            if ( isset( $args[5] ) && ! empty( $args[5] ) ) {
                if ( $wp_filesystem->is_writable( $args[5] ) || ! $wp_filesystem->exists( $args[5] ) ) {
                    $log_content = str_replace( '<br>', "\n", $args[2] );

                    // Append to the log file
                    if ( false === $wp_filesystem->put_contents( $args[5], $log_content, FS_APPEND ) ) {
                        // Handle the error if needed
                        return false;
                    }

                    return true;
                }
            }
        }
    }
}
