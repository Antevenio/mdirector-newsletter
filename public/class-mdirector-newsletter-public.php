<?php

/**
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/public
 * @author     MDirector
 */
class Mdirector_Newsletter_Public {
    /**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $mdirector_newsletter    The ID of this plugin.
	 */
	private $mdirector_newsletter;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
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
	 * @param    string    $mdirector_newsletter       The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct($mdirector_newsletter, $version) {
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR .
            'includes/class-mdirector-newsletter-widget.php';
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR .
            'includes/class-mdirector-newsletter-utils.php';

	    $mdirector_active = get_option('mdirector_active');
        $this->Mdirector_utils = new Mdirector_Newsletter_Utils();

        if ($mdirector_active === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
		    $this->Mdirector_Newsletter_Api = new Mdirector_Newsletter_Api();
		    $this->mdirector_newsletter = $mdirector_newsletter;
            $this->version = $version;

            // shortcode
            add_shortcode('mdirector_subscriptionbox', [$this, 'mdirector_subscriptionbox']);

            // Define ajaxurl for ajax calls
            add_action('wp_head', [$this, 'mdirector_ajaxurl']);

            // ajax calls
            add_action('wp_ajax_md_new', [$this, 'mdirector_ajax_new']);
            add_action('wp_ajax_nopriv_md_new', [$this, 'mdirector_ajax_new']);

            // cron jobs
            add_filter('cron_schedules', [$this, 'md_add_new_interval']);

            if (! wp_next_scheduled ( 'md_newsletter_build' )) {
                wp_schedule_event(time(), 'every_thirty_minutes', 'md_newsletter_build');
            }

            add_action('md_newsletter_build', [$this, 'md_event_cron']);

            register_deactivation_hook(MDIRECTOR_NEWSLETTER_PLUGIN_DIR .
                'mdirector-newsletter.php', [$this, 'md_cron_deactivation']);


        }
	}

    /**
     * @return string
     */
	public function mdirector_subscriptionbox() {
	    return $this->Mdirector_utils->get_register_for_html();
	}

    public function mdirector_ajaxurl() {
    	echo '
    	<script type="text/javascript">
		    var ajaxurl = \''.admin_url('admin-ajax.php').'\';
		</script>
    	';
     }

    /**
     * @throws MDOAuthException2
     */
    public function mdirector_ajax_new() {
        $mdirector_active = get_option('mdirector_active');
        $settings = get_option('mdirector_settings');
        $current_list = 'list';
        $current_language = $this->Mdirector_utils->get_current_lang();

        if ($mdirector_active === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
			$key = $settings['mdirector_api'];
			$secret = $settings['mdirector_secret'];
	        $target_list = 'mdirector_' . $_POST['list'] . '_' .
                $current_list . '_' . $current_language;
			$list = $settings[$target_list];

	        // Fallback to default language in case user language does not exist.
	        if (!$list) {
	            $target_list = 'mdirector_' . $_POST['list'] . '_' .
                    $current_list . '_' .
                    Mdirector_Newsletter_Utils::MDIRECTOR_DEFAULT_USER_LANG;
	            $list = $settings[$target_list];
            }

            if ($list) {
	        	$md_user_id = json_decode(
                    $this->Mdirector_Newsletter_Api->callAPI(
	        		    $key,
                        $secret,
                        Mdirector_Newsletter_Utils::MDIRECTOR_API_CONTACT_ENDPOINT,'POST',
	        			[
	        				'listId' 	=> $list,
	        				'email'		=> $_POST['email']
	        			]
	        		)
	        	);

	        	echo json_encode($md_user_id);
	        }
	    }

		wp_die();
    }

	/**
     * CRON JOBS
     * The scheduled hook is assigned in the constructor because
     * it has given problems in the register_activation_hook.
     */
    public function md_cron_deactivation() {
    	wp_clear_scheduled_hook('md_newsletter_build');
    }

    public function md_add_new_interval($schedules) {
        // add weekly and monthly intervals
    	$schedules['every_thirty_minutes'] = [
    		'interval' => 1800,
    		'display' => __('Every 30 minutes')
    	];

    	return $schedules;
    }

    /**
     * @throws MDOAuthException2
     */
    public function md_event_cron() {
        $mdirector_active = get_option('mdirector_active');
        $settings = get_option('mdirector_settings');

        if ($mdirector_active === Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
            $utils_instance = new Mdirector_Newsletter_Utils();
            if ($settings['mdirector_frequency_daily'] ===
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
                    $utils_instance->build_daily_mails();
            }

            if ($settings['mdirector_frequency_weekly'] ===
                Mdirector_Newsletter_Utils::SETTINGS_OPTION_ON) {
                    $utils_instance->build_weekly_mails();
            }
        }
    }

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
		    $this->mdirector_newsletter,
            plugin_dir_url(__FILE__) . 'css/mdirector-newsletter-public.css',
            [],
            $this->version,
            'all'
        );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
		    $this->mdirector_newsletter,
            plugin_dir_url(__FILE__) . 'js/mdirector-newsletter-public.js',
            ['jquery'],
            $this->version,
            false
        );
	}
}
