jQuery(document).ready(function ($) {
    /* global LOCALES */

    // Subscription via form (widget or shortcode)
    var $widgetForm = $('.md__newsletter--form');

    $widgetForm.on('submit', function (e) {
        e.preventDefault();

        var $this = $(this),
            $ajaxLoader = $this.find('.md_ajax_loader'),
            email = $this.find('.md_newsletter--email_input').val(),
            $privacyCheckbox = $this.find('.md_newsletter--checkbox');

        if (!$privacyCheckbox.length || $privacyCheckbox.is(':checked')) {
            if (email) {
                if (validEmail(email)) {
                    $ajaxLoader.show();
                    var list = $this.find('.md__newsletter--select').val();

                    var ajaxParams = {
                        url: ajaxurl,
                        method: 'post',
                        data: {
                            'action': 'md_new',
                            'list': list,
                            'email': email
                        },
                        dataType: 'json'
                    };

                    $.ajax(ajaxParams).done(
                        function (response) {
                            if (response.response === 'error') {
                                // Error handling
                                md_error_handling($this, response.code);
                            } else {
                                md_success_handling($this,
                                    LOCALES.WIDGET_SCRIPT_SUCCESS);
                            }
                            $ajaxLoader.hide();
                        }
                    );
                } else {
                    md_error_handling($this, 0,
                        LOCALES.WIDGET_SCRIPT_EMAIL_VALIDATION);
                }
            } else {
                md_error_handling($this, 0,
                    LOCALES.WIDGET_SCRIPT_EMAIL_TEXT);
            }
        } else {
            md_error_handling($this, 0,
                LOCALES.WIDGET_SCRIPT_POLICY_VALIDATION);
        }
    });

    function md_success_handling($target, msg) {
        $target.next('.md_handling').remove();
        $target.after('<p class="md_handling md_success_handling">' + msg + '</p>');
    }

    function md_error_handling($target, error_code, custom_msg) {
        $target.next('.md_handling').remove();

        var msg;

        switch (error_code) {
            case 1145:
                msg = LOCALES.WIDGET_SCRIPT_EMAIL_ALREADY_REGISTERED;
                break;

            default:
                msg = custom_msg;
        }

        $target.after('<p class="md_handling md_error_handling">' + msg + '</p>');
    }

    function validEmail(email) {
        return (/(.+)@(.+){2,}\.(.+){2,}/.test(email));
    }
});

