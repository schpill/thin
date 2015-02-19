<?php
    namespace Thin\Check;
    use Thin\Exception;
    use Thin\Arrays;

    class Email
    {
        // holds the socket connection resource
        private $socket;

        // holds all the domains we'll validate accounts on
        private $domains;

        private $domains_info = array();

        // connect timeout for each MTA attempted (seconds)
        private $connect_timeout = 10;

        // default username of sender
        private $from_user = 'user';

        // default host of sender
        private $from_domain = 'localhost';

        // the host we're currently connected to
        private $host = null;

        // holds all the debug info
        private $log = array();

        // array of validation results
        private $results = array();

        // states we can be in
        private $state = array(
            'helo' => false,
            'mail' => false,
            'rcpt' => false
        );

        // print stuff as it happens or not
        public $debug = false;

        // default smtp port
        public $connect_port = 25;

        /**
        * Are 'catch-all' accounts considered valid or not?
        * If not, the class checks for a "catch-all" and if it determines the box
        * has a "catch-all", sets all the emails on that domain as invalid.
        */
        public $catchall_is_valid = true;

        /**
        * Being unable to communicate with the remote MTA could mean an address
        * is invalid, but it might not, depending on your use case, set the
        * value appropriately.
        */
        public $no_comm_is_valid = false;

        // do we consider "greylisted" responses as valid or invalid addresses
        public $greylisted_considered_valid = true;

        /**
        * If on Windows (or other places that don't have getmxrr()), this is the
        * nameserver that will be used for MX querying.
        * Set as empty to use the DNS specified via your current network connection.
        * @see getmxrr()
        */
        // protected $mx_query_ns = 'dns1.t-com.hr';
        protected $mx_query_ns = '';

        /**
        * Timeout values for various commands (in seconds) per RFC 2821
        * @see expect()
        */
        protected $command_timeouts = array(
            'ehlo' => 300,
            'helo' => 300,
            'tls'  => 180, // start tls
            'mail' => 300, // mail from
            'rcpt' => 300, // rcpt to,
            'rset' => 3,
            'quit' => 300,
            'noop' => 300
        );

        // some constants
        const CRLF = "\r\n";

        // some smtp response codes
        const SMTP_CONNECT_SUCCESS = 220;
        const SMTP_QUIT_SUCCESS = 221;
        const SMTP_GENERIC_SUCCESS = 250;
        const SMTP_USER_NOT_LOCAL = 251;
        const SMTP_CANNOT_VRFY = 252;

        const SMTP_SERVICE_UNAVAILABLE = 421;

        // 450  Requested mail action not taken: mailbox unavailable (e.g.,
        // mailbox busy or temporarily blocked for policy reasons)
        const SMTP_MAIL_ACTION_NOT_TAKEN = 450;
        // 451  Requested action aborted: local error in processing
        const SMTP_MAIL_ACTION_ABORTED = 451;
        // 452  Requested action not taken: insufficient system storage
        const SMTP_REQUESTED_ACTION_NOT_TAKEN = 452;

        // 550  Requested action not taken: mailbox unavailable (e.g., mailbox
        // not found, no access, or command rejected for policy reasons)
        const SMTP_MBOX_UNAVAILABLE = 550;

        // 554  Seen this from hotmail MTAs, in response to RSET :(
        const SMTP_TRANSACTION_FAILED = 554;

        // list of codes considered as "greylisted"
        private $greylisted = array(
            self::SMTP_MAIL_ACTION_NOT_TAKEN,
            self::SMTP_MAIL_ACTION_ABORTED,
            self::SMTP_REQUESTED_ACTION_NOT_TAKEN
        );

        /**
        * Constructor.
        * @param $emails array  [optional] Array of emails to validate
        * @param $sender string [optional] Email address of the sender/validator
        */
        function __construct($emails = array(), $sender = '')
        {
            if (!empty($emails)) {
                $this->set_emails($emails);
            }
            if (!empty($sender)) {
                $this->set_sender($sender);
            }
        }

        /**
        * Disconnects from the SMTP server if needed.
        * @return void
        */
        public function __destruct()
        {
            $this->disconnect(false);
        }

        public function accepts_any_recipient($domain)
        {
            $test = 'catch-all-test-' . time();
            $accepted = $this->rcpt($test . '@' . $domain);
            if ($accepted) {
                // success on a non-existing address is a "catch-all"
                $this->domains_info[$domain]['catchall'] = true;
                return true;
            }
            // log the case in which we get disconnected
            // while trying to perform a catchall detect
            $this->noop();
            if (!($this->connected())) {
                $this->debug('Disconnected after trying a non-existing recipient on ' . $domain);
            }
            // nb: disconnects are considered as a non-catch-all case this way
            // this might not be true always
            return false;
        }

        /**
        * Performs validation of specified email addresses.
        * @param array $emails  Emails to validate (recipient emails)
        * @param string $sender Sender email address
        * @return array         List of emails and their results
        */
        public function validate($emails = array(), $sender = '')
        {

            $this->results = array();

            if (!empty($emails)) {
                $this->set_emails($emails);
            }
            if (!empty($sender)) {
                $this->set_sender($sender);
            }

            if (!Arrays::is($this->domains) || empty($this->domains)) {
                return $this->results;
            }

            // query the MTAs on each domain if we have them
            foreach ($this->domains as $domain => $users) {
                $mxs = array();

                // query the mx records for the current domain
                list($hosts, $weights) = $this->mx_query($domain);

                // sort out the MX priorities
                foreach ($hosts as $k => $host) {
                    $mxs[$host] = $weights[$k];
                }
                asort($mxs);

                // add the hostname itself with 0 weight (RFC 2821)
                $mxs[$domain] = 0;

                $this->debug('MX records (' . $domain . '): ' . print_r($mxs, true));
                $this->domains_info[$domain] = array();
                $this->domains_info[$domain]['users'] = $users;
                $this->domains_info[$domain]['mxs'] = $mxs;

                // try each host
                while (list($host) = each($mxs)) {
                    // try connecting to the remote host
                    try {
                        $this->connect($host);
                        if ($this->connected()) {
                            break;
                        }
                    } catch (Exception $e) {
                        // unable to connect to host, so these addresses are invalid?
                        $this->debug('Unable to connect. Exception caught: ' . $e->getMessage());
                        $this->set_domain_results($users, $domain, false);
                    }
                }

                // are we connected?
                if ($this->connected()) {
                    // say helo, and continue if we can talk
                    if ($this->helo()) {

                        // try issuing MAIL FROM
                        if (!($this->mail($this->from_user . '@' . $this->from_domain))) {
                            // MAIL FROM not accepted, we can't talk
                            $this->set_domain_results($users, $domain, $this->no_comm_is_valid);
                        }

                        /**
                        * if we're still connected, proceed (cause we might get
                        * disconnected, or banned, or greylisted temporarily etc.)
                        * see mail() for more
                        */
                        if ($this->connected()) {
                            $this->noop();

                            // Do a catch-all test for the domain always.
                            // This increases checking time for a domain slightly,
                            // but doesn't confuse users.
                            $is_catchall_domain = $this->accepts_any_recipient($domain);

                            // if a catchall domain is detected, and we consider
                            // accounts on such domains as invalid, mark all the
                            // users as invalid and move on
                            if ($is_catchall_domain) {
                                if (!($this->catchall_is_valid)) {
                                    $this->set_domain_results($users, $domain, $this->catchall_is_valid);
                                    continue;
                                }
                            }

                            // if we're still connected, try issuing rcpts
                            if ($this->connected()) {
                                $this->noop();
                                // rcpt to for each user
                                foreach ($users as $user) {
                                    $address = $user . '@' . $domain;
                                    $this->results[$address] = $this->rcpt($address);
                                    $this->noop();
                                }
                            }

                            // saying buh-bye if we're still connected, cause we're done here
                            if ($this->connected()) {
                                // issue a rset for all the things we just made the MTA do
                                $this->rset();
                                // kiss it goodbye
                                $this->disconnect();
                            }

                        }

                    } else {
                        // we didn't get a good response to helo and should be disconnected already
                        $this->set_domain_results($users, $domain, $this->no_comm_is_valid);

                    }

                }

            }

            return $this->get_results();

        }

        public function get_results($include_domains_info = true)
        {
            if ($include_domains_info) {
                $this->results['domains'] = $this->domains_info;
            }
            return $this->results;
        }

        /**
        * Helper to set results for all the users on a domain to a specific value
        * @param array $users   Array of users (usernames)
        * @param string $domain The domain
        * @param bool $val      Value to set
        */
        private function set_domain_results($users, $domain, $val)
        {
            if (!Arrays::is($users)) {
                $users = (array) $users;
            }
            foreach ($users as $user) {
                $this->results[$user . '@' . $domain] = $val;
            }
        }

        /**
        * Returns true if we're connected to an MTA
        * @return bool
        */
        protected function connected()
        {
            return is_resource($this->socket);
        }

        /**
        * Tries to connect to the specified host on the pre-configured port.
        * @param string $host   The host to connect to
        * @return void
        * @throws Exception
        * @throws Exception
        */
        protected function connect($host)
        {
            $remote_socket = $host . ':' . $this->connect_port;
            $errnum = 0;
            $errstr = '';
            $this->host = $remote_socket;
            // open connection
            $this->debug('Connecting to ' . $this->host);
            $this->socket = @stream_socket_client(
                $this->host,
                $errnum,
                $errstr,
                $this->connect_timeout,
                STREAM_CLIENT_CONNECT,
                stream_context_create(array())
            );
            // connected?
            if (!$this->connected()) {
                $this->debug('Connect failed: ' . $errstr . ', error number: ' . $errnum . ', host: ' . $this->host);
                throw new Exception('Cannot ' .
                'open a connection to remote host (' . $this->host . ')');
            }
            $result = stream_set_timeout($this->socket, $this->connect_timeout);
            if (!$result) {
                throw new Exception('Cannot set timeout');
            }
            $this->debug('Connected to ' . $this->host . ' successfully');
        }

        /**
        * Disconnects the currently connected MTA.
        * @param bool $quit Issue QUIT before closing the socket on our end.
        * @return void
        */
        protected function disconnect($quit = true)
        {
            if ($quit) {
                $this->quit();
            }
            if ($this->connected()) {
                $this->debug('Closing socket to ' . $this->host);
                fclose($this->socket);
            }
            $this->host = null;
            $this->reset_state();
        }

        /**
        * Resets internal state flags to defaults
        */
        private function reset_state()
        {
            $this->state['helo'] = false;
            $this->state['mail'] = false;
            $this->state['rcpt'] = false;
        }

        /**
        * Sends a HELO/EHLO sequence
        * @todo Implement TLS
        * @return bool  True if successful, false otherwise
        */
        protected function helo()
        {
            // don't try if it was already done
            if ($this->state['helo']) {
                return;
            }
            try {
                $this->expect(self::SMTP_CONNECT_SUCCESS, $this->command_timeouts['helo']);
                $this->ehlo();
                // session started
                $this->state['helo'] = true;
                // are we going for a TLS connection?
                /*
                if ($this->tls == true) {
                    // send STARTTLS, wait 3 minutes
                    $this->send('STARTTLS');
                    $this->expect(self::SMTP_CONNECT_SUCCESS, $this->command_timeouts['tls']);
                    $result = stream_socket_enable_crypto($this->socket, true,
                        STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    if (!$result) {
                        throw new Exception('Cannot enable TLS');
                    }
                }
                */
                return true;
            } catch (Exception $e) {
                // connected, but recieved an unexpected response, so disconnect
                $this->debug('Unexpected response after connecting: ' . $e->getMessage());
                $this->disconnect(false);
                return false;
            }
        }

        /**
        * Send EHLO or HELO, depending on what's supported by the remote host.
        * @return void
        */
        protected function ehlo()
        {
            try {
                // modern, timeout 5 minutes
                $this->send('EHLO ' . $this->from_domain);
                $this->expect(self::SMTP_GENERIC_SUCCESS, $this->command_timeouts['ehlo']);
            } catch (Exception $e) {
                // legacy, timeout 5 minutes
                $this->send('HELO ' . $this->from_domain);
                $this->expect(self::SMTP_GENERIC_SUCCESS, $this->command_timeouts['helo']);
            }
        }

        /**
        * Sends a MAIL FROM command to indicate the sender.
        * @param string $from   The "From:" address
        * @return bool          If MAIL FROM command was accepted or not
        * @throws Exception
        */
        protected function mail($from)
        {
            if (!$this->state['helo']) {
                throw new Exception('Need HELO before MAIL FROM');
            }
            // issue MAIL FROM, 5 minute timeout
            $this->send('MAIL FROM:<' . $from . '>');
            try {
                $this->expect(self::SMTP_GENERIC_SUCCESS, $this->command_timeouts['mail']);
                // set state flags
                $this->state['mail'] = true;
                $this->state['rcpt'] = false;
                return true;
            } catch (Exception $e) {
                // got something unexpected in response to MAIL FROM
                $this->debug("Unexpected response to MAIL FROM\n:" . $e->getMessage());
                // hotmail is know to do this, and is closing the connection
                // forcibly on their end, so I'm killing the socket here too
                $this->disconnect(false);
                return false;
            }
        }

        /**
        * Sends a RCPT TO command to indicate a recipient.
        * @param string $to Recipient's email address
        * @return bool      Is the recipient accepted
        * @throws Exception
        */
        protected function rcpt($to)
        {
            // need to have issued MAIL FROM first
            if (!$this->state['mail']) {
                throw new Exception('Need MAIL FROM before RCPT TO');
            }
            $is_valid = false;
            $expected_codes = array(
                self::SMTP_GENERIC_SUCCESS,
                self::SMTP_USER_NOT_LOCAL
            );
            if ($this->greylisted_considered_valid) {
                $expected_codes += $this->greylisted;
            }
            // issue RCPT TO, 5 minute timeout
            try {
                $this->send('RCPT TO:<' . $to . '>');
                // process the response
                try {
                    $this->expect($expected_codes, $this->command_timeouts['rcpt']);
                    $this->state['rcpt'] = true;
                    $is_valid = true;
                } catch (Exception $e) {
                    $this->debug('Unexpected response to RCPT TO: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                $this->debug('Sending RCPT TO failed: ' . $e->getMessage());
            }
            return $is_valid;
        }

        /**
        * Sends a RSET command and resets our internal state.
        * @return void
        */
        protected function rset()
        {
            $this->send('RSET');
            // MS ESMTP doesn't follow RFC according to ZF tracker, see [ZF-1377]
            $expected = array(
                self::SMTP_GENERIC_SUCCESS,
                self::SMTP_CONNECT_SUCCESS,
                // hotmail returns this o_O
                self::SMTP_TRANSACTION_FAILED
            );
            $this->expect($expected, $this->command_timeouts['rset']);
            $this->state['mail'] = false;
            $this->state['rcpt'] = false;
        }

        /**
        * Sends a QUIT command.
        * @return void
        */
        protected function quit()
        {
            // although RFC says QUIT can be issued at any time, we won't
            if ($this->state['helo']) {
                // [TODO] might need a try/catch here to cover some edge cases...
                $this->send('QUIT');
                $this->expect(self::SMTP_QUIT_SUCCESS, $this->command_timeouts['quit']);
            }
        }

        /**
        * Sends a NOOP command.
        * @return void
        */
        protected function noop()
        {
            $this->send('NOOP');
            $this->expect(self::SMTP_GENERIC_SUCCESS, $this->command_timeouts['noop']);
        }

        /**
        * Sends a command to the remote host.
        * @param string $cmd    The cmd to send
        * @return int|bool      Number of bytes written to the stream
        * @throws Exception
        * @throws Exception_Send_Failed
        */
        protected function send($cmd)
        {
            // must be connected
            if (!$this->connected()) {
                throw new Exception('No connection');
            }
            $this->debug('send>>>: ' . $cmd);
            // write the cmd to the connection stream
            $result = fwrite($this->socket, $cmd . self::CRLF);
            // did the send work?
            if ($result === false) {
                throw new Exception_Send_Failed('Send failed ' .
                'on: ' . $this->host);
            }
            return $result;
        }

        /**
        * Receives a response line from the remote host.
        * @param int $timeout Timeout in seconds
        * @return string
        * @throws Exception
        * @throws Exception_Socket_Timeout
        * @throws Exception
        */
        protected function recv($timeout = null)
        {
            if (!$this->connected()) {
                throw new Exception('No connection');
            }
            // timeout specified?
            if ($timeout !== null) {
                stream_set_timeout($this->socket, $timeout);
            }
            // retrieve response
            $line = fgets($this->socket, 1024);
            $this->debug('<<<recv: ' . $line);
            // have we timed out?
            $info = stream_get_meta_data($this->socket);
            if (!empty($info['timed_out'])) {
                throw new Exception('Timed out in recv');
            }
            // did we actually receive anything?
            if ($line === false) {
                throw new Exception('No response in recv');
            }
            return $line;
        }

        /**
        * Receives lines from the remote host and looks for expected response codes.
        * @param array $codes   A list of one or more expected response codes
        * @param int $timeout   The timeout for this individual command, if any
        * @return string        The last text message received
        * @throws Exception
        */
        protected function expect($codes, $timeout = null)
        {
            if (!Arrays::is($codes)) {
                $codes = (array) $codes;
            }
            $code = null;
            $text = '';
            try {
                $text = $line = $this->recv($timeout);
                while (preg_match("/^[0-9]+-/", $line)) {
                    $line = $this->recv($timeout);
                    $text .= $line;
                }
                sscanf($line, '%d%s', $code, $text);
                if ($code === null || !Arrays::in($code, $codes)) {
                    throw new Exception($line);
                }
            } catch (Exception $e) {
                // no response in expect() probably means that the
                // remote server forcibly closed the connection so
                // lets clean up on our end as well?
                $this->debug('No response in expect(): ' . $e->getMessage());
                $this->disconnect(false);
            }
            return $text;
        }

        /**
        * Parses an email string into respective user and domain parts and
        * returns those as an array.
        * @param string $email 'user@domain'
        * @return array        ['user', 'domain']
        */
        protected function parse_email($email)
        {
            $parts  = explode('@', $email);
            $domain = array_pop($parts);
            $user   = implode('@', $parts);
            return array($user, $domain);
        }

        /**
        * Sets the email addresses that should be validated.
        * @param array $emails  Array of emails to validate
        * @return void
        */
        public function set_emails($emails)
        {
            if (!Arrays::is($emails)) {
                $emails = (array) $emails;
            }
            $this->domains = array();
            foreach ($emails as $email) {
                list($user, $domain) = $this->parse_email($email);
                if (!isset($this->domains[$domain])) {
                    $this->domains[$domain] = array();
                }
                $this->domains[$domain][] = $user;
            }
        }

        /**
        * Sets the email address to use as the sender/validator.
        * @param string $email
        * @return void
        */
        public function set_sender($email)
        {
            $parts = $this->parse_email($email);
            $this->from_user = $parts[0];
            $this->from_domain = $parts[1];
        }

        /**
        * Queries the DNS server for MX entries of a certain domain.
        * @param string $domain The domain for which to retrieve MX records
        * @return array         MX hosts and their weights
        */
        protected function mx_query($domain)
        {
            $hosts = array();
            $weight = array();
            if (function_exists('getmxrr')) {
                getmxrr($domain, $hosts, $weight);
            } else {
                $this->getmxrr($domain, $hosts, $weight);
            }
            return array($hosts, $weight);
        }

        /**
        * Provides a windows replacement for the getmxrr function.
        * Params and behaviour is that of the regular getmxrr function.
        * @see  http://www.php.net/getmxrr
        */
        protected function getmxrr($hostname, &$mxhosts, &$mxweights)
        {
            if (!Arrays::is($mxhosts)) {
                $mxhosts = array();
            }
            if (!Arrays::is($mxweights)) {
                $mxweights = array();
            }
            if (empty($hostname)) {
                return;
            }
            $cmd = 'nslookup -type=MX ' . escapeshellarg($hostname);
            if (!empty($this->mx_query_ns)) {
                $cmd .= ' ' . escapeshellarg($this->mx_query_ns);
            }
            exec($cmd, $output);
            if (empty($output)) {
                return;
            }
            $i = -1;
            foreach ($output as $line) {
                $i++;
                if (preg_match("/^$hostname\tMX preference = ([0-9]+), mail exchanger = (.+)$/i", $line, $parts)) {
                    $mxweights[$i] = trim($parts[1]);
                    $mxhosts[$i] = trim($parts[2]);
                }
                if (preg_match('/responsible mail addr = (.+)$/i', $line, $parts)) {
                    $mxweights[$i] = $i;
                    $mxhosts[$i] = trim($parts[1]);
                }
            }
            return ($i != -1);
        }

        /**
        * Debug helper. If run in a CLI env, it just dumps $str on a new line,
        * else it prints stuff using <pre>.
        * @param string $str    The debug message
        * @return void
        */
        private function debug($str)
        {
            $this->log($str);
            if ($this->debug == true) {
                if (PHP_SAPI != 'cli') {
                    $str = '<br/><pre>' . htmlspecialchars($str) . '</pre>';
                }
                echo "\n" . $str;
            }
        }

        /**
        * Adds a message to the private log array
        * @param string $msg    The message to add
        */
        private function log($msg)
        {
            $this->log[] = $msg;
        }

        /**
        * Returns the log array
        */
        public function getLog()
        {
            return $this->log;
        }

        /**
        * Truncates the log array
        */
        public function clearLog()
        {
            $this->log = array();
        }

    }
