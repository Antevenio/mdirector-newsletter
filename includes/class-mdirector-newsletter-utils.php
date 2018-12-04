<?php

namespace MDirectorNewsletter\includes;

require_once(plugin_dir_path(__FILE__) . '../vendor/autoload.php');

/**
 * Fired during plugin activation.
 *
 *
 * @since      1.0.0
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 * @author     MDirector
 */
class Mdirector_Newsletter_Utils
{
    // Paths
    const MDIRECTOR_MAIN_URL = 'http://www.mdirector.com';
    const MDIRECTOR_API_DELIVERY_ENDPOINT = self::MDIRECTOR_MAIN_URL
    . '/api_delivery';
    const MDIRECTOR_API_CONTACT_ENDPOINT = self::MDIRECTOR_MAIN_URL
    . '/api_contact';
    const MDIRECTOR_API_LIST_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_list';
    const MDIRECTOR_API_CAMPAIGN_ENDPOINT = self::MDIRECTOR_MAIN_URL
    . '/api_campaign';

    const TEMPLATES_PATH = MDIRECTOR_TEMPLATES_PATH . self::DEFAULT_TEMPLATE
    . '/';
    const TEMPLATE_HTML_BASE_FILE = 'template.html';

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
    const EXCERPT_LENGTH_CHARS = 200;

    // Prefixes
    const FORM_PREFIX = 'mdirector_widget-';
    const DAILY_WEEKS_ALLOWED_PREFIX = 'mdirector_daily_weekday_';
    const FORM_CLASS = 'md__newsletter--form';
    const FORM_NAME = self::FORM_PREFIX . 'form';

    /**
     * @var Mdirector_Newsletter_Twig
     */
    protected $twigInstance;

    /**
     * @var \Twig_Environment
     */
    protected $twigUserTemplate;

    /**
     * @var \Twig_TemplateWrapper
     */
    protected $adminTemplate;

    /**
     * Mdirector_Newsletter_Utils constructor.
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function __construct()
    {
        $this->twigInstance = new Mdirector_Newsletter_Twig();
        $this->adminTemplate = $this->twigInstance->initAdminTemplate();
    }

    public function isWPML()
    {
        return function_exists('icl_object_id');
    }

    public function getCurrentLanguages()
    {
        if ($this->isWPML()) {
            return apply_filters('wpml_active_languages', null,
                'orderby=id&order=desc');
        }

        $defaultName = explode('_', get_locale())[0];
        $languages = [
            $defaultName => [
                'code' => $defaultName,
                'translated_name' => __('DEFAULT-LANGUAGE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
            ]
        ];

        return $languages;
    }

    public function getCurrentLang()
    {
        if ($this->isWPML()) {
            return ICL_LANGUAGE_CODE;
        }

        return self::MDIRECTOR_DEFAULT_USER_LANG;
    }

    /**
     * @param array $args
     * @param null  $instance
     *
     * @return bool|string
     * @throws \Throwable
     */
    public function getRegisterFormHTML($args = [], $instance = null)
    {
        extract($args, EXTR_SKIP);
        $options = $this->getPluginOptions();

        $mdirectorActive = get_option('mdirector_active');
        $output = '';

        if (!isset($beforeTitle)) {
            $beforeTitle = null;
        }

        if (!isset($afterTitle)) {
            $afterTitle = null;
        }

        if (empty($options['mdirector_frequency_daily'])
            && empty($options['mdirector_frequency_weekly'])) {
            return false;
        }

        $title = empty($instance['title'])
            ? ' ' : apply_filters('widget_title', $instance['title']);

        if (!empty($title)) {
            $output .= $beforeTitle . $title . $afterTitle;
        }

        if (!empty($description)) {
            $output .= '<p class="md__newsletter--description">'
                . $instance['description'] . '</p>';
        }

        if ($mdirectorActive === self::SETTINGS_OPTION_ON) {
            if ($options['mdirector_api'] && $options['mdirector_secret']) {
                $selectFrequency = $this->getSelectFrequency();
                $currentLang = $this->getCurrentLang();
                $currentPrivacyText = 'mdirector_policy_text_' . $currentLang;
                $currentPrivacyUrl = 'mdirector_policy_url_' . $currentLang;

                $accept = (isset($options[$currentPrivacyText])
                    && $options[$currentPrivacyText] != '')
                    ? $options[$currentPrivacyText]
                    : __('WIDGET-PRIVACY__POLICY__ACCEPTED',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);

                $mdPrivacyLink = (isset($options[$currentPrivacyUrl])
                    && $options[$currentPrivacyUrl] != '')
                    ? $options[$currentPrivacyUrl] : '#';

                $spinnerPath =
                    MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'assets/ajax-loader.png';

                $templateData = [
                    'formClass' => self::FORM_CLASS,
                    'formName' => self::FORM_NAME,
                    'formPrefix' => self::FORM_PREFIX,
                    'selectFrequency' => $selectFrequency,
                    'isSpinner' => file_exists($spinnerPath),
                    'spinnerPath' => $spinnerPath,
                    'privacyLink' => $mdPrivacyLink,
                    'privacyLinkText' => $accept
                ];
                $output .= $this->adminTemplate->renderBlock('subscriptionForm',
                    $templateData);
            }
        }

        return $output;
    }

