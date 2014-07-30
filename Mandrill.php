<?php
    namespace Thin;
    class Mandrill
    {
    	//public static function __callStatic($method, $args)
    	public static function request($method, $arguments = array())
    	{

    		// load api key
    		$apiKey = Bootstrap::$bag['config']->getMailer()->getPassword();

    		// determine endpoint
    		$endPoint = 'https://mandrillapp.com/api/1.0/' . $method . '.json';

    		// build payload
    		$arguments['key'] = $apiKey;

    		// setup curl request
    		$ch = curl_init();
    		curl_setopt($ch, CURLOPT_URL, $endPoint);
    		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    		curl_setopt($ch, CURLOPT_POST, true);
    		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($arguments));
    		$response = curl_exec($ch);

    		// catch errors
    		if (curl_errno($ch)) {
    			#$errors = curl_error($ch);
    			curl_close($ch);

    			// return false
    			return false;
    		} else {
    			curl_close($ch);

    			// return array
    			return json_decode($response, true);
    		}
    	}

        public static function send($arguments = array())
        {
            $response = static::request('messages/send', $arguments);
            if (false !== $response && Arrays::is($response)) {
                $response = Arrays::first($response);
                return $response['status'] == 'sent' ? true : $response['status'];
            }
            return false;
        }
    }
