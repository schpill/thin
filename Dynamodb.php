<?php
    namespace Thin;
    class Dynamodb
    {
        protected $access_key_id;
        protected $secret_access_key;
        protected $security_token;
        protected $endpoint = 'dynamodb.eu-west-1.amazonaws.com';
        protected $use_https = true;
        protected $version = '20111205';
        protected $versionToken = '2011-06-15';
        protected $base_url = 'https://sts.amazonaws.com/';

        public $status_code;
        public $raw_body;
        public $total_time;

        const USER_AGENT = 'SimpleAmazonDynamoDB/1.0';


        public function __construct($access_key_id, $secret_access_key, $options = array())
        {
            $this->access_key_id = $access_key_id;
            $this->secret_access_key = $secret_access_key;
            foreach ($options as $k => $v) {
                $this->{$k} = $v;
            }

            $r = $this->callToken(
                'GetSessionToken',
                array(
                    'DurationSeconds' => 3600
                )
            );

            $this->access_key_id        = $r['GetSessionTokenResult']['Credentials']['AccessKeyId'];
            $this->secret_access_key    = $r['GetSessionTokenResult']['Credentials']['SecretAccessKey'];
            $this->security_token       = $r['GetSessionTokenResult']['Credentials']['SessionToken'];
        }

        public function getToken()
        {
            return $this->security_token;
        }

        public static function urlencode($string)
        {
            return str_replace('%7E', '~', rawurlencode($string));
        }

        public function callToken($operation, $params = array())
        {
            $params['AWSAccessKeyId'] = $this->access_key_id;
            $params['Version'] = $this->versionToken;
            $params['SignatureMethod'] = 'HmacSHA256';
            $params['SignatureVersion'] = 2;
            $params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
            $params['Action'] = $operation;

            ksort($params);

            $canonical_string = array();
            foreach ($params as $k => $v) {
                $canonical_string[] = self::urlencode($k).'='.self::urlencode($v);
            }
            $canonical_string = join('&', $canonical_string);

            $parsed_url = parse_url($this->base_url);
            $string_to_sign = "POST\n{$parsed_url['host']}\n{$parsed_url['path']}\n{$canonical_string}";
            $params['Signature'] = base64_encode(hash_hmac('sha256', $string_to_sign, $this->secret_access_key, true));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->base_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

            $this->raw_body = curl_exec($ch);
            $this->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->total_time = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 3);
            curl_close($ch);

            $xml = simplexml_load_string($this->raw_body);
            return json_decode(json_encode($xml), true);
        }

        public function call($operation, $params = array())
        {
            $headers                            = array();
            $headers['host']                    = $this->endpoint;
            $headers['x-amz-date']              = gmdate(DATE_RFC2822);
            $headers['x-amz-target']            = "DynamoDB_{$this->version}.{$operation}";
            $headers['x-amz-security-token']    = $this->security_token;
            $headers['content-type']            = 'application/x-amz-json-1.0';

            ksort($headers);
            $canonical_string = '';
            foreach ($headers as $k => $v) {
                $canonical_string .= "{$k}:{$v}\n";
            }
            $body = json_encode($params);
            if ($body === '[]') {
                $body = '{}';
            }
            $string_to_sign = "POST\n/\n\n{$canonical_string}\n{$body}";
            $hash_to_sign = hash('sha256', $string_to_sign, true);
            $signature = base64_encode(hash_hmac('sha256', $hash_to_sign, $this->secret_access_key, true));

            $auth_params = array();
            $auth_params['AWSAccessKeyId'] = $this->access_key_id;
            $auth_params['Algorithm'] = 'HmacSHA256';
            $auth_params['SignedHeaders'] = join(';', array_keys($headers));
            $auth_params['Signature'] = $signature;
            $canonical_auth_string = array();
            foreach ($auth_params as $k => $v) {
                $canonical_auth_string[] = "{$k}={$v}";
            }
            $canonical_auth_string = join(',', $canonical_auth_string);
            $canonical_auth_string = "AWS3 {$canonical_auth_string}";
            $headers['x-amzn-authorization'] = $canonical_auth_string;

            $url = ($this->use_https ? 'https://' : 'http://') . $this->endpoint . '/';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //curl_setopt($ch, CURLINFO_HEADER_OUT, true); // DEBUG:
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);

            $header_array = array();
            foreach ($headers as $k => $v) {
                $header_array[] = "{$k}: {$v}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

            $this->raw_body         = curl_exec($ch);
            $this->status_code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->total_time       = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 3);
            curl_close($ch);

            return json_decode($this->raw_body, true);
        }
    }
