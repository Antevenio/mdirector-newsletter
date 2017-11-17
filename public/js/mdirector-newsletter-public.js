(function( $ ) {
	'use strict';
})( jQuery );
jQuery(document).ready( function(){
	// Suscribe via widget
	jQuery("#mdirector_widget_form").submit(function(e){
		e.preventDefault();
		var email = jQuery("input[name='mdirector_widget-email']").val();
		var privacycheckbox = jQuery("input[name='mdirector_widget-accept']").prop('checked');
		if (privacycheckbox)
		{
			if(email){
				if(validEmail(email)){
					jQuery(".md_ajax_loader.md_widget").show();
					var list = jQuery("#md_frequency").val();

					var data = {
						'action': 'md_new',
						'list': list,
						'email': email
					};
					jQuery.post(ajaxurl, data, function(response) {
						if(response.response == 'error'){
							// Error handling
							md_error_handling('#mdirector_widget_form', response.code);
						}else{
							md_success_handling('#mdirector_widget_form', 'Te has suscrito correctamente a la lista. Gracias por tu interés.');
						}
						jQuery(".md_ajax_loader.md_widget").hide();
					}, 'json');
				}else{
					md_error_handling('#mdirector_widget_form', 0, 'Por favor, introduce un correo electrónico válido.');
				}
			}else{
				md_error_handling('#mdirector_widget_form', 0, 'Por favor, introduce tu correo electrónico.');
			}
		}else{
			md_error_handling('#mdirector_widget_form', 0, 'Por favor, acepta la política de privacidad.');
		}

		});

	// Suscribe shortcode
	
	jQuery("#mdirector_sh_suscription").submit(function(e){
		e.preventDefault();
		var email = jQuery("input#mdirector_sh_email").val();
		var privacycheckboxsh = jQuery("input[name='mdirector_sh-accept']").prop('checked');
		if (privacycheckboxsh)
		{
			if(email){
				if(validEmail(email)){
					jQuery(".md_ajax_loader.md_sh").show();
					var list = jQuery("#md_sh_frequency").val();

					var data = {
						'action': 'md_new',
						'list': list,
						'email': email
					};
					jQuery.post(ajaxurl, data, function(response) {
						if(response.response == 'error'){
							// Error handling
							md_error_handling('#mdirector_sh_suscription', response.code);
						}else{
							md_success_handling('#mdirector_sh_suscription', 'Te has suscrito correctamente a la lista. Gracias por tu interés.');
						}
						jQuery(".md_ajax_loader.md_sh").hide();
					}, 'json');
				}else{
					md_error_handling('#mdirector_sh_suscription', 0, 'Por favor, introduce un correo electrónico válido.');
				}
			}else{
				md_error_handling('#mdirector_sh_suscription', 0, 'Por favor, introduce tu correo electrónico.');
			}
		}else{
			md_error_handling('#mdirector_sh_suscription', 0, 'Por favor, acepta la política de privacidad.');
		}
	});

	function md_success_handling(element, msg){
		jQuery(".md_handling").remove();
		jQuery(element).after('<p class="md_handling md_success_handling">'+ msg +'</p>');
	}

	function md_error_handling(element, error_code, custom_msg){
		jQuery(".md_handling").remove();
		
		var msg;

		switch(error_code){
			case 1145:
				msg = 'El correo introducido ya estaba suscrito a la lista.';
				break;

			default:
				msg = custom_msg;
		}
		jQuery(element).after('<p class="md_handling md_error_handling">'+ msg +'</p>');
	}



	function validEmail(email){
		if( /(.+)@(.+){2,}\.(.+){2,}/.test(email) ){
		// valid email
 return true;
	} else {
	// invalid email
	return false;
	}
}
});

