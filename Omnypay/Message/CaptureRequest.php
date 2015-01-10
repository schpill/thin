<?php
    namespace Thin\Omnipay\Message;

    /**
     * Stripe Capture Request
     */
    class CaptureRequest extends AbstractRequest
    {
        public function getData()
        {
            $this->validate('transactionReference');

            $data = [];

            if ($amount = $this->getAmountInteger()) {
                $data['amount'] = $amount;
            }

            return $data;
        }

        public function getEndpoint()
        {
            return $this->endpoint . '/charges/' . $this->getTransactionReference() . '/capture';
        }
    }
