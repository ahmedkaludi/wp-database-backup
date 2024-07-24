<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

// Function to delete options for a specific site
function wp_db_delete_options_for_site() {
    $wp_db_remove_on_uninstall = get_option('wp_db_remove_on_uninstall', false);

    if ($wp_db_remove_on_uninstall) { 
        // Remove options
        $options_to_remove = array(
            'wp_db_backup_destination_SFTP',
            'wp_db_backup_destination_FTP',
            'wp_db_backup_destination_Email',
            'wp_db_backup_destination_s3',
            'wp_db_remove_local_backup',
            'wp_db_remove_on_uninstall',
            'wp_db_backup_backup_type',
            'wp_db_backup_exclude_dir',
            'wp_db_backup_backups_dir',
            'wp_db_backup_sftp_details',
            'wpdb_dest_bb_s3_bucket',
            'wpdb_dest_bb_s3_bucket_key',
            'wpdb_dest_bb_s3_bucket_secret',
            'wpdb_dest_bb_s3_bucket_host',
            'wp_db_backup_destination_bb',
            'wp_db_backup_backups',
            'wp_db_backup_options'
        );

        foreach ($options_to_remove as $option) {
            delete_option($option);
        }
    }
}

if (is_multisite()) {
    // Get all sites in the network
    $sites = get_sites();
    if(!empty($sites) && is_array($sites)) {
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            wp_db_delete_options_for_site();
            restore_current_blog();
        }
    }

} else {
    // Single site
    wp_db_delete_options_for_site();
}
