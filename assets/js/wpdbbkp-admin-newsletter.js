jQuery(document).ready(function($) {

    /* Newletters js starts here */      
        if(wpdbbkp_localize_data.do_tour){
                    
          var  content = '<h3>Thanks For using BackupforWP!</h3>';
              content += '<p>Do you want the latest on BackupforWP data update before others and some best resources on monetization in a single email? - Free just for users of BackupforWP!</p>';
              content += '<style type="text/css">';
              content += '.wp-pointer-buttons{ padding:0; overflow: hidden; }';
              content += '.wp-pointer-content .button-secondary{  left: -25px;background: transparent;top: 5px; border: 0;position: relative; padding: 0; box-shadow: none;margin: 0;color: #0085ba;} .wp-pointer-content .button-primary{ display:none}  #wpdbbkp_mc_embed_signup{background:#fff; clear:left; font:14px Helvetica,Arial,sans-serif; }';
              content += '</style>';                        
              content += '<div id="wpdbbkp_mc_embed_signup">';
              content += '<form method="POST" accept-charset="utf-8" id="wpdbbkp-news-letter-form">';
              content += '<div id="wpdbbkp_mc_embed_signup_scroll">';
              content += '<div class="wpdbbkp-mc-field-group" style="    margin-left: 15px;    width: 195px;    float: left;">';
              content += '<input type="text" name="wpdbbkp_subscriber_name" class="form-control" placeholder="Name" hidden value="'+wpdbbkp_localize_data.current_user_name+'" style="display:none">';
              content += '<input type="text" value="'+wpdbbkp_localize_data.current_user_email+'" name="wpdbbkp_subscriber_email" class="form-control" placeholder="Email*"  style="      width: 180px;    padding: 6px 5px;">';                        
              content += '<input type="text" name="wpdbbkp_subscriber_website" class="form-control" placeholder="Website" hidden style=" display:none; width: 168px; padding: 6px 5px;" value="'+wpdbbkp_localize_data.get_home_url+'">';
              content += '<input type="hidden" name="ml-submit" value="1" />';
              content += '</div>';
              content += '<div id="mce-responses">';                                                
              content += '</div>';
              content += '<div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_a631df13442f19caede5a5baf_c9a71edce6" tabindex="-1" value=""></div>';
              content += '<input type="submit" value="Subscribe" name="subscribe" id="pointer-close" class="button mc-newsletter-sent" style=" background: #0085ba; border-color: #006799; padding: 0px 16px; text-shadow: 0 -1px 1px #006799,1px 0 1px #006799,0 1px 1px #006799,-1px 0 1px #006799; height: 30px; margin-top: 1px; color: #fff; box-shadow: 0 1px 0 #006799;">';
              content += '<p id="wpdbbkp-news-letter-status"></p>';
              content += '</div>';
              content += '</form>';
              content += '</div>';

              $(document).on("submit", "#wpdbbkp-news-letter-form", function(e){
                e.preventDefault(); 
                
                var $form = $(this),
                name = $form.find('input[name="wpdbbkp_subscriber_name"]').val(),
                email = $form.find('input[name="wpdbbkp_subscriber_email"]').val();
                website = $form.find('input[name="wpdbbkp_subscriber_website"]').val();                          
                
                $.post(wpdbbkp_localize_data.ajax_url,
                            {action:'wpdbbkp_subscribe_to_news_letter',
                            wpdbbkp_security_nonce:wpdbbkp_localize_data.wpdbbkp_security_nonce,
                            name:name, email:email, website:website },
                  function(data) {
                    
                      if(data)
                      {
                        if(data=="Some fields are missing.")
                        {
                          $("#wpdbbkp-news-letter-status").text("");
                          $("#wpdbbkp-news-letter-status").css("color", "red");
                        }
                        else if(data=="Invalid email address.")
                        {
                          $("#wpdbbkp-news-letter-status").text("");
                          $("#wpdbbkp-news-letter-status").css("color", "red");
                        }
                        else if(data=="Invalid list ID.")
                        {
                          $("#wpdbbkp-news-letter-status").text("");
                          $("#wpdbbkp-news-letter-status").css("color", "red");
                        }
                        else if(data=="Already subscribed.")
                        {
                          $("#wpdbbkp-news-letter-status").text("");
                          $("#wpdbbkp-news-letter-status").css("color", "red");
                        }
                        else
                        {
                          $("#wpdbbkp-news-letter-status").text("You're subscribed!");
                          $("#wpdbbkp-news-letter-status").css("color", "green");
                        }
                      }
                      else
                      {
                        alert("Sorry, unable to subscribe. Please try again later!");
                      }
                  }
                );
              });      
      
      var setup;                
      var wp_pointers_tour_opts = {
          content:content,
          position:{
              edge:"top",
              align:"left"
          }
      };

                      
      wp_pointers_tour_opts = $.extend (wp_pointers_tour_opts, {
              buttons: function (event, t) {
                      button= jQuery ('<a id="pointer-close" class="button-secondary">' + wpdbbkp_localize_data.button1 + '</a>');
                      button_2= jQuery ('#pointer-close.button');
                      button.bind ('click.pointer', function () {
                              t.element.pointer ('close');
                      });
                      button_2.on('click', function() {
                        setTimeout(function(){ 
                            t.element.pointer ('close');
                        }, 3000);
                            
                      } );
                      return button;
              },
              close: function () {
                      $.post (wpdbbkp_localize_data.ajax_url, {
                              pointer: 'wpdbbkp_subscribe_pointer',
                              action: 'dismiss-wp-pointer'
                      });
              },
              show: function(event, t){
                t.pointer.css({'left':'170px', 'top':'160px'});
            }                                               
      });
      
      setup = function () {
              $(wpdbbkp_localize_data.displayID).pointer(wp_pointers_tour_opts).pointer('open');
                if (wpdbbkp_localize_data.button2) {
                      jQuery ('#pointer-close').after ('<a id="pointer-primary" class="button-primary">' + wpdbbkp_localize_data.button2+ '</a>');
                      jQuery ('#pointer-primary').click (function () {
                              wpdbbkp_localize_data.function_name;
                      });
                      jQuery ('#pointer-close').click (function () {
                              $.post (wpdbbkp_localize_data.ajax_url, {
                                      pointer: 'wpdbbkp_subscribe_pointer',
                                      action: 'dismiss-wp-pointer'
                              });
                      });
                }
      };
      console.log('content', setup);

      if (wp_pointers_tour_opts.position && wp_pointers_tour_opts.position.defer_loading) {
              $(window).bind('load.wp-pointers', setup);
      }
      else {
              setup ();
      }
      
    }
      
    /* Newletters js ends here */ 

});