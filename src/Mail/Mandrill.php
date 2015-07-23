<?php
	namespace Thin\Mail;

	use Thin\Config;
	use Swift_Transport;
	use GuzzleHttp\Client;
	use Swift_Mime_Message;
	use Swift_Events_EventListener;

	class Mandrill implements Swift_Transport
	{
		/**
		 * The Mandrill API key.
		 *
		 * @var string
		 */
		protected $key;

		/**
		 * Create a new Mandrill transport instance.
		 *
		 * @param  string  $key
		 * @return void
		 */
		public function __construct($key)
		{
			$this->key = $key;
		}

		/**
		 * {@inheritdoc}
		 */
		public function isStarted()
		{
			return true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function start()
		{
			return true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function stop()
		{
			return true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function send(Swift_Mime_Message $message, &$failedRecipients = null)
		{
			$message->setSender(
				Config::get('mailer.global.address', 'mailer@' . SITE_NAME . '.com'),
				Config::get('mailer.global.sender', SITE_NAME)
			);

			$headers = $message->getHeaders();
			$headers->addTextHeader('X-Mailer', Config::get('mailer.app.version', SITE_NAME . ' Mailer v1.2'));
			$headers->addTextHeader('X-MC-Track', Config::get('mailer.global.track', 'clicks_textonly'));

			$message = (string) $message;

			$message = str_replace('swift', SITE_NAME, $message);

			// $res = $client->post('https://mandrillapp.com/api/1.0/messages/send-raw.json', [
			// 	'body' 				=> [
			// 		'key' 			=> $this->key,
			// 		'raw_message' 	=> (string) $message,
			// 		'async'			=> true,
			// 	],
			// ]);

			$arguments = [
				'body' 				=> [
					'key' 			=> $this->key,
					'raw_message' 	=> (string) $message,
					'async'			=> true,
				],
			];

			$ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, 'https://mandrillapp.com/api/1.0/messages/send-raw.json');
	        curl_setopt($ch, CURLOPT_POST, 1);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
	            'key' => $this->key,
	            'raw_message' => (string) $message,
	            'async' => true
	        )));
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

		/**
		 * {@inheritdoc}
		 */
		public function registerPlugin(Swift_Events_EventListener $plugin)
		{
			//
		}

		/**
		 * Get a new HTTP client instance.
		 *
		 * @return \GuzzleHttp\Client
		 */
		protected function getHttpClient()
		{
			return new Client;
		}

		/**
		 * Get the API key being used by the transport.
		 *
		 * @return string
		 */
		public function getKey()
		{
			return $this->key;
		}

		/**
		 * Set the API key being used by the transport.
		 *
		 * @param  string  $key
		 * @return void
		 */
		public function setKey($key)
		{
			return $this->key = $key;
		}
	}
