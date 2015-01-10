<?php
    namespace Thin\Omnipay\Message;

    /**
     * Stripe Fetch Transaction Request
     */
    class FetchTransactionRequest extends AbstractRequest
    {
        public function getData()
        {
            $this->validate('transactionReference');

            $data = [];

            return $data;
        }

        public function getEndpoint()
        {
            return $this->endpoint . '/charges/'.$this->getTransactionReference();
        }
    }
