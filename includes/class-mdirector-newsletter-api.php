<?php

/**
 * MDirector API
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/api
 * @author     MDirector
 */

class Mdirector_Newsletter_Api {
	public function callAPI($key=NULL, $secret=NULL,$url, $method, $params=NULL){
        if (!class_exists('MDOAuthStore')) {
            require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . "/lib/oauth-php/library/OAuthStore.php";

        }
        if (!class_exists('MDOAuthRequester')) {
            require_once MDIRECTOR_NEWSLETTER_PLUGIN_DIR . "/lib/oauth-php/library/OAuthRequester.php";
        }

        if ($key && $secret) {
			$options = array( 'consumer_key' => $key, 'consumer_secret' => $secret );
			MDOAuthStore::instance("2Leg", $options );
            try {
                $request = new MDOAuthRequester($url, $method, $params);
                $result = $request->doRequest();
                $response = $result['body'];
                return $response . "\n";
            } catch (MDOAuthException2 $e) {
                die(print_r($e, true));
                return $e->getMessage();
            }
		}
	}
}
