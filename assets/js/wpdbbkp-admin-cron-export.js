function handleNavigateChildTab(event,type){
	let msubtab = document.querySelectorAll('.msub-tab');
	for (let index = 0; index < msubtab.length; index++) {
		const element = msubtab[index];
		element.classList.remove('active');
	}
	document.getElementById('msub-tab-'+type).classList.add('active');
	let msubblock = document.querySelectorAll('.msub-tab-block');
	for (let index = 0; index < msubblock.length; index++) {
		const element = msubblock[index];
		element.style.display = 'none';
	}
	window.location.href ='#tab_db_migrate';
	document.getElementById('msub-tab-block-'+type).style.display='';
}
function countSlices(fileSize, chunkSize) {
    return Math.ceil(fileSize / chunkSize);
}
document.getElementById('wpdbbkp-upload-import').addEventListener('change', function (event) {
	
    const file = event.target.files[0];
    if (!file) return;
	var filename = file.name;
	document.getElementById('imported-file-name').innerHTML = filename;
	document.getElementById('wpdbbkp-start-full-import').style.display='';
});

	jQuery(document).ready(function($) {
		let chunkSize = 2 * 1024 * 1024; // 2MB chunk size
		let fileInput = $('#wpdbbkp-upload-import');
		//let uploadProgress = $('#uploadProgress');
		let file;

		$('#wpdbbkp-start-full-import').click(function() {
			if (fileInput[0].files.length === 0) {
				alert("Please select a ZIP file to upload.");
				return;
			}

			file = fileInput[0].files[0];
			uploadFileInChunks(0);
			//finalizeExtractProcess();
			//checkExtractStatus();
		});
		
		
		function uploadFileInChunks(offset) {
			let chunk = file.slice(offset, offset + chunkSize);
			let formData = new FormData();
			formData.append("action", "wpdbbkp_upload_site_chunk");
			formData.append("file", chunk);
			formData.append("fileName", file.name);
			formData.append("offset", offset);
			formData.append("wpdbbkp_admin_security_nonce", wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce);
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				contentType: false,
				processData: false,
				success: function(response) {
					if (response.success) {
						let nextOffset = offset + chunkSize;
						let progress = Math.min((nextOffset / file.size) * 100, 100).toFixed(2);
						
						document.getElementById('wpdb-import-process').style.display = '';
						document.getElementById('wpdbbkp_import_progressbar').style.width = progress + '%';
						document.getElementById('wpdbbkp_import_progressbar').innerHTML = progress + '%';
						if (nextOffset < file.size) {
							uploadFileInChunks(nextOffset);
						} else {
							finalizeExtractProcess();
						}
					} else {
						//uploadProgress.html("Upload failed: " + response.data);
					}
				},
				error: function() {
					//uploadProgress.html("Chunk upload failed.");
				}
			});
		}

		function finalizeExtractProcess(){
			checkExtractStatus();
			document.getElementById('wpdbbkp_import_progressbar').style.width =  '100%';
			document.getElementById('wpdbbkp_import_progressbar').innerHTML =  'Upload Completed ! Working on extraction process, it may take more time depending upon uploaded file size, be patience utnill process completes';
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: { action: "wpdbbkp_extract_uploaded_site", fileName: file.name,wpdbbkp_admin_security_nonce: wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce },
				success: function(response) {
				
					document.getElementById('wpdbbkp_import_progressbar').innerHTML =  'Extraction & Restore Completed!';
					setTimeout(() => {
						window.location.reload();
					}, 1500);
				
				},
				error: function() {
					
				}
			});
		}
		function checkExtractStatus(){
			document.getElementById('wpdb-import-process').style.display = '';
			//document.getElementById('wpdbbkp_import_progressbar').style.width =  '100%';
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: { action: "wpdbbkp_check_extract_status",wpdbbkp_admin_security_nonce: wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
				success: function(response) {
					if (response.success) {
						document.getElementById('wpdbbkup_import_process_stats').innerHTML = response.data.message;
						if(response.data.message!=='Process Completed'){
							checkExtractStatus();
						}
					}
				},
				error: function() {
					
				}
			});
		}
	});
