<?php

/**
 * Fired during plugin activation.
 *
 *
 * @since      1.0.0
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 * @author     MDirector
 */
class Mdirector_Newsletter_Utils {
    // Paths
    const MDIRECTOR_MAIN_URL = 'http://www.mdirector.com';
    const MDIRECTOR_API_DELIVERY_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_delivery';
    const MDIRECTOR_API_CONTACT_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_contact';
    const MDIRECTOR_API_LIST_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_list';
    const MDIRECTOR_API_CAMPAIGN_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_campaign';
    const TEMPLATES_PATH = MDIRECTOR_TEMPLATES_PATH . self::DEFAULT_TEMPLATE. '/';

    // Language / templates
    const MDIRECTOR_LANG_DOMAIN = 'mdirector-newsletter';
    const MDIRECTOR_DEFAULT_USER_LANG = 'es';
    const DEFAULT_TEMPLATE = 'default';
    const MAX_IMAGE_SIZE_MDIRECTOR_TEMPLATE = 143;
    const MAX_IMAGE_SIZE_DEFAULT_TEMPLATE = '100%';

    // Newsletter settings
    const DAILY_FREQUENCY = 'daily';
    const WEEKLY_FREQUENCY = 'weekly';
    const DEFAULT_DAILY_MAIL_SUBJECT = 'Daily mail';
    const DEFAULT_WEEKLY_MAIL_SUBJECT = 'Weekly mail';
    const DYNAMIC_SUBJECT = 'dynamic';
    const FIXED_SUBJECT = 'fixed';
    const DYNAMIC_CRITERIA_FIRST_POST = 'first_post';
    const DYNAMIC_CRITERIA_LAST_POST = 'last_post';
    const SETTINGS_OPTION_ON = 'yes';
    const SETTINGS_OPTION_OFF = 'no';
    const MIDNIGHT_HOUR = '00:00';

    const FORM_PREFIX = 'mdirector_widget-';
    const FORM_CLASS = 'md__newsletter--form';
    const FORM_NAME = self::FORM_PREFIX . 'form';

    public function __construct() {}

    private function get_plugin_options() {
        return get_option('mdirector_settings')
            ? get_option('mdirector_settings') : [];
    }

    public function is_wpml() {
        return function_exists('icl_object_id');
    }

