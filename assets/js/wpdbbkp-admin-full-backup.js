jQuery(document).ready(function($){
	$(document).on('click', '#wpdbbkp-create-full-backup', function(e){
		e.preventDefault();
		let backup_data_array = [];
		let backupArchiveReturnData = [];
		$('#wpdb-backup-process').show();
		$(this).attr('disabled', true);
		$.ajax({
			type: 'POST',
			url: wpdbbkp_localize_admin_data.ajax_url,
			data: {action: 'wpdbbkp_ajax_wp_config_path', wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
			success: function(response_one){
				response_one = JSON.parse(response_one);
				backup_data_array = response_one;

				// Ajax call to create cirectory and .htaccess files
				$.ajax({
					type: 'POST',
					url: wpdbbkp_localize_admin_data.ajax_url,
					data: {action: 'wpdbbkp_ajax_mysqldump', FileName:backup_data_array.FileName, logFile:backup_data_array.logFile,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
					success: function(response_two){
						response_two = JSON.parse(response_two);
						if(response_two.status === 'success'){
							if(response_two.tables){
								let backupTables = response_two.tables;
								$.each(backupTables, function(index, element){
									$.ajax({
										type: 'POST',
										url: wpdbbkp_localize_admin_data.ajax_url,
										async: false,
										data: {action: 'wpdbbkp_ajax_create_mysql_backup',FileName:backup_data_array.FileName,logFile:backup_data_array.logFile, tableName:element,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
										success: function(response_three){
										}
									});
								});
								
							}
						}
						$.ajax({
							type: 'POST',
							url: wpdbbkp_localize_admin_data.ajax_url,
							data: {action: 'wpdbbkp_ajax_after_mysql_backup',FileName:backup_data_array.FileName,logFile:backup_data_array.logFile,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
							success: function(response_four){
								response_four = JSON.parse(response_four);
								if(response_four.status === 'success'){
									if(response_four.methodZip == 0){
										$.ajax({
											type: 'POST',
											url: wpdbbkp_localize_admin_data.ajax_url,
											data: {action: 'wpdbbkp_ajax_method_zip',FileName:backup_data_array.FileName,logFile:backup_data_array.logFile,logMessage:backup_data_array.logMessage,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
											success: function(response_five){
												response_five = JSON.parse(response_five);
												if(response_five.status === 'success'){
													if(response_five.update_backup_info){
														backupArchiveReturnData = response_five.update_backup_info;
														wp_all_backup_after_backup_completion(backupArchiveReturnData);
													}
													backup_data_array.logMessage = response_five.logMessage;
												}
											}
										});
									}else if(response_four.ZipArchive){
										// Call ajax for single file
										$.ajax({
											type: 'POST',
											url: wpdbbkp_localize_admin_data.ajax_url,
											data: {action: 'wpdbbkp_ajax_get_backup_files',FileName:backup_data_array.FileName,logFile:backup_data_array.logFile,logMessage:backup_data_array.logMessage,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
											success: function(response_six){
												response_six = JSON.parse(response_six);
												if(response_six.status === 'success'){
													if(response_six.chunk_count > 0){
														let totalChunkCnt = parseInt(response_six.chunk_count) + 1;
														for(let chk_cnt = 1; chk_cnt <= totalChunkCnt; chk_cnt++){
															$.ajax({
																type: 'POST',
																url: wpdbbkp_localize_admin_data.ajax_url,
																async: false,
																data: {action: 'wpdbbkp_ajax_files_backup',FileName:backup_data_array.FileName,logFile:backup_data_array.logFile,files_added:backup_data_array.files_added,chunk_count:chk_cnt,total_chunk_cnt:totalChunkCnt,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
																success: function(response_seven){
																	response_seven = JSON.parse(response_seven);
																	if(response_seven.status === 'success'){
																		backup_data_array.files_added = response_seven.files_added;
																		backup_data_array.logMessage = response_seven.logMessage;
																		if(response_seven.update_backup_info){
																			backupArchiveReturnData = response_seven.update_backup_info;
																			wp_all_backup_after_backup_completion(backupArchiveReturnData);
																		}
																	}
																}
															});
														}	
													}else{
														if(response_six.update_backup_info){
															backupArchiveReturnData = response_six.update_backup_info;
															wp_all_backup_after_backup_completion(backupArchiveReturnData);
														}
													}
												}
											} 
										});
									}else{
										$.ajax({
											type: 'POST',
											url: wpdbbkp_localize_admin_data.ajax_url,
											data: {action: 'wpdbbkp_ajax_execute_file_backup_else', FileName:backup_data_array.FileName,logFile:backup_data_array.logFile,wpdbbkp_admin_security_nonce:wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce},
											success: function(response_else){
												response_else = JSON.parse(response_else);
												if(response_else.status === 'success'){
													if(response_else.update_backup_info){
														backupArchiveReturnData = response_else.update_backup_info;
														wp_all_backup_after_backup_completion(backupArchiveReturnData);
													}
												}
											}
										});
									}
								}
							}
						});
					}
				});
			}
		});
	});
});

function wp_all_backup_after_backup_completion(backupArchiveReturnData) {
	let log_file = ''; let log_file_dir = '';
	if(backupArchiveReturnData.logfile){
		log_file = backupArchiveReturnData.logfile
	}
	if(backupArchiveReturnData.logfileDir){
		log_file_dir = backupArchiveReturnData.logfileDir
	}
	const postData = {
		filename: backupArchiveReturnData.filename,
		dir: backupArchiveReturnData.dir,
		url: backupArchiveReturnData.url,
		size: backupArchiveReturnData.size,
		type: backupArchiveReturnData.type,
		logfile: log_file,
		logfileDir: log_file_dir,
		action: 'wpdbbkp_ajax_backup_event_process',
		wpdbbkp_admin_security_nonce: wpdbbkp_localize_admin_data.wpdbbkp_admin_security_nonce
	}
	jQuery.ajax({
		type: 'POST',
		url: wpdbbkp_localize_admin_data.ajax_url,
		data: postData,
		success: function(response){
			response = JSON.parse(response);
			if(response.status == 'success'){
				jQuery('#wpdbbkp-create-full-backup').removeAttr('disabled', false);
				jQuery('#wpdb-backup-process').hide();
				window.location.href = response.redirect_url;
			}
		}
	});
}