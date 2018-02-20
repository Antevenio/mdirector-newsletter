<?php
if (!class_exists('mdirectorWidget')) {
	class mdirectorWidget extends WP_Widget {
		// constructor
		public function mdirectorWidget() {
			$widget_ops = array('classname' => 'mdirectorWidget',
				'description' => __('Formulario de suscripción', 'mdirector-newsletter')
			);
			$this->WP_Widget('mdirectorWidget', 'MDirector Widget', $widget_ops);
		}

		// widget form creation
        public function form($instance) {
		    $instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		    $title = $instance['title'];
		    $description = $instance['description'];

		?>
		  <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php echo __('Título','mdirector-newsletter');?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
		  <p>
		  	<label for="<?php echo $this->get_field_id('description'); ?>">
                            <?php echo __('Texto explicativo','mdirector-newsletter');?>:
		  		<textarea class="widefat" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>"><?php echo attribute_escape($description); ?></textarea>
		  	</label>
		  </p>
		<?php
		}

		// widget update
		public function update($new_instance, $old_instance) {
			$instance = $old_instance;
		    $instance['title'] = $new_instance['title'];
		    $instance['description'] = $new_instance['description'];
		    return $instance;
		}

		// widget display
		public function widget($args, $instance) {
			extract($args, EXTR_SKIP);
			$settings = get_option( "mdirector_settings" );
			$mdirector_active = get_option( "mdirector_active" );

            $title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);

            if (!empty($title))
                echo $before_title . $title . $after_title;

            if (!empty($description))
                echo '<p class="md__newsletter--description">' . $instance['description'] . '</p>';

            if ($mdirector_active === 'yes') {
                if( $settings['frequency_daily'] === 'yes' && $settings['frequency_weekly'] === 'yes'){
                $select_frequency 	= '<div class="md__newsletter--area__select">';
                $select_frequency 	.= '<select id="md_frequency" class="md__newsletter--select" name="md_frequency">';
			    $select_frequency 	.= '<option value="daily">'.__('Recibir newsletter diaria', 'mdirector-newsletter').'</option>';
			    $select_frequency 	.= '<option value="weekly">'.__('Recibir newsletter semanal', 'mdirector-newsletter').'</option>';
			    $select_frequency 	.= '</select>';
                $select_frequency 	.= '</div>';
                } else if ($settings['frequency_daily'] === 'yes'){
                    $select_frequency = '<input value="daily" name="md_frequency" type="hidden">';
                } else if ($settings['frequency_weekly'] === 'yes'){
                    $select_frequency = '<input value="weekly" name="md_frequency" type="hidden">';
                }
                if ($settings['api'] && $settings['secret']) {
		    	    echo '
		    	    <form id="mdirector_widget_form" class="md__newsletter--form">
		    			<div class="md__newsletter--area__input">
		    				<input type="email" class="md_newsletter--email_input" 
		    				    placeholder="'.__('Correo electrónico', 'mdirector-newsletter').'" 
		    				    value="" name="mdirector_widget-email">
		    			</div>
		    			'.$select_frequency;
		    			echo '<div class="md__newsletter--area__suscribe">';

		    			$settings = get_option( "mdirector_settings" );

		    			$accept = ($settings['md_privacy_text']!='')?$settings['md_privacy_text']:__("Acepto la política de privacidad",'mdirector-newsletter');
		    			$md_privacy_link = ($settings['md_privacy_url']!='')?$settings['md_privacy_url']:'#';
                        echo '
                            <p class="md__newsletter--area__accept">
                            <input type="checkbox" name="mdirector_widget-accept"/>
                            <label for="mdirector_widget_accept"> 
                            <a href="'.$md_privacy_link.'" target="_blank" class="md__newsletter--accept">'.$accept.'</a>
                            </label>
                            </p>';


                        echo '
                            <div class="md__newsletter--area__button">
		    				    <button class="md_newsletter--button">'.__('Suscribirme', 'mdirector-newsletter').'</button>
		    			    </div>
		    	    </form>
		    	    <div class="md_ajax_loader md_widget"><img src="'.MDIRECTOR_NEWSLETTER_PLUGIN_URL.'assets/ajax-loader.gif'.'"></div>';
			    }
		    }
		}
	}

	// register widget
	add_action('widgets_init', create_function('', 'return register_widget("mdirectorWidget");'));
}
