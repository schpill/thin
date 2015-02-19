<?php
    /**
     * Paypal class
     * @author      Gerald Plusquellec
     */
    namespace Thin;
    use PayPal\Rest\ApiContext;
    use PayPal\Api\Address;
    class Paypal
    {
        /**
         * Magic method for handling API methods.
         *
         * @since   PHP 5.3
         * @param   string  $method
         * @param   array   $args
         * @return  array
         */
        public static function __callStatic($method, $args)
        {
            // if production mode...
            if (Config::get('paypal.production_mode') === true) {
                // use production credentials
                $credentials = Config::get('paypal.production');
                // use production endpoint
                $endpoint = 'https://api-3t.paypal.com/nvp';
            } else {
                // use sandbox credentials
                $credentials = Config::get('paypal.sandbox');

                // use sandbox endpoint
                $endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
            }

            // build credentials
            $params = array(
                'VERSION'       => '74.0',
                'USER'          => $credentials['username'],
                'PWD'           => $credentials['password'],
                'SIGNATURE'     => $credentials['signature'],
                'METHOD'        => Inflector::camelize($method),
            );

            // build post data
            $fields = http_build_query($params + $args[0]);

            // curl request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $response = curl_exec($ch);

            // if errors...
            if (curl_errno($ch)) {
                #$errors = curl_error($ch);
                curl_close($ch);

                // return false
                return false;
            } else {
                curl_close($ch);

                // return array
                parse_str($response, $result);
                return $result;
            }
        }

        /**
         * Automatically verify Paypal IPN communications.
         *
         * @return  boolean
         */
        public static function ipn()
        {
            // only accept post data
            if (!count($_POST)) {
                return false;
            }

            // if production mode...
            if (Config::get('paypal.production_mode')) {
                // use production endpoint
                $endpoint = 'https://www.paypal.com/cgi-bin/webscr';
            } else {
                // use sandbox endpoint
                $endpoint = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
            }

            // build response
            $fields = http_build_query(array('cmd' => '_notify-validate') + Input::all());

            // curl request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // if errors...
            if (curl_errno($ch)) {
                #$errors = curl_error($ch);
                curl_close($ch);

                // return false
                return false;
            } else {
                // close connection
                curl_close($ch);

                // if success...
                if ($code === 200 and $response === 'VERIFIED') {
                    return true;
                } else {
                    return false;
                }
            }
        }

        public static function getUrlPaypalPayment(array $args)
        {
            extract($args);
            $token = static::getToken($clientId, $clientSecret, $environment);
            $intent = empty($intent) ? 'sale' : $intent;
            $method = empty($method) ? 'paypal' : $method;

            $data = '{
              "intent":"' . $intent . '",
              "redirect_urls":{
                    "return_url":"' . $returnUrl . '",
                    "cancel_url":"' . $cancelUrl . '"
              },
              "payer":{
                "payment_method":"' . $method . '"
              },
            "transactions": [
                {
                    "amount": {
                        "total": "' . $total . '",
                        "currency": "' . $currency . '",
                        "details": {
                            "subtotal": "' . $subtotal . '",
                            "tax": "' . $tax . '",
                            "shipping": "' . $shipping . '"
                        }
                    },
                    "description": "' . $description . '",
                    "item_list": { "items":[';
            $itemList = array();
            foreach ($items as $item) {
                $content = '{
                    "quantity":"' . $item['quantity'] . '",
                    "name":"' . $item['name'] . '",
                    "price":"' . $item['price'] . '",
                    "sku":"' . $item['id'] . '",
                    "currency":"' . $item['currency'] . '"
                }';
                array_push($itemList, $content);
            }
            $data .= implode(",\n", $itemList) . '  ]      }
                }
            ]
            }';

            $url = ('development' == $environment)
            ? 'https://api.sandbox.paypal.com/v1/payments/payment'
            : 'https://api.paypal.com/v1/payments/payment';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                array(
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json'
                )
            );
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            $data = json_decode(curl_exec($curl), true);

            return static::getPaypalPaymentInfos(
                array(
                    $data['links'][1]['href'],
                    $data['links'][2]['href']
                )
            );
        }

        public static function getToken($clientId, $clientSecret, $environment = 'development')
        {
            $url        = ('development' == $environment)
            ? 'https://api.sandbox.paypal.com/v1/oauth2/token'
            : 'https://api.paypal.com/v1/oauth2/token';

            $auth       = $clientId . ':' . $clientSecret;

            $ch         = curl_init();
            $postData   = "grant_type=client_credentials";

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_PORT , 443);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                    "Accept: application/json",
                    "Accept-Language: en_US",
                    "Content-length: " . Inflector::length($postData)
                )
            );
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $data = json_decode(curl_exec($ch), true);
            curl_close($ch);
            return $data['access_token'];
        }

        public static function getPaypalPaymentInfos($paypalUrl)
        {
            $url        = Arrays::first($paypalUrl);
            $execute    = Arrays::last($paypalUrl);
            $tab        = parse_url($url);
            parse_str($tab['query'], $infos);
            extract($infos);
            /* turl + oken + execute */
            return array('url' => $url, 'token' => $token, 'execute' => $execute);
        }

        public static function after(array $args)
        {
            $url = URLSITE . substr($_SERVER['REQUEST_URI'], 1, strlen($_SERVER['REQUEST_URI']));
            $tab = parse_url($url);
            parse_str($tab['query'], $query);
            $args += $query;

            if (isset($PayerID)) {
                return static::execPayment($args);
            }
            return false;
        }

        public static function execPayment(array $args)
        {
            extract($args);
            // $token = static::getToken(array('environment' => 'development', 'clientId' => 'AR1gYxBYuhVXGHInUsHgSXTZ_OBWj9AsGNPg--92OPZqLsD089GsFfeb8CHB', 'clientSecret' => 'EDh0XRCYD34dDH-n3ad6n-AzYOm3Ko_6AlcwUhMGrJG_5r9lMoKXqBR5hl-7'));
            // $token = static::getToken($args);
            $data = '{ "payer_id" : "' . $PayerID . '" }';

            $curl = curl_init($execute);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Authorization: Bearer ' . static::getToken($clientId, $clientSecret, $environment),
                    'Accept: application/json',
                    'Content-Type: application/json'
                )
            );
            $page = curl_exec($curl);
            return contain('approved', $page) || contain('pending', $page) ? true : false;
        }
    }
