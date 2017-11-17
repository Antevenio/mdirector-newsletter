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
    const MDIRECTOR_API_ENDPOINT = 'http://www.mdirector.com/api_delivery';
    const TEMPLATES_PATH = 'templates/';
    const DAILY_FREQUENCY = 'daily';
    const WEEKLY_FREQUENCY = 'weekly';
    const DEFAULT_DAILY_MAIL_SUBJECT = 'Daily mail';
    const DEFAULT_WEEKLY_MAIL_SUBJECT = 'Weekly mail';
    const DYNAMIC_SUBJECT = 'dynamic';
    const DYNAMIC_CRITERIA_FIRST_POST = 'first_post';
    const DYNAMIC_CRITERIA_LAST_POST = 'last_post';

    private function text_truncate($string) {
        $string = wp_strip_all_tags($string);
        $string = preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $string);

        if ( preg_match('/<!--more(.*?)?-->/', $string, $matches) ) {
            list($main, $extended) = explode($matches[0], $string, 2);

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

    private function md_send_mail($posts, $frequency) {
        $settings = get_option('mdirector_settings');
        add_filter( 'wp_mail_content_type', [$this, 'set_html_content_type'] );

        if (!empty($posts)) {
            $html_content = file_get_contents(MDIRECTOR_NEWSLETTER_PLUGIN_DIR . '/' . self::TEMPLATES_PATH . 'template.html');

            // Time to replace mail content
            $mail_content = str_replace('{{header_title}}', get_bloginfo('name'), $html_content);
            $mail_content = str_replace('{{site_link}}', get_bloginfo('url'), $mail_content);

            if (count($posts) > 1) {
                $list_content = '';
                for ($i = 0; $i < count($posts); $i++) {
                    $row_content = file_get_contents(MDIRECTOR_NEWSLETTER_PLUGIN_DIR . '/' . self::TEMPLATES_PATH . 'list.html');
                    $row_content = str_replace('{{title}}', '<a href="'.$posts[$i]['link'].'" style="color: #333333">'.$posts[$i]['title'].'</a>', $row_content);
                    $row_content = str_replace('{{content}}', $posts[$i]['excerpt'], $row_content);

                    $mail_content = str_replace('{{main_image}}', '', $mail_content);
                    $post_image = $this->get_main_image($posts[$i]['ID'], 'thumb');

                    $row_content = ($post_image)
                        ? str_replace('{{post_image}}', '<img alt="" class="headerImage" id="edit-image-trigger" src="' . $post_image . '" style="max-width: 100%"/>', $row_content)
                        : $row_content = str_replace('{{post_image}}', '', $row_content);

                    $list_content .= $row_content;
                }

                $mail_content = str_replace('{{list}}', $list_content, $mail_content);
            } else {
                // Single post
                $row_content = file_get_contents(MDIRECTOR_NEWSLETTER_PLUGIN_DIR . '/' . self::TEMPLATES_PATH . 'single.html');
                $row_content = str_replace('{{title}}', '<a href="'.$posts[0]['link'].'" style="color: #333333; text-decoration: none">'.$posts[0]['title'].'</a>', $row_content);
                $row_content = str_replace('{{content}}', $posts[0]['excerpt'], $row_content);

                $post_image = $this->get_main_image($posts[0]['ID'], 'full');

                $mail_content = $post_image
                    ? str_replace('{{main_image}}', '<img alt="" class="headerImage" id="edit-image-trigger" src="'.$post_image.'" style="max-width: 490px;" />', $mail_content)
                    : str_replace('{{main_image}}', '', $mail_content);

                $mail_content = str_replace('{{list}}', $row_content, $mail_content);
            }

            $mail_subject = $this->compose_email_subject($settings, $posts, $frequency);
            $this->send_mail_API($mail_content, $mail_subject, $frequency);
        }
    }

    private function get_dynamic_post_title($posts, $criteria) {
        $titles = array_column($posts, 'title');
        $titles_sorted = ($criteria === self::DYNAMIC_CRITERIA_FIRST_POST)
            ? array_reverse($titles)
            : $titles;

        return reset($titles_sorted);
    }

    private function compose_email_subject($settings, $posts, $frequency) {
        if ($frequency === self::DAILY_FREQUENCY ) {
            $subject = ($settings['subject_type_daily'] === self::DYNAMIC_SUBJECT)
                ? $settings['subject_dynamic_prefix_daily'] . ' ' .
                    $this->get_dynamic_post_title($posts, $settings['subject_dynamic_value_daily'])
                : $settings['subject_daily'];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_DAILY_MAIL_SUBJECT;
        } else {
            $subject = ($settings['subject_type_weekly'] === self::DYNAMIC_SUBJECT)
                ? $settings['subject_dynamic_prefix_weekly'] . ' ' .
                    $this->get_dynamic_post_title($posts, $settings['subject_dynamic_value_weekly'])
                : $settings['subject_weekly'];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_WEEKLY_MAIL_SUBJECT;
        }

        return $subject;
    }

    private function send_mail_API($mail_content, $mail_subject, $frequency = null) {
        $settings = get_option('mdirector_settings');
        $mdirector_active = get_option('mdirector_active');

        if ($mdirector_active == 'yes') {
            $key = $settings['api'];
            $secret = $settings['secret'];

            if ($frequency === self::DAILY_FREQUENCY) {
                $list_id = get_option('mdirector_daily_list');
                $campaign_id = get_option('mdirector_daily_campaign');
            } else {
                $list_id = get_option('mdirector_weekly_list');
                $campaign_id = get_option('mdirector_weekly_campaign');
            }

            $mdirector_send_resp =
                json_decode(
                    Mdirector_Newsletter_Api::callAPI($key, $secret, self::MDIRECTOR_API_ENDPOINT,
                        'POST',
                        [
                            'type' => 'email',
                            'name' => $frequency . '_' . date('Y_m_d'),
                            'fromName' => $settings['from_name'] ? $settings['from_name'] : 'from name',
                            'subject' => $mail_subject,
                            'campaign' => $campaign_id,
                            'language' => 'es',
                            'creativity' => base64_encode($mail_content),
                            'segments' => json_encode(['LIST-' . $list_id])
                        ]
                    )
                );

            $env_id = $mdirector_send_resp->data->envId;

            // send the campaign
            if ($env_id) {
                Mdirector_Newsletter_Api::callAPI(
                    $key, $secret, self::MDIRECTOR_API_ENDPOINT, 'PUT',
                    ['envId' => $env_id, 'date' => 'now']
                );
            }
        }
    }

    private function set_html_content_type() {
        return 'text/html';
    }

    private function clean_newsletter_process($frequency) {
        $process = ($frequency === self::DAILY_FREQUENCY)
            ? 'mdirector_daily_sent'
            : 'mdirector_weekly_sent';

        update_option($process, date('Y-m-d') );
        wp_reset_postdata();
        wp_reset_query();
    }

    public function md_send_daily_mails($settings) {
        $hour = ($settings['hour_daily']) ? $settings['hour_daily'] : '00:00';
        $time_exploded = explode(':', $hour);
        $actual_time = current_time('timestamp');
        $mail_sent = get_option('mdirector_daily_sent');
        $can_send = ($mail_sent != date('Y-m-d')) ? 1 : 0;

        $from_date = date('Y-m-d H:i:s', mktime($time_exploded[0],$time_exploded[1], 00, date('m'), date('d') - 1, date('Y')));
        $to_date = date('Y-m-d H:i:s', mktime($time_exploded[0], $time_exploded[1], 00, date('m'), date('d'), date('Y')));

        if ($_POST['cpt_submit_test_now'] || ($actual_time >= strtotime($to_date) && $can_send == 1)) {
            $args = [
                'post_type' 	=> 'post',
                'post_status'   => 'publish',
                'date_query'    => [
                    'column'  	=> 'post_date',
                    'after'   	=> $from_date,
                    'before'	=> $to_date
                ]
            ];
            $exclude_cats = ($settings['exclude_cats']) ? unserialize($settings['exclude_cats']) : [];

            if (count($exclude_cats) > 0) {
                for ($i = 0; $i < count($exclude_cats); $i++) {
                    $exclude_cats[$i] = -1 * abs($exclude_cats[$i]);
                }

                $args['cat'] = implode(', ', $exclude_cats);
            }

            $query = new WP_Query($args);
            $total_posts = count($query->posts);
            $posts = [];

            if (! empty($total_posts)) {
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

            if (!empty($posts)) {
                $this->md_send_mail($posts, self::DAILY_FREQUENCY);
                $this->clean_newsletter_process(self::DAILY_FREQUENCY);

                return true;
            }

            $this->clean_newsletter_process(self::DAILY_FREQUENCY);
        }

        return false;
    }

    public function md_send_weekly_mails($settings) {
        $day = $settings['frequency_day'] ? $settings['frequency_day'] : '1'; # Default: Monday
        $hour = $settings['hour_weekly'] ? $settings['hour_weekly'] : '00:00';
        $time_exploded = explode(':', $hour);

        $from_date = date('Y-m-d H:i:s', mktime($time_exploded[0],$time_exploded[1], 00, date('m'), date('d') - 7, date('Y')));
        $to_date = date('Y-m-d H:i:s', mktime($time_exploded[0], $time_exploded[1], 00, date('m'), date('d'), date('Y')));

        // We check if today is our selected day
        $actual_time = current_time('timestamp');
        $mail_sent = get_option('mdirector_weekly_sent');

        $can_send = ($mail_sent !== date('Y-m-d')) ? 1 : 0;

        if ($_POST['cpt_submit_test_now'] ||
            (date('N') === $day && ($actual_time >= strtotime($to_date)) && ($can_send === 1))) {
            $args = [
                'post_type' 	=> 'post',
                'post_status'   => 'publish',
                'date_query'    => [
                    'column'  	=> 'post_date',
                    'after'   	=> $from_date,
                    'before'	=> $to_date
                ]
            ];
            $exclude_cats = ($settings['exclude_cats']) ? unserialize($settings['exclude_cats']) : [];

            if (count($exclude_cats) > 0) {
                for ($i = 0; $i < count($exclude_cats); $i++) {
                    $exclude_cats[$i] = -1 * abs($exclude_cats[$i]);
                }

                $args['cat'] = implode(', ', $exclude_cats);
            }

            $query = new WP_Query($args);
            $total_posts = count($query->posts);

            $posts = [];

            if ($total_posts > 0) {
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

            if (count($posts) > 0) {
                $this->md_send_mail($posts, self::WEEKLY_FREQUENCY);
                $this->clean_newsletter_process(self::WEEKLY_FREQUENCY);

                return true;
            }

            $this->clean_newsletter_process(self::WEEKLY_FREQUENCY);
        }

        return false;
    }
}
