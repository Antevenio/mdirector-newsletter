<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    MDirector_Newsletter
 * @subpackage MDirector_Newsletter/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    MDirector_Newsletter
 * @subpackage MDirector_Newsletter/admin
 * @author     MDirector
 */
class MDirector_Newsletter_Admin {
    //const MDIRECTOR_MAIN_URL = 'http://www.mdirector.com';
    const REQUEST_RESPONSE_SUCCESS = 'ok';
    const NO_VALUE = '---';
    const DEFAULT_SETTINGS_TAB = 'settings';
    const STR_SEPARATOR = '-';
    const SETTINGS_SEPARATOR = '_';
    const MIDNIGHT = '23:59';
    const FIXED_SUBJECT = 'fixed';
    const TEST_FLAG = 'test';

    protected $frequency_days;
    protected $dynamic_subject_values;
    protected $plugin_notices = [];

    protected $current_languages = [];

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $mdirector_newsletter The ID of this plugin.
     */
    private $mdirector_newsletter;

    protected $api_key;
    protected $api_secret;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * @var Mdirector_Newsletter_Utils
     */
    private $Mdirector_utils;

    /**
     * @var Mdirector_Newsletter_Api
     */
    private $Mdirector_Newsletter_Api;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param      string $mdirector_newsletter The name of this plugin.
     * @param      string $version              The version of this plugin.
     */
    public function __construct($mdirector_newsletter, $version) {
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-utils.php';

        $this->mdirector_newsletter = $mdirector_newsletter;
        $this->version = $version;
        $this->Mdirector_utils = new Mdirector_Newsletter_Utils();
        $this->Mdirector_Newsletter_Api = new Mdirector_Newsletter_Api();

        $this->api_key = $this->get_plugin_api_key();
        $this->api_secret = $this->get_plugin_api_secret();

        add_action('admin_menu', [$this, 'mdirector_newsletter_menu']);
    }

    private function get_plugin_api_key() {
        $options = $this->get_plugin_options();

        return isset($options['mdirector_api'])
            ? $options['mdirector_api'] : null;
    }

    private function get_plugin_api_secret() {
        $options = $this->get_plugin_options();

        return isset($options['mdirector_secret'])
            ? $options['mdirector_secret'] : null;
    }

    private function get_plugin_options() {
        return get_option('mdirector_settings')
            ? get_option('mdirector_settings') : [];
    }

    private function set_current_languages() {
        $this->current_languages = $this->Mdirector_utils->get_current_languages();
    }

    public function print_notices() {
        if (count($this->plugin_notices)) {
            echo join(' ' , $this->plugin_notices);
        }
    }

    private function is_plugin_configured() {
        $options = $this->get_plugin_options();
        $this->api_key || ($this->api_key = $options['mdirector_api']);
        $this->api_secret || ($this->api_secret = $options['mdirector_secret']);

        return $this->api_key && $this->api_secret;
    }

    private function compose_list_name($list, $type) {
        $blog_name = sanitize_title_with_dashes(get_bloginfo('name'));
        $lang = key($list);

        $list_name = $blog_name . self::STR_SEPARATOR .
            ($type === Mdirector_Newsletter_Utils::DAILY_FREQUENCY
                ? Mdirector_Newsletter_Utils::DAILY_FREQUENCY
                : Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY) .
            self::STR_SEPARATOR . $lang;

        return $list_name;
    }

    /**
     * @param $listName
     *
     * @return array|mixed|object
     * @throws MDOAuthException2
     */
    private function create_list_via_API($listName) {
        return json_decode($this->Mdirector_Newsletter_Api->callAPI(
            $this->api_key,
            $this->api_secret,
            Mdirector_Newsletter_Utils::MDIRECTOR_API_LIST_ENDPOINT, 'POST',
            ['listName' => $listName]));
    }

    private function restore_default_lists() {
        $options = $this->get_plugin_options();
        foreach ($this->current_languages as $lang => $data) {
            $options['mdirector_daily_custom_list_' . $lang]
                = $options['mdirector_daily_list_' . $lang];
            $options['mdirector_weekly_custom_list_' . $lang]
                = $options['mdirector_weekly_list_' . $lang];
        }

        $options['mdirector_use_custom_lists'] = null;
        update_option('mdirector_settings', $options);
    }

