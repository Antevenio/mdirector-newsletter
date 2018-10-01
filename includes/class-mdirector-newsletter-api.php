<?php

/**
 * MDirector API
 *
 * @package    Mdirector_Newsletter
 * @subpackage Mdirector_Newsletter/api
 * @author     MDirector
 */
class Mdirector_Newsletter_Api
{
    const OAUTH_PATH = MDIRECTOR_NEWSLETTER_PLUGIN_DIR . '/lib/oauth-php/library/';
    /**
     * @param null $key
     * @param null $secret
     * @param      $url
     * @param      $method
     * @param null $params
     *
     * @return string
     * @throws MDOAuthException2
     */
    public function callAPI(
        $key = null,
        $secret = null,
        $url,
        $method,
        $params = null
    ) {
        if (!class_exists('MDOAuthStore')) {
            require_once self::OAUTH_PATH . 'OAuthStore.php';

        }
        if (!class_exists('MDOAuthRequester')) {
            require_once self::OAUTH_PATH . 'OAuthRequester.php';
        }

        if ($key && $secret) {
            $options = [
                'consumer_key' => $key,
                'consumer_secret' => $secret
            ];

            MDOAuthStore::instance('2Leg', $options);

            try {
                $request = new MDOAuthRequester($url, $method, $params);
                $result = $request->doRequest();
                $response = $result['body'];

                return $response . "\n";
            } catch (MDOAuthException2 $e) {
                return $e->getMessage();
            }
        }
    }
}
