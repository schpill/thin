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

        public static function payment(array $args)
        {
            extract($args);
            $data = '{
              "intent":"sale",
              "redirect_urls":{
                    "return_url":"'. $returnUrl . '",
                    "cancel_url":"' . $cancelUrl . '"
              },
              "payer":{
                "payment_method":"paypal"
              },
            "transactions": [
                {
                    "amount": {
                        "total": "300.00",
                        "currency": "CAD",
                        "details": {
                            "subtotal": "300.00",
                            "tax": "0.00",
                            "shipping": "0.00"
                        }
                    },
                    "description": "This is payment description.",
                    "item_list": {
                        "items":[
                            {
                                "quantity":"1",
                                "name":"One year VIP Card",
                                "price":"300.00",
                                "sku":"' . $id . '",
                                "currency":"CAD"
                            }
                        ]
                    }
                }
            ]
            }';

            $url = ('development' == $environment) ? 'https://api.sandbox.paypal.com/v1/payments/payment' : 'https://api.paypal.com/v1/payments/payment';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json'
            ));

            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            #curl_setopt($curl, CURLOPT_VERBOSE, TRUE);

            $data = json_decode(curl_exec($curl), true);

            return $data['links'][1]['href'];
        }

        public static function getToken($args)
        {
            extract($args);

            $url        = ('development' == $environment) ? 'https://api.sandbox.paypal.com/v1/oauth2/token' : 'https://api.paypal.com/v1/oauth2/token';
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
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
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
    }