    /**
     * @param $lists
     * @param $array_list_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_daily_lists($lists, $array_list_names) {
        $options = $this->get_plugin_options();

        if ($options['mdirector_use_custom_lists']) {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('DAILY-LISTS__ERROR-NOTICE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';

            $this->restore_default_lists();
        }

        foreach($lists as $lang => $id) {
            $daily_name = $this->compose_list_name([$lang => $id], Mdirector_Newsletter_Utils::DAILY_FREQUENCY);

            if (in_array($daily_name, $array_list_names)) {
                $daily_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_daily_id = $this->create_list_via_API($daily_name);

            if ($mdirector_daily_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_daily_list_' . $lang] = $mdirector_daily_id->listId;
                $options['mdirector_daily_list_name_' . $lang] = $daily_name;
                update_option('mdirector_settings', $options);

                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--info-notice">'
                    . __('DAILY-LISTS__NEW-DAILY-LIST-ADDED',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ': ' . $daily_name
                    . '</div>';
            } else {
                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--error-notice">'
                    . __('DAILY-LISTS__NEW-DAILY-LIST-ADDED-ERROR',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';
            }
        }
    }

    /**
     * @param $lists
     * @param $array_list_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_weekly_lists($lists, $array_list_names) {
        $options = $this->get_plugin_options();

        if ($options['mdirector_use_custom_lists']) {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('WEEKLY-LISTS__ERROR-NOTICE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';

            $this->restore_default_lists();
        }

        foreach($lists as $lang => $id) {
            $weekly_name = $this->compose_list_name([$lang => $id],
                Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

            if (in_array($weekly_name, $array_list_names)) {
                $weekly_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_weekly_id = $this->create_list_via_API($weekly_name);

            if ($mdirector_weekly_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_weekly_list_' . $lang] = $mdirector_weekly_id->listId;
                $options['mdirector_weekly_list_name_' . $lang] = $weekly_name;
                update_option('mdirector_settings', $options);

                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--info-notice">'
                    . __('WEEKLY-LISTS__NEW-WEEKLY-LIST-ADDED' . ': ',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . $weekly_name
                    . '</div>';
            } else {
                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--error-notice">'
                    . __('WEEKLY-LISTS__NEW-WEEKLY-LIST-ADDED-ERROR',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';
            }
        }
    }

    private function get_user_lists($type) {
        $options = $this->get_plugin_options();
        $lists = [];
        foreach($this->current_languages as $language) {
            $lang = $language['code'];
            $lists[$lang] = $options[
                (($type === Mdirector_Newsletter_Utils::DAILY_FREQUENCY)
                    ? 'mdirector_daily_list'
                    : 'mdirector_weekly_list')
                . '_' . $lang
            ];
        }

        return $lists;
    }

    /**
     * Create Lists on MDirector Account
     * @throws MDOAuthException2
     */
    public function create_mdirector_lists() {
        if (!$this->is_plugin_configured()) {
            return false;
        }

        $options = $this->get_plugin_options();
        $mdirector_daily_lists = $this->get_user_lists(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
        $mdirector_weekly_lists = $this->get_user_lists(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

        $array_list_names = [];

        $list_of_lists =
            json_decode($this->Mdirector_Newsletter_Api->callAPI(
                $this->api_key,
                $this->api_secret,
                Mdirector_Newsletter_Utils::MDIRECTOR_API_LIST_ENDPOINT, 'GET'));

        if ($list_of_lists->response === self::REQUEST_RESPONSE_SUCCESS) {
            foreach ($list_of_lists->lists as $list) {
                if ($list_found = array_search($list->id, $mdirector_daily_lists)) {
                    unset($mdirector_daily_lists[$list_found]);
                    $options['mdirector_daily_list_name_' . $list_found] = $list->name;
                }

                if ($list_found = array_search($list->id, $mdirector_weekly_lists)) {
                    unset($mdirector_weekly_lists[$list_found]);
                    $options['mdirector_weekly_list_name_' . $list_found] = $list->name;
                }

                $array_list_names[] = $list->name;
            }

            update_option('mdirector_settings', $options);
        }

        if (!empty($mdirector_daily_lists)) {
            $this->create_mdirector_daily_lists($mdirector_daily_lists, $array_list_names);
        }

        if (!empty($mdirector_weekly_lists)) {
            $this->create_mdirector_weekly_lists($mdirector_weekly_lists, $array_list_names);
        }

        return true;
    }

    /**
     * @param $lists
     * @param $array_campaign_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_weekly_campaigns($lists, $array_campaign_names) {
        $options = $this->get_plugin_options();

        foreach($lists as $lang => $id) {
            $weekly_name = $this->compose_list_name([$lang => $id],
                Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

            if (in_array($weekly_name, $array_campaign_names)) {
                $weekly_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_weekly_id = $this->create_campaign_via_API($weekly_name);

            if ($mdirector_weekly_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_weekly_campaign_' . $lang] = $mdirector_weekly_id->data->camId;
                $options['mdirector_weekly_campaign_name_' . $lang] = $weekly_name;
                update_option('mdirector_settings', $options);

                $this->plugin_notices[] = '<div class="updated md_newsletter--info-notice">'
                    . __('WEEKLY-CAMPAIGN__NEW-WEEKLY-CAMPAIGN-ADDED' . ': ',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . $weekly_name . '</div>';
            } else {
                $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                    . __('WEEKLY-CAMPAIGN__NEW-WEEKLY-CAMPAIGN-ADDED-ERROR',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';
            }
        }
    }

    /**
     * @param $campaignName
     *
     * @return array|mixed|object
     * @throws MDOAuthException2
     */
    private function create_campaign_via_API($campaignName) {
        return json_decode($this->Mdirector_Newsletter_Api->callAPI(
            $this->api_key,
            $this->api_secret,
            Mdirector_Newsletter_Utils::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'POST',
            ['name' => $campaignName]));
    }

    /**
     * @param $campaigns
     * @param $array_campaign_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_daily_campaigns($campaigns, $array_campaign_names) {
        $options = $this->get_plugin_options();

        foreach($campaigns as $lang => $id) {
            $daily_name = $this->compose_list_name([$lang => $id],
                Mdirector_Newsletter_Utils::DAILY_FREQUENCY);

            if (in_array($daily_name, $array_campaign_names)) {
                $daily_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_daily_id = $this->create_campaign_via_API($daily_name);

            if ($mdirector_daily_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $options['mdirector_daily_campaign_' . $lang] = $mdirector_daily_id->data->camId;
                $options['mdirector_daily_campaign_name_' . $lang] = $daily_name;
                update_option('mdirector_settings', $options);

                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--info-notice">'
                    . __('DAILY-CAMPAIGN__NEW-DAILY-CAMPAIGN-ADDED' . ': ',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . $daily_name . '</div>';
            } else {
                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--error-notice">'
                    . __('DAILY-CAMPAIGN__NEW-DAILY-CAMPAIGN-ADDED-ERROR',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';
            }
        }
    }

    private function get_user_campaigns($type) {
        $options = $this->get_plugin_options();
        $campaigns = [];

        foreach ($this->current_languages as $language) {
            $lang = $language['code'];
            $campaigns[$lang] = $options[
                (($type === Mdirector_Newsletter_Utils::DAILY_FREQUENCY)
                    ? 'mdirector_daily_campaign'
                    : 'mdirector_weekly_campaign')
                . '_' . $lang
            ];
        }

        return $campaigns;
    }

    /**
     * Create campaigns on MDirector Account
     * @throws MDOAuthException2
     */
    public function create_mdirector_campaigns() {
        if (!$this->is_plugin_configured()) {
            return false;
        }

        $options = $this->get_plugin_options();
        $mdirector_daily_campaigns = $this->get_user_campaigns(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
        $mdirector_weekly_campaigns = $this->get_user_campaigns(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

        $array_campaigns_names = [];

        $list_of_campaigns =
            json_decode($this->Mdirector_Newsletter_Api->callAPI(
                $this->api_key,
                $this->api_secret,
                Mdirector_Newsletter_Utils::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'GET'));

        if ($list_of_campaigns->response === self::REQUEST_RESPONSE_SUCCESS) {
            foreach ($list_of_campaigns->data as $campaign) {
                if ($campaign_found = array_search($campaign->id, $mdirector_daily_campaigns)) {
                    unset($mdirector_daily_campaigns[$campaign_found]);
                    $options['mdirector_daily_campaign_name_' . $campaign_found] =
                        $campaign->campaignName;
                }

                if ($campaign_found = array_search($campaign->id, $mdirector_weekly_campaigns)) {
                    unset($mdirector_weekly_campaigns[$campaign_found]);
                    $options['mdirector_weekly_campaign_name_' . $campaign_found] =
                        $campaign->campaignName;
                }

                update_option('mdirector_settings', $options);

                $array_campaigns_names[] = $campaign->campaignName;
            }
        }

        if (!empty($mdirector_daily_campaigns)) {
            $this->create_mdirector_daily_campaigns($mdirector_daily_campaigns,
                $array_campaigns_names);
        }

        if (!empty($mdirector_weekly_campaigns)) {
            $this->create_mdirector_weekly_campaigns($mdirector_weekly_campaigns,
                $array_campaigns_names);
        }

        return true;
    }

    /**
     * Adds the plugin admin menu.
     *
     * @since    1.0.0
     */
    public function mdirector_newsletter_menu() {
        $menu = add_menu_page('MDirector', 'MDirector', 'manage_options',
            'mdirector-newsletter', [$this, 'mdirector_newsletter_init'],
            MDIRECTOR_NEWSLETTER_PLUGIN_URL . '/assets/icon_mdirector.png');

        add_action("load-{$menu}", [$this, 'mdirector_newsletter_save']);
    }

    private function get_current_tab() {
        return isset($_REQUEST['tab'])
            ? $_REQUEST['tab']
            : self::DEFAULT_SETTINGS_TAB;
    }

    private function save_settings($data) {
        $options = $this->get_plugin_options();

        if ($this->get_current_tab() === 'settings') {
            unset($data['mdirector-newsletter-submit']);

            $form_fields = $this->preg_grep_keys('/^mdirector_/', $data);
            $options = array_merge(
                $options,
                $form_fields);

            // Need to override some values...
            $options['mdirector_use_custom_lists'] = isset($data['mdirector_use_custom_lists'])
                ? $data['mdirector_use_custom_lists'] : null;
            $options['mdirector_frequency_weekly'] = isset($data['mdirector_frequency_weekly'])
                ? $data['mdirector_frequency_weekly'] : null;
            $options['mdirector_frequency_daily'] = isset($data['mdirector_frequency_daily'])
                ? $data['mdirector_frequency_daily'] : null;

            $options['mdirector_exclude_cats'] =
                ((isset($data['mdirector_exclude_cats']) && count($data['mdirector_exclude_cats']) > 0)
                    ? serialize($data['mdirector_exclude_cats'])
                    : []);

            update_option('mdirector_settings', $options);
        }
    }

    private function preg_grep_keys($pattern, $input, $flags = 0) {
        return array_intersect_key(
            $input,
            array_flip(preg_grep($pattern, array_keys($input), $flags))
        );
    }

    private function save_debug_settings($data) {
        $options = $this->get_plugin_options();
        $daily_fields = $this->preg_grep_keys('/^mdirector_daily/', $data);
        $weekly_fields = $this->preg_grep_keys('/^mdirector_weekly/', $data);

        $options = array_merge(
            $options,
            $daily_fields,
            $weekly_fields);

        $options['mdirector_use_test_lists'] =
            $data['mdirector_use_test_lists'];

        update_option('mdirector_settings', $options);
    }

    /**
     * @throws MDOAuthException2
     */
    private function sending_test() {
        $options = $this->get_plugin_options();

        if ($options['mdirector_frequency_daily'] ===
            Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
                $options['mdirector_hour_daily'] = self::MIDNIGHT;
                if ($response_dailies = $this->Mdirector_utils->build_daily_mails()) {
                    foreach ($response_dailies as $lang => $result) {
                        $list_name = $this->Mdirector_utils->get_current_list_id(
                            Mdirector_Newsletter_Utils::DAILY_FREQUENCY, $lang);

                        if ($result) {
                            $this->plugin_notices[] =
                                '<div class="updated md_newsletter--info-notice">'
                                . __('SENDING-TEST__DAILY-SENDING',
                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
                                . ': <strong>'
                                . $list_name
                                . '</strong></div>';
                        }
                    }
                } else {
                    $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                        . __('SENDING-TEST__DAILY-SENDING-ERROR',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' '
                        . __('NO-ENTRIES-IN-BLOG',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '?'
                        . '</div>';
                }
        } else {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('SENDING-TEST__DAILY-DEACTIVATED',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' '
                . '</div>';
        }

        if ($options['mdirector_frequency_weekly'] ===
            Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
                $options['mdirector_hour_weekly'] = self::MIDNIGHT;
                if ($response_weeklies = $this->Mdirector_utils->build_weekly_mails() ) {
                    foreach ($response_weeklies as $lang => $result) {
                        $list_name = $this->Mdirector_utils->get_current_list_id(
                            Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY, $lang);

                        if ($result) {
                            $this->plugin_notices[] =
                                '<div class="updated md_newsletter--info-notice">'
                                . __('SENDING-TEST__WEEKLY-SENDING',
                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
                                . ': <strong>'
                                . $list_name
                                . '</strong></div>';
                        }
                    }
                } else {
                    $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                        . __('SENDING-TEST__WEEKLY-SENDING-ERROR',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' '
                        . __('NO-ENTRIES-IN-BLOG',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '?'
                        . '</div>';
                }
        } else {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('SENDING-TEST__WEEKLY-DEACTIVATED',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' '
                . '</div>';
        }
    }

    /**
     * @throws MDOAuthException2
     */
    public function mdirector_newsletter_save() {
        if (isset($_POST['mdirector-newsletter-submit']) &&
            $_POST['mdirector-newsletter-submit'] ===
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $this->save_settings($_POST);
        } else if (isset($_POST['save-debug-submit']) &&
            $_POST['save-debug-submit'] ===
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $this->save_debug_settings($_POST);
        }

        // Sending the campaigns immediately
        if (isset($_POST['cpt_submit_test_now'])) {
            $this->sending_test();
        }

        // Reset counters
        if (isset($_POST['cpt_submit_reset_now'])) {
            $this->Mdirector_utils->reset_deliveries_sent();
            $this->plugin_notices[] = '<div class="updated md_newsletter--info-notice">'
                . __('LAST-SENDINGS-RESTARTED',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
                . '</div>';
        }
    }

    /**
     * Setting translations strings after current language has been loaded.
     */
    private function set_translations_strings() {
        $this->frequency_days = [
            '1' => __('WEEKDAY__MONDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            '2' => __('WEEKDAY__TUESDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            '3' => __('WEEKDAY__WEDNESDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            '4' => __('WEEKDAY__THURSDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            '5' => __('WEEKDAY__FRIDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            '6' => __('WEEKDAY__SATURDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            '7' => __('WEEKDAY__SUNDAY',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
        ];

        $this->dynamic_subject_values = [
            Mdirector_Newsletter_Utils::DYNAMIC_CRITERIA_FIRST_POST =>
                __('SUBJECT-VALUES__FIRST-POST-TITLE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN),
            Mdirector_Newsletter_Utils::DYNAMIC_CRITERIA_LAST_POST =>
                __('SUBJECT-VALUES__LAST-POST-TITLE',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
        ];
    }

    /**
     * @throws MDOAuthException2
     */
    public function mdirector_newsletter_init() {
        $options = $this->get_plugin_options();
        $this->mdirector_checks();
        $this->set_current_languages();
        $this->set_translations_strings();
//        echo '<pre>';die( var_dump( $options ) );

        $tabs = [
            'settings' => __('CONFIGURATION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
        ];

        if ($this->is_plugin_configured()) {
            $tabs = array_merge($tabs, ['debug' => __('TESTS',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)]);
            $this->create_mdirector_lists();
            $this->create_mdirector_campaigns();
        } else {
            $tabs = array_merge(['welcome' => __('WELCOME',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)], $tabs);
        }

        $tabs = array_merge($tabs, ['help' => __('HELP',
            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)]);
        $current_tab = $this->get_current_tab();
        $this->print_notices();

        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<div class="mdirector-header"></div>';
        echo '<p class="mdirector-text">'
            . __('PLUGIN-PRESENTATION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>';
        echo '<h3 class="mdirector nav-tab-wrapper">';
            foreach ($tabs as $tab => $name) {
                $class = ($current_tab === $tab) ? ' nav-tab-active' : '';
                echo "<a class='nav-tab$class $tab' href='?page=mdirector-newsletter&tab=$tab'>$name</a>";
            }
        echo '</h3>';

        echo '<form method="post" action="' . admin_url('admin.php?page=mdirector-newsletter') . '" class="form-table form-md">';
        wp_nonce_field('mdirector-settings-page');

        switch ($current_tab) {
            case 'settings':
                $this->md_tab_content_settings();
                break;
            case 'help':
                $this->md_tab_content_help();
                break;
            case 'welcome':
                $this->md_tab_content_welcome();
                break;
            case 'debug':
                $this->md_tab_content_debug();
                break;
            default:
                ($this->is_plugin_configured())
                    ? $this->md_tab_content_settings()
                    : $this->md_tab_content_welcome();
                break;
        }
        echo '</form>';
    }

    public function md_tab_content_help() {
        echo '<h4>' . __('TAB-CONTENT-HELP__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
            <p>' . __('TAB-CONTENT-HELP__SUBTITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
            <ol style="max-width:90%">' . __('
                <li><p>'. __('PLUGIN-PRESENTATION__FIRST-STEP', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>
                <li><p>'. __('PLUGIN-PRESENTATION__SECOND-STEP', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                ', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);

        echo '<br><img src="' . MDIRECTOR_NEWSLETTER_PLUGIN_URL . '/assets/api.jpg"/></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-3', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-4', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-5', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-6', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-7', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-8', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-9', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-10', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-11', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-12', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-13', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-14', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p></li>';
        echo '<li><p>' . __('TAB-CONTENT-HELP__LIST-15', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .
            '<a href="http://stackoverflow.com/questions/12895706/when-does-wp-cron-php-run-in-wordpress" target="_blank">' .
                __('MORE-INFO', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</a></p></li>';
        echo '</ol>';
    }

    public function md_tab_content_welcome() {
        echo '
            <div class="mdirector-welcome-box"><a href="https://signup.mdirector.com?lang=es" target="_blank">
                <img src="'. MDIRECTOR_NEWSLETTER_PLUGIN_URL. '/assets/mdirector-welcome.png"/></a>' . '
                <h3>' . __('TAB-CONTENT-WELCOME__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h3>
                <p>' . __('TAB-CONTENT-WELCOME__TEXT-1', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                <p>' . __('TAB-CONTENT-WELCOME__TEXT-2', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                <p>' . __('TAB-CONTENT-WELCOME__TEXT-3', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                <p>' . __('TAB-CONTENT-WELCOME__TEXT-4', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>

                <br class="clear">
                <div class="overflow">
                    <p class="overflow">
                        <a class="btn-orange"
                            href="https://signup.mdirector.com?lang=es"
                            target="_blank">' . __('CREATE-ACCOUNT', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</a>
                        <a class="btn-blue" 
                            href="admin.php?page=mdirector-newsletter&tab=settings">' .
                            __('HAVE-ACCOUNT', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                        </a>
                    </p>
                </div>
                <br class="clear">
            </div>';
    }

    private function get_lists_ids($type, $suffix = '') {
        $options = $this->get_plugin_options();
        $targetList = 'mdirector' . self::SETTINGS_SEPARATOR .
            $type . self::SETTINGS_SEPARATOR;
        $prefix = $targetList .
            ($suffix ? $suffix . self::SETTINGS_SEPARATOR : '') . 'list' .
            self::SETTINGS_SEPARATOR;

        $lists = [];

        foreach ($this->current_languages as $language) {
            $lang = $language['code'];
            $lists[$lang] = [
                'translated_name' => $language['translated_name'],
                'value' => $options[$prefix . $lang]
            ];
        }

        return $lists;
    }

    private function get_last_date_send($frequency) {
        $options = $this->get_plugin_options();
        $current_lang = $this->Mdirector_utils->get_current_lang();

        if ($last_date = $options['mdirector_' . $frequency . '_sent_' . $current_lang]) {
            return date('d-m-Y, H:i', strtotime($last_date));
        }

        return self::NO_VALUE;
    }

    private function generate_template_options($lang = null) {
        $available_templates = $this->Mdirector_utils->get_user_templates();
        $current_template_selected = $this->Mdirector_utils->get_current_template($available_templates, $lang);
        $output = '';

        foreach ($available_templates as $template) {
            $base_template_name = basename($template);
            $template_name = ($base_template_name === Mdirector_Newsletter_Utils::DEFAULT_TEMPLATE)
                ? __(strtoupper($base_template_name), Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN)
                : $base_template_name;
            $selected = ($template_name === $current_template_selected) ? ' selected="selected"' : '';
            $output .= '<option value="' . $template_name . '" ' . $selected . '>' . $template_name . '</option>';
        }

        return $output;
    }

    private function build_options_for_days() {
        $options = $this->get_plugin_options();
        $options_days = '';

        foreach ($this->frequency_days as $key => $value) {
            $options_days .= '<option value="' . $key . '" '
                . (($options['mdirector_frequency_day'] === strval($key)) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        return $options_days;
    }

    private function build_subject_weekly_dynamic() {
        $options = $this->get_plugin_options();
        $options_subject_weekly_dynamic = '';

        foreach ($this->dynamic_subject_values as $key => $value) {
            $options_subject_weekly_dynamic .= '<option value="' . $key . '" '
                . (($options['mdirector_subject_dynamic_value_weekly'] === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        return $options_subject_weekly_dynamic;
    }

    private function build_subject_daily_dynamic() {
        $options = $this->get_plugin_options();
        $options_subject_daily_dynamic = '';

        foreach ($this->dynamic_subject_values as $key => $value) {
            $options_subject_daily_dynamic .= '<option value="' . $key . '" '
                . (($options['mdirector_subject_dynamic_value_daily']
                    === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        return $options_subject_daily_dynamic;
    }

    private function get_wpml_compatibility_template() {
        return '
                <div class="mdirector-settings-box">
                    <h4 class="margin-bottom-5">' .
                        __('WPLM-DETECTED', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                    </h4>
                    <div class="md_newsletter--wpml-logo-container overflow">
                        <img class="left"
                            alt="wpml logo"
                            src="'. MDIRECTOR_NEWSLETTER_PLUGIN_URL .'assets\wpml-logo-64.png">
                        <p class="left">' .
                            __('STEP-8__PLUGIN-AVAILABLE-TEXT-1', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                            <a href="https://wpml.org" target="_blank" title="WPML">WPML</a> ' .
                                __('STEP-8__PLUGIN-AVAILABLE-TEXT-2', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                <br><br>' .
                                __('STEP-8__PLUGIN-AVAILABLE-TEXT-4', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                        </p>
                    </div>
                </div>';

    }

    private function get_html_used_lists($lists, $default_lists, $type) {
        $output = '';

        foreach ($lists as $lang => $data) {
            $id = $data['value'];
            $lang_name = $data['translated_name'];
            $selectedId = ($id ? $id : $default_lists[$lang]['value']);
            $input_name = 'mdirector_' . $type . '_list_'. $lang;

            $output .= '
                <div class="md_newsletter--panel__row">
                    <label for="'. $input_name .'"><span>' . $lang_name . ':</span></label>
                    <input id="'. $input_name .'"
                        name="' . $input_name . '"
                        autocomlete="off"
                        type="text" value="' . $selectedId . '"/>
                    <small>' .
                __('CURRENT', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ': ' . $selectedId . ' (' .
                __('ORIGINAL', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ': ' . $default_lists[$lang]['value'] . ')' . '
                    </small>
                </div>';
        }

        return $output;
    }

    private function get_html_fixed_subjects($frequency) {
        $options = $this->get_plugin_options();
        $output = '';

        foreach($this->current_languages as $lang => $data) {
            $lang_name = $data['translated_name'];
            $input_name = 'mdirector_subject_' . $frequency . '_' . $lang;

            $output .= '
                <div class="md_newsletter--panel__row">
                    <label for="'. $input_name .'"><span>' . $lang_name . ':</span></label>                                                
                    <input '.
                ($options['mdirector_subject_type_' . $frequency] !==
                Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'readonly' : '') . '
                        id="' . $input_name . '"
                        name="' . $input_name . '"
                        class="field-selector"
                        placeholder="' . __('STEP-4-5__SUBJECT',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '"
                        type="text" value="'. $options[$input_name] . '"/>
                </div>';
        }

        return $output;
    }

    private function get_html_dynamic_subjects($frequency) {
        $options = $this->get_plugin_options();
        $output = '';

        foreach($this->current_languages as $lang => $data) {
            $lang_name = $data['translated_name'];
            $input_name = 'mdirector_subject_dynamic_prefix_' . $frequency .'_' . $lang;

            $output .= '
                <div class="md_newsletter--panel__row-alt">
                    <label for="'. $input_name .'"><span>' . $lang_name . ':</span></label>                                            
                    <input ' .
                ($options['mdirector_subject_type_' . $frequency] ===
                Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'readonly' : '') . '
                        id="' . $input_name . '"
                        name="' . $input_name . '"
                        type="text"
                        value="' . $options[$input_name] . '"
                        placeholder="' . __('STEP-4__DYNAMIC-PREFIX',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '"/>
                    <span class="help-block-alt">' .
                        __('STEP-4__DYNAMIC-PREFIX-EXAMPLE',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                    </span>   
                </div>';
        }

        return $output;
    }

    private function get_html_step_1() {
        $options = $this->get_plugin_options();

        return '
        <div class="mdirector-settings-box">
                <h4>' . __('STEP-1__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                . __('STEP-1__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>

                <div class="md_newsletter--wpml-templates">
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select" for="mdirector_api">' .
                            __('consumer-key', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                        </label>
                        <input id="mdirector_api"
                            name="mdirector_api"
                            type="text"
                            value="' . (isset($options['mdirector_api']) ? $options['mdirector_api'] : '') . '"/>
                        <span class="help-block"></span>
                    </div>
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select" for="mdirector_secret">' .
                            __('consumer-secret', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                        </label>
                        <input id="mdirector_secret"
                            name="mdirector_secret"
                            type="text"
                            value="' . (isset($options['mdirector_secret'])
                                ? $options['mdirector_secret']
                                : '') . '"/>
                            <span class="help-block"></span>
                    </div>
                </div>
                <br class="clear">
            </div>';
    }

    private function get_html_step_2() {
        $options = $this->get_plugin_options();
        $use_custom_lists = $options['mdirector_use_custom_lists'];

        $default_daily_lists = $this->get_lists_ids(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
        $daily_lists = $this->get_lists_ids(Mdirector_Newsletter_Utils::DAILY_FREQUENCY,
            ($use_custom_lists) ? 'custom' : null);
        $default_weekly_lists = $this->get_lists_ids(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);
        $weekly_lists = $this->get_lists_ids(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY,
            ($use_custom_lists) ? 'custom' : null);

        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-2__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'. __('STEP-2__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .'</p>
                <p>'. __('STEP-2__TEXT-1', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .'</p>
                <p class="notice-block">' . __('STEP-2__NOTICE-TEXT',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                <br class="clear">
                <div class="md_cat_checkbox">
                    <input type="checkbox"
                        autocomplete="off"
                        data-toggle="mdirector-custom-lists"
                        name="mdirector_use_custom_lists"
                        id="mdirector_use_custom_lists"
                        value="' . Mdirector_Newsletter_Utils:: SETTINGS_OPTION_ON . '" '
                    . (($use_custom_lists === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'checked' : '') . '>' .
                        __('STEP-2__PERSONALIZATED-LISTS', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                </div>
                <div id="mdirector-custom-lists" class="md_newsletter--wpml-templates" ' .
                    ($use_custom_lists !== Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON ? 'style="display:none;" ' : '') .'>
                    <div class="md_newsletter--panel__wrapper">
                        <label 
                            class="select">' .
                            __('STEP-2__DAILY-LIST', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                        </label>
                        <div class="text-right">';
                            $output .= $this->get_html_used_lists($daily_lists, $default_daily_lists,
                                'daily_custom');
                            $output .= '
                        </div>                            
                    </div>
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select">' . __('STEP-2__WEEKLY-LIST',
                                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</label>
                        <div class="text-right">';
                            $output .= $this->get_html_used_lists($weekly_lists, $default_weekly_lists,
                                'weekly_custom');
                            $output .= '
                        </div>
                    </div>
                </div>
            </div>';

        return $output;
    }

    private function get_html_step_3() {
        $options = $this->get_plugin_options();

        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-3__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>' . __('STEP-3__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>

                <div class="md_newsletter--wpml-templates">
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select" for="mdirector_from_name">' .
                            __('STEP-3__FROM-NAME', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':
                        </label>
                        <input id="mdirector_from_name" 
                            name="mdirector_from_name" 
                            type="text" 
                            value="'. $options['mdirector_from_name'] . '"/>
                    </div>
                </div>
            </div>';

        return $output;
    }

    private function get_html_step_4() {
        $options = $this->get_plugin_options();
        $options_days = $this->build_options_for_days();
        $options_subject_weekly_dynamic = $this->build_subject_weekly_dynamic();

        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-4__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>' . __('STEP-4__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                <br class="clear">
                <input type="checkbox"
                    name="mdirector_frequency_weekly"
                    id="mdirector_frequency_weekly"
                    autocomplete="off"
                    data-toggle="weekly_extra"
                    value="' . Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON . '" ' .
                        (($options['mdirector_frequency_weekly'] ===
                            Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'checked' : '') . '> ' .
                __('STEP-4__ACTIVATE-WEEKLY-DELIVERIES',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                <br class="clear">

                <div id="weekly_extra" 
                    class="weekly_extra_selector" 
                    style="' . (($options['mdirector_frequency_weekly'] ===
                        Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'display: block' : '') . '">
                    <fieldset>
                        <legend>' . __('STEP-4-5__CHOOSE-SUBJECT',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</legend>
                        <div class="choice-block">
                            <input ' . ($options['mdirector_subject_type_weekly'] ===
                                Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'checked' : '') . '
                                type="radio"
                                name="mdirector_subject_type_weekly"
                                class="dynamic-choice"
                                id="subject-type-fixed"
                                value="fixed"
                                autocomplete="off">
                            <label for="subject-type-fixed">' .
                                __('STEP-4-5__FIXED-SUBJECT', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .':
                            </label>
                            <br>

                            <div class="subject-block subset ' .
                                ($options['mdirector_subject_type_weekly'] !==
                                    Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'disabled' : '') .'">

                                <div class="md_newsletter--panel__wrapper">
                                    <div class="select text-right">';
                                        $output .= $this->get_html_fixed_subjects(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);
                                        $output .= '
                                            <span class="help-block" style="margin-left: 140px">' .
                                                __('STEP-4__SUBJECT-EXAMPLE',
                                                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' ' . get_bloginfo('name') . '
                                            </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="choice-block">
                            <input ' .
                                ($options['mdirector_subject_type_weekly'] ===
                                Mdirector_Newsletter_Utils::DYNAMIC_SUBJECT ? 'checked' : '') . '
                                type="radio"
                                name="mdirector_subject_type_weekly"
                                class="dynamic-choice"
                                id="subject-type-dynamic"
                                value="dynamic"
                                autocomplete="off">
                            <label for="subject-type-dynamic">' .
                                __('STEP-4-5__DYNAMIC-SUBJECT', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .'
                            <small>' . __('STEP-4-5__DYNAMIC-SUBJECT-DESCRIPTION',
                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</small>
                            </label>
                            <br>

                            <div class="subject-block subset md_newsletter--panel__wrapper ' .
                                ($options['mdirector_subject_type_weekly'] ===
                                    Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'disabled' : '') .'">
                                <div class="block-50">';
                                    $output .= $this->get_html_dynamic_subjects(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);
                                    $output .= '                                        
                                </div>
                                <div class="block-50">
                                    <select '. ($options['mdirector_subject_type_weekly'] ===
                                        Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'readonly' : '') . ' 
                                        name="mdirector_subject_dynamic_value_weekly">' .
                                        $options_subject_weekly_dynamic . '
                                    </select>
                                    <span class="help-block">' . __('STEP-4-5__CHOOSE-DYNAMIC-CONTENT',
                                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                    </span>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <br class="clear">

                    <label class="select">' . __('STEP-4__WEEKDAY',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</label>
                    <select name="mdirector_frequency_day">' . $options_days . '</select>
                    <br class="clear">

                    <label class="select">' . __('STEP-4-5__DELIVERY-TIME',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</label>
                    <input id="mdirector_hour_weekly"
                        name="mdirector_hour_weekly"
                        type="text"
                        class="timepicker"
                        readonly
                        value="'. $options['mdirector_hour_weekly'] . '"/>
                    <span class="help-block">' . __('STEP-4-5__DELIVERY-TIME-NOTE',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .
                        ' ' . date('H:i', current_time('timestamp', 0)) . '</span>                      
                </div>
            </div>';

        return $output;
    }

    private function get_html_step_5() {
        $options = $this->get_plugin_options();
        $options_subject_daily_dynamic = $this->build_subject_daily_dynamic();

        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-5__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>' . __('STEP-5__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
                <br class="clear">
                <input type="checkbox"
                    name="mdirector_frequency_daily"
                    class="dynamic-choice"
                    id="mdirector_frequency_daily"
                    autocomplete="off"
                    data-toggle="daily_extra"
                    value="' . Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON . '" ' .
                    (($options['mdirector_frequency_daily'] ===
                        Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'checked' : '') . '/> ' .
                    __('STEP-5__ACTIVATE-DAILY-DELIVERIES',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '

                <div id="daily_extra"
                    class="weekly_extra_selector"
                    style="' . (($options['mdirector_frequency_daily'] ===
                        Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'display: block' : '') . '">
                    <fieldset>
                        <legend>' . __('STEP-4-5__CHOOSE-SUBJECT',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</legend>
                        <div class="choice-block">
                            <input ' . ($options['mdirector_subject_type_daily'] ===
                                Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'checked' : '') . '
                                type="radio"
                                name="mdirector_subject_type_daily"
                                class="dynamic-choice"
                                id="subject-type-daily-fixed" value="fixed">
                            <label for="subject-type-daily-fixed">' . __('STEP-4-5__FIXED-SUBJECT',
                                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .':</label><br>

                            <div class="subject-block subset ' .
                                ($options['mdirector_subject_type_daily'] !==
                                Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'disabled' : '') .'">

                                <div class="md_newsletter--panel__wrapper">
                                    <div class="select text-right">';
                                        $output .= $this->get_html_fixed_subjects(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
                                        $output .= '
                                            <span class="help-block" style="margin-left: 140px">' .
                                                __('STEP-5__SUBJECT-EXAMPLE',
                                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' ' . get_bloginfo('name') . '
                                            </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="choice-block">
                            <input ' . ($options['mdirector_subject_type_daily'] === 'dynamic' ? 'checked' : '') . '
                                type="radio"
                                name="mdirector_subject_type_daily"
                                class="dynamic-choice"
                                id="subject-type-dynamic"
                                value="dynamic"
                                autocomplete="off">
                            <label for="subject-type-dynamic">' .
                                __('STEP-4-5__DYNAMIC-SUBJECT', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .'
                                <small>' . __('STEP-4-5__DYNAMIC-SUBJECT-DESCRIPTION',
                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</small>
                            </label>
                            <br>

                            <div class="subject-block subset ' .
                                ($options['mdirector_subject_type_daily'] ===
                                    Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'disabled' : '') .'">

                                <div class="md_newsletter--panel__wrapper">
                                    <div class="block-50">';
                                        $output .= $this->get_html_dynamic_subjects(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
                                        $output .= '                                        
                                    </div>
                                    <div class="block-50">
                                        <select '. ($options['mdirector_subject_type_daily'] ===
                                            Mdirector_Newsletter_Utils::FIXED_SUBJECT ? 'readonly' : '') . '
                                            name="mdirector_subject_dynamic_value_daily">' .
                                                $options_subject_daily_dynamic . '
                                            </select>
                                        <span class="help-block">' .
                                            __('STEP-4-5__CHOOSE-DYNAMIC-CONTENT',
                                                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <br class="clear">

                    <label class="select">' . __('STEP-4-5__DELIVERY-TIME',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</label>
                    <input id="mdirector_hour_daily"
                        name="mdirector_hour_daily"
                        type="text"
                        class="timepicker"
                        readonly
                        value="' . $options['mdirector_hour_daily'] . '"/>
                    <span class="help-block">'. __('STEP-4-5__DELIVERY-TIME-NOTE',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ' ' .
                        date('H:i', current_time('timestamp', 0)) . '</span>
                </div>
            </div>';

        return $output;
    }

    private function get_html_step_6() {
        $options = $this->get_plugin_options();

        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-6__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>' .
                    __('STEP-6__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <div class="categories_list" id="categories_list" style="'
                . ((isset($options['exclude_categories']) &&
                    $options['exclude_categories'] ===
                        Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'display: block'
                    : '') . '">'
                . $this->mdirector_get_categories($options['mdirector_exclude_cats']) . '</div>
                <br class="clear">
            </div>';

        return $output;
    }

    private function get_html_step_7() {
        $options = $this->get_plugin_options();

        $output =
            '<div class="mdirector-settings-box">
                <h4>' . __('STEP-7__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                . __('STEP-7__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <div class="md_newsletter--wpml-templates">
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select">' . __('STEP-7__ACCEPT-POLICY-TEXT',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</label>
                        <input id="mdirector_privacy_text"
                            name="mdirector_privacy_text"
                            type="text"
                            value="' . $options['mdirector_privacy_text'] . '"/>
                            <span class="help-block"></span>
                    </div>
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select">' . __('STEP-7__POLICY-URL',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</label>
                        <input id="mdirector_privacy_text"
                            name="mdirector_privacy_url"
                            type="text" value="' . $options['mdirector_privacy_url'] . '"/>
                            <span class="help-block"></span>
                    </div>
                </div>
            </div>';

        return $output;
    }

    private function get_html_step_8() {
        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-8__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                    . __('STEP-8__DESCRIPTION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <div class="md_newsletter--wpml-templates">';
                    // WPML Support
                    if (! $this->Mdirector_utils->is_wpml()) {
                        $output .= '
                            <div class="md_newsletter--panel__wrapper">
                                <label class="select">' . __('STEP-8__AVALAIBLE-TEMPLATES',
                                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</label>
                                <select class="md_template_select" 
                                    name="mdirector_template_general" 
                                    id="mdirector_template_general">' .
                                    $this->generate_template_options() . '
                                </select>                                
                            </div>';
                    } else {
                        $output .= '
                            <div class="overflow md_newsletter--wpml-container">
                                <p>' .
                                    __('STEP-8__PLUGIN-AVAILABLE-TEXT-3',
                                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                                </p>
                                <div class="clear md_newsletter--wpml-templates">';

                                    foreach ($this->current_languages as $language) {
                                        $lang = $language['code'];
                                        $lang_name = $language['translated_name'];
                                        $template_lang = 'mdirector_template_' . $lang;

                                        $output .= '
                                            <div class="overflow">
                                                <label class="md_newsletter--lang-name"><span>' . $lang_name . ':</span></label>
                                                <p class="md_newsletter--lang-template left">
                                                <select class="md_template_select"
                                                    name="' . $template_lang . '" id="' . $template_lang . '">' .
                                                    $this->generate_template_options($lang) . '
                                                </select>
                                                <!--<a 
                                                    href="' . plugins_url('../templates/mdirector/template.html', __FILE__ ) . '" 
                                                    target="_blank">Previsualizar plantilla</a>-->
                                                </p>
                                            </div>';
                                    }
                                    $output .= '
                                </div>
                            </div>';
                    }

                    $output .= '
                </div>
            </div>';

        return $output;
    }

    private function get_html_step_9() {
        $last_daily_send =
            $this->get_last_date_send(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
        $last_weekly_send =
            $this->get_last_date_send(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

        $output = '
            <div class="mdirector-settings-box">
                <h4>' . __('STEP-9__TITLE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                    . __('STEP-9__DESCRIPTION-1', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <p>'
                    . __('STEP-9__DESCRIPTION-2', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <br class="clear">
                <div class="overflow">
                    <label class="block-50">' . __('STEP-9__LAST-DAILY-SENDING',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</label>
                    <label class="block-50">' . $last_daily_send . '</label>
                    <br class="clear"><br class="clear">
                    <label class="block-50">' . __('STEP-9__LAST-WEEKLY-SENDING',
                        Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</label>
                    <label class="block-50">' . $last_weekly_send . '</label>
                    <br class="clear">
                    <div class="choice-block">
                        <button type="submit" class="margin-top-20 button button-submit"
                            name="cpt_submit_reset_now" value="reset_now">'
                        . __('STEP-9__RESTART-LAST-DATES',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</button>
                    </div>
                </div>
            </div>';

        return $output;
    }

    public function md_tab_content_settings() {
        $options = $this->get_plugin_options();
        update_option('mdirector-notice', 'true', true);

        if ($this->is_plugin_configured()) {
            if (empty($options['mdirector_subject_type_daily'])) {
                $options['mdirector_subject_type_daily'] =
                    Mdirector_Newsletter_Utils::DEFAULT_DAILY_MAIL_SUBJECT;
            }

            if (empty($options['mdirector_subject_type_weekly'])) {
                $options['mdirector_subject_type_weekly'] =
                    Mdirector_Newsletter_Utils::DEFAULT_WEEKLY_MAIL_SUBJECT;
            }
        }

        if ($this->Mdirector_utils->is_wpml()) {
            echo $this->get_wpml_compatibility_template();
        }

        echo $this->get_html_step_1();

        if ($this->is_plugin_configured()) {
            echo $this->get_html_step_2();
            echo $this->get_html_step_3();
            echo $this->get_html_step_4();
            echo $this->get_html_step_5();
            echo $this->get_html_step_6();
            echo $this->get_html_step_7();
            echo $this->get_html_step_8();
            echo $this->get_html_step_9();
        }

        echo '
                <p class="submit">
                    <input type="submit" class="button-primary"
                        tabindex="21" name="cpt_submit"
                        value="' . __('SAVE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '">';

        if ($this->is_plugin_configured()) {
            echo '<button type="submit"
                class="margin-left-10 button button-submit"
                tabindex="22"
                name="cpt_submit_test_now"
                value="test_now">' .
                    __('SEND-NOW', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</button>';

            if (!empty($options['mdirector_use_test_lists'])) {
                echo '<small class="margin-left-15 text-red"><strong>' .
                    __('NOTE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</strong> ' .
                    __('STEP-9__ANNOTATION', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .
                    '</small>';
            }
        }

        echo '</p>
              <input type="hidden" 
                name="mdirector-newsletter-submit" 
                value="' . Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON . '" />';
    }

    public function md_tab_content_debug() {
        $options = $this->get_plugin_options();
        $mdirector_daily_test_list = $this->get_lists_ids(
            Mdirector_Newsletter_Utils::DAILY_FREQUENCY,
            self::TEST_FLAG);
        $mdirector_weekly_test_list = $this->get_lists_ids(
            Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY,
            self::TEST_FLAG);
        $mdirector_use_test_lists = $options['mdirector_use_test_lists'];

        $daily_lists = $this->get_lists_ids(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
        $weekly_lists = $this->get_lists_ids(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

        if ($this->Mdirector_utils->is_wpml()) {
            echo $this->get_wpml_compatibility_template();
        }

        echo '<div class="mdirector-settings-box">
            <h4>' . __('TAB-CONTENT-DEBUG__TITLE',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</h4>
            <p class="notice-block">' . __('TAB-CONTENT-DEBUG__NOTICE',
                Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</p>
            <br class="clear">
            <div class="md_cat_checkbox">
                <input type="checkbox"
                    name="mdirector_use_test_lists"
                    id="mdirector_use_test_lists"
                    autocomplete="off"
                    data-toggle="mdirector-test-lists"
                    value="' . Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON . '" ' .
                ( ($mdirector_use_test_lists === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) ? 'checked' : '' ) . '>' .
                __('TAB-CONTENT-DEBUG__USING-TEST-LISTS', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
            </div>
            <div id="mdirector-test-lists" class="md_newsletter--wpml-templates" ' .
                ($mdirector_use_test_lists !==
                    Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON ? 'style="display:none;" ' : '') .'>
                <div class="md_newsletter--panel__wrapper">
                    <label class="select">' .
                        __('TAB-CONTENT-DEBUG__DAILY-TEST',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</label>
                    <div class="text-right">';
                        echo $this->get_html_used_lists($mdirector_daily_test_list, $daily_lists,
                            'daily_test');
                        echo '
                    </div>
                </div>
                <div class="md_newsletter--panel__wrapper">
                    <label class="select">' .
                        __('TAB-CONTENT-DEBUG__WEEKLY-TEST',
                            Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . ':</label>
                    <div class="text-right">';
                        echo $this->get_html_used_lists($mdirector_weekly_test_list, $weekly_lists,
                            'weekly_test');
                    echo '
                    </div>
                </div>
            </div>
        </div>';

        echo '
        <p class="submit">
            <input type="submit"
                class="button-primary"
                tabindex="21"
                name="cpt_submit"
                value="' . __('SAVE', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '">
            <button type="submit"
                class="button button-submit"
                tabindex="22"
                name="cpt_submit_test_now"
                value="test_now">' .
                    __('SEND-TEST-NOW', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '
            </button>
        </p>
        <input type="hidden" name="save-debug-submit" 
            value="' . Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON . '" />
        <input type="hidden" name="tab" value="debug" />';
    }

    /**
     * GET CATEGORIES
     *
     * @param null $selected
     * @return string
     */
    public function mdirector_get_categories($selected = null) {
        $selected = $selected ? unserialize($selected) : [];

        $cat_args = ['parent' => 0, 'hide_empty' => false];
        $parent_categories = get_categories($cat_args);

        $no_of_categories = count($parent_categories);
        $result = '';

        if ($no_of_categories > 0) {
            foreach ($parent_categories as $parent_category) {
                $result .= '<div class="md_cat_checkbox">
                    <input autocomplete="off"
                        name="mdirector_exclude_cats[]"
                        type="checkbox"
                        value="' . $parent_category->term_id . '" '
                            . ((in_array($parent_category->term_id, $selected)
                                ? 'checked' : '')) . '> ' . $parent_category->name .
                    '</div>';

                $parent_id = $parent_category->term_id;
                $terms = get_categories([
                    'child_of' => $parent_id,
                    'hide_empty' => false
                ]);

                foreach ($terms as $term) {
                    $extra_indent = ($term->parent != $parent_category->term_id)
                        ? 'grandchild' : '';
                    $result .= '
                        <div class="md_cat_checkbox child ' . $extra_indent . '">
                            <input name="mdirector_exclude_cats[]" type="checkbox" value="' .
                            $term->term_id . '" ' .
                                ((in_array($term->term_id, $selected) ? 'checked' : '')) . '> ' . $term->name .
                        '</div>';
                }
            }
        }

        return $result;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->mdirector_newsletter,
            MDIRECTOR_NEWSLETTER_PLUGIN_URL
            . 'admin/css/mdirector-newsletter-admin.css', [],
            $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_register_script('timepicker', MDIRECTOR_NEWSLETTER_PLUGIN_URL .
            'admin/js/timepicker.js', ['jquery']);
        wp_enqueue_script('timepicker');
        wp_register_script('mdirector-admin', MDIRECTOR_NEWSLETTER_PLUGIN_URL .
            'admin/js/mdirector-newsletter-admin.js', ['jquery']);
        wp_enqueue_script('mdirector-admin');
    }

    /**
     * CHECK WP VERSION
     */
    public function check_version() {
        if (version_compare(MDIRECTOR_CURRENT_WP_VERSION,
            MDIRECTOR_MIN_WP_VERSION, '<=')) {
            unset($_GET['activate']);

            echo '<div class="error md_newsletter--error-notice">'
                . __('CHECK-WP-VERSION',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) .
                    MDIRECTOR_MIN_WP_VERSION . '</div>';

            return false;
        }

        return true;
    }

    public function check_curl() {
        if (!(function_exists('curl_exec'))) {
            echo '<div class="error md_newsletter--error-notice">'
                . __('CHECK-CURL',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';

            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws MDOAuthException2
     */
    public function check_api() {
        $options = $this->get_plugin_options();

        if ($this->is_plugin_configured()) {
            $response = json_decode($response =
                $this->Mdirector_Newsletter_Api->callAPI(
                    $this->api_key,
                    $this->api_secret,
                    Mdirector_Newsletter_Utils::MDIRECTOR_API_LIST_ENDPOINT, 'GET'));
        } else {
            echo '<div class="error md_newsletter--error-notice">'
                . __('CHECK-API',
                    Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN) . '</div>';

            return false;
        }

        if (isset($response->code) && $response->code === '401') {
            $options['mdirector_api'] = '';
            $options['mdirector_secret'] = '';
            update_option('mdirector_settings', $options);

            echo '<div class="error md_newsletter--error-notice">';
            echo __('CHECK-API-ERROR', Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);
            echo '</div>';

            return false;
        }

        return true;
    }

    /**
     * @throws MDOAuthException2
     */
    public function mdirector_checks() {
        if ($this->check_version() && $this->check_curl() && $this->check_api()) {
            update_option('mdirector_active',
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON);
        } else {
            update_option('mdirector_active',
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_OFF);
        }
    }
}