jQuery(document).ready(function($){
	
	$(document).on('click', '#wpdbbkp-create-full-import', function(e){
		document.getElementById('wpdbbkp-upload-import').click();
	});
	$('#wpdbbkp-create-full-export').attr('disabled', true);
	$(document).on('click', '#wpdbbkp-create-full-export', function(e){
		e.preventDefault();
        $('#wpdb-export-process').show();
        $('.wpdbbkp_notification').hide();
        $(this).attr('disabled', true);
        $.ajax({
            type: 'POST',
            url: wpdbbkp_localize_admin_data.ajax_url,
            data: {action: 'wpdbbkp_start_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
            success: function(response){
                response = JSON.parse(response);
                if(response.status=='success'){
                    setTimeout(wpdbbkp_show_export_progress, 3000);
                    $('#wpdbbkp-stop-full-export').show();
                }else {
                    jQuery('#wpdbbkup_export_process_stats').text('Unable to start Backup, Please refresh the page');
                }	
            }
        });
	});
	$(document).on('click', '#wpdbbkp-stop-full-export', function(e){
		e.preventDefault();
		$('.wpdbbkp_notification').hide();
		$('#wpdbbkup_export_process_stats').text('Cancelling Backup, Please Wait');
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
					jQuery('#wpdbbkup_export_process_stats').text('Refresh the page and try again');
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
					$('#wpdbbkp-create-full-export').attr('disabled', true);
					$('#wpdb-export-process').show();
					setTimeout(wpdbbkp_show_export_progress, 3000);
					$('#wpdbbkp-stop-full-export').show();
				}else {
					$('#wpdbbkp-create-full-export').attr('disabled', false);
					$('#wpdbbkp-stop-full-export').hide();
				}	
			}
		});	
});

function wpdbbkp_show_export_progress(){
	jQuery.ajax({
		type: 'POST',
		url: wpdbbkp_localize_admin_data.ajax_url,
		data: {action: 'wpdbbkp_get_progress', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
		success: function(response){
			response = JSON.parse(response);
			if(response.status=='success'){
			var status = response.backupcron_step+' : '+response.backupcron_current;
			var progress = response.backupcron_progress;
			jQuery('#wpdbbkup_export_process_stats').text(status);
			jQuery('#wpdbbkp_export_progressbar').prop('aria-valuenow',progress);
			jQuery('#wpdbbkp_export_progressbar').text(progress+'%');
			jQuery('#wpdbbkp_export_progressbar').css('width',progress+'%');
			if(progress==100){
				let redirect_url = response.redirect_url;
				redirect_url = redirect_url.replaceAll('&#038;','&');
				redirect_url = redirect_url.replaceAll('#038;','&');
				location.href=redirect_url+'#tab_db_migrate';
			}
			setTimeout(wpdbbkp_show_export_progress, 5000);
			}
			else{
				setTimeout(wpdbbkp_show_export_progress, 5000);
				jQuery('#wpdbbkup_export_process_stats').text(response.msg);
			}

		}
	});
}

function  bkpforwp_cron_start(){
	jQuery('#wpdb-export-process').show();
	jQuery('.wpdbbkp_notification').hide();
	jQuery(this).attr('disabled', true);
	jQuery.ajax({
		type: 'POST',
		url: wpdbbkp_localize_admin_data.ajax_url,
		data: {action: 'wpdbbkp_start_cron_manual', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
		success: function(response){
			response = JSON.parse(response);
			if(response.status=='success'){
			 setTimeout(wpdbbkp_show_export_progress, 3000);
			 jQuery('#wpdbbkp-stop-full-export').show();
			}else {
				jQuery('#wpdbbkup_export_process_stats').text('Unable to start Backup, Please refresh the page');
			}	
		}
	});
}