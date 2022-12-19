var strict;

jQuery(document).ready(function ($) {
    /**
     * DEACTIVATION FEEDBACK FORM
     */
    // show overlay when clicked on "deactivate"
    wpdbbkp_deactivate_link = $('.wp-admin.plugins-php tr[data-slug="wp-database-backup"] .row-actions .deactivate a');
    wpdbbkp_deactivate_link_url = wpdbbkp_deactivate_link.attr('href');

    wpdbbkp_deactivate_link.click(function (e) {
        e.preventDefault();
        
        // only show feedback form once per 30 days
        var c_value = wpdbbkp_admin_get_cookie("wpdbbkp_hide_deactivate_feedback");

        if (c_value === undefined) {
            $('#wpdbbkp-feedback-overlay').show();
        } else {
            // click on the link
            window.location.href = wpdbbkp_deactivate_link_url;
        }
    });
    // show text fields
    $('#wpdbbkp-feedback-content input[type="radio"]').click(function () {
        // show text field if there is one
        var input_value = $(this).attr("value");
        var target_box = $("." + input_value);
        $(".mb-box").not(target_box).hide();
        $(target_box).show();
    });
    // send form or close it
    $('#wpdbbkp-feedback-content .button').click(function (e) {
        e.preventDefault();
        // set cookie for 30 days
        var exdate = new Date();
        exdate.setSeconds(exdate.getSeconds() + 2592000);
        document.cookie = "wpdbbkp_hide_deactivate_feedback=1; expires=" + exdate.toUTCString() + "; path=/";

        $('#wpdbbkp-feedback-overlay').hide();
        if ('wpdbbkp-feedback-submit' === this.id) {
            // Send form data
            $.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: 'wpdbbkp_send_feedback',
                    data: $('#wpdbbkp-feedback-content form').serialize(),
                    gn_security_nonce:gn_pub_script_vars.nonce
                },
                complete: function (MLHttpRequest, textStatus, errorThrown) {
                    // deactivate the plugin and close the popup
                    $('#wpdbbkp-feedback-overlay').remove();
                    window.location.href = wpdbbkp_deactivate_link_url;

                }
            });
        } else {
            $('#wpdbbkp-feedback-overlay').remove();
            window.location.href = wpdbbkp_deactivate_link_url;
        }
    });
    // close form without doing anything
    $('.wpdbbkp-feedback-not-deactivate').click(function (e) {
        $('#wpdbbkp-feedback-overlay').hide();
    });
    
    function wpdbbkp_admin_get_cookie (name) {
	var i, x, y, wpdbbkp_cookies = document.cookie.split( ";" );
	for (i = 0; i < wpdbbkp_cookies.length; i++)
	{
		x = wpdbbkp_cookies[i].substr( 0, wpdbbkp_cookies[i].indexOf( "=" ) );
		y = wpdbbkp_cookies[i].substr( wpdbbkp_cookies[i].indexOf( "=" ) + 1 );
		x = x.replace( /^\s+|\s+$/g, "" );
		if (x === name)
		{
			return unescape( y );
		}
	}
}

}); // document ready