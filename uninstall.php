<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}
$wp_db_backup_backups_dir = get_option('wp_db_backup_backups_dir');
$wp_db_remove_on_uninstall = get_option('wp_db_remove_on_uninstall',false);

if($wp_db_remove_on_uninstall){ 
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
        'wp_db_backup_backups_dir'
    );

    foreach ($options_to_remove as $option) {
        delete_option($option);
    }

    // Remove backup files
    $default_dir = WP_CONTENT_DIR . '/uploads/db-backup';
    $custom_dir = $wp_db_backup_backups_dir;
    
    // remove our default / temporay directory
    if (is_dir($default_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            $file_path = $backup_dir . '/' . $file;
            if (is_file($file_path)) {
                unlink($file_path);
            }
        }
        // Remove the directory itself
        rmdir($backup_dir);
    }
 // remove custom directory if any  
    if (is_dir($custom_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            $file_path = $backup_dir . '/' . $file;
            if (is_file($file_path)) {
                unlink($file_path);
            }
        }
        // Remove the directory itself
        rmdir($backup_dir);
    }

}