    /**
     * @return string|null
     * @throws \Throwable
     */
    private function getSelectFrequency()
    {
        $options = $this->getPluginOptions();
        $selectFrequency = null;

        $templateData = [
            'fieldName' => self::FORM_PREFIX
        ];

        if ($options['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON
            && $options['mdirector_frequency_weekly']
            === self::SETTINGS_OPTION_ON) {
            $selectFrequency =
                $this->adminTemplate->renderBlock('selectFrequencyLayer',
                    $templateData);
        } else if ($options['mdirector_frequency_daily']
            === self::SETTINGS_OPTION_ON) {
            $templateData['value'] = self::DAILY_FREQUENCY;
            $selectFrequency =
                $this->adminTemplate->renderBlock('singleFrequencyLayer',
                    $templateData);
        } else if ($options['mdirector_frequency_weekly']
            === self::SETTINGS_OPTION_ON) {
            $templateData['value'] = self::WEEKLY_FREQUENCY;
            $selectFrequency =
                $this->adminTemplate->renderBlock('singleFrequencyLayer',
                    $templateData);
        }

        return $selectFrequency;
    }

    public function getUserTemplates()
    {
        return array_map('basename',
            glob(MDIRECTOR_TEMPLATES_PATH . '*', GLOB_ONLYDIR));
    }

    public function getCurrentTemplate($available_templates, $lang = null)
    {
        $options = $this->getPluginOptions();
        $template = 'mdirector_template_';

        if (isset($options[$template . $lang])) {
            $currentTemplateSelected = $options[$template . $lang];
        } else if (isset($options[$template . 'general'])) {
            $currentTemplateSelected = $options[$template . 'general'];
        } else {
            return Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE;
        }

        if (!in_array($currentTemplateSelected, $available_templates)) {
            return Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE;
        }

        return $currentTemplateSelected;
    }

    public function cleanNewsletterProcess($frequency, $lang)
    {
        $options = $this->getPluginOptions();
        $process = ($frequency === self::DAILY_FREQUENCY)
            ? 'mdirector_daily_sent_' . $lang
            : 'mdirector_weekly_sent_' . $lang;

        $options[$process] = date('Y-m-d H:i');

        update_option('mdirector_settings', $options);

        wp_reset_postdata();
        wp_reset_query();
    }

    public function resetDeliveriesSent()
    {
        $options = $this->getPluginOptions();

        foreach ($this->getCurrentLanguages() as $language) {
            $lang = $language['code'];
            $options['mdirector_daily_sent_' . $lang] = null;
            $options['mdirector_weekly_sent_' . $lang] = null;
        }

        update_option('mdirector_settings', $options);
    }

    private function getPluginOptions()
    {
        return get_option('mdirector_settings')
            ? get_option('mdirector_settings') : [];
    }

    private function textTruncate($string)
    {
        $string = wp_strip_all_tags($string);
        $string = preg_replace('|\[(.+?)\](.+?\[/\\1\])?|s', '', $string);

        if (preg_match('/<!--more(.*?)?-->/', $string, $matches)) {
            list($main) = explode($matches[0], $string, 2);

            return $main;
        } else {
            $string = htmlspecialchars($string);


            if (strlen($string) > self::EXCERPT_LENGTH_CHARS) {
                $string =
                    preg_replace('/\s+?(\S+)?$/', '...',
                        substr($string, 0, self::EXCERPT_LENGTH_CHARS));
            }

            return $string;
        }
    }

    private function getMainImageSize()
    {
        return (self::TEMPLATES_PATH === 'templates-mdirector/')
            ? self::MAX_IMAGE_SIZE_MDIRECTOR_TEMPLATE
            : self::MAX_IMAGE_SIZE_DEFAULT_TEMPLATE;
    }

    private function getMainImage($postId, $size)
    {
        if ($postId) {
            if (has_post_thumbnail($postId)) {
                $postThumbnailId = get_post_thumbnail_id($postId);
                $thumbnail =
                    wp_get_attachment_image_src($postThumbnailId, $size);

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
     * @return bool
     * @throws \MDOAuthException2
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function mdSendMail(
        $posts,
        $frequency,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG
    ) {
        add_filter('wp_mail_content_type', [$this, 'setHTMLContentType']);

        if (!empty($posts)) {
            $mailSubject =
                $this->composeEmailSubject($posts, $frequency, $lang);

            $templatePath = $this->getTemplatePath($lang);
            $templateURL = MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'templates/' . $this->getTemplateName($lang) . DIRECTORY_SEPARATOR;

            $templateMainFile = $this->getTemplateMainFile($templatePath);

            $templateData = [
                'templateURL' => $templateURL,
                'templatePath' => $templatePath,
                'header_title' => get_bloginfo('name'),
                'site_link' => get_bloginfo('url'),
                'posts' => $posts
            ];

            $this->twigUserTemplate =
                $this->twigInstance->initUserTemplate($templateData);
            $this->twigUserTemplate->loadTemplate($templateMainFile);

            $mailContent = ($this->templateMainFileIsTwig($templateMainFile))
                ? $this->twigUserTemplate->render(Mdirector_Newsletter_Twig::USER_TEMPLATE,
                    $templateData)
                : $this->parsingTemplate($templateData);

            return $this->sendMailAPI($mailContent, $mailSubject, $frequency,
                $lang);
        }

        return false;
    }

    private function getTemplateMainFile($templatePath)
    {
        $templateHTML = $templatePath . self::TEMPLATE_HTML_BASE_FILE;
        $templateTwig = $templatePath . Mdirector_Newsletter_Twig::USER_TEMPLATE;

        if (file_exists($templateHTML)) {
            return self::TEMPLATE_HTML_BASE_FILE;
        } elseif (file_exists($templateTwig)) {
            return Mdirector_Newsletter_Twig::USER_TEMPLATE;
        }

        return false;
    }

    private function templateMainFileIsTwig($templateMainFile)
    {
        $fileParts = pathinfo($templateMainFile);

        return $fileParts['extension'] === 'twig';
    }

    public function getTemplatePath($lang)
    {
        $templateName = $this->getTemplateName($lang);

        return MDIRECTOR_TEMPLATES_PATH . $templateName . DIRECTORY_SEPARATOR;
    }

    public function getTemplateName($lang)
    {
        $templatesAvailable = $this->getUserTemplates();

        return $this->getCurrentTemplate($templatesAvailable, $lang);
    }

    /**
     * @param $template_data
     *
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function parsingTemplate($template_data)
    {
        $template_data['list'] = implode('',
            array_map([$this, 'buildListFromPosts'], $template_data['posts']));

        return $this->twigUserTemplate->render(self::TEMPLATE_HTML_BASE_FILE,
            $template_data);
    }

    /**
     * @param $post
     *
     * @return string
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function buildListFromPosts($post)
    {
        $postData = [
            'post_image' => $this->getImageTagFromPost($post),
            'titleURL' => $post['link'],
            'title' => $post['title'],
            'content' => $post['excerpt']
        ];

        return $this->twigUserTemplate->render('list.html', $postData);
    }

    /**
     * @param $post
     *
     * @return string
     * @throws \Throwable
     */
    private function getImageTagFromPost($post)
    {
        if (!$post['post_image']) {
            return '';
        }

        $templateData = [
            'post' => $post
        ];

        return $this->adminTemplate->renderBlock('imageTagFromPost',
            $templateData);
    }

    private function getDynamicPostTitle($posts, $criteria)
    {
        $titles = array_column($posts, 'title');
        $titlesSorted = ($criteria === self::DYNAMIC_CRITERIA_FIRST_POST)
            ? array_reverse($titles)
            : $titles;

        return reset($titlesSorted);
    }

    private function composeEmailSubject($posts, $frequency, $lang)
    {
        $options = $this->getPluginOptions();

        if ($frequency === self::DAILY_FREQUENCY) {
            $subject = ($options['mdirector_subject_type_daily']
                === self::DYNAMIC_SUBJECT)
                ? $options['mdirector_subject_dynamic_prefix_daily_' . $lang]
                . ' ' .
                $this->getDynamicPostTitle($posts,
                    $options['mdirector_subject_dynamic_value_daily'])
                : $options['mdirector_subject_daily_' . $lang];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_DAILY_MAIL_SUBJECT;
        } else {
            $subject = ($options['mdirector_subject_type_weekly']
                === self::DYNAMIC_SUBJECT)
                ? $options['mdirector_subject_dynamic_prefix_weekly_' . $lang]
                . ' ' .
                $this->getDynamicPostTitle($posts,
                    $options['mdirector_subject_dynamic_value_weekly'])
                : $options['mdirector_subject_weekly_' . $lang];

            $subject = !empty(trim($subject))
                ? $subject
                : self::DEFAULT_WEEKLY_MAIL_SUBJECT;
        }

        return $subject;
    }

    private function getDeliveryCampaignId(
        $frequency,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG
    ) {
        $options = $this->getPluginOptions();

        $campaignId = ($frequency === self::DAILY_FREQUENCY)
            ? $options['mdirector_daily_campaign_' . $lang]
            : $options['mdirector_weekly_campaign_' . $lang];

        return $campaignId;
    }

    /**
     * @param        $mail_content
     * @param        $mail_subject
     * @param null   $frequency
     * @param string $lang
     *
     * @return bool
     * @throws \MDOAuthException2
     */
    private function sendMailAPI(
        $mail_content,
        $mail_subject,
        $frequency = null,
        $lang = self::MDIRECTOR_DEFAULT_USER_LANG
    ) {
        $options = $this->getPluginOptions();
        $mdirectorActive = get_option('mdirector_active');

        if ($mdirectorActive == self::SETTINGS_OPTION_ON) {
            $mdirectorNewsletterApi = new Mdirector_Newsletter_Api();
            $key = $options['mdirector_api'];
            $secret = $options['mdirector_secret'];
            $listId = $this->getCurrentListId($frequency, $lang);
            $campaignId = $this->getDeliveryCampaignId($frequency, $lang);

            $APIData = [
                'type' => 'email',
                'name' => $frequency . '_' . date('Y_m_d'),
                'fromName' => $options['mdirector_from_name']
                    ? $options['mdirector_from_name']
                    : 'from name',
                'subject' => $mail_subject,
                'campaign' => $campaignId,
                'language' => $lang,
                'creativity' => base64_encode($mail_content),
                'segments' => json_encode(['LIST-' . $listId])
            ];

            $mdirectorSendResp =
                json_decode(
                    $mdirectorNewsletterApi->callAPI(
                        $key,
                        $secret,
                        self::MDIRECTOR_API_DELIVERY_ENDPOINT, 'POST',
                        $APIData
                    )
                );

            if (isset($mdirectorSendResp->response) && $mdirectorSendResp->response === 'error') {
                return false;
            }

            $envId = isset($mdirectorSendResp->data)
                ? $mdirectorSendResp->data->envId
                : null;

            // send the campaign
            if ($envId) {
                $campaignData = [
                    'envId' => $envId,
                    'date' => 'now'
                ];

                $mdirectorNewsletterApi->callAPI(
                    $key,
                    $secret,
                    self::MDIRECTOR_API_DELIVERY_ENDPOINT, 'PUT',
                    $campaignData
                );
            }

            return true;
        }

        return false;
    }

    private function setHTMLContentType()
    {
        return 'text/html';
    }

    private function isDeliveryActive($lang, $type)
    {
        $options = $this->getPluginOptions();
        $delivery = 'mdirector_' . $type . '_custom_list_' . $lang . '_active';

        return isset($options[$delivery])
            ? $options[$delivery] === self::SETTINGS_OPTION_ON
            : false;
    }

    private function getExcludeCats()
    {
        $options = $this->getPluginOptions();

        $excludeCats = ($options['mdirector_exclude_cats'])
            ? unserialize($options['mdirector_exclude_cats'])
            : [];

        if (count($excludeCats) > 0) {
            for ($i = 0; $i < count($excludeCats); $i++) {
                $excludeCats[$i] = -1 * abs($excludeCats[$i]);
            }
        }

        return $excludeCats;
    }

    private function buildPosts($foundPosts, $lang)
    {
        $options = $this->getPluginOptions();
        $totalFoundPosts = count($foundPosts);
        $minimumEntries = $options['mdirector_minimum_entries'];

        if (
            is_numeric($minimumEntries) && $totalFoundPosts < $minimumEntries
        ) {
            $complementaryPosts =
                $this->getMinimumPostsForNewsletter($totalFoundPosts,
                    $minimumEntries, $lang);
            $foundPosts = array_merge($foundPosts, $complementaryPosts);
        }

        return array_map([$this, 'parsePost'], $foundPosts);
    }

    private function getMinimumPostsForNewsletter(
        $total_found_posts,
        $minimum_entries,
        $lang
    ) {
        $remainingItems = $minimum_entries - $total_found_posts;

        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'nopaging ' => true,
            'offset' => $total_found_posts,
            'posts_per_page' => $remainingItems
        ];

        if (!empty($excludeCats = $this->getExcludeCats())) {
            $args['cat'] = implode(', ', $excludeCats);
        }

        return $this->getPosts($args, $lang);
    }

    private function parsePost($post)
    {
        return [
            'ID' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'link' => get_permalink($post->ID),
            'excerpt' => $this->textTruncate($post->post_content),
            'date' => $post->post_date,
            'post_image' => $this->getMainImage($post->ID, 'thumb'),
            'post_image_size' => $this->getMainImageSize()
        ];
    }

    private function getPosts($args, $lang)
    {
        do_action('wpml_switch_language', $lang);
        $query = new \WP_Query($args);
        do_action('wpml_switch_language', $this->getCurrentLang());

        return $query->posts;
    }

    /**
     * @param $lang
     *
     * @return bool
     * @throws \MDOAuthException2
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function mdSendDailyMails($lang)
    {
        $options = $this->getPluginOptions();

        $hour = ($options['mdirector_hour_daily'])
            ? $options['mdirector_hour_daily']
            : self::MIDNIGHT_HOUR;
        $timeExploded = explode(':', $hour);
        $actualTime = strtotime(date('Y-m-d H:i:s'));
        $mailSent = date('Y-m-d', strtotime(
            isset($options['mdirector_daily_sent_' . $lang])
                ? $options['mdirector_daily_sent_' . $lang]
                : null
        ));

        $canSend = $this->checkIfDeliveryCanBeSent($mailSent);

        $fromDate =
            $this->calculateFromDate($timeExploded, self::DAILY_FREQUENCY);
        $toDate = $this->calculateToDate($timeExploded);

        if (isset($_POST['cpt_submit_test_now'])
            || ($actualTime >= strtotime($toDate) && $canSend)) {

            $args = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'date_query' => [
                    'after' => $fromDate,
                    'before' => $toDate
                ],
                'nopaging ' => true
            ];

            if (!empty($excludeCats = $this->getExcludeCats())) {
                $args['cat'] = implode(', ', $excludeCats);
            }

            $posts = $this->buildPosts($this->getPosts($args, $lang), $lang);

            if (!empty($posts)) {
                if ($this->mdSendMail($posts, self::DAILY_FREQUENCY, $lang)) {
                    $this->cleanNewsletterProcess(self::DAILY_FREQUENCY, $lang);

                    return true;
                };
            }

            trigger_error('There are no new posts for daily mails and lang ' .
                $lang . print_r($args, true), E_USER_NOTICE);
        }

        return false;
    }

    private function checkIfDeliveryCanBeSent($mailSent)
    {
        $options = $this->getPluginOptions();
        $currentDayOption =
            self::DAILY_WEEKS_ALLOWED_PREFIX . strtolower(date('D'));

        if (isset($options[$currentDayOption])
            && $options[$currentDayOption] !== self::SETTINGS_OPTION_ON) {
            return false;
        }

        return $mailSent !== date('Y-m-d');
    }

    /**
     * @param $lang
     *
     * @return bool
     * @throws \MDOAuthException2
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    private function mdSendWeeklyMails($lang)
    {
        $options = $this->getPluginOptions();

        $day = $options['mdirector_frequency_day']
            ? $options['mdirector_frequency_day']
            : '1'; # Default: Monday
        $hour = $options['mdirector_hour_weekly']
            ? $options['mdirector_hour_weekly']
            : self::MIDNIGHT_HOUR;
        $timeExploded = explode(':', $hour);
        $actualTime = time();
        $mailSent = date('Y-m-d', strtotime(
            isset($options['mdirector_weekly_sent_' . $lang])
                ? $options['mdirector_weekly_sent_' . $lang]
                : null
        ));
        $canSend = ($mailSent !== date('Y-m-d')) ? 1 : 0;

        $fromDate =
            $this->calculateFromDate($timeExploded, self::WEEKLY_FREQUENCY);
        $toDate = $this->calculateToDate($timeExploded);

        if (isset($_POST['cpt_submit_test_now'])
            || (date('N') === $day && ($actualTime >= strtotime($toDate))
                && ($canSend === 1))) {

            $args = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'date_query' => [
                    'after' => $fromDate,
                    'before' => $toDate
                ],
                'nopaging ' => true
            ];

            if (!empty($excludeCats = $this->getExcludeCats())) {
                $args['cat'] = implode(', ', $excludeCats);
            }

            $posts = $this->buildPosts($this->getPosts($args, $lang), $lang);

            if (!empty($posts)) {
                if ($this->mdSendMail($posts, self::WEEKLY_FREQUENCY, $lang)) {
                    $this->cleanNewsletterProcess(self::WEEKLY_FREQUENCY,
                        $lang);

                    return true;
                }
            }

            trigger_error('There are no new posts for weekly mails and lang ' .
                $lang . print_r($args, true), E_USER_NOTICE);
        }

        return false;
    }

    protected function calculateFromDate($time, $frequency)
    {
        $daysToSubtract = $frequency === self::DAILY_FREQUENCY ? 1 : 7;

        return date('Y-m-d H:i:s',
            mktime($time[0], $time[1], 00,
                date('m'), date('d') - $daysToSubtract, date('Y')));

    }

    protected function calculateToDate($time)
    {
        return date('Y-m-d H:i:s',
            mktime($time[0], $time[1], 00,
                date('m'), date('d'), date('Y')));
    }

    /**
     * @return array
     * @throws \MDOAuthException2
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function buildDailyMails()
    {
        $response = [];

        foreach ($this->getCurrentLanguages() as $language) {
            $lang = $language['code'];

            if ($this->isDeliveryActive($lang, self::DAILY_FREQUENCY)) {
                $response[$lang] = $this->mdSendDailyMails($lang);
            }
        }

        return $response;
    }

    /**
     * @return array
     * @throws \MDOAuthException2
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function buildWeeklyMails()
    {
        $response = [];

        foreach ($this->getCurrentLanguages() as $lang) {
            $lang = $lang['code'];

            if ($this->isDeliveryActive($lang, self::WEEKLY_FREQUENCY)) {
                $response[$lang] = $this->mdSendWeeklyMails($lang);
            }
        }

        return $response;
    }

    public function getCurrentListId($type, $lang)
    {
        $options = $this->getPluginOptions();

        if (isset($options['mdirector_use_test_lists'])
            && $options['mdirector_use_test_lists']
            === self::SETTINGS_OPTION_ON) {
            return $options['mdirector_' . $type . '_test_list_' . $lang];
        }

        if (isset($options['mdirector_use_custom_lists'])
            && $options['mdirector_use_custom_lists']
            === self::SETTINGS_OPTION_ON) {
            return $options['mdirector_' . $type . '_custom_list_' . $lang];
        }

        return $options['mdirector_' . $type . '_list_' . $lang];
    }
}
