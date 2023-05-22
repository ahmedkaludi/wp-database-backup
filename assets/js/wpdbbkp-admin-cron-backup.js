
var wpdbbkp_interval=false;
jQuery(document).ready(function($){
	
	$(document).on('click', '#wpdbbkp-create-full-backup', function(e){
		e.preventDefault();
		$('#wpdb-backup-process').show();
		$(this).attr('disabled', true);
		$.ajax({
			type: 'POST',
			url: wpdbbkp_localize_admin_data.ajax_url,
			data: {action: 'wpdbbkp_start_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
			success: function(response){
				response = JSON.parse(response);
				if(response.status=='success'){
					wpdbbkp_interval=setInterval(wpdbbkp_show_progress, 3000);
				}else {
					jQuery('#wpdbbkup_process_stats').text('Unable to start Backup, Please refresh the page');
				}	
			}
		});
			
	});
	$.ajax({
			type: 'POST',
			url: wpdbbkp_localize_admin_data.ajax_url,
			data: {action: 'wpdbbkp_check_fullbackup_stat', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
			success: function(response){
				response = JSON.parse(response);
				if(response.status=='active'){
					$('#wpdbbkp-create-full-backup').attr('disabled', true);
					$('#wpdb-backup-process').show();
					wpdbbkp_interval=setInterval(wpdbbkp_show_progress, 3000);
				}	
			}
		});
	
	
});

function wpdbbkp_show_progress(){
	jQuery.ajax({
		type: 'POST',
		url: wpdbbkp_localize_admin_data.ajax_url,
		data: {action: 'wpdbbkp_get_progress', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
		success: function(response){
			response = JSON.parse(response);
			if(response.status=='success'){
			var status = response.backupcron_step+' : '+response.backupcron_current;
			var progress = response.backupcron_progress;
			jQuery('#wpdbbkup_process_stats').text(status);
			jQuery('#wpdbbkp_progressbar').prop('aria-valuenow',progress);
			jQuery('#wpdbbkp_progressbar').text(progress+'%');
			jQuery('#wpdbbkp_progressbar').css('width',progress+'%');
			if(progress==100){
				clearInterval(wpdbbkp_interval);
				location.href=response.redirect_url;
			}
			}
			else{
				jQuery('#wpdbbkup_process_stats').text(response.msg);
			}

		}
	});
}