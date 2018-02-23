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
    const MDIRECTOR_MAIN_URL = 'http://www.mdirector.com';
    const MDIRECTOR_API_LIST_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_list';
    const MDIRECTOR_API_CAMPAIGN_ENDPOINT = self::MDIRECTOR_MAIN_URL . '/api_campaign';
    const MDIRECTOR_LANG_DOMAIN = 'mdirector-newsletter';
    const SETTINGS_OPTION_ON = 'yes';
    const SETTINGS_OPTION_OFF = 'no';
    const REQUEST_RESPONSE_SUCCESS = 'ok';
    const NO_VALUE = '---';
    const DEFAULT_SETTINGS_TAB = 'settings';
    const DAILY_ID = 'daily';
    const WEEKLY_ID = 'weekly';
    const STR_SEPARATOR = '-';
    const SETTINGS_SEPARATOR = '_';
    const MIDNIGHT = '23:59';

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

    private $plugin_settings;

    private $Mdirector_utils;

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

        $this->plugin_settings = get_option('mdirector_settings')
            ? get_option('mdirector_settings') : [];

        $this->api_key = isset($this->plugin_settings['mdirector_api'])
            ? $this->plugin_settings['mdirector_api'] : null;
        $this->api_secret = isset($this->plugin_settings['mdirector_secret'])
            ? $this->plugin_settings['mdirector_secret'] : null;

        $this->frequency_days = [
            '1' => __('Lunes', self::MDIRECTOR_LANG_DOMAIN),
            '2' => __('Martes', self::MDIRECTOR_LANG_DOMAIN),
            '3' => __('Miércoles', self::MDIRECTOR_LANG_DOMAIN),
            '4' => __('Jueves', self::MDIRECTOR_LANG_DOMAIN),
            '5' => __('Viernes', self::MDIRECTOR_LANG_DOMAIN),
            '6' => __('Sábado', self::MDIRECTOR_LANG_DOMAIN),
            '7' => __('Domingo', self::MDIRECTOR_LANG_DOMAIN)
        ];

        $this->dynamic_subject_values = [
            Mdirector_Newsletter_Utils::DYNAMIC_CRITERIA_FIRST_POST =>
                __('Título del primer post', self::MDIRECTOR_LANG_DOMAIN),
            Mdirector_Newsletter_Utils::DYNAMIC_CRITERIA_LAST_POST =>
                __('Título del último post', self::MDIRECTOR_LANG_DOMAIN)
        ];

        add_action('admin_menu', [$this, 'mdirector_newsletter_menu']);
    }

    private function is_wpml() {
        return function_exists('icl_object_id');
    }

    private function get_current_languages() {
        if ($this->is_wpml()) {
            return apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
        }

        $default_name = explode('_', get_locale())[0];
        $languages = [
            $default_name => [
                'code' => $default_name,
                'translated_name' => __('Lenguaje predeterminado', self::MDIRECTOR_LANG_DOMAIN)
            ]
        ];

        return $languages;
    }

    private function set_current_languages() {
        $this->current_languages = $this->get_current_languages();
    }

    public function print_notices() {
        if (count($this->plugin_notices)) {
            echo join(' ' , $this->plugin_notices);
        }
    }

    private function is_plugin_configured() {
        $this->api_key || ($this->api_key = $this->plugin_settings['mdirector_api']);
        $this->api_secret || ($this->api_secret = $this->plugin_settings['mdirector_secret']);

        return $this->api_key && $this->api_secret;
    }

    private function compose_list_name($list, $type) {

        $blog_name = sanitize_title_with_dashes(get_bloginfo('name'));
        $lang = key($list);

        $list_name = $blog_name . self::STR_SEPARATOR .
            ($type === self::DAILY_ID ? self::DAILY_ID : self::WEEKLY_ID) .
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
            self::MDIRECTOR_API_LIST_ENDPOINT, 'POST',
            ['listName' => $listName]));
    }

    private function restore_default_lists() {
        foreach ($this->current_languages as $lang => $data) {
            $this->plugin_settings['mdirector_daily_custom_list_' . $lang]
                = $this->plugin_settings['mdirector_daily_list_' . $lang];
            $this->plugin_settings['mdirector_weekly_custom_list_' . $lang]
                = $this->plugin_settings['mdirector_weekly_list_' . $lang];
        }

        $this->plugin_settings['mdirector_use_custom_lists'] = null;
        update_option('mdirector_settings', $this->plugin_settings);
    }

    /**
     * @param $lists
     * @param $array_list_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_daily_lists($lists, $array_list_names) {
        if ($this->plugin_settings['mdirector_use_custom_lists']) {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('La lista (o listas) diarias que has indicado no existen. Se utilizarán las originales.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</div>';

            $this->restore_default_lists();
        }

        foreach($lists as $lang => $id) {
            $daily_name = $this->compose_list_name([$lang => $id], self::DAILY_ID);

            if (in_array($daily_name, $array_list_names)) {
                $daily_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_daily_id = $this->create_list_via_API($daily_name);

            if ($mdirector_daily_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $this->plugin_settings['mdirector_daily_list_' . $lang] = $mdirector_daily_id->listId;
                $this->plugin_settings['mdirector_daily_list_name_' . $lang] = $daily_name;
                update_option('mdirector_settings', $this->plugin_settings);

                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--info-notice">'
                    . __('Se ha añadido una nueva lista diaria a tu cuenta de MDirector: ',
                        self::MDIRECTOR_LANG_DOMAIN) . $daily_name
                    . '</div>';
            } else {
                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--error-notice">'
                    . __('No se ha podido crear la lista diaria. Por favor, refresque la pantalla',
                        self::MDIRECTOR_LANG_DOMAIN) . '</div>';
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
        if ($this->plugin_settings['mdirector_use_custom_lists']) {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('La lista (o listas) semanales que has indicado no existen. Se utilizarán las originales.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</div>';

            $this->restore_default_lists();
        }

        foreach($lists as $lang => $id) {
            $weekly_name = $this->compose_list_name([$lang => $id], self::WEEKLY_ID);

            if (in_array($weekly_name, $array_list_names)) {
                $weekly_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_weekly_id = $this->create_list_via_API($weekly_name);

            if ($mdirector_weekly_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $this->plugin_settings['mdirector_weekly_list_' . $lang] = $mdirector_weekly_id->listId;
                $this->plugin_settings['mdirector_weekly_list_name_' . $lang] = $weekly_name;
                update_option('mdirector_settings', $this->plugin_settings);

                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--info-notice">'
                    . __('Se ha añadido una nueva lista semanal a tu cuenta de MDirector: ',
                        self::MDIRECTOR_LANG_DOMAIN) . $weekly_name
                    . '</div>';
            } else {
                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--error-notice">'
                    . __('No se ha podido crear la lista semanal. Por favor, refresque la pantalla',
                        self::MDIRECTOR_LANG_DOMAIN) . '</div>';
            }
        }
    }

    private function get_user_lists($type) {
        $lists = [];
        foreach($this->current_languages as $language) {
            $lang = $language['code'];
            $lists[$lang] = $this->plugin_settings[
                (($type === self::DAILY_ID)
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

        $mdirector_daily_lists = $this->get_user_lists(self::DAILY_ID);
        $mdirector_weekly_lists = $this->get_user_lists(self::WEEKLY_ID);

        $array_list_names = [];

        $list_of_lists =
            json_decode($this->Mdirector_Newsletter_Api->callAPI(
                $this->api_key,
                $this->api_secret,
                self::MDIRECTOR_API_LIST_ENDPOINT, 'GET'));

        if ($list_of_lists->response === self::REQUEST_RESPONSE_SUCCESS) {
            foreach ($list_of_lists->lists as $list) {
                if ($list_found = array_search($list->id, $mdirector_daily_lists)) {
                    unset($mdirector_daily_lists[$list_found]);
                    $this->plugin_settings['mdirector_daily_list_name_' . $list_found] = $list->name;
                }

                if ($list_found = array_search($list->id, $mdirector_weekly_lists)) {
                    unset($mdirector_weekly_lists[$list_found]);
                    $this->plugin_settings['mdirector_weekly_list_name_' . $list_found] = $list->name;
                }

                $array_list_names[] = $list->name;
            }

            update_option('mdirector_settings', $this->plugin_settings);
        }

        if (!empty($mdirector_daily_lists)) {
            $this->create_mdirector_daily_lists($mdirector_daily_lists, $array_list_names);
        }

        if (!empty($mdirector_weekly_lists)) {
            $this->create_mdirector_weekly_lists($mdirector_weekly_lists, $array_list_names);
        }
    }

    /**
     * @param $lists
     * @param $array_campaign_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_weekly_campaigns($lists, $array_campaign_names) {
        foreach($lists as $lang => $id) {
            $weekly_name = $this->compose_list_name([$lang => $id], self::WEEKLY_ID);

            if (in_array($weekly_name, $array_campaign_names)) {
                $weekly_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_weekly_id = $this->create_campaign_via_API($weekly_name);

            if ($mdirector_weekly_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $this->plugin_settings['mdirector_weekly_campaign_' . $lang] = $mdirector_weekly_id->data->camId;
                $this->plugin_settings['mdirector_weekly_campaign_name_' . $lang] = $weekly_name;
                update_option('mdirector_settings', $this->plugin_settings);

                $this->plugin_notices[] = '<div class="updated md_newsletter--info-notice">'
                    . __('Se ha añadido una nueva campaña semanal a tu cuenta de MDirector: ',
                        self::MDIRECTOR_LANG_DOMAIN) . $weekly_name . '</div>';
            } else {
                $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                    . __('No se ha podido crear la campaña semanal. Por favor, refresque la pantalla',
                        self::MDIRECTOR_LANG_DOMAIN) . '</div>';
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
            self::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'POST',
            ['name' => $campaignName]));
    }

    /**
     * @param $campaigns
     * @param $array_campaign_names
     *
     * @throws MDOAuthException2
     */
    private function create_mdirector_daily_campaigns($campaigns, $array_campaign_names) {
        foreach($campaigns as $lang => $id) {
            $daily_name = $this->compose_list_name([$lang => $id], self::DAILY_ID);

            if (in_array($daily_name, $array_campaign_names)) {
                $daily_name .= self::STR_SEPARATOR . time();
            }

            $mdirector_daily_id = $this->create_campaign_via_API($daily_name);

            if ($mdirector_daily_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                $this->plugin_settings['mdirector_daily_campaign_' . $lang] = $mdirector_daily_id->data->camId;
                $this->plugin_settings['mdirector_daily_campaign_name_' . $lang] = $daily_name;
                update_option('mdirector_settings', $this->plugin_settings);

                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--info-notice">'
                    . __('Se ha añadido una nueva campaña diaria a tu cuenta de MDirector: ',
                        self::MDIRECTOR_LANG_DOMAIN) . $daily_name . '</div>';
            } else {
                $this->plugin_notices[] =
                    '<div class="updated md_newsletter--error-notice">'
                    . __('No se ha podido crear la campaña diaria. Por favor, refresque la pantalla',
                        self::MDIRECTOR_LANG_DOMAIN) . '</div>';
            }
        }
    }

    private function get_user_campaigns($type) {
        $campaigns = [];

        foreach ($this->current_languages as $language) {
            $lang = $language['code'];
            $campaigns[$lang] = get_option(
                (($type === self::DAILY_ID)
                    ? 'mdirector_daily_campaign'
                    : 'mdirector_weekly_campaign')
                . '_' . $lang
            );
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

        $mdirector_daily_campaigns = $this->get_user_campaigns(self::DAILY_ID);
        $mdirector_weekly_campaigns = $this->get_user_campaigns(self::WEEKLY_ID);

        $array_campaigns_names = [];

        $list_of_campaigns =
            json_decode($this->Mdirector_Newsletter_Api->callAPI(
                $this->api_key,
                $this->api_secret,
                self::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'GET'));

        if ($list_of_campaigns->response === self::REQUEST_RESPONSE_SUCCESS) {
            foreach ($list_of_campaigns->data as $campaign) {
                if ($campaign_found = array_search($campaign->id, $mdirector_daily_campaigns)) {
                    unset($mdirector_daily_campaigns[$campaign_found]);
                    $this->plugin_settings['mdirector_daily_campaign_name_' . $campaign_found] = $campaign->campaignName;
                }

                if ($campaign_found = array_search($campaign->id, $mdirector_weekly_campaigns)) {
                    unset($mdirector_weekly_campaigns[$campaign_found]);
                    $this->plugin_settings['mdirector_weekly_campaign_name_' . $campaign_found] = $campaign->campaignName;
                }

                update_option('mdirector_settings', $this->plugin_settings);

                $array_campaigns_names[] = $campaign->campaignName;
            }
        }

        if (!empty($mdirector_daily_campaigns)) {
            $this->create_mdirector_daily_campaigns($mdirector_daily_campaigns, $array_campaigns_names);
        }

        if (!empty($mdirector_weekly_campaigns)) {
            $this->create_mdirector_weekly_campaigns($mdirector_weekly_campaigns, $array_campaigns_names);
        }
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

        if ($this->get_current_tab() === 'settings') {
            unset($data['mdirector-newsletter-submit']);

            $form_fields = $this->preg_grep_keys('/^mdirector_/', $data);
            $this->plugin_settings = array_merge(
                $this->plugin_settings,
                $form_fields);

            $this->plugin_settings['mdirector_use_custom_lists'] = $_POST['mdirector_use_custom_lists'];
            $this->plugin_settings['mdirector_frequency_weekly'] = $_POST['mdirector_frequency_weekly'];
            $this->plugin_settings['mdirector_frequency_daily'] = $_POST['mdirector_frequency_daily'];
            $this->plugin_settings['mdirector_exclude_cats'] = ((count($data['mdirector_exclude_cats']) > 0)
                ? serialize($data['mdirector_exclude_cats'])
                : []);

            update_option('mdirector_settings', $this->plugin_settings);
        }
    }

    private function preg_grep_keys($pattern, $input, $flags = 0) {
        return array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags)));
    }

    private function save_debug_settings($data) {
        $daily_fields = $this->preg_grep_keys('/^mdirector_daily/', $data);
        $weekly_fields = $this->preg_grep_keys('/^mdirector_weekly/', $data);

        $this->plugin_settings = array_merge(
            $this->plugin_settings,
            $daily_fields,
            $weekly_fields);

        $this->plugin_settings['mdirector_use_test_lists'] = $data['mdirector_use_test_lists'];
        update_option('mdirector_settings', $this->plugin_settings);
    }

    private function sending_test() {
        if ($this->plugin_settings['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON) {
            $this->plugin_settings['mdirector_hour_daily'] = self::MIDNIGHT;
            if ($this->Mdirector_utils->md_send_daily_mails($this->plugin_settings)) {
                $this->plugin_notices[] = '<div class="updated md_newsletter--info-notice">'
                    . __('Acabas de realizar un envío de tipo diario a la lista: <strong>', self::MDIRECTOR_LANG_DOMAIN)
                    . get_option('mdirector_daily_list_name') . '</strong></div>';
            } else {
                $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                    . __('No se ha podido realizar un envío de tipo diario.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                    . __('¿Quizá no tienes nuevas entradas en el blog?', self::MDIRECTOR_LANG_DOMAIN)
                    . '</div>';
            }
        } else {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('No se ha realizado un envío de tipo diario porque tienes la opción
                        <strong>Enviar mensajes diarios</strong> desactivada.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                . '</div>';
        }

        if ($this->plugin_settings['mdirector_frequency_weekly'] === self::SETTINGS_OPTION_ON) {
            $this->plugin_settings['mdirector_hour_weekly'] = self::MIDNIGHT;
            if ($this->Mdirector_utils->md_send_weekly_mails($this->plugin_settings) ) {
                $this->plugin_notices[] = '<div class="updated md_newsletter--info-notice">'
                    . __('Acabas de realizar un envío de tipo semanal a la lista: <strong>', self::MDIRECTOR_LANG_DOMAIN)
                    . get_option('mdirector_weekly_list_name') . '</strong></div>';
            } else {
                $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                    . __('No se ha podido realizar un envío de tipo semanal.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                    . __('¿Quizá no tienes nuevas entradas en el blog?', self::MDIRECTOR_LANG_DOMAIN)
                    . '</div>';
            }
        } else {
            $this->plugin_notices[] = '<div class="updated md_newsletter--error-notice">'
                . __('No se ha realizado un envío de tipo semanal porque tienes la opción
                        <strong>Enviar mensajes semanales</strong> desactivada.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                . '</div>';
        }
    }

    public function mdirector_newsletter_save() {
        if ($_POST['mdirector-newsletter-submit'] === 'Y') {
            $this->save_settings($_POST);
        } else if ($_POST['save-debug-submit'] === 'Y') {
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
                . __('Fechas de últimos envíos (diario y semanal) reiniciada.', self::MDIRECTOR_LANG_DOMAIN)
                . '</div>';
        }
    }

    /**
     * @throws MDOAuthException2
     */
    public function mdirector_newsletter_init() {
        // TODO: Remove these lines on release; only for testing purposes...
        // update_option('mdirector_settings', null); die('All data removed...');

        $this->mdirector_checks();
        $this->set_current_languages();

        $tabs = [
            'settings' => __('Configuración', self::MDIRECTOR_LANG_DOMAIN)
        ];

        if ($this->is_plugin_configured()) {
            $tabs = array_merge($tabs, ['debug' => __('Pruebas', self::MDIRECTOR_LANG_DOMAIN)]);
            $this->create_mdirector_lists();
            $this->create_mdirector_campaigns();
        } else {
            $tabs = array_merge(['welcome' => __('Bienvenida', self::MDIRECTOR_LANG_DOMAIN)], $tabs);
        }

        $tabs = array_merge($tabs, ['help' => __('Ayuda', self::MDIRECTOR_LANG_DOMAIN)]);
        $current_tab = $this->get_current_tab();
        $this->print_notices();

        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<div class="mdirector-header"></div>';
        echo '<p class="mdirector-text">'
            . __('Con el plugin oficial de <a href="' . self::MDIRECTOR_MAIN_URL . '" target="_blank">MDirector</a> podrás crear formularios de suscripción y programar newsletters diarias y/o semanales a los suscriptores de tu web, usando la tecnología de MDirector.', self::MDIRECTOR_LANG_DOMAIN) . '</p>';
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
                echo $this->md_tab_content_settings();
                break;
            case 'help':
                echo $this->md_tab_content_help();
                break;
            case 'welcome':
                echo $this->md_tab_content_welcome();
                break;
            case 'debug':
                echo $this->md_tab_content_debug();
                break;
            default:
                echo ($this->is_plugin_configured())
                    ? $this->md_tab_content_settings()
                    : $this->md_tab_content_welcome();
                break;
        }
        echo '</form>';
    }

    public function md_tab_content_help() {
        echo __('<h4>Pasos para configurar el plugin de Newsletter de MDirector</h4>
            <p>
            El plugin de MDirector conecta tu wordpress con MDirector, enviando los últimos posts que hayas escrito diaria o semanalmente y permite a tus usuarios suscribirse a la newsletter.
            </p>
            <ol style="max-width:90%">
                <li><p>Crea tu <a href="https://signup.mdirector.com/?lang=es" target="_blank">cuenta de MDirector</a>. Recuerda que con tu cuenta gratuíta puedes enviar hasta 5000 mensajes mensuales. Si ya tienes una puedes continuar con el paso 2.</p></li>
                <li><p>Inicia sesión en tu <a href="https://auth.mdirector.com" target="_blank">cuenta de Email Marketing en MDirector</a>, accede a Preferencias > Información API y activa el API si aún no está activado.</p>
                ', self::MDIRECTOR_LANG_DOMAIN);

        echo '<br><img src="' . MDIRECTOR_NEWSLETTER_PLUGIN_URL . '/assets/api.jpg"/></li>';

        echo __('<li><p>Copia los valores de consumer-key y consumer-secret</p></li>
                <li><p>Accede a la pestaña de configuración del plugin, introduce tu consumer-key y consumer-secret y pincha en guardar cambios.</p></li>
                <li><p>Una vez hechos los primeros 4 pasos, automáticamente se crean dos listas en tu cuenta de MDirector, una para aquellos usuarios que se suscriban a la lista semanal y otra para los que se suscriban a la lista diaria.</p></li>
                <li><p>A continuación elige el From name (Nombre que aparecerá en el mensaje en el campo <b>De:</b>) </p></li>
                <li><p>Por defecto, están desactivados los envíos desde las listas semanales y diarias. Actívalas para activar los envíos automáticos. <br>Rellena el formulario que aparecerá con el asunto del mensaje, la hora a la que quieres programar los envíos y el día de la semana en caso de la lista semanal.<br>NOTA: A pesar de que estén desactivados los envíos, los usuarios podrán suscribirse a las listas. De esta forma podrás crear campañas directamente en MDirector y enviarlas a los suscriptores de tu blog.</p></li>
                <li><p>A continuación puedes seleccionar también las categorías que quieres excluir de los envíos automáticos.</p></li>
                <li><p>Guarda al final de la página de configuración del plugin. Una vez hecho, automáticamente se crean dos listas en tu cuenta de MDirector, una para aquellos usuarios que se suscriban a la newsletter semanal y otra para los que se suscriban a la newsletter diaria.</p></li>
                <li><p>Si quieres realizar una prueba para ver el aspecto de tu campaña antes de enviarla utiliza la pestaña "Pruebas". Desde ahí, puedes indicar una lista alternativa a la que realizar el envío, por ejemplo, una donde figures tú y un par de amigos.</p></li>
                <li><p>Una vez que lo tengas todo configurado, puedes lanzar tu campaña de forma inmediata, o bien esperar al momento programado. Si no quieres esperar, solo pulsa "Enviar ahora mismo" o "Enviar prueba ahora mismo" desde su correspondiente pestaña.</p></li>
                <li><p>Ahora ya puedes colocar el formulario / widget en tu blog. Para ello, accede a Apariencia > Widgets en el panel de tu wordpress y arrastra a tu sidebar el widget de MDirector. Podrás configurar un título, una descripción / explicación para que aparezca encima de tu formulario y un enlace y checkbox para la política de privacidad o términos legales. ¡Ya está! tus usuarios pueden comenzar a suscribirse.</p></li>
                <li><p>Además del widget, puedes usar un shortcode para colocar el formulario de suscripción en cualquier página o post de tu blog. Para ello añade [mdirector_subscriptionbox] al contenido de cualquiera de tus páginas o posts.</p></li>
                <li><p>Puedes personalizar el aspecto de la plantilla de newsletter enviada a los usuarios modificando los ficheros de la carpeta /templates/ del directorio del plugin (Conocimientos de HTML / CSS requeridos).</p></li>
                <li><p>El plugin hace uso de WP Cron para realizar los envíos, el sistema de programación de tareas de Wordpress. Wp Cron funciona sólo cuando se producen visitas a la página, por lo que su ejecución en la hora planificada puede no ser precisa. <a href="http://stackoverflow.com/questions/12895706/when-does-wp-cron-php-run-in-wordpress" target="_blank">Más información aquí</a></p></li>
            </ol>
            ', self::MDIRECTOR_LANG_DOMAIN);
    }

    public function md_tab_content_welcome() {
        echo '
            <div class="mdirector-welcome-box"><a href="https://signup.mdirector.com?lang=es" target="_blank">
                <img src="'. MDIRECTOR_NEWSLETTER_PLUGIN_URL. '/assets/mdirector-welcome.png"/></a>' .
                __('<h3>Wordpress + MDirector = Más visitas en tu blog</h3>
                <p>Integra tu Wordpress con MDirector, la herramienta de envíos de email marketing y sms más avanzada y sencilla del mercado.</p>
                <p>El plugin de MDirector permite a tus visitantes suscribirse a tus publicaciones, asignándoles una lista en MDirector según deseen recibir los posts diaria o semanalmente, y ocupándonos de que se envíen automáticamente tus mensajes a través de MDirector sin que tengas que preocuparte. Además todos tus suscriptores serán también administrables desde MDirector por lo que podrás hacer envíos desde la plataforma.</p>
                <p>Configura ya tu plugin y en unos minutos podrás empezar a recibir suscriptores a tu blog.</p>
                <p>Para usarlo tienes que crear una cuenta en MDirector, recuerda que tienes 5.000 emails gratis al mes sólo por registrarte.</p>
                <br class="clear">
                <div class="overflow">
                    <p class="overflow">
                    <a class="btn-orange" href="https://signup.mdirector.com?lang=es" target="_blank">Crear mi cuenta en MDirector</a> 
                    <a class="btn-blue" href="admin.php?page=mdirector-newsletter&tab=settings">Ya tengo cuenta en MDirector</a>
                    </p>
                </div>
                <br class="clear">
            </div>', self::MDIRECTOR_LANG_DOMAIN);
    }

    private function get_lists_ids($type, $suffix = '') {
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
                'value' => $this->plugin_settings[$prefix . $lang]
            ];
        }

        return $lists;
    }

    private function get_last_date_send($frequency) {
        if ($last_date = get_option('mdirector_' . $frequency . '_sent')) {
            return date('d-m-Y, H:i', strtotime($last_date));
        }

        return self::NO_VALUE;
    }

    private function generate_template_options($lang = null) {
        $available_templates = $this->Mdirector_utils->get_user_templates();
        $current_template_selected = $this->Mdirector_utils->get_current_template($available_templates, $lang);
        $output = '';

        foreach ($available_templates as $template) {
            $template_name = basename($template);
            $selected = ($template_name === $current_template_selected) ? ' selected="selected"' : '';
            $output .= '<option value="' . $template_name . '" ' . $selected . '>' . $template_name . '</option>';
        }

        return $output;
    }

    private function build_options_for_days() {
        $options_days = '';
        foreach ($this->frequency_days as $key => $value) {
            $options_days .= '<option value="' . $key . '" '
                . (($this->plugin_settings['mdirector_frequency_day'] === strval($key)) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        return $options_days;
    }

    private function build_subject_weekly_dynamic() {
        $options_subject_weekly_dynamic = '';

        foreach ($this->dynamic_subject_values as $key => $value) {
            $options_subject_weekly_dynamic .= '<option value="' . $key . '" '
                . (($this->plugin_settings['mdirector_subject_dynamic_value_weekly'] === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        return $options_subject_weekly_dynamic;
    }

    private function build_subject_daily_dynamic() {
        $options_subject_daily_dynamic = '';

        foreach ($this->dynamic_subject_values as $key => $value) {
            $options_subject_daily_dynamic .= '<option value="' . $key . '" '
                . (($this->plugin_settings['mdirector_subject_dynamic_value_daily']
                    === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        return $options_subject_daily_dynamic;
    }

    private function get_wpml_compatibility_template() {
        return '
                <div class="mdirector-settings-box">
                    <h4 class="margin-bottom-5">' . __('¡WPML Detectado!', self::MDIRECTOR_LANG_DOMAIN) . '</h4>                        
                    <div class="md_newsletter--wpml-logo-container overflow">
                        <img class="left" 
                            alt="wpml logo" 
                            src="'. MDIRECTOR_NEWSLETTER_PLUGIN_URL .'assets\wpml-logo-64.png">                        
                        <p class="left">' . __('Vemos que tienes habilitado el plugin', self::MDIRECTOR_LANG_DOMAIN) . '
                            <a href="https://wpml.org" target="_blank" title="WPML">WPML</a> ' .
                __('para ofrecer tu web en varios idiomas.', self::MDIRECTOR_LANG_DOMAIN) . '<br><br>' .
                __('Este plugin es compatible con WPML, por lo que podrás configurar varios de sus aspectos para cada uno de los idiomas que tienes activos en tu sitio web.', self::MDIRECTOR_LANG_DOMAIN) . ' 
                        </p>
                    </div>
                </div>';

    }

    public function md_tab_content_settings() {
        update_option('mdirector-notice', 'true', true);

        if ($this->is_plugin_configured()) {
            $options_days = $this->build_options_for_days();
            $options_subject_weekly_dynamic = $this->build_subject_weekly_dynamic();
            $options_subject_daily_dynamic = $this->build_subject_daily_dynamic();

            $use_custom_lists =
                $this->plugin_settings['mdirector_use_custom_lists'];

            $default_daily_lists = $this->get_lists_ids(self::DAILY_ID);
            $daily_lists = $this->get_lists_ids(self::DAILY_ID,
                ($use_custom_lists) ? 'custom' : null);
            $default_weekly_lists = $this->get_lists_ids(self::WEEKLY_ID);
            $weekly_lists = $this->get_lists_ids(self::WEEKLY_ID,
                ($use_custom_lists) ? 'custom' : null);

            $last_daily_send =
                $this->get_last_date_send(Mdirector_Newsletter_Utils::DAILY_FREQUENCY);
            $last_weekly_send =
                $this->get_last_date_send(Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY);

            if (empty($this->plugin_settings['mdirector_subject_type_daily'])) {
                $this->plugin_settings['mdirector_subject_type_daily'] =
                    Mdirector_Newsletter_Utils::DEFAULT_SUBJECT_TYPE_DAILY;
            }

            if (empty($this->plugin_settings['mdirector_subject_type_weekly'])) {
                $this->plugin_settings['mdirector_subject_type_weekly'] =
                    Mdirector_Newsletter_Utils::DEFAULT_SUBJECT_TYPE_WEEKLY;
            }
        }

        if ($this->is_wpml()) {
            echo $this->get_wpml_compatibility_template();
        }

        echo '
            <!-- STEP 1 -->
            <!-- ------------------------------------- -->
            <div class="mdirector-settings-box">                
                <h4>' . __('1. Datos de conexión API MDirector', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                . __('Por favor, configura tus datos de conexión con la API. Puedes encontrarlos en las preferencias de tu cuenta de MDirector, pestaña <b>Información API</b>.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</p>
                
                <div class="md_newsletter--wpml-templates">
                    <div class="md_newsletter--panel__wrapper">
                        <label class="select" for="mdirector_api">' . __('consumer-key', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                        <input id="mdirector_api" 
                            name="mdirector_api" 
                            type="text" 
                            value="' . (isset($this->plugin_settings['mdirector_api']) ? $this->plugin_settings['mdirector_api'] : '') . '"/> 
                        <span class="help-block"></span>
                    </div>
                    <div class="md_newsletter--panel__wrapper">                
                        <label class="select" for="mdirector_secret">' . __('consumer-secret', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                        <input id="mdirector_secret" 
                            name="mdirector_secret" 
                            type="text" 
                            value="' . (isset($this->plugin_settings['mdirector_secret']) ? $this->plugin_settings['mdirector_secret'] : '') . '"/> 
                            <span class="help-block"></span>
                    </div>
                </div>
                <br class="clear">
            </div>';

        if ($this->is_plugin_configured()) {
            echo '
                <!-- STEP 2 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('2. Listas de contactos personalizadas', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>Este plugin crea de forma automática las listas de contactos necesarias en tu cuenta de MDirector para enviar las campañas diarias o semanales.</p>';

                    if ( $this->is_wpml() ) {
                        echo '<h5>Detectado WPML</h5>';
                    }
                    echo '<p>Si lo prefieres, puedes indicar a continuación alguna de tus listas ya existentes para utilizarlas en su lugar.</p>
                    <p class="notice-block">' . __('Las listas diaria y semanal que utilices deben existir y ser distintas.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <div class="md_cat_checkbox">
                        <input type="checkbox" 
                            autocomplete="off"
                            data-toggle="mdirector-custom-lists"
                            name="mdirector_use_custom_lists" 
                            id="mdirector_use_custom_lists" 
                            value="' . self:: SETTINGS_OPTION_ON . '" '
                        . (($use_custom_lists === self::SETTINGS_OPTION_ON) ? 'checked' : '') . '>
                        ' . __('Utilizar listas personalizadas', self::MDIRECTOR_LANG_DOMAIN) . '
                    </div>
                    <div id="mdirector-custom-lists" class="md_newsletter--wpml-templates"' .
                        ($use_custom_lists !== self::SETTINGS_OPTION_ON ? ' style="display:none;"' : '') .'>
                        <div class="md_newsletter--panel__wrapper">
                            <label class="select md_newsletter--panel__left">' . __('Lista(s) para envíos diarios', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                            <div class="md_newsletter--panel__right">';
                                foreach ($daily_lists as $lang => $data) {
                                    $id = $data['value'];
                                    $lang_name = $data['translated_name'];
                                    $selectedId = ($id ? $id : $default_daily_lists[$lang]['value']);
                                    $input_name = 'mdirector_daily_custom_list_'. $lang;

                                    echo '<div class="md_newsletter--panel__row">';
                                        echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                        echo '<input id="'. $input_name .'" 
                                            name="' . $input_name . '"  
                                            type="text" value="' . $selectedId . '"/>';
                                        echo '<small>' .
                                            __('Actualmente', self::MDIRECTOR_LANG_DOMAIN) . ': ' . $selectedId . ' (' .
                                            __('original', self::MDIRECTOR_LANG_DOMAIN) . ': ' . $default_daily_lists[$lang]['value'] . ')' .
                                            '</small>';
                                    echo '</div>';
                                }
                            echo '</div>';
                            echo '
                        </div>
                        <div class="md_newsletter--panel__wrapper">
                            <label class="select md_newsletter--panel__left">' . __('Lista(s) para envíos semanales', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                            <div class="md_newsletter--panel__right">';
                                foreach ($weekly_lists as $lang => $data) {
                                    $id = $data['value'];
                                    $lang_name = $data['translated_name'];
                                    $selectedId = ($id ? $id : $default_weekly_lists[$lang]['value']);
                                    $input_name = 'mdirector_weekly_custom_list_'. $lang;

                                    echo '<div class="md_newsletter--panel__row">';
                                        echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                        echo '<input id="'. $input_name .'" 
                                            name="'. $input_name .'" 
                                            type="text" value="' . $selectedId . '"/>';
                                    echo '<small>' .
                                        __('Actualmente', self::MDIRECTOR_LANG_DOMAIN) . ': ' . $selectedId . ' (' .
                                        __('original', self::MDIRECTOR_LANG_DOMAIN) . ': ' .
                                            $default_weekly_lists[$lang]['value'] . ')' .
                                        '</small>';
                                    echo '</div>';
                                }
                            echo '</div>                                          
                        </div>
                    </div>
                </div>

                <!-- STEP 3 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('3. Campo From', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>' . __('Configura el nombre que aparecerá en el campo <b>De:</b> de los correos que se envíen automáticamente desde el plugin.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    
                    <div class="md_newsletter--wpml-templates">
                        <div class="md_newsletter--panel__wrapper">
                            <label class="select" for="mdirector_from_name">' . __('Nombre del emisor', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                            <input id="mdirector_from_name" name="mdirector_from_name" type="text" value="'. $this->plugin_settings['mdirector_from_name'] . '"/>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 4 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('4. Enviar mensajes semanales', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>' . __('Activa los envíos de newsletters para los usuarios para los usuarios suscritos a la información semanal. Si esta opción está activada, todas las semanas se enviará un email automático a los usuarios suscritos a la lista, con un resumen de los posts publicados cada la semana.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <input type="checkbox" 
                        name="mdirector_frequency_weekly" 
                        id="mdirector_frequency_weekly"
                        autocomplete="off" 
                        data-toggle="weekly_extra"
                        value="' . self::SETTINGS_OPTION_ON . '" ' .
                            (($this->plugin_settings['mdirector_frequency_weekly'] === self::SETTINGS_OPTION_ON) ? 'checked' : '') . '> ' .
                            __('Activa los envíos semanales', self::MDIRECTOR_LANG_DOMAIN) . '
                    <br class="clear">

                    <div id="weekly_extra" class="weekly_extra_selector" style="' . (($this->plugin_settings['mdirector_frequency_weekly'] === self::SETTINGS_OPTION_ON) ? 'display: block' : '') . '">
                        <fieldset>
                            <legend>' . __('Escoja el tipo de asunto para sus correos:', self::MDIRECTOR_LANG_DOMAIN) . '</legend>
                            <div class="choice-block">
                                <input ' . ($this->plugin_settings['mdirector_subject_type_weekly'] === 'fixed' ? 'checked' : '') . ' 
                                    type="radio" 
                                    name="mdirector_subject_type_weekly" 
                                    class="dynamic-choice" 
                                    id="subject-type-fixed" 
                                    value="fixed"
                                    autocomplete="off">
                                <label for="subject-type-fixed">' .
                                    __('Asunto fijo', self::MDIRECTOR_LANG_DOMAIN) .':
                                </label>
                                <br>

                                <div class="subject-block subset ' .
                                    ($this->plugin_settings['mdirector_subject_type_weekly'] !== 'fixed' ? 'disabled' : '') .'">

                                    <div class="md_newsletter--panel__wrapper">
                                        <div class="select md_newsletter--panel__left">';

                                            foreach($this->current_languages as $lang => $data) {
                                                $lang_name = $data['translated_name'];
                                                $input_name = 'mdirector_subject_weekly_' . $lang;

                                                echo '<div class="md_newsletter--panel__row">';
                                                    echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                                    echo '
                                                    <input '. ($this->plugin_settings['mdirector_subject_type_weekly'] !== 'fixed' ? 'readonly' : '') . '
                                                        id="' . $input_name . '" 
                                                        name="' . $input_name . '"
                                                        class="field-selector"                                         
                                                        placeholder="' . __('Asunto', self::MDIRECTOR_LANG_DOMAIN) . '"
                                                        type="text" value="'. $this->plugin_settings[$input_name] . '"/>';
                                                echo '</div>';
                                            }
                                            echo '                                            
                                            <span class="help-block" style="margin-left: 140px">' .
                                                __("Por ejemplo: 'Newsletter semanal de", self::MDIRECTOR_LANG_DOMAIN) . ' ' . get_bloginfo('name') . '</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="choice-block">
                                <input ' . ($this->plugin_settings['mdirector_subject_type_weekly'] === 'dynamic' ? 'checked' : '') . '
                                    type="radio" 
                                    name="mdirector_subject_type_weekly" 
                                    class="dynamic-choice" 
                                    id="subject-type-dynamic" 
                                    value="dynamic"
                                    autocomplete="off">                                
                                <label for="subject-type-dynamic">' .
                                    __('Asunto dinámico', self::MDIRECTOR_LANG_DOMAIN) .'
                                    <small>' . __('(se forma automáticamente a partir de los siguientes campos)', self::MDIRECTOR_LANG_DOMAIN) . ':</small>
                                </label>
                                <br>
                                
                                <div class="subject-block subset md_newsletter--panel__wrapper '. ($this->plugin_settings['mdirector_subject_type_weekly'] === 'fixed' ? 'disabled' : '') .'">
                                    <div class="block-50">';
                                        foreach($this->current_languages as $lang => $data) {
                                            $lang_name = $data['translated_name'];
                                            $input_name = 'mdirector_subject_dynamic_prefix_weekly_' . $lang;

                                            echo '<div class="md_newsletter--panel__row-alt">';
                                                echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                                echo '                                            
                                                <input ' .
                                                    ($this->plugin_settings['mdirector_subject_type_weekly'] === 'fixed' ? 'readonly' : '') . '
                                                    id="' . $input_name . '"
                                                    name="' . $input_name . '" 
                                                    type="text" 
                                                    value="' . $this->plugin_settings[$input_name] . '" 
                                                    placeholder="' . __('Prefijo', self::MDIRECTOR_LANG_DOMAIN) . '"/>';
                                            echo '</div>';
                                        }
                                        echo '                                        
                                        <span class="help-block-alt">' .
                                            __("Por ejemplo: 'Esta semana destacamos...'", self::MDIRECTOR_LANG_DOMAIN) . '
                                        </span>
                                    </div>
                                    <div class="block-50">
                                        <select '. ($this->plugin_settings['mdirector_subject_type_weekly'] === 'fixed' ? 'readonly' : '') .
                                            ' name="mdirector_subject_dynamic_value_weekly">' . $options_subject_weekly_dynamic . '</select>
                                        <span class="help-block">' . __('Selecciona el contenido dinámico', self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <br class="clear">

                        <label class="select">' . __('Día de la semana', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <select name="mdirector_frequency_day">' . $options_days . '</select>
                        <br class="clear">

                        <label class="select">' . __('Hora de envío', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <input id="mdirector_hour_weekly" 
                            name="mdirector_hour_weekly" 
                            type="text" 
                            class="timepicker" 
                            readonly 
                            value="'. $this->plugin_settings['mdirector_hour_weekly'] . '"/>
                        <span class="help-block">' . __('NOTA: la hora actual en el servidor es ', self::MDIRECTOR_LANG_DOMAIN) .
                                ' ' . date('H:i', current_time('timestamp', 0)) . '</span>
                        <br class="clear">
                        <p>' . __('Los usuarios suscritos a la lista semanal se almacenan en tu cuenta de MDirector, en la lista', self::MDIRECTOR_LANG_DOMAIN) .
                                ' <span class="md_newsletter--text--decorator">' .
                                    get_option("mdirector_weekly_list_name") . '</span>.</p>
                        <p>' . __('Los envíos semanales se guardan en la campaña', self::MDIRECTOR_LANG_DOMAIN) .
                                ' <span class="md_newsletter--text--decorator">' .
                                get_option("mdirector_weekly_campaign_name") . '</span>.</p>
                    </div>

                </div>
                
                <!-- STEP 5 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('5. Enviar mensajes diarios', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>' .
                        __('Activa los envíos de newsletters para los usuarios suscritos a la información diaria. Si esta opción está activada, todos los días se enviará un email automático a los usuarios que hayan elegido recibir emails diarios, con un resumen de los posts publicados cada día.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <input type="checkbox" 
                        name="mdirector_frequency_daily" 
                        class="dynamic-choice" 
                        id="mdirector_frequency_daily"
                        autocomplete="off" 
                        data-toggle="daily_extra"
                        value="' . self::SETTINGS_OPTION_ON . '" ' .
                        (($this->plugin_settings['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON) ? 'checked' : '') . '/> ' .
                        __('Activa los envíos diarios', self::MDIRECTOR_LANG_DOMAIN) . '

                    <div id="daily_extra" 
                        class="weekly_extra_selector" 
                        style="' . (($this->plugin_settings['mdirector_frequency_daily'] === self::SETTINGS_OPTION_ON) ? 'display: block' : '') . '">
                        <fieldset>
                            <legend>' . __('Escoja el tipo de asunto para sus correos:', self::MDIRECTOR_LANG_DOMAIN) . '</legend>
                            <div class="choice-block">
                                <input ' . ($this->plugin_settings['mdirector_subject_type_daily'] === 'fixed' ? 'checked' : '') . '
                                    type="radio" 
                                    name="mdirector_subject_type_daily" 
                                    class="dynamic-choice" 
                                    id="subject-type-daily-fixed" value="fixed">
                                <label for="subject-type-daily-fixed">' . __('Asunto fijo', self::MDIRECTOR_LANG_DOMAIN) .':</label><br>

                                <div class="subject-block subset ' .
                                    ($this->plugin_settings['mdirector_subject_type_daily'] !== 'fixed' ? 'disabled' : '') .'">
                                    
                                    <div class="md_newsletter--panel__wrapper">
                                        <div class="select md_newsletter--panel__left">';

                                            foreach($this->current_languages as $lang => $data) {
                                                $lang_name = $data['translated_name'];
                                                $input_name = 'mdirector_subject_daily_' . $lang;

                                                echo '<div class="md_newsletter--panel__row">';
                                                    echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                                    echo '                                            
                                                    <input '. ($this->plugin_settings['mdirector_subject_type_daily'] !== 'fixed' ? 'readonly' : '') . '
                                                        id="' . $input_name . '" 
                                                        name="' . $input_name . '"
                                                        class="field-selector"
                                                        placeholder="' . __('Asunto', self::MDIRECTOR_LANG_DOMAIN) . '"
                                                        type="text" value="'. $this->plugin_settings[$input_name] . '"/>';
                                                echo '</div>';
                                            }
                                    echo '                                            
                                            <span class="help-block" style="margin-left: 140px">' .
                                                __("Por ejemplo: 'Newsletter diaria de", self::MDIRECTOR_LANG_DOMAIN) . ' ' . get_bloginfo('name') . '
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="choice-block">
                                <input ' . ($this->plugin_settings['mdirector_subject_type_daily'] === 'dynamic' ? 'checked' : '') . '
                                    type="radio" 
                                    name="mdirector_subject_type_daily" 
                                    class="dynamic-choice" 
                                    id="subject-type-dynamic" 
                                    value="dynamic"
                                    autocomplete="off">
                                <label for="subject-type-dynamic">' .
                                    __('Asunto dinámico', self::MDIRECTOR_LANG_DOMAIN) .'
                                    <small>' . __('(se forma automáticamente a partir de los siguientes campos)', self::MDIRECTOR_LANG_DOMAIN) . ':</small>
                                </label>
                                <br>

                                <div class="subject-block subset ' .
                                    ($this->plugin_settings['mdirector_subject_type_daily'] === 'fixed' ? 'disabled' : '') .'">
                                    
                                    <div class="md_newsletter--panel__wrapper">
                                        <div class="block-50">';

                                            foreach($this->current_languages as $lang => $data) {
                                                $lang_name = $data['translated_name'];
                                                $input_name = 'mdirector_subject_dynamic_prefix_daily_' . $lang;
                                                echo '<div class="md_newsletter--panel__row-alt">';
                                                    echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                                    echo '
                                                        <input '. ($this->plugin_settings['mdirector_subject_type_daily'] === 'fixed' ? 'readonly' : '') . '
                                                            id="' . $input_name . '"
                                                            name="' . $input_name . '" 
                                                            type="text" 
                                                            value="' . $this->plugin_settings[$input_name] . '" 
                                                            placeholder="'. __('Prefijo', self::MDIRECTOR_LANG_DOMAIN) . '"/>';
                                                echo '</div>';
                                            }

                                            echo '                                            
                                            <span class="help-block-alt">' .
                                                __("Por ejemplo: 'Hoy destacamos...'", self::MDIRECTOR_LANG_DOMAIN) . '
                                            </span>
                                        </div>
                                        <div class="block-50">
                                            <select '. ($this->plugin_settings['mdirector_subject_type_daily'] === 'fixed' ? 'readonly' : '') . '
                                                name="mdirector_subject_dynamic_value_daily">' . $options_subject_daily_dynamic . '</select>
                                            <span class="help-block">' .
                                                __('Selecciona el contenido dinámico', self::MDIRECTOR_LANG_DOMAIN) . '
                                            </span>
                                        </div>
                                    </div>
                                </div>  
                            </div>      
                        </fieldset>

                        <br class="clear">

                        <label class="select">' . __('Hora de envío', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <input id="mdirector_hour_daily" 
                            name="mdirector_hour_daily" 
                            type="text" 
                            class="timepicker" 
                            readonly 
                            value="' . $this->plugin_settings['mdirector_hour_daily'] . '"/>
                        <span class="help-block">'. __('NOTA: la hora actual en el servidor es ', self::MDIRECTOR_LANG_DOMAIN)
                                . ' ' . date('H:i', current_time('timestamp', 0)) . '</span>
                        <br class="clear">
                        <p>' . __('Los usuarios suscritos a la lista diaria se almacenan en tu cuenta de MDirector, en la lista', self::MDIRECTOR_LANG_DOMAIN) .
                                ' <span class="md_newsletter--text--decorator">' . get_option("mdirector_daily_list_name") . '</span>.</p>
                        <p>' . __('Los envíos diarios se guardan en la campaña', self::MDIRECTOR_LANG_DOMAIN) .
                                ' <span class="md_newsletter--text--decorator">' . get_option("mdirector_daily_campaign_name") . '</span>.</p>
                    </div>

                </div>
                
                <!-- STEP 6 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('6. Excluir categorías de posts en los envíos', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>'
                    . __('Si por cualquier motivo no te interesa que los posts de una o varias categorías no sean enviados en los mensajes automáticos. Elige la/s categoría/s que deseas excluir de los envíos',
                        self::MDIRECTOR_LANG_DOMAIN) . '
                    </p>
                    <div class="categories_list" id="categories_list" style="'
                    . (($this->plugin_settings['exclude_categories'] === self::SETTINGS_OPTION_ON) ? 'display: block'
                        : '') . '">'
                    . $this->mdirector_get_categories($this->plugin_settings['mdirector_exclude_cats']) . '</div>
                    <br class="clear">
                </div>
                
                <!-- STEP 7 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('7. Configuración de widget / shortcode', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>'
                    . __('Configura el texto de aceptación y URL de la política de privacidad', self::MDIRECTOR_LANG_DOMAIN) . '
                    </p>
                    
                    <div class="md_newsletter--wpml-templates">
                        <div class="md_newsletter--panel__wrapper">
                            <label class="select">' . __('Texto de aceptación de política', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                            <input id="mdirector_privacy_text" 
                                name="mdirector_privacy_text" 
                                type="text" 
                                value="' . $this->plugin_settings['mdirector_privacy_text'] . '"/> 
                                <span class="help-block"></span>
                        </div>   
                        <div class="md_newsletter--panel__wrapper">
                            <label class="select">' . __('URL de la política', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                            <input id="mdirector_privacy_text" 
                                name="mdirector_privacy_url" 
                                type="text" value="' . $this->plugin_settings['mdirector_privacy_url'] . '"/> 
                                <span class="help-block"></span>
                        </div>
                    </div>
                </div>
                                
                <!-- STEP 8 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('8. Seleccionar plantilla', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>'
                    . __('Si tienes varias plantillas HTML, puedes escoger cuál utilizar para generar tu Newsletter.', self::MDIRECTOR_LANG_DOMAIN) . '
                    </p>                
                    <div class="md_newsletter--wpml-templates">';
                        // WPML Support
                        if (! $this->is_wpml()) {
                            echo '
                                <div class="md_newsletter--panel__wrapper">
                                    <label class="select">' . __('Plantillas disponibles:', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                                    <select class="md_template_select" name="mdirector_template_general" id="mdirector_template_general">' .
                                        $this->generate_template_options() .
                                    '</select>
                                </div>';
                        } else {
                            echo '                        
                                <div class="overflow md_newsletter--wpml-container">                                                            
                                    <p>' .
                                        __('Puedes configurar una plantilla diferente para cada uno, pudiendo así mejorar de forma signifactica la personalización de tus envíos:', self::MDIRECTOR_LANG_DOMAIN) . '
                                    </p>';

                            echo '<div class="clear md_newsletter--wpml-templates">';

                            foreach ($this->current_languages as $language) {
                                $lang = $language['code'];
                                $lang_name = $language['translated_name'];
                                $template_lang = 'mdirector_template_' . $lang;

                                echo '
                                    <div class="overflow">
                                        <label class="md_newsletter--lang-name">' . $lang_name . ':</label>
                                        <p class="md_newsletter--lang-template left">
                                        <select class="md_template_select" 
                                            name="' . $template_lang . '" id="' . $template_lang . '">' .
                                            $this->generate_template_options($lang) . '
                                        </select>   
                                        </p>
                                    </div>';
                            }

                            echo '</div>'; // .md_newsletter--wpml-templates
                            echo '</div>'; // .md_newsletter--wpml-container
                        }

                    echo '                        
                    </div>
                </div>

                <!-- STEP 9 -->
                <!-- ------------------------------------- -->
                <div class="mdirector-settings-box">
                    <h4>' . __('9. Reiniciar fecha de último envío diario y semanal', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>'
                    . __('Ten en cuenta que para evitar envíos duplicados, el sistema comprueba que no se haya realizado un envío diario en las
                    últimas 24 horas, o uno semanal en los últimos 7 días.', self::MDIRECTOR_LANG_DOMAIN) . '
                    </p>
                    <p>'
                    . __('Es por esto que si ya has realizado un envío y deseas volver a programar otro diario para hoy o uno semanal durante
                    la próxima semana, debes reiniciar la fecha del último envío.', self::MDIRECTOR_LANG_DOMAIN) . '
                    </p>
                    <br class="clear">
                    <div class="overflow">
                        <label class="block-50">' . __('Fecha último envío diario:', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <label class="block-50">' . $last_daily_send . '</label>
                        <br class="clear"><br class="clear">
                        <label class="block-50">' . __('Fecha último envío semanal:', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <label class="block-50">' . $last_weekly_send . '</label>
                        <br class="clear">
                        <div class="choice-block">
                            <button type="submit" class="margin-top-20 button button-submit" 
                                name="cpt_submit_reset_now" value="reset_now">'
                            . __('Reiniciar últimas fechas de envío', self::MDIRECTOR_LANG_DOMAIN) . '</button>
                        </div>
                    </div>
                </div>
                ';
        }
        echo '
                <p class="submit">
                    <input type="submit" class="button-primary" 
                        tabindex="21" name="cpt_submit" 
                        value="' . __('Guardar cambios', self::MDIRECTOR_LANG_DOMAIN) . '">';

        if ($this->is_plugin_configured()) {
            echo '<button type="submit" 
                class="margin-left-10 button button-submit" 
                tabindex="22" 
                name="cpt_submit_test_now" 
                value="test_now">' .
                    __('Enviar ahora mismo', self::MDIRECTOR_LANG_DOMAIN) . '</button>';

            if (!empty(get_option('mdirector_use_test_lists'))) {
                echo '<small class="margin-left-15 text-red"><strong>' .
                    __('NOTA:', self::MDIRECTOR_LANG_DOMAIN) . '</strong> ' .
                    __('Recuerda que estás utilizando listas de pruebas para tu envío!', self::MDIRECTOR_LANG_DOMAIN) .
                    '</small>';
            }
        }

        echo '</p>
              <input type="hidden" name="mdirector-newsletter-submit" value="Y" />';
    }

    public function md_tab_content_debug() {
        $mdirector_daily_test_list = $this->get_lists_ids(self::DAILY_ID, 'test');
        $mdirector_weekly_test_list = $this->get_lists_ids(self::WEEKLY_ID, 'test');
        $mdirector_use_test_lists = $this->plugin_settings['mdirector_use_test_lists'];

        $daily_lists = $this->get_lists_ids(self::DAILY_ID);
        $weekly_lists = $this->get_lists_ids(self::WEEKLY_ID);

        if ($this->is_wpml()) {
            echo $this->get_wpml_compatibility_template();
        }

        echo '<div class="mdirector-settings-box">
            <h4>' . __('1. Listas de test', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
            <p class="notice-block">' . __('Las listas de prueba que indiques a continuación deben existir y ser distintas.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
            <br class="clear">
            <div class="md_cat_checkbox">
                <input type="checkbox" 
                    name="mdirector_use_test_lists" 
                    id="mdirector_use_test_lists" 
                    autocomplete="off"
                    data-toggle="mdirector-test-lists"
                    value="' . self::SETTINGS_OPTION_ON . '" ' .
                ( ($mdirector_use_test_lists === self::SETTINGS_OPTION_ON) ? 'checked' : '' ) . '>' .
                __('Utilizar listas de prueba', self::MDIRECTOR_LANG_DOMAIN) . '
            </div>
            <div id="mdirector-test-lists" class="md_newsletter--wpml-templates"' .
                ($mdirector_use_test_lists !== self::SETTINGS_OPTION_ON ? ' style="display:none;"' : '') .'>
                <div class="md_newsletter--panel__wrapper">            
                    <label class="select md_newsletter--panel__left">' . __('Lista de Test Diaria') . ':</label>
                    <div class="md_newsletter--panel__right">';
                        foreach ($mdirector_daily_test_list as $lang => $data) {
                            $id = $data['value'];
                            $lang_name = $data['translated_name'];
                            $input_name = 'mdirector_daily_test_list_' . $lang;

                            echo '<div class="md_newsletter--panel__row">';
                                echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                echo '<input id="' . $input_name . '" 
                                    name="' . $input_name . '" type="text" 
                                    value="' . $id . '"/>    
                                <small>' .
                                    __('Lista real', self::MDIRECTOR_LANG_DOMAIN) . ': ' . $daily_lists[$lang]['value'] .
                                '</small>
                            </div>';
                        }
                echo '
                    </div>   
                </div>
                <div class="md_newsletter--panel__wrapper">
                    <label class="select md_newsletter--panel__left">' . __('Lista de Test Semanal', self::MDIRECTOR_LANG_DOMAIN) . ':</label>
                    <div class="md_newsletter--panel__right">';
                        foreach ($mdirector_weekly_test_list as $lang => $data) {
                            $id = $data['value'];
                            $lang_name = $data['translated_name'];
                            $input_name = 'mdirector_weekly_test_list_' . $lang;

                            // TODO: This block can be extracted...
                            echo '<div class="md_newsletter--panel__row">';
                                echo '<label for="'. $input_name .'">' . $lang_name . ':</label>';
                                echo '<input id="' . $input_name . '" 
                                    name="' . $input_name . '" type="text" 
                                    value="' . $id . '"/>    
                                <small>' .
                                    __('Lista real', self::MDIRECTOR_LANG_DOMAIN) . ': ' . $weekly_lists[$lang]['value'] .
                                '</small>
                            </div>';
                        }
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
                value="' . __('Guardar cambios', self::MDIRECTOR_LANG_DOMAIN) . '">
            <button type="submit" 
                class="button button-submit" 
                tabindex="22" 
                name="cpt_submit_test_now" 
                value="test_now">' .
                    __('Enviar prueba ahora mismo', self::MDIRECTOR_LANG_DOMAIN) . ' 
            </button>
        </p>
        <input type="hidden" name="save-debug-submit" value="Y" />
        <input type="hidden" name="tab" value="debug" />
        ';
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
                . __('Por favor actualiza tu versión de Wordpress para usar el plugin de MDirector. La mínima versión compatible es: ',
                    self::MDIRECTOR_LANG_DOMAIN) . MDIRECTOR_MIN_WP_VERSION . '</div>';

            return false;
        }

        return true;
    }

    public function check_curl() {
        if (!(function_exists('curl_exec'))) {
            echo '<div class="error md_newsletter--error-notice">'
                . __('El plugin de MDirector hace uso de php-curl, por favor instala dicha librería para continuar.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</div>';

            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws MDOAuthException2
     */
    public function check_api() {
        if ($this->is_plugin_configured()) {
            $response = json_decode($response =
                $this->Mdirector_Newsletter_Api->callAPI(
                    $this->api_key,
                    $this->api_secret,
                    self::MDIRECTOR_API_LIST_ENDPOINT, 'GET'));
        } else {
            echo '<div class="error md_newsletter--error-notice">'
                . __('Para comenzar a usar el plugin de MDirector Newsletter configura los datos de conexión a la API de MDirector',
                    self::MDIRECTOR_LANG_DOMAIN) . '</div>';

            return false;
        }

        if ($response->code === '401') {
            $this->plugin_settings['mdirector_api'] = '';
            $this->plugin_settings['mdirector_secret'] = '';
            update_option('mdirector_settings', $this->plugin_settings);

            echo '<div class="error md_newsletter--error-notice">';
            echo __('Hay problemas de conexión con MDirector, por favor vuelve a introducir los datos de conexión API', self::MDIRECTOR_LANG_DOMAIN);
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
            update_option('mdirector_active', self::SETTINGS_OPTION_ON);
        } else {
            update_option('mdirector_active', self::SETTINGS_OPTION_OFF);
        }
    }
}
