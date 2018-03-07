<?php

/**
 * Fired during plugin activation
 *
 * @link       http://mdirector.com
 * @since      1.0.0
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/includes
 * @author     MDirector
 */
class Mdirector_Newsletter_Activator {
	public static function activate() {
        require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . 'includes/class-mdirector-newsletter-utils.php';
	    $MDirectorUtils = new Mdirector_Newsletter_Utils();
	    $options = get_option('mdirector_settings')
            ? get_option('mdirector_settings')
            : [];

	    foreach($MDirectorUtils->get_current_languages() as $language) {
	        $lang = $language['code'];
            $delivery = 'mdirector_' . $type . '_custom_list_' . $lang . '_active';
            $options[$delivery] = $MDirectorUtils::SETTINGS_OPTION_ON;
        }

        update_option('mdirector_settings', $options);
    }
}
