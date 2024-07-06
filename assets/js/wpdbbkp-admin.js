jQuery(document).ready(function($) {
    $(".wpdbbkp-send-query").on("click", function(e){
    e.preventDefault();   
    var message     = $("#wpdbbkp_query_message").val();  
    var email       = $("#wpdbbkp_query_email").val();  
    
    if($.trim(message) !='' && $.trim(email) !='' && wpdbbkpIsEmail(email) == true){
      $(".wpdbbkp-send-query").text('Sending request...');
     $.ajax({
                    type: "POST",    
                    url:ajaxurl,                    
                    dataType: "json",
                    data:{action:"wpdbbkp_send_query_message",message:message,email:email,wpdbbkp_security_nonce:wpdbbkp_script_vars.nonce},
                    success:function(response){                       
                      if(response['status'] =='t'){
                        $(".wpdbbkp-query-success").show();
                        $(".wpdbbkp-query-error").hide();
                        $(".wpdbbkp-send-query").text(' Send Support Request ');
                      }else{                                  
                        $(".wpdbbkp-query-success").hide();  
                        $(".wpdbbkp-query-error").show();
                        $(".wpdbbkp-send-query").text(' Send Support Request ');
                      }
                    },
                    error: function(response){                    
                    console.log(response);
                    alert('Request was not sent. Please try again.');
                    $(".wpdbbkp-send-query").text(' Resend Support Request ');
                    }
                    });   
    }else{
        
        if($.trim(message) =='' && $.trim(email) ==''){
            alert('Please enter the message, email');
        }else{
        
        if($.trim(message) == ''){
            alert('Please enter the message');
        }
        if($.trim(email) == ''){
            alert('Please enter the email');
        }
        if(wpdbbkpIsEmail(email) == false){
            alert('Please enter a valid email');
        }
            
        }
        
    }                        

});
if (history.replaceState) {
    const url = new URL(window.location.href);
    url.searchParams.delete("notification");
    url.searchParams.delete("_wpnonce");
    history.replaceState(null, null,url);
}
  });

  function wpdbbkpIsEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}

// Javascript to enable link to tab
var wpdbbkp_hash = document.location.hash;
var wpdbbkp_prefix = "tab_";
if (wpdbbkp_hash) {
    if(jQuery('.nav-tabs a[href="'+wpdbbkp_hash.replace(wpdbbkp_prefix,"")+'"]').length){
        jQuery('.nav-tabs a[href="'+wpdbbkp_hash.replace(wpdbbkp_prefix,"")+'"]').tab('show');
        window.scrollTo(0, 0);
    }
   
} 
// Change hash for click
jQuery('.wbdbbkp_has_nav a').on('click', function (e) {
    if(jQuery('.nav-tabs a[href="'+e.target.hash.replace(wpdbbkp_prefix,"")+'"]').length){
        window.location.hash = e.target.hash.replace("#", "#" + wpdbbkp_prefix);
        jQuery('.nav-tabs a[href="'+e.target.hash.replace(wpdbbkp_prefix,"")+'"]').tab('show');
        window.scrollTo(0, 0);
    }
});

jQuery('.toplevel_page_wp-database-backup a').on('click', function (e) {
    if(e.target.hash && jQuery('.nav-tabs a[href="'+e.target.hash.replace(wpdbbkp_prefix,"")+'"]').length){
        window.location.hash = e.target.hash.replace("#", "#" + wpdbbkp_prefix);
        jQuery('.nav-tabs a[href="'+e.target.hash.replace(wpdbbkp_prefix,"")+'"]').tab('show');
        window.scrollTo(0, 0);
    }
});

// Change hash for page-reload
jQuery('.nav-tabs a ').on('shown', function (e) {
    if(e.target.hash.replace("#","").length){
        window.location.hash = e.target.hash.replace("#", "#" + wpdbbkp_prefix);
        window.scrollTo(0, 0);
    }
    
    
});


var wpdbbkp_modal = document.getElementById("wpdbbkpModal");

if(wpdbbkp_modal){
  window.onclick = function(event) {
    if (event.target == wpdbbkp_modal) {
        wpdbbkp_modal.style.display = "none";
    }
  }
}

function wpdbbkp_restore_backup(ele){
var wpdbbkp_span = document.getElementsByClassName("wpdbbkp-close");
let data_title = ele.getAttribute('data-title');
let data_href = ele.getAttribute('data-href');
let data_msg = ele.getAttribute('data-msg');
for(var i=0;i<wpdbbkp_span.length;i++){
    wpdbbkp_span[i].onclick = function() {
        wpdbbkp_modal.style.display = "none";
      }
}
jQuery('#wpdbbkp-modal-header-title').text(data_title);
jQuery('#wpdbbkp-modal-body-text').text(data_msg);
jQuery('#wpdbbkp-proceed-btn').prop('href',data_href);
wpdbbkp_modal.style.display = "block";


}

function wpdbbkp_schduler_switch(){
	var db_backups =document.getElementById('enable_autobackups');
  if(db_backups){
		if(db_backups.checked){
            document.querySelector('.autobackup_type').style.display="block";
            let autobackup_frequency = jQuery('#autobackup_frequency').val();
        if(autobackup_frequency){
            jQuery('.autobackup_frequency').show();
            if(autobackup_frequency=='daily'){
                jQuery('.autobackup_daily_lite').show();
                jQuery('.autobackup_daily_lite').parent().show();
            }else if(autobackup_frequency=='weekly'){
                jQuery('.autobackup_weekly_lite').show();
                jQuery('.autobackup_weekly_lite').parent().show();
            }
            else if(autobackup_frequency=='monthly'){
                jQuery('.autobackup_monthly_lite').show();
                jQuery('.autobackup_monthly_lite').parent().show();
            }
        }

        }else{
            document.querySelector('.autobackup_type').style.display="none";
            jQuery('.autobackup_frequency_pro').hide();
            jQuery('.autobackup_frequency_lite').hide();
            jQuery('.autobackup_frequency').hide();
            jQuery('.database_autobackup').hide();
            
		}
	}
}
jQuery('#enable_autobackups').change(function(){
    wpdbbkp_schduler_switch();
});

