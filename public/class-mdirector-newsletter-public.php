<?php
//'public' is a reserved keyword, which you can't use for namespacing :_(
//namespace MDirectorNewsletter\public;

/**
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/public
 */

use MDirectorNewsletter\includes\Mdirector_Newsletter_Api;
use MDirectorNewsletter\includes\Mdirector_Newsletter_Utils;

/**
 * The public-facing functionality of the plugin.
 *
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/public
 * @author     MDirector
 */
class Mdirector_Newsletter_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $mdirectorNewsletter The ID of this plugin.
     */
    private $mdirectorNewsletter;

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
    private $MdirectorUtils;

    /**
     * @var Mdirector_Newsletter_Api
     */
    private $MdirectorNewsletterApi;

    /**
     * Mdirector_Newsletter_Public constructor.
     *
     * @param $mdirectorNewsletter
     * @param $version
     *
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function __construct($mdirectorNewsletter, $version)
    {
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR .
            'includes/class-mdirector-newsletter-widget.php';
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR .
            'includes/class-mdirector-newsletter-utils.php';

        $mdirectorActive = get_option('mdirector_active');
        $this->MdirectorUtils = new Mdirector_Newsletter_Utils();

        if ($mdirectorActive
            === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $this->MdirectorNewsletterApi = new Mdirector_Newsletter_Api();
            $this->mdirectorNewsletter = $mdirectorNewsletter;
            $this->version = $version;

            // shortcode
            add_shortcode('mdirectorSubscriptionBox', [
                $this,
                'mdirectorSubscriptionBox'
            ]);

            // Define ajaxurl for ajax calls
            add_action('wp_head', [$this, 'mdirectorAjaxUrl']);

            // ajax calls
            add_action('wp_ajax_md_new', [$this, 'mdirectorAjaxNew']);
            add_action('wp_ajax_nopriv_md_new', [$this, 'mdirectorAjaxNew']);

            // cron jobs
            add_filter('cron_schedules', [$this, 'mdAddNewInterval']);

            if (!wp_next_scheduled('md_newsletter_build')) {
                wp_schedule_event(time(), 'every_thirty_minutes',
                    'md_newsletter_build');
            }

            add_action('md_newsletter_build', [$this, 'mdEventCron']);

            register_deactivation_hook(MDIRECTOR_NEWSLETTER_PLUGIN_DIR .
                'mdirector-newsletter.php', [$this, 'mdCronDeactivation']);
        }
    }

    /**
     * @return bool|string
     * @throws Throwable
     */
    public function mdirectorSubscriptionBox()
    {
        return $this->MdirectorUtils->getRegisterFormHTML();
    }

    public function mdirectorAjaxUrl()
    {
        echo '<script type="text/javascript">' .
            'var ajaxurl = "' . admin_url('admin-ajax.php') . '";' .
            '</script>';
    }

    /**
     * @throws MDOAuthException2
     */
    public function mdirectorAjaxNew()
    {
        $mdirectorActive = get_option('mdirector_active');
        $settings = get_option('mdirector_settings');
        $currentList = 'list';
        $currentLanguage = $this->MdirectorUtils->getCurrentLang();

        if ($mdirectorActive
            === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $key = $settings['mdirector_api'];
            $secret = $settings['mdirector_secret'];
            $targetList = 'mdirector_' . $_POST['list'] . '_' .
                'custom_' . $currentList . '_' . $currentLanguage;

            if (empty($settings[$targetList])) {
                $targetList = 'mdirector_' . $_POST['list'] . '_' .
                    $currentList . '_' . $currentLanguage;
            }

            $list = $settings[$targetList];

            // Fallback to default language in case user language does not exist.
            if (!$list) {
                $targetList = 'mdirector_' . $_POST['list'] . '_' .
                    $currentList . '_' .
                    $this->MdirectorUtils->getCurrentLang();
                $list = $settings[$targetList];
            }

            if ($list) {
                $mdUserId = json_decode(
                    $this->MdirectorNewsletterApi->callAPI(
                        $key, $secret,
                        Mdirector_Newsletter_Utils::MDIRECTOR_API_CONTACT_ENDPOINT,
                        'POST', [
                            'listId' => $list,
                            'email' => $_POST['email']
                        ]
                    )
                );

                echo json_encode($mdUserId);
            }
        }

        wp_die();
    }

    /**
     * CRON JOBS
     * The scheduled hook is assigned in the constructor because
     * it has given problems in the register_activation_hook.
     */
    public function mdCronDeactivation()
    {
        wp_clear_scheduled_hook('md_newsletter_build');
    }

    public function mdAddNewInterval($schedules)
    {
        $schedules['every_thirty_minutes'] = [
            'interval' => 1800,
            'display' => __('Every 30 minutes')
        ];

        return $schedules;
    }

    /**
     * @throws MDOAuthException2
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function mdEventCron()
    {
        $mdirectorActive = get_option('mdirector_active');
        $settings = get_option('mdirector_settings');

        if ($mdirectorActive
            === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $utilsInstance = new Mdirector_Newsletter_Utils();
            if ($settings['mdirector_frequency_daily']
                === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
                $utilsInstance->buildDailyMails();
            }

            if ($settings['mdirector_frequency_weekly']
                === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
                $utilsInstance->buildWeeklyMails();
            }
        }
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    {
        wp_enqueue_style(
            $this->mdirectorNewsletter, plugin_dir_url(__FILE__)
            . 'css/mdirector-newsletter-public.css', [], $this->version, 'all'
        );
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    {
        wp_register_script('mdirector-public', MDIRECTOR_NEWSLETTER_PLUGIN_URL .
            'public/js/mdirector-newsletter-public.js', ['jquery']);

        $translatedStrings = [
            'WIDGET_SCRIPT_SUCCESS' =>
                $this->t('WIDGET-SCRIPT-SUCCESS'),
            'WIDGET_SCRIPT_EMAIL_VALIDATION' =>
                $this->t('WIDGET-SCRIPT-EMAIL-VALIDATION'),
            'WIDGET_SCRIPT_EMAIL_TEXT' =>
                $this->t('WIDGET-SCRIPT-EMAIL-TEXT'),
            'WIDGET_SCRIPT_POLICY_VALIDATION' =>
                $this->t('WIDGET-SCRIPT-POLICY-VALIDATION'),
            'WIDGET_SCRIPT_EMAIL_ALREADY_REGISTERED' =>
                $this->t('WIDGET-SCRIPT-EMAIL-ALREADY-REGISTERED'),
            'WIDGET_SCRIPT_GENERAL_ERROR' =>
                $this->t('WIDGET-SCRIPT-GENERAL-ERROR')
        ];

        wp_localize_script('mdirector-public', 'LOCALES', $translatedStrings);
        wp_enqueue_script('mdirector-public');
    }

    /**
     * Translate a string using land domain
     * @param $string
     *
     * @return string|void
     */
    private function t($string)
    {
        return __($string, Mdirector_Newsletter_Utils::MDIRECTOR_LANG_DOMAIN);
    }
}
