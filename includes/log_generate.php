<?php
add_action('wpdbbkp_backup_completed', array('WPDBFullBackupLog', 'wpdbbkp_backup_completed'), 11);

class WPDBFullBackupLog {

    public static function wpdbbkp_backup_completed(&$args) {
        
        $options = get_option('wp_db_backup_backups');
        $newoptions = array();
        $count = 0;

        if(!empty($options) && is_array($options)){

            foreach ($options as $option) {
                if ($option['filename'] == $args[0]) {
                    $newoptions[] = $option;
                    $newoptions['destination'] = wp_kses($args[4]);
                }else{
                        $newoptions[] = $option;
                }
                $count++;
            } 

        }
                                
        update_option('wp_db_backup_backups', $newoptions, false);

        if (get_option('wp_db_log') == 1) {
            if(isset($args[4]) && !empty($args[4]))
            {
                if (is_writable($args[5]) || !file_exists($args[5])) {

                    if (!$handle = @fopen($args[5], 'a'))
                        return;

                    if (!fwrite($handle,  str_replace("<br>", "\n", $args[2])))
                        return;

                    fclose($handle);

                    return true;
                }
            }
        }
    }

}