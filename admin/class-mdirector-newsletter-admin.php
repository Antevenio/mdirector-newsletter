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
    const REQUEST_RESPONSE_SUCCESS = 'ok';

    protected $frequency_types;
    protected $frequency_days;
    protected $dynamic_subject_values;
    protected $plugin_notices = [];

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $mdirector_newsletter The ID of this plugin.
     */
    private $mdirector_newsletter;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    private $Mdirector_utils;

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

        $this->frequency_types = [
            Mdirector_Newsletter_Utils::DAILY_FREQUENCY => __('Diaria', self::MDIRECTOR_LANG_DOMAIN),
            Mdirector_Newsletter_Utils::WEEKLY_FREQUENCY => __('Semanal', self::MDIRECTOR_LANG_DOMAIN)
        ];
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

    function print_notices() {
        if (count($this->plugin_notices)) {
            foreach($this->plugin_notices as $notice) {
                echo $notice;
            }
        }
    }

    /**
     * Create Lists on MDirector Account
     */
    function create_mdirector_lists() {
        $settings = get_option('mdirector_settings');
        $mdirector_active = get_option('mdirector_active');

        if ($mdirector_active === self::SETTINGS_OPTION_ON) {
            $key = $settings['api'];
            $secret = $settings['secret'];

            $mdirector_weekly_list = get_option('mdirector_weekly_list');
            $mdirector_daily_list = get_option('mdirector_daily_list');

            $list_creation_time = time(); //used in case there is another list with the same name we are creating

            $weekly_name = sanitize_title_with_dashes(get_bloginfo('name')) . '-weekly';
            $daily_name = sanitize_title_with_dashes(get_bloginfo('name')) . '-daily';

            $weekly_exists = false;
            $daily_exists = false;

            $array_list_names = [];
            $list_of_lists =
                json_decode(Mdirector_Newsletter_Api::callAPI($key, $secret,
                    self::MDIRECTOR_API_LIST_ENDPOINT, 'GET'));

            if ($list_of_lists->response === self::REQUEST_RESPONSE_SUCCESS) {
                foreach ($list_of_lists->lists as $list) {
                    switch ($list->id) {
                        case $mdirector_weekly_list:
                            $weekly_exists = true;
                            update_option('mdirector_weekly_list_name', $list->name);
                            break;

                        case $mdirector_daily_list:
                            $daily_exists = true;
                            update_option('mdirector_daily_list_name', $list->name);
                            break;
                    }
                    $array_list_names[] = $list->name;
                }
            }

            if (in_array($daily_name, $array_list_names)) {
                $daily_name = $daily_name . '-' . $list_creation_time;
            }

            if (in_array($weekly_name, $array_list_names)) {
                $weekly_name = $weekly_name . '-' . $list_creation_time;
            }

            if (!$mdirector_weekly_list || !$weekly_exists) {
                if ($settings['mdirector_use_custom_lists']) {
                    $this->plugin_notices[] = '<div class="updated md-error">'
                        . __('La lista semanal que has indicado no existe. Se volverá a fijar la original.',
                            self::MDIRECTOR_LANG_DOMAIN) . '</div>';

                        $this->updateDeliveryLists(null, get_option('mdirector_weekly_list_lck'));
                        $settings['mdirector_weekly_custom_list'] = get_option('mdirector_weekly_list');
                        update_option('mdirector_settings', $settings);
                } else {
                    //create weekly list
                    $mdirector_weekly_id =
                        json_decode(Mdirector_Newsletter_Api::callAPI($key, $secret,
                            self::MDIRECTOR_API_LIST_ENDPOINT, 'POST',
                            ['listName' => $weekly_name]));

                    if ($mdirector_weekly_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                        update_option('mdirector_weekly_list', $mdirector_weekly_id->listId);
                        update_option('mdirector_weekly_list_name', $weekly_name);

                        $this->plugin_notices[] = '<div class="updated md-notice">'
                            . __('Se ha añadido una nueva lista semanal a tu cuenta de MDirector: ',
                                self::MDIRECTOR_LANG_DOMAIN) . $weekly_name . '</div>';
                    } else {
                        $this->plugin_notices[] = '<div class="updated md-error">'
                            . __('No se ha podido crear la lista semanal. Por favor, refresque la pantalla',
                                self::MDIRECTOR_LANG_DOMAIN) . '</div>';
                    }
                }
            }

            if (!$mdirector_daily_list || !$daily_exists) {
                if ($settings['mdirector_use_custom_lists']) {
                    $this->plugin_notices[] = '<div class="updated md-error">'
                        . __('La lista diaria que has indicado no existe. Se volverá a fijar la original.',
                            self::MDIRECTOR_LANG_DOMAIN) . '</div>';

                    $this->updateDeliveryLists(get_option('mdirector_daily_list_lck'), null);
                    $settings['mdirector_daily_custom_list'] = get_option('mdirector_daily_list');

                    update_option('mdirector_settings', $settings);
                } else {
                    $mdirector_daily_id =
                        json_decode(Mdirector_Newsletter_Api::callAPI($key, $secret,
                            self::MDIRECTOR_API_LIST_ENDPOINT, 'POST',
                            ['listName' => $daily_name]));

                    if ($mdirector_daily_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                        update_option('mdirector_daily_list', $mdirector_daily_id->listId);
                        update_option('mdirector_daily_list_name', $daily_name);

                        $this->plugin_notices[] = '<div class="updated md-notice">'
                            . __('Se ha añadido una nueva lista diaria a tu cuenta de MDirector: ',
                                self::MDIRECTOR_LANG_DOMAIN) . $daily_name . '</div>';
                    } else {
                        $this->plugin_notices[] = '<div class="updated md-error">'
                            . __('No se ha podido crear la lista diaria. Por favor, refresque la pantalla',
                                self::MDIRECTOR_LANG_DOMAIN) . '</div>';
                    }
                }
            }
        }
    }

    /**
     * Create campaigns on MDirector Account
     */
    function create_mdirector_campaigns() {
        $settings = get_option('mdirector_settings');
        $mdirector_active = get_option('mdirector_active');

        if ($mdirector_active === self::SETTINGS_OPTION_ON) {
            $key = $settings['api'];
            $secret = $settings['secret'];

            $mdirector_weekly_campaign = get_option('mdirector_weekly_campaign');
            $mdirector_daily_campaign = get_option('mdirector_daily_campaign');

            $campaign_creation_time = time(); // Used in case exists another list with the same name
            $weekly_name = sanitize_title_with_dashes(get_bloginfo('name')) . '-weekly';
            $daily_name = sanitize_title_with_dashes(get_bloginfo('name')) . '-daily';

            $array_campaig_names = [];
            $list_of_campagins = json_decode(Mdirector_Newsletter_Api::callAPI(
                $key, $secret, self::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'GET')
            );

            $weekly_exists = false;
            $daily_exists = false;

            if ($list_of_campagins->response === self::REQUEST_RESPONSE_SUCCESS) {
                foreach ($list_of_campagins->data as $campaign) {
                    switch ($campaign->id) {
                        case $mdirector_weekly_campaign:
                            $weekly_exists = true;
                            update_option('mdirector_weekly_campaign_name', $campaign->campaignName);
                            break;

                        case $mdirector_daily_campaign:
                            $daily_exists = true;
                            update_option('mdirector_daily_campaign_name', $campaign->campaignName);
                            break;
                    }

                    $array_campaig_names[] = $campaign->campaignName;
                }
            }

            if (in_array($daily_name, $array_campaig_names)) {
                $daily_name = $daily_name . '-' . $campaign_creation_time;
            }

            if (in_array($weekly_name, $array_campaig_names)) {
                $weekly_name = $weekly_name . '-' . $campaign_creation_time;
            }

            if (!$mdirector_weekly_campaign || !$weekly_exists) {
                //create weekly list
                $mdirector_weekly_id = json_decode(Mdirector_Newsletter_Api::callAPI(
                    $key, $secret, self::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'POST',
                    ['name' => $weekly_name])
                );

                if ($mdirector_weekly_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                    update_option('mdirector_weekly_campaign', $mdirector_weekly_id->data->camId);
                    update_option('mdirector_weekly_campaign_name', $weekly_name);

                    $this->plugin_notices[] = '<div class="updated md-notice">'
                        . __('Se ha añadido una nueva campaña semanal a tu cuenta de MDirector: ',
                            self::MDIRECTOR_LANG_DOMAIN) . $weekly_name . '</div>';
                } else {
                    $this->plugin_notices[] = '<div class="updated md-error">'
                        . __('No se ha podido crear la campaña semanal. Por favor, refresque la pantalla',
                            self::MDIRECTOR_LANG_DOMAIN) . '</div>';
                }
            }

            if (!$mdirector_daily_campaign || !$daily_exists) {
                $mdirector_daily_id =
                    json_decode(Mdirector_Newsletter_Api::callAPI($key, $secret,
                        self::MDIRECTOR_API_CAMPAIGN_ENDPOINT, 'POST',
                        ['name' => $daily_name]));

                if ($mdirector_daily_id->response === self::REQUEST_RESPONSE_SUCCESS) {
                    update_option('mdirector_daily_campaign', $mdirector_daily_id->data->camId);
                    update_option('mdirector_daily_campaign_name', $daily_name);

                    $this->plugin_notices[] = '<div class="updated md-notice">'
                        . __('Se ha añadido una nueva campaña diaria a tu cuenta de MDirector: ',
                            self::MDIRECTOR_LANG_DOMAIN) . $daily_name . '</div>';
                } else {
                    $this->plugin_notices[] = '<div class="updated md-error">'
                        . __('No se ha podido crear la campaña diaria. Por favor, refresque la pantalla',
                            self::MDIRECTOR_LANG_DOMAIN) . '</div>';
                }
            }
        }
    }

    /**
     * Adds the plugin admin menu.
     *
     * @since    1.0.0
     */
    function mdirector_newsletter_menu() {
        $menu = add_menu_page('MDirector', 'MDirector', 'manage_options',
            'mdirector-newsletter', [$this, 'mdirector_newsletter_init'],
            MDIRECTOR_NEWSLETTER_PLUGIN_URL . '/assets/icon_mdirector.png');

        add_action("load-{$menu}", [$this, 'mdirector_newsletter_save']);
    }

    private function get_current_tab() {
        if (isset($_GET['tab'])) {
            return $_GET['tab'];
        } elseif (isset($_POST['tab'])) {
            return $_POST['tab'];
        }

        return 'settings';
    }

    /**
     * SAVE SETTINGS
     */
    function mdirector_newsletter_save() {
        $settings = get_option('mdirector_settings');

        if ($_POST['mdirector-newsletter-submit'] === 'Y') {
            if ($this->get_current_tab() === 'settings' ) {
                $settings['api'] = $_POST['api'];
                $settings['secret'] = $_POST['secret'];
                $settings['frequency_weekly'] = $_POST['frequency_weekly'];
                $settings['frequency_daily'] = $_POST['frequency_daily'];
                $settings['subject_type_weekly'] = $_POST['subject_type_weekly'];
                $settings['subject_type_daily'] = $_POST['subject_type_daily'];
                $settings['subject_weekly'] = $_POST['subject_weekly'];
                $settings['subject_daily'] = $_POST['subject_daily'];
                $settings['from_name'] = $_POST['from_name'];
                $settings['frequency_day'] = $_POST['frequency_day'];
                $settings['subject_dynamic_prefix_weekly'] = $_POST['subject_dynamic_prefix_weekly'];
                $settings['subject_dynamic_value_weekly'] = $_POST['subject_dynamic_value_weekly'];
                $settings['subject_dynamic_prefix_daily'] = $_POST['subject_dynamic_prefix_daily'];
                $settings['subject_dynamic_value_daily'] = $_POST['subject_dynamic_value_daily'];
                $settings['mdirector_use_custom_lists'] = $_POST['mdirector_use_custom_lists'];
                $settings['mdirector_daily_custom_list'] = $_POST['mdirector_daily_custom_list'];
                $settings['mdirector_weekly_custom_list'] = $_POST['mdirector_weekly_custom_list'];
                $settings['hour_weekly'] = $_POST['hour_weekly'];
                $settings['hour_daily'] = $_POST['hour_daily'];
                $settings['exclude_categories'] = $_POST['exclude_categories'];
                $settings['md_privacy_text'] = $_POST['md_privacy_text'];
                $settings['md_privacy_url'] = $_POST['md_privacy_url'];
                $settings['exclude_cats'] = ((count($_POST['exclude_cats']) > 0)
                    ? serialize($_POST['exclude_cats'])
                    : []);

                update_option('mdirector_settings', $settings);
            }
        } else if ($_POST['mdirector-newsletter-debug-submit'] === 'Y') {
            $dailyTest = $_POST['mdirector_daily_test_list'];
            $weeklyTest = $_POST['mdirector_weekly_test_list'];
            update_option('mdirector_daily_test_list', $dailyTest);
            update_option('mdirector_weekly_test_list', $weeklyTest);

            $already_testing = get_option('mdirector_use_test_lists');

            if ($_POST['mdirector_use_test_lists'] && !$already_testing) {
                $this->updateDeliveryLists($dailyTest, $weeklyTest);
            } else if ($_POST['mdirector_use_test_lists'] && $already_testing) {
                $this->updateTestLists($dailyTest, $weeklyTest);
            } else {
                $this->disableTestLists();
            }

            update_option('mdirector_use_test_lists', $_POST['mdirector_use_test_lists']);
        }

        if ($_POST['mdirector_use_custom_lists']) {
            $customDailyList = $_POST['mdirector_daily_custom_list'];
            $customWeeklyList = $_POST['mdirector_weekly_custom_list'];

            $this->updateDeliveryLists($customDailyList, $customWeeklyList);
            $this->create_mdirector_lists();
        }

        // Sending the campaigns inmediately
        if (isset($_POST['cpt_submit_test_now'])) {
            if ($settings['frequency_daily'] === self::SETTINGS_OPTION_ON) {
                $settings['hour_daily'] = '23:59';
                if ($this->Mdirector_utils->md_send_daily_mails($settings)) {
                    $this->plugin_notices[] = '<div class="updated md-notice">'
                        . __('Acabas de realizar un envío de tipo diario a la lista: <strong>', self::MDIRECTOR_LANG_DOMAIN)
                        . get_option('mdirector_daily_list_name') . '</strong></div>';
                } else {
                    $this->plugin_notices[] = '<div class="updated md-error">'
                        . __('No se ha podido realizar un envío de tipo diario.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                        . __('¿Quizá no tienes nuevas entradas en el blog?', self::MDIRECTOR_LANG_DOMAIN)
                        . '</div>';
                }
            } else {
                $this->plugin_notices[] = '<div class="updated md-error">'
                    . __('No se ha realizado un envío de tipo diario porque tienes la opción
                        <strong>Enviar mensajes diarios</strong> desactivada.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                    . '</div>';
            }

            if ($settings['frequency_weekly'] === self::SETTINGS_OPTION_ON) {
                $settings['hour_weekly'] = '23:59';
                if ($this->Mdirector_utils->md_send_weekly_mails($settings) ) {
                    $this->plugin_notices[] = '<div class="updated md-notice">'
                        . __('Acabas de realizar un envío de tipo semanal a la lista: <strong>', self::MDIRECTOR_LANG_DOMAIN)
                        . get_option('mdirector_weekly_list_name') . '</strong></div>';
                } else {
                    $this->plugin_notices[] = '<div class="updated md-error">'
                        . __('No se ha podido realizar un envío de tipo semanal.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                        . __('¿Quizá no tienes nuevas entradas en el blog?', self::MDIRECTOR_LANG_DOMAIN)
                        . '</div>';
                }
            } else {
                $this->plugin_notices[] = '<div class="updated md-error">'
                    . __('No se ha realizado un envío de tipo semanal porque tienes la opción
                        <strong>Enviar mensajes semanales</strong> desactivada.', self::MDIRECTOR_LANG_DOMAIN) . ' '
                    . '</div>';
            }
        }
    }

    function updateDeliveryLists($newDailyList, $newWeeklyList) {
        $daily = get_option('mdirector_daily_list');
        $weekly = get_option('mdirector_weekly_list');

        if (!empty($newDailyList)) {
            update_option('mdirector_daily_list_lck', $daily);
            update_option('mdirector_daily_list', $newDailyList);
        }

        if (!empty($newWeeklyList)) {
            update_option('mdirector_weekly_list_lck', $weekly);
            update_option('mdirector_weekly_list', $newWeeklyList);
        }
    }

    function updateTestLists($dailyTest, $weeklyTest) {
        update_option('mdirector_daily_list', $dailyTest);
        update_option('mdirector_weekly_list', $weeklyTest);
    }

    function disableTestLists() {
        $daily_lck = get_option('mdirector_daily_list_lck');
        $weekly_lck = get_option('mdirector_weekly_list_lck');
        update_option('mdirector_daily_list', $daily_lck);
        update_option('mdirector_weekly_list', $weekly_lck);
        update_option('mdirector_daily_list_lck', '');
        update_option('mdirector_weekly_list_lck', '');
    }

    function mdirector_newsletter_init() {
        $this->mdirector_checks();

        $settings = get_option('mdirector_settings');
        $tabs = [
            'settings' => __('Configuración', self::MDIRECTOR_LANG_DOMAIN)
        ];

        if ($settings['api'] && $settings['secret']) {
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
                echo ($settings['api'])
                    ? $this->md_tab_content_settings()
                    : $this->md_tab_content_welcome();
                break;
        }
        echo '</form>';
    }

    function md_tab_content_help() {
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
                <li><p>Ahora ya puedes colocar el formulario / widget en tu blog. Para ello accede a Apariencia > Widgets en el panel de tu wordpress y arrastra a tu sidebar el widget de MDirector. Podrás configurar un título, una descripción / explicación para que aparezca encima de tu formulario y un enlace y checkbox para la política de privacidad o términos legales. ¡Ya está! tus usuarios pueden comenzar a suscribirse.</p></li>
                <li><p>Además del widget, puedes usar un shortcode para colocar el formulario de suscripción en cualquier página o post de tu blog. Para ello añade [mdirector_subscriptionbox] al contenido de cualquiera de tus páginas o posts.</p></li>
                <li><p>Puedes personalizar el aspecto de la plantilla de newsletter enviada a los usuarios modificando los ficheros de la carpeta /templates/ del directorio del plugin (Conocimientos de HTML / CSS requeridos).</p></li>
                <li><p>El plugin hace uso de WP Cron para realizar los envíos, el sistema de programación de tareas de Wordpress. Wp Cron funciona sólo cuando se producen visitas a la página, por lo que su ejecución en la hora planificada puede no ser precisa. <a href="http://stackoverflow.com/questions/12895706/when-does-wp-cron-php-run-in-wordpress" target="_blank">Más información aquí</a></p></li>
            </ol>
            ', self::MDIRECTOR_LANG_DOMAIN);
    }

    function md_tab_content_welcome() {
        echo '
            <div class="mdirector-welcome-box"><a href="https://signup.mdirector.com?lang=es" target="_blank"><img src="'
            . MDIRECTOR_NEWSLETTER_PLUGIN_URL
            . '/assets/mdirector-welcome.png"/></a>' .
            __('<h3>Wordpress + MDirector = Más visitas en tu blog</h3>
            <p>Integra tu Wordpress con MDirector, la herramienta de envíos de email marketing y sms más avanzada y sencilla del mercado.</p>
            <p>El plugin de MDirector permite a tus visitantes suscribirse a tus publicaciones, asignándoles una lista en MDirector según deseen recibir los posts diaria o semanalmente, y ocupándonos de que se envíen automáticamente tus mensajes a través de MDirector sin que tengas que preocuparte. Además todos tus suscriptores serán también administrables desde MDirector por lo que podrás hacer envíos desde la plataforma.</p>
            <p>Configura ya tu plugin y en unos minutos podrás empezar a recibir suscriptores a tu blog.</p>
            <p>Para usarlo tienes que crear una cuenta en MDirector, recuerda que tienes 5.000 emails gratis al mes sólo por registrarte.</p>
            <br class="clear">
            <p><a class="btn-orange" href="https://signup.mdirector.com?lang=es" target="_blank">Crear mi cuenta en MDirector</a> <a class="btn-blue" href="admin.php?page=mdirector-newsletter&tab=settings">Ya tengo cuenta en MDirector</a></p>
            <br class="clear">
            </div>
            ', self::MDIRECTOR_LANG_DOMAIN);
    }

    private function get_daily_list_id() {
        return get_option('mdirector_use_test_lists')
            ? get_option('mdirector_daily_test_list')
            : get_option('mdirector_daily_list');
    }

    private function get_weekly_list_id () {
        return get_option('mdirector_use_test_lists')
            ? get_option('mdirector_weekly_test_list')
            : get_option('mdirector_weekly_list');
    }

    function md_tab_content_settings() {
        update_option('mdirector-notice', 'true', true);
        $settings = get_option('mdirector_settings');
        $options = '';
        $options_days = '';
        $options_subject_weekly_dynamic = '';
        $options_subject_daily_dynamic = '';
        $daily_list = $this->get_daily_list_id();
        $weekly_list = $this->get_weekly_list_id();
        $mdirector_use_custom_lists = $settings['mdirector_use_custom_lists'];
        $mdirector_daily_custom_list = $settings['mdirector_daily_custom_list'];
        $mdirector_weekly_custom_list = $settings['mdirector_weekly_custom_list'];

        if (empty($settings['subject_type_weekly'])) {
            $settings['subject_type_weekly'] = 'fixed';
        }

        if (empty($settings['subject_type_daily'])) {
            $settings['subject_type_daily'] = 'fixed';
        }

        // frequency select
        foreach ($this->frequency_types as $key => $value) {
            $options .= '<option value="' . $key . '" '
                . (($settings['frequency'] === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        foreach ($this->frequency_days as $key => $value) {
            $options_days .= '<option value="' . $key . '" '
                . (($settings['frequency_day'] === strval($key)) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        foreach ($this->dynamic_subject_values as $key => $value) {
            $options_subject_weekly_dynamic .= '<option value="' . $key . '" '
                . (($settings['subject_dynamic_value_weekly'] === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
            $options_subject_daily_dynamic .= '<option value="' . $key . '" '
                . (($settings['subject_dynamic_value_daily'] === $key) ? 'selected' : '') . '>'
                . $value . '</option>';
        }

        echo '<div class="mdirector-settings-box">
            <h4>' . __('1. Datos de conexión API MDirector', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
            <p>'
            . __('Por favor, configura tus datos de conexión con la API. Puedes encontrarlos en las preferencias de tu cuenta de MDirector, pestaña <b>Información API</b>.',
                self::MDIRECTOR_LANG_DOMAIN) . '</p>
            <br class="clear">
            <label class="select">' . __('consumer-key', self::MDIRECTOR_LANG_DOMAIN) . '</label>
            <input id="api" name="api" type="text" value="' . $settings['api'] . '"/> <span class="help-block"></span>
            <br class="clear">
            <label class="select">' . __('consumer-secret', self::MDIRECTOR_LANG_DOMAIN) . '</label>
            <input id="secret" name="secret" type="text" value="' . $settings['secret'] . '"/> <span class="help-block"></span>
            <br class="clear">
            </div>
            ';

        if ($settings['api'] && $settings['secret']) {
            echo '
                <div class="mdirector-settings-box">
                    <h4>' . __('2. Listas de contactos personalizadas', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>Este plugin crea de forma automática dos listas de contactos en tu cuenta de MDirector para enviar las campañas diarias o semanales.
                    Si lo prefieres, puedes indicar a continuación alguna de tus listas ya existentes para utilizarlas en su lugar.</p>
                    <p class="notice-block">' . __('Las listas diaria y semanal que utilices deben existir y ser distintas.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <div class="md_cat_checkbox">
                        <input type="checkbox" name="mdirector_use_custom_lists" id="mdirector_use_custom_lists" value="yes" '
                        . (($mdirector_use_custom_lists === self::SETTINGS_OPTION_ON) ? 'checked' : '') . '>
                        ' . __('Utilizar listas personalizadas', self::MDIRECTOR_LANG_DOMAIN) . '
                    </div>
                    <br class="clear">
                    <label class="select">' . __('Lista para envíos diarios') . '</label>
                    <input id="mdirector_daily_custom_list" name="mdirector_daily_custom_list" type="text" value="' . $mdirector_daily_custom_list . '"/>
                    <br class="clear">
                    <label class="select">' . __('Lista para envíos semanales', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                    <input id="mdirector_weekly_custom_list" name="mdirector_weekly_custom_list" type="text" value="' . $mdirector_weekly_custom_list . '"/>
                    <br class="clear"><br class="clear">
                    <small>' . __('Actualmente, enviando a la lista diaria ', self::MDIRECTOR_LANG_DOMAIN) . $daily_list . ' ' . __('y a la lista semanal ', self::MDIRECTOR_LANG_DOMAIN) . $weekly_list . '.</small>
                </div>

                <div class="mdirector-settings-box">
                    <h4>' . __('3. Campo From', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>' . __('Configura el nombre que aparecerá en el campo <b>De:</b> de los correos que se envíen automáticamente desde el plugin.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <label class="select">' . __('Nombre del emisor', self::MDIRECTOR_LANG_DOMAIN) . '</label>

                    <input id="from_name" name="from_name" type="text" value="'. $settings['from_name'] . '"/>
                    <br class="clear">
                </div>
                <div class="mdirector-settings-box">
                    <h4>' . __('4. Enviar mensajes semanales', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>' . __('Activa los envíos de newsletters para los usuarios para los usuarios suscritos a la información semanal. Si esta opción está activada, todas las semanas se enviará un email automático a los usuarios suscritos a la lista, con un resumen de los posts publicados cada la semana.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <input type="checkbox" name="frequency_weekly" id="frequency_weekly" value="yes" ' . (($settings['frequency_weekly'] === self::SETTINGS_OPTION_ON) ? 'checked' : '') . '> ' . __('Activa los envíos semanales', self::MDIRECTOR_LANG_DOMAIN) . '<br class="clear">

                    <div id="weekly_extra" class="weekly_extra_selector" style="' . (($settings['frequency_weekly'] === self::SETTINGS_OPTION_ON) ? 'display: block' : '') . '">
                        <fieldset>
                            <legend>' . __('Escoja el tipo de asunto para sus correos:', self::MDIRECTOR_LANG_DOMAIN) . '</legend>
                            <div class="choice-block">
                                <input ' . ($settings['subject_type_weekly'] === 'fixed' ? 'checked' : '')
                . ' type="radio" name="subject_type_weekly" class="dynamic-choice" id="subject-type-fixed" value="fixed">
                                <label for="subject-type-fixed">' . __('Asunto fijo', self::MDIRECTOR_LANG_DOMAIN) .':</label><br>

                                <div class="subject-block subset '. ($settings['subject_type_weekly'] !== 'fixed' ? 'disabled' : '') .'">
                                    <input '. ($settings['subject_type_weekly'] !== 'fixed' ? 'readonly' : '')
                .' id="subject_weekly" class="field-selector" name="subject_weekly"
                                        type="text" value="'. $settings['subject_weekly'] . '"/>
                                    <br>
                                    <span class="help-block">' . __("Por ejemplo: 'Newsletter semanal de Tu Sitio'", self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                </div>
                            </div>
                            <div class="choice-block">
                                <input ' . ($settings['subject_type_weekly'] === 'dynamic' ? 'checked' : '')
                . ' type="radio" name="subject_type_weekly" class="dynamic-choice" id="subject-type-dynamic" value="dynamic">
                                <label for="subject-type-dynamic">' . __('Asunto dinámico', self::MDIRECTOR_LANG_DOMAIN) .'
                                    <small>' . __('(se forma automáticamente a partir de los siguientes campos)', self::MDIRECTOR_LANG_DOMAIN) . ':</small></label><br>

                                <div class="subject-block subset '. ($settings['subject_type_weekly'] === 'fixed' ? 'disabled' : '') .'">
                                    <div class="block-50">
                                        <input '. ($settings['subject_type_weekly'] === 'fixed' ? 'readonly' : '')
                . ' name="subject_dynamic_prefix_weekly" type="text" value="' . $settings['subject_dynamic_prefix_weekly']
                . '" placeholder="'. __('Prefijo', self::MDIRECTOR_LANG_DOMAIN) . '"/>
                                        <span class="help-block">' . __("Por ejemplo: 'Esta semana destacamos...'", self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                    </div>
                                    <div class="block-50">
                                        <select '. ($settings['subject_type_weekly'] === 'fixed' ? 'readonly' : '')
                . ' name="subject_dynamic_value_weekly">' . $options_subject_weekly_dynamic . '</select>
                                        <span class="help-block">' . __('Selecciona el contenido dinámico', self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <br class="clear">

                        <label class="select">' . __('Día de la semana', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <select name="frequency_day">' . $options_days . '</select>
                        <br class="clear">

                        <label class="select">' . __('Hora de envío', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <input id="hour_weekly" name="hour_weekly" type="text" class="timepicker" readonly value="'. $settings['hour_weekly'] . '"/>
                        <span class="help-block">' . __('NOTA: la hora actual en el servidor es ', self::MDIRECTOR_LANG_DOMAIN) . ' ' . date('H:i', current_time('timestamp', 0)) . '</span>
                        <br class="clear">
                        <p>' . __('Los usuarios suscritos a la lista semanal se almacenan en tu cuenta de MDirector, en la lista', self::MDIRECTOR_LANG_DOMAIN) . ' ' . get_option("mdirector_weekly_list_name") . '.</p>
                        <p>' . __('Los envíos semanales se guardan en la campaña', self::MDIRECTOR_LANG_DOMAIN) . ' ' . get_option("mdirector_weekly_campaign_name") . '.</p>
                    </div>

                </div>
                <div class="mdirector-settings-box">
                    <h4>' . __('5. Enviar mensajes diarios', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                    <p>'
                . __('Activa los envíos de newsletters para los usuarios suscritos a la información diaria. Si esta opción está activada, todos los días se enviará un email automático a los usuarios que hayan elegido recibir emails diarios, con un resumen de los posts publicados cada día.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</p>
                    <br class="clear">
                    <input type="checkbox" name="frequency_daily" class="dynamic-choice" id="frequency_daily" value="yes" ' . (($settings['frequency_daily'] === self::SETTINGS_OPTION_ON) ? 'checked' : '') . '> ' . __('Activa los envíos diarios', self::MDIRECTOR_LANG_DOMAIN) . '

                    <div id="daily_extra" class="weekly_extra_selector" style="' . (($settings['frequency_daily'] === self::SETTINGS_OPTION_ON) ? 'display: block' : '') . '">
                        <fieldset>
                            <legend>' . __('Escoja el tipo de asunto para sus correos:', self::MDIRECTOR_LANG_DOMAIN) . '</legend>
                            <div class="choice-block">
                                <input ' . ($settings['subject_type_daily'] === 'fixed' ? 'checked' : '')
                . ' type="radio" name="subject_type_daily" class="dynamic-choice" id="subject-type-daily-fixed" value="fixed">
                                <label for="subject-type-daily-fixed">' . __('Asunto fijo', self::MDIRECTOR_LANG_DOMAIN) .':</label><br>

                                <div class="subject-block subset '. ($settings['subject_type_daily'] !== 'fixed' ? 'disabled' : '') .'">
                                    <input '. ($settings['subject_type_daily'] !== 'fixed' ? 'readonly' : '')
                .' id="subject_daily" class="field-selector" name="subject_daily"
                                        type="text" value="'. $settings['subject_daily'] . '"/>
                                    <br>
                                    <span class="help-block">' . __("Por ejemplo: 'Newsletter diaria de Tu Sitio'", self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                </div>
                            </div>
                            <div class="choice-block">
                                <input ' . ($settings['subject_type_daily'] === 'dynamic' ? 'checked' : '')
                . ' type="radio" name="subject_type_daily" class="dynamic-choice" id="subject-type-dynamic" value="dynamic">
                                <label for="subject-type-dynamic">' . __('Asunto dinámico', self::MDIRECTOR_LANG_DOMAIN) .'
                                    <small>' . __('(se forma automáticamente a partir de los siguientes campos)', self::MDIRECTOR_LANG_DOMAIN) . ':</small></label><br>

                                <div class="subject-block subset '. ($settings['subject_type_daily'] === 'fixed' ? 'disabled' : '') .'">
                                     <div class="block-50">
                                        <input '. ($settings['subject_type_daily'] === 'fixed' ? 'readonly' : '')
                . ' name="subject_dynamic_prefix_daily" type="text" value="' . $settings['subject_dynamic_prefix_daily']
                . '" placeholder="'. __('Prefijo', self::MDIRECTOR_LANG_DOMAIN) . '"/>
                                        <span class="help-block">' . __("Por ejemplo: 'Hoy destacamos...'", self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                     </div>
                                     <div class="block-50">
                                     <select '. ($settings['subject_type_daily'] === 'fixed' ? 'readonly' : '')
                . ' name="subject_dynamic_value_daily">' . $options_subject_daily_dynamic . '</select>
                                     <span class="help-block">' . __('Selecciona el contenido dinámico', self::MDIRECTOR_LANG_DOMAIN) . '</span>
                                </div>
                            </div>
                        </fieldset>

                        <br class="clear">

                        <label class="select">' . __('Hora de envío', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                        <input id="hour_daily" name="hour_daily" type="text" class="timepicker" readonly value="' . $settings['hour_daily'] . '"/>
                        <span class="help-block">'. __('NOTA: la hora actual en el servidor es ', self::MDIRECTOR_LANG_DOMAIN) . ' ' . date('H:i', current_time('timestamp', 0)) . '</span>
                        <br class="clear">
                        <p>' . __('Los usuarios suscritos a la lista diaria se almacenan en tu cuenta de MDirector, en la lista', self::MDIRECTOR_LANG_DOMAIN) . ' ' . get_option("mdirector_daily_list_name") . '.</p>
                        <p>' . __('Los envíos diarios se guardan en la campaña', self::MDIRECTOR_LANG_DOMAIN) . ' ' . get_option("mdirector_daily_campaign_name") . '.</p>
                    </div>

                </div>
                <div class="mdirector-settings-box">
                <h4>' . __('6. Excluir categorías de posts en los envíos', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                . __('Si por cualquier motivo no te interesa que los posts de una o varias categorías no sean enviados en los mensajes automáticos. Elige la/s categoría/s que deseas excluir de los envíos',
                    self::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <div class="categories_list" id="categories_list" style="'
                . (($settings['exclude_categories'] === self::SETTINGS_OPTION_ON) ? 'display: block'
                    : '') . '">'
                . $this->mdirector_get_categories($settings['exclude_cats']) . '</div>
                <br class="clear">
                </div>
                <div class="mdirector-settings-box">
                <h4>' . __('7. Configuración de widget / shortcode', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
                <p>'
                . __('Configura el texto de aceptación y URL de la política de privacidad', self::MDIRECTOR_LANG_DOMAIN) . '
                </p>
                <br class="clear">
                <label class="select">' . __('Texto de aceptación de política', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                <input id="md_privacy_text" name="md_privacy_text" type="text" value="'
                . $settings['md_privacy_text'] . '"/> <span class="help-block"></span>
                <br class="clear">
                <label class="select">' . __('URL de la política', self::MDIRECTOR_LANG_DOMAIN) . '</label>
                <input id="md_privacy_text" name="md_privacy_url" type="text" value="'. $settings['md_privacy_url'] . '"/> <span class="help-block"></span>
                <br class="clear">
                </div>
                ';
        }
        echo '
                <p class="submit">
                    <input type="submit" class="button-primary" tabindex="21" name="cpt_submit" value="'
                        . __('Guardar cambios', self::MDIRECTOR_LANG_DOMAIN) . '">';

        if ($settings['api'] && $settings['secret']) {
            echo '<button type="submit" class="margin-left-10 button button-submit" tabindex="22" name="cpt_submit_test_now" value="test_now">'
                . __('Enviar ahora mismo', self::MDIRECTOR_LANG_DOMAIN) . '</button>';

            if (!empty(get_option('mdirector_use_test_lists'))) {
                echo '<small class="margin-left-15 text-red"><strong>' . __('NOTA:', self::MDIRECTOR_LANG_DOMAIN) . '</strong> '
                    . __('Recuerda que estás utilizando listas de pruebas para tu envío!', self::MDIRECTOR_LANG_DOMAIN) . '</small>';
            }
        }

        echo '
                </p>
                <input type="hidden" name="mdirector-newsletter-submit" value="Y" />
        ';
    }

    function md_tab_content_debug() {
        $mdirector_daily_test_list = get_option('mdirector_daily_test_list');
        $mdirector_weekly_test_list = get_option('mdirector_weekly_test_list');
        $mdirector_use_test_lists = get_option('mdirector_use_test_lists');
        $daily_list = $this->get_daily_list_id();
        $weekly_list = $this->get_weekly_list_id();

        echo '<div class="mdirector-settings-box">
            <h4>' . __('1. Listas de test', self::MDIRECTOR_LANG_DOMAIN) . '</h4>
            <p class="notice-block">' . __('Las listas de prueba que indiques a continuación deben existir y ser distintas.', self::MDIRECTOR_LANG_DOMAIN) . '</p>
            <br class="clear">
            <div class="md_cat_checkbox">
                <input type="checkbox" name="mdirector_use_test_lists" id="mdirector_use_test_lists" value="yes" '.( ($mdirector_use_test_lists === self::SETTINGS_OPTION_ON) ? 'checked' : '' ).'>
                ' . __('Utilizar listas de prueba', self::MDIRECTOR_LANG_DOMAIN) . '
            </div>
            <label class="select">' . __('Lista de Test Diaria') . '</label>
            <input id="mdirector_daily_test_list" name="mdirector_daily_test_list" type="text" value="' . $mdirector_daily_test_list . '"/>
            <br class="clear">
            <label class="select">' . __('Lista de Test Semanal', self::MDIRECTOR_LANG_DOMAIN) . '</label>
            <input id="mdirector_weekly_test_list" name="mdirector_weekly_test_list" type="text" value="' . $mdirector_weekly_test_list . '"/>
            <br class="clear"><br class="clear">
            <small>' . __('Actualmente, enviando a la lista diaria ', self::MDIRECTOR_LANG_DOMAIN) . $daily_list . ' ' . __('y a la lista semanal ', self::MDIRECTOR_LANG_DOMAIN) . $weekly_list . '.</small>
        </div>';

        echo '
            <p class="submit">
                <input type="submit" class="button-primary" tabindex="21" name="cpt_submit" value="' . __('Guardar cambios', self::MDIRECTOR_LANG_DOMAIN) . '">
                <button type="submit" class="button button-submit" tabindex="22" name="cpt_submit_test_now" value="test_now">' . __('Enviar prueba ahora mismo', self::MDIRECTOR_LANG_DOMAIN) . '</button>
            </p>
            <input type="hidden" name="mdirector-newsletter-debug-submit" value="Y" />
            <input type="hidden" name="tab" value="debug" />
        ';
    }

    /**
     * GET CATEGORIES
     *
     * @param null $selected
     * @return string
     */
    function mdirector_get_categories($selected = null) {
        $selected = ($selected) ? unserialize($selected) : [];

        $cat_args = ['parent' => 0, 'hide_empty' => false];
        $parent_categories = get_categories($cat_args);

        $no_of_categories = count($parent_categories);
        $result = '';

        if ($no_of_categories > 0) {
            foreach ($parent_categories as $parent_category) {
                $result .= '<div class="md_cat_checkbox"><input name="exclude_cats[]" type="checkbox" value="'
                    . $parent_category->term_id . '" '
                    . ((in_array($parent_category->term_id, $selected)
                        ? 'checked' : '')) . '> ' . $parent_category->name
                    . '</div>';

                $parent_id = $parent_category->term_id;
                $terms = get_categories([
                    'child_of' => $parent_id,
                    'hide_empty' => false
                ]);

                foreach ($terms as $term) {
                    $extra_indent = ($term->parent != $parent_category->term_id)
                        ? 'grandchild' : '';
                    $result .= '<div class="md_cat_checkbox child ' . $extra_indent . '"><input name="exclude_cats[]" type="checkbox" value="'
                        . $term->term_id . '" ' . ((in_array($term->term_id, $selected) ? 'checked' : '')) . '> ' . $term->name
                        . '</div>';
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
        wp_register_script('timepicker', MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'admin/js/timepicker.js', ['jquery']);
        wp_enqueue_script('timepicker');
        wp_register_script('mdirector-admin', MDIRECTOR_NEWSLETTER_PLUGIN_URL . 'admin/js/mdirector-newsletter-admin.js', ['jquery']);
        wp_enqueue_script('mdirector-admin');
    }

    /**
     * CHECK WP VERSION
     */
    function check_version() {
        if (version_compare(MDIRECTOR_CURRENT_WP_VERSION,
            MDIRECTOR_MIN_WP_VERSION, '<=')) {

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }

            echo '<div class="error" style="padding: 10px; margin: 20px 0 0 2px">'
                . __('Por favor actualiza tu versión de Wordpress para usar el plugin de MDirector. La mínima versión compatible es: ',
                    self::MDIRECTOR_LANG_DOMAIN) . MIN_WP_VERSION . '</div>';

            return false;
        }

        define('MDIRECTOR_VERSION_OK', true);

        return (MDIRECTOR_VERSION_OK);
    }

    function check_curl() {
        if (!(function_exists('curl_exec'))) {
            echo '<div class="error" style="padding: 10px; margin: 20px 0 0 2px">'
                . __('El plugin de MDirector hace uso de php-curl, por favor instala dicha librería para continuar.',
                    self::MDIRECTOR_LANG_DOMAIN) . '</div>';

            return false;
        }

        define('MDIRECTOR_CURL_OK', true);

        return MDIRECTOR_CURL_OK;
    }

    function check_api() {
        $settings = get_option('mdirector_settings');

        if ($settings['api'] && $settings['secret']) {
            $key = $settings['api'];
            $secret = $settings['secret'];
            $response = json_decode($response =
                Mdirector_Newsletter_Api::callAPI($key, $secret,
                    self::MDIRECTOR_API_LIST_ENDPOINT, 'GET'));
        } else {
            echo '<div class="error" style="padding: 10px; margin: 20px 0 0 2px">'
                . __('Para comenzar a usar el plugin de MDirector Newsletter configura los datos de conexión a la API de MDirector',
                    self::MDIRECTOR_LANG_DOMAIN) . '</div>';

            return false;
        }

        if ($response->code === '401') {
            $settings['api'] = '';
            $settings['secret'] = '';
            update_option("mdirector_settings", $settings);
            echo '<div class="error" style="padding: 10px; margin: 20px 0 0 2px">Hay problemas de conexión con MDirector, por favor vuelve a introducir los datos de conexión API</div>';
        } else {
            define('MDIRECTOR_API_OK', true);

            return (MDIRECTOR_API_OK);
        }
    }

    function mdirector_checks() {
        if ($this->check_version() && $this->check_curl() && $this->check_api()) {
            update_option('mdirector_active', self::SETTINGS_OPTION_ON);
        } else {
            update_option('mdirector_active', 'no');
        }
    }
}
