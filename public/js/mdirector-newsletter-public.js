jQuery(document).ready(function ($) {
    // Subscription via form (widget or shortcode)
    var $widgetForm = $('.md__newsletter--form');

    $widgetForm.on('submit', function (e) {
        e.preventDefault();

        var $this = $(this),
            $ajaxLoader = $this.find('.md_ajax_loader'),
            email = $this.find('.md_newsletter--email_input').val(),
            $privacyCheckbox = $this.find('.md_newsletter--checkbox');

        if ($privacyCheckbox.is(':checked')) {
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
                                    'Te has suscrito correctamente a la lista. Gracias por tu interés.');
                            }
                            $ajaxLoader.hide();
                        }
                    );
                } else {
                    md_error_handling($this, 0,
                        'Por favor, introduce un correo electrónico válido.');
                }
            } else {
                md_error_handling($this, 0,
                    'Por favor, introduce tu correo electrónico.');
            }
        } else {
            md_error_handling($this, 0,
                'Por favor, acepta la política de privacidad.');
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
                msg = 'El correo introducido ya estaba suscrito a la lista.';
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

