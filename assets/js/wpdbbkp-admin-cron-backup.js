jQuery(document).ready(function($){
	$('#wpdbbkp-create-full-backup').attr('disabled', true);
	$(document).on('click', '#wpdbbkp-create-full-backup', function(e){
		e.preventDefault();
		if(bkpforwp_token_check()){
			$('#wpdb-backup-process').show();
			$('.wpdbbkp_notification').hide();
			$(this).attr('disabled', true);
			$.ajax({
				type: 'POST',
				url: wpdbbkp_localize_admin_data.ajax_url,
				data: {action: 'wpdbbkp_start_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
				success: function(response){
					response = JSON.parse(response);
					if(response.status=='success'){
					 setTimeout(wpdbbkp_show_progress, 3000);
					 $('#wpdbbkp-stop-full-backup').show();
					}else {
						jQuery('#wpdbbkup_process_stats').text('Unable to start Backup, Please refresh the page');
					}	
				}
			});
		}else{
			let wpdbbkp_offer_modal = $('#wpdbbkp_offer_modal').modal({backdrop: true, keyboard: true});
			wpdbbkp_offer_modal.show();
		
		// When the user selects an option, set the cookie and hide the modal
		document.getElementById("wpdbbkp_server_backup").addEventListener("click", function() {
			wpdbbkp_offer_modal.hide();
			$('#wpdb-backup-process').show();
			$('.wpdbbkp_notification').hide();
			$(this).attr('disabled', true);
			$.ajax({
				type: 'POST',
				url: wpdbbkp_localize_admin_data.ajax_url,
				data: {action: 'wpdbbkp_start_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
				success: function(response){
					response = JSON.parse(response);
					if(response.status=='success'){
					 setTimeout(wpdbbkp_show_progress, 3000);
					 $('#wpdbbkp-stop-full-backup').show();
					}else {
						jQuery('#wpdbbkup_process_stats').text('Unable to start Backup, Please refresh the page');
					}	
				}
			});
		});

		document.getElementById("wpdbbkp_remote_backup").addEventListener("click", function() {
			window.location.href = wpdbbkp_localize_admin_data.home_url+'/wp-admin/admin.php?page=wp-database-backup#tab_db_upgrade';
			window.location.reload();
		});
			
	}
	});


	$(document).on('click', '#wpdbbkp-stop-full-backup', function(e){
		e.preventDefault();
		$('.wpdbbkp_notification').hide();
		$('#wpdbbkup_process_stats').text('Cancelling Backup, Please Wait');
		$(this).attr('disabled', true);
		$.ajax({
			type: 'POST',
			url: wpdbbkp_localize_admin_data.ajax_url,
			data: {action: 'wpdbbkp_stop_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
			success: function(response){
				response = JSON.parse(response);
				if(response.status=='success'){
					window.location.reload();
				}else {
					jQuery('#wpdbbkup_process_stats').text('Refresh the page and try again');
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
					setTimeout(wpdbbkp_show_progress, 3000);
					$('#wpdbbkp-stop-full-backup').show();
				}else {
					$('#wpdbbkp-create-full-backup').attr('disabled', false);
					$('#wpdbbkp-stop-full-backup').hide();
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
				let redirect_url = response.redirect_url;
				redirect_url = redirect_url.replaceAll('&#038;','&');
				redirect_url = redirect_url.replaceAll('#038;','&');
				location.href=redirect_url;
			}
			setTimeout(wpdbbkp_show_progress, 5000);
			}
			else{
				setTimeout(wpdbbkp_show_progress, 5000);
				jQuery('#wpdbbkup_process_stats').text(response.msg);
			}

		}
	});
}

function  bkpforwp_cron_start(){
	jQuery('#wpdb-backup-process').show();
	jQuery('.wpdbbkp_notification').hide();
	jQuery(this).attr('disabled', true);
	jQuery.ajax({
		type: 'POST',
		url: wpdbbkp_localize_admin_data.ajax_url,
		data: {action: 'wpdbbkp_start_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
		success: function(response){
			response = JSON.parse(response);
			if(response.status=='success'){
			 setTimeout(wpdbbkp_show_progress, 3000);
			 jQuery('#wpdbbkp-stop-full-backup').show();
			}else {
				jQuery('#wpdbbkup_process_stats').text('Unable to start Backup, Please refresh the page');
			}	
		}
	});
}