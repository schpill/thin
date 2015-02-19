<?php
    namespace Thin\Mail;

    class Parser
    {
        private $message = array();
        private $parentPartId = "";

        private $mailParts = array();

        private $defaultCharset = "iso-8859-1";

        private $contentTypes = array("text", "image", "video", "audio", "application", "multipart", "message", "related");
        private $contentTransferEncodings = array("7bit", "8bit", "binary", "quoted-printable", "base64", "delivery-status", "rfc822", "report");
        private $contentDispositions = array("inline", "attachment");

        public function __construct($message, $parentPartId = "")
        {
            $this->message      = $message;
            $this->parentPartId = $parentPartId;
        }

        public function get()
        {
            $parsedMessage = array();

            $message                = $this->splitMessage($this->message);
            $message["header"]      = $this->parseHeader($message["header"]);

            $message["contentType"] = $this->parseContentType(
                $message["header"]["content-type"],
                $message["header"]["content-transfer-encoding"],
                $message["header"]["content-disposition"],
                $message["header"]["content-description"]
            );

            $message["charset"]     = $this->getCharset($message["header"]["content-type"]);

            $partId = md5(uniqid("", true));
            $parsedMessage[$partId] = array(
                "parentPartId"    => $this->parentPartId,
                "header"          => $message["header"],
                "contentType"     => $message["contentType"],
                "charset"         => $message["charset"],
            );

            if ($this->isCompositeMessage($message["contentType"])) {
                $parsedMessage[$partId]["body"] = false;

                if ($this->isMultipartMessage($message["contentType"]["type"])) {
                    $message["bodyParts"] = $this->splitBodyParts($message["body"], $message["contentType"]["boundary"]);
                } else {
                    $message["bodyParts"][] = $message["body"];
                }

                foreach ($message["bodyParts"] as $bodyPart) {
                    $childParts = new self($bodyPart, $partId);
                    $parsedMessage = array_merge($parsedMessage, $childParts->get());
                }
            } else {
                $parsedMessage[$partId]["body"] = $this->decodeBody($message["body"], $message["contentType"]);
            }

            return $parsedMessage;
        }

        private function splitMessage($messageLines)
        {
            $message = [];

            list($message["header"], $message["body"]) = explode("\n\n", $messageLines, 2);
            $message["header"] = explode("\n", $message["header"]);

            return $message;
        }

        private function parseHeader($headerLines)
        {
            $messageHeaders = array();

            //Parse header lines
            foreach ($headerLines as $hl) {
                if ($hl == ltrim($hl)) {
                    //No white-space char at the beginnin of the line => new header line
                    $hl = explode(":", $hl, 2);
                    $lastParsedHeaderName = strtolower(trim($hl[0]));
                    $messageHeaders[$lastParsedHeaderName][] = trim($hl[1]);
                    $lastParsedHeaderValueIndex = count($messageHeaders[$lastParsedHeaderName]) - 1;
                } else {
                    //White-space char at the beginnin of the line => folded header line => attach to the last parsed header name
                    $messageHeaders[$lastParsedHeaderName][$lastParsedHeaderValueIndex] .= " " . trim($hl);
                }
            }

            //Implode headers content
            foreach ($messageHeaders as &$mh) {
                $mh = implode("\n", &$mh);
            }

            //Decode encoded headers
            foreach ($messageHeaders as $headName=>$headValue) {
                $numberOfEncodedParts = preg_match_all("/=\?(.*)\?(.*)\?(.*)\?=/U", $headValue, $encodedParts);

                if ($numberOfEncodedParts > 0) {
                    $transEncodedParts = array();

                    for ($i=0; $i<$numberOfEncodedParts; $i++) {
                        $transEncodedParts[$encodedParts[0][$i]] = $this->decodeHeaderPart(
                            $encodedParts[3][$i],
                            $encodedParts[2][$i]
                        );
                    }

                    $messageHeaders[$headName] = strtr($messageHeaders[$headName], $transEncodedParts);
                }
            }

            return $messageHeaders;
        }

        private function decodeHeaderPart($encodedText, $encodeMethod)
        {
            switch (strtolower($encodeMethod)) {
                case "b":
                    $decodedText = base64_decode($encodedText);
                    break;
                case "q":
                default:
                    $decodedText = quoted_printable_decode($encodedText);
                    $decodedText = str_replace("_", " ", $decodedText);
                    break;
            }

            return $decodedText;
        }

        private function parseContentType(
            $contentType,
            $contentTransferEncoding,
            $contentDisposition,
            $contentDescription
        )
        {
            $contentTypeData = array();

            //Analyzing content-type
            $contentType                = explode(";", $contentType);
            $typeAndSubtype             = explode("/", $contentType[0]);
            $contentTypeData["subtype"] = strtolower(trim($typeAndSubtype[1]));
            $contentTypeData["type"]    = strtolower(trim($typeAndSubtype[0]));

            if (!in_array($contentTypeData["type"], $this->contentTypes)) {
                $contentTypeData["type"] = $contentTypeData["subtype"] = "unknown";
            }

            for ($i = (count($contentType) - 1); $i > 0; $i--) {
                $ct = explode("=", $contentType[$i], 2);
                $ct[1] = trim($ct[1]);

                if ($ct[1][0] == "\"") $ct[1] = substr($ct[1], 1);
                if ($ct[1][(strlen($ct[1]) - 1)] == "\"") $ct[1] = substr($ct[1], 0, -1);
                if (strtolower(trim($ct[0])) != "type") $contentTypeData[strtolower(trim($ct[0]))] = $ct[1];
            }

            //Analyzing transfer-encoding
            $contentTypeData["transferEncoding"] = strtolower(trim($contentTransferEncoding));
            if (!in_array($contentTypeData["transferEncoding"], $this->contentTransferEncodings)) {
                $contentTypeData["transferEncoding"] = "unknown";
            }

            //Analyzing content-disposition
            $contentDisposition = explode(";", $contentDisposition);
            $contentTypeData["disposition"] = strtolower(trim($contentDisposition[0]));

            if (!in_array($contentTypeData["disposition"], $this->contentDispositions)) {
                $contentTypeData["disposition"] = "unknown";
            }

            for ($i = (count($contentDisposition) - 1); $i > 0; $i--) {
                $cd = explode("=", $contentDisposition[$i], 2);
                $cd[1] = trim($cd[1]);

                if ($cd[1][0] == "\"") $cd[1] = substr($cd[1], 1);
                if ($cd[1][(strlen($cd[1]) - 1)] == "\"") $cd[1] = substr($cd[1], 0, -1);

                $contentTypeData[strtolower(trim($cd[0]))] = $cd[1];
            }

            //Analyzing content-description
            $contentTypeData["description"] = trim($contentDescription);

            return $contentTypeData;
        }

        private function isCompositeMessage($contentType)
        {
            return $contentType["type"] == "multipart" ? true : false;
        }

        private function isMultipartMessage($contentType)
        {
            return $contentType == "multipart" ? true : false;
        }

        private function splitBodyParts($bodyLines, $boundary)
        {
            $bps = explode("--" . $boundary, $bodyLines);

            foreach ($bps as $bp) {
                $bodyParts[] = trim($bp);
            }

            array_pop($bodyParts);

            return $bodyParts;
        }

        private function decodeBody($bodyLines, $contentType)
        {
            switch ($contentType["transferEncoding"]) {
                case "quoted-printable":
                    $bodyContent = quoted_printable_decode($bodyLines);
                    break;
                case "base64":
                    $bodyContent = base64_decode(trim(str_replace("\n", "", $bodyLines), $bodyContent));
                    break;
                case "7bit":
                case "8bit":
                case "binary":
                default:
                    $bodyContent = $bodyLines;
                    break;
            }

            return $bodyContent;
        }

        private function getCharset($contentType)
        {
            $mailCharset = $this->defaultCharset;
            preg_match("/(.*)charset=(.*)/", $contentType, $tmpCharset);
            $tmpCharset = explode(";", $tmpCharset[2]);

            if ($tmpCharset[0]) {
                $mailCharset = $tmpCharset[0];
            }

            return $mailCharset;
        }
    }
