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

  });

  function wpdbbkpIsEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}

jQuery(document).on("click", ".popoverid", function(e){
  var itrms=jQuery('#example .popover-content');
  for(var i=0;i<itrms.length;i++)
  {
    var popover_con=jQuery(itrms[i]).text();
    jQuery(itrms[i]).html(popover_con);
  }
  
}); 