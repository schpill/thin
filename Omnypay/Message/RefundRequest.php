<?php
    namespace Thin\Omnipay\Message;

    /**
     * Stripe Refund Request
     */
    class RefundRequest extends AbstractRequest
    {
        public function getData()
        {
            $this->validate('transactionReference', 'amount');

            $data = [];
            $data['amount'] = $this->getAmountInteger();

            return $data;
        }

        public function getEndpoint()
        {
            return $this->endpoint . '/charges/' . $this->getTransactionReference() . '/refund';
        }
    }