function wpdbbkp_autobackup_type_switch(){
    var enable_autobackups =document.getElementById('enable_autobackups');
    if(enable_autobackups && !enable_autobackups.checked){

        document.querySelector('.autobackup_frequency').style.display="none";
        document.querySelector('.autobackup_daily_lite').style.display="none";
        return false;

    }
	var db_backups =document.getElementById('autobackup_type');
  if(db_backups.value){
        document.querySelector('.autobackup_frequency').style.display="block";
        document.querySelector('.autobackup_daily_lite').style.display="block";
       
	}else{
        jQuery('.autobackup_frequency_lite').hide();
        document.querySelector('.autobackup_frequency').style.display="none";
        document.querySelector('.autobackup_daily_lite').style.display="none";
        if( document.querySelector('.autobackup_frequency_lite') && document.querySelector('.autobackup_frequency_lite').length){
            document.querySelector('.autobackup_frequency_lite').style.display="none";
        }
        if( document.querySelector('.autobackup_frequency_pro') && document.querySelector('.autobackup_frequency_pro').length){
            document.querySelector('.autobackup_frequency_pro').style.display="none";
        }
    }
}
jQuery('#autobackup_type').change(function(){
    wpdbbkp_autobackup_type_switch();
    autobackup_frequency_info();
});


function wpdbbkp_autobackup_frequency_switch(){
	var db_backups =document.getElementById('autobackup_type');
  if(db_backups.value){
        document.querySelector('.autobackup_frequency').style.display="block";
	}else{
        document.querySelector('.autobackup_frequency').style.display="none";
    }
}


jQuery('#autobackup_frequency').change(function(){
    autobackup_frequency_info();
});

function autobackup_frequency_info(){
    var autobackup_frequency = jQuery('#autobackup_frequency').val();
    if(jQuery('.autobackup_time').length==0){
        if(autobackup_frequency=='daily'){
            jQuery('.autobackup_frequency_lite').hide();
            jQuery('.autobackup_daily_lite').parent().show();
        }else if(autobackup_frequency=='weekly'){
            jQuery('.autobackup_frequency_lite').hide();
            jQuery('.autobackup_weekly_lite').parent().show();
        } else if(autobackup_frequency=='monthly'){
            jQuery('.autobackup_frequency_lite').hide();
            jQuery('.autobackup_monthly_lite').parent().show();
        }else{
            jQuery('.autobackup_frequency_lite').hide();
        }
    
    }
}

function modify_backup_frequency(){
    if(jQuery('.autobackup_time').length==0){
            var wpdbbkp_span = document.getElementsByClassName("wpdbbkp-close");
            let data_title = 'Upgrade to unlock this feature';
            let data_href = 'https://backupforwp.com/pricing#price';
            let data_href_txt = 'Upgrade to Pro';
            let data_msg = 'Upgrade to Pro version and unlock many features including Data anonimization , timed backup , prority support';
            for(var i=0;i<wpdbbkp_span.length;i++){
                wpdbbkp_span[i].onclick = function() {
                    wpdbbkp_modal.style.display = "none";
                  }
            }
            jQuery('#wpdbbkp-modal-header-title').text(data_title);
            jQuery('#wpdbbkp-modal-body-text').text(data_msg);
            jQuery('#wpdbbkp-proceed-btn').prop('href',data_href);
            jQuery('#wpdbbkp-proceed-btn').prop('onclick',"");
            jQuery('#wpdbbkp-proceed-btn').text(data_href_txt);
            wpdbbkp_modal.style.display = "block";
    }
    else{
        modify_backup_frequency_pro();
    }
}

wpdbbkp_schduler_switch();
wpdbbkp_autobackup_type_switch();

jQuery('#wpdbbkp_sftp_auth_select').change(function(){
   if(jQuery(this).val()=='key'){
        jQuery('#wpdbbkp_sftp_sshkey_password').parent().parent().show();
        jQuery('#wp_db_backup_sftp_key').parent().parent().show();
        jQuery('#wpdbbkp_sftp_password').parent().parent().hide();
    }else{
        jQuery('#wpdbbkp_sftp_sshkey_password').parent().parent().hide();
        jQuery('#wp_db_backup_sftp_key').parent().parent().hide();
        jQuery('#wpdbbkp_sftp_password').parent().parent().show();
    }
});

document.querySelector('#wpdbbkp_sftp_sshkey').addEventListener('input', function() {
    const file = this.files[0]
    let fr = new FileReader()
    fr.readAsText(file)
    fr.onload = () => {
        document.querySelector('#wp_db_backup_sftp_key').value=btoa(fr.result);
        console.log('key upload success');
    }
    fr.onerror = () => {
      console.log(fr.error);
    }
  })

if(jQuery("#create_backup")){
    jQuery("#create_backup").click(function(event) {
        jQuery(".wpdbbkp_notification").hide();
        jQuery("#backup_process").show();
        jQuery("#create_backup").attr("disabled", true);
    });
}