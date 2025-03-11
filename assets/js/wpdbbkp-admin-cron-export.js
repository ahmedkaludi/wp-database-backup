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
/* document.getElementById('wpdbbkp-upload-import').addEventListener('change', function (event) {
	document.getElementById('wpdbbkp-upload-import').setAttribute('disabled', true);
    const file = event.target.files[0];
    if (!file) return;
	var filename = file.name;
	document.getElementById('imported-file-name').innerHTML = filename;
    const chunkSize = 1024 * 1024; // 1MB
    let start = 0;
    let chunkIndex = 0;

    function uploadChunk() {
        if (start >= file.size) {
            finalizeUpload(file.name);
            return;
        }

        const chunk = file.slice(start, start + chunkSize);
        const formData = new FormData();
        formData.append("action", 'wpdbbkp_upload_chunk');
        formData.append("nonce", wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce);
        formData.append("file", chunk);
        formData.append("chunkIndex", chunkIndex);
        formData.append("fileName", file.name);

		const totalChunks = countSlices(file.size, chunkSize);
		let calculate_progress = 0;
		if(chunkIndex>0){
			calculate_progress = (chunkIndex / totalChunks) * 100;
		}
		//wpdbbkp_localize_admin_data.ajax_url
		//wpdbbkp_localize_admin_data.ajax_url+"/wp-json/wpdbbkp/v1/upload-chunk"
        fetch(wpdbbkp_localize_admin_data.ajax_url, {
            method: "POST",
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
			calculate_progress = Math.round(calculate_progress);
			document.getElementById('wpdb-import-process').style.display = '';
			document.getElementById('wpdbbkp_import_progressbar').style.width = calculate_progress + '%';
			document.getElementById('wpdbbkp_import_progressbar').innerHTML = calculate_progress + '%';
            if (data.success) {
                start += chunkSize;
                chunkIndex++;
                uploadChunk(); // Upload next chunk
            } else {
                console.error("Chunk upload failed", data);
            }
        })
        .catch(error => console.error("Error:", error));
    }

    uploadChunk();
});

function finalizeUpload(fileName) {
	let calculate_progress = 0;
	setInterval(() => {
		if(calculate_progress==101){
			calculate_progress = 0;
		}
		document.getElementById('wpdbbkp_import_progressbar').style.width = calculate_progress + '%';
		document.getElementById('wpdbbkp_import_progressbar').innerHTML = calculate_progress + '%';
		calculate_progress++;
	}, 1500);
	const formData = new FormData();
	formData.append("action", 'wpdbbkp_finalize_upload');
	formData.append("nonce", wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce);
	formData.append("fileName", fileName);
	

	document.getElementById('wpdbbkup_import_process_stats').innerHTML = 'Finalize Upload';
    fetch(wpdbbkp_localize_admin_data.ajax_url, {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
		calculate_progress = 100;
		document.getElementById('wpdbbkp_import_progressbar').style.width = '100%';
		document.getElementById('wpdbbkp_import_progressbar').innerHTML = '100%';
		setTimeout(() => {
			//window.location.reload()
		}, 500);
	})
    .catch(error => console.error("Error:", error));
} */
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
		});

		function uploadFileInChunks(offset) {
			let chunk = file.slice(offset, offset + chunkSize);
			let formData = new FormData();
			formData.append("action", "wpdbbkp_upload_site_chunk");
			formData.append("file", chunk);
			formData.append("fileName", file.name);
			formData.append("offset", offset);

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
						//uploadProgress.html("Uploading: " + progress + "%");

						if (nextOffset < file.size) {
							uploadFileInChunks(nextOffset);
						} else {
							//uploadProgress.html("Upload Completed! <button id='extractFile'>Extract & Restore</button>");
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
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: { action: "wpdbbkp_extract_uploaded_site", fileName: file.name },
				success: function(response) {
					if (response.success) {
						//$('#uploadProgress').html("Extraction & Restore Completed!");
					} else {
						//$('#uploadProgress').html("Extraction failed: " + response.data);
					}
				},
				error: function() {
					//$('#uploadProgress').html("Extraction error.");
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