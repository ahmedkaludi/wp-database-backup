<?php 
$reasons = array(
    	1 => '<li><label><input type="radio" name="wpdbbkp_disable_reason" value="temporary"/>' . esc_html__('It is only temporary', 'wpdbbkp') . '</label></li>',
		2 => '<li><label><input type="radio" name="wpdbbkp_disable_reason" value="stopped"/>' . esc_html__('I stopped using WP Database Backup on my site', 'wpdbbkp') . '</label></li>',
		3 => '<li><label><input type="radio" name="wpdbbkp_disable_reason" value="missing"/>' . esc_html__('I miss a feature', 'wpdbbkp') . '</label></li>
		<li><input type="text" class="mb-box missing" name="wpdbbkp_disable_text[]" value="" placeholder="'. esc_attr__('Please describe the feature','wpdbbkp').'"/></li>',
		4 => '<li><label><input type="radio" name="wpdbbkp_disable_reason" value="technical"/>' . esc_html__('Technical Issue', 'wpdbbkp') . '</label></li>
		<li><textarea  class="mb-box technical" name="wpdbbkp_disable_text[]" placeholder="'. esc_attr__('How Can we help? Please describe your problem', 'wpdbbkp').'"></textarea></li>',
		5 => '<li><label><input type="radio" name="wpdbbkp_disable_reason" value="another"/>' . esc_html__('I switched to another plugin', 'wpdbbkp') .  '</label></li>
		<li><input type="text"  class="mb-box another" name="wpdbbkp_disable_text[]" value="" placeholder="'. esc_attr__('Name of the plugin','wpdbbkp').'"/></li>',
		6 => '<li><label><input type="radio" name="wpdbbkp_disable_reason" value="other"/>' . esc_html__('Other reason', 'wpdbbkp') . '</label></li>
		<li><textarea  class="mb-box other" name="wpdbbkp_disable_text[]" placeholder="'. esc_attr__('Please specify, if possible', 'wpdbbkp').'"></textarea></li>',
    );
shuffle($reasons);
?>


<div id="wpdbbkp-feedback-overlay" style="display: none;">
    <div id="wpdbbkp-feedback-content">
	<form action="" method="post">
	    <h3><strong><?php echo esc_html__('If you have a moment, please let us know why you are deactivating:', 'wpdbbkp'); ?></strong></h3>
	    <ul>
                <?php 
                foreach ($reasons as $reason){
                    echo wp_kses_post($reason);
                }
                ?>
	    </ul>
	    <?php if ($email) : ?>
    	    <input type="hidden" name="wpdbbkp_disable_from" value="<?php echo esc_attr($email); ?>"/>
	    <?php endif; ?>
	    <input id="wpdbbkp-feedback-submit" class="button button-primary" type="submit" name="wpdbbkp_disable_submit" value="<?php echo esc_html__('Submit & Deactivate', 'wpdbbkp'); ?>"/>
	    <a class="button"><?php echo esc_html__('Only Deactivate', 'wpdbbkp'); ?></a>
	    <a class="wpdbbkp-feedback-not-deactivate" href="#"><?php echo esc_html__('Don\'t deactivate', 'wpdbbkp'); ?></a>
	</form>
    </div>
</div>