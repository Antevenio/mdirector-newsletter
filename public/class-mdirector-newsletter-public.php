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
    const SETTINGS_OPTION_ON = 'yes';

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $mdirector_newsletter       The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct($mdirector_newsletter, $version) {
		$mdirector_active = get_option('mdirector_active');

		if ($mdirector_active === self::SETTINGS_OPTION_ON) {
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
            register_activation_hook(MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'mdirector-newsletter.php', [$this, 'md_cron_activation']);
            register_deactivation_hook(MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'mdirector-newsletter.php', [$this, 'md_cron_deactivation']);
            add_action('md_daily_event', [$this, 'md_event_cron']);

            require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-widget.php';
            require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-utils.php';
        }
	}

	/**
	 * SHORTCODE
     */
	public function mdirector_subscriptionbox() {
		$mdirector_active = get_option('mdirector_active');
        $form = '';

        if ($mdirector_active === self::SETTINGS_OPTION_ON) {
			$select_frequency 	= '<div class="mdirector_sh_field"><select id="md_sh_frequency" name="md_sh_frequency">';
			$select_frequency 	.= '<option value="daily">' . __('Recibir newsletter diaria', 'mdirector-newsletter') . '</option>';
			$select_frequency 	.= '<option value="weekly">' . __('Recibir newsletter semanal', 'mdirector-newsletter') . '</option>';
			$select_frequency 	.= '</select></div>';

            $form = '
                <form name="mdirector_sh_suscription" class="mdirector_sh_suscription" id="mdirector_sh_suscription" method="POST">
                    <div class="mdirector_sh_field">
                        <input type="text" name="mdirector_sh_email" id="mdirector_sh_email" placeholder="'.__('Email','mdirector-newsletter').'">
                    </div>
                    ' . $select_frequency;
                    $settings = get_option('mdirector_settings');

                    $accept = ($settings['md_privacy_text'] !== '')
                        ? $settings['md_privacy_text']
                        :__('Acepto la política de privacidad','mdirector-newsletter');

                    $md_privacy_link = ($settings['md_privacy_url']!== '')
                        ? $settings['md_privacy_url'] :'#';

                    $form .= '<p class="mdirector_sh_accept"><input type="checkbox" name="mdirector_sh-accept"/><label for="mdirector_sh_accept"> <a href="'.$md_privacy_link.'" target="_blank">'.$accept.'</a></label></p>';
                    $form .= '<div class="mdirector_sh_field">
                        <button type="submit">'.__('Suscribirme', 'mdirector-newsletter').'</button>
                    </div>
                </form>
                <div class="md_ajax_loader md_sh"><img src="'.MDIRECTOR_NEWSLETTER_PLUGIN_URL.'assets/ajax-loader.gif'.'"></div>
            ';
		}

		return $form;
	}

	/**
     * ADD JS AJAXURL
     */
    public function mdirector_ajaxurl() {
    	echo '
    	<script type="text/javascript">
		var ajaxurl = \''.admin_url('admin-ajax.php').'\';
		</script>
    	';
     }

	 /**
     * AJAX calls
     */
    public function mdirector_ajax_new() {
        global $wpdb;
        $mdirector_active = get_option('mdirector_active');
        $settings = get_option( "mdirector_settings" );

        if ($mdirector_active === self::SETTINGS_OPTION_ON) {
			$key = $settings['api'];
			$secret = $settings['secret'];
	        $list = get_option('mdirector_' . $_POST['list'] . '_list');

            if ($list) {
	        	$md_user_id = json_decode(
	        		Mdirector_Newsletter_Api::callAPI($key,$secret,'http://www.mdirector.com/api_contact', 'POST',
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
     */
    public function md_cron_activation() {
    	wp_schedule_event(time(), 'every_five_minutes', 'md_daily_event');
    }

    public function md_cron_deactivation() {
    	wp_clear_scheduled_hook('md_daily_event');
    }

    public function md_add_new_interval($schedules) {
    	// add weekly and monthly intervals
    	$schedules['every_five_minutes'] = [
    		'interval' => 300,
    		'display' => __('Every Five minutes')
    	];

    	return $schedules;
    }

    /**
     * On the scheduled action hook, run the function.
     */
    public function md_event_cron() {
        $mdirector_active = get_option('mdirector_active');
        $settings = get_option('mdirector_settings');
        $Mdirector_utils = new Mdirector_Newsletter_Utils();

        if ($mdirector_active === self::SETTINGS_OPTION_ON) {
            if ($settings['frequency_daily'] === self::SETTINGS_OPTION_ON) {
                $Mdirector_utils->md_send_daily_mails($settings);
            }
            if ($settings['frequency_weekly'] === self::SETTINGS_OPTION_ON) {
                $Mdirector_utils->md_send_weekly_mails($settings);
            }
        }
    }

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style($this->mdirector_newsletter, plugin_dir_url(__FILE__) . 'css/mdirector-newsletter-public.css', [], $this->version, 'all');
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script($this->mdirector_newsletter, plugin_dir_url(__FILE__) . 'js/mdirector-newsletter-public.js', ['jquery'], $this->version, false);
	}
}
