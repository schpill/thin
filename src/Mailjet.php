<?php
    /**
     * Mailjet class
     * @author      Gerald Plusquellec
     */
    namespace Thin;

    include_once LIBRARIES_PATH . DS . 'Mailjet' . DS . 'php-mailjet.class-mailjet-0.1.php';

    class Mailjet
    {
        private static $mj;

        public static function instance(array $config)
        {
            extract($config);
            static::$mj = new \Mailjet($apiKey, $secretKey);
        }

        public static function sendTest($email, $campaign)
        {
            # Parameters
            $params = array(
                'method' => 'POST',
                'id' => $campaign,
                'email' => $email
            );

            # Call
            $response = static::$mj->messageTestCampaign($params);
        }

        public static function getStats($campaign)
        {
            $params = array(
                'id' => $campaign
            );

            # Call
            $response = static::$mj->messageStatistics($params);

            # Result
            $res = (array) $response->result;
            $stats = new Stats;
            return $stats->populate($res);
        }

        public static function sendCampaign($campaign)
        {
            # Parameters
            $params = array(
                'method' => 'POST',
                'id' => $campaign
            );

            # Call
            $response = static::$mj->messageSendCampaign($params);
            return $response;
        }
    }
