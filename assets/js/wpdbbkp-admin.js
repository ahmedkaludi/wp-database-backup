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
    if(jQuery('.nav-tabs a[href="'+e.target.hash.replace(wpdbbkp_prefix,"")+'"]').length){
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
