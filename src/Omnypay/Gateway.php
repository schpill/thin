<?php
    namespace Thin\Omnipay;

    use Omnipay\Common\AbstractGateway;
    use Thin\Omnipay\Message\PurchaseRequest;
    use Thin\Omnipay\Message\RefundRequest;

    /**
     * Thin Gateway
     *
     * @link https://stripe.com/docs/api
     */
    class Gateway extends AbstractGateway
    {
        public function getName()
        {
            return 'Stripe';
        }

        public function getDefaultParameters()
        {
            return array(
                'apiKey' => '',
            );
        }

        public function getApiKey()
        {
            return $this->getParameter('apiKey');
        }

        public function setApiKey($value)
        {
            return $this->setParameter('apiKey', $value);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\AuthorizeRequest
         */
        public function authorize(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\AuthorizeRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\\Message\CaptureRequest
         */
        public function capture(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\CaptureRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\PurchaseRequest
         */
        public function purchase(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\PurchaseRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\RefundRequest
         */
        public function refund(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\RefundRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\FetchTransactionRequest
         */
        public function fetchTransaction(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\FetchTransactionRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\CreateCardRequest
         */
        public function createCard(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\CreateCardRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\UpdateCardRequest
         */
        public function updateCard(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\UpdateCardRequest', $parameters);
        }

        /**
         * @param array $parameters
         * @return \Thin\Omnipay\Message\DeleteCardRequest
         */
        public function deleteCard(array $parameters = [])
        {
            return $this->createRequest('\\Thin\\Omnipay\\Message\\DeleteCardRequest', $parameters);
        }
    }