    public function get_current_languages() {
        if ($this->is_wpml()) {
            return apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
        }

        $default_name = explode('_', get_locale())[0];
        $languages = [
            $default_name => [
                'code' => $default_name,
                'translated_name' => __('DEFAULT-LANGUAGE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
            ]
        ];

        return $languages;
    }

    public function get_current_lang() {
        if ($this->is_wpml()) {
            return ICL_LANGUAGE_CODE;
        }

        return self::MDIRECTOR_DEFAULT_USER_LANG;
    }

    public function get_register_for_html($args = [], $instance = null) {
        extract($args, EXTR_SKIP);
        $options = $this->get_plugin_options();

        $mdirector_active = get_option('mdirector_active');
        $select_frequency = null;
        $output = '';

        if (!isset($before_title)) {
            $before_title = null;
        }

        if (!isset($after_title)) {
            $after_title = null;
        }

        if (empty($options['mdirector_frequency_daily'])
            && empty($options['mdirector_frequency_weekly'])) {
            return false;
        }

        $title = empty($instance['title'])
            ? ' ' : apply_filters('widget_title', $instance['title']);

        if (!empty($title)) {
            $output .= $before_title . $title . $after_title;
        }

        if (!empty($description)) {
            $output .= '<p class="md__newsletter--description">'
                . $instance['description'] . '</p>';
        }

        if ($mdirector_active === self::SETTINGS_OPTION_ON) {
            if ($options['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON
                && $options['mdirector_frequency_weekly'] === self::SETTINGS_OPTION_ON) {
                $select_frequency = '
                            <div class="md__newsletter--area__select">
                                <select class="md__newsletter--select" 
                                    name="' . self::FORM_PREFIX . 'frequency">
                                    <option value="daily">' .
                                        __('WIDGET-FREQUENCY__DAILY',
                                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                    </option>
                                    <option value="weekly">' .
                                        __('WIDGET-FREQUENCY__WEEKLY',
                                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                    </option>
                                </select>
                            </div>';
            } else if ($options['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON) {
                $select_frequency = '<input class="md__newsletter--select" 
                    value="daily" name="' . self::FORM_PREFIX . 'frequency" type="hidden">';
            } else if ($options['mdirector_frequency_weekly'] === self::SETTINGS_OPTION_ON) {
                $select_frequency = '<input class="md__newsletter--select" 
                    value="weekly" name="' . self::FORM_PREFIX . 'frequency" type="hidden">';
            }

            if ($options['mdirector_api'] && $options['mdirector_secret']) {
                $output .= '
		    	    <form class="' . self::FORM_CLASS . '" name="'. self::FORM_NAME . '" method="post">
		    			<div class="md__newsletter--area__input">
		    				<input 
		    				    type="email" 
		    				    class="md_newsletter--email_input" 
		    				    placeholder="' . __('WIDGET-EMAIL',
                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '" 
		    				    value="" 
		    				    name="' . self::FORM_PREFIX . 'email">
		    			</div>' . $select_frequency . '
                        <div class="md__newsletter--area__suscribe">';
                $accept = ($options['mdirector_privacy_text'] != '')
                    ? $options['mdirector_privacy_text']
                    : __('WIDGET-PRIVACY__POLICY__ACCEPTED',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);

                $md_privacy_link = ($options['mdirector_privacy_url'] != '')
                    ? $options['mdirector_privacy_url'] : '#';

                $output .= '
                            <p class="md__newsletter--area__accept">
                                <input 
                                    class="md_newsletter--checkbox" 
                                    type="checkbox" 
                                    name="' . self::FORM_PREFIX . 'accept" 
                                    autocomplete="off"/>
                                <label for="mdirector_widget_accept"> 
                                    <a 
                                        href="' . $md_privacy_link . '" 
                                        target="_blank" 
                                        class="md__newsletter--accept">' . $accept . '</a>
                                </label>
                            </p>
                            
                            <div class="md__newsletter--area__button">
                                <button class="md_newsletter--button" type="submit">' .
                                    __('WIDGET-SUBSCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                    <img class="md_ajax_loader" 
                                        src="' . MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'assets/ajax-loader.png' . '"/>
                                </button>
                            </div>
                        </div>
                                   
		    	    </form>';
            }
        }

        return $output;
    }

    private function text_truncate($string) {
        $string = wp_strip_all_tags($string);
        $string = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $string);

        if ( preg_match('/<!--more(.*?)?-->/', $string, $matches) ) {
            list($main) = explode($matches[0], $string, 2);

            return $main;
        } else {
            $string = htmlspecialchars($string);

            $parts = preg_split('/([\s\n\r]+)/', $string, null, PREG_SPLIT_DELIM_CAPTURE);
            $parts_count = count($parts);

            $length = 0;
            $last_part = 0;

            for (; $last_part < $parts_count; ++$last_part) {
                $length += strlen($parts[$last_part]);
                if ($length > 200) { break; }
            }

            return implode(array_slice($parts, 0, $last_part));
        }
    }

    private function get_main_image_size() {
        return (self::TEMPLATES_PATH === 'templates-mdirector/')
            ? self::MAX_IMAGE_SIZE_MDIRECTOR_TEMPLATE
            : self::MAX_IMAGE_SIZE_DEFAULT_TEMPLATE;
    }

    private function get_main_image($post_id, $size) {
        if ($post_id) {
            if (has_post_thumbnail($post_id)) {
                $post_thumbnail_id = get_post_thumbnail_id($post_id);
                $thumbnail = wp_get_attachment_image_src($post_thumbnail_id, $size);

                return $thumbnail[0];
            }
        }

        return false;
    }

    /**
     * @param        $posts
     * @param        $frequency
     * @param string $lang
     *
     * @throws MDOAuthException2
     */
    private function md_send_mail($posts, $frequency, $lang = self::MDIRECTOR_DEFAULT_USER_LANG) {
        add_filter( 'wp_mail_content_type', [$this, 'set_html_content_type'] );

        if (!empty($posts)) {
            $templates_available = $this->get_user_templates();
            $template_path = MDIRECTOR_TEMPLATES_PATH . $this->get_current_template($templates_available, $lang);
            $html_content = file_get_contents($template_path . DIRECTORY_SEPARATOR . 'template.html');

            // Time to replace mail content
            $mail_content = str_replace('{{header_title}}', get_bloginfo('name'), $html_content);
            $mail_content = str_replace('{{site_link}}', get_bloginfo('url'), $mail_content);

            if (count($posts) > 1) {
                $list_content = '';
                for ($i = 0; $i < count($posts); $i++) {
                    $row_content = file_get_contents($template_path . DIRECTORY_SEPARATOR .'list.html');
                    $row_content = str_replace('{{title}}', '
                        <a href="'.$posts[$i]['link'].'" style="color: #333333">' .
                            $posts[$i]['title'] . '
                        </a>', $row_content);
                    $row_content = str_replace('{{content}}', $posts[$i]['excerpt'], $row_content);
                    $row_content = str_replace('{{post-link}}', $posts[$i]['link'], $row_content);

                    $mail_content = str_replace('{{main_image}}', '', $mail_content);
                    $post_image = $this->get_main_image($posts[$i]['ID'], 'thumb');
                    $post_image_size = $this->get_main_image_size();

                    $row_content = ($post_image)
                        ? str_replace('{{post_image}}', '
                            <img alt="Featured Image" 
                                class="headerImage" 
                                id="edit-image-trigger" 
                                src="' . $post_image . '" 
                                width="' . $post_image_size . '" />', $row_content)
                        : $row_content = str_replace('{{post_image}}', '', $row_content);

                    $list_content .= $row_content;
                }

                $mail_content = str_replace('{{list}}', $list_content, $mail_content);
            } else {
                // Single post
                $row_content = file_get_contents($template_path . DIRECTORY_SEPARATOR . 'single.html');
                $row_content = str_replace('{{title}}', '
                        <a href="'.$posts[0]['link'].'" 
                            style="color: #333333; text-decoration: none">' .
                                $posts[0]['title'] . '
                        </a>', $row_content);
                $row_content = str_replace('{{content}}', $posts[0]['excerpt'], $row_content);
                $row_content = str_replace('{{post-link}}', $posts[0]['link'], $row_content);

                $post_image = $this->get_main_image($posts[0]['ID'], 'full');
                $post_image_size = $this->get_main_image_size();

                $mail_content = $post_image
                    ? str_replace('{{main_image}}', '
                        <img alt="Featured Image" 
                        class="headerImage" 
                        id="edit-image-trigger" 
                        src="'.$post_image.'" 
                        width="'.$post_image_size.'" />', $mail_content)
                    : str_replace('{{main_image}}', '', $mail_content);

                $mail_content = str_replace('{{list}}', $row_content, $mail_content);
            }

            $mail_subject = $this->compose_email_subject($posts, $frequency, $lang);

            $this->send_mail_API($mail_content, $mail_subject, $frequency, $lang);
        }
    }

    private function get_dynamic_post_title($posts, $criteria) {
        $titles = array_column($posts, 'title');
        $titles_sorted = ($criteria === self::DYNAMIC_CRITERIA_FIRST_POST)
            ? array_reverse($titles)
            : $titles;

        return reset($titles_sorted);
    }

    private function compose_email_subject($posts, $frequency, $lang) {
        $options = $this->get_plugin_options();

        if ($frequency === self::DAILY_FREQUENCY ) {
            $subject = ($options['mdirector_subject_type_daily'] === self::DYNAMIC_SUBJECT)
                ? $options['mdirector_subject_dynamic_prefix_daily_' . $lang] . ' ' .
                    $this->get_dynamic_post_title($posts,
                        $options['mdirector_subject_dynamic_value_daily'])
                : $options['mdirector_subject_daily_' . $lang];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_DAILY_MAIL_SUBJECT;
        } else {
            $subject = ($options['mdirector_subject_type_weekly'] === self::DYNAMIC_SUBJECT)
                ? $options['mdirector_subject_dynamic_prefix_weekly_' . $lang] . ' ' .
                    $this->get_dynamic_post_title($posts,
                        $options['mdirector_subject_dynamic_value_weekly'])
                : $options['mdirector_subject_weekly_' . $lang];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_WEEKLY_MAIL_SUBJECT;
        }

        return $subject;
    }

    private function get_delivery_campaign_id ($frequency,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG) {
        $options = $this->get_plugin_options();

        if ($frequency === self::DAILY_FREQUENCY) {
            return $options['mdirector_daily_campaign_' . $lang];
        }

        return $options['mdirector_weekly_campaign_' . $lang];
    }

    /**
     * @param        $mail_content
     * @param        $mail_subject
     * @param null   $frequency
     * @param string $lang
     *
     * @throws MDOAuthException2
     */
    private function send_mail_API($mail_content, $mail_subject, $frequency = null,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG) {
        $options = $this->get_plugin_options();
        $mdirector_active = get_option('mdirector_active');

        if ($mdirector_active == self::SETTINGS_OPTION_ON) {
            $mdirector_Newsletter_Api = new Mdirector_Newsletter_Api();
            $key = $options['mdirector_api'];
            $secret = $options['mdirector_secret'];
            $list_id = $this->get_current_list_id($frequency, $lang);
            $campaign_id = $this->get_delivery_campaign_id($frequency, $lang);

            $mdirector_send_resp =
                json_decode(
                    $mdirector_Newsletter_Api->callAPI(
                        $key,
                        $secret,
                        self::MDIRECTOR_API_DELIVERY_ENDPOINT,'POST',
                        [
                            'type' => 'email',
                            'name' => $frequency . '_' . date('Y_m_d'),
                            'fromName' => $options['mdirector_from_name']
                                ? $options['mdirector_from_name']
                                : 'from name',
                            'subject' => $mail_subject,
                            'campaign' => $campaign_id,
                            'language' => $lang,
                            'creativity' => base64_encode($mail_content),
                            'segments' => json_encode(['LIST-' . $list_id])
                        ]
                    )
                );

            $env_id = isset($mdirector_send_resp->data)
                ? $mdirector_send_resp->data->envId
                : null;

            // send the campaign
            if ($env_id) {
                $mdirector_Newsletter_Api->callAPI(
                    $key,
                    $secret,
                    self::MDIRECTOR_API_DELIVERY_ENDPOINT, 'PUT',
                    ['envId' => $env_id, 'date' => 'now']
                );
            }
        }
    }

    private function set_html_content_type() {
        return 'text/html';
    }

    public function get_user_templates() {
        return $available_templates = array_map('basename',
            glob(MDIRECTOR_TEMPLATES_PATH . '*', GLOB_ONLYDIR));
    }

    public function get_current_template($available_templates, $lang = null) {
        $options = $this->get_plugin_options();
        $template = 'mdirector_template_' . (!empty($lang) ? $lang : 'general');

        $current_template_selected = !empty($options[$template])
            ? $options[$template]
            : Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE;

        if (!in_array($current_template_selected, $available_templates) ) {
            $current_template_selected = Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE;
        }

        return $current_template_selected;
    }

    public function clean_newsletter_process($frequency, $lang) {
        $options = $this->get_plugin_options();
        $process = ($frequency === self::DAILY_FREQUENCY)
            ? 'mdirector_daily_sent_' . $lang
            : 'mdirector_weekly_sent_' . $lang;

        $options[$process] = date('Y-m-d H:i');

        update_option('mdirector_settings', $options);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function reset_deliveries_sent() {
        $options = $this->get_plugin_options();

        foreach ($this->get_current_languages() as $language) {
            $lang = $language['code'];
            $options['mdirector_daily_sent_' . $lang] = null;
            $options['mdirector_weekly_sent_' . $lang] = null;
        }

        update_option('mdirector_settings', $options);
    }

    /**
     * @return array
     * @throws MDOAuthException2
     */
    public function build_daily_mails() {
        $response = [];

        foreach( $this->get_current_languages() as $lang) {
            $response[$lang['code']] = $this->md_send_daily_mails($lang['code']);
        }

        return $response;
    }

    /**
     * @return array
     * @throws MDOAuthException2
     */
    public function build_weekly_mails() {
        $response = [];

        foreach ($this->get_current_languages() as $lang) {
            $response[$lang['code']] = $this->md_send_weekly_mails($lang['code']);
        }

        return $response;
    }

    public function get_current_list_id($type, $lang) {
        $options = $this->get_plugin_options();

        if (isset($options['mdirector_use_test_lists']) &&
            $options['mdirector_use_test_lists'] === self::SETTINGS_OPTION_ON) {
            return $options['mdirector_' . $type . '_test_list_' . $lang];
        }

        if (isset($options['mdirector_use_custom_lists']) &&
            $options['mdirector_use_custom_lists'] === self::SETTINGS_OPTION_ON) {
            return $options['mdirector_' . $type . '_custom_list_' . $lang];
        }

        return $options['mdirector_' . $type . '_list_' . $lang];
    }

    private function get_exclude_cats() {
        $options = $this->get_plugin_options();

        $exclude_cats = ($options['mdirector_exclude_cats'])
            ? unserialize($options['mdirector_exclude_cats'])
            : [];

        if (count($exclude_cats) > 0) {
            for ($i = 0; $i < count($exclude_cats); $i++) {
                $exclude_cats[$i] = -1 * abs($exclude_cats[$i]);
            }
        }

        return $exclude_cats;
    }

    private function build_posts($query) {
        $total_posts = count($query->posts);
        $posts = [];

        if (!empty($total_posts)) {
            for ($i = 0; $i < $total_posts; $i++) {
                $selected_post = $query->posts[$i];
                $posts[] = [
                    'ID' => $selected_post->ID,
                    'title' => $selected_post->post_title,
                    'content' => $selected_post->post_content,
                    'link' => get_permalink($selected_post->ID),
                    'excerpt' => $this->text_truncate($selected_post->post_content),
                    'date' => $selected_post->post_date
                ];
            }
        }

        return $posts;
    }

    /**
     * @param $lang
     *
     * @return bool
     * @throws MDOAuthException2
     */
    private function md_send_daily_mails($lang) {
        $options = $this->get_plugin_options();

        $hour = ($options['mdirector_hour_daily'])
            ? $options['mdirector_hour_daily']
            : self::MIDNIGHT_HOUR;
        $time_exploded = explode(':', $hour);
        $actual_time = strtotime(date('Y-m-d H:i:s'));
        $mail_sent = date( 'Y-m-d', strtotime(
            isset($options['mdirector_daily_sent_' . $lang])
                ? $options['mdirector_daily_sent_' . $lang]
                : null
        ));
        $can_send = $mail_sent != date('Y-m-d') ? 1 : 0;

        $from_date = date('Y-m-d H:i:s',
            mktime($time_exploded[0],$time_exploded[1], 00,
                date('m'), date('d') - 1, date('Y')));
        $to_date = date('Y-m-d H:i:s',
            mktime($time_exploded[0], $time_exploded[1], 00,
                date('m'), date('d'), date('Y')));

        if (isset($_POST['cpt_submit_test_now']) ||
            ($actual_time >= strtotime($to_date) && $can_send == 1)) {

            $args = [
                'post_type'     => 'post',
                'post_status'   => 'publish',
                'date_query'    => [
                    'after'     => $from_date,
                    'before'    => $to_date
                ],
                'nopaging '     => true
            ];

            if (!empty($exclude_cats = $this->get_exclude_cats())) {
                $args['cat'] = implode(', ', $exclude_cats);
            }

            do_action( 'wpml_switch_language', $lang );
            $query = new WP_Query($args);
            do_action( 'wpml_switch_language', $this->get_current_lang() );

            $posts = $this->build_posts($query);

            if (!empty($posts)) {
                $this->md_send_mail($posts, self::DAILY_FREQUENCY, $lang);
                $this->clean_newsletter_process(self::DAILY_FREQUENCY, $lang);

                return true;
            }

            trigger_error('There are no new posts for daily mails and lang ' .
                $lang . print_r($args, true), E_USER_NOTICE);
        }

        return false;
    }

    /**
     * @param $lang
     *
     * @return bool
     * @throws MDOAuthException2
     */
    private function md_send_weekly_mails($lang) {
        $options = $this->get_plugin_options();

        $day = $options['mdirector_frequency_day']
            ? $options['mdirector_frequency_day']
            : '1'; # Default: Monday
        $hour = $options['mdirector_hour_weekly']
            ? $options['mdirector_hour_weekly']
            : self::MIDNIGHT_HOUR;
        $time_exploded = explode(':', $hour);
        $actual_time = time();
        $mail_sent = date( 'Y-m-d', strtotime(
            isset($options['mdirector_weekly_sent_' . $lang])
                ? $options['mdirector_weekly_sent_' . $lang]
                : null
        ));
        $can_send = ($mail_sent !== date('Y-m-d')) ? 1 : 0;

        $from_date = date('Y-m-d H:i:s',
            mktime($time_exploded[0],$time_exploded[1], 00,
                date('m'), date('d') - 7, date('Y')));
        $to_date = date('Y-m-d H:i:s',
            mktime($time_exploded[0], $time_exploded[1], 00,
                date('m'), date('d'), date('Y')));

        if (isset($_POST['cpt_submit_test_now']) ||
            (date('N') === $day && ($actual_time >= strtotime($to_date)) && ($can_send === 1))) {

            $args = [
                'post_type'     => 'post',
                'post_status'   => 'publish',
                'date_query'    => [
                    'after'     => $from_date,
                    'before'    => $to_date
                ],
                'nopaging '     => true
            ];

            if (!empty($exclude_cats = $this->get_exclude_cats())) {
                $args['cat'] = implode(', ', $exclude_cats);
            }

            do_action( 'wpml_switch_language', $lang );
            $query = new WP_Query($args);
            do_action( 'wpml_switch_language', $this->get_current_lang() );

            $posts = $this->build_posts($query);

            if (!empty($posts)) {
                $this->md_send_mail($posts, self::WEEKLY_FREQUENCY, $lang);
                $this->clean_newsletter_process(self::WEEKLY_FREQUENCY, $lang);

                return true;
            }

            trigger_error('There are no new posts for weekly mails and lang ' .
                $lang . print_r($args, true), E_USER_NOTICE);
        }

        return false;
    }
}
