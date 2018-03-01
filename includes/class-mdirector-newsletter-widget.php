<?php
if (!class_exists('mdirectorWidget')) {

    /**
     * Class mdirectorWidget
     */
    class mdirectorWidget extends WP_Widget {
        public function mdirectorWidget() {
            $widget_ops = array(
                'classname' => 'mdirectorWidget',
                'description' => __('WIDGET__TITLE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
            );

            $this->WP_Widget('mdirectorWidget', 'MDirector Widget', $widget_ops);
        }

        // widget form creation
        public function form($instance) {
            $instance = wp_parse_args((array)$instance, array('title' => ''));
            $title = $instance['title'];
            $description = $instance['description'];

            echo '
            <p><label
                for="' . $this->get_field_id('title') . '">' .
                __('WIDGET-FORM__TITLE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                <input class="widefat"
                    id="' . $this->get_field_id('title') . '"
                    name="' . $this->get_field_name('title') . '"
                    type="text"
                    value="' . esc_attr($title) . '"/></label>
            </p>
            <p>
                <label for="' . $this->get_field_id('description') . '">' .
                    __('WIDGET-FORM__TEXT_SUPPORT',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                    <textarea class="widefat"
                        id="' . $this->get_field_id('description') . '"
                        name="' . $this->get_field_name('description') . '">' .
                            esc_attr($description) . '
                    </textarea>
                </label>
            </p>';
        }

        /**
         * @param array $new_instance
         * @param array $old_instance
         *
         * @return array
         */
        public function update($new_instance, $old_instance) {
            $instance = $old_instance;
            $instance['title'] = $new_instance['title'];
            $instance['description'] = $new_instance['description'];

            return $instance;
        }

        /**
         * @param array $args
         * @param array $instance
         *
         * @return bool|void
         */
        public function widget($args, $instance) {
            $Mdirector_utils = new Mdirector_Newsletter_Utils();
            $output = $Mdirector_utils->get_register_for_html($args, $instance);

            echo $output;
            return;
        }
    }

    // register widget
    add_action('widgets_init',
        create_function('', 'return register_widget("mdirectorWidget");'));
}
