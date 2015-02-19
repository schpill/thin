<?php
    namespace Thin\Mail;

    class Pop
    {
        private $pop3Connection = array(
            "isValid"           => false,
            "socket"            => false,
            "errorCode"         => 0,
            "numberOfMessages"  => 0,
            "sizeOfMailbox"     => 0
        );

        private $serverAddress  = "";
        private $serverPort     = 0;
        private $authMethod     = "pop3";
        private $username       = "";
        private $password       = "";

        private $validAuthMethods = array("pop3", "apop");

        private $pop3ConnectionErrorCodes = array(
            "0" => "No error",
            "1" => "Unable to open the socket (wrong server address?)",
            "2" => "Login failed (wrong password?)",
            "3" => "Cannot stats the mailbox (service not available?)",
        );

        function __construct($serverAddress, $username, $password, $authMethod = "pop3", $serverPort = 110)
        {
            $this->serverAddress    = $serverAddress;
            $this->username         = $username;
            $this->password         = $password;
            $this->authMethod       = in_array($authMethod, $this->validAuthMethods) ? $authMethod : "pop3";
            $this->serverPort       = $serverPort;

            $this->pop3Connection   = $this->connect();
        }

        function __destruct()
        {
            if ($this->pop3Connection["isValid"]) {
                @fputs($this->pop3Connection["socket"], "quit\r\n");
                $rs = trim(@fgets($this->pop3Connection["socket"], 512));
                @fclose($this->pop3Connection["socket"]);
            }
        }

        public function getStatus()
        {
            return array(
                "isValid"           => $this->pop3Connection["isValid"],
                "errorCode"         => $this->pop3Connection["errorCode"],
                "errorMessage"      => $this->pop3ConnectionErrorCodes[$this->pop3Connection["errorCode"]],
                "numberOfMessages"  => $this->pop3Connection["numberOfMessages"],
                "sizeOfMailbox"     => $this->pop3Connection["sizeOfMailbox"],
            );
        }

        public function getMessage($firstMessage, $numberOfMessages = 1, $reverseOrder = false, $onlyHeaders = false)
        {
            $messages = array();

            if (is_numeric($firstMessage) && ($firstMessage > 0) && is_numeric($numberOfMessages) && is_bool($reverseOrder) && is_bool($onlyHeaders)) {
                if ($reverseOrder) {
                    $startingMessage = $this->pop3Connection["numberOfMessages"] - $firstMessage + 1;
                    $nextMessageIncrement = -1;
                } else {
                    $startingMessage = $firstMessage;
                    $nextMessageIncrement = 1;
                }

                for ($i = $startingMessage; $numberOfMessages > 0; $numberOfMessages--) {
                    if ($reverseOrder && ($i < 1)) break;
                    if (!$reverseOrder && ($i > $this->pop3Connection["numberOfMessages"])) break;

                    $messages[$i] = array();
                    @fputs($this->pop3Connection["socket"], "list $i\r\n");

                    $rs = explode(" ", trim(@fgets($this->pop3Connection["socket"], 512)));

                    if ($rs[0] == "+OK") {
                        $messages[$i]["pop3Id"] = $i;
                        $messages[$i]["size"]   = $rs[2];
                        $pop3Command = ($onlyHeaders) ? "top $i 0" : "retr $i";
                        @fputs($this->pop3Connection["socket"], "$pop3Command\r\n");
                        $rs = explode(" ", trim(@fgets($this->pop3Connection["socket"], 512)));

                        if ($rs[0] == "+OK") {
                            while ($rs = @fgets($this->pop3Connection["socket"], 512)) {
                                $rs = rtrim($rs);

                                if ($rs == ".") break;

                                $messages[$i]["data"] .= $rs . "\n";
                            }
                        }
                    }

                    $i += $nextMessageIncrement;
                }
            }

            return $messages;
        }

        public function deleteMessage($firstMessage, $numberOfMessages = 1)
        {
            $deletedMessages = array();

            if (is_numeric($firstMessage) && is_numeric($numberOfMessages)) {
                for ($i = $firstMessage; $numberOfMessages > 0; $i++, $numberOfMessages--) {
                    if ($i > $this->pop3Connection["numberOfMessages"]) {
                        break;
                    }

                    @fputs($this->pop3Connection["socket"], "dele $i\r\n");
                    $rs = explode(" ", trim(@fgets($this->pop3Connection["socket"], 512)));
                    $deletedMessages[$i] = $rs[0] == "+OK" ? true : false;
                }
            }

            if (is_string($numberOfMessages) && ($numberOfMessages == "list")) {
                $firstMessage = explode(",", $firstMessage);
                foreach ($firstMessage as $msgId) {
                    if ($i > $this->pop3Connection["numberOfMessages"]) {
                        continue;
                    }

                    @fputs($this->pop3Connection["socket"], "dele $msgId\r\n");
                    $rs = explode(" ", trim(@fgets($this->pop3Connection["socket"], 512)));
                    $deletedMessages[$msgId] = $rs[0] == "+OK" ? true : false;
                }
            }

            return $deletedMessages;
        }

        private function connect()
        {
            $errorCode          = 1;
            $isValid            = false;
            $numberOfMessages   = 0;
            $sizeOfMailbox      = 0;

            $socket = @fsockopen($this->serverAddress, $this->serverPort);

            if ($socket) {
                $rs = trim(@fgets($socket, 512));
                $errorCode = 2;

                switch ($this->authMethod) {
                    case "apop":
                        $rs     = explode(" ", $rs);
                        $secret = md5(trim($rs[count($rs) - 1]) . $this->password);
                        @fputs($socket, "apop $this->username $secret\r\n");
                        break;

                    case "pop3":
                    default:
                        @fputs($socket, "user $this->username\r\n");
                        $rs = trim(@fgets($socket, 512));
                        @fputs($socket, "pass $this->password\r\n");
                        break;
                }

                $rs = explode(" ", trim(@fgets($socket, 512)), 2);

                if ($rs[0] == "+OK") {
                    $errorCode = 3;

                    @fputs($socket, "stat\r\n");
                    $rs = explode(" ", trim(@fgets($socket, 512)));

                    if ($rs[0] == "+OK") {
                        $errorCode          = 0;
                        $isValid            = true;
                        $numberOfMessages   = $rs[1];
                        $sizeOfMailbox      = $rs[2];
                    }
                }
            }

            return array(
              "isValid"             => $isValid,
              "socket"              => $isValid ? $socket : false,
              "errorCode"           => $errorCode,
              "numberOfMessages"    => $numberOfMessages,
              "sizeOfMailbox"       => $sizeOfMailbox,
            );
        }
    }
